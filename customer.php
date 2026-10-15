<?php
/**
 * KopiKita - Customer Self-Order & Online Dining Portal
 * Interactive customer menu, item customizer, shopping cart, and live order tracker
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$currentOutlet = getCurrentOutlet();
$allOutlets = getAllOutlets();
$categories = dbFetchAll("SELECT * FROM categories ORDER BY sort_order ASC");
$products = dbFetchAll("
    SELECT p.*, c.name as category_name, c.slug as category_slug
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1
    ORDER BY p.category_id ASC, p.name ASC
");

// Pre-load members for loyalty discount testing
$members = dbFetchAll("SELECT * FROM customers ORDER BY name ASC");
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Menu KopiKita | Self-Order & Dine-In</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom Theme CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        :root {
            --cust-primary: #5C3D2E;
            --cust-accent: #D4A373;
            --cust-cream: #FAF7F2;
        }

        body {
            background-color: var(--cream);
            color: var(--text-primary);
            padding-bottom: 90px;
        }

        /* Top Bar */
        .cust-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1040;
            box-shadow: 0 4px 16px rgba(45, 27, 22, 0.05);
        }

        /* Hero Banner */
        .cust-hero {
            background: linear-gradient(135deg, #2D1B16 0%, #5C3D2E 60%, #865439 100%);
            color: #FAF7F2;
            border-radius: 20px;
            padding: 36px 30px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .cust-hero::after {
            content: "\F2E8";
            font-family: "bootstrap-icons";
            position: absolute;
            right: -20px;
            bottom: -30px;
            font-size: 160px;
            color: rgba(255, 255, 255, 0.05);
            pointer-events: none;
        }

        /* Category Scroll */
        .cust-cat-nav {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 8px;
            margin-bottom: 24px;
            scrollbar-width: none;
        }
        .cust-cat-nav::-webkit-scrollbar { display: none; }

        .cust-cat-pill {
            background: var(--surface);
            border: 1.5px solid var(--border-color);
            border-radius: 30px;
            padding: 9px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cust-cat-pill:hover, .cust-cat-pill.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #FFFFFF;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(92, 61, 46, 0.2);
        }

        /* Product Card */
        .cust-card {
            background: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex;
            flex-direction: column;
            height: 100%;
            transition: all 0.25s cubic-bezier(0.165, 0.84, 0.44, 1);
            position: relative;
        }

        .cust-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--accent);
        }

        .cust-thumb {
            width: 56px;
            height: 56px;
            background: rgba(212, 163, 115, 0.15);
            color: var(--primary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 14px;
        }

        /* Floating Cart Bar */
        .floating-cart-bar {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(120px);
            width: 90%;
            max-width: 600px;
            background: linear-gradient(135deg, #2D1B16 0%, #1A0F0A 100%);
            color: #FFFFFF;
            border-radius: 40px;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            z-index: 1050;
            transition: transform 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
        }

        .floating-cart-bar.show {
            transform: translateX(-50%) translateY(0);
        }

        /* Status Tracker */
        .tracker-step {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
            position: relative;
        }

        .tracker-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #E9ECEF;
            color: #6C757D;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: bold;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .tracker-step.active .tracker-icon {
            background: var(--accent);
            color: #2D1B16;
            box-shadow: 0 0 0 4px rgba(212, 163, 115, 0.25);
            animation: pulse 1.8s infinite;
        }

        .tracker-step.completed .tracker-icon {
            background: #2A9D8F;
            color: #FFFFFF;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.08); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>

<!-- Top Sticky Navbar -->
<header class="cust-header py-3 px-3 px-md-4">
    <div class="container d-flex align-items-center justify-content-between">
        <!-- Brand -->
        <a href="customer.php" class="d-flex align-items-center gap-2 text-decoration-none">
            <div class="rounded-3 d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; background: linear-gradient(135deg, #D4A373 0%, #5C3D2E 100%);">
                <i class="bi bi-cup-hot-fill fs-5"></i>
            </div>
            <div>
                <span class="fw-bold fs-5 text-dark" style="letter-spacing: -0.5px;">KopiKita</span>
                <span class="badge bg-warning text-dark ms-1" style="font-size: 0.68rem;">Customer Portal</span>
            </div>
        </a>

        <!-- Controls: Table, Outlet, Switch to Staff -->
        <div class="d-flex align-items-center gap-2">
            <!-- Outlet Switcher -->
            <div class="d-none d-sm-flex align-items-center gap-1 border rounded-pill px-3 py-1 bg-white small">
                <i class="bi bi-geo-alt-fill text-danger"></i>
                <select class="border-0 bg-transparent fw-bold" style="outline: none;" onchange="window.location.href='?outlet_id=' + this.value;">
                    <?php foreach ($allOutlets as $o): ?>
                        <option value="<?php echo $o['id']; ?>" <?php echo $o['id'] == $currentOutlet['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($o['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Table Number Pill -->
            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="openTablePicker()">
                <i class="bi bi-shop me-1 text-primary"></i> <span id="lblSelectedTable">Meja #04</span>
            </button>

            <!-- Sound toggle -->
            <button class="btn btn-sm btn-outline-secondary rounded-circle" id="custSoundToggle" title="Toggle Efek Suara" onclick="toggleAudioSFX()">
                <i class="bi bi-volume-up-fill"></i>
            </button>

            <!-- Cart Trigger Button -->
            <button class="btn btn-coffee btn-sm rounded-pill px-3 d-flex align-items-center gap-2" onclick="openCartModal()">
                <i class="bi bi-cart3 fs-6"></i>
                <span class="badge bg-white text-dark rounded-pill" id="cartBadgeCount">0</span>
            </button>

            <!-- Switch Back to Manager/ERP -->
            <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 d-none d-md-inline-flex align-items-center gap-1" title="Kembali ke Dashboard ERP">
                <i class="bi bi-shield-lock"></i> Mode Staff / Manager
            </a>
        </div>
    </div>
</header>

<main class="container mt-4">
    <!-- Hero Banner -->
    <div class="cust-hero">
        <div class="row align-items-center">
            <div class="col-12 col-md-8">
                <div class="badge bg-warning text-dark px-3 py-1 mb-2 rounded-pill fw-bold">
                    <i class="bi bi-stars"></i> Sajian Kopi Spesialti & Pastry Hangat
                </div>
                <h1 class="display-6 fw-bold mb-2">Mau ngopi apa hari ini?</h1>
                <p class="mb-3 text-light" style="opacity: 0.9;">
                    Pesan langsung dari meja Anda di <strong><?php echo htmlspecialchars($currentOutlet['name']); ?></strong>. 
                    Barista kami akan segera meraciknya segar untuk Anda.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <div class="btn-group rounded-pill p-1 bg-white bg-opacity-25" role="group">
                        <input type="radio" class="btn-check" name="orderTypeRadio" id="optDineIn" value="Dine In" checked onchange="setOrderType('Dine In')">
                        <label class="btn btn-sm btn-outline-light rounded-pill px-3 border-0" for="optDineIn">
                            <i class="bi bi-shop me-1"></i> Dine In (Di Meja)
                        </label>

                        <input type="radio" class="btn-check" name="orderTypeRadio" id="optTakeAway" value="Take Away" onchange="setOrderType('Take Away')">
                        <label class="btn btn-sm btn-outline-light rounded-pill px-3 border-0" for="optTakeAway">
                            <i class="bi bi-bag-check me-1"></i> Take Away (Bawa Pulang)
                        </label>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 d-none d-md-block text-end">
                <div class="p-3 bg-white bg-opacity-10 rounded-4 text-start border border-light border-opacity-25">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-award-fill text-warning fs-4"></i>
                        <span class="fw-bold">Member Loyalitas</span>
                    </div>
                    <p class="small text-light mb-2">Gunakan nomor WhatsApp Anda untuk diskon otomatis <strong>5% - 10%</strong> dan dapatkan poin belanja!</p>
                    <button class="btn btn-warning btn-sm w-100 fw-bold rounded-pill text-dark" onclick="openMemberCheckModal()">
                        <i class="bi bi-search me-1"></i> Cek Poin & Diskon Member
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="row g-3 mb-3 align-items-center">
        <div class="col-12 col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-3">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" id="custSearchInput" class="form-control border-start-0 rounded-end-pill py-2" placeholder="Cari kopi, aren, matcha, croissant...">
            </div>
        </div>
        <div class="col-12 col-md-7">
            <!-- Categories Pills -->
            <div class="cust-cat-nav mb-0">
                <button class="cust-cat-pill active" data-cat="all" onclick="filterCustCategory('all', this)">
                    <i class="bi bi-grid-fill"></i> Semua Menu
                </button>
                <?php foreach ($categories as $cat): ?>
                    <button class="cust-cat-pill" data-cat="<?php echo htmlspecialchars($cat['slug']); ?>" onclick="filterCustCategory('<?php echo htmlspecialchars($cat['slug']); ?>', this)">
                        <i class="bi <?php echo htmlspecialchars($cat['icon']); ?>"></i>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="row g-3" id="custProductGrid">
        <?php foreach ($products as $p): ?>
            <div class="col-6 col-md-4 col-lg-3 cust-item-col" 
                 data-category="<?php echo htmlspecialchars($p['category_slug']); ?>" 
                 data-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>">
                
                <div class="cust-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="cust-thumb">
                            <i class="bi <?php echo htmlspecialchars($p['image_icon'] ?: 'bi-cup-hot'); ?>"></i>
                        </div>
                        <span class="badge bg-light text-muted border small"><?php echo htmlspecialchars($p['category_name']); ?></span>
                    </div>

                    <h5 class="fw-bold fs-6 text-dark mb-1"><?php echo htmlspecialchars($p['name']); ?></h5>
                    <p class="text-muted small text-truncate-2 mb-3" style="min-height: 38px; font-size: 0.8rem; line-height: 1.3;">
                        <?php echo htmlspecialchars($p['description']); ?>
                    </p>

                    <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top">
                        <div class="fw-bold fs-6 text-primary"><?php echo formatRupiah($p['price']); ?></div>
                        <button class="btn btn-coffee btn-sm rounded-pill px-3 fw-bold" 
                                onclick="openCustomizerModal(<?php echo htmlspecialchars(json_encode([
                                    'id' => (int)$p['id'],
                                    'name' => $p['name'],
                                    'price' => (float)$p['price'],
                                    'category' => $p['category_slug'],
                                    'icon' => $p['image_icon']
                                ])); ?>)">
                            <i class="bi bi-plus-lg"></i> Pesan
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<!-- Floating Cart Bar (Bottom) -->
<div class="floating-cart-bar" id="floatingCartBar" onclick="openCartModal()">
    <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; font-size: 1.25rem;">
            <i class="bi bi-bag-check-fill"></i>
        </div>
        <div>
            <div class="fw-bold" id="floatingCartItemsCount">0 Item dalam Pesanan</div>
            <div class="small text-light" style="opacity: 0.8;" id="floatingCartSubtitle">Klik untuk periksa dan checkout</div>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="fw-bold fs-5 text-warning" id="floatingCartTotal">Rp 0</span>
        <i class="bi bi-arrow-right-circle-fill fs-4 text-warning"></i>
    </div>
