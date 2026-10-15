<?php
/**
 * KopiKita ERP - Helper & Utility Functions
 */

require_once __DIR__ . '/db.php';

/**
 * Format number to Indonesian Rupiah
 */
function formatRupiah($amount, $withPrefix = true) {
    $num = is_numeric($amount) ? (float)$amount : 0;
    $formatted = number_format($num, 0, ',', '.');
    return $withPrefix ? 'Rp ' . $formatted : $formatted;
}

/**
 * Format date to Indonesian localized display
 */
function formatDate($datetime, $format = 'datetime') {
    if (!$datetime) return '-';
    $time = strtotime($datetime);
    if (!$time) return $datetime;

    $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $d = date('j', $time);
    $m = (int)date('n', $time);
    $y = date('Y', $time);
    $his = date('H:i', $time);

    switch ($format) {
        case 'date':
            return "{$d} {$bulanIndo[$m]} {$y}";
        case 'short':
            return date('d/m/Y', $time);
        case 'time':
            return $his;
        case 'datetime':
        default:
            return "{$d} " . substr($bulanIndo[$m], 0, 3) . " {$y}, {$his}";
    }
}

/**
 * Flash message helpers
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $msg = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $msg;
    }
    return null;
}

/**
 * Active outlet session management
 */
function getCurrentOutletId() {
    if (isset($_GET['outlet_id'])) {
        $_SESSION['active_outlet_id'] = (int)$_GET['outlet_id'];
    }
    return $_SESSION['active_outlet_id'] ?? 1;
}

function getCurrentOutlet() {
    $outletId = getCurrentOutletId();
    $outlet = dbFetchOne("SELECT * FROM outlets WHERE id = ?", [$outletId]);
    if (!$outlet) {
        $outlet = dbFetchOne("SELECT * FROM outlets ORDER BY id ASC LIMIT 1");
    }
    return $outlet;
}

function getAllOutlets() {
    return dbFetchAll("SELECT * FROM outlets WHERE is_active = 1 ORDER BY id ASC");
}

/**
 * Current user profile and role management
 */
function getCurrentUser() {
    if (isset($_SESSION['user_profile']) && !empty($_SESSION['user_profile']['name'])) {
        return $_SESSION['user_profile'];
    }

    $managerName = getAppSetting('manager_name', 'Kevin Wijaya');
    $managerRole = getAppSetting('manager_role', 'Store Manager');
    $activeRole = $_SESSION['active_role'] ?? 'manager';

    $initials = '';
    $parts = explode(' ', trim($managerName));
    foreach ($parts as $p) {
        if (!empty($p)) $initials .= strtoupper(substr($p, 0, 1));
        if (strlen($initials) >= 2) break;
    }
    if (empty($initials)) $initials = 'KW';

    $user = [
        'name' => $managerName,
        'role' => $managerRole,
        'initials' => $initials,
        'active_role' => $activeRole
    ];

    $_SESSION['user_profile'] = $user;
    return $user;
}

function setCurrentUser($name, $role, $activeRole = 'manager') {
    $initials = '';
    $parts = explode(' ', trim($name));
    foreach ($parts as $p) {
        if (!empty($p)) $initials .= strtoupper(substr($p, 0, 1));
        if (strlen($initials) >= 2) break;
    }
    if (empty($initials)) $initials = 'KW';

    $user = [
        'name' => $name,
        'role' => $role,
        'initials' => $initials,
        'active_role' => $activeRole
    ];

    $_SESSION['user_profile'] = $user;
    $_SESSION['active_role'] = $activeRole;

    try {
        $db = getDb();
        $stmt = $db->prepare("INSERT OR REPLACE INTO app_settings (key_name, val_text) VALUES (?, ?)");
        $stmt->execute(['manager_name', $name]);
        $stmt->execute(['manager_role', $role]);
    } catch (Exception $e) {
        // Ignored if table not ready
    }

    return $user;
}

/**
 * Count raw materials below minimum safety stock
 */
function getLowStockCount($outletId = null) {
    if (!$outletId) $outletId = getCurrentOutletId();
    $row = dbFetchOne("SELECT COUNT(*) as cnt FROM raw_materials WHERE current_stock <= min_stock AND outlet_id = ?", [$outletId]);
    return $row ? (int)$row['cnt'] : 0;
}

