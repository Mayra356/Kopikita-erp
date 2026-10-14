<?php
/**
 * KopiKita ERP - Vercel Serverless Function Entry Point & Router
 * Dispatches all dynamic incoming routes to the respective root PHP scripts
 */

// Define execution environment
if (!getenv('VERCEL') && empty($_ENV['VERCEL'])) {
    putenv('VERCEL=1');
    $_ENV['VERCEL'] = '1';
}

$rootDir = dirname(__DIR__);

// Extract request path
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = trim($requestUri, '/');

// Default route -> dashboard
if ($path === '' || $path === 'index.php' || $path === 'api' || $path === 'api/index.php') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    require $rootDir . '/index.php';
    exit;
}

// Target file resolution
$targetFile = $rootDir . '/' . $path;

// Auto-append .php if omitted (e.g. /pos -> /pos.php)
if (!file_exists($targetFile) && file_exists($targetFile . '.php')) {
    $targetFile .= '.php';
    $path .= '.php';
}

$realTarget = realpath($targetFile);

// If target exists, is within project root, and is a PHP script
if ($realTarget && str_starts_with($realTarget, $rootDir) && pathinfo($realTarget, PATHINFO_EXTENSION) === 'php') {
    $_SERVER['SCRIPT_NAME'] = '/' . basename($realTarget);
    $_SERVER['PHP_SELF'] = '/' . basename($realTarget);
    require $realTarget;
    exit;
}

// Static asset handler fallback
if ($realTarget && file_exists($realTarget) && !is_dir($realTarget)) {
    $ext = strtolower(pathinfo($realTarget, PATHINFO_EXTENSION));
    $mimes = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf'
    ];
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
        header('Cache-Control: public, max-age=86400');
        readfile($realTarget);
        exit;
    }
}

// 404 handler
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>404 - Halaman Tidak Ditemukan | KopiKita ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="text-center p-4">
        <h1 class="display-1 fw-bold text-muted">404</h1>
        <h3 class="fw-bold mb-3">Halaman Tidak Ditemukan</h3>
        <p class="text-muted mb-4">Halaman <code>/<?php echo htmlspecialchars($path); ?></code> tidak tersedia di KopiKita ERP.</p>
        <a href="/" class="btn btn-dark px-4 py-2">Kembali ke Dashboard</a>
    </div>
</body>
</html>
