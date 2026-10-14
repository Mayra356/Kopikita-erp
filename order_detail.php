<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order = dbFetchOne("
    SELECT o.*, out.name as outlet_name, out.address as outlet_address, out.phone as outlet_phone,
           c.tier as customer_tier, c.points as customer_points
    FROM orders o
    LEFT JOIN outlets out ON o.outlet_id = out.id
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.id = ?
", [$orderId]);

if (!$order) {
    die("Transaksi tidak ditemukan.");
}

$items = dbFetchAll("
    SELECT oi.*, p.code as product_code
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
", [$orderId]);

// Fetch associated stock mutations for this invoice
$mutations = dbFetchAll("
    SELECT sm.*, rm.name as material_name, rm.unit as material_unit
    FROM stock_mutations sm
    JOIN raw_materials rm ON sm.raw_material_id = rm.id
    WHERE sm.reference_no = ?
    ORDER BY sm.id ASC
", [$order['invoice_no']]);

// Check if print preview directly requested
$isPrintOnly = isset($_GET['print']) && $_GET['print'] == '1';

if ($isPrintOnly) {
    // Render clean thermal print preview
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Struk - <?php echo htmlspecialchars($order['invoice_no']); ?></title>
        <link rel="stylesheet" href="assets/css/style.css">
        <style>
            body { background: #fff; padding: 20px; display: flex; justify-content: center; }
            .thermal-receipt { width: 300px; font-family: 'Courier New', Courier, monospace; }
            @media print {
                body { padding: 0; }
                .no-print { display: none !important; }
            }
        </style>
    </head>
    <body onload="window.print()">
        <div class="thermal-receipt">
            <div class="text-center mb-3">
                <h3 style="font-weight: 800; margin-bottom: 2px;">KOPIKITA</h3>
                <div style="font-size: 0.85rem;"><?php echo htmlspecialchars($order['outlet_name']); ?></div>
                <div style="font-size: 0.75rem;"><?php echo htmlspecialchars($order['outlet_address']); ?></div>
                <div style="font-size: 0.75rem;">Telp: <?php echo htmlspecialchars($order['outlet_phone']); ?></div>
            </div>

            <div class="receipt-divider">--------------------------------</div>

            <div style="font-size: 0.8rem; line-height: 1.4;">
                <div>No: <strong><?php echo htmlspecialchars($order['invoice_no']); ?></strong></div>
                <div>Tgl: <?php echo formatDate($order['created_at']); ?></div>
                <div>Kasir: <?php echo htmlspecialchars($order['cashier_name']); ?></div>
                <div>Pelanggan: <?php echo htmlspecialchars($order['customer_name']); ?></div>
                <div>Tipe: <?php echo htmlspecialchars($order['order_type']); ?><?php echo $order['table_no'] ? " (Meja {$order['table_no']})" : ""; ?></div>
            </div>

            <div class="receipt-divider">--------------------------------</div>

            <table style="width: 100%; font-size: 0.82rem; border-collapse: collapse;">
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td colspan="2" style="font-weight: bold;"><?php echo htmlspecialchars($it['product_name']); ?></td>
                    </tr>
                    <tr>
                        <td style="color: #444; padding-left: 6px;">
                            <?php echo $it['quantity']; ?> x <?php echo formatRupiah($it['price'], false); ?>
                            <?php if ($it['notes']): ?><br><em>*<?php echo htmlspecialchars($it['notes']); ?></em><?php endif; ?>
                        </td>
                        <td style="text-align: right; font-weight: bold; vertical-align: top;"><?php echo formatRupiah($it['subtotal'], false); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <div class="receipt-divider">--------------------------------</div>

            <div style="font-size: 0.82rem; line-height: 1.5;">
                <div class="d-flex justify-content-between">
                    <span>Subtotal</span>
                    <span><?php echo formatRupiah($order['subtotal']); ?></span>
                </div>
                <?php if ($order['discount'] > 0): ?>
                    <div class="d-flex justify-content-between">
                        <span>Diskon</span>
                        <span>-<?php echo formatRupiah($order['discount']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between">
                    <span>PB1 (10%)</span>
                    <span><?php echo formatRupiah($order['tax']); ?></span>
                </div>
                <div class="d-flex justify-content-between" style="font-weight: bold; font-size: 1rem; border-top: 1px dashed #000; padding-top: 4px; margin-top: 4px;">
                    <span>TOTAL</span>
                    <span><?php echo formatRupiah($order['total_amount']); ?></span>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <span>Bayar (<?php echo htmlspecialchars($order['payment_method']); ?>)</span>
                    <span><?php echo formatRupiah($order['cash_given']); ?></span>
                </div>
                <?php if ($order['payment_method'] === 'Cash'): ?>
                    <div class="d-flex justify-content-between">
                        <span>Kembali</span>
                        <span><?php echo formatRupiah($order['change_amount']); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="receipt-divider">--------------------------------</div>

            <div class="text-center" style="font-size: 0.75rem; color: #555;">
                <p>Terima kasih atas kunjungan Anda!<br>Follow IG: @kopikita.id</p>
            </div>
            
            <div class="text-center no-print mt-4">
                <button onclick="window.print()" class="btn btn-sm btn-primary">Cetak Struk</button>
                <button onclick="window.close()" class="btn btn-sm btn-secondary">Tutup</button>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$pageTitle = 'Detail Transaksi ' . $order['invoice_no'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="orders.php" class="text-muted text-decoration-none">
                <i class="bi bi-arrow-left"></i> Kembali ke Riwayat
            </a>
            <span class="text-muted">/</span>
            <span class="fw-bold"><?php echo htmlspecialchars($order['invoice_no']); ?></span>
        </div>
        <h1 class="page-title">Invoice #<?php echo htmlspecialchars($order['invoice_no']); ?></h1>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="order_detail.php?id=<?php echo $order['id']; ?>&print=1" target="_blank" class="btn btn-coffee">
            <i class="bi bi-printer me-1"></i> Cetak Struk Kasir
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left: Order Details & Items -->
    <div class="col-12 col-lg-8">
        <div class="card-coffee mb-4">
            <div class="card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-bag-check-fill text-primary"></i>
                    <span class="fw-bold">Item Pesanan (<?php echo count($items); ?> Menu)</span>
                </div>
                <span class="badge-soft-success">
                    <i class="bi bi-check-circle-fill me-1"></i> <?php echo htmlspecialchars($order['payment_status']); ?>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table-coffee">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th class="text-end">Harga Satuan</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($it['product_name']); ?></div>
                                    <?php if (!empty($it['notes'])): ?>
                                        <div class="text-muted small">
                                            <i class="bi bi-chat-left-text me-1"></i> Catatan: <em><?php echo htmlspecialchars($it['notes']); ?></em>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?php echo formatRupiah($it['price']); ?></td>
                                <td class="text-center fw-bold"><?php echo $it['quantity']; ?></td>
                                <td class="text-end fw-bold"><?php echo formatRupiah($it['subtotal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end fw-bold">Subtotal:</td>
                            <td class="text-end fw-bold"><?php echo formatRupiah($order['subtotal']); ?></td>
                        </tr>
                        <?php if ($order['discount'] > 0): ?>
                            <tr>
                                <td colspan="3" class="text-end text-danger">Diskon Pelanggan:</td>
                                <td class="text-end text-danger">-<?php echo formatRupiah($order['discount']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="3" class="text-end text-muted">Pajak Restoran (PB1 10%):</td>
                            <td class="text-end text-muted"><?php echo formatRupiah($order['tax']); ?></td>
                        </tr>
                        <tr class="table-active">
                            <td colspan="3" class="text-end fw-bold fs-6">TOTAL DIBAYAR:</td>
                            <td class="text-end fw-bold fs-6 text-primary"><?php echo formatRupiah($order['total_amount']); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- BOM Material Consumption Card (ERP Feature) -->
        <div class="card-coffee">
            <div class="card-header bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-diagram-3-fill text-warning"></i>
                    <span class="fw-bold">Audit Pengurangan Bahan Baku Otomatis (BOM Recipe)</span>
                </div>
                <span class="badge bg-secondary-subtle text-dark border">ERP Inventory Deduction</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table-coffee mb-0" style="font-size: 0.85rem;">
                        <thead>
                            <tr>
                                <th>Bahan Baku</th>
                                <th>Jumlah Terpotong</th>
                                <th>Stok Sebelum</th>
                                <th>Stok Sesudah</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mutations)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-3 text-muted">Tidak ada mutasi bahan baku tercatat</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($mutations as $m): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($m['material_name']); ?></td>
                                        <td class="text-danger fw-bold">
                                            <?php echo number_format($m['qty_change'], 1) . ' ' . htmlspecialchars($m['material_unit']); ?>
                                        </td>
                                        <td class="text-muted"><?php echo number_format($m['qty_before'], 1) . ' ' . htmlspecialchars($m['material_unit']); ?></td>
                                        <td class="text-dark fw-bold"><?php echo number_format($m['qty_after'], 1) . ' ' . htmlspecialchars($m['material_unit']); ?></td>
                                        <td class="text-muted small"><?php echo htmlspecialchars($m['notes']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Summary & Customer Sidebar -->
    <div class="col-12 col-lg-4">
        <!-- Metadata Info -->
        <div class="card-coffee mb-4">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-info-circle me-1"></i> Informasi Transaksi</span>
            </div>
            <div class="card-body">
                <div class="mb-3 pb-3 border-bottom">
                    <div class="text-muted small">Waktu Transaksi</div>
                    <div class="fw-bold"><?php echo formatDate($order['created_at']); ?></div>
                </div>
                <div class="mb-3 pb-3 border-bottom">
                    <div class="text-muted small">Outlet Cabang</div>
                    <div class="fw-bold"><?php echo htmlspecialchars($order['outlet_name']); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars($order['outlet_address']); ?></div>
                </div>
                <div class="mb-3 pb-3 border-bottom">
                    <div class="text-muted small">Kasir Bertugas</div>
                    <div class="fw-bold"><?php echo htmlspecialchars($order['cashier_name']); ?></div>
                </div>
                <div class="mb-3 pb-3 border-bottom">
                    <div class="text-muted small">Tipe Pemesanan</div>
                    <div class="fw-bold">
                        <span class="badge bg-light text-dark border">
                            <?php echo htmlspecialchars($order['order_type']); ?>
                            <?php echo $order['table_no'] ? " (Meja #{$order['table_no']})" : ""; ?>
                        </span>
                    </div>
                </div>
                <div>
                    <div class="text-muted small">Metode Pembayaran</div>
                    <div class="fw-bold fs-6 text-primary mt-1">
                        <i class="bi <?php echo $order['payment_method'] === 'Cash' ? 'bi-cash-stack' : 'bi-qr-code'; ?> me-1"></i>
                        <?php echo htmlspecialchars($order['payment_method']); ?>
                    </div>
                    <?php if ($order['payment_method'] === 'Cash'): ?>
                        <div class="small text-muted mt-1">Tunai Diterima: <?php echo formatRupiah($order['cash_given']); ?></div>
                        <div class="small text-muted">Kembalian: <?php echo formatRupiah($order['change_amount']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Customer CRM Card -->
        <div class="card-coffee">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-person-heart me-1"></i> Data Pelanggan</span>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 1.25rem;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                        <?php if ($order['customer_id']): ?>
                            <span class="badge bg-warning text-dark">Member Tier <?php echo htmlspecialchars($order['customer_tier']); ?></span>
                        <?php else: ?>
                            <span class="badge bg-light text-muted border">Walk-in Guest</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($order['customer_id']): ?>
                    <div class="p-3 bg-light rounded-3 text-muted small">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Total Poin Member:</span>
                            <span class="fw-bold text-dark"><?php echo number_format($order['customer_points']); ?> Poin</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