/**
 * Get setting value
 */
function getAppSetting($key, $default = '') {
    $row = dbFetchOne("SELECT val_text FROM app_settings WHERE key_name = ?", [$key]);
    return $row ? $row['val_text'] : $default;
}

/**
 * Generate unique Invoice Number (e.g., INV-20261014-0005)
 */
function generateInvoiceNo() {
    $prefix = 'INV-' . date('Ymd') . '-';
    $last = dbFetchOne("SELECT invoice_no FROM orders WHERE invoice_no LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
    if ($last) {
        $parts = explode('-', $last['invoice_no']);
        $seq = (int)end($parts) + 1;
    } else {
        $seq = 1;
    }
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate unique Purchase Order Number (e.g., PO-202610-003)
 */
function generatePONumber() {
    $prefix = 'PO-' . date('Ym') . '-';
    $last = dbFetchOne("SELECT po_number FROM purchase_orders WHERE po_number LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
    if ($last) {
        $parts = explode('-', $last['po_number']);
        $seq = (int)end($parts) + 1;
    } else {
        $seq = 1;
    }
    return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

/**
 * Record stock mutation ledger (Kartu Stok)
 */
function recordStockMutation($rawMaterialId, $outletId, $type, $referenceNo, $qtyChange, $notes = '') {
    $material = dbFetchOne("SELECT current_stock FROM raw_materials WHERE id = ?", [$rawMaterialId]);
    if (!$material) return false;

    $currentStock = (float)$material['current_stock'];
    $newStock = $currentStock + (float)$qtyChange;

    // Update raw material stock
    dbUpdate('raw_materials', ['current_stock' => $newStock], 'id = :id', ['id' => $rawMaterialId]);

    // Insert mutation record
    dbInsert('stock_mutations', [
        'raw_material_id' => $rawMaterialId,
        'outlet_id' => $outletId,
        'mutation_type' => $type,
        'reference_no' => $referenceNo,
        'qty_change' => $qtyChange,
        'qty_before' => $currentStock,
        'qty_after' => $newStock,
        'notes' => $notes
    ]);

    return true;
}

/**
 * Calculate Product COGS / HPP dynamically based on Bill of Materials recipe
 */
function calculateProductCOGS($productId) {
    $recipes = dbFetchAll("
        SELECT pr.quantity, rm.cost_per_unit 
        FROM product_recipes pr
        JOIN raw_materials rm ON pr.raw_material_id = rm.id
        WHERE pr.product_id = ?
    ", [$productId]);

    $totalCost = 0;
    foreach ($recipes as $r) {
        $totalCost += ((float)$r['quantity'] * (float)$r['cost_per_unit']);
    }
    return $totalCost;
}

/**
 * Deduct stock automatically based on recipe when menu item is sold
 */
function deductRecipeStock($productId, $soldQty, $invoiceNo, $outletId = 1) {
    $recipes = dbFetchAll("SELECT raw_material_id, quantity, unit FROM product_recipes WHERE product_id = ?", [$productId]);
    foreach ($recipes as $r) {
        $deduction = -1 * ((float)$r['quantity'] * (float)$soldQty);
        recordStockMutation(
            $r['raw_material_id'],
            $outletId,
            'POS_SALE',
            $invoiceNo,
            $deduction,
            "Penjualan POS ({$soldQty} porsi)"
        );
    }
}

/**
 * Record double-entry bookkeeping journal entry
 */
function recordJournalEntry($description, $refNo, $debitAcc, $creditAcc, $amount, $date = null) {
    if ($amount <= 0) return;
    if (!$date) $date = date('Y-m-d');

    $entryNo = 'JRN-' . date('Ymd') . '-' . substr(uniqid(), -4);

    dbInsert('journal_entries', [
        'entry_no' => $entryNo,
        'entry_date' => $date,
        'description' => $description,
        'reference_no' => $refNo,
        'debit_account' => $debitAcc,
        'credit_account' => $creditAcc,
        'amount' => $amount
    ]);
}
