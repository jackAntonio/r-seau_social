<?php
require 'security.php';
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
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
$media   = null;

if (!empty($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'video/mp4'  => 'mp4',
        'video/webm' => 'webm',
    ];

    if (validate_upload($_FILES['media'], array_keys($allowed_mimes))) {
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mime     = $finfo->file($_FILES['media']['tmp_name']);
        $ext      = $allowed_mimes[$mime] ?? 'bin';
        $mediaDir = 'uploads/stories/';
        make_upload_dir($mediaDir);
        $media = $mediaDir . bin2hex(random_bytes(8)) . '.' . $ext;
        move_uploaded_file($_FILES['media']['tmp_name'], $media);

        $stmt = $conn->prepare("INSERT INTO stories (user_id, image, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $user_id, $media);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Fichier invalide ou type non autorisé']);
