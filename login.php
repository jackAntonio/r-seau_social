<?php
require 'security.php';
session_start();
require 'config.php';
require 'audit.php';

if (isset($_SESSION['user_id'])) { header("Location: accueil.php"); exit; }

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) {
        $error = "Requête invalide.";
    } else {
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $email = trim($_POST['email'] ?? '');

        if (is_rate_limited($pdo, $ip)) {
            $error = "Trop de tentatives. Réessayez dans une heure.";
        } else {
            $password = $_POST['password'] ?? '';
            $stmt = $pdo->prepare("SELECT id, password, is_verified, nom FROM users WHERE email=?");
            $stmt->execute([$email]);
            $row = $stmt->fetch();

            if ($row && $row['is_verified'] && password_verify($password, $row['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['nom']     = $row['nom'];

                $s2 = $pdo->prepare("SELECT totp_enabled FROM users WHERE id=?");
                $s2->execute([$row['id']]);
                $totp_enabled = (bool)$s2->fetchColumn();

                if ($totp_enabled) {
                    $_SESSION['totp_pending'] = true;
                    audit_log($pdo, 'login_2fa_required', $row['id'], 'user', $row['id']);
                    header("Location: totp_verify.php");
                } else {
                    audit_log($pdo, 'login_success', $row['id'], 'user', $row['id']);
                    header("Location: accueil.php");
                }
                exit;
            } else {
                record_attempt($pdo, $ip, $email);
                audit_log($pdo, 'login_failed');
                $error = "Email ou mot de passe incorrect.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Connexion</title>
  <style>
    body { background-color:#777; display:flex; justify-content:center; align-items:center; height:100vh; font-family:Arial,sans-serif; }
    .container { background:#fff; padding:30px; border-radius:15px; box-shadow:0 0 15px rgba(0,0,0,0.3); width:320px; }
    h2 { margin-bottom:20px; color:#333; text-align:center; }
    input[type="email"], input[type="password"] { width:100%; padding:12px; margin-bottom:15px; border:1px solid #ccc; border-radius:8px; font-size:16px; box-sizing:border-box; }
    button { width:100%; padding:12px; background:green; color:#fff; font-size:16px; border:none; border-radius:8px; cursor:pointer; }
    .error { color:red; margin-bottom:15px; text-align:center; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Connexion</h2>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" action="login.php">
      <?= csrf_field() ?>
      <input type="email" name="email" placeholder="Votre email" required />
      <input type="password" name="password" placeholder="Mot de passe" required />
      <button type="submit">Se connecter</button>
    </form>
    <p style="text-align:center;margin-top:12px;"><a href="register.php">Créer un compte</a></p>
  </div>
</body>
</html>
