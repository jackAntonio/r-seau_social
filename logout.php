<?php
require 'security.php';
session_start();

// Vérifier CSRF si soumis via formulaire POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    header("Location: accueil.php");
    exit;
}

// Détruire proprement la session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: login.php");
exit;
