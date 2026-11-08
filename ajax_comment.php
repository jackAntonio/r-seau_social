<?php
require 'security.php';
session_start();
require 'config.php';
require 'roles.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'error'=>'Non connecté']); exit; }
    if (!csrf_verify()) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'CSRF invalide']); exit; }

    $user_id = intval($_SESSION['user_id']);

    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $comment_id = intval($_POST['comment_id'] ?? 0);
        $chk = $pdo->prepare("SELECT user_id, post_id FROM comments WHERE id=?");
        $chk->execute([$comment_id]);
        $c = $chk->fetch();
        if ($c && ($c['user_id'] == $user_id || is_admin($pdo, $user_id))) {
            $pdo->prepare("DELETE FROM comments WHERE id=?")->execute([$comment_id]);
            echo json_encode(['success' => true]);
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Non autorisé']);
        exit;
    }

    $post_id = intval($_POST['post_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if (!$post_id || $comment === '') { echo json_encode(['success'=>false, 'error'=>'Commentaire vide']); exit; }

    $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->execute([$post_id, $user_id, $comment]);

    // Notification à l'auteur du post si différent
    $owner = $pdo->prepare("SELECT user_id FROM posts WHERE id=?");
    $owner->execute([$post_id]);
    $oid = intval($owner->fetchColumn());
    if ($oid && $oid !== $user_id) {
        $me = $pdo->prepare("SELECT nom, prenom FROM users WHERE id=?");
        $me->execute([$user_id]);
        $me_u = $me->fetch();
        $pdo->prepare("INSERT INTO notifications (user_id, content, link) VALUES (?,?,?)")
            ->execute([$oid, "{$me_u['nom']} {$me_u['prenom']} a commenté votre publication", "accueil.php#post-{$post_id}"]);
    }

    echo json_encode(['success' => true]);
    exit;
}

// GET : afficher les commentaires
if (isset($_GET['post_id'])) {
    $post_id = intval($_GET['post_id']);
    $me = intval($_SESSION['user_id'] ?? 0);
    $is_adm = $me ? is_admin($pdo, $me) : false;
    $stmt    = $pdo->prepare(
        "SELECT c.*, u.nom, u.prenom, u.photo FROM comments c
         JOIN users u ON u.id=c.user_id WHERE c.post_id=? ORDER BY c.created_at ASC"
    );
    $stmt->execute([$post_id]);
    $comments = $stmt->fetchAll();
    
    if (empty($comments)) {
        echo '<div class="no-comments" style="color:var(--text-secondary);font-size:0.86rem;padding:8px 0;text-align:center;">Aucun commentaire pour le moment. Soyez le premier !</div>';
        exit;
    }

    foreach ($comments as $c) {
        $can_delete = ($me === intval($c['user_id']) || $is_adm);
        $cid = intval($c['id']);
        $time_diff = time() - strtotime($c['created_at']);
        if ($time_diff < 60) $rel = "À l'instant";
        elseif ($time_diff < 3600) $rel = intval($time_diff/60) . " min";
        elseif ($time_diff < 86400) $rel = intval($time_diff/3600) . " h";
        else $rel = date('d M', strtotime($c['created_at']));

        echo '<div class="fb-comment-item" id="comment-' . $cid . '">';
        echo '<a href="profile.php?id=' . intval($c['user_id']) . '"><img src="' . htmlspecialchars($c['photo'] ?: 'default-user.png') . '" class="fb-comment-avatar" alt=""></a>';
        echo '<div class="fb-comment-body">';
        echo '<div class="fb-comment-bubble">';
        echo '<a href="profile.php?id=' . intval($c['user_id']) . '" class="fb-comment-author">' . htmlspecialchars($c['nom'] . ' ' . $c['prenom']) . '</a>';
        echo '<div class="fb-comment-text">' . nl2br(htmlspecialchars($c['content'])) . '</div>';
        echo '</div>';
        echo '<div class="fb-comment-meta">';
        echo '<span>' . htmlspecialchars($rel) . '</span>';
        if ($can_delete) {
            echo ' · <button type="button" class="fb-comment-del" onclick="deleteComment(' . $cid . ',' . $post_id . ')">Supprimer</button>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