</div>

<!-- Modal 1: Drink Customizer -->
<div class="modal fade" id="customizerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-sliders text-warning fs-5"></i>
                    <h5 class="modal-title fw-bold" id="custModalProdName">Kustomisasi Sajian</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="custModalProdId">
                <input type="hidden" id="custModalProdBasePrice">

                <!-- Temperature Selection -->
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Pilihan Suhu Sajian</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="tempChoice" id="tempIced" value="Dingin (Iced)" checked>
                            <label class="btn btn-outline-secondary w-100 py-2 d-flex align-items-center justify-content-center gap-2" for="tempIced">
                                <i class="bi bi-snow text-info"></i> Dingin (Iced)
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="tempChoice" id="tempHot" value="Panas (Hot)">
                            <label class="btn btn-outline-secondary w-100 py-2 d-flex align-items-center justify-content-center gap-2" for="tempHot">
                                <i class="bi bi-fire text-danger"></i> Panas (Hot)
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Sugar Level Selection -->
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Tingkat Manis (Sugar Level)</label>
                    <div class="row g-2">
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="sugarChoice" id="sugarNormal" value="Normal Sugar (100%)" checked>
                            <label class="btn btn-outline-secondary w-100 py-2 small" for="sugarNormal">Normal (100%)</label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="sugarChoice" id="sugarLess" value="Less Sugar (50%)">
                            <label class="btn btn-outline-secondary w-100 py-2 small" for="sugarLess">Less (50%)</label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="sugarChoice" id="sugarNone" value="No Sugar (0%)">
                            <label class="btn btn-outline-secondary w-100 py-2 small" for="sugarNone">Tanpa Gula</label>
                        </div>
                    </div>
                </div>

                <!-- Dairy Upgrade Option -->
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Pilihan Susu (Dairy)</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="dairyChoice" id="dairyFresh" value="Fresh Milk" data-extra="0" checked onchange="updateCustomizerTotal()">
                            <label class="btn btn-outline-secondary w-100 py-2 small text-start ps-3" for="dairyFresh">
                                <div class="fw-bold">Fresh Milk</div>
                                <div class="text-muted" style="font-size: 0.72rem;">Standar Barista (+Rp 0)</div>
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="dairyChoice" id="dairyOat" value="Oatside Oat Milk" data-extra="6000" onchange="updateCustomizerTotal()">
                            <label class="btn btn-outline-secondary w-100 py-2 small text-start ps-3" for="dairyOat">
                                <div class="fw-bold text-primary">Oat Milk</div>
                                <div class="text-muted" style="font-size: 0.72rem;">Plant-based (+Rp 6.000)</div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Special Notes for Barista -->
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Catatan Tambahan untuk Barista</label>
                    <input type="text" id="custModalNotes" class="form-control" placeholder="Contoh: Es batu sedikit, sedotan terpisah...">
                </div>

                <!-- Quantity Control -->
                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <span class="fw-bold">Jumlah Porsi:</span>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle" style="width: 32px; height: 32px;" onclick="changeModalQty(-1)">-</button>
                        <span class="fw-bold px-2 fs-6" id="custModalQty">1</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle" style="width: 32px; height: 32px;" onclick="changeModalQty(1)">+</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light p-3">
                <button type="button" class="btn btn-coffee w-100 py-2 fw-bold d-flex justify-content-between align-items-center" onclick="confirmAddToCart()">
                    <span><i class="bi bi-cart-plus me-1"></i> Tambahkan ke Keranjang</span>
                    <span id="custModalFinalTotal">Rp 0</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Customer Cart & Checkout Drawer -->
