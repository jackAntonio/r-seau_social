<?php
// Charger les credentials depuis un fichier gitignored
if (file_exists(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
} else {
    die('Fichier secrets.php manquant. Copier secrets.example.php en secrets.php.');
}

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "auth_demo";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    // Ne pas exposer les détails de connexion en production
    error_log("Échec connexion DB : " . $conn->connect_error);
    die("Erreur de connexion à la base de données.");
}

$conn->set_charset('utf8mb4');
