<?php
require 'security.php';
session_start();
require 'config.php';
require 'audit.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (!csrf_verify()) { header("Location: users.php?err=csrf"); exit; }

$user_id = intval($_SESSION['user_id']);
$action  = $_POST['action'] ?? 'request';

if ($action === 'request' && isset($_POST['to_user'])) {
    $to_user = intval($_POST['to_user']);
    if ($to_user > 0 && $to_user !== $user_id) {
        $pdo->prepare(
            "INSERT INTO friends (from_user, to_user, status) VALUES (?,?,'pending')
             ON CONFLICT (from_user, to_user) DO NOTHING"
        )->execute([$user_id, $to_user]);

        // Notification au destinataire
        $from = $pdo->prepare("SELECT nom, prenom FROM users WHERE id=?");
        $from->execute([$user_id]);
        $fr = $from->fetch();
        $pdo->prepare("INSERT INTO notifications (user_id, content, link) VALUES (?,?,?)")
            ->execute([$to_user, "{$fr['nom']} {$fr['prenom']} vous a envoyé une demande d'ami", "users.php"]);

        audit_log($pdo, 'friend_request', $user_id, 'user', $to_user);
        header("Location: users.php?sent=1"); exit;
    }
}

if ($action === 'accept' && isset($_POST['from_user'])) {
    $from_user = intval($_POST['from_user']);
    $pdo->prepare(
        "UPDATE friends SET status='accepted' WHERE from_user=? AND to_user=? AND status='pending'"
    )->execute([$from_user, $user_id]);

    $from = $pdo->prepare("SELECT nom, prenom FROM users WHERE id=?");
    $from->execute([$user_id]);
    $fr = $from->fetch();
    $pdo->prepare("INSERT INTO notifications (user_id, content, link) VALUES (?,?,?)")
        ->execute([$from_user, "{$fr['nom']} {$fr['prenom']} a accepté votre demande d'ami", "chat.php?with={$user_id}"]);

    audit_log($pdo, 'friend_accept', $user_id, 'user', $from_user);
    header("Location: users.php?accepted=1"); exit;
}

if ($action === 'reject' && isset($_POST['from_user'])) {
    $from_user = intval($_POST['from_user']);
    $pdo->prepare(
        "DELETE FROM friends WHERE from_user=? AND to_user=? AND status='pending'"
    )->execute([$from_user, $user_id]);
    header("Location: users.php"); exit;
}

header("Location: users.php");
