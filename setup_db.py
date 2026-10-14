import sqlite3
import os
import datetime

db_path = os.path.join(os.path.dirname(__file__), 'kopikita.db')
conn = sqlite3.connect(db_path)
c = conn.cursor()

# Enable foreign keys
c.execute("PRAGMA foreign_keys = ON;")

# Schema
c.executescript("""
CREATE TABLE IF NOT EXISTS outlets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    address TEXT,
    phone TEXT,
    manager_name TEXT,
    is_active INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    icon TEXT DEFAULT 'bi-cup-hot',
    sort_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS raw_materials (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    category TEXT NOT NULL,
    unit TEXT NOT NULL,
    current_stock REAL DEFAULT 0,
    min_stock REAL DEFAULT 10,
    cost_per_unit REAL DEFAULT 0,
    outlet_id INTEGER DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (outlet_id) REFERENCES outlets(id)
);

CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    category_id INTEGER NOT NULL,
    description TEXT,
    price REAL NOT NULL,
    image_icon TEXT DEFAULT 'bi-cup-hot',
    is_active INTEGER DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE IF NOT EXISTS product_recipes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    raw_material_id INTEGER NOT NULL,
    quantity REAL NOT NULL,
    unit TEXT NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (raw_material_id) REFERENCES raw_materials(id)
);

CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    phone TEXT UNIQUE,
    email TEXT,
    points INTEGER DEFAULT 0,
    tier TEXT DEFAULT 'Bronze',
    total_spent REAL DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_no TEXT UNIQUE NOT NULL,
    outlet_id INTEGER DEFAULT 1,
    customer_id INTEGER,
    customer_name TEXT DEFAULT 'Walk-in Guest',
    cashier_name TEXT DEFAULT 'Barista',
    order_type TEXT DEFAULT 'Dine In',
    table_no TEXT,
    subtotal REAL DEFAULT 0,
    discount REAL DEFAULT 0,
    tax REAL DEFAULT 0,
    service_charge REAL DEFAULT 0,
    total_amount REAL DEFAULT 0,
    payment_method TEXT DEFAULT 'Cash',
    payment_status TEXT DEFAULT 'Paid',
    cash_given REAL DEFAULT 0,
    change_amount REAL DEFAULT 0,
    notes TEXT,
    created_at TEXT DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (outlet_id) REFERENCES outlets(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    product_name TEXT NOT NULL,
    price REAL NOT NULL,
    quantity INTEGER NOT NULL,
    subtotal REAL NOT NULL,
    modifiers TEXT,
    notes TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS stock_mutations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    raw_material_id INTEGER NOT NULL,
    outlet_id INTEGER DEFAULT 1,
    mutation_type TEXT NOT NULL,
    reference_no TEXT,
    qty_change REAL NOT NULL,
    qty_before REAL NOT NULL,
    qty_after REAL NOT NULL,
    notes TEXT,
    created_at TEXT DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (raw_material_id) REFERENCES raw_materials(id)
);

CREATE TABLE IF NOT EXISTS vendors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    category TEXT NOT NULL,
    contact_person TEXT,
    phone TEXT,
    email TEXT,
    address TEXT
);

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    po_number TEXT UNIQUE NOT NULL,
    vendor_id INTEGER NOT NULL,
    outlet_id INTEGER DEFAULT 1,
    po_date TEXT NOT NULL,
    expected_date TEXT,
    status TEXT DEFAULT 'Draft',
    total_amount REAL DEFAULT 0,
    notes TEXT,
    created_at TEXT DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (outlet_id) REFERENCES outlets(id)
);

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    po_id INTEGER NOT NULL,
    raw_material_id INTEGER NOT NULL,
    quantity REAL NOT NULL,
    unit_price REAL NOT NULL,
    subtotal REAL NOT NULL,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (raw_material_id) REFERENCES raw_materials(id)
);

CREATE TABLE IF NOT EXISTS production_batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_no TEXT UNIQUE NOT NULL,
    outlet_id INTEGER DEFAULT 1,
    batch_type TEXT NOT NULL,
    input_details TEXT,
    output_material_id INTEGER,
    output_qty REAL NOT NULL,
    waste_qty REAL DEFAULT 0,
    cost_total REAL DEFAULT 0,
    status TEXT DEFAULT 'Completed',
    created_at TEXT DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS gl_accounts (
    code INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    type TEXT NOT NULL,
    balance REAL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS journal_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_no TEXT NOT NULL,
    entry_date TEXT NOT NULL,
    description TEXT NOT NULL,
    reference_no TEXT,
    debit_account INTEGER NOT NULL,
    credit_account INTEGER NOT NULL,
    amount REAL NOT NULL,
    created_at TEXT DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (debit_account) REFERENCES gl_accounts(code),
    FOREIGN KEY (credit_account) REFERENCES gl_accounts(code)
);

CREATE TABLE IF NOT EXISTS cash_registers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    outlet_id INTEGER DEFAULT 1,
    cashier_name TEXT NOT NULL,
    opened_at TEXT NOT NULL,
    closed_at TEXT,
    opening_cash REAL DEFAULT 300000,
    cash_sales REAL DEFAULT 0,
    non_cash_sales REAL DEFAULT 0,
    cash_in REAL DEFAULT 0,
    cash_out REAL DEFAULT 0,
    expected_cash REAL DEFAULT 0,
    actual_cash REAL DEFAULT 0,
    variance REAL DEFAULT 0,
    status TEXT DEFAULT 'Open',
    notes TEXT
);

CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nik TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    role TEXT NOT NULL,
    phone TEXT,
    email TEXT,
    salary REAL DEFAULT 0,
    status TEXT DEFAULT 'Active',
    created_at TEXT DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS shifts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    outlet_id INTEGER DEFAULT 1,
    shift_date TEXT NOT NULL,
    shift_name TEXT NOT NULL,
    clock_in TEXT,
    clock_out TEXT,
    handover_notes TEXT,
    status TEXT DEFAULT 'Scheduled',
    FOREIGN KEY (employee_id) REFERENCES employees(id)
);

CREATE TABLE IF NOT EXISTS app_settings (
    key_name TEXT PRIMARY KEY,
    val_text TEXT
);
""")

