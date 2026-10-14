<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$poId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$po = dbFetchOne("
    SELECT po.*, v.name as vendor_name, v.category as vendor_category, v.contact_person, v.phone as vendor_phone, v.email as vendor_email, v.address as vendor_address,
           out.name as outlet_name, out.address as outlet_address, out.phone as outlet_phone
    FROM purchase_orders po
    JOIN vendors v ON po.vendor_id = v.id
    JOIN outlets out ON po.outlet_id = out.id
    WHERE po.id = ?
", [$poId]);

if (!$po) {
    die("Purchase Order tidak ditemukan.");
}

$items = dbFetchAll("
    SELECT poi.*, rm.name as material_name, rm.code as material_code, rm.unit as material_unit
    FROM purchase_order_items poi
    JOIN raw_materials rm ON poi.raw_material_id = rm.id
    WHERE poi.po_id = ?
", [$poId]);

// Workflow Actions: Approve, Receive, Cancel
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'approve' && $po['status'] === 'Draft') {
        dbUpdate('purchase_orders', ['status' => 'Approved'], 'id = :id', ['id' => $poId]);
        setFlashMessage('success', "PO {$po['po_number']} berhasil disetujui (Approved). Siap dikirim ke vendor.");
        header('Location: po_detail.php?id=' . $poId);
        exit;
    } elseif ($action === 'cancel' && $po['status'] !== 'Received') {
        dbUpdate('purchase_orders', ['status' => 'Cancelled'], 'id = :id', ['id' => $poId]);
        setFlashMessage('warning', "PO {$po['po_number']} telah dibatalkan.");
        header('Location: po_detail.php?id=' . $poId);
        exit;
    } elseif ($action === 'receive' && $po['status'] === 'Approved') {
        // Receive goods into inventory!
        $db = getDb();
        $db->beginTransaction();

        try {
            // Update PO status
            dbUpdate('purchase_orders', ['status' => 'Received'], 'id = :id', ['id' => $poId]);

            // Add stock and log mutation for each item
            foreach ($items as $it) {
                $matId = (int)$it['raw_material_id'];
                $qty = (float)$it['quantity'];
                $price = (float)$it['unit_price'];

                // 1. Record stock mutation
                recordStockMutation(
                    $matId,
                    $po['outlet_id'],
                    'PURCHASE_RECEIPT',
                    $po['po_number'],
                    $qty,
                    "Penerimaan Purchase Order dari {$po['vendor_name']}"
                );

                // 2. Update moving average cost per unit
                $currentMat = dbFetchOne("SELECT current_stock, cost_per_unit FROM raw_materials WHERE id = ?", [$matId]);
                if ($currentMat) {
                    $newStock = (float)$currentMat['current_stock'];
                    // Moving average cost calculation
                    $oldStock = max(0, $newStock - $qty);
                    $totalOldValue = $oldStock * (float)$currentMat['cost_per_unit'];
                    $totalNewValue = $totalOldValue + ($qty * $price);
                    $newMovingAverage = $newStock > 0 ? ($totalNewValue / $newStock) : $price;

                    dbUpdate('raw_materials', ['cost_per_unit' => $newMovingAverage], 'id = :id', ['id' => $matId]);
                }
            }

            // 3. Record Accounting Journal (Double-Entry Bookkeeping)
            // Debit: Persediaan Bahan Baku & Packaging (1301)
            // Credit: Bank BCA Giro Operasional (1102)
            recordJournalEntry(
                "Penerimaan Pembelian Barang " . $po['po_number'] . " (" . $po['vendor_name'] . ")",
                $po['po_number'],
                1301, // Persediaan Bahan Baku
                1102, // Bank BCA Giro Operasional
                $po['total_amount']
            );

            $db->commit();
            setFlashMessage('success', "Barang Purchase Order {$po['po_number']} berhasil diterima! Seluruh stok bahan baku dan jurnal keuangan telah otomatis diperbarui.");
            header('Location: po_detail.php?id=' . $poId);
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            setFlashMessage('danger', "Gagal menerima barang: " . $e->getMessage());
            header('Location: po_detail.php?id=' . $poId);
            exit;
        }
    }
}

