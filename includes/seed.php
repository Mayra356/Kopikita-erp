<?php
/**
 * KopiKita ERP - Initial Seeder
 * Populates realistic coffee shop master data and transactions
 */

function seedKopiKitaData($db) {
    // Check if already seeded
    $count = $db->query("SELECT COUNT(*) FROM outlets")->fetchColumn();
    if ($count > 0) {
        return; // Already seeded
    }

    // 1. Settings
    $settings = [
        'app_name' => 'KopiKita Coffee & Roastery',
        'tagline' => 'Integrated Coffee ERP & POS System',
        'currency' => 'Rp',
        'tax_rate' => '10', // PB1 10%
        'service_rate' => '0',
        'receipt_footer' => "Terima kasih sudah menikmati sajian KopiKita!\nFollow IG: @kopikita.id | WiFi: KopiKita_Guest (pass: nikmatkopi)",
        'default_outlet_id' => '1',
    ];
    $stmtSet = $db->prepare("INSERT OR REPLACE INTO app_settings (key_name, val_text) VALUES (?, ?)");
    foreach ($settings as $k => $v) {
        $stmtSet->execute([$k, $v]);
    }

    // 2. Outlets
    $outlets = [
        ['code' => 'KPK-JKT01', 'name' => 'KopiKita Flagship Senopati', 'address' => 'Jl. Senopati No. 42, Kebayoran Baru, Jakarta Selatan', 'phone' => '021-7201988', 'manager_name' => 'Kevin Wijaya'],
        ['code' => 'KPK-TNG01', 'name' => 'KopiKita Roastery & Cafe BSD', 'address' => 'The Breeze BSD City Unit L-12, Tangerang Selatan', 'phone' => '021-5381120', 'manager_name' => 'Aris Munandar'],
        ['code' => 'KPK-BDG01', 'name' => 'KopiKita Dago View', 'address' => 'Jl. Ir. H. Djuanda No. 108, Coblong, Bandung', 'phone' => '022-2503411', 'manager_name' => 'Maya Anggraini']
    ];
    $stmtOut = $db->prepare("INSERT INTO outlets (code, name, address, phone, manager_name) VALUES (?, ?, ?, ?, ?)");
    foreach ($outlets as $o) {
        $stmtOut->execute([$o['code'], $o['name'], $o['address'], $o['phone'], $o['manager_name']]);
    }

    // 3. Categories
    $categories = [
        ['name' => 'Signature Coffee', 'slug' => 'signature-coffee', 'icon' => 'bi-stars', 'sort_order' => 1],
        ['name' => 'Espresso Based', 'slug' => 'espresso-based', 'icon' => 'bi-cup-hot-fill', 'sort_order' => 2],
        ['name' => 'Manual Brew & Filter', 'slug' => 'manual-brew', 'icon' => 'bi-droplet-half', 'sort_order' => 3],
        ['name' => 'Non-Coffee & Tea', 'slug' => 'non-coffee', 'icon' => 'bi-cup-straw', 'sort_order' => 4],
        ['name' => 'Pastry & Bakery', 'slug' => 'pastry-bakery', 'icon' => 'bi-cake2', 'sort_order' => 5],
        ['name' => 'Light Bites & Food', 'slug' => 'light-bites', 'icon' => 'bi-egg-fried', 'sort_order' => 6],
        ['name' => 'Beans & Merchandise', 'slug' => 'merchandise', 'icon' => 'bi-bag-heart', 'sort_order' => 7],
    ];
    $stmtCat = $db->prepare("INSERT INTO categories (name, slug, icon, sort_order) VALUES (?, ?, ?, ?)");
    foreach ($categories as $c) {
        $stmtCat->execute([$c['name'], $c['slug'], $c['icon'], $c['sort_order']]);
    }

    // 4. Raw Materials & Packaging
    $rawMaterials = [
        // Biji Kopi
        ['code' => 'RM-BEANS-01', 'name' => 'House Blend Espresso Beans (70:30)', 'category' => 'Biji Kopi', 'unit' => 'gram', 'current_stock' => 18500, 'min_stock' => 5000, 'cost_per_unit' => 220],
        ['code' => 'RM-BEANS-02', 'name' => 'Single Origin Arabica Gayo Natural', 'category' => 'Biji Kopi', 'unit' => 'gram', 'current_stock' => 6200, 'min_stock' => 2000, 'cost_per_unit' => 340],
        ['code' => 'RM-BEANS-03', 'name' => 'Single Origin Arabica Flores Bajawa', 'category' => 'Biji Kopi', 'unit' => 'gram', 'current_stock' => 4800, 'min_stock' => 2000, 'cost_per_unit' => 310],
        // Dairy & Susu
        ['code' => 'RM-MILK-01', 'name' => 'Fresh Milk Pasteurisasi Greenfields', 'category' => 'Susu & Dairy', 'unit' => 'ml', 'current_stock' => 42000, 'min_stock' => 15000, 'cost_per_unit' => 22],
        ['code' => 'RM-MILK-02', 'name' => 'Oat Milk Barista Edition (Oatside)', 'category' => 'Susu & Dairy', 'unit' => 'ml', 'current_stock' => 12000, 'min_stock' => 5000, 'cost_per_unit' => 46],
        // Sirup & Flavour
        ['code' => 'RM-SYR-01', 'name' => 'Sirup Gula Aren Organik Liquid', 'category' => 'Sirup & Flavour', 'unit' => 'ml', 'current_stock' => 8500, 'min_stock' => 3000, 'cost_per_unit' => 38],
        ['code' => 'RM-SYR-02', 'name' => 'Sirup Vanilla Monin 700ml', 'category' => 'Sirup & Flavour', 'unit' => 'ml', 'current_stock' => 2800, 'min_stock' => 1500, 'cost_per_unit' => 180],
        ['code' => 'RM-SYR-03', 'name' => 'Sirup Salted Caramel Monin 700ml', 'category' => 'Sirup & Flavour', 'unit' => 'ml', 'current_stock' => 1900, 'min_stock' => 1500, 'cost_per_unit' => 180],
        ['code' => 'RM-SYR-04', 'name' => 'Sirup Pandan Wangi Premium', 'category' => 'Sirup & Flavour', 'unit' => 'ml', 'current_stock' => 3200, 'min_stock' => 1500, 'cost_per_unit' => 60],
        // Bubuk & Teh
        ['code' => 'RM-POW-01', 'name' => 'Pure Matcha Uji Ceremonial Powder', 'category' => 'Bubuk & Teh', 'unit' => 'gram', 'current_stock' => 1400, 'min_stock' => 800, 'cost_per_unit' => 450],
        ['code' => 'RM-POW-02', 'name' => 'Belgian Dark Chocolate Powder 70%', 'category' => 'Bubuk & Teh', 'unit' => 'gram', 'current_stock' => 3500, 'min_stock' => 1500, 'cost_per_unit' => 260],
        ['code' => 'RM-POW-03', 'name' => 'Earl Grey Loose Leaf Tea', 'category' => 'Bubuk & Teh', 'unit' => 'gram', 'current_stock' => 2100, 'min_stock' => 1000, 'cost_per_unit' => 280],
        // Kemasan & Packaging
        ['code' => 'RM-PKG-01', 'name' => 'Cup Plastik Iced 16oz PET Custom Print', 'category' => 'Kemasan & Cup', 'unit' => 'pcs', 'current_stock' => 850, 'min_stock' => 300, 'cost_per_unit' => 950],
        ['code' => 'RM-PKG-02', 'name' => 'Paper Cup Hot 8oz Double Wall', 'category' => 'Kemasan & Cup', 'unit' => 'pcs', 'current_stock' => 420, 'min_stock' => 200, 'cost_per_unit' => 750],
        ['code' => 'RM-PKG-03', 'name' => 'Paper Cup Hot 12oz Double Wall', 'category' => 'Kemasan & Cup', 'unit' => 'pcs', 'current_stock' => 380, 'min_stock' => 200, 'cost_per_unit' => 850],
        ['code' => 'RM-PKG-04', 'name' => 'Lid Strawless Transparan 93mm', 'category' => 'Kemasan & Cup', 'unit' => 'pcs', 'current_stock' => 920, 'min_stock' => 300, 'cost_per_unit' => 350],
        ['code' => 'RM-PKG-05', 'name' => 'Sedotan Kertas Paper Straw Black', 'category' => 'Kemasan & Cup', 'unit' => 'pcs', 'current_stock' => 1200, 'min_stock' => 400, 'cost_per_unit' => 150],
        ['code' => 'RM-PKG-06', 'name' => 'Kertas Filter V60 Hario 02', 'category' => 'Kemasan & Cup', 'unit' => 'pcs', 'current_stock' => 350, 'min_stock' => 100, 'cost_per_unit' => 600],
        // Pastry & Kitchen
        ['code' => 'RM-FOD-01', 'name' => 'Croissant Butter Frozen Dough', 'category' => 'Bahan Makanan', 'unit' => 'pcs', 'current_stock' => 45, 'min_stock' => 20, 'cost_per_unit' => 11500],
        ['code' => 'RM-FOD-02', 'name' => 'Pain au Chocolat Frozen Dough', 'category' => 'Bahan Makanan', 'unit' => 'pcs', 'current_stock' => 35, 'min_stock' => 15, 'cost_per_unit' => 13500],
        ['code' => 'RM-FOD-03', 'name' => 'French Fries Potato Shoestring 1kg', 'category' => 'Bahan Makanan', 'unit' => 'pack', 'current_stock' => 14, 'min_stock' => 5, 'cost_per_unit' => 36000],
    ];
    $stmtMat = $db->prepare("INSERT INTO raw_materials (code, name, category, unit, current_stock, min_stock, cost_per_unit, outlet_id) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    foreach ($rawMaterials as $m) {
        $stmtMat->execute([$m['code'], $m['name'], $m['category'], $m['unit'], $m['current_stock'], $m['min_stock'], $m['cost_per_unit']]);
    }

    // 5. Products (Menu)
    $products = [
        // Signature Coffee
        ['code' => 'PRD-SIG-01', 'name' => 'Kopi Susu Gula Aren "KopiKita"', 'category_id' => 1, 'description' => 'Espresso blend, fresh milk pasteurisasi, dan gula aren organik khas KopiKita', 'price' => 28000, 'image_icon' => 'bi-cup-straw'],
        ['code' => 'PRD-SIG-02', 'name' => 'Salted Caramel Macchiato', 'category_id' => 1, 'description' => 'Espresso creamy dengan saus salted caramel istimewa', 'price' => 36000, 'image_icon' => 'bi-cup-hot'],
        ['code' => 'PRD-SIG-03', 'name' => 'Pandan Latte Iced', 'category_id' => 1, 'description' => 'Kombinasi harum sirup pandan wangi alami dengan susu dan double shot espresso', 'price' => 32000, 'image_icon' => 'bi-cup-straw'],
        // Espresso Based
        ['code' => 'PRD-ESP-01', 'name' => 'Americano / Long Black', 'category_id' => 2, 'description' => 'Double shot espresso dengan air murni panas / es segar', 'price' => 25000, 'image_icon' => 'bi-cup-hot-fill'],
        ['code' => 'PRD-ESP-02', 'name' => 'Caffe Latte', 'category_id' => 2, 'description' => 'Espresso halus dengan velvety steamed milk dan latte art cantik', 'price' => 32000, 'image_icon' => 'bi-cup-hot'],
        ['code' => 'PRD-ESP-03', 'name' => 'Cappuccino', 'category_id' => 2, 'description' => 'Keseimbangan sempurna espresso kaya rasa dengan busa susu tebal', 'price' => 32000, 'image_icon' => 'bi-cup-hot'],
        // Manual Brew
        ['code' => 'PRD-FLT-01', 'name' => 'V60 Pour Over - Arabica Gayo', 'category_id' => 3, 'description' => 'Tasting notes: Tropical fruit, black tea, bright acidity, sweet caramel finish', 'price' => 35000, 'image_icon' => 'bi-droplet-half'],
        ['code' => 'PRD-FLT-02', 'name' => 'Japanese Iced Drip - Flores Bajawa', 'category_id' => 3, 'description' => 'Tasting notes: Chocolate cocoa, floral, citrus mandarin segar dingin', 'price' => 38000, 'image_icon' => 'bi-droplet-half'],
        // Non-Coffee
        ['code' => 'PRD-NCO-01', 'name' => 'Artisan Matcha Latte', 'category_id' => 4, 'description' => 'Pure ceremonial Uji Matcha Jepang berpadu dengan creamy fresh milk', 'price' => 35000, 'image_icon' => 'bi-cup-straw'],
        ['code' => 'PRD-NCO-02', 'name' => 'Belgian Dark Chocolate 70%', 'category_id' => 4, 'description' => 'Cokelat Belgia pekat dengan rasa kaya, creamy dan manis seimbang', 'price' => 34000, 'image_icon' => 'bi-cup-hot'],
        ['code' => 'PRD-NCO-03', 'name' => 'Earl Grey Milk Tea', 'category_id' => 4, 'description' => 'Teh bergamot aromatik diseduh lambat dengan sentuhan susu manis', 'price' => 29000, 'image_icon' => 'bi-cup-straw'],
        // Pastry & Food
        ['code' => 'PRD-PAS-01', 'name' => 'Golden Butter Croissant', 'category_id' => 5, 'description' => 'Croissant renyah berlapis-lapis mentega Prancis harum dipanggang hangat', 'price' => 28000, 'image_icon' => 'bi-cake2'],
        ['code' => 'PRD-PAS-02', 'name' => 'Pain au Chocolat', 'category_id' => 5, 'description' => 'Pastry renyah dengan isian double dark chocolate baton leleh', 'price' => 32000, 'image_icon' => 'bi-cake2'],
        ['code' => 'PRD-FOD-01', 'name' => 'Crispy Truffle Fries', 'category_id' => 6, 'description' => 'Kentang goreng gurih renyah dengan aroma minyak truffle dan parutan keju', 'price' => 35000, 'image_icon' => 'bi-egg-fried'],
        // Merchandise
        ['code' => 'PRD-MCH-01', 'name' => 'House Blend Whole Beans 250g', 'category_id' => 7, 'description' => 'Biji kopi sangrai segar kemasan 250g valve pack siap seduh di rumah', 'price' => 85000, 'image_icon' => 'bi-bag-heart'],
    ];
    $stmtPrd = $db->prepare("INSERT INTO products (code, name, category_id, description, price, image_icon, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
    foreach ($products as $p) {
        $stmtPrd->execute([$p['code'], $p['name'], $p['category_id'], $p['description'], $p['price'], $p['image_icon']]);
    }

    // 6. BOM Recipes (Link product to raw materials)
    // Map material codes to IDs
    $matMap = [];
    $rows = $db->query("SELECT id, code FROM raw_materials")->fetchAll();
    foreach ($rows as $r) {
        $matMap[$r['code']] = $r['id'];
    }
    // Map product codes to IDs
    $prdMap = [];
    $rows = $db->query("SELECT id, code FROM products")->fetchAll();
    foreach ($rows as $r) {
        $prdMap[$r['code']] = $r['id'];
    }

    $recipes = [
        // Kopi Susu Gula Aren "KopiKita"
        ['prd' => 'PRD-SIG-01', 'mat' => 'RM-BEANS-01', 'qty' => 18, 'unit' => 'gram'],
        ['prd' => 'PRD-SIG-01', 'mat' => 'RM-MILK-01',  'qty' => 120, 'unit' => 'ml'],
        ['prd' => 'PRD-SIG-01', 'mat' => 'RM-SYR-01',   'qty' => 25, 'unit' => 'ml'],
        ['prd' => 'PRD-SIG-01', 'mat' => 'RM-PKG-01',   'qty' => 1, 'unit' => 'pcs'],
        ['prd' => 'PRD-SIG-01', 'mat' => 'RM-PKG-04',   'qty' => 1, 'unit' => 'pcs'],
        ['prd' => 'PRD-SIG-01', 'mat' => 'RM-PKG-05',   'qty' => 1, 'unit' => 'pcs'],

        // Salted Caramel Macchiato
        ['prd' => 'PRD-SIG-02', 'mat' => 'RM-BEANS-01', 'qty' => 18, 'unit' => 'gram'],
        ['prd' => 'PRD-SIG-02', 'mat' => 'RM-MILK-01',  'qty' => 120, 'unit' => 'ml'],
        ['prd' => 'PRD-SIG-02', 'mat' => 'RM-SYR-03',   'qty' => 20, 'unit' => 'ml'],
        ['prd' => 'PRD-SIG-02', 'mat' => 'RM-PKG-01',   'qty' => 1, 'unit' => 'pcs'],
        ['prd' => 'PRD-SIG-02', 'mat' => 'RM-PKG-04',   'qty' => 1, 'unit' => 'pcs'],
        ['prd' => 'PRD-SIG-02', 'mat' => 'RM-PKG-05',   'qty' => 1, 'unit' => 'pcs'],

        // Pandan Latte Iced
        ['prd' => 'PRD-SIG-03', 'mat' => 'RM-BEANS-01', 'qty' => 18, 'unit' => 'gram'],
        ['prd' => 'PRD-SIG-03', 'mat' => 'RM-MILK-01',  'qty' => 130, 'unit' => 'ml'],
        ['prd' => 'PRD-SIG-03', 'mat' => 'RM-SYR-04',   'qty' => 20, 'unit' => 'ml'],
        ['prd' => 'PRD-SIG-03', 'mat' => 'RM-PKG-01',   'qty' => 1, 'unit' => 'pcs'],
        ['prd' => 'PRD-SIG-03', 'mat' => 'RM-PKG-04',   'qty' => 1, 'unit' => 'pcs'],

        // Americano
        ['prd' => 'PRD-ESP-01', 'mat' => 'RM-BEANS-01', 'qty' => 18, 'unit' => 'gram'],
        ['prd' => 'PRD-ESP-01', 'mat' => 'RM-PKG-01',   'qty' => 1, 'unit' => 'pcs'],
        ['prd' => 'PRD-ESP-01', 'mat' => 'RM-PKG-04',   'qty' => 1, 'unit' => 'pcs'],

        // Caffe Latte Hot
        ['prd' => 'PRD-ESP-02', 'mat' => 'RM-BEANS-01', 'qty' => 18, 'unit' => 'gram'],
        ['prd' => 'PRD-ESP-02', 'mat' => 'RM-MILK-01',  'qty' => 180, 'unit' => 'ml'],
        ['prd' => 'PRD-ESP-02', 'mat' => 'RM-PKG-03',   'qty' => 1, 'unit' => 'pcs'],

        // Cappuccino
        ['prd' => 'PRD-ESP-03', 'mat' => 'RM-BEANS-01', 'qty' => 18, 'unit' => 'gram'],
        ['prd' => 'PRD-ESP-03', 'mat' => 'RM-MILK-01',  'qty' => 140, 'unit' => 'ml'],
        ['prd' => 'PRD-ESP-03', 'mat' => 'RM-PKG-02',   'qty' => 1, 'unit' => 'pcs'],

        // V60 Gayo
        ['prd' => 'PRD-FLT-01', 'mat' => 'RM-BEANS-02', 'qty' => 15, 'unit' => 'gram'],
        ['prd' => 'PRD-FLT-01', 'mat' => 'RM-PKG-06',   'qty' => 1, 'unit' => 'pcs'],

        // Japanese Flores
        ['prd' => 'PRD-FLT-02', 'mat' => 'RM-BEANS-03', 'qty' => 16, 'unit' => 'gram'],
        ['prd' => 'PRD-FLT-02', 'mat' => 'RM-PKG-06',   'qty' => 1, 'unit' => 'pcs'],
        ['prd' => 'PRD-FLT-02', 'mat' => 'RM-PKG-01',   'qty' => 1, 'unit' => 'pcs'],

        // Matcha Latte
        ['prd' => 'PRD-NCO-01', 'mat' => 'RM-POW-01',   'qty' => 12, 'unit' => 'gram'],
        ['prd' => 'PRD-NCO-01', 'mat' => 'RM-MILK-01',  'qty' => 160, 'unit' => 'ml'],
        ['prd' => 'PRD-NCO-01', 'mat' => 'RM-PKG-01',   'qty' => 1, 'unit' => 'pcs'],
        ['prd' => 'PRD-NCO-01', 'mat' => 'RM-PKG-04',   'qty' => 1, 'unit' => 'pcs'],

        // Dark Chocolate
        ['prd' => 'PRD-NCO-02', 'mat' => 'RM-POW-02',   'qty' => 25, 'unit' => 'gram'],
        ['prd' => 'PRD-NCO-02', 'mat' => 'RM-MILK-01',  'qty' => 160, 'unit' => 'ml'],
        ['prd' => 'PRD-NCO-02', 'mat' => 'RM-PKG-01',   'qty' => 1, 'unit' => 'pcs'],

        // Croissant
        ['prd' => 'PRD-PAS-01', 'mat' => 'RM-FOD-01',   'qty' => 1, 'unit' => 'pcs'],

        // Pain au Chocolat
        ['prd' => 'PRD-PAS-02', 'mat' => 'RM-FOD-02',   'qty' => 1, 'unit' => 'pcs'],

        // Truffle Fries
        ['prd' => 'PRD-FOD-01', 'mat' => 'RM-FOD-03',   'qty' => 0.2, 'unit' => 'pack'],

        // Whole Beans 250g
        ['prd' => 'PRD-MCH-01', 'mat' => 'RM-BEANS-01', 'qty' => 250, 'unit' => 'gram'],
    ];

    $stmtRec = $db->prepare("INSERT INTO product_recipes (product_id, raw_material_id, quantity, unit) VALUES (?, ?, ?, ?)");
    foreach ($recipes as $rec) {
        if (isset($prdMap[$rec['prd']]) && isset($matMap[$rec['mat']])) {
            $stmtRec->execute([$prdMap[$rec['prd']], $matMap[$rec['mat']], $rec['qty'], $rec['unit']]);
        }
    }

    // 7. Customers
    $customers = [
        ['code' => 'CUST-001', 'name' => 'Dimas Pratama', 'phone' => '081234567890', 'email' => 'dimas.pratama@gmail.com', 'points' => 380, 'tier' => 'Gold', 'total_spent' => 1850000],
        ['code' => 'CUST-002', 'name' => 'Siti Nurhaliza', 'phone' => '081298765432', 'email' => 'siti.nur@yahoo.com', 'points' => 190, 'tier' => 'Silver', 'total_spent' => 920000],
        ['code' => 'CUST-003', 'name' => 'Budi Santoso', 'phone' => '081311223344', 'email' => 'budi.santoso@outlook.com', 'points' => 60, 'tier' => 'Bronze', 'total_spent' => 320000],
        ['code' => 'CUST-004', 'name' => 'Alina Zahra', 'phone' => '085788990011', 'email' => 'alina.z@gmail.com', 'points' => 450, 'tier' => 'Gold', 'total_spent' => 2100000],
    ];
    $stmtCst = $db->prepare("INSERT INTO customers (code, name, phone, email, points, tier, total_spent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($customers as $c) {
        $stmtCst->execute([$c['code'], $c['name'], $c['phone'], $c['email'], $c['points'], $c['tier'], $c['total_spent']]);
    }

    // 8. Vendors
    $vendors = [
        ['code' => 'VND-001', 'name' => 'CV Tanamera Roastery Supply', 'category' => 'Roaster & Green Beans', 'contact_person' => 'Hendro Susilo', 'phone' => '0811882233', 'email' => 'sales@tanameraroastery.id', 'address' => 'Kawasan Pergudangan Pluit, Jakarta Utara'],
        ['code' => 'VND-002', 'name' => 'PT Greenfields Fresh Dairy', 'category' => 'Dairy & Milk', 'contact_person' => 'Rina Kartika', 'phone' => '021-8934521', 'email' => 'order@greenfieldsdairy.co.id', 'address' => 'Cikarang Industrial Estate, Bekasi'],
        ['code' => 'VND-003', 'name' => 'PT Toffin Indonesia (Syrup & Machines)', 'category' => 'Syrup & Flavour', 'contact_person' => 'Tony Tan', 'phone' => '021-5830070', 'email' => 'info@toffin.id', 'address' => 'Jl. Panjang No. 8, Kebon Jeruk, Jakarta Barat'],
        ['code' => 'VND-004', 'name' => 'CV Eco Packaging Nusantara', 'category' => 'Kemasan & Cup', 'contact_person' => 'Agus Priyono', 'phone' => '0819001122', 'email' => 'custom@ecopack.co.id', 'address' => 'Jl. Rungkut Industri IV No. 20, Surabaya'],
    ];
    $stmtVnd = $db->prepare("INSERT INTO vendors (code, name, category, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($vendors as $v) {
        $stmtVnd->execute([$v['code'], $v['name'], $v['category'], $v['contact_person'], $v['phone'], $v['email'], $v['address']]);
    }

    // 9. Chart of Accounts (COA)
    $glAccounts = [
        ['code' => 1101, 'name' => 'Kas Kasir (Petty Cash Outlet)', 'type' => 'Asset', 'balance' => 3500000],
        ['code' => 1102, 'name' => 'Bank BCA Giro Operasional', 'type' => 'Asset', 'balance' => 48200000],
        ['code' => 1103, 'name' => 'Piutang Settlement QRIS & EDC', 'type' => 'Asset', 'balance' => 4500000],
        ['code' => 1301, 'name' => 'Persediaan Bahan Baku & Packaging', 'type' => 'Asset', 'balance' => 18450000],
        ['code' => 1501, 'name' => 'Peralatan Mesin Espresso & Grinder', 'type' => 'Asset', 'balance' => 145000000],
        ['code' => 2101, 'name' => 'Utang Usaha Supplier / Vendor', 'type' => 'Liability', 'balance' => 8400000],
        ['code' => 3101, 'name' => 'Modal Usaha Pemilik', 'type' => 'Equity', 'balance' => 200000000],
        ['code' => 4101, 'name' => 'Pendapatan Penjualan Minuman & Kopi', 'type' => 'Revenue', 'balance' => 12500000],
        ['code' => 4102, 'name' => 'Pendapatan Penjualan Makanan & Pastry', 'type' => 'Revenue', 'balance' => 4200000],
        ['code' => 4103, 'name' => 'Pendapatan Biji Kopi & Merchandise', 'type' => 'Revenue', 'balance' => 1800000],
        ['code' => 5101, 'name' => 'HPP (Harga Pokok Penjualan) Bahan Baku', 'type' => 'Expense', 'balance' => 4950000],
        ['code' => 6101, 'name' => 'Beban Gaji Karyawan & Barista', 'type' => 'Expense', 'balance' => 8500000],
        ['code' => 6102, 'name' => 'Beban Listrik, Air & Gas Outlet', 'type' => 'Expense', 'balance' => 2800000],
        ['code' => 6103, 'name' => 'Beban Sewa Gedung', 'type' => 'Expense', 'balance' => 12000000],
        ['code' => 6104, 'name' => 'Beban Operasional & Kebersihan Toko', 'type' => 'Expense', 'balance' => 1100000],
    ];
    $stmtGL = $db->prepare("INSERT INTO gl_accounts (code, name, type, balance) VALUES (?, ?, ?, ?)");
    foreach ($glAccounts as $g) {
        $stmtGL->execute([$g['code'], $g['name'], $g['type'], $g['balance']]);
    }

    // 10. Employees
    $employees = [
        ['nik' => 'KPK-EMP-001', 'name' => 'Kevin Wijaya', 'role' => 'Store Manager', 'phone' => '081211112222', 'email' => 'kevin@kopikita.id', 'salary' => 8500000],
        ['nik' => 'KPK-EMP-002', 'name' => 'Aris Munandar', 'role' => 'Head Barista', 'phone' => '081322223333', 'email' => 'aris@kopikita.id', 'salary' => 6500000],
        ['nik' => 'KPK-EMP-003', 'name' => 'Maya Anggraini', 'role' => 'Barista', 'phone' => '081433334444', 'email' => 'maya@kopikita.id', 'salary' => 4800000],
        ['nik' => 'KPK-EMP-004', 'name' => 'Fajar Ramadhan', 'role' => 'Cashier', 'phone' => '081544445555', 'email' => 'fajar@kopikita.id', 'salary' => 4500000],
        ['nik' => 'KPK-EMP-005', 'name' => 'Dedi Kusnadi', 'role' => 'Kitchen', 'phone' => '081655556666', 'email' => 'dedi@kopikita.id', 'salary' => 4600000],
    ];
    $stmtEmp = $db->prepare("INSERT INTO employees (nik, name, role, phone, email, salary) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($employees as $e) {
        $stmtEmp->execute([$e['nik'], $e['name'], $e['role'], $e['phone'], $e['email'], $e['salary']]);
    }

    // 11. Today's Shift & Active Cash Register
    $today = date('Y-m-d');
    $db->exec("INSERT INTO shifts (employee_id, outlet_id, shift_date, shift_name, clock_in, status, handover_notes)
        VALUES (2, 1, '{$today}', 'Shift Pagi (07:00 - 15:30)', '06:55:12', 'On Duty', 'Kalibrasi grinder espresso dial-in 18g out 36g time 27s. Suhu mesin normal.');");
    $db->exec("INSERT INTO shifts (employee_id, outlet_id, shift_date, shift_name, clock_in, status, handover_notes)
        VALUES (4, 1, '{$today}', 'Shift Pagi (07:00 - 15:30)', '06:58:30', 'On Duty', 'Modal awal laci kasir Rp 300.000 sudah dihitung.');");

    $db->exec("INSERT INTO cash_registers (outlet_id, cashier_name, opened_at, opening_cash, cash_sales, non_cash_sales, expected_cash, actual_cash, status)
        VALUES (1, 'Fajar Ramadhan', datetime('now', '-4 hours', 'localtime'), 300000, 485000, 920000, 785000, 0, 'Open');");

    // 12. Seed Sample Purchase Orders
    $poDate = date('Y-m-d', strtotime('-3 days'));
    $db->exec("INSERT INTO purchase_orders (po_number, vendor_id, outlet_id, po_date, expected_date, status, total_amount, notes)
        VALUES ('PO-202610-001', 1, 1, '{$poDate}', '{$poDate}', 'Received', 4400000, 'Pengadaan Biji Kopi House Blend 20kg');");
    $poId = $db->lastInsertId();
    $db->exec("INSERT INTO purchase_order_items (po_id, raw_material_id, quantity, unit_price, subtotal)
        VALUES ({$poId}, {$matMap['RM-BEANS-01']}, 20000, 220, 4400000);");

    $poDate2 = date('Y-m-d');
    $db->exec("INSERT INTO purchase_orders (po_number, vendor_id, outlet_id, po_date, expected_date, status, total_amount, notes)
        VALUES ('PO-202610-002', 2, 1, '{$poDate2}', '{$poDate2}', 'Approved', 1100000, 'Restock Fresh Milk 50 Liter');");
    $poId2 = $db->lastInsertId();
    $db->exec("INSERT INTO purchase_order_items (po_id, raw_material_id, quantity, unit_price, subtotal)
        VALUES ({$poId2}, {$matMap['RM-MILK-01']}, 50000, 22, 1100000);");

    // 13. Seed Sample Completed Orders (POS Transactions)
    $sampleOrders = [
        [
            'inv' => 'INV-202610-0001',
            'cust' => 1,
            'cust_name' => 'Dimas Pratama',
            'type' => 'Dine In',
            'table' => 'Meja 04',
            'method' => 'QRIS',
            'items' => [
                ['prd' => 'PRD-SIG-01', 'qty' => 2, 'price' => 28000, 'sub' => 56000, 'mod' => 'Less Sweet'],
                ['prd' => 'PRD-PAS-01', 'qty' => 1, 'price' => 28000, 'sub' => 28000, 'mod' => 'Warm Toast']
            ],
            'subtotal' => 84000, 'tax' => 8400, 'total' => 92400,
            'time' => date('Y-m-d H:i:s', strtotime('-3 hours'))
        ],
        [
            'inv' => 'INV-202610-0002',
            'cust' => 2,
            'cust_name' => 'Siti Nurhaliza',
            'type' => 'Dine In',
            'table' => 'Meja 08',
            'method' => 'Cash',
            'items' => [
                ['prd' => 'PRD-ESP-02', 'qty' => 1, 'price' => 32000, 'sub' => 32000, 'mod' => 'Hot Oat Milk'],
                ['prd' => 'PRD-PAS-02', 'qty' => 1, 'price' => 32000, 'sub' => 32000, 'mod' => 'Warm']
            ],
            'subtotal' => 64000, 'tax' => 6400, 'total' => 70400,
            'time' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'inv' => 'INV-202610-0003',
            'cust' => null,
            'cust_name' => 'Walk-in Guest',
            'type' => 'Take Away',
            'table' => null,
            'method' => 'QRIS',
            'items' => [
                ['prd' => 'PRD-SIG-01', 'qty' => 3, 'price' => 28000, 'sub' => 84000, 'mod' => 'Normal Sugar'],
                ['prd' => 'PRD-FOD-01', 'qty' => 1, 'price' => 35000, 'sub' => 35000, 'mod' => 'Extra Sauce']
            ],
            'subtotal' => 119000, 'tax' => 11900, 'total' => 130900,
            'time' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ],
        [
            'inv' => 'INV-202610-0004',
            'cust' => 4,
            'cust_name' => 'Alina Zahra',
            'type' => 'Dine In',
            'table' => 'Bar Counter',
            'method' => 'Debit Card',
            'items' => [
                ['prd' => 'PRD-FLT-01', 'qty' => 1, 'price' => 35000, 'sub' => 35000, 'mod' => 'Hot V60'],
                ['prd' => 'PRD-MCH-01', 'qty' => 1, 'price' => 85000, 'sub' => 85000, 'mod' => 'Whole Beans']
            ],
            'subtotal' => 120000, 'tax' => 12000, 'total' => 132000,
            'time' => date('Y-m-d H:i:s', strtotime('-25 minutes'))
        ],
    ];

    $stmtOrd = $db->prepare("INSERT INTO orders (invoice_no, outlet_id, customer_id, customer_name, cashier_name, order_type, table_no, subtotal, discount, tax, service_charge, total_amount, payment_method, payment_status, cash_given, change_amount, created_at)
        VALUES (?, 1, ?, ?, 'Fajar Ramadhan', ?, ?, ?, 0, ?, 0, ?, ?, 'Paid', ?, ?, ?)");
    $stmtItm = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal, modifiers) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($sampleOrders as $so) {
        $cashGiven = $so['method'] === 'Cash' ? 100000 : $so['total'];
        $changeAmt = $so['method'] === 'Cash' ? (100000 - $so['total']) : 0;
        $stmtOrd->execute([
            $so['inv'], $so['cust'], $so['cust_name'], $so['type'], $so['table'],
            $so['subtotal'], $so['tax'], $so['total'], $so['method'],
            $cashGiven, $changeAmt, $so['time']
        ]);
        $orderId = $db->lastInsertId();

        foreach ($so['items'] as $item) {
            $pId = $prdMap[$item['prd']] ?? 1;
            $pName = '';
            foreach ($products as $px) {
                if ($px['code'] === $item['prd']) {
                    $pName = $px['name'];
                    break;
                }
            }
            $stmtItm->execute([$orderId, $pId, $pName, $item['price'], $item['qty'], $item['sub'], $item['mod']]);
        }
    }

    // 14. Sample Stock Mutations
    $db->exec("INSERT INTO stock_mutations (raw_material_id, outlet_id, mutation_type, reference_no, qty_change, qty_before, qty_after, notes)
        VALUES ({$matMap['RM-BEANS-01']}, 1, 'PURCHASE_RECEIPT', 'PO-202610-001', 20000, 1000, 21000, 'Penerimaan PO 20kg House Blend');");
    $db->exec("INSERT INTO stock_mutations (raw_material_id, outlet_id, mutation_type, reference_no, qty_change, qty_before, qty_after, notes)
        VALUES ({$matMap['RM-BEANS-01']}, 1, 'POS_SALE', 'INV-202610-0001', -36, 21000, 20964, 'Penjualan 2x Kopi Susu Gula Aren');");

    // 15. Sample Double-Entry Journal Entries
    $db->exec("INSERT INTO journal_entries (entry_no, entry_date, description, reference_no, debit_account, credit_account, amount)
        VALUES ('JRN-202610-001', '{$today}', 'Penerimaan Kas & QRIS Penjualan Pagi', 'INV-BATCH-01', 1101, 4101, 425700);");
    $db->exec("INSERT INTO journal_entries (entry_no, entry_date, description, reference_no, debit_account, credit_account, amount)
        VALUES ('JRN-202610-002', '{$today}', 'Pengakuan HPP Bahan Baku Terpakai', 'INV-BATCH-01', 5101, 1301, 128500);");
}
