<?php
$pageTitle = 'Member & Program Loyalitas (CRM)';
require_once __DIR__ . '/includes/header.php';

// Handle Add / Edit Member
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_customer') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $tier = trim($_POST['tier'] ?? 'Bronze');
        $points = (int)($_POST['points'] ?? 0);

        if (!empty($name)) {
            if ($customerId > 0) {
                dbUpdate('customers', [
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'tier' => $tier,
                    'points' => $points
                ], 'id = :id', ['id' => $customerId]);
                setFlashMessage('success', "Data member {$name} berhasil diperbarui.");
            } else {
                if (empty($code)) $code = 'CST-' . rand(1000, 9999);
                dbInsert('customers', [
                    'code' => $code,
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'tier' => $tier,
                    'points' => $points,
                    'total_spent' => 0
                ]);
                setFlashMessage('success', "Member loyalitas baru {$name} ({$tier}) berhasil didaftarkan.");
            }
            header('Location: customers.php');
            exit;
        }
    }
}

// Fetch customers with transaction stats
$customers = dbFetchAll("
    SELECT c.*, COUNT(o.id) as visit_count, MAX(o.created_at) as last_visit
    FROM customers c
    LEFT JOIN orders o ON c.id = o.customer_id
    GROUP BY c.id
    ORDER BY c.total_spent DESC, c.points DESC
");

// Metrics
$totalMembers = count($customers);
$goldCount = count(array_filter($customers, fn($c) => $c['tier'] === 'Gold'));
$silverCount = count(array_filter($customers, fn($c) => $c['tier'] === 'Silver'));
$totalPoints = array_sum(array_column($customers, 'points'));
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Member & Program Loyalitas Pelanggan (CRM)</h1>
        <p class="page-subtitle">Poin reward, diskon bertingkat (Gold 10%, Silver 5%), dan retensi pelanggan setia</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-coffee" onclick="openCustomerModal()">
            <i class="bi bi-person-plus-fill me-1"></i> Daftarkan Member Baru
        </button>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Total Member Terdaftar</div>
                    <div class="kpi-value"><?php echo $totalMembers; ?> <span style="font-size: 1rem; font-weight: 500;">Member</span></div>
                    <div class="kpi-desc">Pelanggan terhubung loyalitas</div>
                </div>
                <div class="kpi-icon bg-primary-subtle text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card warning">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Member Tier Gold (10% Off)</div>
                    <div class="kpi-value text-warning"><?php echo $goldCount; ?> <span style="font-size: 1rem; font-weight: 500;">VIP</span></div>
                    <div class="kpi-desc">Diskon otomatis saat checkout POS</div>
                </div>
                <div class="kpi-icon bg-warning-subtle text-warning">
                    <i class="bi bi-award-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card info">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Member Tier Silver (5% Off)</div>
                    <div class="kpi-value text-primary"><?php echo $silverCount; ?> <span style="font-size: 1rem; font-weight: 500;">Member</span></div>
                    <div class="kpi-desc">Pelanggan reguler aktif</div>
                </div>
                <div class="kpi-icon bg-info-subtle text-info">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-title">Total Poin Loyalitas</div>
                    <div class="kpi-value text-success"><?php echo number_format($totalPoints); ?> <span style="font-size: 1rem; font-weight: 500;">Pts</span></div>
                    <div class="kpi-desc">+1 Poin tiap belanja Rp 10.000</div>
                </div>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="bi bi-stars"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customers Table -->
<div class="card-coffee">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-person-lines-fill text-primary"></i>
            <span class="fw-bold">Database Pelanggan Tetap</span>
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Cetak Daftar
        </button>
    </div>
    <div class="table-responsive">
        <table class="table-coffee table-hover">
            <thead>
                <tr>
                    <th>Pelanggan & Kode</th>
                    <th>Nomor WhatsApp / Kontak</th>
                    <th>Tier Loyalitas</th>
                    <th class="text-center">Poin Poin</th>
                    <th>Total Belanja (LTV)</th>
                    <th>Kunjungan</th>
                    <th>Kunjungan Terakhir</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">Belum ada data member terdaftar</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($c['name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($c['code']); ?> &bull; <?php echo htmlspecialchars($c['email'] ?: '-'); ?></div>
                            </td>
                            <td>
                                <?php if ($c['phone']): ?>
                                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $c['phone']); ?>" target="_blank" class="text-decoration-none text-success fw-bold">
                                        <i class="bi bi-whatsapp me-1"></i> <?php echo htmlspecialchars($c['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['tier'] === 'Gold'): ?>
                                    <span class="badge bg-warning text-dark border"><i class="bi bi-award-fill me-1"></i> Gold (10%)</span>
                                <?php elseif ($c['tier'] === 'Silver'): ?>
                                    <span class="badge bg-secondary-subtle text-dark border"><i class="bi bi-shield-fill me-1"></i> Silver (5%)</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Bronze</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success fs-6 fw-bold">
                                    <i class="bi bi-star-fill text-warning me-1"></i> <?php echo number_format($c['points']); ?>
                                </span>
                            </td>
                            <td class="fw-bold text-primary">
                                <?php echo formatRupiah($c['total_spent']); ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo (int)$c['visit_count']; ?>x Transaksi</span>
                            </td>
                            <td class="small text-muted">
                                <?php echo $c['last_visit'] ? formatDate($c['last_visit'], 'date') : '-'; ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-coffee" title="Edit Member" onclick="editCustomer(<?php echo htmlspecialchars(json_encode($c)); ?>)">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form Member -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save_customer">
                <input type="hidden" name="customer_id" id="customerIdInput" value="0">

                <div class="modal-header bg-cream">
                    <h5 class="modal-title fw-bold" id="customerModalTitle">
                        <i class="bi bi-person-heart text-warning me-2"></i> Pendaftaran Member Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kode Member</label>
                        <input type="text" name="code" id="customerCodeInput" class="form-control" placeholder="CST-1005">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="customerNameInput" class="form-control" placeholder="e.g. Andika Pratama" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">No. WhatsApp / HP</label>
                            <input type="text" name="phone" id="customerPhoneInput" class="form-control" placeholder="0812xxxx" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Tier Member</label>
                            <select name="tier" id="customerTierInput" class="form-select">
                                <option value="Bronze">Bronze (Member Baru)</option>
                                <option value="Silver">Silver (Diskon 5%)</option>
                                <option value="Gold">Gold (VIP - Diskon 10%)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" name="email" id="customerEmailInput" class="form-control" placeholder="member@gmail.com">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-bold">Poin Awal</label>
                            <input type="number" name="points" id="customerPointsInput" class="form-control" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-coffee btn-sm px-4">Simpan Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCustomerModal() {
    document.getElementById('customerIdInput').value = '0';
    document.getElementById('customerCodeInput').value = 'CST-' + Math.floor(1000 + Math.random() * 9000);
    document.getElementById('customerNameInput').value = '';
    document.getElementById('customerPhoneInput').value = '';
    document.getElementById('customerEmailInput').value = '';
    document.getElementById('customerTierInput').value = 'Bronze';
    document.getElementById('customerPointsInput').value = '0';
    document.getElementById('customerModalTitle').innerHTML = '<i class="bi bi-person-heart text-warning me-2"></i> Pendaftaran Member Baru';

    new bootstrap.Modal(document.getElementById('customerModal')).show();
}

function editCustomer(c) {
    document.getElementById('customerIdInput').value = c.id;
    document.getElementById('customerCodeInput').value = c.code;
    document.getElementById('customerNameInput').value = c.name;
    document.getElementById('customerPhoneInput').value = c.phone || '';
    document.getElementById('customerEmailInput').value = c.email || '';
    document.getElementById('customerTierInput').value = c.tier || 'Bronze';
    document.getElementById('customerPointsInput').value = c.points || '0';
    document.getElementById('customerModalTitle').innerHTML = '<i class="bi bi-pencil text-warning me-2"></i> Edit Member: ' + c.name;

    new bootstrap.Modal(document.getElementById('customerModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
