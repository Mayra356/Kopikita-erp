<?php
$pageTitle = 'Bahan Baku & Persediaan Gudang';
require_once __DIR__ . '/includes/header.php';

$outletId = $currentOutlet['id'];

// Handle quick stock adjustment from modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_adjust') {
    $matId = (int)$_POST['material_id'];
    $adjustQty = (float)$_POST['adjust_qty']; // positive or negative
    $reason = trim($_POST['reason'] ?? 'Penyesuaian Cepat');
    $adjustType = trim($_POST['adjust_type'] ?? 'WASTE');

    if ($matId > 0 && $adjustQty != 0) {
        recordStockMutation($matId, $outletId, $adjustType, 'ADJ-' . date('ymd-His'), $adjustQty, $reason);
        setFlashMessage('success', 'Stok bahan baku berhasil disesuaikan.');
        header('Location: inventory.php');
        exit;
    }
}

// Filters
$categoryFilter = $_GET['category'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['search'] ?? '');

$where = ["outlet_id = :outlet_id"];
$params = [':outlet_id' => $outletId];

if ($categoryFilter !== 'all') {
    $where[] = "category = :cat";
    $params[':cat'] = $categoryFilter;
}

if ($statusFilter === 'low') {
    $where[] = "current_stock <= min_stock";
} elseif ($statusFilter === 'safe') {
    $where[] = "current_stock > min_stock";
}

if (!empty($searchQuery)) {
    $where[] = "(code LIKE :q OR name LIKE :q)";
    $params[':q'] = "%{$searchQuery}%";
}

$whereClause = implode(' AND ', $where);

