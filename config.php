<?php
// En production (Railway/Render), les variables d'env remplacent secrets.php
if (getenv('DB_HOST')) {
    define('DB_DRIVER', getenv('DB_DRIVER') ?: 'pgsql');
    define('DB_HOST',   getenv('DB_HOST'));
    define('DB_PORT',   getenv('DB_PORT') ?: (getenv('DB_DRIVER') === 'mysql' ? '3306' : '5432'));
    define('DB_NAME',   getenv('DB_NAME') ?: (getenv('DB_DRIVER') === 'mysql' ? 'reseau_social' : 'postgres'));
    define('DB_USER',   getenv('DB_USER'));
    define('DB_PASS',   getenv('DB_PASS'));
    defined('SMTP_USER') || define('SMTP_USER', getenv('SMTP_USER') ?: '');
    defined('SMTP_PASS') || define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
} elseif (file_exists(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
} else {
    die('Fichier secrets.php manquant. Copier secrets.example.php en secrets.php.');
}

// Détection automatique du pilote de base de données (mysql pour XAMPP, pgsql pour Supabase)
if (!defined('DB_DRIVER')) {
    if (defined('DB_PORT') && DB_PORT == 3306) {
        define('DB_DRIVER', 'mysql');
    } elseif (defined('DB_HOST') && (DB_HOST === 'localhost' || DB_HOST === '127.0.0.1') && (!defined('DB_PORT') || DB_PORT == 3306)) {
        define('DB_DRIVER', 'mysql');
    } else {
        define('DB_DRIVER', 'pgsql');
    }
}

try {
    if (DB_DRIVER === 'mysql') {
        $port = defined('DB_PORT') ? DB_PORT : 3306;
        $dsn  = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo  = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } else {
        $port = defined('DB_PORT') ? DB_PORT : 5432;
        $ssl  = (defined('DB_SSL') && !DB_SSL) ? '' : ';sslmode=require';
        $dsn  = "pgsql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . $ssl;
        $pdo  = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        try {
            $pdo->exec("SET search_path TO social, public");
        } catch (Exception $e) {}
    }
} catch (PDOException $e) {
    error_log("Échec connexion DB : " . $e->getMessage());
    die("<div style='font-family:sans-serif;max-width:600px;margin:50px auto;padding:24px;border:1px solid #f5c6cb;background:#f8d7da;color:#721c24;border-radius:10px;'>" .
        "<h3 style='margin-top:0;'>⚠️ Erreur de connexion à la base de données (" . htmlspecialchars(DB_DRIVER) . ")</h3>" .
        "<p>" . htmlspecialchars($e->getMessage()) . "</p>" .
        "<hr style='border:0;border-top:1px solid #f5c6cb;'>" .
        "<p style='font-size:0.9em;margin-bottom:0;'><b>Conseil XAMPP :</b> Vérifiez que le module <b>MySQL</b> est bien démarré (bouton vert dans le panneau XAMPP) et que la base de données <b>" . htmlspecialchars(DB_NAME) . "</b> a été créée dans phpMyAdmin.</p>" .
        "</div>");
}
