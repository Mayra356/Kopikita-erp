<?php
/**
 * KopiKita ERP - POS Order Processing Endpoint
 * Handles checkout, BOM raw material stock deduction, accounting journal, and thermal receipt generation
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
    echo json_encode(['success' => false, 'error' => 'Data keranjang kosong atau tidak valid.']);
    exit;
}

try {
    $db = getDb();
    $db->beginTransaction();

    $outletId = !empty($data['outlet_id']) ? (int)$data['outlet_id'] : getCurrentOutletId();
    $customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
    $customerName = !empty($data['customer_name']) ? trim($data['customer_name']) : 'Walk-in Guest';
    $orderType = !empty($data['order_type']) ? trim($data['order_type']) : 'Dine In';
    $tableNo = !empty($data['table_no']) ? trim($data['table_no']) : null;
    $paymentMethod = !empty($data['payment_method']) ? trim($data['payment_method']) : 'Cash';
    $notes = !empty($data['notes']) ? trim($data['notes']) : '';

    $subtotal = (float)($data['subtotal'] ?? 0);
    $discount = (float)($data['discount'] ?? 0);
    $tax = (float)($data['tax'] ?? 0);
    $totalAmount = (float)($data['total_amount'] ?? 0);
    $cashGiven = (float)($data['cash_given'] ?? $totalAmount);
    $changeAmount = max(0, $cashGiven - $totalAmount);

    // Get active cashier name if available
    $cashierName = 'Barista Kasir';
    $cashRegister = dbFetchOne("SELECT cashier_name FROM cash_registers WHERE outlet_id = ? AND status = 'Open' ORDER BY id DESC LIMIT 1", [$outletId]);
    if ($cashRegister && !empty($cashRegister['cashier_name'])) {
        $cashierName = $cashRegister['cashier_name'];
    }

    $invoiceNo = generateInvoiceNo();

    // 1. Insert into orders table
    $orderId = dbInsert('orders', [
        'invoice_no' => $invoiceNo,
        'outlet_id' => $outletId,
        'customer_id' => $customerId,
        'customer_name' => $customerName,
        'cashier_name' => $cashierName,
        'order_type' => $orderType,
        'table_no' => $tableNo,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'tax' => $tax,
        'service_charge' => 0,
        'total_amount' => $totalAmount,
        'payment_method' => $paymentMethod,
        'payment_status' => 'Paid',
        'cash_given' => $cashGiven,
        'change_amount' => $changeAmount,
        'notes' => $notes
    ]);

    // 2. Insert order items & calculate total COGS
    $totalCOGS = 0;
    $itemsListForReceipt = [];

    foreach ($data['items'] as $item) {
        $productId = (int)$item['id'];
        $qty = (int)$item['qty'];
        $price = (float)$item['price'];
        $itemSubtotal = $price * $qty;
        $modifiers = !empty($item['modifiers']) ? trim($item['modifiers']) : '';
        $itemNotes = !empty($item['notes']) ? trim($item['notes']) : '';

        // Calculate COGS for this product
        $itemCOGS = calculateProductCOGS($productId);
        $totalCOGS += ($itemCOGS * $qty);

        dbInsert('order_items', [
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_name' => $item['name'],
            'price' => $price,
            'quantity' => $qty,
            'subtotal' => $itemSubtotal,
            'modifiers' => $modifiers,
            'notes' => $itemNotes
        ]);

        // 3. Deduct stock automatically based on BOM Recipe
        deductRecipeStock($productId, $qty, $invoiceNo, $outletId);

        $itemsListForReceipt[] = [
            'name' => $item['name'],
            'qty' => $qty,
            'price' => $price,
            'subtotal' => $itemSubtotal,
            'notes' => $itemNotes
        ];
    }

    // 4. Update customer loyalty points & spend if member
    if ($customerId) {
        $earnedPoints = (int)floor($totalAmount / 10000); // 1 point per 10k IDR
        $db->prepare("
            UPDATE customers 
            SET points = points + ?, total_spent = total_spent + ? 
            WHERE id = ?
        ")->execute([$earnedPoints, $totalAmount, $customerId]);
    }

    // 5. Update cash register totals if open
    if ($paymentMethod === 'Cash') {
        $db->prepare("UPDATE cash_registers SET cash_sales = cash_sales + ? WHERE outlet_id = ? AND status = 'Open'")
           ->execute([$totalAmount, $outletId]);
    } else {
        $db->prepare("UPDATE cash_registers SET non_cash_sales = non_cash_sales + ? WHERE outlet_id = ? AND status = 'Open'")
           ->execute([$totalAmount, $outletId]);
    }

    // 6. Record Accounting Journal Entry (Double-Entry Bookkeeping)
    // Debit: Kas Kasir (1101) or Settlement QRIS/EDC (1103)
    // Credit: Pendapatan Penjualan Minuman & Kopi (4101)
    $cashAccount = ($paymentMethod === 'Cash') ? 1101 : 1103;
    recordJournalEntry(
        "Penjualan POS " . $invoiceNo . " (" . $customerName . ")",
        $invoiceNo,
        $cashAccount,
        4101,
        $totalAmount
    );

    // Debit: Cost of Goods Sold / HPP Bahan Baku (5101)
    // Credit: Persediaan Bahan Baku & Packaging (1301)
    if ($totalCOGS > 0) {
        recordJournalEntry(
            "Beban HPP Penjualan " . $invoiceNo,
            $invoiceNo,
            5101,
            1301,
            $totalCOGS
        );
    }

    $db->commit();

    // 7. Generate Thermal Receipt HTML Preview
    $outlet = getCurrentOutlet();
    $receiptFooter = getAppSetting('receipt_footer', "Terima kasih sudah berkunjung ke KopiKita!\nFollow IG: @kopikita.id");
    $nowFormatted = formatDate(date('Y-m-d H:i:s'), 'datetime');

    ob_start();
    ?>
    <div class="thermal-receipt" id="printableReceipt">
        <div class="receipt-header text-center">
            <h3 class="store-name">KOPIKITA</h3>
            <div class="store-sub"><?php echo htmlspecialchars($outlet['name']); ?></div>
            <div class="store-address"><?php echo htmlspecialchars($outlet['address']); ?></div>
            <div class="store-phone">Telp: <?php echo htmlspecialchars($outlet['phone']); ?></div>
        </div>

        <div class="receipt-divider">--------------------------------</div>

        <div class="receipt-meta">
            <div><span>No. Invoice:</span> <strong><?php echo htmlspecialchars($invoiceNo); ?></strong></div>
            <div><span>Tanggal:</span> <span><?php echo $nowFormatted; ?></span></div>
            <div><span>Kasir:</span> <span><?php echo htmlspecialchars($cashierName); ?></span></div>
            <div><span>Pelanggan:</span> <span><?php echo htmlspecialchars($customerName); ?></span></div>
            <div><span>Tipe / Meja:</span> <span><?php echo htmlspecialchars($orderType); ?><?php echo $tableNo ? " (Meja: {$tableNo})" : ""; ?></span></div>
        </div>

        <div class="receipt-divider">--------------------------------</div>

        <table class="receipt-items">
            <thead>
                <tr>
                    <th style="text-align: left;">Menu</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itemsListForReceipt as $it): ?>
                    <tr>
                        <td colspan="3" style="font-weight: 600; padding-top: 4px;"><?php echo htmlspecialchars($it['name']); ?></td>
                    </tr>
                    <tr>
                        <td style="font-size: 0.8rem; color: #555; padding-left: 8px;">
                            @ <?php echo formatRupiah($it['price'], false); ?>
                            <?php if (!empty($it['notes'])): ?>
                                <br><em>*<?php echo htmlspecialchars($it['notes']); ?></em>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center; font-size: 0.85rem; vertical-align: top;">x<?php echo $it['qty']; ?></td>
                        <td style="text-align: right; font-weight: 600; vertical-align: top;"><?php echo formatRupiah($it['subtotal'], false); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="receipt-divider">--------------------------------</div>

        <div class="receipt-summary">
            <div class="d-flex justify-content-between">
                <span>Subtotal:</span>
                <span><?php echo formatRupiah($subtotal); ?></span>
            </div>
            <?php if ($discount > 0): ?>
                <div class="d-flex justify-content-between text-danger">
                    <span>Diskon Member:</span>
                    <span>-<?php echo formatRupiah($discount); ?></span>
                </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between">
                <span>Pajak Restoran (PB1 10%):</span>
                <span><?php echo formatRupiah($tax); ?></span>
            </div>
            <div class="d-flex justify-content-between grand-total-row mt-2 pt-2 border-top">
                <strong>TOTAL BAYAR:</strong>
                <strong style="font-size: 1.1rem;"><?php echo formatRupiah($totalAmount); ?></strong>
            </div>
            <div class="d-flex justify-content-between mt-2 text-muted">
                <span>Metode:</span>
                <span><strong><?php echo htmlspecialchars($paymentMethod); ?></strong></span>
            </div>
            <?php if ($paymentMethod === 'Cash'): ?>
                <div class="d-flex justify-content-between">
                    <span>Tunai Diterima:</span>
                    <span><?php echo formatRupiah($cashGiven); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Kembalian:</span>
                    <span><?php echo formatRupiah($changeAmount); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="receipt-divider">--------------------------------</div>

        <div class="receipt-footer text-center">
            <p><?php echo nl2br(htmlspecialchars($receiptFooter)); ?></p>
            <div style="font-size: 0.75rem; color: #888; margin-top: 6px;">Powered by KopiKita ERP v2.4</div>
        </div>
    </div>
    <?php
    $receiptHtml = ob_get_clean();

    echo json_encode([
        'success' => true,
        'invoice_no' => $invoiceNo,
        'order_id' => $orderId,
        'receipt_html' => $receiptHtml,
        'message' => 'Transaksi berhasil disimpan dan stok bahan otomatis diperbarui.'
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
