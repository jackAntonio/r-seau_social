<?php
require 'security.php';
session_start();
require 'config.php';

function add_notification(mysqli $conn, int $user_id, string $content, string $link = ''): void {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, content, link) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $content, $link);
    $stmt->execute();
    $stmt->close();
}

// Publication
if (isset($_POST['publish'], $_SESSION['user_id'])) {
    if (!csrf_verify()) { header("Location: feed.php"); exit; }

    $user_id = intval($_SESSION['user_id']);
    $type    = in_array($_POST['type'], ['text', 'photo', 'video']) ? $_POST['type'] : 'text';
    $content = trim($_POST['content'] ?? '');
    $media   = '';

    if (in_array($type, ['photo', 'video']) && isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $allowed_mimes = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
            'video/mp4' => 'mp4', 'video/webm' => 'webm',
        ];
        if (validate_upload($_FILES['media'], array_keys($allowed_mimes))) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($_FILES['media']['tmp_name']);
            $ext   = $allowed_mimes[$mime] ?? 'bin';
            make_upload_dir('uploads/posts');
            $media = 'uploads/posts/' . bin2hex(random_bytes(8)) . '.' . $ext;
            move_uploaded_file($_FILES['media']['tmp_name'], $media);
        }
    }

    $stmt = $conn->prepare("INSERT INTO posts (user_id, type, content, media) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $type, $content, $media);
    $stmt->execute();
    $stmt->close();
    header("Location: feed.php");
    exit;
}

// Like/dislike
if (isset($_POST['like'], $_SESSION['user_id'])) {
    if (!csrf_verify()) { exit; }

    $user_id = intval($_SESSION['user_id']);
    $post_id = intval($_POST['post_id'] ?? 0);
    $type    = in_array($_POST['like'], ['like', 'dislike']) ? $_POST['like'] : 'like';

    $stmt = $conn->prepare("INSERT INTO post_likes (post_id, user_id, type) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE type=?");
    $stmt->bind_param("iiss", $post_id, $user_id, $type, $type);
    $stmt->execute();
    $stmt->close();

    // Notification au propriétaire
    $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id=?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $stmt->bind_result($owner_id);
    if ($stmt->fetch() && $owner_id !== $user_id) {
        $stmt->close();
        $label = $type === 'like' ? 'aimé' : 'disliké';
        add_notification($conn, $owner_id, "Quelqu'un a {$label} votre post", "feed.php#post{$post_id}");
    } else {
        $stmt->close();
    }
    exit;
}

