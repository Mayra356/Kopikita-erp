<?php
$pageTitle = 'Point of Sale (Kasir)';
require_once __DIR__ . '/includes/header.php';

$outletId = $currentOutlet['id'];

// Get Categories
$categories = dbFetchAll("SELECT * FROM categories ORDER BY sort_order ASC");

// Get Active Products
$products = dbFetchAll("
    SELECT p.*, c.slug as category_slug, c.name as category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1
    ORDER BY p.category_id ASC, p.name ASC
");

// Get Customers for membership selector
$customers = dbFetchAll("SELECT * FROM customers ORDER BY name ASC");

// Check active cash register
$cashRegister = dbFetchOne("
    SELECT * FROM cash_registers 
    WHERE outlet_id = ? AND status = 'Open' 
    ORDER BY id DESC LIMIT 1
", [$outletId]);
?>

<div class="pos-container">
    <!-- Left: Catalog & Categories -->
    <div class="pos-catalog">
        <!-- Top Toolbar: Search & Register Warning -->
        <div class="d-flex align-items-center justify-content-between mb-3 gap-3">
            <div class="input-group" style="max-width: 400px;">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="posSearch" class="form-control border-start-0 ps-0" placeholder="Cari menu kopi, pastry, beans (Ctrl+/)...">
            </div>

            <div class="d-flex align-items-center gap-2">
                <?php if ($cashRegister): ?>
                    <span class="badge bg-success-subtle text-success border px-3 py-2">
                        <i class="bi bi-cash-stack me-1"></i> Kasir: <?php echo htmlspecialchars($cashRegister['cashier_name']); ?> (Shift Buka)
                    </span>
                <?php else: ?>
                    <a href="cash_register.php" class="badge bg-warning-subtle text-warning border px-3 py-2 text-decoration-none">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Buka Kasir Terlebih Dahulu
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Categories horizontal pills -->
        <div class="pos-categories" id="categoryTabs">
            <button class="category-pill active" data-category="all">
                <i class="bi bi-grid-fill"></i> Semua Menu
            </button>
            <?php foreach ($categories as $cat): ?>
                <button class="category-pill" data-category="<?php echo htmlspecialchars($cat['slug']); ?>">
                    <i class="bi <?php echo htmlspecialchars($cat['icon']); ?>"></i>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Products Grid -->
        <div class="pos-grid" id="posProductGrid">
            <?php foreach ($products as $p): ?>
                <?php 
                    // Calculate estimated COGS
                    $cogs = calculateProductCOGS($p['id']);
                ?>
                <div class="product-pos-card" 
                     data-id="<?php echo $p['id']; ?>"
                     data-name="<?php echo htmlspecialchars($p['name']); ?>"
                     data-price="<?php echo (float)$p['price']; ?>"
                     data-category="<?php echo htmlspecialchars($p['category_slug']); ?>"
                     onclick="addToCart(<?php echo htmlspecialchars(json_encode([
                         'id' => (int)$p['id'],
                         'name' => $p['name'],
                         'price' => (float)$p['price'],
                         'cogs' => $cogs
                     ])); ?>)">
                    
                    <div class="card-thumb">
                        <i class="bi <?php echo htmlspecialchars($p['image_icon'] ?: 'bi-cup-hot'); ?>"></i>
                    </div>
                    <div>
                        <h4 class="product-name"><?php echo htmlspecialchars($p['name']); ?></h4>
                        <div class="text-muted small mb-2 text-truncate"><?php echo htmlspecialchars($p['description']); ?></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-auto">
                        <div class="product-price"><?php echo formatRupiah($p['price']); ?></div>
                        <span class="btn btn-sm btn-outline-coffee p-1 px-2 rounded-circle">
                            <i class="bi bi-plus-lg"></i>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right: Cart & Checkout Drawer -->
    <div class="pos-cart">
        <!-- Cart Header: Order Type & Table -->
        <div class="p-3 border-bottom bg-light">
            <div class="d-flex gap-2 mb-2">
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="orderType" id="typeDineIn" value="Dine In" checked onchange="toggleTableInput(true)">
                    <label class="btn btn-outline-secondary btn-sm" for="typeDineIn">Dine In</label>

                    <input type="radio" class="btn-check" name="orderType" id="typeTakeAway" value="Take Away" onchange="toggleTableInput(false)">
                    <label class="btn btn-outline-secondary btn-sm" for="typeTakeAway">Take Away</label>

                    <input type="radio" class="btn-check" name="orderType" id="typeDelivery" value="Delivery" onchange="toggleTableInput(false)">
                    <label class="btn btn-outline-secondary btn-sm" for="typeDelivery">Delivery</label>
                </div>
            </div>

            <div class="row g-2">
                <div class="col-7">
                    <select class="form-select form-select-sm" id="posCustomerSelect" onchange="applyCustomerDiscount()">
                        <option value="0" data-tier="None" data-points="0">Guest / Pelanggan Umum</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>" data-name="<?php echo htmlspecialchars($c['name']); ?>" data-tier="<?php echo $c['tier']; ?>" data-points="<?php echo $c['points']; ?>">
                                <?php echo htmlspecialchars($c['name']); ?> (<?php echo $c['tier']; ?> - <?php echo $c['points']; ?> Poin)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-5" id="tableInputContainer">
                    <input type="text" class="form-control form-control-sm" id="posTableNo" placeholder="No. Meja (01)">
                </div>
            </div>
        </div>

        <!-- Cart Items List -->
        <div class="cart-items-wrapper" id="cartItemsList">
            <div class="text-center py-5 text-muted" id="emptyCartMessage">
                <i class="bi bi-cup-hot fs-1 d-block mb-2 text-black-50"></i>
                <p class="mb-0">Keranjang pesanan masih kosong</p>
                <small>Pilih menu di sebelah kiri untuk menambahkan</small>
            </div>
        </div>

        <!-- Cart Financial Summary -->
        <div class="p-3 border-top bg-light">
            <div class="d-flex justify-content-between mb-1 small text-muted">
                <span>Subtotal</span>
                <span id="lblSubtotal">Rp 0</span>
            </div>
            <div class="d-flex justify-content-between mb-1 small text-muted">
                <div class="d-flex align-items-center gap-1">
                    <span>Diskon Member</span>
                    <span class="badge bg-secondary-subtle text-dark" id="lblDiscountBadge">0%</span>
                </div>
                <span id="lblDiscountAmount" class="text-danger">- Rp 0</span>
            </div>
            <div class="d-flex justify-content-between mb-2 small text-muted">
                <span>Pajak Restoran (PB1 10%)</span>
                <span id="lblTax">Rp 0</span>
            </div>
            <div class="d-flex justify-content-between fs-5 fw-bold text-primary border-top pt-2 mb-3">
                <span>Total Bayar</span>
                <span id="lblGrandTotal">Rp 0</span>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearCart()" title="Kosongkan Pesanan">
                    <i class="bi bi-trash"></i>
                </button>
                <button type="button" class="btn btn-accent w-100 py-2 fw-bold" id="btnPay" onclick="openPaymentModal()" disabled>
                    <i class="bi bi-credit-card-2-front-fill me-1"></i> Proses Pembayaran
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pembayaran & Checkout -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-wallet2 text-warning me-2"></i> Pembayaran Transaksi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3">
                    <div class="text-muted small text-uppercase fw-bold">Total Yang Harus Dibayar</div>
                    <div class="display-6 fw-bold text-primary" id="modalTotalPay">Rp 0</div>
                </div>

                <!-- Payment Methods Tabs -->
                <ul class="nav nav-pills nav-fill mb-3" id="paymentMethodTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active fw-bold" id="tab-cash" data-bs-toggle="pill" data-bs-target="#method-cash" type="button" onclick="setPaymentMethod('Cash')">
                            <i class="bi bi-cash me-1"></i> Tunai (Cash)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-bold" id="tab-qris" data-bs-toggle="pill" data-bs-target="#method-qris" type="button" onclick="setPaymentMethod('QRIS')">
                            <i class="bi bi-qr-code me-1"></i> QRIS
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-bold" id="tab-card" data-bs-toggle="pill" data-bs-target="#method-card" type="button" onclick="setPaymentMethod('Debit Card')">
                            <i class="bi bi-credit-card me-1"></i> EDC / Debit
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Cash Method -->
                    <div class="tab-pane fade show active" id="method-cash">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Uang Diterima dari Pelanggan</label>
                            <input type="number" class="form-control form-control-lg text-end fw-bold" id="inputCashGiven" placeholder="0" oninput="calculateChange()">
                        </div>

                        <!-- Quick Cash Buttons -->
                        <div class="d-flex gap-2 mb-3 flex-wrap">
                            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="setCashAmount('exact')">Uang Pas</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="setCashAmount(50000)">Rp 50.000</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="setCashAmount(100000)">Rp 100.000</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="setCashAmount(200000)">Rp 200.000</button>
                        </div>

                        <div class="p-3 bg-light rounded border text-center">
                            <div class="text-muted small fw-bold">Kembalian</div>
                            <div class="fs-4 fw-bold text-success" id="lblCashChange">Rp 0</div>
                        </div>
                    </div>

                    <!-- QRIS Method -->
                    <div class="tab-pane fade text-center" id="method-qris">
                        <div class="p-3 bg-light rounded border mb-3">
                            <p class="small text-muted mb-2">Scan kode QRIS di bawah menggunakan GoPay, OVO, Dana, BCA Mobile, dll.</p>
                            <!-- Simulated QR Code -->
                            <div class="d-inline-block p-3 bg-white border rounded shadow-sm">
                                <i class="bi bi-qr-code text-dark" style="font-size: 130px; display: block; line-height: 1;"></i>
                            </div>
                            <div class="fw-bold mt-2 text-primary">NMID: ID1020261014KPK</div>
                            <small class="text-success"><i class="bi bi-check-circle"></i> Siap scan dan menerima pembayaran</small>
                        </div>
                    </div>

                    <!-- Debit Card Method -->
                    <div class="tab-pane fade" id="method-card">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Bank EDC Mesin</label>
                            <select class="form-select" id="cardBank">
                                <option value="BCA">BCA EDC</option>
                                <option value="Mandiri">Mandiri EDC</option>
                                <option value="BRI">BRI EDC</option>
                                <option value="BNI">BNI EDC</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nomor Persetujuan / Trace No.</label>
                            <input type="text" class="form-control" id="cardTrace" placeholder="Contoh: 894321">
                        </div>
                    </div>
                </div>

                <!-- Notes for Barista/Kitchen -->
                <div class="mt-3">
                    <label class="form-label small fw-bold">Catatan Pesanan Keseluruhan (Opsional)</label>
                    <input type="text" class="form-control form-control-sm" id="orderGeneralNotes" placeholder="Contoh: Takeaway plastik terpisah, sedotan kertas">
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success fw-bold px-4" id="btnSubmitOrder" onclick="submitPOSOrder()">
                    <i class="bi bi-printer-fill me-1"></i> Simpan & Cetak Struk
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Struk Thermal Preview -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="resetPOS()"></button>
            </div>
            <div class="modal-body p-2 pt-0" id="receiptModalBody">
                <!-- Thermal receipt content injected here -->
            </div>
            <div class="modal-footer border-0 p-3 justify-content-center">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" onclick="resetPOS()">Selesai (Pesanan Baru)</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak Struk
                </button>
            </div>
        </div>
    </div>
