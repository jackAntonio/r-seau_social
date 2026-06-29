<?php
// En production (Railway/Render), les variables d'env remplacent secrets.php
if (getenv('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST'));
    define('DB_PORT', getenv('DB_PORT') ?: '5432');
    define('DB_NAME', getenv('DB_NAME') ?: 'postgres');
    define('DB_USER', getenv('DB_USER'));
    define('DB_PASS', getenv('DB_PASS'));
    defined('SMTP_USER') || define('SMTP_USER', getenv('SMTP_USER') ?: '');
    defined('SMTP_PASS') || define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
} elseif (file_exists(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
} else {
    die('Fichier secrets.php manquant. Copier .env.example en secrets.php.');
}

// Connexion PostgreSQL via PDO (Supabase)
// Les constantes DB_* sont définies dans secrets.php
try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // Schéma dédié au réseau social, isolé de GabonConnect (public)
    $pdo->exec("SET search_path TO social, public");
} catch (PDOException $e) {
    error_log("Échec connexion DB : " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}
