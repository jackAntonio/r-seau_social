<?php
require 'security.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'config.php';

$id = $_SESSION['user_id'];

// Récupérer l'utilisateur connecté
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Vérifier si l'utilisateur connecté a une story (on suppose une table 'stories' avec user_id, image, created_at)
$stmt = $conn->prepare("SELECT * FROM stories WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$user_story = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Récupérer les stories des autres utilisateurs (1 par utilisateur, la plus récente)
$stmt = $conn->prepare(
    "SELECT s.*, u.nom, u.prenom, u.photo 
    FROM stories s 
    JOIN users u ON u.id = s.user_id 
    WHERE s.user_id != ? 
    AND s.created_at = (
        SELECT MAX(s2.created_at) FROM stories s2 WHERE s2.user_id = s.user_id
    )
    ORDER BY s.created_at DESC"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$stories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Exemple de récupération des posts (fil d'actualité) - à adapter selon ton besoin
$stmt = $conn->prepare(
    "SELECT p.*, u.nom, u.prenom, u.photo
     FROM posts p
     JOIN users u ON u.id = p.user_id
     ORDER BY p.created_at DESC
     LIMIT 10"
);
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Accueil - Réseau Social</title>
    <meta name="viewport" content="width=1030">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Segoe+UI:400,700&display=swap">
    <style>
/* Design Facebook-like, voir précédentes réponses */
body {
    background: #f0f2f5;
    margin: 0;
    font-family: 'Segoe UI', Arial, sans-serif;
    color: #1c1e21;
}
.headerbar {
    background: #fff;
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 0 18px 0 12px;
    box-shadow: 0 2px 10px #0001;
    height: 60px;
    position: sticky;
    top: 0;
    z-index: 11;
}
.headerbar img.logo {
    width: 40px;
    border-radius: 50%;
}
.headerbar .search {
    margin-left: 16px;
    background: #f0f2f5;
    border-radius: 20px;
    padding: 8px 16px;
    border: none;
    outline: none;
    font-size: 1em;
    width: 180px;
}
.headerbar .nav-icons {
    margin: 0 0 0 30px;
    display: flex;
    gap: 36px;
    font-size: 1.36em;
    color: #65676b;
}
.headerbar .nav-icons .active {
    color: #2563f8;
    border-bottom: 3px solid #2563f8;
    padding-bottom: 7px;
    border-radius: 0;
}
.headerbar .actions {
    margin-left: auto;
    display: flex;
    gap: 17px;
    align-items: center;
}
.headerbar .actions img.profile {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #2563f8;
}
.headerbar .actions .user {
    font-weight: 600;
    font-size: 1em;
    margin-left: 5px;
}
.headerbar .actions a {
    color: #2563f8;
    font-size: 1.06em;
    text-decoration: none;
    margin-left: 12px;
    font-weight: 550;
    background: #f0f2f5;
    padding: 7px 18px;
    border-radius: 20px;
    transition: background .18s;
}
.headerbar .actions a:hover {
    background: #e8f0fe;
}
.wrapper {
    display: flex;
    justify-content: center;
    gap: 34px;
    margin: 0 auto;
    max-width: 1030px;
    padding: 22px 10px 0 10px;
}
.left-col {
    width: 230px;
    min-width: 230px;
    margin-top: 8px;
}
.center-col {
    flex: 1 1 530px;
    max-width: 530px;
    min-width: 330px;
}
.right-col {
    width: 230px;
    min-width: 180px;
    margin-top: 8px;
}
.stories-bar {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 12px #0001;
    padding: 18px 10px 14px 18px;
    display: flex;
    align-items: flex-end;
    gap: 13px;
    margin-bottom: 20px;
    overflow-x: auto;
}
.story {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 85px;
    min-width: 85px;
    margin-right: 4px;
    cursor: pointer;
    position: relative;
}
.story .story-img {
    width: 68px;
    height: 68px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #2563f8;
    box-shadow: 0 2px 12px #2563f811;
}
.story.create .story-img {
    border: 3px dashed #2eccff;
    filter: grayscale(0.4);
    opacity: 0.7;
}
.story .plus {
    position: absolute;
    left: 46px;
    top: 44px;
    width: 25px;
    height: 25px;
    background: #2563f8;
    color: #fff;
    font-size: 1.5em;
    border-radius: 50%;
    border: 3px solid #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    box-shadow: 0 1px 9px #2eccff22;
}
.story .name {
    margin-top: 5px;
    text-align: center;
    font-size: 0.97em;
    font-weight: 600;
    color: #212529;
    word-break: break-all;
    max-width: 80px;
}
.postbar {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 12px #0001;
    padding: 17px 24px;
    margin-bottom: 22px;
    display: flex;
    flex-direction: column;
    gap: 13px;
}
.postbar .me-row {
    display: flex;
    align-items: center;
    gap: 13px;
}
.postbar img.profile {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #2563f8;
}
.postbar input[type="text"] {
    flex: 1 1 auto;
    background: #f0f2f5;
    border: none;
    border-radius: 18px;
    padding: 11px 22px;
    font-size: 1.08em;
    outline: none;
    margin-left: 6px;
    transition: background .18s;
}
.postbar input:focus {
    background: #eaf0fb;
}
.postbar .actions {
    display: flex;
    gap: 20px;
    margin-top: 6px;
    justify-content: flex-start;
}
.postbar .actions button {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f0f2f5;
    border: none;
    border-radius: 11px;
    padding: 7px 17px;
    font-size: 1.04em;
    color: #2563f8;
    font-weight: 600;
    cursor: pointer;
    transition: background .14s;
}
.postbar .actions button:hover {
    background: #e8f0fe;
}
.feed {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.feed-card {
    background: #fff;
    border-radius: 11px;
    box-shadow: 0 1px 10px #0001;
    padding: 17px 17px 8px 17px;
    display: flex;
    flex-direction: column;
    gap: 7px;
}
.feed-card .author-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 2px;
}
.feed-card .author-img {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #2563f8;
}
.feed-card .author-name {
    font-weight: 700;
    font-size: 1em;
    color: #212529;
}
.feed-card .post-date {
    font-size: 0.93em;
    color: #888;
}
.feed-card .content {
    margin-top: 4px;
    font-size: 1.1em;
    color: #262626;
    word-break: break-word;
}
.feed-card .post-img {
    margin-top: 8px;
    max-width: 100%;
    border-radius: 10px;
    object-fit: cover;
    box-shadow: 0 2px 12px #0001;
}
.feed-card .post-actions {
    margin-top: 11px;
    display: flex;
    gap: 17px;
}
.feed-card .post-actions button {
    background: #f0f2f5;
    color: #2563f8;
    border: none;
    border-radius: 8px;
    padding: 5px 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background .14s;
}
.feed-card .post-actions button:hover {
    background: #e8f0fe;
}
.feed-card .comments {
    margin: 6px 0 0 0;
    padding-left: 8px;
}
.feed-card form {
    display: flex;
    gap: 8px;
    margin-top: 5px;
}
.feed-card input[name="comment"] {
    flex: 1 1 auto;
    border-radius: 20px;
    border: 1px solid #e4e6eb;
    padding: 5px 12px;
    font-size: 1em;
    background: #f0f2f5;
}
.feed-card button[type="submit"] {
    border-radius: 17px;
    background: #2563f8;
    color: #fff;
    border: none;
    padding: 5px 13px;
    font-weight: 600;
    cursor: pointer;
}
::-webkit-scrollbar { width: 8px; background: #f2f2f2; }
::-webkit-scrollbar-thumb { background: #2563f8; border-radius:6px; }
@media (max-width: 1050px) {
    .wrapper { max-width: 100vw; }
    .left-col, .right-col { display: none; }
    .center-col { min-width: 0; }
}
    </style>
</head>
<body>
<div class="headerbar">
    
    
    <div class="actions">
        <img src="<?= htmlspecialchars($user['photo'] ?: 'default-user.png') ?>" alt="profile" class="profile">
        <span class="user"><?= htmlspecialchars($user['nom']) ?></span>
        <a href="users.php">Membres</a>
        <a href="logout.php" style="background: #fff;color:#2563f8;">Déconnexion</a>
    </div>
</div>
<div class="wrapper">
    <div class="left-col"></div>
    <div class="center-col">
        <!-- Stories -->
        <div class="stories-bar">
            <?php if ($user_story): ?>
                <div class="story" title="Voir ma story">
                    <img class="story-img" src="<?= htmlspecialchars($user_story['image'] ?: $user['photo'] ?: 'default-user.png') ?>" alt="<?= htmlspecialchars($user['nom']) ?>">
                    <span class="name">Votre story</span>
                </div>
            <?php else: ?>
                <div class="story create" title="Créer une story">
                    <img class="story-img" src="<?= htmlspecialchars($user['photo'] ?: 'default-user.png') ?>" alt="<?= htmlspecialchars($user['nom']) ?>">
                    <span class="plus">+</span>
                    <span class="name">Créer story</span>
                </div>
            <?php endif; ?>

            <?php foreach ($stories as $story): ?>
                <div class="story" title="<?= htmlspecialchars($story['nom'].' '.$story['prenom']) ?>">
                    <img class="story-img" src="<?= htmlspecialchars($story['image'] ?: $story['photo'] ?: 'default-user.png') ?>" alt="<?= htmlspecialchars($story['nom'].' '.$story['prenom']) ?>">
                    <span class="name"><?= htmlspecialchars($story['nom'].' '.$story['prenom']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- Post bar -->
        <div class="postbar">
            <form id="statusForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="me-row">
                    <img src="<?= htmlspecialchars($user['photo'] ?: 'default-user.png') ?>" class="profile">
                    <input type="text" name="content" placeholder="What's on your mind, <?= htmlspecialchars($user['nom']) ?>?" required>
                    <input type="file" name="media" accept="image/*,video/*" style="display:none" id="mediaInput">
                </div>
                <div class="actions">
                    <button type="button" onclick="document.getElementById('mediaInput').click()"><span style="color:#45bd62;font-size:1.21em;">&#128247;</span> Photo/vidéo</button>
                    <button type="submit" style="background:#2563f8;color:#fff;">Publier</button>
                </div>
            </form>
        </div>
        <!-- Feed -->
        <div class="feed" id="feed">
            <?php if (count($posts) == 0): ?>
                <div style="text-align:center;color:#666;padding:30px 0;">Aucun post pour l’instant.</div>
            <?php else: ?>
                <?php foreach ($posts as $p): ?>
                    <div class="feed-card" data-post="<?= $p['id'] ?>">
                        <div class="author-row">
                            <img src="<?= htmlspecialchars($p['photo'] ?: 'default-user.png') ?>" class="author-img" alt="">
                            <span class="author-name"><?= htmlspecialchars($p['nom'].' '.$p['prenom']) ?></span>
                            <span class="post-date"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></span>
                        </div>
                        <div class="content"><?= nl2br(htmlspecialchars($p['content'])) ?></div>
                        <?php if (!empty($p['media'])): ?>
                            <?php if (preg_match('/\.(mp4|webm)$/i', $p['media'])): ?>
                                <video class="post-img" controls src="<?= htmlspecialchars($p['media']) ?>"></video>
                            <?php else: ?>
                                <img class="post-img" src="<?= htmlspecialchars($p['media']) ?>">
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="post-actions">
                          <button onclick="likePost(<?= $p['id'] ?>)">👍 Like (<span id="like-count-<?= $p['id'] ?>">0</span>)</button>
                          <button onclick="showComments(<?= $p['id'] ?>)">💬 Comment</button>
                          <button onclick="sharePost(<?= $p['id'] ?>)">🔗 Share</button>
                        </div>
                        <div class="comments" id="comments-<?= $p['id'] ?>"></div>
                        <form onsubmit="return addComment(this, <?= $p['id'] ?>)">
                          <input type="text" name="comment" placeholder="Ecrire un commentaire...">
                          <button type="submit">Envoyer</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="right-col"></div>
</div>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;

document.getElementById('statusForm').onsubmit = function(e){
  e.preventDefault();
  let formData = new FormData(this);
  fetch('ajax_post.php', {
    method: 'POST',
    body: formData
  }).then(r=>r.json()).then(res=>{
    if(res.success) {
      this.reset();
      location.reload();
    } else {
      alert('Erreur : ' + (res.error||''));
    }
  });
};

function likePost(id) {
  fetch('ajax_like.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: 'post_id='+id+'&csrf_token='+encodeURIComponent(CSRF)
  }).then(r=>r.json()).then(res=>{
    if(res.success) {
      document.getElementById('like-count-'+id).textContent = res.likes;
    }
  });
}

function sharePost(id) {
  fetch('ajax_share.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: 'post_id='+id+'&csrf_token='+encodeURIComponent(CSRF)
  }).then(r=>r.json()).then(res=>{
    if(res.success) {
      alert('Post partagé sur votre fil !');
    }
  });
}

function addComment(form, postId) {
  let fd = new FormData(form);
  fd.append('post_id', postId);
  fd.append('csrf_token', CSRF);
  fetch('ajax_comment.php', {
    method:'POST', body: fd
  }).then(r=>r.json()).then(res=>{
    if(res.success) {
      form.reset();
      showComments(postId);
    }
  });
  return false;
}

function showComments(postId) {
  fetch('ajax_comment.php?post_id='+postId)
    .then(r=>r.text())
    .then(html=>{
      document.getElementById('comments-'+postId).innerHTML = html;
    });
}
</script>
</body>
</html>