// Fetch all materials for this outlet
$materials = dbFetchAll("
    SELECT * FROM raw_materials 
    WHERE {$whereClause}
    ORDER BY (current_stock <= min_stock) DESC, category ASC, name ASC
", $params);

// Overall KPIs
$kpis = dbFetchOne("
    SELECT 
        COUNT(*) as total_sku,
        SUM(current_stock * cost_per_unit) as total_valuation,
        SUM(CASE WHEN current_stock <= min_stock THEN 1 ELSE 0 END) as low_stock_count
    FROM raw_materials
    WHERE outlet_id = ?
", [$outletId]);

// Distinct categories
$categoriesList = dbFetchAll("SELECT DISTINCT category FROM raw_materials WHERE outlet_id = ? ORDER BY category ASC", [$outletId]);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Bahan Baku & Persediaan Gudang</h1>
        <p class="page-subtitle">Monitoring stok fisik, safety stock alerts, dan valuasi aset persediaan kedai</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="stock_card.php" class="btn btn-outline-coffee">
            <i class="bi bi-card-checklist me-1"></i> Kartu Stok Mutasi
        </a>
        <a href="inventory_form.php" class="btn btn-coffee">
            <i class="bi bi-plus-circle me-1"></i> Tambah Bahan Baru
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="kpi-card primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Total Valuasi Nilai Stok</div>
                    <div class="kpi-value"><?php echo formatRupiah($kpis['total_valuation']); ?></div>
                    <div class="kpi-desc">Aset lancar persediaan bahan kedai</div>
                </div>
                <div class="kpi-icon bg-primary-subtle text-primary">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-4">
        <div class="kpi-card info">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Total SKU Bahan Terdaftar</div>
                    <div class="kpi-value text-primary"><?php echo (int)$kpis['total_sku']; ?> <span style="font-size: 1rem; font-weight: 500;">Item</span></div>
                    <div class="kpi-desc">Biji kopi, susu, sirup & kemasan cup</div>
                </div>
                <div class="kpi-icon bg-info-subtle text-info">
                    <i class="bi bi-boxes"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-4">
        <div class="kpi-card <?php echo $kpis['low_stock_count'] > 0 ? 'warning' : 'success'; ?>">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Bahan di Bawah Safety Stock</div>
                    <div class="kpi-value <?php echo $kpis['low_stock_count'] > 0 ? 'text-danger' : 'text-success'; ?>">
                        <?php echo (int)$kpis['low_stock_count']; ?> <span style="font-size: 1rem; font-weight: 500;">Bahan</span>
                    </div>
                    <div class="kpi-desc">
                        <?php if ($kpis['low_stock_count'] > 0): ?>
                            <a href="po_create.php" class="text-danger fw-bold text-decoration-none">
                                <i class="bi bi-arrow-right-circle-fill"></i> Buat PO Pengadaan Sekarang
                            </a>
                        <?php else: ?>
                            <span class="text-success"><i class="bi bi-check-circle-fill"></i> Semua stok dalam batas aman</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="kpi-icon <?php echo $kpis['low_stock_count'] > 0 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'; ?>">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card-coffee mb-4">
    <div class="card-body p-3">
        <form method="GET" action="inventory.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Cari nama atau kode SKU..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
            </div>

            <div class="col-6 col-md-3">
                <select name="category" class="form-select" onchange="this.form.submit()">
                    <option value="all">Semua Kategori Bahan</option>
                    <?php foreach ($categoriesList as $cl): ?>
                        <option value="<?php echo htmlspecialchars($cl['category']); ?>" <?php echo $categoryFilter === $cl['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cl['category']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>Semua Kondisi Stok</option>
                    <option value="low" <?php echo $statusFilter === 'low' ? 'selected' : ''; ?>>⚠️ Menipis (Perlu Reorder)</option>
                    <option value="safe" <?php echo $statusFilter === 'safe' ? 'selected' : ''; ?>>✅ Stok Aman</option>
                </select>
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-coffee w-100">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <a href="inventory.php" class="btn btn-outline-secondary" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Inventory Table -->
<div class="card-coffee">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-boxes text-primary"></i>
            <span class="fw-bold">Daftar Bahan Baku (<?php echo count($materials); ?> SKU)</span>
        </div>
        <div class="d-flex gap-2">
            <a href="stock_opname.php" class="btn btn-sm btn-outline-coffee">
                <i class="bi bi-clipboard-check me-1"></i> Stock Opname
            </a>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Cetak
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table-coffee table-hover">
            <thead>
                <tr>
                    <th>Bahan Baku & SKU</th>
                    <th>Kategori</th>
                    <th>Stok Fisik Saat Ini</th>
                    <th>Safety Stock</th>
                    <th>Biaya / Unit (HPP)</th>
                    <th>Total Valuasi</th>
                    <th>Status Stok</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($materials)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-box-seam fs-1 d-block mb-2 text-secondary"></i>
                            Tidak ada data bahan baku pada filter ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($materials as $m): ?>
                        <?php 
                            $isLow = $m['current_stock'] <= $m['min_stock'];
                            $ratio = $m['min_stock'] > 0 ? ($m['current_stock'] / $m['min_stock']) : 1;
                            $valuation = $m['current_stock'] * $m['cost_per_unit'];
                        ?>
                        <tr class="<?php echo $isLow ? 'table-danger-subtle' : ''; ?>">
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($m['name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($m['code']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?php echo htmlspecialchars($m['category']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold fs-6 <?php echo $isLow ? 'text-danger' : 'text-dark'; ?>">
                                    <?php echo number_format($m['current_stock'], $m['unit'] === 'pcs' ? 0 : 1); ?>
                                </span>
                                <span class="text-muted small"><?php echo htmlspecialchars($m['unit']); ?></span>
                            </td>
                            <td>
                                <span class="text-muted">
                                    <?php echo number_format($m['min_stock'], 0); ?> <?php echo htmlspecialchars($m['unit']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-secondary"><?php echo formatRupiah($m['cost_per_unit']); ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;">per <?php echo htmlspecialchars($m['unit']); ?></div>
                            </td>
                            <td class="fw-bold text-dark">
                                <?php echo formatRupiah($valuation); ?>
                            </td>
                            <td>
                                <?php if ($m['current_stock'] <= 0): ?>
                                    <span class="badge-soft-danger"><i class="bi bi-x-circle-fill"></i> Habis</span>
                                <?php elseif ($isLow): ?>
                                    <span class="badge-soft-danger"><i class="bi bi-exclamation-triangle-fill"></i> Kritis / Reorder</span>
                                <?php elseif ($ratio < 1.5): ?>
                                    <span class="badge-soft-warning">Menengah</span>
                                <?php else: ?>
                                    <span class="badge-soft-success"><i class="bi bi-check-circle-fill"></i> Aman</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="stock_card.php?material_id=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Kartu Stok Mutasi">
                                        <i class="bi bi-card-checklist"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-warning text-dark" title="Penyesuaian Stok Cepat (Waste/Selisih)" 
                                            onclick="openQuickAdjustModal(<?php echo $m['id']; ?>, '<?php echo htmlspecialchars(addslashes($m['name'])); ?>', <?php echo (float)$m['current_stock']; ?>, '<?php echo htmlspecialchars($m['unit']); ?>')">
                                        <i class="bi bi-sliders"></i>
                                    </button>
                                    <a href="inventory_form.php?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-coffee" title="Edit Data Bahan">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Quick Adjust Modal -->
<div class="modal fade" id="quickAdjustModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="quick_adjust">
                <input type="hidden" name="material_id" id="modalMaterialId">

                <div class="modal-header bg-cream">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-sliders text-warning me-2"></i> Penyesuaian Stok Cepat
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Bahan Baku</label>
                        <div class="fw-bold fs-6" id="modalMaterialName">-</div>
                        <div class="text-muted small">Stok saat ini di sistem: <strong id="modalCurrentStock">0</strong></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Jenis Penyesuaian</label>
                        <select name="adjust_type" class="form-select" required>
                            <option value="WASTE">Bahan Tumpah / Kadaluarsa / Rusak (Pengurangan)</option>
                            <option value="STOCK_OPNAME">Koreksi Hitung Fisik Harian</option>
                            <option value="PRODUCTION_IN">Produksi Masuk / Retur</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Perubahan Jumlah Stok</label>
                        <div class="input-group">
                            <input type="number" step="0.1" name="adjust_qty" class="form-control" placeholder="Contoh: -50 atau 100" required>
                            <span class="input-group-text" id="modalUnitLabel">satuan</span>
                        </div>
                        <div class="form-text">Gunakan tanda minus (-) untuk pengurangan (misal: -100 untuk tumpah).</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan / Alasan</label>
                        <input type="text" name="reason" class="form-control" placeholder="Contoh: Susu basi / Tumpah saat kalibrasi mesin" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-coffee btn-sm">Simpan Penyesuaian</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openQuickAdjustModal(matId, matName, currentStock, unit) {
    document.getElementById('modalMaterialId').value = matId;
    document.getElementById('modalMaterialName').innerText = matName;
    document.getElementById('modalCurrentStock').innerText = currentStock + ' ' + unit;
    document.getElementById('modalUnitLabel').innerText = unit;

    const modal = new bootstrap.Modal(document.getElementById('quickAdjustModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
