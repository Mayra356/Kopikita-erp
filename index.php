<?php
$pageTitle = 'Executive Dashboard';
require_once __DIR__ . '/includes/header.php';

$outletId = $currentOutlet['id'];
$today = date('Y-m-d');

// 1. KPI Today's Sales
$todaySales = dbFetchOne("
    SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_revenue, COALESCE(SUM(subtotal), 0) as total_subtotal
    FROM orders 
    WHERE outlet_id = ? AND date(created_at) = ? AND payment_status = 'Paid'
", [$outletId, $today]);

// 2. Month-to-date Revenue
$startOfMonth = date('Y-m-01');
$mtdSales = dbFetchOne("
    SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_revenue
    FROM orders 
    WHERE outlet_id = ? AND date(created_at) >= ? AND payment_status = 'Paid'
", [$outletId, $startOfMonth]);

// 3. Approximate Today's COGS
$todayItems = dbFetchAll("
    SELECT oi.product_id, oi.quantity 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE o.outlet_id = ? AND date(o.created_at) = ? AND o.payment_status = 'Paid'
", [$outletId, $today]);

$todayCOGS = 0;
foreach ($todayItems as $item) {
    $todayCOGS += (calculateProductCOGS($item['product_id']) * $item['quantity']);
}
$todayGrossProfit = (float)$todaySales['total_subtotal'] - $todayCOGS;

// 4. Low stock materials
$lowStockItems = dbFetchAll("
    SELECT * FROM raw_materials 
    WHERE current_stock <= min_stock AND outlet_id = ? 
    ORDER BY (current_stock / min_stock) ASC LIMIT 5
", [$outletId]);

// 5. Active shift
$activeShift = dbFetchOne("
    SELECT s.*, e.name as employee_name, e.role 
    FROM shifts s
    JOIN employees e ON s.employee_id = e.id
    WHERE s.outlet_id = ? AND s.shift_date = ? AND s.status = 'On Duty'
    ORDER BY s.id DESC LIMIT 1
", [$outletId, $today]);

// 6. Recent Orders
$recentOrders = dbFetchAll("
    SELECT * FROM orders 
    WHERE outlet_id = ? 
    ORDER BY id DESC LIMIT 5
", [$outletId]);

// 7. Top Selling Products
$topProducts = dbFetchAll("
    SELECT p.name, p.price, c.name as category_name, SUM(oi.quantity) as total_qty, SUM(oi.subtotal) as total_sales
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.outlet_id = ? AND o.payment_status = 'Paid'
    GROUP BY p.id
    ORDER BY total_qty DESC
    LIMIT 5
", [$outletId]);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Executive Dashboard</h1>
        <p class="page-subtitle">Ringkasan operasional harian kedai <strong><?php echo htmlspecialchars($currentOutlet['name']); ?></strong></p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge-soft-primary px-3 py-2">
            <i class="bi bi-calendar3 me-1"></i> <?php echo formatDate(date('Y-m-d H:i'), 'date'); ?>
        </span>
        <a href="pos.php" class="btn btn-coffee">
            <i class="bi bi-plus-circle"></i> Transaksi Baru
        </a>
    </div>
</div>

<!-- 4 Key Performance Indicators (KPI) -->
<div class="row g-3 mb-4">
    <!-- Today's Revenue -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Pendapatan Hari Ini</div>
                    <div class="kpi-value text-success"><?php echo formatRupiah($todaySales['total_revenue']); ?></div>
                    <div class="kpi-desc">
                        <i class="bi bi-check-circle-fill text-success"></i> <strong><?php echo (int)$todaySales['total_orders']; ?></strong> transaksi berhasil
                    </div>
                </div>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="bi bi-cash-coin"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Month to Date Revenue -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Omzet Bulan Ini</div>
                    <div class="kpi-value"><?php echo formatRupiah($mtdSales['total_revenue']); ?></div>
                    <div class="kpi-desc">
                        Bulan <?php echo date('F Y'); ?> (<?php echo (int)$mtdSales['total_orders']; ?> pesanan)
                    </div>
                </div>
                <div class="kpi-icon bg-secondary-subtle text-dark">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Gross Profit Today -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card info">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Laba Kotor (Gross Profit)</div>
                    <div class="kpi-value text-primary"><?php echo formatRupiah($todayGrossProfit); ?></div>
                    <div class="kpi-desc">
                        HPP Bahan: <?php echo formatRupiah($todayCOGS); ?>
                    </div>
                </div>
                <div class="kpi-icon bg-info-subtle text-info">
                    <i class="bi bi-pie-chart-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card warning">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Stok Perlu Reorder</div>
                    <div class="kpi-value <?php echo $lowStockCount > 0 ? 'text-danger' : 'text-secondary'; ?>">
                        <?php echo $lowStockCount; ?> <span style="font-size: 1rem; font-weight: 500;">Bahan</span>
                    </div>
                    <div class="kpi-desc">
                        <?php if ($lowStockCount > 0): ?>
                            <span class="text-danger fw-bold"><i class="bi bi-exclamation-circle-fill"></i> Butuh Purchase Order</span>
                        <?php else: ?>
                            <span class="text-success"><i class="bi bi-shield-check"></i> Persediaan aman</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="kpi-icon bg-warning-subtle text-warning">
                    <i class="bi bi-boxes"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="card-coffee h-100">
            <div class="card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-bar-chart-line-fill text-primary"></i>
                    <span>Tren Penjualan 7 Hari Terakhir</span>
                </div>
                <span class="badge bg-light text-muted border">Minggu Ini</span>
            </div>
            <div class="card-body">
                <canvas id="salesTrendChart" style="height: 280px; width: 100%;"></canvas>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card-coffee h-100">
            <div class="card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-pie-chart-fill text-warning"></i>
                    <span>Kategori Terlaris</span>
                </div>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                <div style="width: 210px; height: 210px;">
                    <canvas id="categoryDonutChart"></canvas>
                </div>
                <div class="text-center mt-3 text-muted small">
                    Signature Coffee mendominasi 54% total omzet
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Section: Recent Orders & Low Stock Table -->
<div class="row g-3">
    <!-- Recent Transactions -->
    <div class="col-12 col-lg-7">
        <div class="card-coffee">
            <div class="card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-primary"></i>
                    <span>Transaksi Terakhir (POS)</span>
                </div>
                <a href="orders.php" class="btn btn-sm btn-outline-coffee">Lihat Semua</a>
            </div>
            <div class="table-responsive">
                <table class="table-coffee">
                    <thead>
                        <tr>
                            <th>No. Invoice</th>
                            <th>Tipe / Meja</th>
                            <th>Pelanggan</th>
                            <th>Total</th>
                            <th>Metode</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentOrders)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada transaksi hari ini</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $ro): ?>
                                <tr>
                                    <td>
                                        <a href="order_detail.php?id=<?php echo $ro['id']; ?>" class="fw-bold text-decoration-none text-primary">
                                            <?php echo htmlspecialchars($ro['invoice_no']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?php echo htmlspecialchars($ro['order_type']); ?>
                                            <?php echo $ro['table_no'] ? ' (' . htmlspecialchars($ro['table_no']) . ')' : ''; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ro['customer_name']); ?></td>
                                    <td class="fw-bold"><?php echo formatRupiah($ro['total_amount']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-dark">
                                            <?php echo htmlspecialchars($ro['payment_method']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted" style="font-size: 0.8rem;">
                                        <?php echo formatDate($ro['created_at'], 'time'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Products & Low Stock Alert Side -->
    <div class="col-12 col-lg-5">
        <!-- Top Products List -->
        <div class="card-coffee mb-3">
            <div class="card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-award-fill text-warning"></i>
                    <span>Top 5 Menu Paling Laku</span>
                </div>
                <a href="menu.php" class="small text-muted text-decoration-none">Kelola Menu</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (empty($topProducts)): ?>
                        <li class="list-group-item text-center py-3 text-muted">Belum ada data penjualan menu</li>
                    <?php else: ?>
                        <?php $rank = 1; foreach ($topProducts as $tp): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge rounded-pill <?php echo $rank == 1 ? 'bg-warning text-dark' : 'bg-light text-muted border'; ?>" style="width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center;">
                                        <?php echo $rank++; ?>
                                    </span>
                                    <div>
                                        <div class="fw-bold" style="font-size: 0.9rem;"><?php echo htmlspecialchars($tp['name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($tp['category_name']); ?> &bull; <?php echo formatRupiah($tp['price']); ?></div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary text-white"><?php echo $tp['total_qty']; ?> cup / porsi</span>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?php echo formatRupiah($tp['total_sales']); ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Low Stock Warning Box -->
        <?php if (!empty($lowStockItems)): ?>
            <div class="card-coffee border-danger-subtle">
                <div class="card-header bg-danger-subtle text-danger">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span class="fw-bold">Peringatan: Bahan Baku Menipis</span>
                    </div>
                    <a href="po_create.php" class="btn btn-sm btn-danger py-0 px-2" style="font-size: 0.75rem;">Buat PO</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($lowStockItems as $lsi): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <div>
                                    <div class="fw-bold text-danger" style="font-size: 0.85rem;"><?php echo htmlspecialchars($lsi['name']); ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;">Min. Safety Stock: <?php echo number_format($lsi['min_stock']) . ' ' . $lsi['unit']; ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge-soft-danger"><?php echo number_format($lsi['current_stock']) . ' ' . $lsi['unit']; ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Sales Trend Chart
    const trendCtx = document.getElementById('salesTrendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: ['Kamis', 'Jumat', 'Sabtu', 'Minggu', 'Senin', 'Selasa', 'Hari Ini'],
            datasets: [{
                label: 'Penjualan (IDR)',
                data: [1850000, 2450000, 3950000, 4200000, 2100000, 2650000, <?php echo (float)$todaySales['total_revenue']; ?>],
                borderColor: '#5C3D2E',
                backgroundColor: 'rgba(92, 61, 46, 0.08)',
                borderWidth: 3,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#D4A373',
                pointBorderColor: '#5C3D2E',
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' ' + formatRupiahJS(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + (value / 1000) + 'k';
                        }
                    },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

    // Category Donut Chart
    const donutCtx = document.getElementById('categoryDonutChart').getContext('2d');
    new Chart(donutCtx, {
        type: 'doughnut',
        data: {
            labels: ['Signature Coffee', 'Espresso Based', 'Non-Coffee & Tea', 'Pastry & Food', 'Manual Brew'],
            datasets: [{
                data: [52, 22, 14, 8, 4],
                backgroundColor: ['#5C3D2E', '#D4A373', '#2A9D8F', '#E76F51', '#264653'],
                borderWidth: 2,
                borderColor: '#FFFFFF'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            cutout: '72%'
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
