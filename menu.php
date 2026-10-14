<?php
$pageTitle = 'Katalog Menu & Resep BOM';
require_once __DIR__ . '/includes/header.php';

// Handle delete / toggle status action
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($_GET['action'] === 'toggle') {
        $p = dbFetchOne("SELECT is_active FROM products WHERE id = ?", [$id]);
        if ($p) {
            $newStatus = $p['is_active'] ? 0 : 1;
            dbUpdate('products', ['is_active' => $newStatus], 'id = :id', ['id' => $id]);
            setFlashMessage('success', 'Status produk berhasil diperbarui.');
            header('Location: menu.php');
            exit;
        }
    } elseif ($_GET['action'] === 'delete') {
        dbQuery("DELETE FROM product_recipes WHERE product_id = ?", [$id]);
        dbQuery("DELETE FROM products WHERE id = ?", [$id]);
        setFlashMessage('success', 'Menu berhasil dihapus dari sistem.');
        header('Location: menu.php');
        exit;
    }
}

// Fetch categories
$categories = dbFetchAll("SELECT * FROM categories ORDER BY sort_order ASC");

// Fetch products with their categories
$products = dbFetchAll("
    SELECT p.*, c.name as category_name, c.slug as category_slug
    FROM products p
    JOIN categories c ON p.category_id = c.id
    ORDER BY p.category_id ASC, p.name ASC
");

// Precompute COGS for each product
$productCOGS = [];
$totalMarginSum = 0;
$validMarginCount = 0;
$highestMarginProd = null;
$maxMargin = -999;

foreach ($products as $p) {
    $cogs = calculateProductCOGS($p['id']);
    $margin = $p['price'] > 0 ? (($p['price'] - $cogs) / $p['price']) * 100 : 0;
    $productCOGS[$p['id']] = [
        'cogs' => $cogs,
        'profit' => $p['price'] - $cogs,
        'margin_pct' => $margin
    ];

    if ($p['is_active'] && $p['price'] > 0) {
        $totalMarginSum += $margin;
        $validMarginCount++;
        if ($margin > $maxMargin) {
            $maxMargin = $margin;
            $highestMarginProd = $p;
        }
    }
}

$avgMargin = $validMarginCount > 0 ? ($totalMarginSum / $validMarginCount) : 0;
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Katalog Menu & Resep BOM</h1>
        <p class="page-subtitle">Kelola harga jual, resep takaran bahan baku (Bill of Materials), dan kalkulasi HPP otomatis</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="menu_form.php" class="btn btn-coffee">
            <i class="bi bi-plus-circle me-1"></i> Tambah Menu Baru
        </a>
    </div>
</div>

<!-- KPI Metrics -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Total Menu Terdaftar</div>
                    <div class="kpi-value"><?php echo count($products); ?> <span style="font-size: 1rem; font-weight: 500;">Menu</span></div>
                    <div class="kpi-desc"><?php echo count($categories); ?> kategori sajian</div>
                </div>
                <div class="kpi-icon bg-primary-subtle text-primary">
                    <i class="bi bi-cup-hot-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Rata-rata Margin Laba</div>
                    <div class="kpi-value text-success"><?php echo number_format($avgMargin, 1); ?>%</div>
                    <div class="kpi-desc">Standar industri kafe: > 65%</div>
                </div>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="bi bi-percent"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card info">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Menu Paling Menguntungkan</div>
                    <div class="kpi-value text-truncate" style="max-width: 170px; font-size: 1.15rem;">
                        <?php echo $highestMarginProd ? htmlspecialchars($highestMarginProd['name']) : '-'; ?>
                    </div>
                    <div class="kpi-desc text-success">
                        <i class="bi bi-arrow-up-circle-fill"></i> Margin: <?php echo number_format($maxMargin, 1); ?>%
                    </div>
                </div>
                <div class="kpi-icon bg-info-subtle text-info">
                    <i class="bi bi-trophy-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card warning">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Formula Resep (BOM)</div>
                    <div class="kpi-value text-warning">
                        <?php 
                            $totalRecipes = dbFetchOne("SELECT COUNT(*) as cnt FROM product_recipes")['cnt'];
                            echo $totalRecipes; 
                        ?> <span style="font-size: 0.9rem; font-weight: 500;">Bahan Terkoneksi</span>
                    </div>
                    <div class="kpi-desc">Auto deduksi stok di POS</div>
                </div>
                <div class="kpi-icon bg-warning-subtle text-warning">
                    <i class="bi bi-diagram-3-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Pills & Search -->
<div class="card-coffee mb-4">
    <div class="card-body p-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-2 overflow-x-auto pb-1" id="categoryFilterBar">
                <button class="btn btn-sm btn-outline-coffee active-cat-btn" onclick="filterByCategory('all', this)">
                    Semua (<?php echo count($products); ?>)
                </button>
                <?php foreach ($categories as $cat): ?>
                    <button class="btn btn-sm btn-outline-secondary text-nowrap" onclick="filterByCategory('<?php echo htmlspecialchars($cat['slug']); ?>', this)">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="menuSearchInput" class="form-control border-start-0" placeholder="Cari menu...">
            </div>
        </div>
    </div>
</div>

<!-- Product Table -->
<div class="card-coffee">
    <div class="table-responsive">
        <table class="table-coffee table-hover" id="productsTable">
            <thead>
                <tr>
                    <th>Menu & Kode</th>
                    <th>Kategori</th>
                    <th>Harga Jual</th>
                    <th>HPP (BOM)</th>
                    <th>Laba Kotor / Porsi</th>
                    <th>Margin</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <?php 
                        $c = $productCOGS[$p['id']];
                        $cogs = $c['cogs'];
                        $profit = $c['profit'];
                        $marginPct = $c['margin_pct'];
                    ?>
                    <tr class="product-row" data-category="<?php echo htmlspecialchars($p['category_slug']); ?>" data-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>">
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-3 bg-light d-flex align-items-center justify-content-center text-primary" style="width: 42px; height: 42px; font-size: 1.25rem;">
                                    <i class="bi <?php echo htmlspecialchars($p['image_icon'] ?: 'bi-cup-hot'); ?>"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($p['code']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <?php echo htmlspecialchars($p['category_name']); ?>
                            </span>
                        </td>
                        <td class="fw-bold fs-6"><?php echo formatRupiah($p['price']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-link text-decoration-none p-0 fw-bold text-secondary" onclick="viewRecipeModal(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['name'])); ?>')">
                                <?php echo formatRupiah($cogs); ?>
                                <i class="bi bi-info-circle ms-1 text-muted" style="font-size: 0.8rem;"></i>
                            </button>
                        </td>
                        <td class="fw-bold text-success">
                            <?php echo formatRupiah($profit); ?>
                        </td>
                        <td>
                            <?php if ($marginPct >= 70): ?>
                                <span class="badge bg-success-subtle text-success border">
                                    <i class="bi bi-arrow-up-right"></i> <?php echo number_format($marginPct, 1); ?>%
                                </span>
                            <?php elseif ($marginPct >= 50): ?>
                                <span class="badge bg-warning-subtle text-warning border">
                                    <?php echo number_format($marginPct, 1); ?>%
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border">
                                    <i class="bi bi-arrow-down-right"></i> <?php echo number_format($marginPct, 1); ?>%
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="menu.php?action=toggle&id=<?php echo $p['id']; ?>" class="badge-soft-<?php echo $p['is_active'] ? 'success' : 'secondary'; ?> text-decoration-none">
                                <?php echo $p['is_active'] ? 'Aktif di POS' : 'Nonaktif'; ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <button class="btn btn-sm btn-outline-secondary" title="Lihat Resep BOM" onclick="viewRecipeModal(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['name'])); ?>')">
                                    <i class="bi bi-diagram-3"></i>
                                </button>
                                <a href="menu_form.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-coffee" title="Edit Menu & Resep">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                <a href="menu.php?action=delete&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" title="Hapus Menu" onclick="return confirm('Hapus menu <?php echo htmlspecialchars(addslashes($p['name'])); ?> beserta komposisi resepnya?')">
                                    <i class="bi bi-trash-fill"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recipe Detail Modal -->
<div class="modal fade" id="recipeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-cream">
                <h5 class="modal-title fw-bold" id="recipeModalTitle">
                    <i class="bi bi-diagram-3-fill text-warning me-2"></i> Rincian Resep (BOM)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="recipeModalBody">
                <div class="text-center py-4">
                    <span class="spinner-border spinner-border-sm text-primary"></span> Memuat resep...
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="btnEditRecipeLink" class="btn btn-coffee btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit Komposisi Resep
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
// Filter by category
function filterByCategory(catSlug, btn) {
    document.querySelectorAll('#categoryFilterBar button').forEach(b => {
        b.classList.remove('btn-coffee');
        b.classList.add('btn-outline-secondary');
    });
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('btn-coffee');

    document.querySelectorAll('.product-row').forEach(row => {
        if (catSlug === 'all' || row.getAttribute('data-category') === catSlug) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Search filter
document.getElementById('menuSearchInput').addEventListener('keyup', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.product-row').forEach(row => {
        const name = row.getAttribute('data-name');
        row.style.display = name.includes(q) ? '' : 'none';
    });
});

// View Recipe Ajax Modal
function viewRecipeModal(productId, productName) {
    document.getElementById('recipeModalTitle').innerHTML = '<i class="bi bi-diagram-3-fill text-warning me-2"></i> Resep BOM: ' + productName;
    document.getElementById('btnEditRecipeLink').href = 'menu_form.php?id=' + productId;
    
    const body = document.getElementById('recipeModalBody');
    body.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Memuat resep...</div>';

    const modal = new bootstrap.Modal(document.getElementById('recipeModal'));
    modal.show();

    fetch('api_recipe.php?product_id=' + productId)
        .then(res => res.json())
        .then(data => {
            if (!data.success || data.recipes.length === 0) {
                body.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-exclamation-circle fs-2 d-block mb-2 text-secondary"></i>
                        Belum ada komposisi resep bahan baku untuk menu ini.
                        <div class="mt-2">
                            <a href="menu_form.php?id=${productId}" class="btn btn-sm btn-outline-coffee">Atur Resep Sekarang</a>
                        </div>
                    </div>
                `;
                return;
            }

            let html = `
                <table class="table table-sm table-striped mb-0" style="font-size: 0.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Bahan Baku</th>
                            <th>Takaran</th>
                            <th>Biaya Satuan</th>
                            <th class="text-end pe-3">Subtotal Biaya</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            let totalCost = 0;
            data.recipes.forEach(r => {
                const sub = r.quantity * r.cost_per_unit;
                totalCost += sub;
                html += `
                    <tr>
                        <td class="ps-3 fw-bold">${r.material_name}</td>
                        <td>${parseFloat(r.quantity)} ${r.unit}</td>
                        <td class="text-muted">${formatRupiahJS(r.cost_per_unit)} / ${r.unit}</td>
                        <td class="text-end pe-3 fw-bold">${formatRupiahJS(sub)}</td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="3" class="ps-3">ESTIMASI TOTAL HPP (COGS):</td>
                            <td class="text-end pe-3 text-primary fs-6">${formatRupiahJS(totalCost)}</td>
                        </tr>
                    </tfoot>
                </table>
            `;

            body.innerHTML = html;
        })
        .catch(err => {
            body.innerHTML = '<div class="p-3 text-danger text-center">Gagal memuat data resep: ' + err + '</div>';
        });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
