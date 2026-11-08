<?php
require 'security.php';
session_start();
require 'config.php';
require 'functions.php';

$me = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { echo "Profil introuvable."; exit; }

$stmt = $pdo->prepare("SELECT id, nom, prenom, photo, sexe, date_naissance, contact, email, bio FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { echo "Utilisateur inconnu."; exit; }

$friend = ($me && $id) ? is_friend($pdo, $me, $id) : false;
$show_email = ($me === $id) || $friend;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Profil de <?= htmlspecialchars($user['nom']) ?></title>
  <link rel="stylesheet" href="profile-card.css">
</head>
<body>
  <div style="text-align:center;margin-top:12px;"><img src="site-logo.png" alt="Logo" style="max-width:90px;"></div>
  <div class="container d-flex justify-content-center">
    <div class="card p-3 py-4">
      <div class="text-center">
        <img src="<?= htmlspecialchars($user['photo'] ?: 'default-user.png') ?>" class="profile-pic" alt="">
        <h3 class="mt-2"><?= htmlspecialchars($user['nom'] . ' ' . $user['prenom']) ?></h3>
        <?php if ($show_email): ?>
          <span class="mt-1 clearfix"><?= htmlspecialchars($user['email']) ?></span>
        <?php endif; ?>
        <?php if (!empty($user['bio'])): ?>
          <p class="bio" style="font-style:italic;color:#555;margin:12px auto;max-width:380px;line-height:1.4;"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
        <?php endif; ?>
        <div class="row mt-3 mb-3" style="display:flex;justify-content:center;">
          <div class="col-md-4"><h5>Sexe</h5><span class="num"><?= htmlspecialchars($user['sexe']) ?></span></div>
          <div class="col-md-4"><h5>Naissance</h5><span class="num"><?= htmlspecialchars($user['date_naissance']) ?></span></div>
          <?php if ($show_email): ?>
            <div class="col-md-4"><h5>Contact</h5><span class="num"><?= htmlspecialchars($user['contact']) ?></span></div>
          <?php endif; ?>
        </div>
        <hr class="line">
        <?php if ($me && $me === $id): ?>
          <a href="edit_profile.php" class="profile_button" style="background:#2563f8;">✏️ Modifier mon profil</a>
        <?php elseif ($me && $me !== $id): ?>
          <?php if (!$friend): ?>
            <form method="post" action="friend_request.php" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="to_user" value="<?= $id ?>">
              <button type="submit" class="profile_button">Demander en ami</button>
            </form>
          <?php else: ?>
            <a href="chat.php?with=<?= $id ?>" class="profile_button">Discuter 💬</a>
          <?php endif; ?>
        <?php elseif (!$me): ?>
          <p><a href="login.php">Connectez-vous pour interagir</a></p>
        <?php endif; ?>
        <br><br>
        <a href="<?= $me ? 'users.php' : 'login.php' ?>" class="profile_button">Retour</a>
      </div>
    </div>
  </div>
</body>
</html>
