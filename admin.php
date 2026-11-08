<?php
require 'security.php';
session_start();
require 'config.php';
require 'roles.php';
require 'storage.php';
require 'audit.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$admin_id = intval($_SESSION['user_id']);
require_role($pdo, $admin_id, 'admin');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) { die("Requête invalide."); }

if (isset($_POST['set_role'])) {
    set_user_role($pdo, intval($_POST['target_id']), $_POST['new_role'] ?? 'user', $admin_id);
    audit_log($pdo, 'set_role', $admin_id, 'user', intval($_POST['target_id']));
    $message = "Rôle mis à jour.";
}

if (isset($_POST['delete_post'])) {
    $pid = intval($_POST['delete_post']);
    $row = $pdo->prepare("SELECT media FROM posts WHERE id=?"); $row->execute([$pid]); $post = $row->fetch();
    if ($post) { Storage::delete($post['media'] ?? null); }
    $pdo->prepare("DELETE FROM posts WHERE id=?")->execute([$pid]);
    audit_log($pdo, 'admin_delete_post', $admin_id, 'post', $pid);
    $message = "Post supprimé.";
}

if (isset($_POST['ban_user'])) {
    $uid = intval($_POST['ban_user']);
    $pdo->prepare("UPDATE users SET is_verified=0 WHERE id=?")->execute([$uid]);
    audit_log($pdo, 'admin_ban_user', $admin_id, 'user', $uid);
    $message = "Utilisateur banni.";
}

$users = $pdo->query("SELECT id, nom, prenom, email, role, is_verified FROM users ORDER BY id DESC")->fetchAll();
$posts = $pdo->query("SELECT p.id, p.content, p.created_at, u.nom FROM posts p JOIN users u ON p.user_id=u.id ORDER BY p.created_at DESC LIMIT 20")->fetchAll();
$logs  = $pdo->query("SELECT al.*, u.nom FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Administration</title>
<style>
body{font-family:Arial,sans-serif;background:#f0f2f5;margin:0;}
.nav{background:#1a3e72;color:#fff;padding:14px 24px;display:flex;gap:20px;align-items:center;}
.nav h1{margin:0;font-size:1.3em;} .nav a{color:#90caf9;text-decoration:none;}
.panel{max-width:1100px;margin:30px auto;display:flex;gap:20px;flex-wrap:wrap;}
.card{background:#fff;border-radius:12px;box-shadow:0 2px 12px #0001;padding:20px;flex:1 1 320px;}
h2{color:#1a3e72;font-size:1.1em;border-bottom:2px solid #e8f0fe;padding-bottom:8px;}
table{width:100%;border-collapse:collapse;font-size:.95em;}
th{background:#e8f0fe;color:#1a3e72;padding:7px;text-align:left;}
td{padding:6px 7px;border-bottom:1px solid #f0f2f5;}
select,button{padding:4px 10px;border-radius:6px;border:1px solid #ccc;cursor:pointer;}
button.del{background:#c0392b;color:#fff;border:none;}
button.ban{background:#e67e22;color:#fff;border:none;}
.msg{background:#d4edda;color:#155724;padding:10px;border-radius:8px;margin-bottom:16px;width:100%;}
.badge{padding:2px 8px;border-radius:10px;font-size:.85em;font-weight:700;}
.badge-admin{background:#c0392b;color:#fff;} .badge-moderateur{background:#e67e22;color:#fff;} .badge-user{background:#e8f0fe;color:#1a3e72;}
</style>
</head>
<body>
<div class="nav"><h1>⚙️ Administration</h1><a href="accueil.php">← Accueil</a></div>
<div class="panel">
  <?php if ($message): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <div class="card" style="flex:2 1 600px;">
    <h2>👥 Utilisateurs (<?= count($users) ?>)</h2>
    <table><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Actions</th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= htmlspecialchars($u['nom'].' '.$u['prenom']) ?></td>
      <td><?= htmlspecialchars($u['email']) ?></td>
      <td><span class="badge badge-<?= htmlspecialchars($u['role']??'user') ?>"><?= htmlspecialchars($u['role']??'user') ?></span></td>
      <td><?= $u['is_verified'] ? '✅' : '🚫' ?></td>
      <td>
        <form method="post" style="display:inline;"><?= csrf_field() ?>
          <input type="hidden" name="target_id" value="<?= intval($u['id']) ?>">
          <select name="new_role">
            <?php foreach(['user','moderateur','admin'] as $r): ?>
              <option value="<?= $r ?>" <?= ($u['role']??'user')===$r?'selected':'' ?>><?= $r ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" name="set_role">OK</button>
        </form>
        <?php if (intval($u['id']) !== $admin_id): ?>
          <form method="post" style="display:inline;" onsubmit="return confirm('Bannir ?')"><?= csrf_field() ?>
            <button type="submit" name="ban_user" value="<?= intval($u['id']) ?>" class="ban">Bannir</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </table>
  </div>
  <div class="card">
    <h2>📝 Posts récents</h2>
    <table><tr><th>Auteur</th><th>Contenu</th><th>Date</th><th></th></tr>
    <?php foreach ($posts as $p): ?>
    <tr>
      <td><?= htmlspecialchars($p['nom']) ?></td>
      <td><?= htmlspecialchars(mb_substr($p['content'],0,40)) ?>…</td>
      <td style="font-size:.85em;"><?= htmlspecialchars($p['created_at']) ?></td>
      <td><form method="post" onsubmit="return confirm('Supprimer ?')"><?= csrf_field() ?>
        <button type="submit" name="delete_post" value="<?= intval($p['id']) ?>" class="del">🗑</button>
      </form></td>
    </tr>
    <?php endforeach; ?>
    </table>
  </div>
  <div class="card" style="flex:3 1 100%;">
    <h2>📋 Logs d'audit</h2>
    <table><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Cible</th><th>IP</th></tr>
    <?php foreach ($logs as $l): ?>
    <tr>
      <td style="font-size:.85em;"><?= htmlspecialchars($l['created_at']) ?></td>
      <td><?= htmlspecialchars($l['nom']??'-') ?></td>
      <td><?= htmlspecialchars($l['action']) ?></td>
      <td><?= htmlspecialchars(($l['target_type']??'').' #'.($l['target_id']??'')) ?></td>
      <td style="font-size:.85em;"><?= htmlspecialchars($l['ip']??'') ?></td>
    </tr>
    <?php endforeach; ?>
    </table>
  </div>
</div>
</body>
</html>