<div class="modal fade" id="customerCartModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-bag-check-fill text-warning fs-5"></i>
                    <h5 class="modal-title fw-bold">Keranjang Pesanan Anda</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Left: Cart Items List -->
                    <div class="col-12 col-lg-7 border-end">
                        <h6 class="fw-bold text-muted small text-uppercase mb-3">Item yang Dipesan</h6>
                        
                        <div id="cartItemsContainer" style="max-height: 320px; overflow-y: auto;">
                            <!-- Injected by JS -->
                        </div>

                        <div id="cartEmptyNotice" class="text-center py-5 text-muted" style="display: none;">
                            <i class="bi bi-cart-x fs-1 d-block mb-2 text-secondary"></i>
                            Keranjang pesanan masih kosong.<br>Silakan pilih menu kopi favorit Anda terlebih dahulu.
                        </div>
                    </div>

                    <!-- Right: Checkout Details & Payment -->
                    <div class="col-12 col-lg-5">
                        <h6 class="fw-bold text-muted small text-uppercase mb-3">Data Pemesan & Pembayaran</h6>

                        <!-- Customer Name -->
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Nama Anda <span class="text-danger">*</span></label>
                            <input type="text" id="inputCustomerName" class="form-control" placeholder="Nama untuk panggilan antrean" value="Tamu KopiKita" required>
                        </div>

                        <!-- Table Number or Takeaway -->
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Nomor Meja</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-shop"></i></span>
                                <input type="text" id="inputTableNo" class="form-control" placeholder="Contoh: 04" value="04">
                            </div>
                        </div>

                        <!-- WhatsApp / Member Check -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold">No. WhatsApp / Pilihan Member</label>
                            <select id="selectCustomerMember" class="form-select form-select-sm" onchange="applyMemberDiscount(this.value)">
                                <option value="0" data-tier="None" data-discount="0">-- Tamu Reguler (Tanpa Diskon) --</option>
                                <?php foreach ($members as $m): ?>
                                    <?php 
                                        $disc = ($m['tier'] === 'Gold') ? 10 : (($m['tier'] === 'Silver') ? 5 : 0);
                                    ?>
                                    <option value="<?php echo $m['id']; ?>" data-name="<?php echo htmlspecialchars($m['name']); ?>" data-tier="<?php echo $m['tier']; ?>" data-discount="<?php echo $disc; ?>">
                                        <?php echo htmlspecialchars($m['name']); ?> (Tier <?php echo $m['tier']; ?><?php echo $disc > 0 ? " - Diskon {$disc}%" : ""; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Price Breakdown -->
                        <div class="p-3 bg-light rounded-3 mb-3 small">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Subtotal:</span>
                                <strong id="lblCartSubtotal">Rp 0</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1 text-danger" id="rowCartDiscount" style="display: none;">
                                <span>Diskon Member (<span id="lblCartDiscountPct">0%</span>):</span>
                                <strong id="lblCartDiscount">-Rp 0</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Pajak Restoran (PB1 10%):</span>
                                <strong id="lblCartTax">Rp 0</strong>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between fs-6">
                                <strong>TOTAL BAYAR:</strong>
                                <strong class="text-primary fs-5" id="lblCartGrandTotal">Rp 0</strong>
                            </div>
                        </div>

                        <!-- Payment Method Selection -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Pilih Metode Pembayaran</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="custPayMethod" id="payQRIS" value="QRIS" checked onchange="toggleQRISPreview(true)">
                                    <label class="btn btn-outline-secondary w-100 py-2 small d-flex align-items-center justify-content-center gap-1" for="payQRIS">
                                        <i class="bi bi-qr-code text-primary"></i> QRIS Instan
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="custPayMethod" id="payCashier" value="Kasir" onchange="toggleQRISPreview(false)">
                                    <label class="btn btn-outline-secondary w-100 py-2 small d-flex align-items-center justify-content-center gap-1" for="payCashier">
                                        <i class="bi bi-cash-stack text-success"></i> Bayar di Kasir
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic QRIS Preview Box -->
                        <div id="qrisPreviewBox" class="text-center p-3 border rounded-3 bg-white mb-3">
                            <div class="small text-muted mb-1">Scan QRIS via GoPay / OVO / Dana / BCA:</div>
                            <div class="p-2 d-inline-block bg-light border rounded">
                                <i class="bi bi-qr-code text-dark" style="font-size: 110px; display: block; line-height: 1;"></i>
                            </div>
                            <div class="fw-bold small text-primary mt-1">NMID: ID1020261014KPK</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Kembali Pilih Menu</button>
                <button type="button" class="btn btn-coffee px-4 py-2 fw-bold" id="btnSubmitCustomerOrder" onclick="submitCustomerOrder()">
                    <i class="bi bi-check2-circle me-1"></i> Konfirmasi & Kirim Pesanan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 3: Order Success & Live Ticket Tracker -->
<div class="modal fade" id="orderSuccessModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white border-0 text-center justify-content-center py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                    <h5 class="modal-title fw-bold">Pesanan Berhasil Diterima!</h5>
                </div>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="text-muted small text-uppercase fw-bold mb-1">Nomor Antrean Anda</div>
                <div class="display-3 fw-bold text-primary mb-2" id="ticketQueueNo">#A-01</div>
                
                <div class="badge bg-light text-dark border px-3 py-2 mb-3">
                    Invoice: <strong id="ticketInvoiceNo">INV-...</strong> &bull; <span id="ticketTableNo">Meja #04</span>
                </div>

                <!-- Animated Live Status Tracker -->
                <div class="p-3 bg-light rounded-4 text-start mb-4">
                    <div class="fw-bold small text-muted text-uppercase mb-3">Status Pembuatan Kopi:</div>
                    
                    <div class="tracker-step completed">
                        <div class="tracker-icon"><i class="bi bi-check"></i></div>
                        <div>
                            <div class="fw-bold small">1. Pesanan Diterima Sistem</div>
                            <div class="text-muted" style="font-size: 0.75rem;">Stok bahan baku & resep BOM otomatis dipersiapkan</div>
                        </div>
                    </div>

                    <div class="tracker-step active" id="trackerStepBrewing">
                        <div class="tracker-icon"><i class="bi bi-cup-hot-fill"></i></div>
                        <div>
                            <div class="fw-bold small text-primary">2. Sedang Diracik Barista</div>
                            <div class="text-muted" style="font-size: 0.75rem;">Barista sedang mengekstraksi espresso & steaming susu</div>
                        </div>
                    </div>

                    <div class="tracker-step" id="trackerStepReady">
                        <div class="tracker-icon"><i class="bi bi-bell-fill"></i></div>
                        <div>
                            <div class="fw-bold small">3. Siap Diambil di Pick-Up Bar</div>
                            <div class="text-muted" style="font-size: 0.75rem;">Nomor antrean Anda akan dipanggil saat siap</div>
                        </div>
                    </div>
                </div>

                <!-- Items Ordered Summary -->
                <div class="text-start mb-3">
                    <div class="fw-bold small mb-2">Ringkasan Pesanan:</div>
                    <ul class="list-group list-group-flush small" id="ticketItemsList">
                        <!-- Injected by JS -->
                    </ul>
                </div>

                <div class="d-flex justify-content-between p-2 bg-light rounded-3 fw-bold mb-3">
                    <span>Total Pembayaran:</span>
                    <span class="text-primary" id="ticketTotalAmount">Rp 0</span>
                </div>

                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-coffee" onclick="finishAndResetOrder()">
                        <i class="bi bi-plus-circle me-1"></i> Pesan Menu Tambahan Lainnya
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-speedometer2 me-1"></i> Buka Dashboard ERP Staff
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal 4: Table Picker -->
<div class="modal fade" id="tablePickerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-cream">
                <h5 class="modal-title fw-bold"><i class="bi bi-shop text-warning me-2"></i> Pilih Nomor Meja Anda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <p class="small text-muted mb-3">Silakan klik nomor meja tempat Anda duduk di kedai:</p>
                <div class="row g-2 justify-content-center">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <div class="col-3">
                            <button class="btn btn-outline-secondary w-100 py-3 fw-bold table-btn" 
                                    onclick="selectTable('<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>')">
                                Meja <?php echo $i; ?>
                            </button>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>

<script>
let customerCart = [];
let activeOrderType = 'Dine In';
let selectedTable = '04';
let currentMemberDiscount = 0;
let modalCurrentProduct = null;
let modalCurrentQty = 1;
let isAudioEnabled = true;

// Web Audio API Synthesizer (Instant SFX without external files)
function playCoffeeChime() {
    if (!isAudioEnabled) return;
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        // Pleasant bell chime
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
        osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.15); // A5
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.8);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.8);
    } catch(e) {}
}

