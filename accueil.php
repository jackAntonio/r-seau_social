<?php
require 'security.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'config.php';

$id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM stories WHERE user_id=? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$id]);
$user_story = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT DISTINCT ON (s.user_id) s.*, u.nom, u.prenom, u.photo
     FROM stories s JOIN users u ON u.id = s.user_id
     WHERE s.user_id != ? AND s.expires_at > NOW()
     ORDER BY s.user_id, s.created_at DESC"
);
$stmt->execute([$id]);
$stories = $stmt->fetchAll();

// Le fil d'actualité est chargé via pagination infinie (api_posts.php + IntersectionObserver)
$posts = [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Accueil - Réseau Social</title>
    <meta name="viewport" content="width=1030">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2563f8">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
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
    position: relative;
}
.feed-card .post-actions button:hover {
    background: #e8f0fe;
}
/* Sélecteur de réactions */
.reaction-wrap { position: relative; display: inline-block; }
.reaction-picker {
    display: none;
    position: absolute;
    bottom: 38px;
    left: 0;
    background: #fff;
    border-radius: 30px;
    box-shadow: 0 4px 22px #0002;
    padding: 6px 10px;
    gap: 6px;
    flex-direction: row;
    z-index: 99;
    white-space: nowrap;
}
.reaction-wrap:hover .reaction-picker,
.reaction-wrap:focus-within .reaction-picker { display: flex; }
.reaction-picker span {
    font-size: 1.5em;
    cursor: pointer;
    transition: transform .15s;
    padding: 2px;
}
.reaction-picker span:hover { transform: scale(1.4); }
.like-btn { display: flex; align-items: center; gap: 5px; }
.like-btn.reacted { background: #e8f0fe; color: #1a56db; }
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
        <a href="edit_profile.php">✏️ Profil</a>
        <a href="totp_setup.php">🔐 2FA</a>
        <a href="admin.php">⚙️ Admin</a>
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
        <!-- Feed avec pagination infinie -->
        <div class="feed" id="feed">
            <div style="text-align:center;color:#666;padding:20px 0;" id="feed-loading">Chargement...</div>
        </div>
        <!-- Sentinelle IntersectionObserver -->
        <div id="feed-sentinel" style="height:30px;margin-bottom:20px;"></div>
        <div id="feed-end" style="text-align:center;color:#999;padding:20px;display:none;">
            Vous avez tout vu !
        </div>
    </div>
    <div class="right-col"></div>
</div>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;

// ===== PAGINATION INFINIE =====
let currentPage = 0;
let isLoading   = false;
let hasMore     = true;
const feed      = document.getElementById('feed');
const sentinel  = document.getElementById('feed-sentinel');
const feedEnd   = document.getElementById('feed-end');

function escHtml(s) {
  const d = document.createElement('div'); d.textContent = s; return d.innerHTML;
}

function renderPost(p) {
  const isVideo = p.media && /\.(mp4|webm)$/i.test(p.media);
  const mediaHtml = p.media
    ? (isVideo
      ? `<video class="post-img" controls src="${escHtml(p.media)}"></video>`
      : `<img class="post-img" src="${escHtml(p.media)}" alt="">`)
    : '';

  const card = document.createElement('div');
  card.className = 'feed-card';
  card.dataset.post = p.id;
  card.innerHTML = `
    <div class="author-row">
      <img src="${escHtml(p.photo||'default-user.png')}" class="author-img" alt="">
      <span class="author-name">${escHtml(p.nom+' '+p.prenom)}</span>
      <span class="post-date">${escHtml(p.created_at)}</span>
    </div>
    <div class="content">${escHtml(p.content).replace(/\n/g,'<br>')}</div>
    ${mediaHtml}
    <div class="post-actions">
      <div class="reaction-wrap">
        <button class="like-btn" id="like-btn-${p.id}" onclick="reactPost(${p.id},'like')">
          ${reactionIcon(p.my_reaction)} <span id="like-label-${p.id}">${reactionLabel(p)}</span>
        </button>
        <div class="reaction-picker">
          <span title="J'aime"    onclick="reactPost(${p.id},'like')">👍</span>
          <span title="J'adore"   onclick="reactPost(${p.id},'love')">❤️</span>
          <span title="Haha"      onclick="reactPost(${p.id},'haha')">😂</span>
          <span title="Wow"       onclick="reactPost(${p.id},'wow')">😮</span>
          <span title="Triste"    onclick="reactPost(${p.id},'sad')">😢</span>
          <span title="En colère" onclick="reactPost(${p.id},'angry')">😡</span>
          <span title="Je n'aime pas" onclick="reactPost(${p.id},'dislike')">👎</span>
        </div>
      </div>
      <button onclick="showComments(${p.id})">💬 Commenter</button>
      <button onclick="sharePost(${p.id})">🔗 Partager</button>
    </div>
    <div class="comments" id="comments-${p.id}"></div>
    <form onsubmit="return addComment(this,${p.id})">
      <input type="text" name="comment" placeholder="Écrire un commentaire...">
      <button type="submit">Envoyer</button>
    </form>`;
  return card;
}

function loadMore() {
  if (isLoading || !hasMore) return;
  isLoading = true;
  currentPage++;
  document.getElementById('feed-loading').style.display = 'block';

  fetch(`api_posts.php?page=${currentPage}`)
    .then(r => r.json())
    .then(data => {
      document.getElementById('feed-loading').style.display = 'none';
      data.posts.forEach(p => feed.insertBefore(renderPost(p), document.getElementById('feed-loading')));
      hasMore = data.has_more;
      if (!hasMore) { sentinel.style.display = 'none'; feedEnd.style.display = 'block'; }
      isLoading = false;
    })
    .catch(() => { isLoading = false; document.getElementById('feed-loading').style.display = 'none'; });
}

// IntersectionObserver pour déclencher le chargement au scroll
const observer = new IntersectionObserver(entries => {
  if (entries[0].isIntersecting) loadMore();
}, { rootMargin: '200px' });
observer.observe(sentinel);

// Chargement initial
loadMore();

// ===== ACTIONS =====
document.getElementById('statusForm').onsubmit = function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fetch('ajax_post.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        this.reset();
        // Recharger le fil depuis le début
        feed.innerHTML = '<div id="feed-loading" style="text-align:center;color:#666;padding:20px;">Chargement...</div>';
        currentPage = 0; hasMore = true;
        observer.observe(sentinel);
        loadMore();
      } else {
        alert('Erreur : ' + (res.error||''));
      }
    });
};

