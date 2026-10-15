<?php
/**
 * KopiKita ERP - Profile & Role Updater Endpoint
 * Allows dynamically changing Manager Name, Role, and switching to Customer / Staff mode
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_GET['ajax']) && $_GET['ajax'] == '1')
          || (isset($_POST['ajax']) && $_POST['ajax'] == '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? 'Store Manager');
    $activeRole = trim($_POST['active_role'] ?? 'manager');
    $redirect = $_POST['redirect'] ?? '';

    if (empty($name)) {
        $currentUser = getCurrentUser();
        $name = $currentUser['name'];
    }

    $updatedUser = setCurrentUser($name, $role, $activeRole);

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "Profil berhasil diperbarui: {$name} ({$role})",
            'user' => $updatedUser
        ]);
        exit;
    }

    setFlashMessage('success', "Profil dan peran berhasil diperbarui: {$name} ({$role})");

    if (!empty($redirect)) {
        header("Location: {$redirect}");
        exit;
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header("Location: {$referer}");
    exit;
}

// If GET switch role
if (isset($_GET['switch_role'])) {
    $role = $_GET['switch_role']; // 'customer' or 'manager'
    $currentUser = getCurrentUser();
    if ($role === 'customer') {
        $_SESSION['active_role'] = 'customer';
        header('Location: customer.php');
        exit;
    } else {
        $_SESSION['active_role'] = 'manager';
        header('Location: index.php');
        exit;
    }
}

header('Location: index.php');
exit;
