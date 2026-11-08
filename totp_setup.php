<?php
require 'security.php';
session_start();
require 'config.php';
require 'audit.php';
require 'totp.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = intval($_SESSION['user_id']);

$stmt = $pdo->prepare("SELECT email, nom, totp_enabled FROM users WHERE id=?");
$stmt->execute([$user_id]);
$row  = $stmt->fetch();
$email        = $row['email'];
$totp_enabled = (bool)$row['totp_enabled'];
$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disable_2fa'])) {
    if (!csrf_verify()) { $error = "Requête invalide."; goto render; }
    $pdo->prepare("UPDATE users SET totp_enabled=0, totp_secret=NULL WHERE id=?")->execute([$user_id]);
    audit_log($pdo, '2fa_disabled', $user_id, 'user', $user_id);
    $totp_enabled = false; $message = "2FA désactivé."; goto render;
}

if (!isset($_SESSION['totp_pending_secret'])) {
    $_SESSION['totp_pending_secret'] = TOTP::generate_secret();
}
$pending_secret = $_SESSION['totp_pending_secret'];
$otp_url = TOTP::otpauth_url($pending_secret, $email);
$qr_url  = TOTP::qr_url($otp_url);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_code'])) {
    if (!csrf_verify()) { $error = "Requête invalide."; goto render; }
    $code = preg_replace('/\D/', '', $_POST['totp_code'] ?? '');
    if (TOTP::verify($pending_secret, $code)) {
        $pdo->prepare("UPDATE users SET totp_secret=?, totp_enabled=1 WHERE id=?")
            ->execute([$pending_secret, $user_id]);
        unset($_SESSION['totp_pending_secret']);
        audit_log($pdo, '2fa_enabled', $user_id, 'user', $user_id);
        $totp_enabled = true; $message = "2FA activé !";
    } else { $error = "Code invalide."; }
}

render:
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>2FA</title>
<style>body{font-family:Arial,sans-serif;background:#f0f2f5;}
.box{max-width:440px;margin:60px auto;background:#fff;border-radius:14px;padding:32px;box-shadow:0 2px 18px #0002;}
h2{color:#1a3e72;text-align:center;}
.secret{background:#f0f2f5;border-radius:8px;padding:10px;text-align:center;font-family:monospace;font-size:1.1em;letter-spacing:2px;margin:10px 0;}
input[type=text]{width:100%;padding:14px;font-size:1.3em;text-align:center;letter-spacing:8px;border:1.5px solid #ccc;border-radius:8px;box-sizing:border-box;margin:10px 0;}
button{width:100%;padding:12px;background:#1a3e72;color:#fff;border:none;border-radius:8px;font-size:1em;font-weight:700;cursor:pointer;margin-top:8px;}
.danger{background:#c0392b;}
.msg{color:green;text-align:center;font-weight:700;}
.err{color:red;text-align:center;}
</style>
</head>
<body>
<div class="box">
  <h2>🔐 Authentification 2FA</h2>
  <?php if ($message): ?><p class="msg"><?= htmlspecialchars($message) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if ($totp_enabled): ?>
    <p style="text-align:center;color:green;font-weight:700;">✅ 2FA activé</p>
    <form method="post"><?= csrf_field() ?><button type="submit" name="disable_2fa" class="danger">Désactiver le 2FA</button></form>
  <?php else: ?>
    <ol><li>Installez <b>Google Authenticator</b> ou <b>Authy</b></li><li>Scannez le QR code</li><li>Entrez le code pour confirmer</li></ol>
    <div style="text-align:center;margin:20px 0;">
      <canvas id="qr-canvas"></canvas>
      <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
      <script>QRCode.toCanvas(document.getElementById('qr-canvas'), <?= json_encode($otp_url) ?>, {width:200}, function(e){if(e)console.error(e);});</script>
    </div>
    <p>Clé manuelle :</p><div class="secret"><?= htmlspecialchars($pending_secret) ?></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="text" name="totp_code" placeholder="000000" maxlength="6" pattern="\d{6}" required>
      <button type="submit">Activer</button>
    </form>
  <?php endif; ?>
  <br><a href="accueil.php" style="display:block;text-align:center;">← Retour</a>
</div>
</body>
</html>
