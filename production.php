<?php
$pageTitle = 'Produksi & Roastery (Batching)';
require_once __DIR__ . '/includes/header.php';

$outletId = $currentOutlet['id'];

// Handle New Batch Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_batch') {
    $batchType = trim($_POST['batch_type'] ?? 'Roasting Blend');
    $inputMaterialId = (int)$_POST['input_material_id'];
    $inputQty = (float)$_POST['input_qty'];
    $outputMaterialId = (int)$_POST['output_material_id'];
    $outputQty = (float)$_POST['output_qty'];
    $notes = trim($_POST['notes'] ?? '');

    if ($inputMaterialId > 0 && $outputMaterialId > 0 && $inputQty > 0 && $outputQty > 0) {
        $db = getDb();
        $db->beginTransaction();

        try {
            $batchNo = 'BTC-' . date('Ymd-His');
            
            // Get cost of input
            $inMat = dbFetchOne("SELECT cost_per_unit, name, unit FROM raw_materials WHERE id = ?", [$inputMaterialId]);
            $outMat = dbFetchOne("SELECT cost_per_unit, name, unit FROM raw_materials WHERE id = ?", [$outputMaterialId]);
            
            $totalCost = $inputQty * (float)$inMat['cost_per_unit'];
            $newOutputCostPerUnit = $outputQty > 0 ? ($totalCost / $outputQty) : 0;
            $wasteQty = max(0, $inputQty - $outputQty);

            // 1. Deduct input raw material
            recordStockMutation(
                $inputMaterialId, 
                $outletId, 
                'PRODUCTION_OUT', 
                $batchNo, 
                -1 * $inputQty, 
                "Konsumsi bahan untuk {$batchType} ({$batchNo})"
            );

            // 2. Add output produced material
            recordStockMutation(
                $outputMaterialId, 
                $outletId, 
                'PRODUCTION_IN', 
                $batchNo, 
                $outputQty, 
                "Hasil produksi {$batchType} ({$batchNo})"
            );

            // Update output material's cost_per_unit moving average if applicable
            if ($newOutputCostPerUnit > 0) {
                dbUpdate('raw_materials', ['cost_per_unit' => $newOutputCostPerUnit], 'id = :id', ['id' => $outputMaterialId]);
            }

            // 3. Record batch
            dbInsert('production_batches', [
                'batch_no' => $batchNo,
                'outlet_id' => $outletId,
                'batch_type' => $batchType,
                'input_details' => json_encode([
                    'material_id' => $inputMaterialId,
                    'name' => $inMat['name'],
                    'qty' => $inputQty,
                    'unit' => $inMat['unit']
                ]),
                'output_material_id' => $outputMaterialId,
                'output_qty' => $outputQty,
                'waste_qty' => $wasteQty,
                'cost_total' => $totalCost,
                'status' => 'Completed'
            ]);

            // 4. Record journal
            recordJournalEntry(
                "Konversi Produksi {$batchType} ({$batchNo})",
                $batchNo,
                1301, // Persediaan Bahan Baku (Output)
                1301, // Persediaan Bahan Baku (Input)
                $totalCost
            );

            $db->commit();
            setFlashMessage('success', "Batch produksi {$batchNo} sukses diproses! Stok bahan input & output telah diperbarui.");
            header('Location: production.php');
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errorMsg = "Gagal memproses produksi: " . $e->getMessage();
        }
    } else {
        $errorMsg = "Mohon lengkapi bahan input, bahan output, dan jumlah produksi.";
    }
}

