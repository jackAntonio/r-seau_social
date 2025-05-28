<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'], $_POST['post_id'], $_POST['comment'])) {
        echo json_encode(['success' => false]);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $post_id = intval($_POST['post_id']);
    $comment = trim($_POST['comment']);
    if ($comment === '') {
        echo json_encode(['success' => false]);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $post_id, $user_id, $comment);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

// Affichage des commentaires d'un post (GET)
if (isset($_GET['post_id'])) {
    $post_id = intval($_GET['post_id']);
    $stmt = $conn->prepare("SELECT c.*, u.nom, u.prenom, u.photo FROM comments c JOIN users u ON u.id = c.user_id WHERE c.post_id=? ORDER BY c.created_at ASC");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($c = $res->fetch_assoc()) {
        ?>
        <div style="margin:6px 0 6px 0;display:flex;align-items:center;gap:7px;">
            <img src="<?= htmlspecialchars($c['photo'] ?: 'default-user.png') ?>" style="width:28px;height:28px;border-radius:50%;">
            <b><?= htmlspecialchars($c['nom'] . ' ' . $c['prenom']) ?> :</b>
            <span><?= nl2br(htmlspecialchars($c['content'])) ?></span>
        </div>
        <?php
    }
    $stmt->close();
}