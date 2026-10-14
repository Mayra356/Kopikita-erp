<?php
$pageTitle = 'Buat Purchase Order Baru';
require_once __DIR__ . '/includes/header.php';

$outletId = $currentOutlet['id'];
$preselectedVendor = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;

$vendors = dbFetchAll("SELECT * FROM vendors ORDER BY name ASC");
$rawMaterials = dbFetchAll("SELECT * FROM raw_materials WHERE outlet_id = ? ORDER BY category ASC, name ASC", [$outletId]);

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vendorId = (int)$_POST['vendor_id'];
    $poDate = trim($_POST['po_date'] ?? date('Y-m-d'));
    $expectedDate = trim($_POST['expected_date'] ?? date('Y-m-d', strtotime('+3 days')));
    $notes = trim($_POST['notes'] ?? '');
    $status = trim($_POST['status'] ?? 'Approved');

    $materials = $_POST['material_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unitPrices = $_POST['unit_price'] ?? [];

    if ($vendorId > 0 && !empty($materials)) {
        $db = getDb();
        $db->beginTransaction();

        try {
            $poNumber = generatePONumber();
            $totalAmount = 0;
            $itemsToInsert = [];

            for ($i = 0; $i < count($materials); $i++) {
                $matId = (int)$materials[$i];
                $qty = (float)$quantities[$i];
                $price = (float)$unitPrices[$i];
                if ($matId > 0 && $qty > 0) {
                    $subtotal = $qty * $price;
                    $totalAmount += $subtotal;
                    $itemsToInsert[] = [
                        'raw_material_id' => $matId,
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'subtotal' => $subtotal
                    ];
                }
            }

            if (empty($itemsToInsert)) {
                throw new Exception("Minimal harus ada 1 item bahan baku dengan jumlah valid.");
            }

            $poId = dbInsert('purchase_orders', [
                'po_number' => $poNumber,
                'vendor_id' => $vendorId,
                'outlet_id' => $outletId,
                'po_date' => $poDate,
                'expected_date' => $expectedDate,
                'status' => $status,
                'total_amount' => $totalAmount,
                'notes' => $notes
            ]);

            $stmtItem = $db->prepare("
                INSERT INTO purchase_order_items (po_id, raw_material_id, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($itemsToInsert as $it) {
                $stmtItem->execute([$poId, $it['raw_material_id'], $it['quantity'], $it['unit_price'], $it['subtotal']]);
            }

            $db->commit();
            setFlashMessage('success', "Purchase Order {$poNumber} berhasil diterbitkan dengan total " . formatRupiah($totalAmount));
            header('Location: po_detail.php?id=' . $poId);
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errorMsg = "Gagal membuat PO: " . $e->getMessage();
        }
    } else {
        $errorMsg = "Mohon pilih supplier dan tambahkan setidaknya 1 item bahan baku.";
    }
}
?>

<div class="page-header">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="purchasing.php" class="text-muted text-decoration-none">
                <i class="bi bi-arrow-left"></i> Kembali ke Daftar PO
            </a>
            <span class="text-muted">/</span>
            <span class="fw-bold">PO Baru</span>
        </div>
        <h1 class="page-title">Penerbitan Purchase Order Baru</h1>
        <p class="page-subtitle">Pemesanan bahan baku biji kopi, susu, sirup, atau kemasan ke mitra supplier</p>
    </div>
</div>

<?php if (isset($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($errorMsg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" id="poForm">
    <div class="row g-4">
        <!-- Top: PO Header Details -->
        <div class="col-12 col-lg-4">
            <div class="card-coffee mb-4">
                <div class="card-header">
                    <span class="fw-bold"><i class="bi bi-info-circle me-1"></i> Data Pemesanan PO</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih Supplier / Vendor <span class="text-danger">*</span></label>
                        <select name="vendor_id" class="form-select form-select-lg" required>
                            <option value="">-- Pilih Rekanan Supplier --</option>
                            <?php foreach ($vendors as $v): ?>
                                <option value="<?php echo $v['id']; ?>" <?php echo $preselectedVendor == $v['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($v['name']); ?> (<?php echo htmlspecialchars($v['category']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Belum ada di daftar? <a href="vendors.php" target="_blank">Tambah supplier baru</a></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal PO Dibuat</label>
                        <input type="date" name="po_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Estimasi Pengiriman Tiba</label>
                        <input type="date" name="expected_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Status Awal</label>
                        <select name="status" class="form-select">
                            <option value="Approved">Approved (Langsung Kirim ke Supplier)</option>
                            <option value="Draft">Draft (Simpan Sementara)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Catatan untuk Supplier</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="Instruksi pengiriman, terms pembayaran, atau jadwal dropping barang..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Total Summary Card -->
            <div class="card-coffee border-primary-subtle bg-light">
                <div class="card-body">
                    <div class="text-muted small">TOTAL ESTIMASI BIAYA PO:</div>
                    <div class="fw-bold fs-3 text-primary my-2" id="grandTotalDisplay">Rp 0</div>
                    <div class="text-muted small">
                        Saat barang diterima nanti, sistem akan otomatis menambah stok bahan dan mencatat jurnal akuntansi.
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Items Table -->
        <div class="col-12 col-lg-8">
            <div class="card-coffee h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold"><i class="bi bi-cart-plus me-1"></i> Rincian Bahan Baku yang Dipesan</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-coffee" onclick="addPOItemRow()">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Item Bahan
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table-coffee" id="poItemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 45%;">Bahan Baku</th>
                                    <th style="width: 20%;">Jumlah Order</th>
                                    <th style="width: 25%;" class="text-end">Harga Satuan (IDR)</th>
                                    <th style="width: 10%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="poRowsContainer">
                                <!-- Dynamic rows added by JS -->
                            </tbody>
                        </table>
                    </div>

                    <div id="emptyNotice" class="text-center py-5 text-muted">
                        <i class="bi bi-box-seam fs-1 d-block mb-2 text-secondary"></i>
                        Belum ada item bahan baku dalam PO ini.<br>
                        Klik tombol <strong>"Tambah Item Bahan"</strong> untuk mulai menambahkan pesanan.
                    </div>
                </div>

                <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                    <a href="purchasing.php" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-coffee px-4">
                        <i class="bi bi-check2-circle me-1"></i> Terbitkan Purchase Order
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const rawMaterials = <?php echo json_encode(array_map(function($rm) {
    return [
        'id' => (int)$rm['id'],
        'name' => $rm['name'],
        'unit' => $rm['unit'],
        'cost' => (float)$rm['cost_per_unit'],
        'stock' => (float)$rm['current_stock']
    ];
}, $rawMaterials)); ?>;

function addPOItemRow() {
    document.getElementById('emptyNotice').style.display = 'none';
    const container = document.getElementById('poRowsContainer');

    let options = '<option value="">-- Pilih Bahan Baku --</option>';
    rawMaterials.forEach(rm => {
        options += `<option value="${rm.id}" data-unit="${rm.unit}" data-cost="${rm.cost}">
            ${rm.name} (Stok: ${rm.stock} ${rm.unit})
        </option>`;
    });

    const tr = document.createElement('tr');
    tr.className = 'po-row';
    tr.innerHTML = `
        <td>
            <select name="material_id[]" class="form-select form-select-sm mat-select" onchange="onPOMaterialChange(this)" required>
                ${options}
            </select>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <input type="number" step="0.1" min="0.1" name="quantity[]" class="form-control qty-input" value="10" required oninput="calculatePOTotals()">
                <span class="input-group-text unit-span">satuan</span>
            </div>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text">Rp</span>
                <input type="number" step="0.1" name="unit_price[]" class="form-control price-input text-end" value="0" required oninput="calculatePOTotals()">
            </div>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removePORow(this)">
                <i class="bi bi-x-lg"></i>
            </button>
        </td>
    `;
    container.appendChild(tr);
    calculatePOTotals();
}

function removePORow(btn) {
    btn.closest('tr').remove();
    if (document.querySelectorAll('.po-row').length === 0) {
        document.getElementById('emptyNotice').style.display = 'block';
    }
    calculatePOTotals();
}

function onPOMaterialChange(select) {
    const row = select.closest('tr');
    const opt = select.options[select.selectedIndex];
    const unit = opt.getAttribute('data-unit') || 'satuan';
    const cost = parseFloat(opt.getAttribute('data-cost')) || 0;

    row.querySelector('.unit-span').innerText = unit;
    row.querySelector('.price-input').value = cost;
    calculatePOTotals();
}

function calculatePOTotals() {
    let grandTotal = 0;
    document.querySelectorAll('.po-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        grandTotal += (qty * price);
    });
    document.getElementById('grandTotalDisplay').innerText = formatRupiahJS(grandTotal);
}

// Initialize with 1 row
document.addEventListener('DOMContentLoaded', function () {
    addPOItemRow();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
