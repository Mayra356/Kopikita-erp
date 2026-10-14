<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db.php';

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if (!$productId) {
    echo json_encode(['success' => false, 'error' => 'Product ID diperlukan']);
    exit;
}

$recipes = dbFetchAll("
    SELECT pr.*, rm.name as material_name, rm.cost_per_unit, rm.unit as material_unit
    FROM product_recipes pr
    JOIN raw_materials rm ON pr.raw_material_id = rm.id
    WHERE pr.product_id = ?
    ORDER BY pr.id ASC
", [$productId]);

echo json_encode([
    'success' => true,
    'recipes' => $recipes
]);
