<?php
require 'security.php';
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token CSRF invalide']);
    exit;
}

if (!isset($_POST['post_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$post_id = intval($_POST['post_id']);

$stmt = $conn->prepare("SELECT id FROM likes WHERE user_id=? AND post_id=?");
$stmt->bind_param("ii", $user_id, $post_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->fetch_assoc()) {
    $stmt->close();
    $del = $conn->prepare("DELETE FROM likes WHERE user_id=? AND post_id=?");
    $del->bind_param("ii", $user_id, $post_id);
    $del->execute();
    $del->close();
} else {
    $stmt->close();
    $ins = $conn->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
    $ins->bind_param("ii", $user_id, $post_id);
    $ins->execute();
    $ins->close();
}

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM likes WHERE post_id=?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode(['success' => true, 'likes' => intval($res['cnt'])]);
