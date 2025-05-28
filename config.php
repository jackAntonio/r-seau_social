<?php
// SMTP params
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USER')) define('SMTP_USER', 'jackiningwe@gmail.com');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'uvcv hoim wbeu vdbp');
if (!defined('SMTP_FROM')) define('SMTP_FROM', 'jackiningwe@gmail.com');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'ANTONIO JACK ININGWE');

// Connexion à la base de données en local
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "auth_demo";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}
?>
