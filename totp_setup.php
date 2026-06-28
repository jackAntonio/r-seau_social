<?php
require 'security.php';
session_start();
require 'config.php';
require 'audit.php';
require 'totp.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = intval($_SESSION['user_id']);

$stmt = $conn->prepare("SELECT email, nom, totp_enabled FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($email, $nom, $totp_enabled);
$stmt->fetch();
$stmt->close();

$message = '';
$error   = '';

// Désactiver le 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disable_2fa'])) {
    if (!csrf_verify()) { $error = "Requête invalide."; goto render; }
    $upd = $conn->prepare("UPDATE users SET totp_enabled=0, totp_secret=NULL WHERE id=?");
    $upd->bind_param("i", $user_id);
    $upd->execute();
    $upd->close();
    audit_log($conn, '2fa_disabled', $user_id, 'user', $user_id);
    $totp_enabled = 0;
    $message = "2FA désactivé.";
    goto render;
}

// Activer le 2FA : étape 1 — générer le secret et afficher le QR code
if (!isset($_SESSION['totp_pending_secret'])) {
    $_SESSION['totp_pending_secret'] = TOTP::generate_secret();
}
$pending_secret = $_SESSION['totp_pending_secret'];
$label      = $email;
$otp_url    = TOTP::otpauth_url($pending_secret, $label);
$qr_url     = TOTP::qr_url($otp_url);

// Valider le code entré
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_code'])) {
    if (!csrf_verify()) { $error = "Requête invalide."; goto render; }
    $code = preg_replace('/\D/', '', $_POST['totp_code']);
    if (TOTP::verify($pending_secret, $code)) {
        $upd = $conn->prepare("UPDATE users SET totp_secret=?, totp_enabled=1 WHERE id=?");
        $upd->bind_param("si", $pending_secret, $user_id);
        $upd->execute();
        $upd->close();
        unset($_SESSION['totp_pending_secret']);
        audit_log($conn, '2fa_enabled', $user_id, 'user', $user_id);
        $totp_enabled = 1;
        $message = "2FA activé avec succès !";
    } else {
        $error = "Code invalide. Vérifiez votre application.";
    }
}

render:
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Configuration 2FA</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body { font-family: Arial, sans-serif; background: #f0f2f5; }
    .container { max-width: 460px; margin: 60px auto; background: #fff; border-radius: 14px; padding: 32px; box-shadow: 0 2px 18px #0002; }
    h2 { color: #1a3e72; text-align: center; }
    .qr-box { text-align: center; margin: 20px 0; }
    .secret-box { background: #f0f2f5; border-radius: 8px; padding: 10px; text-align: center; font-family: monospace; font-size: 1.1em; letter-spacing: 2px; margin: 10px 0; }
    input[type="text"] { width: 100%; padding: 12px; font-size: 1.2em; text-align: center; letter-spacing: 6px; border: 1.5px solid #ccc; border-radius: 8px; box-sizing: border-box; margin: 10px 0; }
    button { width: 100%; padding: 12px; background: #1a3e72; color: #fff; border: none; border-radius: 8px; font-size: 1em; font-weight: 700; cursor: pointer; margin-top: 8px; }
    .danger { background: #c0392b; }
    .msg { color: green; text-align: center; margin: 10px 0; font-weight: 700; }
    .err { color: red; text-align: center; margin: 10px 0; }
    ol { line-height: 2; }
  </style>
</head>
<body>
<div class="container">
  <h2>🔐 Authentification à 2 facteurs</h2>
  <?php if ($message): ?><p class="msg"><?= htmlspecialchars($message) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

  <?php if ($totp_enabled): ?>
    <p style="text-align:center;color:green;font-weight:700;">✅ 2FA activé sur votre compte</p>
    <form method="post">
      <?= csrf_field() ?>
      <button type="submit" name="disable_2fa" class="danger">Désactiver le 2FA</button>
    </form>
  <?php else: ?>
    <ol>
      <li>Installez <strong>Google Authenticator</strong> ou <strong>Authy</strong></li>
      <li>Scannez le QR code ci-dessous</li>
      <li>Entrez le code à 6 chiffres pour confirmer</li>
    </ol>
    <div class="qr-box">
      <img src="<?= htmlspecialchars($qr_url) ?>" alt="QR Code 2FA" width="200" height="200">
    </div>
    <p>Ou saisissez la clé manuellement :</p>
    <div class="secret-box"><?= htmlspecialchars($pending_secret) ?></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="text" name="totp_code" placeholder="000000" maxlength="6" autocomplete="one-time-code" pattern="\d{6}" required>
      <button type="submit">Activer le 2FA</button>
    </form>
  <?php endif; ?>
  <br>
  <a href="accueil.php" style="display:block;text-align:center;">← Retour à l'accueil</a>
</div>
</body>
</html>