# Seed data
# Settings
settings = [
    ('app_name', 'KopiKita Coffee & Roastery'),
    ('tagline', 'Integrated Coffee ERP & POS System'),
    ('currency', 'Rp'),
    ('tax_rate', '10'),
    ('service_rate', '0'),
    ('receipt_footer', "Terima kasih sudah menikmati sajian KopiKita!\nFollow IG: @kopikita.id | WiFi: KopiKita_Guest (pass: nikmatkopi)"),
    ('default_outlet_id', '1')
]
c.executemany("INSERT OR REPLACE INTO app_settings (key_name, val_text) VALUES (?, ?)", settings)

# Outlets
outlets = [
    ('KPK-JKT01', 'KopiKita Flagship Senopati', 'Jl. Senopati No. 42, Kebayoran Baru, Jakarta Selatan', '021-7201988', 'Kevin Wijaya'),
    ('KPK-TNG01', 'KopiKita Roastery & Cafe BSD', 'The Breeze BSD City Unit L-12, Tangerang Selatan', '021-5381120', 'Aris Munandar'),
    ('KPK-BDG01', 'KopiKita Dago View', 'Jl. Ir. H. Djuanda No. 108, Coblong, Bandung', '022-2503411', 'Maya Anggraini')
]
c.executemany("INSERT INTO outlets (code, name, address, phone, manager_name) VALUES (?, ?, ?, ?, ?)", outlets)

