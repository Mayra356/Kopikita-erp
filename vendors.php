<?php
$pageTitle = 'Master Data Supplier / Vendor';
require_once __DIR__ . '/includes/header.php';

// Handle Add / Edit / Delete Vendor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_vendor') {
        $vendorId = (int)($_POST['vendor_id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'Roaster & Green Beans');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (!empty($name)) {
            if ($vendorId > 0) {
                dbUpdate('vendors', [
                    'code' => $code,
                    'name' => $name,
                    'category' => $category,
                    'contact_person' => $contactPerson,
                    'phone' => $phone,
                    'email' => $email,
                    'address' => $address
                ], 'id = :id', ['id' => $vendorId]);
                setFlashMessage('success', 'Data supplier berhasil diperbarui.');
            } else {
                if (empty($code)) $code = 'VND-' . strtoupper(substr(uniqid(), -5));
                dbInsert('vendors', [
                    'code' => $code,
                    'name' => $name,
                    'category' => $category,
                    'contact_person' => $contactPerson,
                    'phone' => $phone,
                    'email' => $email,
                    'address' => $address
                ]);
                setFlashMessage('success', 'Supplier baru berhasil ditambahkan.');
            }
            header('Location: vendors.php');
            exit;
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    dbQuery("DELETE FROM vendors WHERE id = ?", [$id]);
    setFlashMessage('success', 'Data supplier berhasil dihapus.');
    header('Location: vendors.php');
    exit;
}

$vendors = dbFetchAll("
    SELECT v.*, COUNT(po.id) as total_pos, COALESCE(SUM(po.total_amount), 0) as total_spent
    FROM vendors v
    LEFT JOIN purchase_orders po ON v.id = po.vendor_id
    GROUP BY v.id
    ORDER BY v.name ASC
");
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Data Supplier & Rekanan Vendor</h1>
        <p class="page-subtitle">Kelola mitra pengadaan biji kopi roaster, distributor susu dairy, kemasan cup, dan sirup</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-coffee" onclick="openVendorModal()">
            <i class="bi bi-plus-circle me-1"></i> Tambah Supplier Baru
        </button>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="kpi-card primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Total Mitra Supplier</div>
                    <div class="kpi-value"><?php echo count($vendors); ?> <span style="font-size: 1rem; font-weight: 500;">Vendor</span></div>
                    <div class="kpi-desc">Roastery, susu, kemasan & sirup</div>
                </div>
                <div class="kpi-icon bg-primary-subtle text-primary">
                    <i class="bi bi-truck"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-4">
        <div class="kpi-card success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Total Belanja Pengadaan</div>
                    <div class="kpi-value text-success">
                        <?php echo formatRupiah(array_sum(array_column($vendors, 'total_spent'))); ?>
                    </div>
                    <div class="kpi-desc">Akumulasi seluruh Purchase Order</div>
                </div>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="bi bi-bag-check-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-4">
        <div class="kpi-card info">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Hubungan Mitra</div>
                    <div class="kpi-value text-primary">Aktif</div>
                    <div class="kpi-desc">Rantai pasok beroperasi lancar</div>
                </div>
                <div class="kpi-icon bg-info-subtle text-info">
                    <i class="bi bi-shield-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Vendors Table -->
<div class="card-coffee">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-building text-primary"></i>
            <span class="fw-bold">Daftar Rekanan Supplier</span>
        </div>
        <a href="purchasing.php" class="btn btn-sm btn-outline-coffee">
            <i class="bi bi-bag-check me-1"></i> Lihat Semua PO
        </a>
    </div>
    <div class="table-responsive">
        <table class="table-coffee table-hover">
            <thead>
                <tr>
                    <th>Kode & Nama Supplier</th>
                    <th>Kategori Pasokan</th>
                    <th>Kontak Person</th>
                    <th>No. Telepon / WhatsApp</th>
                    <th>Alamat / Kota</th>
                    <th>Total PO</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vendors)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">Belum ada supplier terdaftar</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($vendors as $v): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($v['name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($v['code']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?php echo htmlspecialchars($v['category']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($v['contact_person'] ?: '-'); ?></td>
                            <td>
                                <?php if ($v['phone']): ?>
                                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $v['phone']); ?>" target="_blank" class="text-decoration-none text-success">
                                        <i class="bi bi-whatsapp me-1"></i> <?php echo htmlspecialchars($v['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted" style="max-width: 220px;"><?php echo htmlspecialchars($v['address'] ?: '-'); ?></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-dark border">
                                    <?php echo $v['total_pos']; ?> PO (<?php echo formatRupiah($v['total_spent']); ?>)
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="po_create.php?vendor_id=<?php echo $v['id']; ?>" class="btn btn-sm btn-outline-success" title="Buat PO untuk Supplier Ini">
                                        <i class="bi bi-cart-plus"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-coffee" title="Edit Supplier" 
                                            onclick="editVendor(<?php echo htmlspecialchars(json_encode($v)); ?>)">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <a href="vendors.php?action=delete&id=<?php echo $v['id']; ?>" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Hapus supplier <?php echo htmlspecialchars(addslashes($v['name'])); ?>?')">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Vendor Form -->
<div class="modal fade" id="vendorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save_vendor">
                <input type="hidden" name="vendor_id" id="vendorIdInput" value="0">

                <div class="modal-header bg-cream">
                    <h5 class="modal-title fw-bold" id="vendorModalTitle">
                        <i class="bi bi-truck text-warning me-2"></i> Tambah Supplier Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kode Supplier</label>
                        <input type="text" name="code" id="vendorCodeInput" class="form-control" placeholder="e.g. VND-ROAST-01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Supplier / PT <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="vendorNameInput" class="form-control" placeholder="e.g. PT Gayo Kopi Nusantara" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kategori Pasokan</label>
                        <select name="category" id="vendorCatInput" class="form-select">
                            <option value="Roaster & Green Beans">Roaster & Green Beans (Kopi)</option>
                            <option value="Dairy & Milk">Dairy & Milk (Susu)</option>
                            <option value="Packaging">Packaging & Cups (Kemasan)</option>
                            <option value="Syrup & Flavour">Syrup & Flavour (Sirup & Bubuk)</option>
                            <option value="Food & Pastry">Food & Pastry (Bahan Makanan)</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Kontak Person</label>
                            <input type="text" name="contact_person" id="vendorPersonInput" class="form-control" placeholder="Nama sales/PIC">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">No. Telepon / WA</label>
                            <input type="text" name="phone" id="vendorPhoneInput" class="form-control" placeholder="0812xxxx">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" name="email" id="vendorEmailInput" class="form-control" placeholder="sales@vendor.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Alamat Gudang / Kantor</label>
                        <textarea name="address" id="vendorAddressInput" rows="2" class="form-control" placeholder="Alamat lengkap..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-coffee btn-sm px-4">Simpan Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openVendorModal() {
    document.getElementById('vendorIdInput').value = '0';
    document.getElementById('vendorCodeInput').value = 'VND-' + Math.floor(1000 + Math.random() * 9000);
    document.getElementById('vendorNameInput').value = '';
    document.getElementById('vendorPersonInput').value = '';
    document.getElementById('vendorPhoneInput').value = '';
    document.getElementById('vendorEmailInput').value = '';
    document.getElementById('vendorAddressInput').value = '';
    document.getElementById('vendorModalTitle').innerHTML = '<i class="bi bi-truck text-warning me-2"></i> Tambah Supplier Baru';
    
    new bootstrap.Modal(document.getElementById('vendorModal')).show();
}

function editVendor(v) {
    document.getElementById('vendorIdInput').value = v.id;
    document.getElementById('vendorCodeInput').value = v.code;
    document.getElementById('vendorNameInput').value = v.name;
    document.getElementById('vendorCatInput').value = v.category;
    document.getElementById('vendorPersonInput').value = v.contact_person || '';
    document.getElementById('vendorPhoneInput').value = v.phone || '';
    document.getElementById('vendorEmailInput').value = v.email || '';
    document.getElementById('vendorAddressInput').value = v.address || '';
    document.getElementById('vendorModalTitle').innerHTML = '<i class="bi bi-pencil text-warning me-2"></i> Edit Supplier: ' + v.name;

    new bootstrap.Modal(document.getElementById('vendorModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