function playClickSound() {
    if (!isAudioEnabled) return;
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(440, audioCtx.currentTime);
        gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.1);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.1);
    } catch(e) {}
}

function toggleAudioSFX() {
    isAudioEnabled = !isAudioEnabled;
    const btn = document.getElementById('custSoundToggle');
    if (isAudioEnabled) {
        btn.innerHTML = '<i class="bi bi-volume-up-fill text-success"></i>';
        showToast('Efek suara aktif', 'success');
        playClickSound();
    } else {
        btn.innerHTML = '<i class="bi bi-volume-mute-fill text-muted"></i>';
        showToast('Efek suara dinonaktifkan', 'info');
    }
}

// Category Filter
function filterCustCategory(catSlug, btn) {
    playClickSound();
    document.querySelectorAll('.cust-cat-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('.cust-item-col').forEach(card => {
        if (catSlug === 'all' || card.getAttribute('data-category') === catSlug) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Search Filter
document.getElementById('custSearchInput').addEventListener('keyup', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.cust-item-col').forEach(card => {
        const name = card.getAttribute('data-name');
        card.style.display = name.includes(q) ? 'block' : 'none';
    });
});

// Table & Order Type
function openTablePicker() {
    new bootstrap.Modal(document.getElementById('tablePickerModal')).show();
}