# Categories
cats = [
    ('Signature Coffee', 'signature-coffee', 'bi-stars', 1),
    ('Espresso Based', 'espresso-based', 'bi-cup-hot-fill', 2),
    ('Manual Brew & Filter', 'manual-brew', 'bi-droplet-half', 3),
    ('Non-Coffee & Tea', 'non-coffee', 'bi-cup-straw', 4),
    ('Pastry & Bakery', 'pastry-bakery', 'bi-cake2', 5),
    ('Light Bites & Food', 'light-bites', 'bi-egg-fried', 6),
    ('Beans & Merchandise', 'merchandise', 'bi-bag-heart', 7),
]
c.executemany("INSERT INTO categories (name, slug, icon, sort_order) VALUES (?, ?, ?, ?)", cats)

# Raw Materials
materials = [
    ('RM-BEANS-01', 'House Blend Espresso Beans (70:30)', 'Biji Kopi', 'gram', 18500, 5000, 220),
    ('RM-BEANS-02', 'Single Origin Arabica Gayo Natural', 'Biji Kopi', 'gram', 6200, 2000, 340),
    ('RM-BEANS-03', 'Single Origin Arabica Flores Bajawa', 'Biji Kopi', 'gram', 4800, 2000, 310),
    ('RM-MILK-01', 'Fresh Milk Pasteurisasi Greenfields', 'Susu & Dairy', 'ml', 42000, 15000, 22),
    ('RM-MILK-02', 'Oat Milk Barista Edition (Oatside)', 'Susu & Dairy', 'ml', 12000, 5000, 46),
    ('RM-SYR-01', 'Sirup Gula Aren Organik Liquid', 'Sirup & Flavour', 'ml', 8500, 3000, 38),
    ('RM-SYR-02', 'Sirup Vanilla Monin 700ml', 'Sirup & Flavour', 'ml', 2800, 1500, 180),
    ('RM-SYR-03', 'Sirup Salted Caramel Monin 700ml', 'Sirup & Flavour', 'ml', 1900, 1500, 180),
    ('RM-SYR-04', 'Sirup Pandan Wangi Premium', 'Sirup & Flavour', 'ml', 3200, 1500, 60),
    ('RM-POW-01', 'Pure Matcha Uji Ceremonial Powder', 'Bubuk & Teh', 'gram', 1400, 800, 450),
    ('RM-POW-02', 'Belgian Dark Chocolate Powder 70%', 'Bubuk & Teh', 'gram', 3500, 1500, 260),
    ('RM-POW-03', 'Earl Grey Loose Leaf Tea', 'Bubuk & Teh', 'gram', 2100, 1000, 280),
    ('RM-PKG-01', 'Cup Plastik Iced 16oz PET Custom Print', 'Kemasan & Cup', 'pcs', 850, 300, 950),
    ('RM-PKG-02', 'Paper Cup Hot 8oz Double Wall', 'Kemasan & Cup', 'pcs', 420, 200, 750),
    ('RM-PKG-03', 'Paper Cup Hot 12oz Double Wall', 'Kemasan & Cup', 'pcs', 380, 200, 850),
    ('RM-PKG-04', 'Lid Strawless Transparan 93mm', 'Kemasan & Cup', 'pcs', 920, 300, 350),
    ('RM-PKG-05', 'Sedotan Kertas Paper Straw Black', 'Kemasan & Cup', 'pcs', 1200, 400, 150),
    ('RM-PKG-06', 'Kertas Filter V60 Hario 02', 'Kemasan & Cup', 'pcs', 350, 100, 600),
    ('RM-FOD-01', 'Croissant Butter Frozen Dough', 'Bahan Makanan', 'pcs', 45, 20, 11500),
    ('RM-FOD-02', 'Pain au Chocolat Frozen Dough', 'Bahan Makanan', 'pcs', 35, 15, 13500),
    ('RM-FOD-03', 'French Fries Potato Shoestring 1kg', 'Bahan Makanan', 'pack', 14, 5, 36000),
]
c.executemany("INSERT INTO raw_materials (code, name, category, unit, current_stock, min_stock, cost_per_unit, outlet_id) VALUES (?, ?, ?, ?, ?, ?, ?, 1)", materials)

