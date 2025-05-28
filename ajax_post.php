<?php
session_start();
require 'config.php';

// Vérifier connexion
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit;
}

$user_id = $_SESSION['user_id'];
$content = trim($_POST['content'] ?? '');
$media = null;

// Gestion du média (image ou vidéo)
if (!empty($_FILES['media']) && $_FILES['media']['error'] == 0) {
    $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm'];
    if (in_array($ext, $allowed)) {
        $mediaDir = 'uploads/posts/';
        if (!is_dir($mediaDir)) mkdir($mediaDir, 0777, true);
        $media = $mediaDir . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['media']['tmp_name'], $media);
    }
}

if ($content === '' && !$media) {
    echo json_encode(['success' => false, 'error' => 'Message vide']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO posts (user_id, content, media, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iss", $user_id, $content, $media);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);