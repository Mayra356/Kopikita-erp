<?php
$pageTitle = 'Karyawan & Manajemen Shift';
require_once __DIR__ . '/includes/header.php';

$outletId = $currentOutlet['id'];
$today = date('Y-m-d');

// Handle Actions: Add Employee, Schedule Shift, Clock In, Clock Out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_employee') {
        $nik = trim($_POST['nik'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? 'Barista');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $salary = (float)($_POST['salary'] ?? 0);

        if (!empty($name)) {
            if (empty($nik)) $nik = 'EMP-' . rand(1000, 9999);
            dbInsert('employees', [
                'nik' => $nik,
                'name' => $name,
                'role' => $role,
                'phone' => $phone,
                'email' => $email,
                'salary' => $salary,
                'status' => 'Active'
            ]);
            setFlashMessage('success', "Karyawan {$name} ({$role}) berhasil didaftarkan.");
            header('Location: employees.php');
            exit;
        }
    } elseif ($action === 'schedule_shift') {
        $empId = (int)$_POST['employee_id'];
        $shiftDate = trim($_POST['shift_date'] ?? $today);
        $shiftName = trim($_POST['shift_name'] ?? 'Shift Pagi (07:00 - 15:30)');

        if ($empId > 0) {
            dbInsert('shifts', [
                'employee_id' => $empId,
                'outlet_id' => $outletId,
                'shift_date' => $shiftDate,
                'shift_name' => $shiftName,
                'status' => 'Scheduled'
            ]);
            setFlashMessage('success', 'Jadwal shift berhasil ditambahkan.');
            header('Location: employees.php');
            exit;
        }
    } elseif ($action === 'clock_action') {
        $shiftId = (int)$_POST['shift_id'];
        $clockType = $_POST['clock_type']; // 'in' or 'out'
        $now = date('H:i');

        if ($clockType === 'in') {
            dbUpdate('shifts', ['clock_in' => $now, 'status' => 'On Duty'], 'id = :id', ['id' => $shiftId]);
            setFlashMessage('success', "Clock In tercatat pada jam {$now} WIB. Selamat bertugas!");
        } else {
            $notes = trim($_POST['handover_notes'] ?? '');
            dbUpdate('shifts', ['clock_out' => $now, 'status' => 'Completed', 'handover_notes' => $notes], 'id = :id', ['id' => $shiftId]);
            setFlashMessage('info', "Clock Out tercatat pada jam {$now} WIB. Terima kasih atas kerja keras hari ini.");
        }
        header('Location: employees.php');
        exit;
    }
}

// Fetch all employees
$employees = dbFetchAll("SELECT * FROM employees ORDER BY status ASC, role ASC, name ASC");

