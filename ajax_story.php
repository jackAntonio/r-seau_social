<?php
require 'security.php';
session_start();
require 'config.php';
require 'storage.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'Non connecté']); exit; }
if (!csrf_verify()) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'CSRF invalide']); exit; }

$user_id = intval($_SESSION['user_id']);

if (!empty($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
    $media = Storage::store($_FILES['media'], 'stories', MIME_MEDIA);
    if ($media) {
        $pdo->prepare("INSERT INTO stories (user_id, image) VALUES (?,?)")->execute([$user_id, $media]);
        echo json_encode(['success'=>true]);
        exit;
    }
}

echo json_encode(['success'=>false,'error'=>'Fichier invalide ou type non autorisé']);