// Suppression post
if (isset($_POST['delete_post'], $_SESSION['user_id'])) {
    if (!csrf_verify()) { header("Location: feed.php"); exit; }

    $post_id = intval($_POST['delete_post']);
    $user_id = intval($_SESSION['user_id']);

    $stmt = $conn->prepare("SELECT user_id, media FROM posts WHERE id=?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $stmt->bind_result($owner_id, $media_path);
    if ($stmt->fetch() && $owner_id === $user_id) {
        $stmt->close();
        $del = $conn->prepare("DELETE FROM posts WHERE id=?");
        $del->bind_param("i", $post_id);
        $del->execute();
        $del->close();
        if ($media_path && file_exists($media_path)) @unlink($media_path);
        $del2 = $conn->prepare("DELETE FROM post_likes WHERE post_id=?");
        $del2->bind_param("i", $post_id);
        $del2->execute();
        $del2->close();
    } else {
        $stmt->close();
    }
    header("Location: feed.php");
    exit;
}

// Partage
if (isset($_POST['share_post'], $_SESSION['user_id'])) {
    if (!csrf_verify()) { header("Location: feed.php"); exit; }

    $user_id = intval($_SESSION['user_id']);
    $post_id = intval($_POST['share_post']);

    $stmt = $conn->prepare("SELECT user_id, type, content, media FROM posts WHERE id=?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($post) {
        $content = "[Partagé] " . $post['content'];
        $ins = $conn->prepare("INSERT INTO posts (user_id, type, content, media) VALUES (?, ?, ?, ?)");
        $ins->bind_param("isss", $user_id, $post['type'], $content, $post['media']);
        $ins->execute();
        $ins->close();
        add_notification($conn, $post['user_id'], "Quelqu'un a partagé votre post", "feed.php#post{$post_id}");
    }
    header("Location: feed.php");
    exit;
}

// Récupération posts
$stmt = $conn->prepare("SELECT p.*, u.nom, u.prenom, u.photo FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 50");
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fil d'actualité</title>
    <link rel="stylesheet" href="style.css">
    <style>
      .loader {display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:#fff8f880;z-index:9999;align-items:center;justify-content:center;}
      .loader.show {display:flex;}
    </style>
</head>
<body>
<div class="loader" id="loader"><img src="loader.gif" alt="Loading..."></div>
<div style="text-align:center;margin-top:12px;">
  <img src="site-logo.png" alt="Logo du site" style="max-width:90px;">
</div>
<div class="container">
    <?php if(isset($_SESSION['user_id'])): ?>
    <form method="post" enctype="multipart/form-data" onsubmit="showLoader()">
        <?= csrf_field() ?>
        <h3>Publier quelque chose</h3>
        <textarea name="content" placeholder="Exprimez-vous..." required style="width:98%;"></textarea><br>
        <select name="type" id="type" onchange="showFileInput()" required>
            <option value="text">Texte</option>
            <option value="photo">Photo</option>
            <option value="video">Vidéo</option>
        </select>
        <input type="file" name="media" id="media" accept="image/*,video/*" style="display:none;">
        <button type="submit" name="publish">Publier</button>
    </form>
    <?php endif; ?>
    <h2>Fil d'actualité</h2>
    <?php foreach($posts as $post):
        $pid = intval($post['id']);

        $stmt = $conn->prepare("SELECT COUNT(*) as nb FROM post_likes WHERE post_id=? AND type='like'");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $likes = $stmt->get_result()->fetch_assoc()['nb'];
        $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) as nb FROM post_likes WHERE post_id=? AND type='dislike'");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $dislikes = $stmt->get_result()->fetch_assoc()['nb'];
        $stmt->close();

        $myLike = 0;
        if (isset($_SESSION['user_id'])) {
            $uid = intval($_SESSION['user_id']);
            $stmt = $conn->prepare("SELECT type FROM post_likes WHERE post_id=? AND user_id=?");
            $stmt->bind_param("ii", $pid, $uid);
            $stmt->execute();
            $ul = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($ul) $myLike = $ul['type'] === 'like' ? 1 : -1;
        }
    ?>
        <div id="post<?= $pid ?>" style="margin-bottom:26px;padding:14px;background:#f8fbff;border-radius:10px;position:relative;">
            <img src="<?= htmlspecialchars($post['photo'] ?: 'default-user.png') ?>" alt="" style="width:45px;height:45px;border-radius:50%;vertical-align:middle;">
            <b><?= htmlspecialchars($post['nom']." ".$post['prenom']) ?></b>
            <span style="color:#888;font-size:90%"><?= htmlspecialchars($post['created_at']) ?></span>
            <?php if (isset($_SESSION['user_id']) && intval($_SESSION['user_id']) === intval($post['user_id'])): ?>
                <form method="post" style="display:inline;position:absolute;right:12px;top:13px;" onsubmit="return confirm('Supprimer ce post ?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_post" value="<?= $pid ?>">
                    <button type="submit" style="background:none;border:none;color:red;">Supprimer</button>
                </form>
            <?php endif; ?>
            <div style="margin-top:8px;">
                <?php if($post['type']==='photo' && $post['media']): ?>
                    <img src="<?= htmlspecialchars($post['media']) ?>" style="max-width:250px;">
                <?php elseif($post['type']==='video' && $post['media']): ?>
                    <video controls src="<?= htmlspecialchars($post['media']) ?>" style="max-width:280px;"></video>
                <?php endif; ?>
            </div>
            <div style="margin:8px 0"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
            <?php if(isset($_SESSION['user_id'])): ?>
            <button type="button" onclick="likePost(<?= $pid ?>,'like')" style="color:<?= $myLike==1?'green':'#333' ?>">👍 <?= intval($likes) ?></button>
            <button type="button" onclick="likePost(<?= $pid ?>,'dislike')" style="color:<?= $myLike==-1?'red':'#333' ?>">👎 <?= intval($dislikes) ?></button>
            <form method="post" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="share_post" value="<?= $pid ?>">
                <button type="submit">Partager</button>
            </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <a href="accueil.php" class="profile_button">Accueil</a>
    <a href="notifications.php" class="profile_button">Notifications</a>
</div>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;

function showFileInput() {
    let t = document.getElementById('type').value;
    document.getElementById('media').style.display = (t==='photo'||t==='video') ? 'block' : 'none';
}
function showLoader() { document.getElementById('loader').classList.add('show'); }

function likePost(postId, type) {
    showLoader();
    fetch('feed.php', {
        method: 'POST',
        headers: {'X-CSRF-Token': CSRF},
        body: new URLSearchParams({post_id: postId, like: type, csrf_token: CSRF})
    }).then(() => window.location.reload());
}
</script>
</body>
</html>
