<?php
/**
 * KopiKita ERP - Header Component
 * Enhanced with interactive Manager Profile Modal, Role Switcher, Customer Portal link, Command Palette, and Dark Mode
 */
require_once __DIR__ . '/functions.php';

$currentOutlet = getCurrentOutlet();
$allOutlets = getAllOutlets();
$lowStockCount = getLowStockCount($currentOutlet['id']);
$flash = getFlashMessage();
$activePage = basename($_SERVER['PHP_SELF'], '.php');
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | KopiKita ERP' : 'KopiKita - Integrated Coffee Shop ERP & POS'; ?></title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom Theme CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="app-container">
    <!-- Sidebar Navigation -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="app-main">
        <!-- Top Sticky Header -->
        <header class="app-header">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-md-none" id="sidebarToggle">
                    <i class="bi bi-list fs-5"></i>
                </button>
                
                <!-- Outlet Switcher -->
                <div class="outlet-badge">
                    <i class="bi bi-shop text-warning"></i>
                    <select onchange="window.location.href='?outlet_id=' + this.value;" title="Ganti Cabang Aktif">
                        <?php foreach ($allOutlets as $o): ?>
                            <option value="<?php echo $o['id']; ?>" <?php echo $o['id'] == $currentOutlet['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($o['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Spotlight Search Trigger Button (Ctrl+K) -->
                <button class="btn btn-sm btn-light border d-none d-lg-flex align-items-center gap-2 text-muted px-3 rounded-pill" onclick="openCommandPalette()">
                    <i class="bi bi-search"></i>
                    <span>Cari menu, PO, bahan...</span>
                    <kbd class="bg-white border text-dark ms-2" style="font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;">⌘K</kbd>
                </button>

                <?php if ($lowStockCount > 0): ?>
                    <a href="inventory.php?filter=low" class="badge-soft-danger text-decoration-none d-none d-sm-inline-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?php echo $lowStockCount; ?> Bahan Baku Menipis</span>
                    </a>
                <?php endif; ?>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- Customer Self-Order Portal Button -->
                <a href="customer.php" class="btn btn-warning btn-sm d-flex align-items-center gap-2 px-3 fw-bold rounded-pill text-dark shadow-sm" title="Buka Portal Pemesanan Pelanggan">
                    <i class="bi bi-phone-fill"></i>
                    <span class="d-none d-sm-inline">Mode Customer</span>
                    <span class="badge bg-dark text-white rounded-pill" style="font-size: 0.65rem;">Self-Order</span>
                </a>

                <!-- Quick POS Button -->
                <a href="pos.php" class="btn btn-accent btn-sm d-flex align-items-center gap-2 px-3 rounded-pill">
                    <i class="bi bi-calculator-fill"></i>
                    <span class="d-none d-md-inline">POS Kasir</span>
                </a>

                <!-- Dark / Light Mode Toggle Button -->
                <button class="btn btn-sm btn-outline-secondary rounded-circle" id="themeToggleBtn" title="Ganti Tema (Dark/Light)" onclick="toggleTheme()" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-moon-fill" id="themeIcon"></i>
                </button>

                <!-- Interactive User Profile Pill & Manager Switcher -->
                <div class="d-flex align-items-center gap-2 border-start ps-3 ms-1" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#userProfileModal" title="Klik untuk Ganti Nama Manager & Role">
                    <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center border border-warning" style="width: 38px; height: 38px; font-weight: 700; font-size: 0.85rem;" id="headerUserInitials">
                        <?php echo htmlspecialchars($currentUser['initials']); ?>
                    </div>
                    <div class="d-none d-md-block text-start" style="line-height: 1.2;">
                        <div class="fw-bold d-flex align-items-center gap-1" style="font-size: 0.85rem;">
                            <span id="headerUserName"><?php echo htmlspecialchars($currentUser['name']); ?></span>
                            <i class="bi bi-pencil-fill text-muted" style="font-size: 0.7rem;"></i>
                        </div>
                        <div class="text-muted" style="font-size: 0.72rem;" id="headerUserRole"><?php echo htmlspecialchars($currentUser['role']); ?></div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Flash Alert Message -->
        <?php if ($flash): ?>
            <div class="px-4 pt-3">
                <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <?php echo htmlspecialchars($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Body -->
        <main class="page-body">

<!-- Interactive Modal: User Profile & Edit Manager Name -->
<div class="modal fade" id="userProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-cream">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-person-gear text-warning me-2"></i> Profil Pengguna & Ganti Manager
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="update_profile.php" method="POST" id="formUpdateProfile">
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-dark text-white d-inline-flex align-items-center justify-content-center mb-2 border border-warning border-3" style="width: 72px; height: 72px; font-weight: 800; font-size: 1.75rem;">
                            <?php echo htmlspecialchars($currentUser['initials']); ?>
                        </div>
                        <div class="small text-muted">Akun Petugas / Pengguna Aktif Sistem</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Manager / Pengguna <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" name="name" id="inputProfileName" class="form-control form-control-lg fw-bold" 
                                   value="<?php echo htmlspecialchars($currentUser['name']); ?>" placeholder="Masukkan nama manager..." required>
                        </div>
                        <div class="form-text">Nama ini akan tercatat di struk kasir, invoice, dan rekap shift.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Peran / Jabatan (Role)</label>
                        <select name="role" id="selectProfileRole" class="form-select">
                            <option value="Store Manager" <?php echo $currentUser['role'] === 'Store Manager' ? 'selected' : ''; ?>>Store Manager (Kepala Toko)</option>
                            <option value="General Manager" <?php echo $currentUser['role'] === 'General Manager' ? 'selected' : ''; ?>>General Manager / Area Manager</option>
                            <option value="Head Barista" <?php echo $currentUser['role'] === 'Head Barista' ? 'selected' : ''; ?>>Head Barista</option>
                            <option value="Barista & Kasir" <?php echo $currentUser['role'] === 'Barista & Kasir' ? 'selected' : ''; ?>>Barista & Kasir</option>
                            <option value="Owner / Investor" <?php echo $currentUser['role'] === 'Owner / Investor' ? 'selected' : ''; ?>>Owner / Investor Kedai</option>
                        </select>
                    </div>

                    <!-- Mode Switcher Quick Buttons -->
                    <div class="p-3 bg-light rounded-3 mb-2">
                        <div class="fw-bold small text-muted text-uppercase mb-2">Beralih Tampilan / Mode Operasional:</div>
                        <div class="d-flex gap-2">
                            <a href="customer.php" class="btn btn-warning btn-sm flex-fill fw-bold text-dark">
                                <i class="bi bi-phone me-1"></i> Buka Mode Customer
                            </a>
                            <a href="pos.php" class="btn btn-outline-coffee btn-sm flex-fill fw-bold">
                                <i class="bi bi-calculator me-1"></i> Buka Layar Kasir
                            </a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-coffee btn-sm px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Nama & Profil
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Interactive Modal: Spotlight Command Palette (Cmd+K / Ctrl+K) -->
<div class="modal fade" id="commandPaletteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body p-3">
                <div class="input-group input-group-lg border-bottom pb-2 mb-3">
                    <span class="input-group-text bg-transparent border-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="cmdPaletteInput" class="form-control border-0 bg-transparent fs-5" placeholder="Ketik navigasi atau aksi cepat (e.g. pos, stok, aren, po)..." autofocus>
                    <span class="input-group-text bg-transparent border-0"><kbd class="text-muted small">ESC</kbd></span>
                </div>

                <div class="small text-muted text-uppercase fw-bold px-2 mb-2">Navigasi Cepat & Modul</div>
                <div class="list-group list-group-flush" id="cmdPaletteResults">
                    <a href="customer.php" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2 border-0 rounded-2">
                        <div class="rounded bg-warning text-dark p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;"><i class="bi bi-phone-fill"></i></div>
                        <div>
                            <div class="fw-bold">Mode Customer (Pesan Mandiri & Keranjang)</div>
                            <div class="text-muted" style="font-size: 0.75rem;">customer.php &bull; Layar pemesanan untuk pelanggan meja</div>
                        </div>
                    </a>
                    <a href="pos.php" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2 border-0 rounded-2">
                        <div class="rounded bg-primary text-white p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;"><i class="bi bi-calculator"></i></div>
                        <div>
                            <div class="fw-bold">Point of Sale (Kasir Cepat)</div>
                            <div class="text-muted" style="font-size: 0.75rem;">pos.php &bull; Input pesanan dine-in & takeaway kasir</div>
                        </div>
                    </a>
                    <a href="menu.php" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2 border-0 rounded-2">
                        <div class="rounded bg-secondary text-white p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;"><i class="bi bi-book"></i></div>
                        <div>
                            <div class="fw-bold">Katalog Menu & Resep BOM</div>
                            <div class="text-muted" style="font-size: 0.75rem;">menu.php &bull; Atur harga, racikan gramatur, dan HPP</div>
                        </div>
                    </a>
                    <a href="inventory.php" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2 border-0 rounded-2">
                        <div class="rounded bg-success text-white p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;"><i class="bi bi-boxes"></i></div>
                        <div>
                            <div class="fw-bold">Stok Bahan Baku & Kemasan</div>
                            <div class="text-muted" style="font-size: 0.75rem;">inventory.php &bull; Monitor safety stock biji kopi & susu</div>
                        </div>
                    </a>
                    <a href="finance.php" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2 border-0 rounded-2">
                        <div class="rounded bg-info text-white p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;"><i class="bi bi-pie-chart-fill"></i></div>
                        <div>
                            <div class="fw-bold">Laporan Laba Rugi & Arus Kas</div>
                            <div class="text-muted" style="font-size: 0.75rem;">finance.php &bull; Analisis gross profit, net margin & omzet</div>
                        </div>
                    </a>
                    <a href="stock_card.php" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2 border-0 rounded-2">
                        <div class="rounded bg-dark text-white p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;"><i class="bi bi-card-checklist"></i></div>
                        <div>
                            <div class="fw-bold">Kartu Stok Mutasi (Audit Trail)</div>
                            <div class="text-muted" style="font-size: 0.75rem;">stock_card.php &bull; Log mutasi bahan masuk dan keluar</div>
                        </div>
                    </a>
                    <a href="production.php" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2 border-0 rounded-2">
                        <div class="rounded bg-danger text-white p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;"><i class="bi bi-fire"></i></div>
                        <div>
                            <div class="fw-bold">Batch Roastery & Produksi</div>
                            <div class="text-muted" style="font-size: 0.75rem;">production.php &bull; Sangrai green beans & batching cold brew</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
