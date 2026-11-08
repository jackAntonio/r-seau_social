<?php
require 'security.php';
session_start();
require 'config.php';
require 'storage.php';
require 'roles.php';
require 'audit.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit;
}

if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token CSRF invalide']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$post_id = intval($_POST['post_id'] ?? 0);

if (!$post_id) {
    echo json_encode(['success' => false, 'error' => 'ID de publication manquant']);
    exit;
}

$stmt = $pdo->prepare("SELECT user_id, media FROM posts WHERE id=?");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if (!$post) {
    echo json_encode(['success' => false, 'error' => 'Publication introuvable']);
    exit;
}

$is_author = (intval($post['user_id']) === $user_id);
$is_adm    = is_admin($pdo, $user_id);

if (!$is_author && !$is_adm) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Vous n\'êtes pas autorisé à supprimer ce post']);
    exit;
}

// Supprimer le média associé si existant
if (!empty($post['media'])) {
    Storage::delete($post['media']);
}

// Supprimer le post (cascade supprime likes & comments)
$del = $pdo->prepare("DELETE FROM posts WHERE id=?");
$del->execute([$post_id]);

audit_log($pdo, 'post_delete', $user_id, 'post', $post_id);

echo json_encode(['success' => true]);