// Print view
if (isset($_GET['print']) && $_GET['print'] == '1') {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Purchase Order - <?php echo htmlspecialchars($po['po_number']); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: #fff; font-family: sans-serif; padding: 40px; color: #333; }
            .po-title { font-weight: 800; color: #5C3D2E; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body onload="window.print()">
        <div class="container" style="max-width: 800px;">
            <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
                <div>
                    <h2 class="po-title mb-1">KOPIKITA ROASTERY & COFFEE</h2>
                    <div><?php echo htmlspecialchars($po['outlet_name']); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars($po['outlet_address']); ?></div>
                    <div class="small text-muted">Telp: <?php echo htmlspecialchars($po['outlet_phone']); ?></div>
                </div>
                <div class="text-end">
                    <h3 class="fw-bold mb-1">PURCHASE ORDER</h3>
                    <div class="fs-5 text-primary fw-bold"><?php echo htmlspecialchars($po['po_number']); ?></div>
                    <div class="small text-muted">Tanggal: <?php echo formatDate($po['po_date'], 'date'); ?></div>
                    <div class="small text-muted">Est. Tiba: <?php echo formatDate($po['expected_date'], 'date'); ?></div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-6">
                    <div class="fw-bold text-muted small">KEPADA SUPPLIER:</div>
                    <h5 class="fw-bold mt-1"><?php echo htmlspecialchars($po['vendor_name']); ?></h5>
                    <div>UP: <?php echo htmlspecialchars($po['contact_person'] ?: '-'); ?></div>
                    <div>Telp/WA: <?php echo htmlspecialchars($po['vendor_phone'] ?: '-'); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars($po['vendor_address'] ?: '-'); ?></div>
                </div>
                <div class="col-6 text-end">
                    <div class="fw-bold text-muted small">PENGIRIMAN KE:</div>
                    <h5 class="fw-bold mt-1"><?php echo htmlspecialchars($po['outlet_name']); ?></h5>
                    <div class="small text-muted"><?php echo htmlspecialchars($po['outlet_address']); ?></div>
                </div>
            </div>

            <table class="table table-bordered mb-4">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>Kode SKU</th>
                        <th>Deskripsi Bahan Baku</th>
                        <th class="text-center">Kuantitas</th>
                        <th class="text-end">Harga Satuan</th>
                        <th class="text-end">Total Harga</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($items as $it): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($it['material_code']); ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($it['material_name']); ?></td>
                            <td class="text-center"><?php echo number_format($it['quantity'], 1) . ' ' . htmlspecialchars($it['material_unit']); ?></td>
                            <td class="text-end"><?php echo formatRupiah($it['unit_price']); ?></td>
                            <td class="text-end fw-bold"><?php echo formatRupiah($it['subtotal']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-end fw-bold fs-6">TOTAL PEMBELIAN:</td>
                        <td class="text-end fw-bold fs-6 text-primary"><?php echo formatRupiah($po['total_amount']); ?></td>
                    </tr>
                </tfoot>
            </table>

            <?php if (!empty($po['notes'])): ?>
                <div class="p-3 bg-light rounded-3 mb-4">
                    <strong>Catatan:</strong> <?php echo nl2br(htmlspecialchars($po['notes'])); ?>
                </div>
            <?php endif; ?>

            <div class="row text-center mt-5 pt-3">
                <div class="col-6">
                    <p class="small text-muted mb-5">Dibuat Oleh (Purchasing / Store Manager)</p>
                    <p class="fw-bold">( Kevin Wijaya )</p>
                </div>
                <div class="col-6">
                    <p class="small text-muted mb-5">Disetujui Rekanan (Vendor / Supplier)</p>
                    <p class="fw-bold">( <?php echo htmlspecialchars($po['vendor_name']); ?> )</p>
                </div>
            </div>

            <div class="text-center no-print mt-4">
                <button onclick="window.print()" class="btn btn-primary">Cetak Dokumen PO</button>
                <button onclick="window.close()" class="btn btn-secondary">Tutup</button>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$pageTitle = 'Detail PO: ' . $po['po_number'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="purchasing.php" class="text-muted text-decoration-none">
                <i class="bi bi-arrow-left"></i> Kembali ke Daftar PO
            </a>
            <span class="text-muted">/</span>
            <span class="fw-bold"><?php echo htmlspecialchars($po['po_number']); ?></span>
        </div>
        <h1 class="page-title">Purchase Order #<?php echo htmlspecialchars($po['po_number']); ?></h1>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="po_detail.php?id=<?php echo $po['id']; ?>&print=1" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i> Cetak Dokumen PO
        </a>

        <?php if ($po['status'] === 'Draft'): ?>
            <a href="po_detail.php?id=<?php echo $po['id']; ?>&action=approve" class="btn btn-primary" onclick="return confirm('Setujui Purchase Order ini?')">
                <i class="bi bi-check2-circle me-1"></i> Setujui PO (Approve)
            </a>
        <?php elseif ($po['status'] === 'Approved'): ?>
            <a href="po_detail.php?id=<?php echo $po['id']; ?>&action=receive" class="btn btn-success" onclick="return confirm('Terima barang sekarang? Stok bahan baku dan jurnal akan otomatis bertambah.')">
                <i class="bi bi-box-arrow-in-down me-1"></i> Terima Barang Masuk Gudang
            </a>
        <?php endif; ?>

        <?php if ($po['status'] !== 'Received' && $po['status'] !== 'Cancelled'): ?>
            <a href="po_detail.php?id=<?php echo $po['id']; ?>&action=cancel" class="btn btn-outline-danger" onclick="return confirm('Batalkan PO ini?')">
                <i class="bi bi-x-circle me-1"></i> Batalkan
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Left: Line items -->
    <div class="col-12 col-lg-8">
        <div class="card-coffee mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-cart-check-fill text-primary"></i>
                    <span class="fw-bold">Item Bahan yang Dipesan</span>
                </div>
                <span class="badge-soft-<?php 
                    echo $po['status'] === 'Received' ? 'success' : ($po['status'] === 'Approved' ? 'warning' : 'secondary'); 
                ?>">
                    Status: <?php echo htmlspecialchars($po['status']); ?>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table-coffee">
                    <thead>
                        <tr>
                            <th>Bahan Baku</th>
                            <th class="text-center">Kuantitas</th>
                            <th class="text-end">Harga Satuan</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($it['material_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($it['material_code']); ?></div>
                                </td>
                                <td class="text-center fw-bold">
                                    <?php echo number_format($it['quantity'], 1); ?> <?php echo htmlspecialchars($it['material_unit']); ?>
                                </td>
                                <td class="text-end"><?php echo formatRupiah($it['unit_price']); ?></td>
                                <td class="text-end fw-bold"><?php echo formatRupiah($it['subtotal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-active">
                            <td colspan="3" class="text-end fw-bold fs-6">TOTAL PURCHASE ORDER:</td>
                            <td class="text-end fw-bold fs-6 text-primary"><?php echo formatRupiah($po['total_amount']); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?php if (!empty($po['notes'])): ?>
            <div class="card-coffee">
                <div class="card-header"><span class="fw-bold">Catatan Pengiriman / Terms</span></div>
                <div class="card-body">
                    <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($po['notes'])); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Metadata & Supplier Info -->
    <div class="col-12 col-lg-4">
        <!-- Supplier Details -->
        <div class="card-coffee mb-4">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-truck me-1"></i> Data Supplier</span>
            </div>
            <div class="card-body">
                <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($po['vendor_name']); ?></h5>
                <span class="badge bg-light text-dark border mb-3"><?php echo htmlspecialchars($po['vendor_category']); ?></span>

                <div class="mb-2">
                    <span class="text-muted small">Kontak Person:</span>
                    <div class="fw-bold"><?php echo htmlspecialchars($po['contact_person'] ?: '-'); ?></div>
                </div>
                <div class="mb-2">
                    <span class="text-muted small">Telepon / WhatsApp:</span>
                    <div>
                        <?php if ($po['vendor_phone']): ?>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $po['vendor_phone']); ?>" target="_blank" class="text-decoration-none text-success fw-bold">
                                <i class="bi bi-whatsapp me-1"></i> <?php echo htmlspecialchars($po['vendor_phone']); ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-2">
                    <span class="text-muted small">Alamat Supplier:</span>
                    <div class="text-muted small"><?php echo htmlspecialchars($po['vendor_address'] ?: '-'); ?></div>
                </div>
            </div>
        </div>

        <!-- PO Timeline -->
        <div class="card-coffee">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-calendar3 me-1"></i> Jadwal Pengadaan</span>
            </div>
            <div class="card-body">
                <div class="mb-3 pb-3 border-bottom">
                    <div class="text-muted small">Tanggal PO Terbit</div>
                    <div class="fw-bold"><?php echo formatDate($po['po_date'], 'date'); ?></div>
                </div>
                <div class="mb-3 pb-3 border-bottom">
                    <div class="text-muted small">Estimasi Barang Tiba</div>
                    <div class="fw-bold"><?php echo formatDate($po['expected_date'], 'date'); ?></div>
                </div>
                <div>
                    <div class="text-muted small">Lokasi Penerimaan Gudang</div>
                    <div class="fw-bold"><?php echo htmlspecialchars($po['outlet_name']); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars($po['outlet_address']); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
