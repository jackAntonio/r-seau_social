<?php
require 'security.php';
session_start();
require 'config.php';
require 'storage.php';
require 'audit.php';

function add_notification(PDO $pdo, int $user_id, string $content, string $link = ''): void {
    $pdo->prepare("INSERT INTO notifications (user_id, content, link) VALUES (?,?,?)")
        ->execute([$user_id, $content, $link]);
}

// Publication
if (isset($_POST['publish'], $_SESSION['user_id'])) {
    if (!csrf_verify()) { header("Location: feed.php"); exit; }
    $user_id = intval($_SESSION['user_id']);
    $type    = in_array($_POST['type'] ?? '', ['text','photo','video']) ? $_POST['type'] : 'text';
    $content = trim($_POST['content'] ?? '');
    $media   = '';
    if (in_array($type, ['photo','video']) && isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $m = Storage::store($_FILES['media'], 'posts', MIME_MEDIA);
        if ($m) $media = $m;
    }
    $pdo->prepare("INSERT INTO posts (user_id, type, content, media) VALUES (?,?,?,?)")
        ->execute([$user_id, $type, $content, $media]);
    audit_log($pdo, 'post_create', $user_id, 'post');
    header("Location: feed.php"); exit;
}

// Like/dislike
if (isset($_POST['like'], $_SESSION['user_id'])) {
    if (!csrf_verify()) exit;
    $user_id = intval($_SESSION['user_id']);
    $post_id = intval($_POST['post_id'] ?? 0);
    $type    = in_array($_POST['like'], ['like','dislike']) ? $_POST['like'] : 'like';
    $pdo->prepare(
        "INSERT INTO post_likes (post_id, user_id, type) VALUES (?,?,?) ON CONFLICT (post_id, user_id) DO UPDATE SET type=?"
    )->execute([$post_id, $user_id, $type, $type]);
    $owner = $pdo->prepare("SELECT user_id FROM posts WHERE id=?");
    $owner->execute([$post_id]);
    $oid = intval($owner->fetchColumn());
    if ($oid && $oid !== $user_id) {
        add_notification($pdo, $oid, "Quelqu'un a {$type} votre post", "feed.php#post{$post_id}");
    }
    exit;
}

// Suppression post
if (isset($_POST['delete_post'], $_SESSION['user_id'])) {
    if (!csrf_verify()) { header("Location: feed.php"); exit; }
    $post_id = intval($_POST['delete_post']);
    $user_id = intval($_SESSION['user_id']);
    $chk = $pdo->prepare("SELECT user_id, media FROM posts WHERE id=?");
    $chk->execute([$post_id]);
    $row = $chk->fetch();
    if ($row && intval($row['user_id']) === $user_id) {
        $pdo->prepare("DELETE FROM posts WHERE id=?")->execute([$post_id]);
        Storage::delete($row['media']);
        audit_log($pdo, 'post_delete', $user_id, 'post', $post_id);
    }
    header("Location: feed.php"); exit;
}

// Partage
if (isset($_POST['share_post'], $_SESSION['user_id'])) {
    if (!csrf_verify()) { header("Location: feed.php"); exit; }
    $user_id = intval($_SESSION['user_id']);
    $post_id = intval($_POST['share_post']);
    $chk = $pdo->prepare("SELECT user_id, type, content, media FROM posts WHERE id=?");
    $chk->execute([$post_id]);
    $post = $chk->fetch();
    if ($post) {
        $pdo->prepare("INSERT INTO posts (user_id, type, content, media) VALUES (?,?,?,?)")
            ->execute([$user_id, $post['type'], '[Partagé] '.$post['content'], $post['media']]);
        add_notification($pdo, $post['user_id'], "Quelqu'un a partagé votre post", "feed.php#post{$post_id}");
    }
    header("Location: feed.php"); exit;
}

// Récupération posts
$stmt = $pdo->prepare(
    "SELECT p.*, u.nom, u.prenom, u.photo FROM posts p
     JOIN users u ON p.user_id=u.id ORDER BY p.created_at DESC LIMIT 50"
);
$stmt->execute();
$posts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><title>Fil d'actualité</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div style="text-align:center;margin-top:12px;"><img src="site-logo.png" alt="Logo" style="max-width:90px;"></div>
<div class="container">
  <?php if(isset($_SESSION['user_id'])): ?>
  <form method="post" enctype="multipart/form-data">
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
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id=? AND type='like'");
    $cnt->execute([$pid]); $likes = intval($cnt->fetchColumn());
    $cnt->execute([$pid]); // reuse would fail, just recount
    $cnt2 = $pdo->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id=? AND type='dislike'");
    $cnt2->execute([$pid]); $dislikes = intval($cnt2->fetchColumn());
    $myLike = 0;
    if (isset($_SESSION['user_id'])) {
        $ml = $pdo->prepare("SELECT type FROM post_likes WHERE post_id=? AND user_id=?");
        $ml->execute([$pid, intval($_SESSION['user_id'])]);
        $ul = $ml->fetch();
        if ($ul) $myLike = $ul['type']==='like' ? 1 : -1;
    }
  ?>
    <div id="post<?= $pid ?>" style="margin-bottom:26px;padding:14px;background:#f8fbff;border-radius:10px;position:relative;">
      <img src="<?= htmlspecialchars($post['photo'] ?: 'default-user.png') ?>" style="width:45px;height:45px;border-radius:50%;vertical-align:middle;" alt="">
      <b><?= htmlspecialchars($post['nom'].' '.$post['prenom']) ?></b>
      <span style="color:#888;font-size:90%"><?= htmlspecialchars($post['created_at']) ?></span>
      <?php if (isset($_SESSION['user_id']) && intval($_SESSION['user_id']) === intval($post['user_id'])): ?>
        <form method="post" style="display:inline;position:absolute;right:12px;top:13px;" onsubmit="return confirm('Supprimer ?')">
          <?= csrf_field() ?>
          <button type="submit" name="delete_post" value="<?= $pid ?>" style="background:none;border:none;color:red;">Supprimer</button>
        </form>
      <?php endif; ?>
      <div style="margin-top:8px;">
        <?php if($post['media']): ?>
          <?php if(preg_match('/\.(mp4|webm)$/i', $post['media'])): ?>
            <video controls src="<?= htmlspecialchars($post['media']) ?>" style="max-width:280px;"></video>
          <?php else: ?>
            <img src="<?= htmlspecialchars($post['media']) ?>" style="max-width:250px;" alt="">
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <div style="margin:8px 0"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
      <?php if(isset($_SESSION['user_id'])): ?>
        <button onclick="likePost(<?= $pid ?>,'like')" style="color:<?= $myLike==1?'green':'#333' ?>">👍 <?= $likes ?></button>
        <button onclick="likePost(<?= $pid ?>,'dislike')" style="color:<?= $myLike==-1?'red':'#333' ?>">👎 <?= $dislikes ?></button>
        <form method="post" style="display:inline;">
          <?= csrf_field() ?>
          <button type="submit" name="share_post" value="<?= $pid ?>">Partager</button>
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
  const t = document.getElementById('type').value;
  document.getElementById('media').style.display = (t==='photo'||t==='video') ? 'block' : 'none';
}
function likePost(postId, type) {
  fetch('feed.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`post_id=${postId}&like=${type}&csrf_token=${encodeURIComponent(CSRF)}`
  }).then(()=>window.location.reload());
}
</script>
</body>
</html>
