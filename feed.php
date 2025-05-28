<?php
session_start();
require 'config.php';

function add_notification($user_id, $content, $link = '') {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, content, link) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $content, $link);
    $stmt->execute(); $stmt->close();
}

// Publication
if (isset($_POST['publish']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $type = $_POST['type'];
    $content = trim($_POST['content']);
    $media = '';
    if ($type === 'photo' && isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
        $ext = pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION);
        $media = 'uploads/posts/' . uniqid() . '.' . $ext;
        if (!is_dir('uploads/posts')) mkdir('uploads/posts', 0777, true);
        move_uploaded_file($_FILES['media']['tmp_name'], $media);
    }
    if ($type === 'video' && isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
        $ext = pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION);
        $media = 'uploads/posts/' . uniqid() . '.' . $ext;
        if (!is_dir('uploads/posts')) mkdir('uploads/posts', 0777, true);
        move_uploaded_file($_FILES['media']['tmp_name'], $media);
    }
    $stmt = $conn->prepare("INSERT INTO posts (user_id, type, content, media) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $type, $content, $media);
    $stmt->execute(); $stmt->close();
    header("Location: feed.php");
    exit;
}

// Like/dislike
if (isset($_POST['like']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $post_id = intval($_POST['post_id']);
    $type = $_POST['like'];
    $stmt = $conn->prepare("INSERT INTO post_likes (post_id, user_id, type) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE type=?");
    $stmt->bind_param("iiss", $post_id, $user_id, $type, $type);
    $stmt->execute();
    // Notification au propriétaire du post (si ce n'est pas moi)
    $q = $conn->query("SELECT user_id FROM posts WHERE id = $post_id");
    if ($q && $row = $q->fetch_assoc() and $row['user_id'] != $user_id) {
        add_notification($row['user_id'], "Quelqu'un a " . ($type=='like'?"aimé":"disliké") . " votre post", "feed.php#post$post_id");
    }
    $stmt->close();
    exit;
}

// Suppression post
if (isset($_POST['delete_post']) && isset($_SESSION['user_id'])) {
    $post_id = intval($_POST['delete_post']);
    $user_id = $_SESSION['user_id'];
    $q = $conn->query("SELECT user_id, media FROM posts WHERE id = $post_id");
    if ($q && $row = $q->fetch_assoc() and $row['user_id'] == $user_id) {
        // Supprimer le post et le média du serveur
        $conn->query("DELETE FROM posts WHERE id = $post_id");
        if ($row['media'] && file_exists($row['media'])) @unlink($row['media']);
        $conn->query("DELETE FROM post_likes WHERE post_id = $post_id");
    }
    header("Location: feed.php");
    exit;
}

// Partage (double le post)
if (isset($_POST['share_post']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $post_id = intval($_POST['share_post']);
    $q = $conn->query("SELECT * FROM posts WHERE id = $post_id");
    if ($q && $post = $q->fetch_assoc()) {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, type, content, media) VALUES (?, ?, ?, ?)");
        $content = "[Partagé] " . $post['content'];
        $stmt->bind_param("isss", $user_id, $post['type'], $content, $post['media']);
        $stmt->execute(); $stmt->close();
        add_notification($post['user_id'], "Quelqu'un a partagé votre post", "feed.php#post$post_id");
    }
    header("Location: feed.php");
    exit;
}

// Récupération posts
$posts = $conn->query("SELECT p.*, u.nom, u.prenom, u.photo FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
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
    <?php foreach($posts as $post): ?>
        <?php
        $likes = $conn->query("SELECT COUNT(*) as nb FROM post_likes WHERE post_id={$post['id']} AND type='like'")->fetch_assoc()['nb'];
        $dislikes = $conn->query("SELECT COUNT(*) as nb FROM post_likes WHERE post_id={$post['id']} AND type='dislike'")->fetch_assoc()['nb'];
        // ai-je liké/disliké ?
        $myLike = 0;
        if (isset($_SESSION['user_id'])) {
            $ul = $conn->query("SELECT type FROM post_likes WHERE post_id={$post['id']} AND user_id={$_SESSION['user_id']}")->fetch_assoc();
            if ($ul) $myLike = $ul['type']=='like' ? 1 : -1;
        }
        ?>
        <div id="post<?= $post['id'] ?>" style="margin-bottom:26px;padding:14px;background:#f8fbff;border-radius:10px;position:relative;">
            <img src="<?= htmlspecialchars($post['photo']) ?>" alt="" style="width:45px;height:45px;border-radius:50%;vertical-align:middle;">
            <b><?= htmlspecialchars($post['nom']." ".$post['prenom']) ?></b>
            <span style="color:#888;font-size:90%"><?= $post['created_at'] ?></span>
            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id']==$post['user_id']): ?>
                <form method="post" style="display:inline;position:absolute;right:12px;top:13px;" onsubmit="return confirm('Supprimer ce post ?')">
                    <input type="hidden" name="delete_post" value="<?= $post['id'] ?>">
                    <button type="submit" style="background:none;border:none;color:red;">Supprimer</button>
                </form>
            <?php endif; ?>
            <div style="margin-top:8px;">
                <?php if($post['type']=='photo' && $post['media']): ?>
                    <img src="<?= htmlspecialchars($post['media']) ?>" style="max-width:250px;">
                <?php elseif($post['type']=='video' && $post['media']): ?>
                    <video controls src="<?= htmlspecialchars($post['media']) ?>" style="max-width:280px;"></video>
                <?php endif; ?>
            </div>
            <div style="margin:8px 0"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
            <?php if(isset($_SESSION['user_id'])): ?>
            <form method="post" style="display:inline;" class="like-form" data-id="<?= $post['id'] ?>">
                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                <button type="button" onclick="likePost(<?= $post['id'] ?>,'like')" style="color:<?= $myLike==1?'green':'#333' ?>">👍 <?= $likes ?></button>
                <button type="button" onclick="likePost(<?= $post['id'] ?>,'dislike')" style="color:<?= $myLike==-1?'red':'#333' ?>">👎 <?= $dislikes ?></button>
            </form>
            <form method="post" style="display:inline;">
                <input type="hidden" name="share_post" value="<?= $post['id'] ?>">
                <button type="submit">Partager</button>
            </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <a href="accueil.php" class="profile_button">Accueil</a>
    <a href="notifications.php" class="profile_button">Notifications</a>
</div>
<script>
function showFileInput() {
    let t = document.getElementById('type').value;
    document.getElementById('media').style.display = (t=='photo'||t=='video') ? 'block' : 'none';
}
function showLoader() { document.getElementById('loader').classList.add('show'); }
function hideLoader() { document.getElementById('loader').classList.remove('show'); }

function likePost(postId, type) {
    showLoader();
    fetch('feed.php', {
        method:'POST',
        body: new URLSearchParams({post_id:postId, like:type})
    }).then(r=>r.text()).then(()=>window.location.reload());
}
</script>
</body>
</html>