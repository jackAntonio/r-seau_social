<?php
require 'security.php';
session_start();
require 'config.php';
require 'audit.php';
require 'totp.php';

// Uniquement accessible si login réussi mais 2FA en attente
if (!isset($_SESSION['user_id'], $_SESSION['totp_pending'])) {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = "Requête invalide.";
    } else {
        $code = preg_replace('/\D/', '', $_POST['code'] ?? '');

        $stmt = $conn->prepare("SELECT totp_secret FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($secret);
        $stmt->fetch();
        $stmt->close();

        if ($secret && TOTP::verify($secret, $code)) {
            unset($_SESSION['totp_pending']);
            audit_log($conn, 'login_2fa_success', $user_id, 'user', $user_id);
            header("Location: accueil.php");
            exit;
        } else {
            record_attempt($conn, $_SERVER['REMOTE_ADDR'] ?? '', '');
            audit_log($conn, 'login_2fa_failed', $user_id, 'user', $user_id);
            $error = "Code invalide.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Vérification 2FA</title>
  <style>
    body { background: #777; display:flex; justify-content:center; align-items:center; height:100vh; font-family:Arial,sans-serif; }
    .box { background:#fff; padding:32px; border-radius:14px; box-shadow:0 0 18px #0003; width:300px; text-align:center; }
    h2 { color:#1a3e72; }
    input { width:100%; padding:14px; font-size:1.4em; text-align:center; letter-spacing:8px; border:1.5px solid #ccc; border-radius:8px; box-sizing:border-box; margin:14px 0; }
    button { width:100%; padding:12px; background:#1a3e72; color:#fff; border:none; border-radius:8px; font-size:1em; font-weight:700; cursor:pointer; }
    .err { color:red; margin-bottom:12px; }
  </style>
</head>
<body>
<div class="box">
  <h2>🔐 Vérification 2FA</h2>
  <p>Entrez le code de votre application d'authentification</p>
  <?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="text" name="code" placeholder="000 000" maxlength="6" autocomplete="one-time-code" pattern="\d{6}" autofocus required>
    <button type="submit">Valider</button>
  </form>
  <br>
  <a href="logout.php" style="color:#888;font-size:0.95em;">Annuler</a>
</div>
</body>
</html>
