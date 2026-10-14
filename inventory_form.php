<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$material = null;

if ($id > 0) {
    $material = dbFetchOne("SELECT * FROM raw_materials WHERE id = ?", [$id]);
    if (!$material) {
        setFlashMessage('danger', 'Bahan baku tidak ditemukan.');
        header('Location: inventory.php');
        exit;
    }
}

$outletId = getCurrentOutletId();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? 'Biji Kopi');
    $unit = trim($_POST['unit'] ?? 'gram');
    $currentStock = (float)($_POST['current_stock'] ?? 0);
    $minStock = (float)($_POST['min_stock'] ?? 10);
    $costPerUnit = (float)($_POST['cost_per_unit'] ?? 0);

    if (empty($name) || empty($code)) {
        $errorMessage = 'Kode SKU dan Nama Bahan wajib diisi.';
    } else {
        try {
            if ($id > 0) {
                // Update
                dbUpdate('raw_materials', [
                    'code' => $code,
                    'name' => $name,
                    'category' => $category,
                    'unit' => $unit,
                    'current_stock' => $currentStock,
                    'min_stock' => $minStock,
                    'cost_per_unit' => $costPerUnit
                ], 'id = :id', ['id' => $id]);

                setFlashMessage('success', 'Data bahan baku berhasil diperbarui.');
            } else {
                // Insert
                $newId = dbInsert('raw_materials', [
                    'code' => $code,
                    'name' => $name,
                    'category' => $category,
                    'unit' => $unit,
                    'current_stock' => $currentStock,
                    'min_stock' => $minStock,
                    'cost_per_unit' => $costPerUnit,
                    'outlet_id' => $outletId
                ]);

                // Record initial stock mutation
                if ($currentStock > 0) {
                    recordStockMutation($newId, $outletId, 'STOCK_OPNAME', 'INIT-' . date('Ymd'), $currentStock, 'Saldo Awal Input Bahan');
                }

                setFlashMessage('success', 'Bahan baku baru berhasil ditambahkan.');
            }

            header('Location: inventory.php');
            exit;

        } catch (Exception $e) {
            $errorMessage = 'Gagal menyimpan: ' . $e->getMessage();
        }
    }
}

$pageTitle = ($id > 0 ? 'Edit Bahan: ' . $material['name'] : 'Tambah Bahan Baku Baru');
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="inventory.php" class="text-muted text-decoration-none">
                <i class="bi bi-arrow-left"></i> Kembali ke Inventori
            </a>
            <span class="text-muted">/</span>
            <span class="fw-bold"><?php echo $id > 0 ? 'Edit Bahan' : 'Bahan Baru'; ?></span>
        </div>
        <h1 class="page-title"><?php echo $id > 0 ? 'Edit Bahan Baku & Kemasan' : 'Tambah Bahan Baku & Kemasan Baru'; ?></h1>
        <p class="page-subtitle">Atur identitas bahan, safety stock untuk notifikasi reorder, dan harga pokok per satuan</p>
    </div>
</div>

<?php if (isset($errorMessage)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($errorMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card-coffee">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-box-seam text-primary me-2"></i> Formulir Bahan Baku</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Kode SKU <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" 
                                   value="<?php echo htmlspecialchars($material['code'] ?? 'RM-' . strtoupper(substr(uniqid(), -6))); ?>" required>
                            <div class="form-text">Contoh: RM-BEANS-04, RM-MILK-03, RM-PKG-07</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Kategori Bahan <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <?php 
                                $categories = ['Biji Kopi', 'Susu & Dairy', 'Sirup & Flavour', 'Bubuk & Teh', 'Kemasan & Cup', 'Bahan Makanan'];
                                foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo (isset($material['category']) && $material['category'] === $cat) ? 'selected' : ''; ?>>
                                        <?php echo $cat; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Nama Bahan Baku <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-lg" 
                                   value="<?php echo htmlspecialchars($material['name'] ?? ''); ?>" placeholder="e.g. Single Origin Arabica Mandheling" required>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Satuan Ukuran (UoM) <span class="text-danger">*</span></label>
                            <select name="unit" class="form-select" required>
                                <?php 
                                $units = ['gram' => 'gram (g)', 'ml' => 'mililiter (ml)', 'pcs' => 'pieces (pcs / cup / lid)', 'pack' => 'pack / bungkus'];
                                foreach ($units as $uVal => $uLabel): ?>
                                    <option value="<?php echo $uVal; ?>" <?php echo (isset($material['unit']) && $material['unit'] === $uVal) ? 'selected' : ''; ?>>
                                        <?php echo $uLabel; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Stok Fisik Saat Ini <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="current_stock" class="form-control" 
                                   value="<?php echo (float)($material['current_stock'] ?? 0); ?>" required>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Safety Stock Minimum <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="min_stock" class="form-control" 
                                   value="<?php echo (float)($material['min_stock'] ?? 10); ?>" required>
                            <div class="form-text">Batas peringatan untuk purchase order</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Harga Pokok per Satuan (IDR) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" step="0.1" name="cost_per_unit" class="form-control fw-bold" 
                                       value="<?php echo (float)($material['cost_per_unit'] ?? 0); ?>" required>
                            </div>
                            <div class="form-text">Contoh: Rp 250 / gram, Rp 22 / ml, Rp 850 / pcs cup</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="inventory.php" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-coffee px-4">
                            <i class="bi bi-check-lg me-1"></i> Simpan Bahan Baku
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