function selectTable(tableNum) {
    selectedTable = tableNum;
    document.getElementById('lblSelectedTable').innerText = 'Meja #' + tableNum;
    document.getElementById('inputTableNo').value = tableNum;
    bootstrap.Modal.getInstance(document.getElementById('tablePickerModal')).hide();
    showToast('Nomor meja dipilih: Meja #' + tableNum, 'success');
}

function setOrderType(type) {
    activeOrderType = type;
    playClickSound();
}

// Customizer Modal
function openCustomizerModal(prod) {
    playClickSound();
    modalCurrentProduct = prod;
    modalCurrentQty = 1;
    document.getElementById('custModalProdId').value = prod.id;
    document.getElementById('custModalProdBasePrice').value = prod.price;
    document.getElementById('custModalProdName').innerText = prod.name;
    document.getElementById('custModalQty').innerText = '1';
    document.getElementById('custModalNotes').value = '';
    
    // Reset radios
    document.getElementById('tempIced').checked = true;
    document.getElementById('sugarNormal').checked = true;
    document.getElementById('dairyFresh').checked = true;

    updateCustomizerTotal();
    new bootstrap.Modal(document.getElementById('customizerModal')).show();
}

function changeModalQty(delta) {
    playClickSound();
    modalCurrentQty = Math.max(1, modalCurrentQty + delta);
    document.getElementById('custModalQty').innerText = modalCurrentQty;
    updateCustomizerTotal();
}

