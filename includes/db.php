<?php
/**
 * KopiKita ERP - Database Connection & Bootstrapper
 * Coffee Shop & Roastery Management System
 */

// Check if running in Vercel or read-only serverless environment
$isVercel = !empty($_ENV['VERCEL']) || !empty(getenv('VERCEL')) || !is_writable(dirname(__DIR__));

if (session_status() === PHP_SESSION_NONE) {
    if ($isVercel) {
        @session_save_path('/tmp');
    }
    session_start();
}

// Database configuration
if ($isVercel) {
    $dbPath = '/tmp/kopikita.db';
    $sourceDb = dirname(__DIR__) . '/kopikita.db';
    if (!file_exists($dbPath) && file_exists($sourceDb)) {
        copy($sourceDb, $dbPath);
    }
    define('DB_FILE', $dbPath);
} else {
    define('DB_FILE', dirname(__DIR__) . '/kopikita.db');
}

try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON;');
    if (!$isVercel) {
        $db->exec('PRAGMA journal_mode = WAL;');
    }
} catch (PDOException $e) {
    die("KopiKita ERP Database Error: " . $e->getMessage());
}

/**
 * Global database helper
 */
function getDb() {
    global $db;
    return $db;
}

/**
 * Execute query and return PDOStatement
 */
function dbQuery($sql, $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch a single row
 */
function dbFetchOne($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Fetch all matching rows
 */
function dbFetchAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Insert helper returning last inserted ID
 */
function dbInsert($table, $data) {
    global $db;
    $keys = array_keys($data);
    $fields = implode(', ', $keys);
    $placeholders = ':' . implode(', :', $keys);
    $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
    $stmt = $db->prepare($sql);
    $stmt->execute($data);
    return $db->lastInsertId();
}

/**
 * Update helper
 */
function dbUpdate($table, $data, $whereClause, $whereParams = []) {
    global $db;
    $setParts = [];
    $params = [];
    foreach ($data as $key => $val) {
        $setParts[] = "{$key} = :set_{$key}";
        $params["set_{$key}"] = $val;
    }
    $setStr = implode(', ', $setParts);
    $sql = "UPDATE {$table} SET {$setStr} WHERE {$whereClause}";
    $stmt = $db->prepare($sql);
    return $stmt->execute(array_merge($params, $whereParams));
}

// Auto-initialize schema & seed data if first time
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/seed.php';

try {
    initKopiKitaSchema($db);
    seedKopiKitaData($db);
} catch (Exception $e) {
    error_log("KopiKita Bootstrap Warning: " . $e->getMessage());
}
