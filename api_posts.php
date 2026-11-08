<?php
require 'security.php';
session_start();
require 'config.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Non connecté']); exit; }

$page     = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset   = ($page - 1) * $per_page;
$me       = intval($_SESSION['user_id']);

// Requête avec agrégation ANSI SQL compatible MySQL et PostgreSQL
$stmt = $pdo->prepare("
    SELECT p.id, p.content, p.media, p.created_at,
           u.id AS user_id, u.nom, u.prenom, u.photo,
           (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count,
           COUNT(CASE WHEN pl.type='like' THEN 1 END)    AS r_like,
           COUNT(CASE WHEN pl.type='love' THEN 1 END)    AS r_love,
           COUNT(CASE WHEN pl.type='haha' THEN 1 END)    AS r_haha,
           COUNT(CASE WHEN pl.type='wow' THEN 1 END)     AS r_wow,
           COUNT(CASE WHEN pl.type='sad' THEN 1 END)     AS r_sad,
           COUNT(CASE WHEN pl.type='angry' THEN 1 END)   AS r_angry,
           COUNT(CASE WHEN pl.type='dislike' THEN 1 END) AS r_dislike,
           MAX(CASE WHEN pl.user_id=? THEN pl.type END)  AS my_reaction
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN post_likes pl ON pl.post_id = p.id
    GROUP BY p.id, u.id, u.nom, u.prenom, u.photo, p.content, p.media, p.created_at
    ORDER BY p.created_at DESC
    LIMIT " . intval($per_page) . " OFFSET " . intval($offset) . "
");
$stmt->execute([$me]);
$posts = $stmt->fetchAll();

$total    = intval($pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn());
$has_more = ($page * $per_page) < $total;

echo json_encode(['posts' => $posts, 'page' => $page, 'has_more' => $has_more, 'total' => $total]);
