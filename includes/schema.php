<?php
/**
 * KopiKita ERP - Database Schema Definition
 */

function initKopiKitaSchema($db) {
    // 1. Outlets / Branches
    $db->exec("CREATE TABLE IF NOT EXISTS outlets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        address TEXT,
        phone TEXT,
        manager_name TEXT,
        is_active INTEGER DEFAULT 1
    );");

    // 2. Categories
    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT UNIQUE NOT NULL,
        icon TEXT DEFAULT 'bi-cup-hot',
        sort_order INTEGER DEFAULT 0
    );");

    // 3. Raw Materials & Packaging (Inventory)
    $db->exec("CREATE TABLE IF NOT EXISTS raw_materials (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        category TEXT NOT NULL, -- 'Biji Kopi', 'Susu & Dairy', 'Sirup & Flavour', 'Bubuk & Teh', 'Kemasan & Cup', 'Bahan Makanan'
        unit TEXT NOT NULL, -- 'gram', 'ml', 'pcs', 'pack'
        current_stock REAL DEFAULT 0,
        min_stock REAL DEFAULT 10,
        cost_per_unit REAL DEFAULT 0, -- Moving average / Standard cost in IDR per unit
        outlet_id INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now', 'localtime')),
        FOREIGN KEY (outlet_id) REFERENCES outlets(id)
    );");

    // 4. Finished Menu Products
    $db->exec("CREATE TABLE IF NOT EXISTS products (
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
    );");

    // 5. Bill of Materials (BOM / Resep Produk)
    $db->exec("CREATE TABLE IF NOT EXISTS product_recipes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        raw_material_id INTEGER NOT NULL,
        quantity REAL NOT NULL, -- e.g. 18 (gram), 120 (ml), 1 (pcs cup)
        unit TEXT NOT NULL,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (raw_material_id) REFERENCES raw_materials(id)
    );");

    // 6. Customers (CRM & Loyalty)
    $db->exec("CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        phone TEXT UNIQUE,
        email TEXT,
        points INTEGER DEFAULT 0,
        tier TEXT DEFAULT 'Bronze', -- 'Bronze', 'Silver', 'Gold'
        total_spent REAL DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now', 'localtime'))
    );");

    // 7. Orders / Sales Orders (POS & Invoicing)
    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        invoice_no TEXT UNIQUE NOT NULL,
        outlet_id INTEGER DEFAULT 1,
        customer_id INTEGER,
        customer_name TEXT DEFAULT 'Walk-in Guest',
        cashier_name TEXT DEFAULT 'Barista',
        order_type TEXT DEFAULT 'Dine In', -- 'Dine In', 'Take Away', 'Delivery'
        table_no TEXT,
        subtotal REAL DEFAULT 0,
        discount REAL DEFAULT 0,
        tax REAL DEFAULT 0, -- PB1 / PPN
        service_charge REAL DEFAULT 0,
        total_amount REAL DEFAULT 0,
        payment_method TEXT DEFAULT 'Cash', -- 'Cash', 'QRIS', 'Debit Card', 'Transfer Bank'
        payment_status TEXT DEFAULT 'Paid', -- 'Paid', 'Pending', 'Cancelled'
        cash_given REAL DEFAULT 0,
        change_amount REAL DEFAULT 0,
        notes TEXT,
        created_at TEXT DEFAULT (datetime('now', 'localtime')),
        FOREIGN KEY (outlet_id) REFERENCES outlets(id),
        FOREIGN KEY (customer_id) REFERENCES customers(id)
    );");

    // 8. Order Items
    $db->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        product_name TEXT NOT NULL,
        price REAL NOT NULL,
        quantity INTEGER NOT NULL,
        subtotal REAL NOT NULL,
        modifiers TEXT, -- e.g. 'Less Sweet, Oat Milk'
        notes TEXT,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id)
    );");

    // 9. Stock Mutation History (Kartu Stok)
    $db->exec("CREATE TABLE IF NOT EXISTS stock_mutations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        raw_material_id INTEGER NOT NULL,
        outlet_id INTEGER DEFAULT 1,
        mutation_type TEXT NOT NULL, -- 'POS_SALE', 'PURCHASE_RECEIPT', 'STOCK_OPNAME', 'WASTE', 'PRODUCTION_IN', 'PRODUCTION_OUT'
        reference_no TEXT, -- Invoice No, PO No, etc.
        qty_change REAL NOT NULL, -- negative for deduction, positive for addition
        qty_before REAL NOT NULL,
        qty_after REAL NOT NULL,
        notes TEXT,
        created_at TEXT DEFAULT (datetime('now', 'localtime')),
        FOREIGN KEY (raw_material_id) REFERENCES raw_materials(id)
    );");

    // 10. Vendors / Suppliers
    $db->exec("CREATE TABLE IF NOT EXISTS vendors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        category TEXT NOT NULL, -- 'Roaster & Green Beans', 'Dairy & Milk', 'Packaging', 'Syrup & Flavour'
        contact_person TEXT,
        phone TEXT,
        email TEXT,
        address TEXT
    );");

    // 11. Purchase Orders (Procurement)
    $db->exec("CREATE TABLE IF NOT EXISTS purchase_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        po_number TEXT UNIQUE NOT NULL,
        vendor_id INTEGER NOT NULL,
        outlet_id INTEGER DEFAULT 1,
        po_date TEXT NOT NULL,
        expected_date TEXT,
        status TEXT DEFAULT 'Draft', -- 'Draft', 'Approved', 'Received', 'Cancelled'
        total_amount REAL DEFAULT 0,
        notes TEXT,
        created_at TEXT DEFAULT (datetime('now', 'localtime')),
        FOREIGN KEY (vendor_id) REFERENCES vendors(id),
        FOREIGN KEY (outlet_id) REFERENCES outlets(id)
    );");

    // 12. Purchase Order Items
    $db->exec("CREATE TABLE IF NOT EXISTS purchase_order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        po_id INTEGER NOT NULL,
        raw_material_id INTEGER NOT NULL,
        quantity REAL NOT NULL,
        unit_price REAL NOT NULL,
        subtotal REAL NOT NULL,
        FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (raw_material_id) REFERENCES raw_materials(id)
    );");

    // 13. Production Batches (Cold Brew, In-House Roasting, Simple Syrup)
    $db->exec("CREATE TABLE IF NOT EXISTS production_batches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        batch_no TEXT UNIQUE NOT NULL,
        outlet_id INTEGER DEFAULT 1,
        batch_type TEXT NOT NULL, -- 'Cold Brew 10L', 'Roasting Blend', 'Gula Aren Liquid Batch'
        input_details TEXT, -- JSON of raw materials consumed
        output_material_id INTEGER,
        output_qty REAL NOT NULL,
        waste_qty REAL DEFAULT 0,
        cost_total REAL DEFAULT 0,
        status TEXT DEFAULT 'Completed',
        created_at TEXT DEFAULT (datetime('now', 'localtime'))
    );");

    // 14. Chart of Accounts (COA / Buku Besar)
    $db->exec("CREATE TABLE IF NOT EXISTS gl_accounts (
        code INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        type TEXT NOT NULL, -- 'Asset', 'Liability', 'Equity', 'Revenue', 'Expense'
        balance REAL DEFAULT 0
    );");

    // 15. Journal Entries (Double-Entry Bookkeeping Ledger)
    $db->exec("CREATE TABLE IF NOT EXISTS journal_entries (
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
    );");

    // 16. Cash Registers (Buka / Tutup Kasir & Z-Report)
    $db->exec("CREATE TABLE IF NOT EXISTS cash_registers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        outlet_id INTEGER DEFAULT 1,
        cashier_name TEXT NOT NULL,
        opened_at TEXT NOT NULL,
        closed_at TEXT,
        opening_cash REAL DEFAULT 300000, -- Modal Kas Awal (float)
        cash_sales REAL DEFAULT 0,
        non_cash_sales REAL DEFAULT 0,
        cash_in REAL DEFAULT 0,
        cash_out REAL DEFAULT 0,
        expected_cash REAL DEFAULT 0,
        actual_cash REAL DEFAULT 0,
        variance REAL DEFAULT 0, -- Difference (actual - expected)
        status TEXT DEFAULT 'Open', -- 'Open', 'Closed'
        notes TEXT
    );");

    // 17. Employees & Staff Directory
    $db->exec("CREATE TABLE IF NOT EXISTS employees (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nik TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        role TEXT NOT NULL, -- 'Owner/Admin', 'Store Manager', 'Head Barista', 'Barista', 'Cashier', 'Kitchen'
        phone TEXT,
        email TEXT,
        salary REAL DEFAULT 0,
        status TEXT DEFAULT 'Active',
        created_at TEXT DEFAULT (datetime('now', 'localtime'))
    );");

    // 18. Shift Scheduling & Attendance
    $db->exec("CREATE TABLE IF NOT EXISTS shifts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        outlet_id INTEGER DEFAULT 1,
        shift_date TEXT NOT NULL,
        shift_name TEXT NOT NULL, -- 'Shift Pagi (07:00 - 15:30)', 'Shift Sore (15:00 - 23:00)'
        clock_in TEXT,
        clock_out TEXT,
        handover_notes TEXT,
        status TEXT DEFAULT 'Scheduled', -- 'Scheduled', 'On Duty', 'Completed', 'Absent'
        FOREIGN KEY (employee_id) REFERENCES employees(id)
    );");

    // 19. App Settings (Key-Value Config)
    $db->exec("CREATE TABLE IF NOT EXISTS app_settings (
        key_name TEXT PRIMARY KEY,
        val_text TEXT
    );");

    // Indexes for fast querying
    $db->exec("CREATE INDEX IF NOT EXISTS idx_orders_outlet ON orders(outlet_id);");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_stock_mut_material ON stock_mutations(raw_material_id);");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_recipes_product ON product_recipes(product_id);");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_po_vendor ON purchase_orders(vendor_id);");
}
