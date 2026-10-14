<?php
$pageTitle = 'Riwayat Transaksi (Sales Orders)';
require_once __DIR__ . '/includes/header.php';

$outletId = $currentOutlet['id'];

// Filter parameters
$dateFilter = $_GET['date'] ?? 'today';
$paymentFilter = $_GET['payment'] ?? 'all';
$orderTypeFilter = $_GET['type'] ?? 'all';
$searchQuery = trim($_GET['search'] ?? '');

// Build query
$where = ["outlet_id = :outlet_id"];
$params = [':outlet_id' => $outletId];

// Date conditions
$today = date('Y-m-d');
if ($dateFilter === 'today') {
    $where[] = "date(created_at) = :today";
    $params[':today'] = $today;
} elseif ($dateFilter === 'yesterday') {
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $where[] = "date(created_at) = :yesterday";
    $params[':yesterday'] = $yesterday;
} elseif ($dateFilter === '7days') {
    $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
    $where[] = "date(created_at) >= :sevendays";
    $params[':sevendays'] = $sevenDaysAgo;
} elseif ($dateFilter === 'month') {
    $startOfMonth = date('Y-m-01');
    $where[] = "date(created_at) >= :startofmonth";
    $params[':startofmonth'] = $startOfMonth;
}

if ($paymentFilter !== 'all') {
    $where[] = "payment_method = :pay_method";
    $params[':pay_method'] = $paymentFilter;
}

if ($orderTypeFilter !== 'all') {
    $where[] = "order_type = :order_type";
    $params[':order_type'] = $orderTypeFilter;
}

if (!empty($searchQuery)) {
    $where[] = "(invoice_no LIKE :q OR customer_name LIKE :q OR table_no LIKE :q)";
    $params[':q'] = "%{$searchQuery}%";
}

$whereClause = implode(' AND ', $where);

