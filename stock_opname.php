<?php
$pageTitle = 'Stock Opname Fisik (Audit Persediaan)';
require_once __DIR__ . '/includes/header.php';

$outletId = $currentOutlet['id'];

// Handle Opname Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_opname') {
    $materials = $_POST['material_id'] ?? [];
    $actualCounts = $_POST['actual_count'] ?? [];
    $systemCounts = $_POST['system_count'] ?? [];
    $notesList = $_POST['notes'] ?? [];
    $opnameDate = date('Y-m-d H:i:s');
    $opnameRef = 'SOP-' . date('Ymd-His');

    $adjustedCount = 0;
    $totalFinancialVariance = 0;

    $db = getDb();
    $db->beginTransaction();

    try {
        for ($i = 0; $i < count($materials); $i++) {
            $matId = (int)$materials[$i];
            $systemQty = (float)$systemCounts[$i];
            $actualInput = trim($actualCounts[$i]);

            if ($actualInput === '') continue; // Skip uncounted items

            $actualQty = (float)$actualInput;
            $diff = $actualQty - $systemQty;

            if (abs($diff) > 0.0001) {
                // There is a variance!
                $matInfo = dbFetchOne("SELECT cost_per_unit FROM raw_materials WHERE id = ?", [$matId]);
                $unitCost = (float)($matInfo['cost_per_unit'] ?? 0);
                $varianceCost = $diff * $unitCost;
                $totalFinancialVariance += $varianceCost;

                $note = trim($notesList[$i] ?? '');
                $fullNote = "Stock Opname Fisik: Selisih " . ($diff > 0 ? '+' : '') . number_format($diff, 1) . ($note ? " ({$note})" : "");

                recordStockMutation($matId, $outletId, 'STOCK_OPNAME', $opnameRef, $diff, $fullNote);
                $adjustedCount++;
            }
        }

        // Record accounting adjustment if financial variance occurred
        if ($totalFinancialVariance < 0) {
            // Shrinkage loss
            recordJournalEntry(
                "Penyesuaian Selisih Stock Opname (Kurang Fisik) " . $opnameRef,
                $opnameRef,
                6104, // Beban Operasional / Selisih Persediaan
                1301, // Persediaan Bahan Baku
                abs($totalFinancialVariance)
            );
        } elseif ($totalFinancialVariance > 0) {
            // Surplus gain
            recordJournalEntry(
                "Penyesuaian Selisih Stock Opname (Lebih Fisik) " . $opnameRef,
                $opnameRef,
                1301, // Persediaan Bahan Baku
                4101, // Pendapatan
                $totalFinancialVariance
            );
        }

        $db->commit();
        setFlashMessage('success', "Stock Opname berhasil disimpan! Terkoreksi {$adjustedCount} item bahan.");
        header('Location: stock_opname.php');
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $errorMsg = "Gagal memproses Stock Opname: " . $e->getMessage();
    }
}

