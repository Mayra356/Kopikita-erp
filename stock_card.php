<?php
$pageTitle = 'Kartu Stok Mutasi Persediaan';
require_once __DIR__ . '/includes/header.php';

$outletId = $currentOutlet['id'];

// Filter params
$materialId = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;
$mutationType = $_GET['type'] ?? 'all';
$dateFilter = $_GET['date'] ?? '30days';

$where = ["sm.outlet_id = :outlet_id"];
$params = [':outlet_id' => $outletId];

if ($materialId > 0) {
    $where[] = "sm.raw_material_id = :mat_id";
    $params[':mat_id'] = $materialId;
}

if ($mutationType !== 'all') {
    $where[] = "sm.mutation_type = :mut_type";
    $params[':mut_type'] = $mutationType;
}

if ($dateFilter === 'today') {
    $where[] = "date(sm.created_at) = :today";
    $params[':today'] = date('Y-m-d');
} elseif ($dateFilter === '7days') {
    $where[] = "date(sm.created_at) >= :sevendays";
    $params[':sevendays'] = date('Y-m-d', strtotime('-7 days'));
} elseif ($dateFilter === '30days') {
    $where[] = "date(sm.created_at) >= :thirtydays";
    $params[':thirtydays'] = date('Y-m-d', strtotime('-30 days'));
}

$whereClause = implode(' AND ', $where);

