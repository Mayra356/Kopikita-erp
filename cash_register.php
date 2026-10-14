<?php
$pageTitle = 'Kasir & Rekap Harian (Cash Register)';
require_once __DIR__ . '/includes/header.php';

$outletId = $currentOutlet['id'];

// Check current open register
$openRegister = dbFetchOne("
    SELECT * FROM cash_registers 
    WHERE outlet_id = ? AND status = 'Open' 
    ORDER BY id DESC LIMIT 1
", [$outletId]);

// Handle Actions: Open, Cash In/Out, Close
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'open_register') {
        $cashierName = trim($_POST['cashier_name'] ?? 'Barista');
        $openingCash = (float)($_POST['opening_cash'] ?? 300000);
        $notes = trim($_POST['notes'] ?? '');

        dbInsert('cash_registers', [
            'outlet_id' => $outletId,
            'cashier_name' => $cashierName,
            'opened_at' => date('Y-m-d H:i:s'),
            'opening_cash' => $openingCash,
            'cash_sales' => 0,
            'non_cash_sales' => 0,
            'cash_in' => 0,
            'cash_out' => 0,
            'expected_cash' => $openingCash,
            'actual_cash' => 0,
            'variance' => 0,
            'status' => 'Open',
            'notes' => $notes
        ]);

        setFlashMessage('success', "Shift kasir berhasil dibuka untuk {$cashierName} dengan modal awal " . formatRupiah($openingCash));
        header('Location: cash_register.php');
        exit;
    } elseif ($action === 'cash_movement' && $openRegister) {
        $moveType = $_POST['move_type']; // 'IN' or 'OUT'
        $amount = (float)$_POST['amount'];
        $reason = trim($_POST['reason'] ?? '');

        if ($amount > 0) {
            if ($moveType === 'IN') {
                dbUpdate('cash_registers', [
                    'cash_in' => (float)$openRegister['cash_in'] + $amount
                ], 'id = :id', ['id' => $openRegister['id']]);

                recordJournalEntry("Kas Masuk Tambahan Kasir: {$reason}", "CSH-IN-" . date('ymd-His'), 1101, 3101, $amount);
                setFlashMessage('success', "Kas masuk sebesar " . formatRupiah($amount) . " berhasil dicatat.");
            } else {
                dbUpdate('cash_registers', [
                    'cash_out' => (float)$openRegister['cash_out'] + $amount
                ], 'id = :id', ['id' => $openRegister['id']]);

                recordJournalEntry("Kas Keluar Petty Cash Kasir: {$reason}", "CSH-OUT-" . date('ymd-His'), 6104, 1101, $amount);
                setFlashMessage('warning', "Kas keluar sebesar " . formatRupiah($amount) . " berhasil dicatat.");
            }
            header('Location: cash_register.php');
            exit;
        }
    } elseif ($action === 'close_register' && $openRegister) {
        $actualCash = (float)$_POST['actual_cash'];
        $closeNotes = trim($_POST['notes'] ?? '');

        $expected = (float)$openRegister['opening_cash'] + (float)$openRegister['cash_sales'] + (float)$openRegister['cash_in'] - (float)$openRegister['cash_out'];
        $variance = $actualCash - $expected;

        dbUpdate('cash_registers', [
            'closed_at' => date('Y-m-d H:i:s'),
            'expected_cash' => $expected,
            'actual_cash' => $actualCash,
            'variance' => $variance,
            'status' => 'Closed',
            'notes' => ($openRegister['notes'] ? $openRegister['notes'] . " | " : "") . $closeNotes
        ], 'id = :id', ['id' => $openRegister['id']]);

        setFlashMessage('info', "Shift kasir resmi ditutup! Selisih kas: " . formatRupiah($variance));
        header('Location: cash_register.php?z_report=' . $openRegister['id']);
        exit;
    }
}