</div>

<!-- POS Client Logic -->
<script>
let cart = [];
let currentPaymentMethod = 'Cash';
let grandTotal = 0;
let subtotalAmount = 0;
let discountPercent = 0;
let discountAmount = 0;
let taxAmount = 0;

// Category Filter
document.querySelectorAll('.category-pill').forEach(pill => {
    pill.addEventListener('click', function () {
        document.querySelectorAll('.category-pill').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        const cat = this.getAttribute('data-category');
        
        document.querySelectorAll('.product-pos-card').forEach(card => {
            if (cat === 'all' || card.getAttribute('data-category') === cat) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    });
});

// Search Filter
document.getElementById('posSearch').addEventListener('keyup', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.product-pos-card').forEach(card => {
        const name = card.getAttribute('data-name').toLowerCase();
        card.style.display = name.includes(q) ? 'flex' : 'none';
    });
});

// Add Item To Cart
function addToCart(product) {
    const existing = cart.find(item => item.id === product.id);
    if (existing) {
        existing.qty += 1;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            cogs: product.cogs || 0,
            qty: 1,
            modifiers: '',
            notes: ''
        });
    }
    renderCart();
}

// Update Qty
function updateCartQty(productId, delta) {
    const item = cart.find(i => i.id === productId);
    if (!item) return;

    item.qty += delta;
    if (item.qty <= 0) {
        cart = cart.filter(i => i.id !== productId);
    }
    renderCart();
}

