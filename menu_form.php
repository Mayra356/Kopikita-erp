<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$existingRecipes = [];

if ($id > 0) {
    $product = dbFetchOne("SELECT * FROM products WHERE id = ?", [$id]);
    if (!$product) {
        setFlashMessage('danger', 'Menu tidak ditemukan.');
        header('Location: menu.php');
        exit;
    }
    $existingRecipes = dbFetchAll("
        SELECT pr.*, rm.name as material_name, rm.cost_per_unit, rm.unit as raw_unit
        FROM product_recipes pr
        JOIN raw_materials rm ON pr.raw_material_id = rm.id
        WHERE pr.product_id = ?
    ", [$id]);
}

$categories = dbFetchAll("SELECT * FROM categories ORDER BY sort_order ASC");
$rawMaterials = dbFetchAll("SELECT * FROM raw_materials ORDER BY category ASC, name ASC");

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 1);
    $price = (float)($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $imageIcon = trim($_POST['image_icon'] ?? 'bi-cup-hot');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $recipeMaterials = $_POST['recipe_material_id'] ?? [];
    $recipeQtys = $_POST['recipe_quantity'] ?? [];
    $recipeUnits = $_POST['recipe_unit'] ?? [];

    if (empty($name) || $price <= 0) {
        $errorMessage = 'Nama menu dan harga jual wajib diisi.';
    } else {
        try {
            $db = getDb();
            $db->beginTransaction();

            if ($id > 0) {
                // Update product
                dbUpdate('products', [
                    'code' => $code,
                    'name' => $name,
                    'category_id' => $categoryId,
                    'price' => $price,
                    'description' => $description,
                    'image_icon' => $imageIcon,
                    'is_active' => $isActive
                ], 'id = :id', ['id' => $id]);
                $productId = $id;
            } else {
                // Insert product
                if (empty($code)) {
                    $code = 'PRD-' . strtoupper(substr(uniqid(), -6));
                }
                $productId = dbInsert('products', [
                    'code' => $code,
                    'name' => $name,
                    'category_id' => $categoryId,
                    'price' => $price,
                    'description' => $description,
                    'image_icon' => $imageIcon,
                    'is_active' => $isActive
                ]);
            }

            // Sync Recipes (BOM)
            dbQuery("DELETE FROM product_recipes WHERE product_id = ?", [$productId]);

            if (!empty($recipeMaterials)) {
                $stmtInsertRecipe = $db->prepare("
                    INSERT INTO product_recipes (product_id, raw_material_id, quantity, unit) 
                    VALUES (?, ?, ?, ?)
                ");
                for ($i = 0; $i < count($recipeMaterials); $i++) {
                    $matId = (int)$recipeMaterials[$i];
                    $qty = (float)$recipeQtys[$i];
                    $unit = $recipeUnits[$i] ?? 'gram';
                    if ($matId > 0 && $qty > 0) {
                        $stmtInsertRecipe->execute([$productId, $matId, $qty, $unit]);
                    }
                }
            }

            $db->commit();
            setFlashMessage('success', 'Data menu & formula resep BOM berhasil disimpan!');
            header('Location: menu.php');
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errorMessage = 'Gagal menyimpan: ' . $e->getMessage();
        }
    }
}

$pageTitle = ($id > 0 ? 'Edit Menu: ' . $product['name'] : 'Tambah Menu Baru');
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="menu.php" class="text-muted text-decoration-none">
                <i class="bi bi-arrow-left"></i> Kembali ke Menu
            </a>
            <span class="text-muted">/</span>
            <span class="fw-bold"><?php echo $id > 0 ? 'Edit Menu' : 'Menu Baru'; ?></span>
        </div>
        <h1 class="page-title"><?php echo $id > 0 ? 'Edit Menu & Formula Resep (BOM)' : 'Tambah Menu Baru & Formula Resep'; ?></h1>
        <p class="page-subtitle">Atur profil sajian, harga jual, dan takaran bahan baku untuk kalkulasi HPP otomatis</p>
    </div>
</div>

<?php if (isset($errorMessage)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($errorMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" id="menuForm">
    <div class="row g-4">
        <!-- Left: Basic Product Info -->
        <div class="col-12 col-lg-5">
            <div class="card-coffee mb-4">
                <div class="card-header">
                    <span class="fw-bold"><i class="bi bi-info-circle me-1"></i> Data Dasar Menu</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kode Menu / SKU</label>
                        <input type="text" name="code" class="form-control" 
                               value="<?php echo htmlspecialchars($product['code'] ?? 'PRD-' . date('Ym') . '-' . rand(10,99)); ?>" required>
                        <div class="form-text">Contoh: PRD-SIG-05, PRD-ESP-04</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Menu <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-lg" 
                               value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" placeholder="e.g. Avocado Espresso Float" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Kategori Menu <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($product['category_id']) && $product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Harga Jual Konsumen (IDR) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" step="500" name="price" id="sellingPriceInput" class="form-control fw-bold fs-5 text-primary" 
                                   value="<?php echo $product['price'] ?? 28000; ?>" required oninput="calculateBOMTotals()">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Deskripsi Sajian</label>
                        <textarea name="description" rows="3" class="form-control" placeholder="Rasa, profil notes kopi, bahan pemanis..."><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Ikon Tampilan POS</label>
                        <select name="image_icon" class="form-select">
                            <?php 
                            $icons = [
                                'bi-cup-hot' => 'Cangkir Kopi Panas (Hot Cup)',
                                'bi-cup-straw' => 'Gelas Kopi Dingin (Iced Straw)',
                                'bi-cup-hot-fill' => 'Espresso Shot Cangkir Tebal',
                                'bi-stars' => 'Bintang Signature',
                                'bi-cake2' => 'Kue / Pastry / Bakery',
                                'bi-egg-fried' => 'Makanan Ringan / Food',
                                'bi-bag-heart' => 'Biji Kopi Kemasan / Retail Beans'
                            ];
                            foreach ($icons as $icoVal => $icoLabel): ?>
                                <option value="<?php echo $icoVal; ?>" <?php echo (isset($product['image_icon']) && $product['image_icon'] == $icoVal) ? 'selected' : ''; ?>>
                                    <?php echo $icoLabel; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActiveSwitch" <?php echo (!isset($product) || $product['is_active']) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="isActiveSwitch">Tampilkan Menu di Kasir (POS Aktif)</label>
                    </div>
                </div>
            </div>

            <!-- Profitability Live Summary Widget -->
            <div class="card-coffee border-primary-subtle bg-light">
                <div class="card-header bg-white">
                    <span class="fw-bold text-primary"><i class="bi bi-graph-up-arrow me-1"></i> Analisis Profitabilitas Resep</span>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Harga Jual:</span>
                        <strong id="lblSellingPricePreview">Rp 0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">HPP Bahan Baku (BOM):</span>
                        <strong class="text-danger" id="lblTotalCOGSPreview">Rp 0</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-bold">Estimasi Laba Kotor:</span>
                        <strong class="text-success fs-6" id="lblGrossProfitPreview">Rp 0</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Margin Keuntungan:</span>
                        <span class="badge bg-success fs-6" id="lblMarginPctBadge">0%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Bill of Materials (BOM) Editor -->
        <div class="col-12 col-lg-7">
            <div class="card-coffee h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold"><i class="bi bi-diagram-3-fill text-warning me-1"></i> Bill of Materials (Resep Bahan Baku)</span>
                        <div class="text-muted small">Takaran bahan baku yang akan berkurang otomatis saat menu dipesan di POS</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-coffee" onclick="addRecipeRow()">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Bahan
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table-coffee" id="bomTable">
                            <thead>
                                <tr>
                                    <th style="width: 45%;">Bahan Baku</th>
                                    <th style="width: 25%;">Takaran per Porsi</th>
                                    <th style="width: 20%;" class="text-end">Subtotal Biaya</th>
                                    <th style="width: 10%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="bomRowsContainer">
                                <?php if (!empty($existingRecipes)): ?>
                                    <?php foreach ($existingRecipes as $index => $er): ?>
                                        <tr class="bom-row" data-index="<?php echo $index; ?>">
                                            <td>
                                                <select name="recipe_material_id[]" class="form-select form-select-sm material-selector" onchange="onMaterialChange(this)" required>
                                                    <option value="">-- Pilih Bahan --</option>
                                                    <?php foreach ($rawMaterials as $rm): ?>
                                                        <option value="<?php echo $rm['id']; ?>" 
                                                                data-unit="<?php echo htmlspecialchars($rm['unit']); ?>" 
                                                                data-cost="<?php echo (float)$rm['cost_per_unit']; ?>"
                                                                <?php echo $rm['id'] == $er['raw_material_id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($rm['name']); ?> (Rp <?php echo number_format($rm['cost_per_unit']); ?>/<?php echo $rm['unit']; ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="hidden" name="recipe_unit[]" class="row-unit-input" value="<?php echo htmlspecialchars($er['unit']); ?>">
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" step="0.1" min="0.1" name="recipe_quantity[]" class="form-control form-control-sm qty-input" 
                                                           value="<?php echo (float)$er['quantity']; ?>" required oninput="calculateBOMTotals()">
                                                    <span class="input-group-text row-unit-label"><?php echo htmlspecialchars($er['unit']); ?></span>
                                                </div>
                                            </td>
                                            <td class="text-end fw-bold row-cost-label">
                                                <?php echo formatRupiah($er['quantity'] * $er['cost_per_unit']); ?>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeRecipeRow(this)">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="emptyBOMNotice" class="text-center py-4 text-muted" style="<?php echo !empty($existingRecipes) ? 'display: none;' : ''; ?>">
                        <i class="bi bi-boxes fs-2 d-block mb-2 text-secondary"></i>
                        Belum ada bahan baku dalam resep ini.<br>
                        Klik tombol <strong>"Tambah Bahan"</strong> untuk memasukkan biji kopi, susu, sirup, atau kemasan cup.
                    </div>
                </div>

                <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small">Total Komponen:</span>
                        <strong id="lblComponentCount">0</strong> bahan
                    </div>
                    <div class="d-flex gap-2">
                        <a href="menu.php" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-coffee px-4">
                            <i class="bi bi-check-lg me-1"></i> Simpan Menu & Resep
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Raw materials JSON for client row creation -->
<script>
const rawMaterialsData = <?php echo json_encode(array_map(function($rm) {
    return [
        'id' => (int)$rm['id'],
        'name' => $rm['name'],
        'unit' => $rm['unit'],
        'cost_per_unit' => (float)$rm['cost_per_unit'],
        'category' => $rm['category']
    ];
}, $rawMaterials)); ?>;

function addRecipeRow() {
    const container = document.getElementById('bomRowsContainer');
    document.getElementById('emptyBOMNotice').style.display = 'none';

    let optionsHtml = '<option value="">-- Pilih Bahan Baku --</option>';
    rawMaterialsData.forEach(rm => {
        optionsHtml += `<option value="${rm.id}" data-unit="${rm.unit}" data-cost="${rm.cost_per_unit}">${rm.name} (Rp ${new Intl.NumberFormat('id-ID').format(rm.cost_per_unit)}/${rm.unit})</option>`;
    });

    const tr = document.createElement('tr');
    tr.className = 'bom-row';
    tr.innerHTML = `
        <td>
            <select name="recipe_material_id[]" class="form-select form-select-sm material-selector" onchange="onMaterialChange(this)" required>
                ${optionsHtml}
            </select>
            <input type="hidden" name="recipe_unit[]" class="row-unit-input" value="gram">
        </td>
        <td>
            <div class="input-group input-group-sm">
                <input type="number" step="0.1" min="0.1" name="recipe_quantity[]" class="form-control form-control-sm qty-input" value="1" required oninput="calculateBOMTotals()">
                <span class="input-group-text row-unit-label">satuan</span>
            </div>
        </td>
        <td class="text-end fw-bold row-cost-label">Rp 0</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeRecipeRow(this)">
                <i class="bi bi-x-lg"></i>
            </button>
        </td>
    `;
    container.appendChild(tr);
    calculateBOMTotals();
}

function removeRecipeRow(btn) {
    btn.closest('tr').remove();
    const rows = document.querySelectorAll('.bom-row');
    if (rows.length === 0) {
        document.getElementById('emptyBOMNotice').style.display = 'block';
    }
    calculateBOMTotals();
}

function onMaterialChange(select) {
    const row = select.closest('tr');
    const opt = select.options[select.selectedIndex];
    const unit = opt.getAttribute('data-unit') || 'gram';
    
    row.querySelector('.row-unit-label').innerText = unit;
    row.querySelector('.row-unit-input').value = unit;
    calculateBOMTotals();
}

function calculateBOMTotals() {
    const rows = document.querySelectorAll('.bom-row');
    let totalCOGS = 0;
    let count = 0;

    rows.forEach(row => {
        const select = row.querySelector('.material-selector');
        const opt = select.options[select.selectedIndex];
        if (opt && opt.value) {
            count++;
            const costPerUnit = parseFloat(opt.getAttribute('data-cost')) || 0;
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const subtotalCost = costPerUnit * qty;
            row.querySelector('.row-cost-label').innerText = formatRupiahJS(subtotalCost);
            totalCOGS += subtotalCost;
        } else {
            row.querySelector('.row-cost-label').innerText = 'Rp 0';
        }
    });

    document.getElementById('lblComponentCount').innerText = count;

    // Selling price & margin calculation
    const sellingPrice = parseFloat(document.getElementById('sellingPriceInput').value) || 0;
    const grossProfit = sellingPrice - totalCOGS;
    const marginPct = sellingPrice > 0 ? (grossProfit / sellingPrice) * 100 : 0;

    document.getElementById('lblSellingPricePreview').innerText = formatRupiahJS(sellingPrice);
    document.getElementById('lblTotalCOGSPreview').innerText = formatRupiahJS(totalCOGS);
    document.getElementById('lblGrossProfitPreview').innerText = formatRupiahJS(grossProfit);
    
    const badge = document.getElementById('lblMarginPctBadge');
    badge.innerText = marginPct.toFixed(1) + '%';
    if (marginPct >= 65) {
        badge.className = 'badge bg-success fs-6';
    } else if (marginPct >= 40) {
        badge.className = 'badge bg-warning fs-6';
    } else {
        badge.className = 'badge bg-danger fs-6';
    }
}

// Initial calculation on load
document.addEventListener('DOMContentLoaded', function () {
    calculateBOMTotals();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
