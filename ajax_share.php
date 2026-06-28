<?php
require 'security.php';
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
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

$stmt = $conn->prepare("SELECT content, media FROM posts WHERE id=?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$post) {
    echo json_encode(['success' => false]);
    exit;
}

$content = '[Partagé] ' . $post['content'];
$media   = $post['media'];
$stmt    = $conn->prepare("INSERT INTO posts (user_id, content, media, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iss", $user_id, $content, $media);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