function updateCustomizerTotal() {
    if (!modalCurrentProduct) return;
    const basePrice = modalCurrentProduct.price;
    const dairyExtra = parseFloat(document.querySelector('input[name="dairyChoice"]:checked').getAttribute('data-extra')) || 0;
    const itemTotal = (basePrice + dairyExtra) * modalCurrentQty;
    document.getElementById('custModalFinalTotal').innerText = formatRupiahJS(itemTotal);
}

function confirmAddToCart() {
    if (!modalCurrentProduct) return;
    playClickSound();

    const temp = document.querySelector('input[name="tempChoice"]:checked').value;
    const sugar = document.querySelector('input[name="sugarChoice"]:checked').value;
    const dairy = document.querySelector('input[name="dairyChoice"]:checked').value;
    const dairyExtra = parseFloat(document.querySelector('input[name="dairyChoice"]:checked').getAttribute('data-extra')) || 0;
    const notes = document.getElementById('custModalNotes').value.trim();

    const unitPrice = modalCurrentProduct.price + dairyExtra;
    const customizations = `${temp}, ${sugar}, ${dairy}`;

    // Add to cart array
    customerCart.push({
        id: modalCurrentProduct.id,
        name: modalCurrentProduct.name,
        price: unitPrice,
        qty: modalCurrentQty,
        customizations: customizations,
        notes: notes
    });

    bootstrap.Modal.getInstance(document.getElementById('customizerModal')).hide();
    renderCustomerCart();
    showToast(`"${modalCurrentProduct.name}" berhasil ditambahkan ke keranjang!`, 'success');
}

