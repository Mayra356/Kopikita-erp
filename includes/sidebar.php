<?php
/**
 * KopiKita ERP - Sidebar Navigation
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="app-sidebar" id="appSidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="logo-icon">
            <i class="bi bi-cup-hot-fill"></i>
        </div>
        <div class="brand-text">
            <h1 class="brand-title">KopiKita</h1>
            <p class="brand-subtitle">Coffee & Roastery ERP</p>
        </div>
    </div>

    <!-- Nav List -->
    <div class="sidebar-nav">
        <p class="nav-section-title">Utama</p>
        <a href="index.php" class="nav-link <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>
        <a href="customer.php" class="nav-link <?php echo $currentPage == 'customer.php' ? 'active' : ''; ?>">
            <i class="bi bi-phone-fill text-warning"></i>
            <span>Mode Customer</span>
            <span class="badge bg-warning text-dark ms-auto" style="font-size: 0.65rem; font-weight: 700;">Self-Order</span>
        </a>
        <a href="pos.php" class="nav-link <?php echo $currentPage == 'pos.php' ? 'active' : ''; ?>">
            <i class="bi bi-calculator-fill"></i>
            <span>Point of Sale (POS)</span>
        </a>
        <a href="orders.php" class="nav-link <?php echo $currentPage == 'orders.php' ? 'active' : ''; ?>">
            <i class="bi bi-receipt"></i>
            <span>Riwayat Transaksi</span>
        </a>

        <p class="nav-section-title">Menu & Resep</p>
        <a href="menu.php" class="nav-link <?php echo in_array($currentPage, ['menu.php', 'menu_form.php']) ? 'active' : ''; ?>">
            <i class="bi bi-book-half"></i>
            <span>Katalog & Resep BOM</span>
        </a>

        <p class="nav-section-title">Stok & Gudang</p>
        <a href="inventory.php" class="nav-link <?php echo in_array($currentPage, ['inventory.php', 'inventory_form.php']) ? 'active' : ''; ?>">
            <i class="bi bi-boxes"></i>
            <span>Bahan Baku & Kemasan</span>
        </a>
        <a href="stock_card.php" class="nav-link <?php echo $currentPage == 'stock_card.php' ? 'active' : ''; ?>">
            <i class="bi bi-card-checklist"></i>
            <span>Kartu Stok Mutasi</span>
        </a>
        <a href="stock_opname.php" class="nav-link <?php echo $currentPage == 'stock_opname.php' ? 'active' : ''; ?>">
            <i class="bi bi-clipboard-check-fill"></i>
            <span>Stok Opname</span>
        </a>
        <a href="production.php" class="nav-link <?php echo $currentPage == 'production.php' ? 'active' : ''; ?>">
            <i class="bi bi-fire"></i>
            <span>Produksi & Roastery</span>
        </a>

        <p class="nav-section-title">Pengadaan & Vendor</p>
        <a href="purchasing.php" class="nav-link <?php echo in_array($currentPage, ['purchasing.php', 'po_create.php', 'po_detail.php']) ? 'active' : ''; ?>">
            <i class="bi bi-bag-check-fill"></i>
            <span>Purchase Order (PO)</span>
        </a>
        <a href="vendors.php" class="nav-link <?php echo $currentPage == 'vendors.php' ? 'active' : ''; ?>">
            <i class="bi bi-truck"></i>
            <span>Data Supplier / Vendor</span>
        </a>

        <p class="nav-section-title">Keuangan & Kas</p>
        <a href="cash_register.php" class="nav-link <?php echo $currentPage == 'cash_register.php' ? 'active' : ''; ?>">
            <i class="bi bi-cash-stack"></i>
            <span>Kasir & Rekap Harian</span>
        </a>
        <a href="finance.php" class="nav-link <?php echo $currentPage == 'finance.php' ? 'active' : ''; ?>">
            <i class="bi bi-pie-chart-fill"></i>
            <span>Laba Rugi & Arus Kas</span>
        </a>
        <a href="journals.php" class="nav-link <?php echo $currentPage == 'journals.php' ? 'active' : ''; ?>">
            <i class="bi bi-journal-bookmark-fill"></i>
            <span>Jurnal Umum Double-Entry</span>
        </a>

        <p class="nav-section-title">Operasional & CRM</p>
        <a href="employees.php" class="nav-link <?php echo $currentPage == 'employees.php' ? 'active' : ''; ?>">
            <i class="bi bi-people-fill"></i>
            <span>Karyawan & Shift</span>
        </a>
        <a href="customers.php" class="nav-link <?php echo $currentPage == 'customers.php' ? 'active' : ''; ?>">
            <i class="bi bi-person-heart"></i>
            <span>Member & Loyalitas</span>
        </a>
        <a href="outlets.php" class="nav-link <?php echo $currentPage == 'outlets.php' ? 'active' : ''; ?>">
            <i class="bi bi-shop-window"></i>
            <span>Multi-Cabang (Outlets)</span>
        </a>

        <p class="nav-section-title">Konfigurasi</p>
        <a href="settings.php" class="nav-link <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>">
            <i class="bi bi-gear-fill"></i>
            <span>Pengaturan & Backup</span>
        </a>
    </div>

    <!-- Footer info -->
    <div class="sidebar-footer">
        <div class="d-flex align-items-center justify-content-between text-muted" style="font-size: 0.75rem;">
            <span>v2.4 Enterprise</span>
            <span class="badge bg-success text-white py-1 px-2">Online</span>
        </div>
    </div>
</aside>