// Update Item Note / Modifier
function updateItemNote(productId, text) {
    const item = cart.find(i => i.id === productId);
    if (item) item.notes = text;
}

// Clear Cart
function clearCart() {
    if (cart.length === 0) return;
    if (confirm('Kosongkan semua pesanan di keranjang?')) {
        cart = [];
        renderCart();
    }
}

// Toggle Table Input
function toggleTableInput(show) {
    const container = document.getElementById('tableInputContainer');
    if (show) {
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
        document.getElementById('posTableNo').value = '';
    }
}

// Apply Member Loyalty Discount
function applyCustomerDiscount() {
    const select = document.getElementById('posCustomerSelect');
    const opt = select.options[select.selectedIndex];
    const tier = opt.getAttribute('data-tier');
    
    if (tier === 'Gold') {
        discountPercent = 10; // 10% discount for Gold members
    } else if (tier === 'Silver') {
        discountPercent = 5; // 5% discount for Silver members
    } else {
        discountPercent = 0;
    }
    renderCart();
}

// Render Cart UI
function renderCart() {
    const container = document.getElementById('cartItemsList');
    const emptyMsg = document.getElementById('emptyCartMessage');
    const btnPay = document.getElementById('btnPay');

    if (cart.length === 0) {
        container.innerHTML = '';
        container.appendChild(emptyMsg);
        emptyMsg.style.display = 'block';
        btnPay.disabled = true;
        subtotalAmount = 0;
        discountAmount = 0;
        taxAmount = 0;
        grandTotal = 0;
        updateTotalsDisplay();
        return;
    }

    emptyMsg.style.display = 'none';
    let html = '';
    subtotalAmount = 0;

    cart.forEach(item => {
        const itemSubtotal = item.price * item.qty;
        subtotalAmount += itemSubtotal;

        html += `
            <div class="cart-item">
                <div class="item-title">
                    <span>${item.name}</span>
                    <span>${formatRupiahJS(itemSubtotal)}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="qty-control">
                        <button type="button" class="qty-btn" onclick="updateCartQty(${item.id}, -1)">-</button>
                        <span class="fw-bold px-2">${item.qty}</span>
                        <button type="button" class="qty-btn" onclick="updateCartQty(${item.id}, 1)">+</button>
                    </div>
                    <span class="text-muted small">${formatRupiahJS(item.price)} / cup</span>
                </div>
                <input type="text" class="form-control form-control-sm mt-2" 
                       placeholder="Catatan (e.g. Less Sugar, Panas)..." 
                       value="${item.notes || ''}" 
                       onchange="updateItemNote(${item.id}, this.value)">
            </div>
        `;
    });

    container.innerHTML = html;
    btnPay.disabled = false;

    // Financial calculations
    discountAmount = (subtotalAmount * discountPercent) / 100;
    const taxableAmount = subtotalAmount - discountAmount;
    taxAmount = taxableAmount * 0.10; // PB1 10%
    grandTotal = taxableAmount + taxAmount;

    updateTotalsDisplay();
}

