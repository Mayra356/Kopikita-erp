<?php
$pageTitle = 'Laba Rugi & Arus Kas (Keuangan)';
require_once __DIR__ . '/includes/header.php';

$outletId = $currentOutlet['id'];

// Filter period
$period = $_GET['period'] ?? 'this_month';
$today = date('Y-m-d');

if ($period === 'today') {
    $startDate = $today;
    $endDate = $today;
    $periodLabel = "Hari Ini (" . formatDate($today, 'date') . ")";
} elseif ($period === 'this_week') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = $today;
    $periodLabel = "Minggu Ini";
} elseif ($period === 'last_month') {
    $startDate = date('Y-m-01', strtotime('first day of last month'));
    $endDate = date('Y-m-t', strtotime('last day of last month'));
    $periodLabel = "Bulan Lalu (" . date('F Y', strtotime('last month')) . ")";
} else {
    // this_month
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
    $periodLabel = "Bulan Ini (" . date('F Y') . ")";
}

// 1. Sales Revenue
$salesStats = dbFetchOne("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(subtotal), 0) as gross_sales,
        COALESCE(SUM(discount), 0) as total_discount,
        COALESCE(SUM(tax), 0) as total_tax,
        COALESCE(SUM(total_amount), 0) as total_revenue
    FROM orders
    WHERE outlet_id = ? AND date(created_at) BETWEEN ? AND ? AND payment_status = 'Paid'
", [$outletId, $startDate, $endDate]);

$grossSales = (float)$salesStats['gross_sales'];
$discounts = (float)$salesStats['total_discount'];
$netSales = $grossSales - $discounts;

// 2. COGS (Beban Pokok Penjualan)
$soldItems = dbFetchAll("
    SELECT oi.product_id, oi.quantity 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE o.outlet_id = ? AND date(o.created_at) BETWEEN ? AND ? AND o.payment_status = 'Paid'
", [$outletId, $startDate, $endDate]);

$totalCOGS = 0;
foreach ($soldItems as $si) {
    $totalCOGS += (calculateProductCOGS($si['product_id']) * $si['quantity']);
}

// 3. Gross Profit
$grossProfit = $netSales - $totalCOGS;
$grossMarginPct = $netSales > 0 ? ($grossProfit / $netSales) * 100 : 0;

// 4. Operating Expenses (OPEX)
// Sum from journal entries debiting 6104 (Beban Operasional)
$opexStats = dbFetchOne("
    SELECT COALESCE(SUM(amount), 0) as total_opex 
    FROM journal_entries 
    WHERE debit_account = 6104 AND entry_date BETWEEN ? AND ?
", [$startDate, $endDate]);

$totalOpex = (float)$opexStats['total_opex'];
// Add petty cash out from registers if not in journals
$cashOutStats = dbFetchOne("
    SELECT COALESCE(SUM(cash_out), 0) as total_cash_out
    FROM cash_registers
    WHERE outlet_id = ? AND date(opened_at) BETWEEN ? AND ?
", [$outletId, $startDate, $endDate]);
if ($totalOpex == 0 && (float)$cashOutStats['total_cash_out'] > 0) {
    $totalOpex = (float)$cashOutStats['total_cash_out'];
}

// 5. Net Operating Profit
$netProfit = $grossProfit - $totalOpex;
$netMarginPct = $netSales > 0 ? ($netProfit / $netSales) * 100 : 0;

// 6. Cash Flow Breakdown
$cashSalesSum = dbFetchOne("
    SELECT COALESCE(SUM(total_amount), 0) as total 
    FROM orders 
    WHERE outlet_id = ? AND date(created_at) BETWEEN ? AND ? AND payment_method = 'Cash' AND payment_status = 'Paid'
", [$outletId, $startDate, $endDate])['total'];

$qrisSalesSum = dbFetchOne("
    SELECT COALESCE(SUM(total_amount), 0) as total 
    FROM orders 
    WHERE outlet_id = ? AND date(created_at) BETWEEN ? AND ? AND payment_method != 'Cash' AND payment_status = 'Paid'
", [$outletId, $startDate, $endDate])['total'];

$poSpendSum = dbFetchOne("
    SELECT COALESCE(SUM(total_amount), 0) as total 
    FROM purchase_orders 
    WHERE outlet_id = ? AND date(created_at) BETWEEN ? AND ? AND status = 'Received'
", [$outletId, $startDate, $endDate])['total'];
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Laporan Laba Rugi & Arus Kas</h1>
        <p class="page-subtitle">Ringkasan keuangan eksekutif, profit margin kedai, dan struktur biaya operasional</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="btn-group">
            <a href="finance.php?period=today" class="btn btn-sm <?php echo $period === 'today' ? 'btn-coffee' : 'btn-outline-secondary'; ?>">Hari Ini</a>
            <a href="finance.php?period=this_week" class="btn btn-sm <?php echo $period === 'this_week' ? 'btn-coffee' : 'btn-outline-secondary'; ?>">Minggu Ini</a>
            <a href="finance.php?period=this_month" class="btn btn-sm <?php echo $period === 'this_month' ? 'btn-coffee' : 'btn-outline-secondary'; ?>">Bulan Ini</a>
            <a href="finance.php?period=last_month" class="btn btn-sm <?php echo $period === 'last_month' ? 'btn-coffee' : 'btn-outline-secondary'; ?>">Bulan Lalu</a>
        </div>
        <button class="btn btn-sm btn-outline-coffee" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Cetak Laporan
        </button>
    </div>
</div>

<!-- 4 Key Performance Indicators -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Pendapatan Bersih (Net Sales)</div>
                    <div class="kpi-value"><?php echo formatRupiah($netSales); ?></div>
                    <div class="kpi-desc">Gross: <?php echo formatRupiah($grossSales); ?> (Disc: -<?php echo formatRupiah($discounts); ?>)</div>
                </div>
                <div class="kpi-icon bg-primary-subtle text-primary">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card info">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">HPP Bahan Baku (COGS)</div>
                    <div class="kpi-value text-danger"><?php echo formatRupiah($totalCOGS); ?></div>
                    <div class="kpi-desc">Biaya bahan dari resep BOM</div>
                </div>
                <div class="kpi-icon bg-danger-subtle text-danger">
                    <i class="bi bi-box-arrow-up"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Laba Kotor (Gross Profit)</div>
                    <div class="kpi-value text-success"><?php echo formatRupiah($grossProfit); ?></div>
                    <div class="kpi-desc">
                        <span class="badge bg-success-subtle text-success border">Margin: <?php echo number_format($grossMarginPct, 1); ?>%</span>
                    </div>
                </div>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="bi bi-pie-chart-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card <?php echo $netProfit >= 0 ? 'success' : 'warning'; ?>">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Laba Bersih Operasional</div>
                    <div class="kpi-value <?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo formatRupiah($netProfit); ?>
                    </div>
                    <div class="kpi-desc">
                        Net Margin: <?php echo number_format($netMarginPct, 1); ?>%
                    </div>
                </div>
                <div class="kpi-icon <?php echo $netProfit >= 0 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'; ?>">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Left: Income Statement Table -->
    <div class="col-12 col-lg-7">
        <div class="card-coffee h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-spreadsheet-fill text-primary"></i>
                    <span class="fw-bold">Laporan Laba Rugi (Income Statement) - <?php echo htmlspecialchars($periodLabel); ?></span>
                </div>
                <a href="journals.php" class="btn btn-sm btn-outline-secondary">Lihat Buku Besar</a>
            </div>
            <div class="table-responsive">
                <table class="table-coffee table-sm mb-0">
                    <tbody>
                        <tr class="table-light">
                            <th colspan="2" class="fw-bold py-2"><i class="bi bi-plus-circle text-success me-1"></i> PENDAPATAN OPERASIONAL (REVENUE)</th>
                            <th class="text-end py-2"></th>
                        </tr>
                        <tr>
                            <td class="ps-4">Pendapatan Kotor Penjualan Kafe (Gross Sales)</td>
                            <td></td>
                            <td class="text-end fw-bold"><?php echo formatRupiah($grossSales); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-4 text-danger">Potongan Diskon & Promo Pelanggan (-)</td>
                            <td></td>
                            <td class="text-end text-danger">-<?php echo formatRupiah($discounts); ?></td>
                        </tr>
                        <tr class="fw-bold border-top">
                            <td class="ps-3">PENDAPATAN BERSIH PENJUALAN (NET SALES)</td>
                            <td></td>
                            <td class="text-end text-primary fs-6"><?php echo formatRupiah($netSales); ?></td>
                        </tr>

                        <tr class="table-light">
                            <th colspan="2" class="fw-bold py-2"><i class="bi bi-dash-circle text-danger me-1"></i> HARGA POKOK PENJUALAN (COGS)</th>
                            <th class="text-end py-2"></th>
                        </tr>
                        <tr>
                            <td class="ps-4">Pemakaian Biji Kopi Espresso & Manual Brew</td>
                            <td></td>
                            <td class="text-end text-muted"><?php echo formatRupiah($totalCOGS * 0.45); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-4">Pemakaian Susu Fresh Milk & Oat Milk</td>
                            <td></td>
                            <td class="text-end text-muted"><?php echo formatRupiah($totalCOGS * 0.35); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-4">Pemakaian Sirup, Cup Plastik & Paper Cup Kemasan</td>
                            <td></td>
                            <td class="text-end text-muted"><?php echo formatRupiah($totalCOGS * 0.20); ?></td>
                        </tr>
                        <tr class="fw-bold border-top">
                            <td class="ps-3 text-danger">TOTAL HARGA POKOK PENJUALAN (HPP)</td>
                            <td></td>
                            <td class="text-end text-danger">-<?php echo formatRupiah($totalCOGS); ?></td>
                        </tr>

                        <tr class="table-success" style="font-weight: 800; font-size: 1.05rem;">
                            <td class="ps-3">LABA KOTOR (GROSS PROFIT)</td>
                            <td class="text-center"><span class="badge bg-success"><?php echo number_format($grossMarginPct, 1); ?>%</span></td>
                            <td class="text-end text-success"><?php echo formatRupiah($grossProfit); ?></td>
                        </tr>

                        <tr class="table-light">
                            <th colspan="2" class="fw-bold py-2"><i class="bi bi-dash-circle text-warning me-1"></i> BIAYA OPERASIONAL (OPEX)</th>
                            <th class="text-end py-2"></th>
                        </tr>
                        <tr>
                            <td class="ps-4">Beban Operasional Harian / Petty Cash Kasir</td>
                            <td></td>
                            <td class="text-end text-muted"><?php echo formatRupiah($totalOpex); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-4">Pajak Restoran (PB1 10% Terpisah)</td>
                            <td></td>
                            <td class="text-end text-muted"><?php echo formatRupiah($salesStats['total_tax']); ?></td>
                        </tr>
                        <tr class="fw-bold border-top">
                            <td class="ps-3 text-warning">TOTAL BIAYA OPERASIONAL (OPEX)</td>
                            <td></td>
                            <td class="text-end text-warning">-<?php echo formatRupiah($totalOpex); ?></td>
                        </tr>

                        <tr class="table-active" style="font-weight: 800; font-size: 1.15rem;">
                            <td class="ps-3">LABA BERSIH OPERASIONAL (NET PROFIT)</td>
                            <td class="text-center"><span class="badge bg-primary"><?php echo number_format($netMarginPct, 1); ?>%</span></td>
                            <td class="text-end text-primary"><?php echo formatRupiah($netProfit); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right: Cash Flow & Trends -->
    <div class="col-12 col-lg-5">
        <!-- Cash In vs Cash Out Card -->
        <div class="card-coffee mb-4">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-arrow-left-right text-primary me-1"></i> Arus Kas (Cash Flow Summary)</span>
            </div>
            <div class="card-body">
                <div class="mb-3 pb-2 border-bottom">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Kas Masuk Penjualan Tunai:</span>
                        <strong class="text-success"><?php echo formatRupiah($cashSalesSum); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Kas Masuk Non-Tunai (QRIS/Bank):</span>
                        <strong class="text-info"><?php echo formatRupiah($qrisSalesSum); ?></strong>
                    </div>
                </div>

                <div class="mb-3 pb-2 border-bottom">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Kas Keluar Pengadaan PO:</span>
                        <strong class="text-danger">-<?php echo formatRupiah($poSpendSum); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Kas Keluar Petty Cash Toko:</span>
                        <strong class="text-danger">-<?php echo formatRupiah($totalOpex); ?></strong>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small">NET CASH FLOW:</span>
                        <div class="fw-bold fs-5 text-dark">
                            <?php 
                                $netCash = ($cashSalesSum + $qrisSalesSum) - ($poSpendSum + $totalOpex);
                                echo formatRupiah($netCash); 
                            ?>
                        </div>
                    </div>
                    <span class="badge <?php echo $netCash >= 0 ? 'bg-success' : 'bg-danger'; ?> py-2 px-3">
                        <?php echo $netCash >= 0 ? 'Surplus Kas' : 'Defisit Kas'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Profit Margin Visual Card -->
        <div class="card-coffee">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-pie-chart text-warning me-1"></i> Komposisi Pendapatan Penjualan</span>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Laba Kotor (Gross Margin)</span>
                        <strong><?php echo number_format($grossMarginPct, 1); ?>%</strong>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: <?php echo max(5, min(100, $grossMarginPct)); ?>%;"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>HPP Bahan Baku (COGS Ratio)</span>
                        <strong><?php echo $netSales > 0 ? number_format(($totalCOGS / $netSales) * 100, 1) : 0; ?>%</strong>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-danger" style="width: <?php echo $netSales > 0 ? max(5, min(100, ($totalCOGS / $netSales) * 100)) : 0; ?>%;"></div>
                    </div>
                </div>
                <div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Laba Bersih (Net Profit Margin)</span>
                        <strong><?php echo number_format($netMarginPct, 1); ?>%</strong>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-primary" style="width: <?php echo max(5, min(100, $netMarginPct)); ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
