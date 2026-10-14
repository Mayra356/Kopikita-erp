<?php
$pageTitle = 'Pengaturan Sistem & Toko';
require_once __DIR__ . '/includes/header.php';

// Handle Backup Download
if (isset($_GET['action']) && $_GET['action'] === 'download_db') {
    $dbFile = DB_FILE;
    if (file_exists($dbFile)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/x-sqlite3');
        header('Content-Disposition: attachment; filename="kopikita-backup-' . date('Y-m-d-His') . '.db"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($dbFile));
        readfile($dbFile);
        exit;
    }
}

// Handle Settings Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $keys = [
            'app_name' => trim($_POST['app_name'] ?? 'KopiKita Coffee & Roastery'),
            'tagline' => trim($_POST['tagline'] ?? ''),
            'tax_rate' => (float)($_POST['tax_rate'] ?? 10),
            'service_rate' => (float)($_POST['service_rate'] ?? 0),
            'receipt_footer' => trim($_POST['receipt_footer'] ?? ''),
            'printer_width' => trim($_POST['printer_width'] ?? '58mm')
        ];

        $stmtSet = getDb()->prepare("INSERT OR REPLACE INTO app_settings (key_name, val_text) VALUES (?, ?)");
        foreach ($keys as $k => $v) {
            $stmtSet->execute([$k, (string)$v]);
        }

        setFlashMessage('success', 'Konfigurasi pengaturan toko berhasil disimpan.');
        header('Location: settings.php');
        exit;
    } elseif ($action === 'optimize_db') {
        getDb()->exec("VACUUM; PRAGMA optimize;");
        setFlashMessage('success', 'Database SQLite berhasil dioptimalkan (VACUUM).');
        header('Location: settings.php');
        exit;
    }
}

// Current settings
$appName = getAppSetting('app_name', 'KopiKita Coffee & Roastery');
$tagline = getAppSetting('tagline', 'Integrated Coffee ERP & POS System');
$taxRate = getAppSetting('tax_rate', '10');
$serviceRate = getAppSetting('service_rate', '0');
$receiptFooter = getAppSetting('receipt_footer', "Terima kasih sudah menikmati sajian KopiKita!\nFollow IG: @kopikita.id | WiFi: KopiKita_Guest (pass: nikmatkopi)");
$printerWidth = getAppSetting('printer_width', '58mm');

$dbFileSize = file_exists(DB_FILE) ? round(filesize(DB_FILE) / 1024, 1) : 0;
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Pengaturan Sistem & Konfigurasi Toko</h1>
        <p class="page-subtitle">Atur profil brand, tarif pajak restoran PB1, format struk printer thermal, dan backup database</p>
    </div>
</div>

<div class="row g-4">
    <!-- Left: Store & Tax Settings -->
    <div class="col-12 col-lg-8">
        <form method="POST">
            <input type="hidden" name="action" value="save_settings">

            <div class="card-coffee mb-4">
                <div class="card-header">
                    <span class="fw-bold"><i class="bi bi-gear-wide-connected text-primary me-2"></i> Profil Bisnis & Kedai</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Usaha / Brand ERP</label>
                        <input type="text" name="app_name" class="form-control form-control-lg" value="<?php echo htmlspecialchars($appName); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Slogan / Tagline</label>
                        <input type="text" name="tagline" class="form-control" value="<?php echo htmlspecialchars($tagline); ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Pajak Restoran (PB1 / PPN %)</label>
                            <div class="input-group">
                                <input type="number" step="0.5" name="tax_rate" class="form-control" value="<?php echo htmlspecialchars($taxRate); ?>">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Dihitung otomatis pada transaksi POS.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Service Charge (%)</label>
                            <div class="input-group">
                                <input type="number" step="0.5" name="service_rate" class="form-control" value="<?php echo htmlspecialchars($serviceRate); ?>">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Receipt & Thermal Printer Settings -->
            <div class="card-coffee mb-4">
                <div class="card-header">
                    <span class="fw-bold"><i class="bi bi-printer-fill text-warning me-2"></i> Konfigurasi Struk Kasir (Receipt)</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Lebar Kertas Printer Kasir Thermal</label>
                        <select name="printer_width" class="form-select">
                            <option value="58mm" <?php echo $printerWidth === '58mm' ? 'selected' : ''; ?>>Thermal 58mm (Kecil / Mobile Bluetooth)</option>
                            <option value="80mm" <?php echo $printerWidth === '80mm' ? 'selected' : ''; ?>>Thermal 80mm (Standar POS Desktop)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Pesan Footer Struk Kasir</label>
                        <textarea name="receipt_footer" rows="3" class="form-control"><?php echo htmlspecialchars($receiptFooter); ?></textarea>
                        <div class="form-text">Dapat berisi akun Instagram, nomor kontak, serta username & password WiFi tamu.</div>
                    </div>
                </div>
                <div class="card-footer bg-light p-3 d-flex justify-content-end">
                    <button type="submit" class="btn btn-coffee px-4">
                        <i class="bi bi-check2 me-1"></i> Simpan Konfigurasi
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Right: Database & System Backup -->
    <div class="col-12 col-lg-4">
        <!-- Database Backup Card -->
        <div class="card-coffee mb-4">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-database-fill-gear text-primary me-2"></i> Backup & Database SQLite</span>
            </div>
            <div class="card-body">
                <div class="mb-3 pb-3 border-bottom">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Status Mesin Database:</span>
                        <span class="badge bg-success-subtle text-success border">Active WAL Mode</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Ukuran File Database:</span>
                        <strong class="text-dark"><?php echo $dbFileSize; ?> KB</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Path Penyimpanan:</span>
                        <span class="small text-truncate" style="max-width: 170px;" title="<?php echo DB_FILE; ?>"><?php echo basename(DB_FILE); ?></span>
                    </div>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <a href="settings.php?action=download_db" class="btn btn-outline-coffee">
                        <i class="bi bi-download me-1"></i> Download Backup Database (.db)
                    </a>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="optimize_db">
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-speedometer2 me-1"></i> Optimalkan Database (VACUUM)
                    </button>
                </form>
            </div>
        </div>

        <!-- System Architecture Information -->
        <div class="card-coffee">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-info-circle me-1"></i> Info Versi & Arsitektur</span>
            </div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">KopiKita ERP:</span>
                    <strong>v2.4 Enterprise</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">PHP Engine:</span>
                    <strong><?php echo phpversion(); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Database Engine:</span>
                    <strong>SQLite 3 (PDO)</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Web Server:</span>
                    <strong>Apache / XAMPP</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Integrasi POS:</span>
                    <span class="badge bg-success-subtle text-success">Realtime BOM Linked</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