# Products
products = [
    ('PRD-SIG-01', 'Kopi Susu Gula Aren "KopiKita"', 1, 'Espresso blend, fresh milk pasteurisasi, dan gula aren organik khas KopiKita', 28000, 'bi-cup-straw'),
    ('PRD-SIG-02', 'Salted Caramel Macchiato', 1, 'Espresso creamy dengan saus salted caramel istimewa', 36000, 'bi-cup-hot'),
    ('PRD-SIG-03', 'Pandan Latte Iced', 1, 'Kombinasi harum sirup pandan wangi alami dengan susu dan double shot espresso', 32000, 'bi-cup-straw'),
    ('PRD-ESP-01', 'Americano / Long Black', 2, 'Double shot espresso dengan air murni panas / es segar', 25000, 'bi-cup-hot-fill'),
    ('PRD-ESP-02', 'Caffe Latte', 2, 'Espresso halus dengan velvety steamed milk dan latte art cantik', 32000, 'bi-cup-hot'),
    ('PRD-ESP-03', 'Cappuccino', 2, 'Keseimbangan sempurna espresso kaya rasa dengan busa susu tebal', 32000, 'bi-cup-hot'),
    ('PRD-FLT-01', 'V60 Pour Over - Arabica Gayo', 3, 'Tasting notes: Tropical fruit, black tea, bright acidity, sweet caramel finish', 35000, 'bi-droplet-half'),
    ('PRD-FLT-02', 'Japanese Iced Drip - Flores Bajawa', 3, 'Tasting notes: Chocolate cocoa, floral, citrus mandarin segar dingin', 38000, 'bi-droplet-half'),
    ('PRD-NCO-01', 'Artisan Matcha Latte', 4, 'Pure ceremonial Uji Matcha Jepang berpadu dengan creamy fresh milk', 35000, 'bi-cup-straw'),
    ('PRD-NCO-02', 'Belgian Dark Chocolate 70%', 4, 'Cokelat Belgia pekat dengan rasa kaya, creamy dan manis seimbang', 34000, 'bi-cup-hot'),
    ('PRD-NCO-03', 'Earl Grey Milk Tea', 4, 'Teh bergamot aromatik diseduh lambat dengan sentuhan susu manis', 29000, 'bi-cup-straw'),
    ('PRD-PAS-01', 'Golden Butter Croissant', 5, 'Croissant renyah berlapis-lapis mentega Prancis harum dipanggang hangat', 28000, 'bi-cake2'),
    ('PRD-PAS-02', 'Pain au Chocolat', 5, 'Pastry renyah dengan isian double dark chocolate baton leleh', 32000, 'bi-cake2'),
    ('PRD-FOD-01', 'Crispy Truffle Fries', 6, 'Kentang goreng gurih renyah dengan aroma minyak truffle dan parutan keju', 35000, 'bi-egg-fried'),
    ('PRD-MCH-01', 'House Blend Whole Beans 250g', 7, 'Biji kopi sangrai segar kemasan 250g valve pack siap seduh di rumah', 85000, 'bi-bag-heart'),
]
c.executemany("INSERT INTO products (code, name, category_id, description, price, image_icon, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)", products)

# Recipes
mat_map = {row[1]: row[0] for row in c.execute("SELECT id, code FROM raw_materials").fetchall()}
prd_map = {row[1]: row[0] for row in c.execute("SELECT id, code FROM products").fetchall()}

