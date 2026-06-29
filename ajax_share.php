<?php
require 'security.php';
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'Non connecté']); exit; }
if (!csrf_verify()) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'CSRF invalide']); exit; }
if (!isset($_POST['post_id'])) { echo json_encode(['success'=>false]); exit; }

$user_id = intval($_SESSION['user_id']);
$post_id = intval($_POST['post_id']);

$stmt = $pdo->prepare("SELECT content, media FROM posts WHERE id=?");
$stmt->execute([$post_id]);
$post = $stmt->fetch();
if (!$post) { echo json_encode(['success'=>false]); exit; }

$pdo->prepare("INSERT INTO posts (user_id, content, media) VALUES (?,?,?)")
    ->execute([$user_id, '[Partagé] ' . $post['content'], $post['media']]);

echo json_encode(['success'=>true]);
