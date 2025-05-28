<?php
session_start();
require 'config.php';

$email = $_GET['email'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $code = $_POST['code'] ?? '';

    if (!$email || !$code) {
        $error = "Veuillez renseigner l'email et le code.";
    } else {
        // Prépare la requête pour récupérer le code
        $stmt = $conn->prepare("SELECT code FROM users WHERE email = ? AND is_verified = 0");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();  // <--- IMPORTANT pour éviter l'erreur de désynchronisation
        $stmt->bind_result($db_code);

        if ($stmt->fetch()) {
            if ($db_code == $code) {
                $stmt->close();

                // Met à jour is_verified à 1
                $update = $conn->prepare("UPDATE users SET is_verified = 1 WHERE email = ?");
                $update->bind_param("s", $email);
                $update->execute();
                $update->close();

                $success = "Compte vérifié avec succès ! Vous pouvez maintenant vous connecter.";
                header("Location: login.php?verifie=ok");
                exit;

            } else {

                $error = "Code invalide.";
            }
        } else {
            $error = "Compte déjà vérifié ou email introuvable.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Vérification du compte</title>
    <link rel="stylesheet" href="style.css" />
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 400px; margin: 100px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; }
        input, button { width: 100%; padding: 10px; margin: 8px 0; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
<div class="container">
    <h2>Vérifiez votre compte</h2>
    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p class="success"><?= htmlspecialchars($success) ?></p>
        <p><a href="login.php">Se connecter</a></p>
    <?php else: ?>
        <form method="POST" action="verify.php">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>" />
            <input type="text" name="code" placeholder="Entrez le code reçu par email" required />
            <button type="submit">Valider</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
