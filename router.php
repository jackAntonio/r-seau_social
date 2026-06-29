<?php
// Router pour le serveur PHP intégré : php -S localhost:8080 router.php
// Bloque l'accès HTTP aux fichiers sensibles

$uri  = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$file = __DIR__ . $path;

// Fichiers interdits en HTTP
$blocked = [
    '/secrets.php', '/secrets.example.php', '/composer.phar',
    '/composer.json', '/composer.lock', '/router.php',
    '/.gitignore', '/cron_cleanup.php',
];
foreach ($blocked as $b) {
    if ($path === $b) { http_response_code(403); echo '403 Forbidden'; exit; }
}

// Bloquer les logs
if (preg_match('/\.(log)$/i', $path)) { http_response_code(403); echo '403 Forbidden'; exit; }

// Laisser le serveur gérer les fichiers statiques existants
if (is_file($file) && !str_ends_with($path, '.php')) {
    return false;
}

// Routing normal
if (is_file($file)) {
    require $file;
} elseif (is_file(__DIR__ . '/index.php')) {
    require __DIR__ . '/index.php';
} else {
    http_response_code(404);
    echo '404 Not Found';
}