// Fetch mutations
$mutations = dbFetchAll("
    SELECT sm.*, rm.name as material_name, rm.code as material_code, rm.unit as material_unit
    FROM stock_mutations sm
    JOIN raw_materials rm ON sm.raw_material_id = rm.id
    WHERE {$whereClause}
    ORDER BY sm.id DESC
", $params);

// Fetch raw materials for selector
$rawMaterials = dbFetchAll("SELECT * FROM raw_materials WHERE outlet_id = ? ORDER BY category ASC, name ASC", [$outletId]);

// Summary stats
$totalIn = 0;
$totalOut = 0;
foreach ($mutations as $m) {
    if ($m['qty_change'] > 0) {
        $totalIn += $m['qty_change'];
    } else {
        $totalOut += abs($m['qty_change']);
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Kartu Stok Mutasi Persediaan</h1>
        <p class="page-subtitle">Buku besar audit mutasi bahan: penjualan POS, penerimaan PO, waste, dan rekonsiliasi opname</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="inventory.php" class="btn btn-outline-coffee">
            <i class="bi bi-boxes me-1"></i> Data Bahan Baku
        </a>
        <a href="stock_opname.php" class="btn btn-coffee">
            <i class="bi bi-clipboard-check me-1"></i> Lakukan Stock Opname
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="kpi-card info">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Total Baris Mutasi</div>
                    <div class="kpi-value"><?php echo count($mutations); ?> <span style="font-size: 1rem; font-weight: 500;">Peristiwa</span></div>
                    <div class="kpi-desc">Terekam dalam audit trail sistem</div>
                </div>
                <div class="kpi-icon bg-info-subtle text-info">
                    <i class="bi bi-journal-text"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-4">
        <div class="kpi-card success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Mutasi Masuk (+)</div>
                    <div class="kpi-value text-success">+<?php echo number_format($totalIn, 1); ?></div>
                    <div class="kpi-desc">Penerimaan PO & Batch Produksi</div>
                </div>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="bi bi-arrow-down-left-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-4">
        <div class="kpi-card warning">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Mutasi Keluar (-)</div>
                    <div class="kpi-value text-danger">-<?php echo number_format($totalOut, 1); ?></div>
                    <div class="kpi-desc">Penjualan POS & Waste Bahan</div>
                </div>
                <div class="kpi-icon bg-danger-subtle text-danger">
                    <i class="bi bi-arrow-up-right-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card-coffee mb-4">
    <div class="card-body p-3">
        <form method="GET" action="stock_card.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <select name="material_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">Semua Bahan Baku (Seluruh Item)</option>
                    <?php foreach ($rawMaterials as $rm): ?>
                        <option value="<?php echo $rm['id']; ?>" <?php echo $materialId == $rm['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($rm['name']); ?> (<?php echo htmlspecialchars($rm['code']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-3">
                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="all">Semua Tipe Mutasi</option>
                    <option value="POS_SALE" <?php echo $mutationType === 'POS_SALE' ? 'selected' : ''; ?>>Penjualan POS (POS_SALE)</option>
                    <option value="PURCHASE_RECEIPT" <?php echo $mutationType === 'PURCHASE_RECEIPT' ? 'selected' : ''; ?>>Penerimaan PO (PURCHASE_RECEIPT)</option>
                    <option value="STOCK_OPNAME" <?php echo $mutationType === 'STOCK_OPNAME' ? 'selected' : ''; ?>>Koreksi Opname (STOCK_OPNAME)</option>
                    <option value="WASTE" <?php echo $mutationType === 'WASTE' ? 'selected' : ''; ?>>Tumpah / Kadaluarsa (WASTE)</option>
                    <option value="PRODUCTION_IN" <?php echo $mutationType === 'PRODUCTION_IN' ? 'selected' : ''; ?>>Produksi Masuk (PRODUCTION_IN)</option>
                    <option value="PRODUCTION_OUT" <?php echo $mutationType === 'PRODUCTION_OUT' ? 'selected' : ''; ?>>Produksi Keluar (PRODUCTION_OUT)</option>
                </select>
            </div>

            <div class="col-6 col-md-3">
                <select name="date" class="form-select" onchange="this.form.submit()">
                    <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                    <option value="7days" <?php echo $dateFilter === '7days' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
                    <option value="30days" <?php echo $dateFilter === '30days' ? 'selected' : ''; ?>>30 Hari Terakhir</option>
                    <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>Semua Waktu</option>
                </select>
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-coffee w-100">
                    <i class="bi bi-filter"></i> Filter
                </button>
                <a href="stock_card.php" class="btn btn-outline-secondary" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Mutations Table -->
<div class="card-coffee">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-card-checklist text-primary"></i>
            <span class="fw-bold">Log Mutasi Stok Terperinci</span>
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Cetak Kartu Stok
        </button>
    </div>
    <div class="table-responsive">
        <table class="table-coffee table-hover">
            <thead>
                <tr>
                    <th>Waktu Mutasi</th>
                    <th>No. Referensi</th>
                    <th>Bahan Baku</th>
                    <th>Jenis Mutasi</th>
                    <th class="text-end">Stok Awal</th>
                    <th class="text-end">Perubahan (+/-)</th>
                    <th class="text-end">Stok Akhir</th>
                    <th>Keterangan / Alasan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mutations)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                            Tidak ada riwayat mutasi stok untuk filter ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($mutations as $m): ?>
                        <tr>
                            <td>
                                <div style="font-size: 0.85rem; font-weight: 500;"><?php echo formatDate($m['created_at'], 'date'); ?></div>
                                <div class="text-muted small"><?php echo formatDate($m['created_at'], 'time'); ?> WIB</div>
                            </td>
                            <td>
                                <?php if (str_starts_with($m['reference_no'], 'INV-')): ?>
                                    <span class="badge bg-light text-primary border">
                                        <i class="bi bi-receipt me-1"></i> <?php echo htmlspecialchars($m['reference_no']); ?>
                                    </span>
                                <?php elseif (str_starts_with($m['reference_no'], 'PO-')): ?>
                                    <span class="badge bg-light text-success border">
                                        <i class="bi bi-bag-check me-1"></i> <?php echo htmlspecialchars($m['reference_no']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border">
                                        <?php echo htmlspecialchars($m['reference_no'] ?: '-'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($m['material_name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($m['material_code']); ?></div>
                            </td>
                            <td>
                                <?php if ($m['mutation_type'] === 'POS_SALE'): ?>
                                    <span class="badge bg-danger-subtle text-danger border">
                                        <i class="bi bi-cart-dash"></i> Penjualan POS
                                    </span>
                                <?php elseif ($m['mutation_type'] === 'PURCHASE_RECEIPT'): ?>
                                    <span class="badge bg-success-subtle text-success border">
                                        <i class="bi bi-truck"></i> Penerimaan PO
                                    </span>
                                <?php elseif ($m['mutation_type'] === 'STOCK_OPNAME'): ?>
                                    <span class="badge bg-info-subtle text-info border">
                                        <i class="bi bi-clipboard-check"></i> Opname Fisik
                                    </span>
                                <?php elseif ($m['mutation_type'] === 'WASTE'): ?>
                                    <span class="badge bg-warning-subtle text-warning border">
                                        <i class="bi bi-trash"></i> Waste / Rusak
                                    </span>
                                <?php elseif (str_starts_with($m['mutation_type'], 'PRODUCTION')): ?>
                                    <span class="badge bg-primary-subtle text-primary border">
                                        <i class="bi bi-fire"></i> Batch Roastery
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($m['mutation_type']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-muted">
                                <?php echo number_format($m['qty_before'], 1); ?> <?php echo htmlspecialchars($m['material_unit']); ?>
                            </td>
                            <td class="text-end fw-bold <?php echo $m['qty_change'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($m['qty_change'] > 0 ? '+' : '') . number_format($m['qty_change'], 1); ?> <?php echo htmlspecialchars($m['material_unit']); ?>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                <?php echo number_format($m['qty_after'], 1); ?> <?php echo htmlspecialchars($m['material_unit']); ?>
                            </td>
                            <td class="small text-muted">
                                <?php echo htmlspecialchars($m['notes'] ?: '-'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
