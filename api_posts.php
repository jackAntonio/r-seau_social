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

// Une seule requête avec agrégation des réactions (élimine le N+1)
$stmt = $pdo->prepare("
    SELECT p.id, p.content, p.media, p.created_at,
           u.id AS user_id, u.nom, u.prenom, u.photo,
           COUNT(pl.id) FILTER (WHERE pl.type='like')    AS r_like,
           COUNT(pl.id) FILTER (WHERE pl.type='love')    AS r_love,
           COUNT(pl.id) FILTER (WHERE pl.type='haha')    AS r_haha,
           COUNT(pl.id) FILTER (WHERE pl.type='wow')     AS r_wow,
           COUNT(pl.id) FILTER (WHERE pl.type='sad')     AS r_sad,
           COUNT(pl.id) FILTER (WHERE pl.type='angry')   AS r_angry,
           COUNT(pl.id) FILTER (WHERE pl.type='dislike') AS r_dislike,
           MAX(CASE WHEN pl.user_id=? THEN pl.type END)  AS my_reaction
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN post_likes pl ON pl.post_id = p.id
    GROUP BY p.id, u.id, u.nom, u.prenom, u.photo
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$me, $per_page, $offset]);
$posts = $stmt->fetchAll();

$total    = intval($pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn());
$has_more = ($page * $per_page) < $total;

echo json_encode(['posts' => $posts, 'page' => $page, 'has_more' => $has_more, 'total' => $total]);
