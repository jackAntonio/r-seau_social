<?php
require 'security.php';
session_start();
require 'config.php';
require 'storage.php';

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Non connecté']); exit; }

$user_id = intval($_SESSION['user_id']);
$with    = intval($_GET['with'] ?? $_POST['with'] ?? 0);

// IDOR : seuls les deux participants peuvent accéder à la conversation
$chk = $pdo->prepare("SELECT id FROM friends WHERE ((from_user=? AND to_user=?) OR (from_user=? AND to_user=?)) AND status='accepted'");
$chk->execute([$user_id, $with, $with, $user_id]);
if (!$chk->fetch()) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accès refusé']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'CSRF invalide']); exit; }

    $content   = trim($_POST['message'] ?? '');
    $file_path = null;
    $file_type = null;

    if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $all = MIME_MEDIA + MIME_AUDIO + MIME_DOCS;
        $file_path = Storage::store($_FILES['file'], 'chat', $all);
        if ($file_path) {
            $finfo     = new finfo(FILEINFO_MIME_TYPE);
            $file_type = $finfo->file($file_path);
        }
    }

    if ($content === '' && !$file_path) { echo json_encode(['success'=>false,'error'=>'Message vide']); exit; }

    $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content, file, file_type) VALUES (?,?,?,?,?)")
        ->execute([$user_id, $with, $content, $file_path, $file_type]);

    echo json_encode(['success'=>true]);
    exit;
}

// GET : messages
$stmt = $pdo->prepare(
    "SELECT * FROM messages
     WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)
     ORDER BY created_at ASC LIMIT 100"
);
$stmt->execute([$user_id, $with, $with, $user_id]);
header('Content-Type: application/json');
echo json_encode($stmt->fetchAll());
