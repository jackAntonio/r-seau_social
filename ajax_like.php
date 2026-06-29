<?php
require 'security.php';
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false]); exit; }
if (!csrf_verify()) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'CSRF invalide']); exit; }
if (!isset($_POST['post_id'])) { echo json_encode(['success'=>false]); exit; }

$user_id  = intval($_SESSION['user_id']);
$post_id  = intval($_POST['post_id']);
$reaction = $_POST['reaction'] ?? 'like';
$allowed  = ['like','love','haha','wow','sad','angry','dislike'];
if (!in_array($reaction, $allowed)) $reaction = 'like';

// Toggle : même réaction = suppression, sinon upsert
$chk = $pdo->prepare("SELECT type FROM post_likes WHERE user_id=? AND post_id=?");
$chk->execute([$user_id, $post_id]);
$current = $chk->fetchColumn();

if ($current === $reaction) {
    $pdo->prepare("DELETE FROM post_likes WHERE user_id=? AND post_id=?")->execute([$user_id, $post_id]);
    $my_reaction = null;
} else {
    $pdo->prepare("INSERT INTO post_likes (user_id, post_id, type) VALUES (?,?,?)
                   ON CONFLICT (post_id, user_id) DO UPDATE SET type=EXCLUDED.type")
        ->execute([$user_id, $post_id, $reaction]);
    $my_reaction = $reaction;
}

// Compter toutes les réactions d'un coup
$cnt = $pdo->prepare("
    SELECT type, COUNT(*) as total FROM post_likes WHERE post_id=? GROUP BY type
");
$cnt->execute([$post_id]);
$counts = [];
foreach ($cnt->fetchAll() as $row) $counts[$row['type']] = intval($row['total']);

echo json_encode(['success' => true, 'counts' => $counts, 'my_reaction' => $my_reaction]);
