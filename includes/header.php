<?php
/**
 * KopiKita ERP - Header Component
 */
require_once __DIR__ . '/functions.php';

$currentOutlet = getCurrentOutlet();
$allOutlets = getAllOutlets();
$lowStockCount = getLowStockCount($currentOutlet['id']);
$flash = getFlashMessage();
$activePage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="id">
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
                    <select onchange="window.location.href='?outlet_id=' + this.value;">
                        <?php foreach ($allOutlets as $o): ?>
                            <option value="<?php echo $o['id']; ?>" <?php echo $o['id'] == $currentOutlet['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($o['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($lowStockCount > 0): ?>
                    <a href="inventory.php?filter=low" class="badge-soft-danger text-decoration-none d-none d-sm-inline-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?php echo $lowStockCount; ?> Bahan Baku Menipis</span>
                    </a>
                <?php endif; ?>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- Quick POS Button -->
                <a href="pos.php" class="btn btn-accent btn-sm d-flex align-items-center gap-2 px-3">
                    <i class="bi bi-calculator-fill"></i>
                    <span class="d-none d-sm-inline">Buka POS Kasir</span>
                </a>

                <!-- User profile pill -->
                <div class="d-flex align-items-center gap-2 border-start ps-3">
                    <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-weight: 700; font-size: 0.85rem;">
                        KW
                    </div>
                    <div class="d-none d-md-block text-start" style="line-height: 1.2;">
                        <div class="fw-bold" style="font-size: 0.85rem;">Kevin Wijaya</div>
                        <div class="text-muted" style="font-size: 0.72rem;">Store Manager</div>
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
