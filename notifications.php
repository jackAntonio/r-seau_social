<?php
require 'security.php';
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = intval($_SESSION['user_id']);

$pdo->prepare("UPDATE notifications SET is_read=TRUE WHERE user_id=?")->execute([$user_id]);
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 40");
$stmt->execute([$user_id]);
$notifs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Notifications</title>
  <style>
    body { font-family:Arial,sans-serif; background:#f7fafd; }
    .container { max-width:480px; margin:40px auto; background:#fff; border-radius:14px; padding:24px; box-shadow:0 2px 18px #0002; }
    h2 { color:#1a3e72; }
    .notif { margin-bottom:14px; padding:10px; background:#f0f4ff; border-radius:8px; }
    a { color:#1a3e72; font-weight:600; text-decoration:none; }
    .profile_button { display:inline-block; background:#1a3e72; color:#fff; border-radius:10px; padding:8px 20px; margin-top:12px; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Vos notifications</h2>
    <?php foreach($notifs as $n): ?>
      <div class="notif">
        <?= htmlspecialchars($n['content']) ?>
        <?php if(!empty($n['link'])): ?>
          <a href="<?= htmlspecialchars($n['link']) ?>"> Voir</a>
        <?php endif; ?>
        <span style="float:right;color:#999;font-size:0.88em;"><?= htmlspecialchars($n['created_at']) ?></span>
      </div>
    <?php endforeach; ?>
    <?php if (empty($notifs)): ?><p style="color:#888;text-align:center;">Aucune notification.</p><?php endif; ?>
    <a href="accueil.php" class="profile_button">Retour</a>
  </div>
</body>
</html>
