<?php
require 'security.php';
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = intval($_SESSION['user_id']);

// Marquer toutes les notifications comme lues (requête préparée)
$stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

// Récupérer les notifications (requête préparée)
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 40");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Notifications</title>
   <style>
:root {
  --bg: #f7fafd; --card: #fff; --primary: #1a3e72; --accent: #00ffe7;
  --text: #18223b; --border: #dee4ef; --shadow: 0 2px 18px #0002; --radius: 16px;
}
:root[data-theme="deep"] {
  --bg: #090f1b; --card: rgba(16,18,40,0.8); --primary: #00ffe7; --accent: #8be9fd;
  --text: #e7f8fa; --border: #293a51; --shadow: 0 2px 32px #00ffe733;
}
html, body { background: var(--bg); color: var(--text); font-family: 'Inter','Segoe UI',Arial,sans-serif; min-height:100vh; margin:0; }
.container { background:var(--card); border-radius:var(--radius); box-shadow:var(--shadow); padding:2.2rem 1.2rem; margin:2rem auto; max-width:480px; border:1.5px solid var(--border); }
h2 { color:var(--primary); font-weight:800; font-size:1.35em; margin:12px 0; }
a { color:var(--primary); text-decoration:none; font-weight:600; }
button, .profile_button { background:var(--primary); color:#fff; border:none; border-radius:12px; padding:0.7em 1.5em; font-size:1.08em; font-weight:700; cursor:pointer; margin:8px 3px 0 3px; transition:background 0.22s; }
    </style>
</head>
<body>
    <div style="text-align:center;margin-top:12px;">
      <img src="site-logo.png" alt="Logo du site" style="max-width:90px;">
    </div>
    <div class="container">
        <h2>Vos notifications</h2>
        <?php foreach($notifs as $n): ?>
            <div style="margin-bottom:16px;padding:9px;background:#f7f7f7;border-radius:6px;">
                <?= htmlspecialchars($n['content']) ?>
                <?php if(!empty($n['link'])): ?>
                    <a href="<?= htmlspecialchars($n['link']) ?>">Voir</a>
                <?php endif; ?>
                <span style="float:right;color:#999;"><?= htmlspecialchars($n['created_at']) ?></span>
            </div>
        <?php endforeach; ?>
        <?php if (empty($notifs)): ?>
            <p style="color:#888;text-align:center;">Aucune notification.</p>
        <?php endif; ?>
        <a href="feed.php" class="profile_button">Retour au fil</a>
    </div>
</body>
</html>
