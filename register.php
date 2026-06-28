<?php
require 'security.php';
session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Vérification CSRF
    if (!csrf_verify()) {
        die("Requête invalide.");
    }

    $nom          = trim($_POST['nom'] ?? '');
    $prenom       = trim($_POST['prenom'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $sexe         = $_POST['sexe'] ?? '';
    $contact      = trim($_POST['contact'] ?? '');
    $date_naissance = $_POST['date_naissance'] ?? '';
    $adresse      = trim($_POST['adresse'] ?? '');
    $password     = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Code cryptographiquement sécurisé
    $code = random_int(100000, 999999);

    // Upload photo avec validation MIME réelle
    $photo = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (validate_upload($_FILES['photo'], $allowed_mimes)) {
            $ext   = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $safe_ext = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'gif' => 'gif', 'webp' => 'webp'];
            $ext   = $safe_ext[$ext] ?? 'jpg';
            make_upload_dir('uploads/');
            $photo = 'uploads/' . bin2hex(random_bytes(8)) . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], $photo);
        }
    }

    $sql  = "INSERT INTO users (photo, nom, prenom, email, sexe, contact, password, code, is_verified, date_naissance, adresse)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", $photo, $nom, $prenom, $email, $sexe, $contact, $password, $code, $date_naissance, $adresse);

    if ($stmt->execute()) {
        require 'send_mail.php';
        sendValidationEmail($email, $code);
        header("Location: verify.php?email=" . urlencode($email));
        exit;
    } else {
        $error = "Erreur d'inscription. Email peut-être déjà utilisé.";
    }
}
?>