recipes = [
    ('PRD-SIG-01', 'RM-BEANS-01', 18, 'gram'),
    ('PRD-SIG-01', 'RM-MILK-01', 120, 'ml'),
    ('PRD-SIG-01', 'RM-SYR-01', 25, 'ml'),
    ('PRD-SIG-01', 'RM-PKG-01', 1, 'pcs'),
    ('PRD-SIG-01', 'RM-PKG-04', 1, 'pcs'),
    ('PRD-SIG-01', 'RM-PKG-05', 1, 'pcs'),
    ('PRD-SIG-02', 'RM-BEANS-01', 18, 'gram'),
    ('PRD-SIG-02', 'RM-MILK-01', 120, 'ml'),
    ('PRD-SIG-02', 'RM-SYR-03', 20, 'ml'),
    ('PRD-SIG-02', 'RM-PKG-01', 1, 'pcs'),
    ('PRD-SIG-02', 'RM-PKG-04', 1, 'pcs'),
    ('PRD-SIG-02', 'RM-PKG-05', 1, 'pcs'),
    ('PRD-SIG-03', 'RM-BEANS-01', 18, 'gram'),
    ('PRD-SIG-03', 'RM-MILK-01', 130, 'ml'),
    ('PRD-SIG-03', 'RM-SYR-04', 20, 'ml'),
    ('PRD-SIG-03', 'RM-PKG-01', 1, 'pcs'),
    ('PRD-SIG-03', 'RM-PKG-04', 1, 'pcs'),
    ('PRD-ESP-01', 'RM-BEANS-01', 18, 'gram'),
    ('PRD-ESP-01', 'RM-PKG-01', 1, 'pcs'),
    ('PRD-ESP-01', 'RM-PKG-04', 1, 'pcs'),
    ('PRD-ESP-02', 'RM-BEANS-01', 18, 'gram'),
    ('PRD-ESP-02', 'RM-MILK-01', 180, 'ml'),
    ('PRD-ESP-02', 'RM-PKG-03', 1, 'pcs'),
    ('PRD-ESP-03', 'RM-BEANS-01', 18, 'gram'),
    ('PRD-ESP-03', 'RM-MILK-01', 140, 'ml'),
    ('PRD-ESP-03', 'RM-PKG-02', 1, 'pcs'),
    ('PRD-FLT-01', 'RM-BEANS-02', 15, 'gram'),
    ('PRD-FLT-01', 'RM-PKG-06', 1, 'pcs'),
    ('PRD-FLT-02', 'RM-BEANS-03', 16, 'gram'),
    ('PRD-FLT-02', 'RM-PKG-06', 1, 'pcs'),
    ('PRD-FLT-02', 'RM-PKG-01', 1, 'pcs'),
    ('PRD-NCO-01', 'RM-POW-01', 12, 'gram'),
    ('PRD-NCO-01', 'RM-MILK-01', 160, 'ml'),
    ('PRD-NCO-01', 'RM-PKG-01', 1, 'pcs'),
    ('PRD-NCO-01', 'RM-PKG-04', 1, 'pcs'),
    ('PRD-NCO-02', 'RM-POW-02', 25, 'gram'),
    ('PRD-NCO-02', 'RM-MILK-01', 160, 'ml'),
    ('PRD-NCO-02', 'RM-PKG-01', 1, 'pcs'),
    ('PRD-PAS-01', 'RM-FOD-01', 1, 'pcs'),
    ('PRD-PAS-02', 'RM-FOD-02', 1, 'pcs'),
    ('PRD-FOD-01', 'RM-FOD-03', 0.2, 'pack'),
    ('PRD-MCH-01', 'RM-BEANS-01', 250, 'gram'),
]
for p_code, m_code, q, u in recipes:
    if p_code in prd_map and m_code in mat_map:
        c.execute("INSERT INTO product_recipes (product_id, raw_material_id, quantity, unit) VALUES (?, ?, ?, ?)",
                  (prd_map[p_code], mat_map[m_code], q, u))

# Customers
custs = [
    ('CUST-001', 'Dimas Pratama', '081234567890', 'dimas.pratama@gmail.com', 380, 'Gold', 1850000),
    ('CUST-002', 'Siti Nurhaliza', '081298765432', 'siti.nur@yahoo.com', 190, 'Silver', 920000),
    ('CUST-003', 'Budi Santoso', '081311223344', 'budi.santoso@outlook.com', 60, 'Bronze', 320000),
    ('CUST-004', 'Alina Zahra', '085788990011', 'alina.z@gmail.com', 450, 'Gold', 2100000),
]
c.executemany("INSERT INTO customers (code, name, phone, email, points, tier, total_spent) VALUES (?, ?, ?, ?, ?, ?, ?)", custs)

