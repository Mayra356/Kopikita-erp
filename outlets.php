<?php
$pageTitle = 'Multi-Cabang (Outlets)';
require_once __DIR__ . '/includes/header.php';

// Handle Add / Edit Outlet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_outlet') {
        $outletId = (int)($_POST['outlet_id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $manager = trim($_POST['manager_name'] ?? '');

        if (!empty($name)) {
            if ($outletId > 0) {
                dbUpdate('outlets', [
                    'code' => $code,
                    'name' => $name,
                    'address' => $address,
                    'phone' => $phone,
                    'manager_name' => $manager
                ], 'id = :id', ['id' => $outletId]);
                setFlashMessage('success', "Data cabang {$name} berhasil diperbarui.");
            } else {
                if (empty($code)) $code = 'KPK-' . strtoupper(substr(uniqid(), -5));
                $newId = dbInsert('outlets', [
                    'code' => $code,
                    'name' => $name,
                    'address' => $address,
                    'phone' => $phone,
                    'manager_name' => $manager,
                    'is_active' => 1
                ]);

                // Copy default raw materials for the new branch
                $defaultMats = dbFetchAll("SELECT * FROM raw_materials WHERE outlet_id = 1");
                $stmtInsertMat = getDb()->prepare("
                    INSERT INTO raw_materials (code, name, category, unit, current_stock, min_stock, cost_per_unit, outlet_id)
                    VALUES (?, ?, ?, ?, 100, ?, ?, ?)
                ");
                foreach ($defaultMats as $dm) {
                    $stmtInsertMat->execute([$dm['code'] . '-' . $newId, $dm['name'], $dm['category'], $dm['unit'], $dm['min_stock'], $dm['cost_per_unit'], $newId]);
                }

                setFlashMessage('success', "Cabang baru {$name} berhasil dibuka dan katalog bahan baku telah diinisialisasi.");
            }
            header('Location: outlets.php');
            exit;
        }
    }
}

// Fetch all outlets with their performance
$outlets = dbFetchAll("
    SELECT o.*,
           (SELECT COUNT(*) FROM raw_materials WHERE outlet_id = o.id) as total_sku,
           (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE outlet_id = o.id AND payment_status = 'Paid') as total_sales,
           (SELECT COUNT(*) FROM employees WHERE role LIKE '%Manager%' OR status = 'Active') as total_staff
    FROM outlets o
    ORDER BY o.id ASC
");
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Manajemen Multi-Cabang (Outlets)</h1>
        <p class="page-subtitle">Kontrol ekspansi kedai, monitoring omzet antar cabang, dan pergantian outlet aktif</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-coffee" onclick="openOutletModal()">
            <i class="bi bi-plus-circle me-1"></i> Tambah Cabang Baru
        </button>
    </div>
</div>

<!-- Outlets Cards Grid -->
<div class="row g-4 mb-4">
    <?php foreach ($outlets as $o): ?>
        <?php $isActiveCurrent = ($o['id'] == $currentOutlet['id']); ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card-coffee h-100 <?php echo $isActiveCurrent ? 'border-primary' : ''; ?>" style="<?php echo $isActiveCurrent ? 'border-width: 2px;' : ''; ?>">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-3 <?php echo $isActiveCurrent ? 'bg-primary text-white' : 'bg-light text-primary'; ?> d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-shop fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($o['name']); ?></h5>
                            <div class="text-muted small"><?php echo htmlspecialchars($o['code']); ?></div>
                        </div>
                    </div>
                    <?php if ($isActiveCurrent): ?>
                        <span class="badge bg-primary text-white">Cabang Aktif</span>
                    <?php else: ?>
                        <span class="badge bg-light text-muted border">Online</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Alamat Lokasi:</div>
                        <div class="small fw-semibold"><?php echo htmlspecialchars($o['address'] ?: '-'); ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small"><i class="bi bi-telephone-fill text-success me-1"></i> Kontak Telepon:</div>
                        <div class="small"><?php echo htmlspecialchars($o['phone'] ?: '-'); ?></div>
                    </div>
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="text-muted small"><i class="bi bi-person-badge text-primary me-1"></i> Store Manager:</div>
                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($o['manager_name'] ?: 'Belum ditunjuk'); ?></div>
                    </div>

                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <div class="p-2 bg-light rounded-3">
                                <div class="text-muted small">Total Penjualan</div>
                                <div class="fw-bold text-success fs-6"><?php echo formatRupiah($o['total_sales']); ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded-3">
                                <div class="text-muted small">Persediaan Gudang</div>
                                <div class="fw-bold text-dark fs-6"><?php echo $o['total_sku']; ?> SKU Bahan</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                    <button class="btn btn-sm btn-outline-secondary" onclick="editOutlet(<?php echo htmlspecialchars(json_encode($o)); ?>)">
                        <i class="bi bi-pencil me-1"></i> Edit Data
                    </button>
                    <?php if (!$isActiveCurrent): ?>
                        <a href="?outlet_id=<?php echo $o['id']; ?>" class="btn btn-sm btn-coffee">
                            <i class="bi bi-arrow-repeat me-1"></i> Kelola Cabang Ini
                        </a>
                    <?php else: ?>
                        <span class="btn btn-sm btn-success disabled">
                            <i class="bi bi-check2 me-1"></i> Sedang Dipilih
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal Form Cabang -->
<div class="modal fade" id="outletModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save_outlet">
                <input type="hidden" name="outlet_id" id="outletIdInput" value="0">

                <div class="modal-header bg-cream">
                    <h5 class="modal-title fw-bold" id="outletModalTitle">
                        <i class="bi bi-shop text-warning me-2"></i> Tambah Cabang Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kode Outlet</label>
                        <input type="text" name="code" id="outletCodeInput" class="form-control" placeholder="KPK-SBY01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Cabang Kedai <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="outletNameInput" class="form-control" placeholder="e.g. KopiKita Graha Famili Surabaya" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Alamat Lengkap</label>
                        <textarea name="address" id="outletAddressInput" rows="2" class="form-control" placeholder="Jl. Raya..."></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">No. Telepon</label>
                            <input type="text" name="phone" id="outletPhoneInput" class="form-control" placeholder="021-xxxx">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Store Manager</label>
                            <input type="text" name="manager_name" id="outletManagerInput" class="form-control" placeholder="Nama Manager">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-coffee btn-sm px-4">Simpan Cabang</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openOutletModal() {
    document.getElementById('outletIdInput').value = '0';
    document.getElementById('outletCodeInput').value = 'KPK-' + Math.floor(100 + Math.random() * 900);
    document.getElementById('outletNameInput').value = '';
    document.getElementById('outletAddressInput').value = '';
    document.getElementById('outletPhoneInput').value = '';
    document.getElementById('outletManagerInput').value = '';
    document.getElementById('outletModalTitle').innerHTML = '<i class="bi bi-shop text-warning me-2"></i> Tambah Cabang Baru';

    new bootstrap.Modal(document.getElementById('outletModal')).show();
}

function editOutlet(o) {
    document.getElementById('outletIdInput').value = o.id;
    document.getElementById('outletCodeInput').value = o.code;
    document.getElementById('outletNameInput').value = o.name;
    document.getElementById('outletAddressInput').value = o.address || '';
    document.getElementById('outletPhoneInput').value = o.phone || '';
    document.getElementById('outletManagerInput').value = o.manager_name || '';
    document.getElementById('outletModalTitle').innerHTML = '<i class="bi bi-pencil text-warning me-2"></i> Edit Cabang: ' + o.name;

    new bootstrap.Modal(document.getElementById('outletModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
