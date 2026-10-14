<?php
$pageTitle = 'Jurnal Umum & Buku Besar (Double-Entry)';
require_once __DIR__ . '/includes/header.php';

// Handle Manual Journal Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_journal') {
    $desc = trim($_POST['description'] ?? '');
    $refNo = trim($_POST['reference_no'] ?? 'JRN-MANUAL');
    $debitAcc = (int)$_POST['debit_account'];
    $creditAcc = (int)$_POST['credit_account'];
    $amount = (float)$_POST['amount'];
    $date = trim($_POST['entry_date'] ?? date('Y-m-d'));

    if (!empty($desc) && $amount > 0 && $debitAcc > 0 && $creditAcc > 0) {
        recordJournalEntry($desc, $refNo, $debitAcc, $creditAcc, $amount, $date);
        setFlashMessage('success', 'Jurnal penyesuaian manual berhasil dibukukan.');
        header('Location: journals.php');
        exit;
    }
}

// Fetch Chart of Accounts
$accounts = dbFetchAll("SELECT * FROM gl_accounts ORDER BY code ASC");
$accMap = [];
foreach ($accounts as $a) {
    $accMap[$a['code']] = $a['name'];
}

// Fetch Journal Entries
$journals = dbFetchAll("
    SELECT j.*, 
           da.name as debit_name, da.type as debit_type,
           ca.name as credit_name, ca.type as credit_type
    FROM journal_entries j
    LEFT JOIN gl_accounts da ON j.debit_account = da.code
    LEFT JOIN gl_accounts ca ON j.credit_account = ca.code
    ORDER BY j.id DESC
");

$totalDebitSum = array_sum(array_column($journals, 'amount'));
$totalCreditSum = $totalDebitSum; // double-entry always balanced per row
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Jurnal Umum & Buku Besar (General Ledger)</h1>
        <p class="page-subtitle">Sistem pembukuan double-entry otomatis terintegrasi penjualan POS, pengadaan PO, dan HPP</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#newJournalModal">
            <i class="bi bi-plus-circle me-1"></i> Input Jurnal Manual
        </button>
    </div>
</div>

<!-- Balance Status Bar -->
<div class="card-coffee border-success-subtle mb-4">
    <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="bi bi-shield-check fs-4"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-0 text-success">Status Pembukuan: Balanced (Seimbang)</h5>
                <div class="text-muted small">Total Debit: <strong><?php echo formatRupiah($totalDebitSum); ?></strong> = Total Kredit: <strong><?php echo formatRupiah($totalCreditSum); ?></strong></div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-light text-dark border p-2">Total <?php echo count($journals); ?> Ayat Jurnal</span>
        </div>
    </div>
</div>

<!-- Chart of Accounts Pill Accordion -->
<div class="card-coffee mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-list-columns-reverse text-warning me-1"></i> Bagan Akun Standar (Chart of Accounts - COA)</span>
        <button class="btn btn-sm btn-link text-decoration-none text-muted p-0" type="button" data-bs-toggle="collapse" data-bs-target="#coaCollapse">
            Tampilkan / Sembunyikan Akun
        </button>
    </div>
    <div class="collapse show" id="coaCollapse">
        <div class="card-body p-3">
            <div class="row g-2">
                <?php foreach ($accounts as $acc): ?>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="p-2 border rounded-3 bg-light d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-secondary text-white me-1"><?php echo $acc['code']; ?></span>
                                <span class="fw-bold text-dark small"><?php echo htmlspecialchars($acc['name']); ?></span>
                            </div>
                            <span class="badge bg-white text-muted border small"><?php echo htmlspecialchars($acc['type']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Journals Table -->
<div class="card-coffee">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-journal-bookmark-fill text-primary"></i>
            <span class="fw-bold">Buku Jurnal Umum Terperinci</span>
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Cetak Jurnal
        </button>
    </div>
    <div class="table-responsive">
        <table class="table-coffee table-hover">
            <thead>
                <tr>
                    <th>No. Jurnal & Waktu</th>
                    <th>No. Referensi</th>
                    <th>Deskripsi / Transaksi</th>
                    <th>Akun Debit</th>
                    <th>Akun Kredit</th>
                    <th class="text-end">Jumlah (Debit = Kredit)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($journals)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">Belum ada ayat jurnal pembukuan</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($journals as $j): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($j['entry_no']); ?></div>
                                <div class="text-muted small"><?php echo formatDate($j['entry_date'], 'date'); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?php echo htmlspecialchars($j['reference_no'] ?: '-'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark" style="max-width: 280px;"><?php echo htmlspecialchars($j['description']); ?></div>
                            </td>
                            <td>
                                <div class="text-success fw-bold">
                                    [<?php echo $j['debit_account']; ?>] <?php echo htmlspecialchars($j['debit_name'] ?? $accMap[$j['debit_account']] ?? 'Akun Debit'); ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-danger fw-bold">
                                    [<?php echo $j['credit_account']; ?>] <?php echo htmlspecialchars($j['credit_name'] ?? $accMap[$j['credit_account']] ?? 'Akun Kredit'); ?>
                                </div>
                            </td>
                            <td class="text-end fw-bold fs-6 text-dark">
                                <?php echo formatRupiah($j['amount']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Input Jurnal Manual -->
<div class="modal fade" id="newJournalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save_journal">

                <div class="modal-header bg-cream">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-journal-plus text-warning me-2"></i> Input Ayat Jurnal Manual
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal Jurnal</label>
                        <input type="date" name="entry_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">No. Referensi / Bukti Transaksi</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="Contoh: BKK-001, KWT-109">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan / Deskripsi Transaksi <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" placeholder="Contoh: Bayar listrik dan WiFi kedai bulan ini" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Akun Debit (Bertambah) <span class="text-danger">*</span></label>
                        <select name="debit_account" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?php echo $acc['code']; ?>">
                                    [<?php echo $acc['code']; ?>] <?php echo htmlspecialchars($acc['name']); ?> (<?php echo $acc['type']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Akun Kredit (Berkurang) <span class="text-danger">*</span></label>
                        <select name="credit_account" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?php echo $acc['code']; ?>" <?php echo $acc['code'] == 1001 ? 'selected' : ''; ?>>
                                    [<?php echo $acc['code']; ?>] <?php echo htmlspecialchars($acc['name']); ?> (<?php echo $acc['type']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Nominal Jumlah (IDR) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" step="1000" name="amount" class="form-control form-control-lg fw-bold" placeholder="500000" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-coffee btn-sm px-4">Bukukan Jurnal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