// Fetch all batches
$batches = dbFetchAll("
    SELECT pb.*, rm.name as output_name, rm.unit as output_unit
    FROM production_batches pb
    LEFT JOIN raw_materials rm ON pb.output_material_id = rm.id
    WHERE pb.outlet_id = ?
    ORDER BY pb.id DESC
", [$outletId]);

// Fetch raw materials for form dropdowns
$rawMaterials = dbFetchAll("SELECT * FROM raw_materials WHERE outlet_id = ? ORDER BY category ASC, name ASC", [$outletId]);

// KPIs
$totalBatches = count($batches);
$totalOutputSum = array_sum(array_column($batches, 'output_qty'));
$totalCostSum = array_sum(array_column($batches, 'cost_total'));
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Produksi & Roastery (Batching)</h1>
        <p class="page-subtitle">Manajemen batch sangrai green beans, ekstraksi Cold Brew concentrate, dan sirup in-house</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#newBatchModal">
            <i class="bi bi-fire me-1"></i> Mulai Batch Produksi Baru
        </button>
    </div>
</div>

<?php if (isset($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($errorMsg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="kpi-card primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Total Batch Selesai</div>
                    <div class="kpi-value"><?php echo $totalBatches; ?> <span style="font-size: 1rem; font-weight: 500;">Batch</span></div>
                    <div class="kpi-desc">Produksi in-house roastery & kitchen</div>
                </div>
                <div class="kpi-icon bg-primary-subtle text-primary">
                    <i class="bi bi-fire"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-4">
        <div class="kpi-card success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Total Biaya Produksi Terkonversi</div>
                    <div class="kpi-value text-success"><?php echo formatRupiah($totalCostSum); ?></div>
                    <div class="kpi-desc">Nilai bahan baku yang diproses</div>
                </div>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-4">
        <div class="kpi-card info">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Status Fasilitas Roastery</div>
                    <div class="kpi-value text-primary">Ready</div>
                    <div class="kpi-desc">Mesin Roaster & Brew Tank aktif</div>
                </div>
                <div class="kpi-icon bg-info-subtle text-info">
                    <i class="bi bi-gear-wide-connected"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Batches Table -->
<div class="card-coffee">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-journal-album text-primary"></i>
            <span class="fw-bold">Riwayat Batch Produksi (Roastery Logs)</span>
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Cetak Log
        </button>
    </div>
    <div class="table-responsive">
        <table class="table-coffee table-hover">
            <thead>
                <tr>
                    <th>No. Batch & Tanggal</th>
                    <th>Tipe Produksi</th>
                    <th>Bahan Baku Digunakan (Input)</th>
                    <th>Hasil Produksi (Output)</th>
                    <th>Susut / Waste (Loss)</th>
                    <th>Total Biaya HPP</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($batches)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-fire fs-1 d-block mb-2 text-secondary"></i>
                            Belum ada riwayat batch produksi. Klik tombol <strong>"Mulai Batch Produksi Baru"</strong> untuk memproses bahan.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($batches as $b): ?>
                        <?php 
                            $inputData = json_decode($b['input_details'], true) ?: [];
                            $yieldPct = ($inputData && $inputData['qty'] > 0) ? (($b['output_qty'] / $inputData['qty']) * 100) : 100;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($b['batch_no']); ?></div>
                                <div class="text-muted small"><?php echo formatDate($b['created_at']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border">
                                    <i class="bi bi-fire me-1"></i> <?php echo htmlspecialchars($b['batch_type']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($inputData)): ?>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($inputData['name'] ?? '-'); ?></div>
                                    <div class="text-danger small">
                                        -<?php echo number_format($inputData['qty'], 1); ?> <?php echo htmlspecialchars($inputData['unit'] ?? ''); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-success">
                                    +<?php echo number_format($b['output_qty'], 1); ?> <?php echo htmlspecialchars($b['output_unit']); ?>
                                </div>
                                <div class="text-muted small"><?php echo htmlspecialchars($b['output_name']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-muted border">
                                    Susut: <?php echo number_format($b['waste_qty'], 1); ?> (Yield <?php echo number_format($yieldPct, 1); ?>%)
                                </span>
                            </td>
                            <td class="fw-bold text-dark">
                                <?php echo formatRupiah($b['cost_total']); ?>
                            </td>
                            <td>
                                <span class="badge-soft-success">
                                    <i class="bi bi-check-circle-fill me-1"></i> <?php echo htmlspecialchars($b['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New Batch Modal -->
<div class="modal fade" id="newBatchModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="new_batch">

                <div class="modal-header bg-cream">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-fire text-warning me-2"></i> Mulai Batch Produksi & Roastery Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih Template / Jenis Batch</label>
                        <select name="batch_type" id="batchTypeSelect" class="form-select" onchange="applyBatchPreset(this.value)">
                            <option value="Roasting Arabica Blend">Sangrai Kopi (Green Beans -> House Blend Roasted)</option>
                            <option value="Ekstraksi Cold Brew 10L">Ekstraksi Cold Brew Concentrate (10 Liter Batch)</option>
                            <option value="Sirup Gula Aren Liquid">Pembuatan Sirup Gula Aren Organik Cair</option>
                            <option value="Custom Batch Produksi">Batch Produksi Kustom</option>
                        </select>
                    </div>

                    <div class="row g-3">
                        <!-- Input Section -->
                        <div class="col-12 col-md-6 border-end">
                            <h6 class="fw-bold text-danger mb-3"><i class="bi bi-box-arrow-up me-1"></i> Bahan Baku yang Digunakan (Konsumsi Input)</h6>
                            <div class="mb-3">
                                <label class="form-label small">Pilih Bahan Input</label>
                                <select name="input_material_id" id="inputMaterialSelect" class="form-select" required onchange="updateUnits()">
                                    <option value="">-- Pilih Bahan Baku --</option>
                                    <?php foreach ($rawMaterials as $rm): ?>
                                        <option value="<?php echo $rm['id']; ?>" data-unit="<?php echo $rm['unit']; ?>" data-cost="<?php echo $rm['cost_per_unit']; ?>">
                                            <?php echo htmlspecialchars($rm['name']); ?> (Stok: <?php echo number_format($rm['current_stock']); ?> <?php echo $rm['unit']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Jumlah Digunakan</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" name="input_qty" id="inputQtyInput" class="form-control" placeholder="0" required oninput="calculateYield()">
                                    <span class="input-group-text" id="inputUnitSpan">satuan</span>
                                </div>
                            </div>
                        </div>

                        <!-- Output Section -->
                        <div class="col-12 col-md-6">
                            <h6 class="fw-bold text-success mb-3"><i class="bi bi-box-arrow-in-down me-1"></i> Produk Jadi / Bahan Hasil (Output)</h6>
                            <div class="mb-3">
                                <label class="form-label small">Pilih Bahan Output</label>
                                <select name="output_material_id" id="outputMaterialSelect" class="form-select" required onchange="updateUnits()">
                                    <option value="">-- Pilih Bahan Jadi --</option>
                                    <?php foreach ($rawMaterials as $rm): ?>
                                        <option value="<?php echo $rm['id']; ?>" data-unit="<?php echo $rm['unit']; ?>">
                                            <?php echo htmlspecialchars($rm['name']); ?> (Stok: <?php echo number_format($rm['current_stock']); ?> <?php echo $rm['unit']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Jumlah Hasil Jadi (Yield)</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" name="output_qty" id="outputQtyInput" class="form-control" placeholder="0" required oninput="calculateYield()">
                                    <span class="input-group-text" id="outputUnitSpan">satuan</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Live Conversion Preview -->
                    <div class="p-3 bg-light rounded-3 mt-3 border">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small">Estimasi Rendemen / Yield:</span>
                                <strong id="lblYieldDisplay" class="ms-1 text-dark">-</strong>
                            </div>
                            <div>
                                <span class="text-muted small">Estimasi Total Biaya:</span>
                                <strong id="lblCostDisplay" class="ms-1 text-success">Rp 0</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-coffee btn-sm px-4">
                        <i class="bi bi-play-circle-fill me-1"></i> Proses Batch Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateUnits() {
    const inSel = document.getElementById('inputMaterialSelect');
    const inOpt = inSel.options[inSel.selectedIndex];
    document.getElementById('inputUnitSpan').innerText = inOpt ? (inOpt.getAttribute('data-unit') || 'satuan') : 'satuan';

    const outSel = document.getElementById('outputMaterialSelect');
    const outOpt = outSel.options[outSel.selectedIndex];
    document.getElementById('outputUnitSpan').innerText = outOpt ? (outOpt.getAttribute('data-unit') || 'satuan') : 'satuan';

    calculateYield();
}

function calculateYield() {
    const inQty = parseFloat(document.getElementById('inputQtyInput').value) || 0;
    const outQty = parseFloat(document.getElementById('outputQtyInput').value) || 0;

    const inSel = document.getElementById('inputMaterialSelect');
    const inOpt = inSel.options[inSel.selectedIndex];
    const unitCost = inOpt ? (parseFloat(inOpt.getAttribute('data-cost')) || 0) : 0;

    if (inQty > 0 && outQty > 0) {
        const yieldRatio = (outQty / inQty) * 100;
        document.getElementById('lblYieldDisplay').innerText = yieldRatio.toFixed(1) + '%';
        const totalCost = inQty * unitCost;
        document.getElementById('lblCostDisplay').innerText = formatRupiahJS(totalCost);
    } else {
        document.getElementById('lblYieldDisplay').innerText = '-';
        document.getElementById('lblCostDisplay').innerText = 'Rp 0';
    }
}

function applyBatchPreset(type) {
    if (type.includes('Roasting')) {
        document.getElementById('inputQtyInput').value = 5000;
        document.getElementById('outputQtyInput').value = 4150;
    } else if (type.includes('Cold Brew')) {
        document.getElementById('inputQtyInput').value = 1000;
        document.getElementById('outputQtyInput').value = 9000;
    } else if (type.includes('Gula Aren')) {
        document.getElementById('inputQtyInput').value = 2000;
        document.getElementById('outputQtyInput').value = 2500;
    }
    calculateYield();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