// Render Cart
function renderCustomerCart() {
    const container = document.getElementById('cartItemsContainer');
    const emptyNotice = document.getElementById('cartEmptyNotice');
    const floatingBar = document.getElementById('floatingCartBar');

    const totalQty = customerCart.reduce((sum, item) => sum + item.qty, 0);
    document.getElementById('cartBadgeCount').innerText = totalQty;

    if (customerCart.length === 0) {
        container.innerHTML = '';
        emptyNotice.style.display = 'block';
        floatingBar.classList.remove('show');
        calculateCartTotals();
        return;
    }

    emptyNotice.style.display = 'none';
    floatingBar.classList.add('show');

    let html = '';
    customerCart.forEach((it, idx) => {
        html += `
            <div class="p-3 border rounded-3 mb-2 bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-bold">${it.name}</div>
                        <div class="small text-muted">${it.customizations}</div>
                        ${it.notes ? `<div class="small text-primary fst-italic">*${it.notes}</div>` : ''}
                    </div>
                    <div class="fw-bold text-end">${formatRupiahJS(it.price * it.qty)}</div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                    <span class="small text-muted">@ ${formatRupiahJS(it.price)}</span>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="updateItemCartQty(${idx}, -1)">-</button>
                        <span class="fw-bold px-1">${it.qty}</span>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="updateItemCartQty(${idx}, 1)">+</button>
                        <button class="btn btn-sm btn-outline-danger py-0 px-2 ms-2" onclick="removeCartItem(${idx})"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
    calculateCartTotals();
}

function updateItemCartQty(index, delta) {
    playClickSound();
    customerCart[index].qty += delta;
    if (customerCart[index].qty <= 0) {
        customerCart.splice(index, 1);
    }
    renderCustomerCart();
}

function removeCartItem(index) {
    playClickSound();
    customerCart.splice(index, 1);
    renderCustomerCart();
}

function calculateCartTotals() {
    let subtotal = 0;
    customerCart.forEach(it => {
        subtotal += (it.price * it.qty);
    });

    const discountAmount = (subtotal * currentMemberDiscount) / 100;
    const taxableAmount = Math.max(0, subtotal - discountAmount);
    const taxAmount = taxableAmount * 0.10; // PB1 10%
    const grandTotal = taxableAmount + taxAmount;

    document.getElementById('lblCartSubtotal').innerText = formatRupiahJS(subtotal);
    if (currentMemberDiscount > 0) {
        document.getElementById('rowCartDiscount').style.display = 'flex';
        document.getElementById('lblCartDiscountPct').innerText = currentMemberDiscount + '%';
        document.getElementById('lblCartDiscount').innerText = '- ' + formatRupiahJS(discountAmount);
    } else {
        document.getElementById('rowCartDiscount').style.display = 'none';
    }

    document.getElementById('lblCartTax').innerText = formatRupiahJS(taxAmount);
    document.getElementById('lblCartGrandTotal').innerText = formatRupiahJS(grandTotal);

    // Update Floating bar
    const totalQty = customerCart.reduce((sum, item) => sum + item.qty, 0);
    document.getElementById('floatingCartItemsCount').innerText = `${totalQty} Item dalam Pesanan`;
    document.getElementById('floatingCartTotal').innerText = formatRupiahJS(grandTotal);
}

function applyMemberDiscount(memberId) {
    const sel = document.getElementById('selectCustomerMember');
    const opt = sel.options[sel.selectedIndex];
    currentMemberDiscount = parseFloat(opt.getAttribute('data-discount')) || 0;
    
    if (memberId > 0) {
        const memName = opt.getAttribute('data-name');
        document.getElementById('inputCustomerName').value = memName;
        showToast(`Diskon Member ${currentMemberDiscount}% diterapkan!`, 'success');
    }
    calculateCartTotals();
}

function toggleQRISPreview(show) {
    document.getElementById('qrisPreviewBox').style.display = show ? 'block' : 'none';
}

function openCartModal() {
    playClickSound();
    new bootstrap.Modal(document.getElementById('customerCartModal')).show();
}

// Submit Checkout
function submitCustomerOrder() {
    if (customerCart.length === 0) {
        showToast('Keranjang Anda masih kosong!', 'error');
        return;
    }

    const customerName = document.getElementById('inputCustomerName').value.trim() || 'Pelanggan';
    const tableNo = document.getElementById('inputTableNo').value.trim();
    const selMember = document.getElementById('selectCustomerMember');
    const customerId = selMember.value > 0 ? selMember.value : null;
    const paymentMethod = document.querySelector('input[name="custPayMethod"]:checked').value;

    const submitBtn = document.getElementById('btnSubmitCustomerOrder');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses Pesanan...';

    // Compute totals
    let subtotal = 0;
    customerCart.forEach(it => { subtotal += (it.price * it.qty); });
    const discountAmount = (subtotal * currentMemberDiscount) / 100;
    const taxAmount = (subtotal - discountAmount) * 0.10;
    const grandTotal = (subtotal - discountAmount) + taxAmount;

    const payload = {
        outlet_id: <?php echo (int)$currentOutlet['id']; ?>,
        order_type: activeOrderType,
        table_no: tableNo,
        customer_name: customerName,
        customer_id: customerId,
        items: customerCart,
        subtotal: subtotal,
        discount: discountAmount,
        tax: taxAmount,
        total_amount: grandTotal,
        payment_method: paymentMethod,
        notes: `Customer Order Portal (${activeOrderType})`
    };

    fetch('customer_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Konfirmasi & Kirim Pesanan';

        if (data.success) {
            playCoffeeChime();
            bootstrap.Modal.getInstance(document.getElementById('customerCartModal')).hide();

            // Populate Success Ticket Modal
            document.getElementById('ticketQueueNo').innerText = '#' + data.queue_no;
            document.getElementById('ticketInvoiceNo').innerText = data.invoice_no;
            document.getElementById('ticketTableNo').innerText = data.table_no ? 'Meja #' + data.table_no : 'Take Away';
            document.getElementById('ticketTotalAmount').innerText = data.total_amount_formatted;

            let itemsHtml = '';
            data.items.forEach(it => {
                itemsHtml += `
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <div>
                            <strong>${it.name}</strong> x${it.qty}
                            <div class="text-muted" style="font-size: 0.75rem;">${it.customizations}</div>
                        </div>
                        <span class="text-dark">${formatRupiahJS(it.subtotal)}</span>
                    </li>
                `;
            });
            document.getElementById('ticketItemsList').innerHTML = itemsHtml;

            // Show Success Modal
            new bootstrap.Modal(document.getElementById('orderSuccessModal')).show();

            // Animate Tracker: Step 2 brewing after 3 seconds
            setTimeout(() => {
                const stepBrew = document.getElementById('trackerStepBrewing');
                if (stepBrew) stepBrew.classList.add('active');
            }, 3000);
        } else {
            showToast('Gagal memproses pesanan: ' + (data.error || 'Terjadi kesalahan'), 'error');
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Konfirmasi & Kirim Pesanan';
        showToast('Koneksi server gagal: ' + err, 'error');
    });
}

function finishAndResetOrder() {
    customerCart = [];
    renderCustomerCart();
    bootstrap.Modal.getInstance(document.getElementById('orderSuccessModal')).hide();
    showToast('Terima kasih! Pesanan Anda sedang disiapkan barista.', 'success');
}
</script>

</body>
</html>
