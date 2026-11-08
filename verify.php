<?php
require 'security.php';
session_start();
require 'config.php';

$email   = $_GET['email'] ?? '';
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $error = "Requête invalide."; goto render; }

    $email = $_POST['email'] ?? '';
    $code  = $_POST['code'] ?? '';

    if (!$email || !$code) {
        $error = "Veuillez renseigner l'email et le code.";
        goto render;
    }

    $stmt = $pdo->prepare("SELECT code FROM users WHERE email=? AND (is_verified=0 OR is_verified=FALSE)");
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if ($row && hash_equals((string)$row['code'], (string)$code)) {
        $upd = $pdo->prepare("UPDATE users SET is_verified=1 WHERE email=?");
        $upd->execute([$email]);
        header("Location: login.php?verifie=ok");
        exit;
    } elseif ($row) {
        $error = "Code invalide.";
    } else {
        $error = "Compte déjà vérifié ou email introuvable.";
    }
}

render:
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Vérification du compte</title>
  <style>
    body { font-family: Arial, sans-serif; }
    .container { max-width:400px; margin:100px auto; padding:20px; border:1px solid #ccc; border-radius:8px; }
    input, button { width:100%; padding:10px; margin:8px 0; box-sizing:border-box; }
    .error { color: red; }
    .success { color: green; }
  </style>
</head>
<body>
<div class="container">
  <h2>Vérifiez votre compte</h2>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if ($success): ?>
    <p class="success"><?= htmlspecialchars($success) ?></p>
    <p><a href="login.php">Se connecter</a></p>
  <?php else: ?>
    <form method="POST" action="verify.php">
      <?= csrf_field() ?>
      <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>" />
      <input type="text" name="code" placeholder="Entrez le code reçu par email" required />
      <button type="submit">Valider</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
