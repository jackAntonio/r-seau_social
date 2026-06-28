<?php
require 'security.php';
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit;
}

// Vérification CSRF
if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token CSRF invalide']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$content = trim($_POST['content'] ?? '');
$media   = null;

if (!empty($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
    $allowed_mimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/webm',
    ];
    if (validate_upload($_FILES['media'], $allowed_mimes)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($_FILES['media']['tmp_name']);
        $ext_map = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
            'image/webp' => 'webp', 'video/mp4' => 'mp4', 'video/webm' => 'webm',
        ];
        $ext      = $ext_map[$mime] ?? 'bin';
        $mediaDir = 'uploads/posts/';
        make_upload_dir($mediaDir);
        $media = $mediaDir . bin2hex(random_bytes(8)) . '.' . $ext;
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