// Fetch all materials for opname
$materials = dbFetchAll("
    SELECT * FROM raw_materials 
    WHERE outlet_id = ? 
    ORDER BY category ASC, name ASC
", [$outletId]);

// Fetch recent opname history
$opnameHistory = dbFetchAll("
    SELECT sm.*, rm.name as material_name, rm.unit as material_unit
    FROM stock_mutations sm
    JOIN raw_materials rm ON sm.raw_material_id = rm.id
    WHERE sm.outlet_id = ? AND sm.mutation_type = 'STOCK_OPNAME'
    ORDER BY sm.id DESC
    LIMIT 10
", [$outletId]);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Stock Opname Fisik</h1>
        <p class="page-subtitle">Hitung fisik persediaan aktual, rekonsiliasi selisih stok sistem, dan audit kartu mutasi</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="inventory.php" class="btn btn-outline-coffee">
            <i class="bi bi-boxes me-1"></i> Kembali ke Inventori
        </a>
    </div>
</div>

<?php if (isset($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($errorMsg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Instructions Alert -->
<div class="alert alert-info d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-info-circle-fill fs-3 text-info"></i>
    <div>
        <strong>Petunjuk Stock Opname:</strong>
        Masukkan angka hasil timbangan / hitungan fisik pada kolom <em>"Hitung Fisik Aktual"</em>.
        Sistem akan otomatis menghitung selisih (variance +/-) dan menyesuaikan stok sistem ke kartu mutasi.
    </div>
</div>

<form method="POST" id="opnameForm">
    <input type="hidden" name="action" value="save_opname">

    <div class="card-coffee mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-clipboard-check-fill text-warning"></i>
                <span class="fw-bold">Lembar Audit Hitung Fisik (<?php echo count($materials); ?> SKU)</span>
            </div>
            <div class="text-muted small">
                Outlet: <strong><?php echo htmlspecialchars($currentOutlet['name']); ?></strong>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table-coffee table-hover" id="opnameTable">
                <thead>
                    <tr>
                        <th style="width: 25%;">Bahan Baku & SKU</th>
                        <th style="width: 15%;">Kategori</th>
                        <th style="width: 15%;" class="text-end">Stok Sistem</th>
                        <th style="width: 20%;">Hitung Fisik Aktual</th>
                        <th style="width: 10%;" class="text-center">Selisih</th>
                        <th style="width: 15%;">Catatan Selisih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materials as $idx => $m): ?>
                        <tr class="opname-row" data-cost="<?php echo (float)$m['cost_per_unit']; ?>" data-unit="<?php echo htmlspecialchars($m['unit']); ?>">
                            <td>
                                <input type="hidden" name="material_id[]" value="<?php echo $m['id']; ?>">
                                <input type="hidden" name="system_count[]" class="system-count-val" value="<?php echo (float)$m['current_stock']; ?>">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($m['name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($m['code']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($m['category']); ?></span>
                            </td>
                            <td class="text-end fw-bold text-secondary">
                                <?php echo number_format($m['current_stock'], 1); ?> <?php echo htmlspecialchars($m['unit']); ?>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.1" name="actual_count[]" 
                                           class="form-control form-control-sm actual-input fw-bold" 
                                           value="<?php echo (float)$m['current_stock']; ?>"
                                           placeholder="Isi fisik..."
                                           oninput="calculateRowVariance(this)">
                                    <span class="input-group-text"><?php echo htmlspecialchars($m['unit']); ?></span>
                                </div>
                            </td>
                            <td class="text-center variance-col">
                                <span class="badge bg-light text-muted border">Pas (0)</span>
                            </td>
                            <td>
                                <input type="text" name="notes[]" class="form-control form-control-sm" placeholder="e.g. Kalibrasi mesin...">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
            <div>
                <span class="text-muted small">Item dengan selisih:</span>
                <strong id="lblVarianceCount" class="text-danger">0</strong> bahan
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="resetOpnameInputs()">Reset ke Stok Sistem</button>
                <button type="submit" class="btn btn-coffee px-4" onclick="return confirm('Simpan Stock Opname dan sesuaikan stok fisik ke sistem?')">
                    <i class="bi bi-check2-circle me-1"></i> Simpan & Rekonsiliasi Stok
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Recent Opname History -->
<div class="card-coffee">
    <div class="card-header">
        <span class="fw-bold"><i class="bi bi-clock-history me-1"></i> Riwayat Koreksi Opname Terakhir</span>
    </div>
    <div class="table-responsive">
        <table class="table-coffee table-sm" style="font-size: 0.85rem;">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>No. Ref</th>
                    <th>Bahan Baku</th>
                    <th>Stok Sebelum</th>
                    <th>Penyesuaian</th>
                    <th>Stok Sesudah</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($opnameHistory)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-3 text-muted">Belum ada riwayat stock opname</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($opnameHistory as $oh): ?>
                        <tr>
                            <td><?php echo formatDate($oh['created_at']); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($oh['reference_no']); ?></span></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($oh['material_name']); ?></td>
                            <td><?php echo number_format($oh['qty_before'], 1); ?> <?php echo htmlspecialchars($oh['material_unit']); ?></td>
                            <td class="fw-bold <?php echo $oh['qty_change'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($oh['qty_change'] > 0 ? '+' : '') . number_format($oh['qty_change'], 1); ?> <?php echo htmlspecialchars($oh['material_unit']); ?>
                            </td>
                            <td class="fw-bold"><?php echo number_format($oh['qty_after'], 1); ?> <?php echo htmlspecialchars($oh['material_unit']); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($oh['notes']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function calculateRowVariance(input) {
    const row = input.closest('tr');
    const systemQty = parseFloat(row.querySelector('.system-count-val').value) || 0;
    const actualQty = parseFloat(input.value) || 0;
    const diff = actualQty - systemQty;
    const unit = row.getAttribute('data-unit');
    const badgeCol = row.querySelector('.variance-col');

    if (Math.abs(diff) < 0.001) {
        badgeCol.innerHTML = '<span class="badge bg-light text-muted border">Pas (0)</span>';
        row.classList.remove('table-warning');
    } else if (diff > 0) {
        badgeCol.innerHTML = `<span class="badge bg-success-subtle text-success border">+${diff.toFixed(1)} ${unit}</span>`;
        row.classList.add('table-warning');
    } else {
        badgeCol.innerHTML = `<span class="badge bg-danger-subtle text-danger border">${diff.toFixed(1)} ${unit}</span>`;
        row.classList.add('table-warning');
    }

    updateVarianceSummary();
}

function updateVarianceSummary() {
    let diffCount = 0;
    document.querySelectorAll('.opname-row').forEach(row => {
        const sys = parseFloat(row.querySelector('.system-count-val').value) || 0;
        const act = parseFloat(row.querySelector('.actual-input').value) || 0;
        if (Math.abs(act - sys) >= 0.001) {
            diffCount++;
        }
    });
    document.getElementById('lblVarianceCount').innerText = diffCount;
}

function resetOpnameInputs() {
    document.querySelectorAll('.opname-row').forEach(row => {
        const sys = row.querySelector('.system-count-val').value;
        const actInput = row.querySelector('.actual-input');
        actInput.value = sys;
        calculateRowVariance(actInput);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
