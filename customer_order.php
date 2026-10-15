<?php
/**
 * KopiKita ERP - Customer Self-Order Checkout Endpoint
 * Processes customer digital orders, queue numbering, BOM raw material stock deduction, and loyalty points
 */

header('Content-Type: application/json');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metode request tidak valid.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || empty($data['items'])) {
    echo json_encode(['success' => false, 'error' => 'Keranjang pesanan masih kosong.']);
    exit;
}

try {
    $db = getDb();
    $db->beginTransaction();

    $outletId = !empty($data['outlet_id']) ? (int)$data['outlet_id'] : getCurrentOutletId();
    $customerName = !empty($data['customer_name']) ? trim($data['customer_name']) : 'Pelanggan KopiKita';
    $customerPhone = !empty($data['customer_phone']) ? trim($data['customer_phone']) : '';
    $orderType = !empty($data['order_type']) ? trim($data['order_type']) : 'Dine In';
    $tableNo = !empty($data['table_no']) ? trim($data['table_no']) : null;
    $paymentMethod = !empty($data['payment_method']) ? trim($data['payment_method']) : 'QRIS';
    $notes = !empty($data['notes']) ? trim($data['notes']) : '';

    $subtotal = (float)($data['subtotal'] ?? 0);
    $discount = (float)($data['discount'] ?? 0);
    $tax = (float)($data['tax'] ?? 0);
    $totalAmount = (float)($data['total_amount'] ?? 0);

    // Check if customer exists by phone or ID to apply member points
    $customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
    if (!$customerId && !empty($customerPhone)) {
        $existingCust = dbFetchOne("SELECT id, tier FROM customers WHERE phone = ? LIMIT 1", [$customerPhone]);
        if ($existingCust) {
            $customerId = (int)$existingCust['id'];
        } else {
            // Auto-register member on first self-order
            $custCode = 'CST-' . rand(1000, 9999);
            $customerId = dbInsert('customers', [
                'code' => $custCode,
                'name' => $customerName,
                'phone' => $customerPhone,
                'tier' => 'Bronze',
                'points' => 0,
                'total_spent' => 0
            ]);
        }
    }

    $invoiceNo = generateInvoiceNo();

    // Generate Queue Number (e.g. A-01, A-02 for today)
    $today = date('Y-m-d');
    $todayOrdersCount = dbFetchOne("SELECT COUNT(*) as cnt FROM orders WHERE date(created_at) = ?", [$today])['cnt'];
    $queueNo = 'A-' . str_pad(((int)$todayOrdersCount + 1), 2, '0', STR_PAD_LEFT);

    $fullNotes = "Self-Order Customer [Antrean {$queueNo}]" . ($tableNo ? " Meja #{$tableNo}" : "") . ($notes ? " - Catatan: {$notes}" : "");

    // 1. Insert into orders table
    $orderId = dbInsert('orders', [
        'invoice_no' => $invoiceNo,
        'outlet_id' => $outletId,
        'customer_id' => $customerId,
        'customer_name' => $customerName . " ({$queueNo})",
        'cashier_name' => 'Self-Order Portal',
        'order_type' => $orderType,
        'table_no' => $tableNo,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'tax' => $tax,
        'service_charge' => 0,
        'total_amount' => $totalAmount,
        'payment_method' => $paymentMethod,
        'payment_status' => ($paymentMethod === 'Kasir') ? 'Pending' : 'Paid',
        'cash_given' => $totalAmount,
        'change_amount' => 0,
        'notes' => $fullNotes
    ]);

    // 2. Insert order items & calculate total COGS
    $totalCOGS = 0;
    $itemsList = [];

    foreach ($data['items'] as $item) {
        $productId = (int)$item['id'];
        $qty = (int)$item['qty'];
        $price = (float)$item['price'];
        $itemSubtotal = $price * $qty;
        $customizations = !empty($item['customizations']) ? trim($item['customizations']) : '';
        $itemNotes = !empty($item['notes']) ? trim($item['notes']) : '';
        
        $combinedNotes = trim($customizations . ($itemNotes ? " ({$itemNotes})" : ""));

        // Calculate COGS
        $itemCOGS = calculateProductCOGS($productId);
        $totalCOGS += ($itemCOGS * $qty);

        dbInsert('order_items', [
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_name' => $item['name'],
            'price' => $price,
            'quantity' => $qty,
            'subtotal' => $itemSubtotal,
            'modifiers' => $customizations,
            'notes' => $itemNotes
        ]);

        // 3. Deduct stock automatically based on BOM Recipe
        deductRecipeStock($productId, $qty, $invoiceNo, $outletId);

        $itemsList[] = [
            'name' => $item['name'],
            'qty' => $qty,
            'price' => $price,
            'subtotal' => $itemSubtotal,
            'customizations' => $combinedNotes
        ];
    }

    // 4. Update customer loyalty points (+1 point per Rp 10.000)
    if ($customerId) {
        $earnedPoints = (int)floor($totalAmount / 10000);
        $db->prepare("
            UPDATE customers 
            SET points = points + ?, total_spent = total_spent + ? 
            WHERE id = ?
        ")->execute([$earnedPoints, $totalAmount, $customerId]);
    }

    // 5. Update cash register non_cash_sales / cash_sales
    if ($paymentMethod === 'Kasir') {
        // Pending payment at counter
    } elseif ($paymentMethod === 'Cash') {
        $db->prepare("UPDATE cash_registers SET cash_sales = cash_sales + ? WHERE outlet_id = ? AND status = 'Open'")
           ->execute([$totalAmount, $outletId]);
    } else {
        $db->prepare("UPDATE cash_registers SET non_cash_sales = non_cash_sales + ? WHERE outlet_id = ? AND status = 'Open'")
           ->execute([$totalAmount, $outletId]);
    }

    // 6. Record Accounting Journal
    $cashAccount = ($paymentMethod === 'Cash') ? 1101 : 1103;
    recordJournalEntry(
        "Self-Order Customer " . $invoiceNo . " ({$customerName} - Antrean {$queueNo})",
        $invoiceNo,
        $cashAccount,
        4101,
        $totalAmount
    );

    if ($totalCOGS > 0) {
        recordJournalEntry(
            "Beban HPP Self-Order " . $invoiceNo,
            $invoiceNo,
            5101,
            1301,
            $totalCOGS
        );
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'queue_no' => $queueNo,
        'invoice_no' => $invoiceNo,
        'order_id' => $orderId,
        'customer_name' => $customerName,
        'order_type' => $orderType,
        'table_no' => $tableNo,
        'payment_method' => $paymentMethod,
        'total_amount' => $totalAmount,
        'total_amount_formatted' => formatRupiah($totalAmount),
        'items' => $itemsList,
        'created_at' => formatDate(date('Y-m-d H:i:s'), 'datetime'),
        'message' => "Pesanan berhasil dibuat! Nomor antrean Anda adalah {$queueNo}."
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error' => 'Gagal memproses pesanan: ' . $e->getMessage()
    ]);
}
