<?php
require 'security.php';
session_start();
require 'config.php';
require 'storage.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Non connecté']); exit; }
if (!csrf_verify()) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Token CSRF invalide']); exit; }

$user_id = intval($_SESSION['user_id']);
$content = trim($_POST['content'] ?? '');
$media   = null;

if (!empty($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
    $media = Storage::store($_FILES['media'], 'posts', MIME_MEDIA);
}

if ($content === '' && !$media) { echo json_encode(['success'=>false,'error'=>'Message vide']); exit; }

$stmt = $pdo->prepare("INSERT INTO posts (user_id, content, media) VALUES (?, ?, ?)");
$stmt->execute([$user_id, $content, $media]);

echo json_encode(['success' => true]);
