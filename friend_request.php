<?php
session_start();
require 'config.php';
require_once 'send_mail.php';

function is_friend($user_id, $other_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM friends WHERE ((from_user=? AND to_user=?) OR (from_user=? AND to_user=?)) AND status='accepted'");
    $stmt->bind_param("iiii", $user_id, $other_id, $other_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    $is_friend = $stmt->num_rows > 0;
    $stmt->close();
    return $is_friend;
}

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['to_user'])) {
    $to_user = intval($_POST['to_user']);
    if ($to_user != $user_id && !is_friend($user_id, $to_user)) {
        // Ajout direct comme ami
        $stmt = $conn->prepare("INSERT IGNORE INTO friends (from_user, to_user, status) VALUES (?, ?, 'accepted')");
        $stmt->bind_param("ii", $user_id, $to_user);
        $stmt->execute();
        $stmt->close();

        // Envoi email
        $stmt = $conn->prepare("SELECT email, nom, prenom FROM users WHERE id = ?");
        $stmt->bind_param("i", $to_user);
        $stmt->execute();
        $stmt->bind_result($to_email, $to_nom, $to_prenom);
        $stmt->fetch();
        $stmt->close();

        $from_stmt = $conn->prepare("SELECT nom, prenom FROM users WHERE id = ?");
        $from_stmt->bind_param("i", $user_id);
        $from_stmt->execute();
        $from_stmt->bind_result($from_nom, $from_prenom);
        $from_stmt->fetch();
        $from_stmt->close();

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
?>