# Vendors
vnds = [
    ('VND-001', 'CV Tanamera Roastery Supply', 'Roaster & Green Beans', 'Hendro Susilo', '0811882233', 'sales@tanameraroastery.id', 'Kawasan Pergudangan Pluit, Jakarta Utara'),
    ('VND-002', 'PT Greenfields Fresh Dairy', 'Dairy & Milk', 'Rina Kartika', '021-8934521', 'order@greenfieldsdairy.co.id', 'Cikarang Industrial Estate, Bekasi'),
    ('VND-003', 'PT Toffin Indonesia (Syrup & Machines)', 'Syrup & Flavour', 'Tony Tan', '021-5830070', 'info@toffin.id', 'Jl. Panjang No. 8, Kebon Jeruk, Jakarta Barat'),
    ('VND-004', 'CV Eco Packaging Nusantara', 'Kemasan & Cup', 'Agus Priyono', '0819001122', 'custom@ecopack.co.id', 'Jl. Rungkut Industri IV No. 20, Surabaya'),
]
c.executemany("INSERT INTO vendors (code, name, category, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?, ?, ?)", vnds)

# GL Accounts
gls = [
    (1101, 'Kas Kasir (Petty Cash Outlet)', 'Asset', 3500000),
    (1102, 'Bank BCA Giro Operasional', 'Asset', 48200000),
    (1103, 'Piutang Settlement QRIS & EDC', 'Asset', 4500000),
    (1301, 'Persediaan Bahan Baku & Packaging', 'Asset', 18450000),
    (1501, 'Peralatan Mesin Espresso & Grinder', 'Asset', 145000000),
    (2101, 'Utang Usaha Supplier / Vendor', 'Liability', 8400000),
    (3101, 'Modal Usaha Pemilik', 'Equity', 200000000),
    (4101, 'Pendapatan Penjualan Minuman & Kopi', 'Revenue', 12500000),
    (4102, 'Pendapatan Penjualan Makanan & Pastry', 'Revenue', 4200000),
    (4103, 'Pendapatan Biji Kopi & Merchandise', 'Revenue', 1800000),
    (5101, 'HPP (Harga Pokok Penjualan) Bahan Baku', 'Expense', 4950000),
    (6101, 'Beban Gaji Karyawan & Barista', 'Expense', 8500000),
    (6102, 'Beban Listrik, Air & Gas Outlet', 'Expense', 2800000),
    (6103, 'Beban Sewa Gedung', 'Expense', 12000000),
    (6104, 'Beban Operasional & Kebersihan Toko', 'Expense', 1100000),
]
c.executemany("INSERT INTO gl_accounts (code, name, type, balance) VALUES (?, ?, ?, ?)", gls)

# Employees
emps = [
    ('KPK-EMP-001', 'Kevin Wijaya', 'Store Manager', '081211112222', 'kevin@kopikita.id', 8500000),
    ('KPK-EMP-002', 'Aris Munandar', 'Head Barista', '081322223333', 'aris@kopikita.id', 6500000),
    ('KPK-EMP-003', 'Maya Anggraini', 'Barista', '081433334444', 'maya@kopikita.id', 4800000),
    ('KPK-EMP-004', 'Fajar Ramadhan', 'Cashier', '081544445555', 'fajar@kopikita.id', 4500000),
    ('KPK-EMP-005', 'Dedi Kusnadi', 'Kitchen', '081655556666', 'dedi@kopikita.id', 4600000),
]
c.executemany("INSERT INTO employees (nik, name, role, phone, email, salary) VALUES (?, ?, ?, ?, ?, ?)", emps)