// Fetch Summary Stats for this filter
$stats = dbFetchOne("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(SUM(discount), 0) as total_discount,
        COALESCE(SUM(tax), 0) as total_tax,
        COALESCE(AVG(total_amount), 0) as avg_order_value
    FROM orders
    WHERE {$whereClause} AND payment_status = 'Paid'
", $params);

// Fetch Orders
$orders = dbFetchAll("
    SELECT * FROM orders
    WHERE {$whereClause}
    ORDER BY id DESC
", $params);

// Pre-fetch items count for each order
$orderIds = array_column($orders, 'id');
$orderItemsMap = [];
if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $rawItems = dbFetchAll("SELECT * FROM order_items WHERE order_id IN ({$placeholders})", $orderIds);
    foreach ($rawItems as $ri) {
        $orderItemsMap[$ri['order_id']][] = $ri;
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Riwayat Transaksi Penjualan</h1>
        <p class="page-subtitle">Daftar struk, rekonsiliasi kasir, dan arsip penjualan kedai</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="pos.php" class="btn btn-coffee">
            <i class="bi bi-plus-circle me-1"></i> Transaksi Kasir Baru
        </a>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Total Pendapatan Terfilter</div>
                    <div class="kpi-value text-success"><?php echo formatRupiah($stats['total_revenue']); ?></div>
                    <div class="kpi-desc">Dari <?php echo (int)$stats['total_orders']; ?> transaksi selesai</div>
                </div>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Rata-rata Basket Size (AOV)</div>
                    <div class="kpi-value"><?php echo formatRupiah($stats['avg_order_value']); ?></div>
                    <div class="kpi-desc">Rata-rata per pelanggan</div>
                </div>
                <div class="kpi-icon bg-primary-subtle text-primary">
                    <i class="bi bi-calculator"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card warning">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Pajak Restoran (PB1)</div>
                    <div class="kpi-value text-warning"><?php echo formatRupiah($stats['total_tax']); ?></div>
                    <div class="kpi-desc">Kewajiban pajak daerah 10%</div>
                </div>
                <div class="kpi-icon bg-warning-subtle text-warning">
                    <i class="bi bi-bank"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card info">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Total Diskon Diberikan</div>
                    <div class="kpi-value text-danger"><?php echo formatRupiah($stats['total_discount']); ?></div>
                    <div class="kpi-desc">Reward loyalitas & promo</div>
                </div>
                <div class="kpi-icon bg-danger-subtle text-danger">
                    <i class="bi bi-tags-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card-coffee mb-4">
    <div class="card-body p-3">
        <form method="GET" action="orders.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Cari invoice / pelanggan..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
            </div>

            <div class="col-6 col-md-2">
                <select name="date" class="form-select" onchange="this.form.submit()">
                    <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                    <option value="yesterday" <?php echo $dateFilter === 'yesterday' ? 'selected' : ''; ?>>Kemarin</option>
                    <option value="7days" <?php echo $dateFilter === '7days' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
                    <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>Bulan Ini</option>
                    <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>Semua Waktu</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <select name="payment" class="form-select" onchange="this.form.submit()">
                    <option value="all">Semua Pembayaran</option>
                    <option value="Cash" <?php echo $paymentFilter === 'Cash' ? 'selected' : ''; ?>>Cash (Tunai)</option>
                    <option value="QRIS" <?php echo $paymentFilter === 'QRIS' ? 'selected' : ''; ?>>QRIS (Gopay/OVO)</option>
                    <option value="Debit Card" <?php echo $paymentFilter === 'Debit Card' ? 'selected' : ''; ?>>Kartu Debit</option>
                    <option value="Transfer Bank" <?php echo $paymentFilter === 'Transfer Bank' ? 'selected' : ''; ?>>Transfer</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="all">Semua Tipe Pesanan</option>
                    <option value="Dine In" <?php echo $orderTypeFilter === 'Dine In' ? 'selected' : ''; ?>>Dine In</option>
                    <option value="Take Away" <?php echo $orderTypeFilter === 'Take Away' ? 'selected' : ''; ?>>Take Away</option>
                    <option value="Delivery" <?php echo $orderTypeFilter === 'Delivery' ? 'selected' : ''; ?>>Delivery</option>
                </select>
            </div>

            <div class="col-6 col-md-3 d-flex gap-2 justify-content-end">
                <button type="submit" class="btn btn-coffee w-100">
                    <i class="bi bi-funnel"></i> Terapkan
                </button>
                <a href="orders.php" class="btn btn-outline-secondary" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card-coffee">
    <div class="card-header">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-receipt text-primary"></i>
            <span class="fw-bold">Daftar Transaksi (<?php echo count($orders); ?> data)</span>
        </div>
        <button class="btn btn-sm btn-outline-coffee" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Cetak Laporan
        </button>
    </div>
    <div class="table-responsive">
        <table class="table-coffee table-hover">
            <thead>
                <tr>
                    <th>No. Invoice</th>
                    <th>Waktu</th>
                    <th>Pelanggan</th>
                    <th>Tipe / Meja</th>
                    <th>Rincian Menu</th>
                    <th>Total Bayar</th>
                    <th>Metode</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                            Tidak ditemukan transaksi pada filter yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <?php 
                            $items = $orderItemsMap[$o['id']] ?? [];
                            $totalQty = array_sum(array_column($items, 'quantity'));
                        ?>
                        <tr>
                            <td>
                                <a href="order_detail.php?id=<?php echo $o['id']; ?>" class="fw-bold text-decoration-none text-primary">
                                    <?php echo htmlspecialchars($o['invoice_no']); ?>
                                </a>
                                <div class="text-muted" style="font-size: 0.72rem;">Kasir: <?php echo htmlspecialchars($o['cashier_name']); ?></div>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem; font-weight: 500;"><?php echo formatDate($o['created_at'], 'short'); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo formatDate($o['created_at'], 'time'); ?> WIB</div>
                            </td>
                            <td>
                                <div class="fw-bold" style="font-size: 0.85rem;"><?php echo htmlspecialchars($o['customer_name']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi <?php echo $o['order_type'] === 'Dine In' ? 'bi-shop' : 'bi-bag-check'; ?> me-1"></i>
                                    <?php echo htmlspecialchars($o['order_type']); ?>
                                </span>
                                <?php if ($o['table_no']): ?>
                                    <div class="text-muted small mt-1">Meja #<?php echo htmlspecialchars($o['table_no']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1" style="max-width: 250px;">
                                    <?php foreach (array_slice($items, 0, 2) as $it): ?>
                                        <span class="badge bg-secondary-subtle text-dark border" style="font-size: 0.72rem;">
                                            <?php echo htmlspecialchars($it['product_name']); ?> (x<?php echo $it['quantity']; ?>)
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($items) > 2): ?>
                                        <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">
                                            +<?php echo count($items) - 2; ?> lainnya
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo formatRupiah($o['total_amount']); ?></div>
                                <?php if ($o['discount'] > 0): ?>
                                    <span class="text-danger small" style="font-size: 0.72rem;">Diskon: <?php echo formatRupiah($o['discount']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $o['payment_method'] === 'Cash' ? 'bg-success-subtle text-success' : 'bg-info-subtle text-info'; ?> border">
                                    <i class="bi <?php echo $o['payment_method'] === 'Cash' ? 'bi-cash' : 'bi-qr-code'; ?> me-1"></i>
                                    <?php echo htmlspecialchars($o['payment_method']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($o['payment_status'] === 'Paid'): ?>
                                    <span class="badge-soft-success">Lunas</span>
                                <?php elseif ($o['payment_status'] === 'Cancelled'): ?>
                                    <span class="badge-soft-danger">Dibatalkan</span>
                                <?php else: ?>
                                    <span class="badge-soft-warning"><?php echo htmlspecialchars($o['payment_status']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle py-0 px-2" data-bs-toggle="dropdown">
                                        Opsi
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <li>
                                            <a class="dropdown-item" href="order_detail.php?id=<?php echo $o['id']; ?>">
                                                <i class="bi bi-eye text-primary me-2"></i> Rincian Lengkap
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="order_detail.php?id=<?php echo $o['id']; ?>&print=1" target="_blank">
                                                <i class="bi bi-printer text-secondary me-2"></i> Cetak Ulang Struk
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
