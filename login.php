<?php
require 'security.php';
session_start();
require 'config.php';
require 'audit.php';

if (isset($_SESSION['user_id'])) {
    header("Location: accueil.php");
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) {
        $error = "Requête invalide.";
    } else {
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $email = trim($_POST['email'] ?? '');

        // Suggestion 1 : Rate limiting (max 5 tentatives / heure / IP)
        if (is_rate_limited($conn, $ip)) {
            $error = "Trop de tentatives. Réessayez dans une heure.";
        } else {
            $password = $_POST['password'] ?? '';
            $stmt     = $conn->prepare("SELECT id, password, is_verified, nom FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->bind_result($id, $hash, $is_verified, $nom);
            $found = $stmt->fetch();
            $stmt->close();

            if ($found && $is_verified && password_verify($password, $hash)) {
                // Connexion réussie
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                $_SESSION['nom']     = $nom;

                // Vérifier si 2FA activé
                $s2 = $conn->prepare("SELECT totp_enabled FROM users WHERE id=?");
                $s2->bind_param("i", $id);
                $s2->execute();
                $s2->bind_result($totp_enabled);
                $s2->fetch();
                $s2->close();

                if ($totp_enabled) {
                    $_SESSION['totp_pending'] = true;
                    audit_log($conn, 'login_2fa_required', $id, 'user', $id);
                    header("Location: totp_verify.php");
                } else {
                    audit_log($conn, 'login_success', $id, 'user', $id);
                    header("Location: accueil.php");
                }
                exit;
            } else {
                // Enregistrer la tentative échouée
                record_attempt($conn, $ip, $email);
                audit_log($conn, 'login_failed', null, null, null);
                // Message générique pour éviter l'énumération
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
    body { background-color: #777; display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial, sans-serif; }
    .container { background-color: white; padding: 30px; border-radius: 15px; box-shadow: 0 0 15px rgba(0,0,0,0.3); width: 320px; }
    h2 { margin-bottom: 20px; color: #333; text-align: center; }
    input[type="email"], input[type="password"] { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 8px; font-size: 16px; box-sizing: border-box; }
    button { width: 100%; padding: 12px; background-color: green; color: white; font-size: 16px; border: none; border-radius: 8px; cursor: pointer; }
    .error { color: red; margin-bottom: 15px; text-align: center; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Connexion</h2>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="login.php">
      <?= csrf_field() ?>
      <input type="email" name="email" placeholder="Votre email" required />
      <input type="password" name="password" placeholder="Mot de passe" required />
      <button type="submit">Se connecter</button>
    </form>
    <p style="text-align:center;margin-top:12px;"><a href="register.html">Créer un compte</a></p>
  </div>
</body>
</html>