// Fetch today's shifts
$todayShifts = dbFetchAll("
    SELECT s.*, e.name as employee_name, e.role as employee_role, e.nik
    FROM shifts s
    JOIN employees e ON s.employee_id = e.id
    WHERE s.outlet_id = ? AND s.shift_date = ?
    ORDER BY s.id ASC
", [$outletId, $today]);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Karyawan & Manajemen Shift</h1>
        <p class="page-subtitle">Direktori tim barista, jadwal shift operasional kedai, dan pencatatan absensi harian</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-coffee" data-bs-toggle="modal" data-bs-target="#newShiftModal">
            <i class="bi bi-calendar-plus me-1"></i> Jadwalkan Shift
        </button>
        <button class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#newEmployeeModal">
            <i class="bi bi-person-plus-fill me-1"></i> Tambah Karyawan Baru
        </button>
    </div>
</div>

<!-- Active Shifts Today -->
<div class="card-coffee border-primary-subtle mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-primary"></i>
            <span class="fw-bold">Jadwal & Absensi Shift Hari Ini (<?php echo formatDate($today, 'date'); ?>)</span>
        </div>
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#newShiftModal">
            <i class="bi bi-plus-lg me-1"></i> Tambah Barista ke Shift
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-coffee table-hover mb-0">
                <thead>
                    <tr>
                        <th>Barista & Role</th>
                        <th>Shift Kerja</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Catatan Handover</th>
                        <th>Status</th>
                        <th class="text-center">Aksi Absensi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($todayShifts)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                Belum ada barista yang dijadwalkan shift hari ini. Klik "Jadwalkan Shift" untuk mengatur penugasan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($todayShifts as $ts): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($ts['employee_name']); ?></div>
                                    <span class="badge bg-light text-muted border"><?php echo htmlspecialchars($ts['employee_role']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-dark border">
                                        <?php echo htmlspecialchars($ts['shift_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($ts['clock_in']): ?>
                                        <span class="fw-bold text-success"><i class="bi bi-check-circle me-1"></i> <?php echo htmlspecialchars($ts['clock_in']); ?> WIB</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ts['clock_out']): ?>
                                        <span class="fw-bold text-muted"><?php echo htmlspecialchars($ts['clock_out']); ?> WIB</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted" style="max-width: 200px;">
                                    <?php echo htmlspecialchars($ts['handover_notes'] ?: '-'); ?>
                                </td>
                                <td>
                                    <?php if ($ts['status'] === 'On Duty'): ?>
                                        <span class="badge-soft-success"><i class="bi bi-lightning-fill"></i> Sedang Bertugas</span>
                                    <?php elseif ($ts['status'] === 'Completed'): ?>
                                        <span class="badge-soft-secondary">Shift Selesai</span>
                                    <?php else: ?>
                                        <span class="badge-soft-warning">Terjadwal</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($ts['status'] === 'Scheduled'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="clock_action">
                                            <input type="hidden" name="shift_id" value="<?php echo $ts['id']; ?>">
                                            <input type="hidden" name="clock_type" value="in">
                                            <button type="submit" class="btn btn-sm btn-success py-0 px-2">
                                                <i class="bi bi-box-arrow-in-right me-1"></i> Clock In
                                            </button>
                                        </form>
                                    <?php elseif ($ts['status'] === 'On Duty'): ?>
                                        <button class="btn btn-sm btn-outline-danger py-0 px-2" 
                                                onclick="openClockOutModal(<?php echo $ts['id']; ?>, '<?php echo htmlspecialchars(addslashes($ts['employee_name'])); ?>')">
                                            <i class="bi bi-box-arrow-right me-1"></i> Clock Out
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">Tuntas</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- All Employees Table -->
<div class="card-coffee">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-people-fill text-primary"></i>
            <span class="fw-bold">Direktori Karyawan & Barista (<?php echo count($employees); ?> Orang)</span>
        </div>
        <button class="btn btn-sm btn-coffee" data-bs-toggle="modal" data-bs-target="#newEmployeeModal">
            <i class="bi bi-plus-lg me-1"></i> Tambah Karyawan
        </button>
    </div>
    <div class="table-responsive">
        <table class="table-coffee table-hover">
            <thead>
                <tr>
                    <th>Karyawan & NIK</th>
                    <th>Jabatan / Role</th>
                    <th>No. Telepon / WhatsApp</th>
                    <th>Email</th>
                    <th>Gaji Pokok / Bulan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $e): ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($e['name']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($e['nik']); ?></div>
                        </td>
                        <td>
                            <span class="badge bg-secondary-subtle text-dark border">
                                <?php echo htmlspecialchars($e['role']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($e['phone']): ?>
                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $e['phone']); ?>" target="_blank" class="text-decoration-none text-success">
                                    <i class="bi bi-whatsapp me-1"></i> <?php echo htmlspecialchars($e['phone']); ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?php echo htmlspecialchars($e['email'] ?: '-'); ?></td>
                        <td class="fw-bold"><?php echo formatRupiah($e['salary']); ?></td>
                        <td>
                            <span class="badge-soft-<?php echo $e['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                <?php echo htmlspecialchars($e['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Karyawan -->
<div class="modal fade" id="newEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save_employee">
                <div class="modal-header bg-cream">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill text-warning me-2"></i> Pendaftaran Karyawan Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nomor Induk Karyawan (NIK)</label>
                        <input type="text" name="nik" class="form-control" placeholder="EMP-1006">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Dimas Pratama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Jabatan / Role</label>
                        <select name="role" class="form-select">
                            <option value="Head Barista">Head Barista</option>
                            <option value="Barista">Barista</option>
                            <option value="Cashier">Kasir (Cashier)</option>
                            <option value="Store Manager">Store Manager</option>
                            <option value="Roaster">Coffee Roaster</option>
                            <option value="Kitchen / Cook">Kitchen / Pastry Cook</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">No. Telepon / WA</label>
                            <input type="text" name="phone" class="form-control" placeholder="0812xxxx">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Gaji Pokok (IDR)</label>
                            <input type="number" step="100000" name="salary" class="form-control" value="4500000">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="nama@kopikita.id">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-coffee btn-sm px-4">Simpan Karyawan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Jadwalkan Shift -->
<div class="modal fade" id="newShiftModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="schedule_shift">
                <div class="modal-header bg-cream">
                    <h5 class="modal-title fw-bold"><i class="bi bi-calendar-plus text-warning me-2"></i> Jadwalkan Shift Barista</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih Barista / Staf <span class="text-danger">*</span></label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">-- Pilih Karyawan --</option>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?php echo $e['id']; ?>">
                                    <?php echo htmlspecialchars($e['name']); ?> (<?php echo htmlspecialchars($e['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal Shift</label>
                        <input type="date" name="shift_date" class="form-control" value="<?php echo $today; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Sesi Shift</label>
                        <select name="shift_name" class="form-select">
                            <option value="Shift Pagi (07:00 - 15:30)">Shift Pagi (07:00 - 15:30) - Opening & Prep</option>
                            <option value="Shift Sore (15:00 - 23:00)">Shift Sore (15:00 - 23:00) - Peak & Closing</option>
                            <option value="Full Day (09:00 - 21:00)">Full Day (09:00 - 21:00)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-coffee btn-sm px-4">Jadwalkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Clock Out & Handover Notes -->
<div class="modal fade" id="clockOutModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="clock_action">
                <input type="hidden" name="clock_type" value="out">
                <input type="hidden" name="shift_id" id="clockOutShiftId">

                <div class="modal-header bg-cream">
                    <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-right text-danger me-2"></i> Konfirmasi Clock Out</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Clock Out untuk: <strong id="clockOutEmployeeName">-</strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Catatan Handover untuk Shift Berikutnya</label>
                        <textarea name="handover_notes" rows="3" class="form-control" placeholder="Contoh: Fresh milk sisa 3 karton, grinder kalibrasi 2.2, es batu aman..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4">Selesaikan Shift & Clock Out</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openClockOutModal(shiftId, name) {
    document.getElementById('clockOutShiftId').value = shiftId;
    document.getElementById('clockOutEmployeeName').innerText = name;
    new bootstrap.Modal(document.getElementById('clockOutModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
