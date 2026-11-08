<?php
// Copier ce fichier en secrets.php et adapter si nécessaire

// Pilote : 'mysql' pour XAMPP / 'pgsql' pour Supabase
define('DB_DRIVER', 'mysql');

// Configuration XAMPP locale
define('DB_HOST',   '127.0.0.1');
define('DB_PORT',   '3306');
define('DB_NAME',   'reseau_social');
define('DB_USER',   'root');
define('DB_PASS',   '');

define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'VOTRE_EMAIL@gmail.com');
define('SMTP_PASS',      'VOTRE_APP_PASSWORD');
define('SMTP_FROM',      'VOTRE_EMAIL@gmail.com');
define('SMTP_FROM_NAME', 'Forgab');
