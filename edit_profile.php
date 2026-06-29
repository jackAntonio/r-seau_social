<?php
require 'security.php';
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = intval($_SESSION['user_id']);

$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $error = 'Token CSRF invalide.'; }
    else {
        $nom     = mb_substr(trim($_POST['nom']  ?? ''), 0, 60);
        $prenom  = mb_substr(trim($_POST['prenom'] ?? ''), 0, 60);
        $bio     = mb_substr(trim($_POST['bio']  ?? ''), 0, 300);
        $contact = mb_substr(trim($_POST['contact'] ?? ''), 0, 120);

        if ($nom === '' || $prenom === '') { $error = 'Nom et prénom obligatoires.'; }
        else {
            $photo = $user['photo'];

            // Upload photo de profil
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $_FILES['photo']['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mime, $allowed_mime)) {
                    $error = 'Format image non autorisé (JPG, PNG, GIF, WebP).';
                } elseif ($_FILES['photo']['size'] > 3_000_000) {
                    $error = 'Image trop lourde (max 3 Mo).';
                } else {
                    $ext  = match($mime) {
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/gif'  => 'gif',
                        'image/webp' => 'webp',
                        default      => 'jpg',
                    };
                    $filename = 'uploads/profile_' . $user_id . '_' . time() . '.' . $ext;
                    if (!is_dir('uploads')) mkdir('uploads', 0755, true);
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $filename)) {
                        // Supprimer l'ancienne photo (si pas la photo par défaut)
                        if ($user['photo'] && str_starts_with($user['photo'], 'uploads/') && file_exists($user['photo'])) {
                            unlink($user['photo']);
                        }
                        $photo = $filename;
                    } else {
                        $error = 'Erreur lors de l\'upload.';
                    }
                }
            }

            if (!$error) {
                $pdo->prepare("UPDATE users SET nom=?, prenom=?, bio=?, contact=?, photo=? WHERE id=?")
                    ->execute([$nom, $prenom, $bio, $contact, $photo, $user_id]);
                $success = 'Profil mis à jour avec succès !';
                // Rafraîchir les données affichées
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Modifier mon profil</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    * { box-sizing: border-box; }
    body { background: linear-gradient(110deg,#e6ecf3 60%,#eaf6ff 100%); font-family:'Segoe UI',Arial,sans-serif; margin:0; color:#243045; min-height:100vh; }
    .container { max-width:500px; margin:40px auto; padding:0 14px; }
    .card { background:#fff; border-radius:18px; box-shadow:0 4px 28px #2e89ff14; padding:34px 30px; }
    h1 { font-size:1.4em; color:#2563f8; margin:0 0 24px; text-align:center; }
    .avatar-block { display:flex; flex-direction:column; align-items:center; margin-bottom:24px; gap:10px; }
    .avatar-block img { width:90px; height:90px; border-radius:50%; object-fit:cover; border:3px solid #2563f8; }
    .avatar-block label { font-size:.9em; color:#2563f8; cursor:pointer; font-weight:600; }
    .avatar-block input[type=file] { display:none; }
    label.field-label { display:block; font-weight:600; color:#1a3e72; margin-bottom:4px; font-size:.95em; }
    input[type=text], textarea { width:100%; border:1.5px solid #d0dce8; border-radius:10px; padding:9px 14px; font-size:1em; margin-bottom:16px; outline:none; resize:vertical; }
    input[type=text]:focus, textarea:focus { border-color:#2563f8; background:#f5f8ff; }
    textarea { min-height:80px; }
    .btn-row { display:flex; gap:12px; margin-top:8px; }
    .btn { flex:1; padding:11px; border-radius:11px; font-weight:700; font-size:1.02em; border:none; cursor:pointer; }
    .btn-primary { background:linear-gradient(90deg,#2e89ff,#2563f8); color:#fff; }
    .btn-outline { background:#fff; color:#2563f8; border:1.5px solid #2563f8; }
    .alert { padding:10px 16px; border-radius:10px; margin-bottom:16px; font-size:.96em; }
    .alert-error   { background:#fde8e8; color:#b91c1c; }
    .alert-success { background:#d1fae5; color:#065f46; }
    .char-count { font-size:.82em; color:#8899aa; text-align:right; margin-top:-12px; margin-bottom:12px; }
  </style>
</head>
<body>
<div class="container">
  <div class="card">
    <h1>Modifier mon profil</h1>

    <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <div class="avatar-block">
        <img src="<?= htmlspecialchars($user['photo'] ?: 'default-user.png') ?>" id="avatar-preview" alt="Photo">
        <label for="photo-input">📷 Changer la photo</label>
        <input type="file" name="photo" id="photo-input" accept="image/*" onchange="previewPhoto(this)">
      </div>

      <label class="field-label">Nom</label>
      <input type="text" name="nom" value="<?= htmlspecialchars($user['nom'] ?? '') ?>" maxlength="60" required>

      <label class="field-label">Prénom</label>
      <input type="text" name="prenom" value="<?= htmlspecialchars($user['prenom'] ?? '') ?>" maxlength="60" required>

      <label class="field-label">Bio</label>
      <textarea name="bio" maxlength="300" id="bio-field" oninput="updateCount()"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
      <div class="char-count"><span id="bio-count"><?= mb_strlen($user['bio'] ?? '') ?></span>/300</div>

      <label class="field-label">Contact / lien</label>
      <input type="text" name="contact" value="<?= htmlspecialchars($user['contact'] ?? '') ?>" maxlength="120" placeholder="Instagram, téléphone, site...">

      <div class="btn-row">
        <a href="accueil.php" class="btn btn-outline" style="text-align:center;text-decoration:none;line-height:2.1;">← Retour</a>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<script>
function previewPhoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => document.getElementById('avatar-preview').src = e.target.result;
    reader.readAsDataURL(input.files[0]);
  }
}
function updateCount() {
  document.getElementById('bio-count').textContent = document.getElementById('bio-field').value.length;
}
</script>
</body>
</html>