const EMOJI_MAP = {like:'👍',love:'❤️',haha:'😂',wow:'😮',sad:'😢',angry:'😡',dislike:'👎'};

function reactionIcon(r) { return r ? (EMOJI_MAP[r]||'👍') : '👍'; }

function reactionLabel(p) {
  const total = (p.r_like||0)+(p.r_love||0)+(p.r_haha||0)+(p.r_wow||0)+(p.r_sad||0)+(p.r_angry||0)+(p.r_dislike||0);
  if (!total) return 'Réagir';
  const parts = [];
  for (const [k,e] of Object.entries(EMOJI_MAP)) if (p['r_'+k]>0) parts.push(e+' '+p['r_'+k]);
  return parts.slice(0,3).join('  ');
}

function reactPost(id, reaction) {
  fetch('ajax_like.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`post_id=${id}&reaction=${reaction}&csrf_token=${encodeURIComponent(CSRF)}`
  }).then(r=>r.json()).then(res=>{
    if (!res.success) return;
    const btn = document.getElementById('like-btn-'+id);
    const lbl = document.getElementById('like-label-'+id);
    if (btn) btn.firstChild.textContent = reactionIcon(res.my_reaction);
    if (btn) btn.className = 'like-btn' + (res.my_reaction ? ' reacted' : '');
    // Reconstruire le label depuis les counts
    const p2 = {};
    for (const [k,v] of Object.entries(res.counts||{})) p2['r_'+k]=v;
    if (lbl) lbl.textContent = reactionLabel(p2);
  });
}

function sharePost(id) {
  fetch('ajax_share.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`post_id=${id}&csrf_token=${encodeURIComponent(CSRF)}`
  }).then(r=>r.json()).then(res=>{
    if(res.success) alert('Post partagé !');
  });
}

function addComment(form, postId) {
  const fd = new FormData(form);
  fd.append('post_id', postId);
  fd.append('csrf_token', CSRF);
  fetch('ajax_comment.php', { method:'POST', body:fd })
    .then(r=>r.json()).then(res=>{
      if(res.success) { form.reset(); showComments(postId); }
    });
  return false;
}

function showComments(postId) {
  fetch('ajax_comment.php?post_id='+postId)
    .then(r=>r.text())
    .then(html=>{ document.getElementById('comments-'+postId).innerHTML = html; });
}

// ===== PWA Service Worker =====
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(()=>{});
}

// ===== SSE NOTIFICATIONS =====
(function() {
  let lastNotifId = 0;
  function connectNotifSSE() {
    const es = new EventSource(`sse_notifications.php?last_id=${lastNotifId}`);
    es.onmessage = e => {
      const data = JSON.parse(e.data);
      if (data.close) { es.close(); setTimeout(connectNotifSSE, 1000); return; }
      lastNotifId = Math.max(lastNotifId, data.id);
      // Afficher un toast de notification
      showNotifToast(data.content, data.link);
    };
    es.onerror = () => { es.close(); setTimeout(connectNotifSSE, 5000); };
  }
  connectNotifSSE();

  function showNotifToast(msg, link) {
    const toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1a3e72;color:#fff;padding:12px 20px;border-radius:12px;box-shadow:0 4px 20px #0003;z-index:9999;max-width:280px;font-size:0.97em;cursor:pointer;animation:fadeInUp .3s;';
    toast.innerHTML = `🔔 ${msg.replace(/</g,'&lt;')}`;
    if (link) toast.onclick = () => window.location.href = link;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
  }
})();
</script>
<style>
@keyframes fadeInUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:none; } }
</style>
</body>
</html>