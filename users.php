<?php
require 'security.php';
session_start();
require 'config.php';
require 'functions.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = intval($_SESSION['user_id']);

// Recherche
$search = trim($_GET['q'] ?? '');
$search_param = '%' . $search . '%';

// Utilisateurs avec statut de relation
$stmt = $pdo->prepare("
    SELECT u.id, u.nom, u.prenom, u.photo, u.bio,
           f.status AS friend_status, f.from_user AS req_from
    FROM users u
    LEFT JOIN friends f ON (
        (f.from_user = ? AND f.to_user = u.id) OR
        (f.to_user = ? AND f.from_user = u.id)
    )
    WHERE u.id != ?
      AND (? = '' OR LOWER(u.nom) LIKE LOWER(?) OR LOWER(u.prenom) LIKE LOWER(?) OR LOWER(CONCAT(u.nom,' ',u.prenom)) LIKE LOWER(?))
    ORDER BY u.nom ASC
");
$stmt->execute([$user_id, $user_id, $user_id, $search, $search_param, $search_param, $search_param]);
$users = $stmt->fetchAll();

// Demandes reçues en attente
$pending = $pdo->prepare("
    SELECT u.id, u.nom, u.prenom, u.photo FROM friends f
    JOIN users u ON u.id = f.from_user
    WHERE f.to_user=? AND f.status='pending'
");
$pending->execute([$user_id]);
$requests = $pending->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Membres</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    * { box-sizing: border-box; }
    body { background: linear-gradient(110deg,#e6ecf3 60%,#eaf6ff 100%); font-family:'Segoe UI',Arial,sans-serif; margin:0; color:#243045; }
    .topbar { background:#fff; box-shadow:0 2px 12px #2e89ff22; padding:14px 20px; position:sticky; top:0; z-index:2; display:flex; align-items:center; gap:12px; }
    .top-title { font-size:1.3em; font-weight:700; color:#2563f8; }
    .search-bar { flex:1; max-width:340px; display:flex; gap:8px; }
    .search-bar input { flex:1; border:1.5px solid #d0dce8; border-radius:20px; padding:7px 16px; font-size:1em; outline:none; }
    .search-bar input:focus { border-color:#2563f8; }
    .container { max-width:600px; margin:28px auto; padding:0 12px; }
    .section-title { font-weight:700; color:#1a3e72; margin:18px 0 10px; font-size:1.05em; }
    .user-card { display:flex; align-items:center; gap:14px; padding:12px 14px; background:#fff; border-radius:14px; box-shadow:0 2px 14px #2e89ff0d; border:1px solid #eaf6ff; margin-bottom:12px; transition:box-shadow .18s; }
    .user-card:hover { box-shadow:0 4px 20px #2e89ff22; }
    .profile-pic { width:54px; height:54px; border-radius:50%; object-fit:cover; border:2px solid #e3ecf8; }
    .user-info { flex:1; }
    .user-info b { font-size:1.05em; color:#1b2e4b; }
    .user-info small { color:#8899aa; font-size:.88em; display:block; }
    .actions { display:flex; gap:7px; flex-wrap:wrap; justify-content:flex-end; }
    .btn { padding:6px 14px; border-radius:9px; font-weight:600; font-size:.93em; border:none; cursor:pointer; text-decoration:none; display:inline-block; }
    .btn-primary { background:linear-gradient(90deg,#2e89ff,#2563f8); color:#fff; }
    .btn-outline { background:#fff; color:#2563f8; border:1.5px solid #2563f8; }
    .btn-success { background:#27ae60; color:#fff; }
    .btn-danger  { background:#e74c3c; color:#fff; }
    .btn-gray    { background:#e8ecf1; color:#5c7896; }
    .badge { background:#e8f0fe; color:#1a3e72; border-radius:20px; padding:2px 10px; font-size:.82em; font-weight:600; }
    .badge-pending { background:#fff3cd; color:#856404; }
    .empty { text-align:center; color:#8899aa; padding:32px 0; }
    a.btn { line-height:1.5; }
  </style>
</head>
<body>
<div class="topbar">
  <span class="top-title">Membres</span>
  <form class="search-bar" method="get" action="users.php">
    <input type="text" name="q" placeholder="Rechercher un membre..." value="<?= htmlspecialchars($search) ?>" autofocus>
    <button type="submit" class="btn btn-primary">🔍</button>
  </form>
  <a href="accueil.php" class="btn btn-outline" style="white-space:nowrap;">← Accueil</a>
</div>

<div class="container">

  <?php if (!empty($requests)): ?>
  <div class="section-title">📨 Demandes d'ami reçues (<?= count($requests) ?>)</div>
  <?php foreach ($requests as $r): ?>
    <div class="user-card">
      <img src="<?= htmlspecialchars($r['photo'] ?: 'default-user.png') ?>" class="profile-pic" alt="">
      <div class="user-info">
        <b><?= htmlspecialchars($r['nom'].' '.$r['prenom']) ?></b>
        <small>veut être votre ami</small>
      </div>
      <div class="actions">
        <form method="post" action="friend_request.php" style="margin:0">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="accept">
          <input type="hidden" name="from_user" value="<?= intval($r['id']) ?>">
          <button class="btn btn-success">✓ Accepter</button>
        </form>
        <form method="post" action="friend_request.php" style="margin:0">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reject">
          <input type="hidden" name="from_user" value="<?= intval($r['id']) ?>">
          <button class="btn btn-danger">✕</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="section-title">
    👥 <?= $search ? 'Résultats pour « '.htmlspecialchars($search).' »' : 'Tous les membres' ?>
    <?php if (!$search): ?><span class="badge"><?= count($users) ?></span><?php endif; ?>
  </div>

  <?php if (empty($users)): ?>
    <div class="empty">Aucun membre trouvé.</div>
  <?php endif; ?>

  <?php foreach ($users as $u): ?>
    <?php
      $uid    = intval($u['id']);
      $status = $u['friend_status'];
      $req_from = intval($u['req_from'] ?? 0);
    ?>
    <div class="user-card">
      <img src="<?= htmlspecialchars($u['photo'] ?: 'default-user.png') ?>" class="profile-pic" alt="">
      <div class="user-info">
        <b><?= htmlspecialchars($u['nom'].' '.$u['prenom']) ?></b>
        <small><?= $u['bio'] ? htmlspecialchars(mb_substr($u['bio'],0,60)) : '•••' ?></small>
      </div>
      <div class="actions">
        <?php if ($status === 'accepted'): ?>
          <a href="chat.php?with=<?= $uid ?>" class="btn btn-primary">💬 Discuter</a>
        <?php elseif ($status === 'pending' && $req_from === $user_id): ?>
          <span class="btn btn-gray badge-pending">⏳ En attente</span>
        <?php elseif ($status === 'pending' && $req_from !== $user_id): ?>
          <form method="post" action="friend_request.php" style="margin:0">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="accept">
            <input type="hidden" name="from_user" value="<?= $uid ?>">
            <button class="btn btn-success">✓ Accepter</button>
          </form>
        <?php else: ?>
          <form method="post" action="friend_request.php" style="margin:0">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="request">
            <input type="hidden" name="to_user" value="<?= $uid ?>">
            <button class="btn btn-primary">+ Ajouter</button>
          </form>
        <?php endif; ?>
        <a href="profile.php?id=<?= $uid ?>" class="btn btn-outline">Profil</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
</body>
</html>
