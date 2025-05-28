<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'], $_POST['post_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];
$post_id = intval($_POST['post_id']);

// Vérifier si déjà liké
$stmt = $conn->prepare("SELECT id FROM likes WHERE user_id=? AND post_id=?");
$stmt->bind_param("ii", $user_id, $post_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->fetch_assoc()) {
    // Unlike
    $stmt2 = $conn->prepare("DELETE FROM likes WHERE user_id=? AND post_id=?");
    $stmt2->bind_param("ii", $user_id, $post_id);
    $stmt2->execute();
    $stmt2->close();
} else {
    // Like
    $stmt2 = $conn->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
    $stmt2->bind_param("ii", $user_id, $post_id);
    $stmt2->execute();
    $stmt2->close();
}
$stmt->close();

// Retourner le nombre de likes
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM likes WHERE post_id=?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode(['success' => true, 'likes' => intval($res['cnt'])]);