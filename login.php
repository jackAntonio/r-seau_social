<?php
require 'config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT id, password, is_verified, nom FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $stmt->bind_result($id, $hash, $is_verified, $nom);
    if ($stmt->fetch()) {
        if (!$is_verified) {
            $error = "Compte non vérifié.";
        } elseif (password_verify($password, $hash)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['nom'] = $nom;
            header("Location: accueil.php");
            exit;
        } else {
            $error = "Mot de passe incorrect.";
        }
    } else {
        $error = "Email non trouvé.";
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
    .container {
      background-color: white;
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 0 15px rgba(0,0,0,0.3);
      width: 320px;
    }
    h2 {
      margin-bottom: 20px;
      color: #333;
      text-align: center;
    }
    input[type="email"], input[type="password"] {
      width: 100%;
      padding: 12px;
      margin-bottom: 15px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 16px;
    }
    button {
      width: 100%;
      padding: 12px;
      background-color: green;
      color: white;
      font-size: 16px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
    }
    .error {
      color: red;
      margin-bottom: 15px;
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Connexion</h2>
    <!-- Affiche le message d’erreur s’il existe dans l’URL -->
    <?php if (isset($_GET['error'])): ?>
      <div class="error"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>
    <form method="post" action="login.php">
      <input type="email" name="email" placeholder="Votre email" required />
      <input type="password" name="password" placeholder="Mot de passe" required />
      <button type="submit">Se connecter</button>
    </form>
  </div>
</body>
</html>