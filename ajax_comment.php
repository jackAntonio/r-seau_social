<?php
require 'security.php';
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false]); exit; }
    if (!csrf_verify()) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'CSRF invalide']); exit; }

    $user_id = intval($_SESSION['user_id']);
    $post_id = intval($_POST['post_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if (!$post_id || $comment === '') { echo json_encode(['success'=>false]); exit; }

    $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->execute([$post_id, $user_id, $comment]);
    echo json_encode(['success' => true]);
    exit;
}

// GET : afficher les commentaires
if (isset($_GET['post_id'])) {
    $post_id = intval($_GET['post_id']);
    $stmt    = $pdo->prepare(
        "SELECT c.*, u.nom, u.prenom, u.photo FROM comments c
         JOIN users u ON u.id=c.user_id WHERE c.post_id=? ORDER BY c.created_at ASC"
    );
    $stmt->execute([$post_id]);
    while ($c = $stmt->fetch()) {
        echo '<div style="margin:6px 0;display:flex;align-items:center;gap:7px;">';
        echo '<img src="' . htmlspecialchars($c['photo'] ?: 'default-user.png') . '" style="width:28px;height:28px;border-radius:50%;">';
        echo '<b>' . htmlspecialchars($c['nom'] . ' ' . $c['prenom']) . ' :</b>';
        echo '<span>' . nl2br(htmlspecialchars($c['content'])) . '</span>';
        echo '</div>';
    }
}
