<?php
$pageTitle = 'Pengadaan & Purchase Order (PO)';
require_once __DIR__ . '/includes/header.php';

$outletId = $currentOutlet['id'];

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$vendorFilter = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;

$where = ["po.outlet_id = :outlet_id"];
$params = [':outlet_id' => $outletId];

if ($statusFilter !== 'all') {
    $where[] = "po.status = :status";
    $params[':status'] = $statusFilter;
}

if ($vendorFilter > 0) {
    $where[] = "po.vendor_id = :vendor_id";
    $params[':vendor_id'] = $vendorFilter;
}

$whereClause = implode(' AND ', $where);

// Fetch POs
$pos = dbFetchAll("
    SELECT po.*, v.name as vendor_name, v.category as vendor_category, v.phone as vendor_phone
    FROM purchase_orders po
    JOIN vendors v ON po.vendor_id = v.id
    WHERE {$whereClause}
    ORDER BY po.id DESC
", $params);

// Fetch summary metrics
$kpis = dbFetchOne("
    SELECT 
        COUNT(*) as total_po,
        SUM(CASE WHEN status IN ('Draft', 'Approved') THEN 1 ELSE 0 END) as pending_po,
        SUM(CASE WHEN status = 'Received' THEN 1 ELSE 0 END) as completed_po,
        COALESCE(SUM(total_amount), 0) as total_spend
    FROM purchase_orders
    WHERE outlet_id = ?
", [$outletId]);

$vendors = dbFetchAll("SELECT * FROM vendors ORDER BY name ASC");
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Purchase Order & Pengadaan Barang</h1>
        <p class="page-subtitle">Pemesanan biji kopi, susu, sirup, dan kemasan ke supplier dengan integrasi stok otomatis</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="vendors.php" class="btn btn-outline-coffee">
            <i class="bi bi-truck me-1"></i> Data Supplier
        </a>
        <a href="po_create.php" class="btn btn-coffee">
            <i class="bi bi-plus-circle me-1"></i> Buat Purchase Order Baru
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Total Belanja Pengadaan</div>
                    <div class="kpi-value"><?php echo formatRupiah($kpis['total_spend']); ?></div>
                    <div class="kpi-desc">Akumulasi transaksi pembelian</div>
                </div>
                <div class="kpi-icon bg-primary-subtle text-primary">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card warning">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">PO Menunggu Penerimaan</div>
                    <div class="kpi-value text-warning"><?php echo (int)$kpis['pending_po']; ?> <span style="font-size: 1rem; font-weight: 500;">PO</span></div>
                    <div class="kpi-desc">Draft / Dalam perjalanan kurir</div>
                </div>
                <div class="kpi-icon bg-warning-subtle text-warning">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">PO Berhasil Diterima</div>
                    <div class="kpi-value text-success"><?php echo (int)$kpis['completed_po']; ?> <span style="font-size: 1rem; font-weight: 500;">PO</span></div>
                    <div class="kpi-desc">Stok masuk ke gudang kedai</div>
                </div>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card info">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Total PO Diterbitkan</div>
                    <div class="kpi-value text-primary"><?php echo (int)$kpis['total_po']; ?> <span style="font-size: 1rem; font-weight: 500;">PO</span></div>
                    <div class="kpi-desc">Rekam jejak pengadaan</div>
                </div>
                <div class="kpi-icon bg-info-subtle text-info">
                    <i class="bi bi-bag-check-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card-coffee mb-4">
    <div class="card-body p-3">
        <form method="GET" action="purchasing.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <select name="vendor_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">Semua Rekanan Supplier</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?php echo $v['id']; ?>" <?php echo $vendorFilter == $v['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($v['name']); ?> (<?php echo htmlspecialchars($v['category']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-4">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="all">Semua Status PO</option>
                    <option value="Draft" <?php echo $statusFilter === 'Draft' ? 'selected' : ''; ?>>Draft (Konsep)</option>
                    <option value="Approved" <?php echo $statusFilter === 'Approved' ? 'selected' : ''; ?>>Approved (Menunggu Pengiriman)</option>
                    <option value="Received" <?php echo $statusFilter === 'Received' ? 'selected' : ''; ?>>Received (Barang Sudah Diterima)</option>
                    <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled (Dibatalkan)</option>
                </select>
            </div>

            <div class="col-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-coffee w-100">
                    <i class="bi bi-funnel"></i> Terapkan
                </button>
                <a href="purchasing.php" class="btn btn-outline-secondary" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- PO Table -->
<div class="card-coffee">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-text text-primary"></i>
            <span class="fw-bold">Daftar Purchase Order</span>
        </div>
        <a href="po_create.php" class="btn btn-sm btn-coffee">
            <i class="bi bi-plus-lg me-1"></i> Buat PO
        </a>
    </div>
    <div class="table-responsive">
        <table class="table-coffee table-hover">
            <thead>
                <tr>
                    <th>No. PO</th>
                    <th>Supplier / Vendor</th>
                    <th>Tanggal PO</th>
                    <th>Estimasi Tiba</th>
                    <th>Total Nilai PO</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pos)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-bag-x fs-1 d-block mb-2 text-secondary"></i>
                            Tidak ada Purchase Order pada filter ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pos as $po): ?>
                        <tr>
                            <td>
                                <a href="po_detail.php?id=<?php echo $po['id']; ?>" class="fw-bold text-decoration-none text-primary">
                                    <?php echo htmlspecialchars($po['po_number']); ?>
                                </a>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($po['vendor_name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($po['vendor_category']); ?></div>
                            </td>
                            <td><?php echo formatDate($po['po_date'], 'date'); ?></td>
                            <td>
                                <?php if ($po['expected_date']): ?>
                                    <span class="text-secondary"><?php echo formatDate($po['expected_date'], 'date'); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold fs-6 text-dark"><?php echo formatRupiah($po['total_amount']); ?></td>
                            <td>
                                <?php if ($po['status'] === 'Received'): ?>
                                    <span class="badge-soft-success">
                                        <i class="bi bi-check-circle-fill me-1"></i> Diterima
                                    </span>
                                <?php elseif ($po['status'] === 'Approved'): ?>
                                    <span class="badge-soft-warning">
                                        <i class="bi bi-truck me-1"></i> Dikirim / Approved
                                    </span>
                                <?php elseif ($po['status'] === 'Draft'): ?>
                                    <span class="badge-soft-secondary">Draft</span>
                                <?php else: ?>
                                    <span class="badge-soft-danger">Dibatalkan</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="po_detail.php?id=<?php echo $po['id']; ?>" class="btn btn-sm btn-outline-coffee" title="Rincian & Dokumen PO">
                                        <i class="bi bi-eye"></i> Detail
                                    </a>
                                    <?php if ($po['status'] === 'Approved'): ?>
                                        <a href="po_detail.php?id=<?php echo $po['id']; ?>&action=receive" class="btn btn-sm btn-success" title="Terima Barang ke Gudang">
                                            <i class="bi bi-box-arrow-in-down"></i> Terima
                                        </a>
                                    <?php endif; ?>
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