// Fetch past registers
$pastRegisters = dbFetchAll("
    SELECT * FROM cash_registers 
    WHERE outlet_id = ? 
    ORDER BY id DESC 
    LIMIT 15
", [$outletId]);

// Calculate live expected cash for open register
$currentExpected = 0;
if ($openRegister) {
    $currentExpected = (float)$openRegister['opening_cash'] + (float)$openRegister['cash_sales'] + (float)$openRegister['cash_in'] - (float)$openRegister['cash_out'];
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Rekap Kasir Harian & Shift (Cash Register)</h1>
        <p class="page-subtitle">Kontrol uang laci kasir, pencatatan petty cash kas keluar, dan penutupan Z-Report harian</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="pos.php" class="btn btn-outline-coffee">
            <i class="bi bi-calculator me-1"></i> Buka Terminal POS
        </a>
    </div>
</div>

<?php if (isset($_GET['z_report'])): ?>
    <?php 
        $zrId = (int)$_GET['z_report'];
        $zr = dbFetchOne("SELECT * FROM cash_registers WHERE id = ?", [$zrId]);
    ?>
    <?php if ($zr): ?>
        <div class="card-coffee border-success-subtle mb-4" id="zReportCard">
            <div class="card-header bg-success-subtle text-success d-flex justify-content-between align-items-center">
                <div class="fw-bold"><i class="bi bi-file-earmark-check-fill me-2"></i> Ringkasan Z-Report Penutupan Kasir (Shift Selesai)</div>
                <button class="btn btn-sm btn-outline-success" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Cetak Z-Report
                </button>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-12 col-md-4 border-end">
                        <div class="text-muted small">Kasir Bertugas</div>
                        <div class="fw-bold fs-5"><?php echo htmlspecialchars($zr['cashier_name']); ?></div>
                        <div class="text-muted small mt-2">Waktu Buka: <?php echo formatDate($zr['opened_at']); ?></div>
                        <div class="text-muted small">Waktu Tutup: <?php echo formatDate($zr['closed_at']); ?></div>
                    </div>
                    <div class="col-12 col-md-4 border-end">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Modal Kas Awal:</span>
                            <span class="fw-bold"><?php echo formatRupiah($zr['opening_cash']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Penjualan Tunai:</span>
                            <span class="fw-bold text-success">+<?php echo formatRupiah($zr['cash_sales']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Penjualan Non-Tunai (QRIS/EDC):</span>
                            <span class="fw-bold text-info"><?php echo formatRupiah($zr['non_cash_sales']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Kas Masuk Tambahan:</span>
                            <span class="fw-bold text-success">+<?php echo formatRupiah($zr['cash_in']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Kas Keluar (Petty Cash):</span>
                            <span class="fw-bold text-danger">-<?php echo formatRupiah($zr['cash_out']); ?></span>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="p-3 bg-light rounded-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Kas Fisik Seharusnya:</span>
                                <strong class="fs-6"><?php echo formatRupiah($zr['expected_cash']); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Kas Fisik Dihitung:</span>
                                <strong class="fs-6 text-primary"><?php echo formatRupiah($zr['actual_cash']); ?></strong>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Selisih Kas (Variance):</span>
                                <span class="badge <?php echo $zr['variance'] == 0 ? 'bg-success' : ($zr['variance'] > 0 ? 'bg-info' : 'bg-danger'); ?> fs-6">
                                    <?php echo ($zr['variance'] > 0 ? '+' : '') . formatRupiah($zr['variance']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Active Register Status Card -->
<?php if ($openRegister): ?>
    <div class="card-coffee border-primary-subtle mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success text-white py-1 px-2">
                    <i class="bi bi-circle-fill" style="font-size: 0.6rem;"></i> Shift Kasir Sedang Buka
                </span>
                <span class="fw-bold ms-2"><?php echo htmlspecialchars($openRegister['cashier_name']); ?></span>
                <span class="text-muted small">(Dibuka sejak <?php echo formatDate($openRegister['opened_at'], 'time'); ?> WIB)</span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#cashMovementModal">
                    <i class="bi bi-arrow-left-right me-1"></i> Catat Kas Masuk / Keluar
                </button>
                <button class="btn btn-sm btn-danger px-3" data-bs-toggle="modal" data-bs-target="#closeRegisterModal">
                    <i class="bi bi-lock-fill me-1"></i> Tutup Kasir & Z-Report
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-6 col-md-2 border-end">
                    <div class="text-muted small">Modal Awal Kas</div>
                    <div class="fw-bold fs-5 text-dark mt-1"><?php echo formatRupiah($openRegister['opening_cash']); ?></div>
                </div>
                <div class="col-6 col-md-2 border-end">
                    <div class="text-muted small">Penjualan Tunai</div>
                    <div class="fw-bold fs-5 text-success mt-1">+<?php echo formatRupiah($openRegister['cash_sales']); ?></div>
                </div>
                <div class="col-6 col-md-2 border-end">
                    <div class="text-muted small">QRIS / Non-Tunai</div>
                    <div class="fw-bold fs-5 text-info mt-1"><?php echo formatRupiah($openRegister['non_cash_sales']); ?></div>
                </div>
                <div class="col-6 col-md-2 border-end">
                    <div class="text-muted small">Kas Masuk Tambahan</div>
                    <div class="fw-bold fs-5 text-success mt-1">+<?php echo formatRupiah($openRegister['cash_in']); ?></div>
                </div>
                <div class="col-6 col-md-2 border-end">
                    <div class="text-muted small">Kas Keluar Operasional</div>
                    <div class="fw-bold fs-5 text-danger mt-1">-<?php echo formatRupiah($openRegister['cash_out']); ?></div>
                </div>
                <div class="col-12 col-md-2 bg-light rounded-3 py-2">
                    <div class="text-muted small">Uang Kas di Laci Saat Ini</div>
                    <div class="fw-bold fs-5 text-primary mt-1"><?php echo formatRupiah($currentExpected); ?></div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Open Register Callout -->
    <div class="card-coffee border-warning-subtle mb-4">
        <div class="card-body p-4 text-center">
            <div class="rounded-circle bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; font-size: 1.75rem;">
                <i class="bi bi-cash-stack"></i>
            </div>
            <h4 class="fw-bold">Belum Ada Shift Kasir yang Dibuka</h4>
            <p class="text-muted" style="max-width: 500px; margin: 0 auto 20px;">
                Buka shift kasir terlebih dahulu untuk mencatat modal kas awal laci sebelum memulai melayani pesanan di Point of Sale (POS).
            </p>
            <button class="btn btn-coffee px-4" data-bs-toggle="modal" data-bs-target="#openRegisterModal">
                <i class="bi bi-unlock-fill me-1"></i> Buka Shift Kasir Baru
            </button>
        </div>
    </div>
<?php endif; ?>

<!-- Past Cash Registers Table -->
<div class="card-coffee">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-primary"></i>
            <span class="fw-bold">Riwayat Shift Kasir & Rekap Harian</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table-coffee table-hover">
            <thead>
                <tr>
                    <th>Waktu Buka / Tutup</th>
                    <th>Kasir Bertugas</th>
                    <th>Modal Awal</th>
                    <th>Total Tunai</th>
                    <th>Total Non-Tunai</th>
                    <th>Fisik di Laci</th>
                    <th>Selisih Kas</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pastRegisters)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">Belum ada riwayat shift kasir</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pastRegisters as $pr): ?>
                        <tr>
                            <td>
                                <div style="font-size: 0.85rem; font-weight: 500;"><?php echo formatDate($pr['opened_at'], 'date'); ?></div>
                                <div class="text-muted small">
                                    <?php echo formatDate($pr['opened_at'], 'time'); ?> - <?php echo $pr['closed_at'] ? formatDate($pr['closed_at'], 'time') : 'Aktif'; ?>
                                </div>
                            </td>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($pr['cashier_name']); ?></td>
                            <td><?php echo formatRupiah($pr['opening_cash']); ?></td>
                            <td class="text-success fw-bold"><?php echo formatRupiah($pr['cash_sales']); ?></td>
                            <td class="text-info"><?php echo formatRupiah($pr['non_cash_sales']); ?></td>
                            <td class="fw-bold"><?php echo formatRupiah($pr['actual_cash'] ?: $pr['expected_cash']); ?></td>
                            <td>
                                <?php if ($pr['status'] === 'Closed'): ?>
                                    <span class="badge <?php echo $pr['variance'] == 0 ? 'bg-success-subtle text-success' : ($pr['variance'] > 0 ? 'bg-info-subtle text-info' : 'bg-danger-subtle text-danger'); ?> border">
                                        <?php echo ($pr['variance'] > 0 ? '+' : '') . formatRupiah($pr['variance']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-soft-<?php echo $pr['status'] === 'Open' ? 'success' : 'secondary'; ?>">
                                    <?php echo htmlspecialchars($pr['status']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($pr['status'] === 'Closed'): ?>
                                    <a href="cash_register.php?z_report=<?php echo $pr['id']; ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Lihat Z-Report">
                                        <i class="bi bi-file-earmark-text"></i> Z-Report
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-light text-success border">Sedang Berjalan</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Buka Kasir -->
<div class="modal fade" id="openRegisterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="open_register">
                <div class="modal-header bg-cream">
                    <h5 class="modal-title fw-bold"><i class="bi bi-unlock-fill text-warning me-2"></i> Buka Shift Kasir Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Kasir Bertugas <span class="text-danger">*</span></label>
                        <input type="text" name="cashier_name" class="form-control" value="Barista Kasir" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Modal Awal Kas di Laci (Float) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" step="1000" name="opening_cash" class="form-control form-control-lg fw-bold" value="300000" required>
                        </div>
                        <div class="form-text">Uang pecahan kecil untuk kembalian transaksi tunai awal.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Catatan Shift</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Catatan shift pagi / operasional..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-coffee btn-sm px-4">Buka Shift Sekarang</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Catat Kas Masuk / Kas Keluar (Petty Cash) -->
<?php if ($openRegister): ?>
<div class="modal fade" id="cashMovementModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="cash_movement">
                <div class="modal-header bg-cream">
                    <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right text-warning me-2"></i> Kas Masuk / Keluar (Petty Cash)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tipe Arus Kas</label>
                        <select name="move_type" class="form-select" required>
                            <option value="OUT">Kas Keluar (Beli Es Batu / Galon / Operasional Toko)</option>
                            <option value="IN">Kas Masuk (Tambah Modal Kembalian dari Brankas)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nominal Jumlah (IDR) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" step="500" name="amount" class="form-control fw-bold" placeholder="50000" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan / Keperluan <span class="text-danger">*</span></label>
                        <input type="text" name="reason" class="form-control" placeholder="Contoh: Beli es kristal darurat 2 karung" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-coffee btn-sm">Simpan Arus Kas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tutup Kasir & Z-Report -->
<div class="modal fade" id="closeRegisterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="close_register">
                <div class="modal-header bg-cream">
                    <h5 class="modal-title fw-bold"><i class="bi bi-lock-fill text-danger me-2"></i> Tutup Shift Kasir (Z-Report)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="p-3 bg-light rounded-3 mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Modal Awal:</span>
                            <span><?php echo formatRupiah($openRegister['opening_cash']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Penjualan Tunai:</span>
                            <span class="text-success">+<?php echo formatRupiah($openRegister['cash_sales']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Kas Masuk / Keluar Net:</span>
                            <span><?php echo formatRupiah($openRegister['cash_in'] - $openRegister['cash_out']); ?></span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <strong>Uang Kas Seharusnya di Laci:</strong>
                            <strong class="text-primary fs-6"><?php echo formatRupiah($currentExpected); ?></strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Hasil Hitung Fisik Uang di Laci Kasir <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" step="500" name="actual_cash" id="closeActualCashInput" 
                                   class="form-control form-control-lg fw-bold" 
                                   value="<?php echo $currentExpected; ?>" required oninput="calcCloseVariance(this.value)">
                        </div>
                        <div class="form-text">Hitung seluruh lembar dan koin fisik di laci kasir saat ini.</div>
                    </div>

                    <div class="p-3 border rounded-3 mb-3 d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Estimasi Selisih (Variance):</span>
                        <strong id="lblCloseVariance" class="text-success">Pas (Rp 0)</strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Catatan Penutupan</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Keterangan penyerahan ke brankas atau selisih..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4">Tutup Kasir Sekarang</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const expectedCashAmount = <?php echo (float)$currentExpected; ?>;

function calcCloseVariance(actualVal) {
    const act = parseFloat(actualVal) || 0;
    const diff = act - expectedCashAmount;
    const lbl = document.getElementById('lblCloseVariance');
    if (Math.abs(diff) < 1) {
        lbl.innerText = 'Pas (Rp 0)';
        lbl.className = 'text-success fw-bold';
    } else if (diff > 0) {
        lbl.innerText = '+ Lebih ' + formatRupiahJS(diff);
        lbl.className = 'text-info fw-bold';
    } else {
        lbl.innerText = '- Kurang ' + formatRupiahJS(Math.abs(diff));
        lbl.className = 'text-danger fw-bold';
    }
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
