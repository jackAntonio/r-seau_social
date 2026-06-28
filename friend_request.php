<?php
require 'security.php';
session_start();
require 'config.php';
require_once 'send_mail.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// Vérification CSRF
if (!csrf_verify()) { header("Location: users.php?friend=error"); exit; }

function is_friend(mysqli $conn, int $user_id, int $other_id): bool {
    $stmt = $conn->prepare("SELECT id FROM friends WHERE ((from_user=? AND to_user=?) OR (from_user=? AND to_user=?)) AND status='accepted'");
    $stmt->bind_param("iiii", $user_id, $other_id, $other_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    $res = $stmt->num_rows > 0;
    $stmt->close();
    return $res;
}

$user_id = intval($_SESSION['user_id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['to_user'])) {
    $to_user = intval($_POST['to_user']);
    if ($to_user > 0 && $to_user !== $user_id && !is_friend($conn, $user_id, $to_user)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO friends (from_user, to_user, status) VALUES (?, ?, 'accepted')");
        $stmt->bind_param("ii", $user_id, $to_user);
        $stmt->execute();
        $stmt->close();

        // Email de notification
        $stmt = $conn->prepare("SELECT email, nom, prenom FROM users WHERE id=?");
        $stmt->bind_param("i", $to_user);
        $stmt->execute();
        $stmt->bind_result($to_email, $to_nom, $to_prenom);
        $stmt->fetch();
        $stmt->close();

        $stmt = $conn->prepare("SELECT nom, prenom FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($from_nom, $from_prenom);
        $stmt->fetch();
        $stmt->close();

        $subject = "Demande d'amitié sur le réseau social";
        $message = "Bonjour $to_nom $to_prenom,\n\n"
            . "$from_nom $from_prenom vient de vous ajouter en ami sur le réseau social !\n\n"
            . "Vous pouvez discuter directement depuis la plateforme.";
        sendValidationEmail($to_email, '', $subject, $message);

        header("Location: users.php?friend=ok");
        exit;
    }
}
header("Location: users.php?friend=error");
exit;