function updateTotalsDisplay() {
    document.getElementById('lblSubtotal').innerText = formatRupiahJS(subtotalAmount);
    document.getElementById('lblDiscountBadge').innerText = discountPercent + '%';
    document.getElementById('lblDiscountAmount').innerText = '- ' + formatRupiahJS(discountAmount);
    document.getElementById('lblTax').innerText = formatRupiahJS(taxAmount);
    document.getElementById('lblGrandTotal').innerText = formatRupiahJS(grandTotal);
}

// Payment Modal Handlers
function openPaymentModal() {
    if (cart.length === 0) return;
    document.getElementById('modalTotalPay').innerText = formatRupiahJS(grandTotal);
    document.getElementById('inputCashGiven').value = grandTotal;
    calculateChange();
    
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}

function setPaymentMethod(method) {
    currentPaymentMethod = method;
}

function setCashAmount(amt) {
    const input = document.getElementById('inputCashGiven');
    if (amt === 'exact') {
        input.value = grandTotal;
    } else {
        input.value = amt;
    }
    calculateChange();
}

function calculateChange() {
    const cash = parseFloat(document.getElementById('inputCashGiven').value) || 0;
    const change = Math.max(0, cash - grandTotal);
    document.getElementById('lblCashChange').innerText = formatRupiahJS(change);
}

// Submit Order to Backend API
function submitPOSOrder() {
    const submitBtn = document.getElementById('btnSubmitOrder');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

    const orderType = document.querySelector('input[name="orderType"]:checked').value;
    const tableNo = document.getElementById('posTableNo').value;
    const customerSelect = document.getElementById('posCustomerSelect');
    const customerId = customerSelect.value;
    const customerName = customerSelect.options[customerSelect.selectedIndex].text.split('(')[0].trim();
    const cashGiven = parseFloat(document.getElementById('inputCashGiven').value) || grandTotal;
    const generalNotes = document.getElementById('orderGeneralNotes').value;

    const payload = {
        outlet_id: <?php echo (int)$outletId; ?>,
        order_type: orderType,
        table_no: tableNo,
        customer_id: customerId > 0 ? customerId : null,
        customer_name: customerName,
        items: cart,
        subtotal: subtotalAmount,
        discount: discountAmount,
        tax: taxAmount,
        total_amount: grandTotal,
        payment_method: currentPaymentMethod,
        cash_given: cashGiven,
        notes: generalNotes
    };

    fetch('pos_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-printer-fill me-1"></i> Simpan & Cetak Struk';

        if (data.success) {
            // Close Payment Modal
            bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
            
            // Render Thermal Receipt Preview
            document.getElementById('receiptModalBody').innerHTML = data.receipt_html;
            const rModal = new bootstrap.Modal(document.getElementById('receiptModal'));
            rModal.show();
        } else {
            showToast('Gagal memproses transaksi: ' + (data.error || 'Terjadi kesalahan'), 'error');
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-printer-fill me-1"></i> Simpan & Cetak Struk';
        showToast('Koneksi server gagal: ' + err, 'error');
    });
}

function resetPOS() {
    cart = [];
    document.getElementById('posTableNo').value = '';
    document.getElementById('orderGeneralNotes').value = '';
    renderCart();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