# Shifts & Cash Register
today_str = datetime.date.today().strftime('%Y-%m-%d')
c.execute(f"""
INSERT INTO shifts (employee_id, outlet_id, shift_date, shift_name, clock_in, status, handover_notes)
VALUES (2, 1, '{today_str}', 'Shift Pagi (07:00 - 15:30)', '06:55:12', 'On Duty', 'Kalibrasi grinder espresso dial-in 18g out 36g time 27s. Suhu mesin normal.');
""")
c.execute(f"""
INSERT INTO shifts (employee_id, outlet_id, shift_date, shift_name, clock_in, status, handover_notes)
VALUES (4, 1, '{today_str}', 'Shift Pagi (07:00 - 15:30)', '06:58:30', 'On Duty', 'Modal awal laci kasir Rp 300.000 sudah dihitung.');
""")
c.execute("""
INSERT INTO cash_registers (outlet_id, cashier_name, opened_at, opening_cash, cash_sales, non_cash_sales, expected_cash, actual_cash, status)
VALUES (1, 'Fajar Ramadhan', datetime('now', '-4 hours', 'localtime'), 300000, 485000, 920000, 785000, 0, 'Open');
""")

# Sample Orders
orders_data = [
    ('INV-202610-0001', 1, 1, 'Dimas Pratama', 'Fajar Ramadhan', 'Dine In', 'Meja 04', 84000, 0, 8400, 0, 92400, 'QRIS', 'Paid', 92400, 0, "datetime('now', '-3 hours', 'localtime')"),
    ('INV-202610-0002', 1, 2, 'Siti Nurhaliza', 'Fajar Ramadhan', 'Dine In', 'Meja 08', 64000, 0, 6400, 0, 70400, 'Cash', 'Paid', 100000, 29600, "datetime('now', '-2 hours', 'localtime')"),
    ('INV-202610-0003', 1, None, 'Walk-in Guest', 'Fajar Ramadhan', 'Take Away', None, 119000, 0, 11900, 0, 130900, 'QRIS', 'Paid', 130900, 0, "datetime('now', '-1 hour', 'localtime')"),
    ('INV-202610-0004', 1, 4, 'Alina Zahra', 'Fajar Ramadhan', 'Dine In', 'Bar Counter', 120000, 0, 12000, 0, 132000, 'Debit Card', 'Paid', 132000, 0, "datetime('now', '-25 minutes', 'localtime')"),
]
for inv, out_id, cust_id, cust_n, cash_n, otype, tbl, sub, disc, tax, svc, tot, meth, stat, c_giv, c_chg, dt_expr in orders_data:
    c.execute(f"""
    INSERT INTO orders (invoice_no, outlet_id, customer_id, customer_name, cashier_name, order_type, table_no, subtotal, discount, tax, service_charge, total_amount, payment_method, payment_status, cash_given, change_amount, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, {dt_expr})
    """, (inv, out_id, cust_id, cust_n, cash_n, otype, tbl, sub, disc, tax, svc, tot, meth, stat, c_giv, c_chg))
    order_id = c.lastrowid
    
    if inv == 'INV-202610-0001':
        c.execute("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal, modifiers) VALUES (?, ?, ?, ?, ?, ?, ?)",
                  (order_id, prd_map['PRD-SIG-01'], 'Kopi Susu Gula Aren "KopiKita"', 28000, 2, 56000, 'Less Sweet'))
        c.execute("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal, modifiers) VALUES (?, ?, ?, ?, ?, ?, ?)",
                  (order_id, prd_map['PRD-PAS-01'], 'Golden Butter Croissant', 28000, 1, 28000, 'Warm Toast'))
    elif inv == 'INV-202610-0002':
        c.execute("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal, modifiers) VALUES (?, ?, ?, ?, ?, ?, ?)",
                  (order_id, prd_map['PRD-ESP-02'], 'Caffe Latte', 32000, 1, 32000, 'Hot Oat Milk'))
        c.execute("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal, modifiers) VALUES (?, ?, ?, ?, ?, ?, ?)",
                  (order_id, prd_map['PRD-PAS-02'], 'Pain au Chocolat', 32000, 1, 32000, 'Warm'))
    elif inv == 'INV-202610-0003':
        c.execute("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal, modifiers) VALUES (?, ?, ?, ?, ?, ?, ?)",
                  (order_id, prd_map['PRD-SIG-01'], 'Kopi Susu Gula Aren "KopiKita"', 28000, 3, 84000, 'Normal Sugar'))
        c.execute("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal, modifiers) VALUES (?, ?, ?, ?, ?, ?, ?)",
                  (order_id, prd_map['PRD-FOD-01'], 'Crispy Truffle Fries', 35000, 1, 35000, 'Extra Sauce'))
    elif inv == 'INV-202610-0004':
        c.execute("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal, modifiers) VALUES (?, ?, ?, ?, ?, ?, ?)",
                  (order_id, prd_map['PRD-FLT-01'], 'V60 Pour Over - Arabica Gayo', 35000, 1, 35000, 'Hot V60'))
        c.execute("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal, modifiers) VALUES (?, ?, ?, ?, ?, ?, ?)",
                  (order_id, prd_map['PRD-MCH-01'], 'House Blend Whole Beans 250g', 85000, 1, 85000, 'Whole Beans'))

# Sample POs
c.execute("""
INSERT INTO purchase_orders (po_number, vendor_id, outlet_id, po_date, expected_date, status, total_amount, notes)
VALUES ('PO-202610-001', 1, 1, date('now', '-3 days'), date('now', '-3 days'), 'Received', 4400000, 'Pengadaan Biji Kopi House Blend 20kg');
""")
po1_id = c.lastrowid
c.execute("INSERT INTO purchase_order_items (po_id, raw_material_id, quantity, unit_price, subtotal) VALUES (?, ?, 20000, 220, 4400000)", (po1_id, mat_map['RM-BEANS-01']))

c.execute("""
INSERT INTO purchase_orders (po_number, vendor_id, outlet_id, po_date, expected_date, status, total_amount, notes)
VALUES ('PO-202610-002', 2, 1, date('now'), date('now'), 'Approved', 1100000, 'Restock Fresh Milk 50 Liter');
""")
po2_id = c.lastrowid
c.execute("INSERT INTO purchase_order_items (po_id, raw_material_id, quantity, unit_price, subtotal) VALUES (?, ?, 50000, 22, 1100000)", (po2_id, mat_map['RM-MILK-01']))

# Stock mutations
c.execute(f"""
INSERT INTO stock_mutations (raw_material_id, outlet_id, mutation_type, reference_no, qty_change, qty_before, qty_after, notes)
VALUES ({mat_map['RM-BEANS-01']}, 1, 'PURCHASE_RECEIPT', 'PO-202610-001', 20000, 1000, 21000, 'Penerimaan PO 20kg House Blend');
""")
c.execute(f"""
INSERT INTO stock_mutations (raw_material_id, outlet_id, mutation_type, reference_no, qty_change, qty_before, qty_after, notes)
VALUES ({mat_map['RM-BEANS-01']}, 1, 'POS_SALE', 'INV-202610-0001', -36, 21000, 20964, 'Penjualan 2x Kopi Susu Gula Aren');
""")

# Journal Entries
c.execute(f"""
INSERT INTO journal_entries (entry_no, entry_date, description, reference_no, debit_account, credit_account, amount)
VALUES ('JRN-202610-001', '{today_str}', 'Penerimaan Kas & QRIS Penjualan Pagi', 'INV-BATCH-01', 1101, 4101, 425700);
""")
c.execute(f"""
INSERT INTO journal_entries (entry_no, entry_date, description, reference_no, debit_account, credit_account, amount)
VALUES ('JRN-202610-002', '{today_str}', 'Pengakuan HPP Bahan Baku Terpakai', 'INV-BATCH-01', 5101, 1301, 128500);
""")

conn.commit()
conn.close()
print("Database kopikita.db successfully created and seeded!")
