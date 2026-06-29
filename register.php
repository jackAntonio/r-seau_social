<?php
require 'security.php';
session_start();

// ── GET : afficher le formulaire ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Forgab – Inscription</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <style>
    body { min-height:100vh; background:linear-gradient(120deg,#e0c3fc 0%,#8ec5fc 100%); display:flex; align-items:center; justify-content:center; }
    .register-box { max-width:650px; width:100%; margin:3rem auto; }
    .register-logo a { font-weight:bold; font-size:2.5rem; color:#0d6efd; letter-spacing:2px; text-decoration:none; }
    .card { border-radius:1.5rem; box-shadow:0 10px 40px 0 rgba(60,60,120,0.18); border:none; background:rgba(255,255,255,0.92); }
    .register-box-msg { font-size:1.35rem; color:#212529; margin-bottom:1.5rem; font-weight:600; }
    .input-group .form-control, .input-group .input-group-text { border-radius:0.75rem; }
    .input-group-text { background:#f1f3f4; border:none; color:#0d6efd; }
    .btn-primary { border-radius:0.75rem; font-weight:600; letter-spacing:1px; background:linear-gradient(90deg,#0d6efd 60%,#4e8cff 100%); border:none; }
    .photo-preview { display:flex; justify-content:center; margin-bottom:1rem; }
    .photo-preview img { max-width:160px; max-height:160px; border-radius:50%; object-fit:cover; box-shadow:0 2px 14px rgba(0,0,0,0.15); border:4px solid #f1f3f4; }
    #pwd-strength { height:6px; border-radius:4px; transition:width .3s,background .3s; width:0; }
    .pwd-rule { font-size:0.85em; }
    .pwd-rule.ok { color:green; }
    .pwd-rule.ko { color:#888; }
    @media(max-width:800px){ .register-box{max-width:98vw;} .register-logo a{font-size:1.4rem;} }
  </style>
</head>
<body>
  <div class="register-box">
    <div class="register-logo text-center mb-4"><a href="#"><b>FOR</b>GAB</a></div>
    <div class="card p-4">
      <div class="card-body">
        <p class="register-box-msg text-center">Créer un compte</p>

        <div class="photo-preview" id="photoPreview" style="display:none;">
          <img id="previewImg" src="#" alt="Aperçu" />
        </div>

        <form action="register.php" method="post" enctype="multipart/form-data" id="registerForm" novalidate>
          <?= csrf_field() ?>

          <div class="mb-3 input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" placeholder="Nom" name="nom" required minlength="2" />
          </div>

          <div class="mb-3 input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" placeholder="Prénom" name="prenom" required minlength="2" />
          </div>

          <div class="mb-3 input-group">
            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
            <input type="tel" class="form-control" placeholder="Téléphone" name="contact" required pattern="[0-9+\s\-]{7,20}" />
          </div>

          <div class="mb-3 input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" placeholder="Email" name="email" required />
          </div>

          <div class="mb-1 input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" class="form-control" placeholder="Mot de passe" name="password" id="pwd" required />
          </div>
          <div class="mb-1 bg-light rounded" style="height:6px;overflow:hidden;">
            <div id="pwd-strength"></div>
          </div>
          <div class="mb-2 ps-1">
            <small id="r-len" class="pwd-rule ko">✗ 8 caractères minimum</small><br>
            <small id="r-maj" class="pwd-rule ko">✗ 1 majuscule</small><br>
            <small id="r-num" class="pwd-rule ko">✗ 1 chiffre</small><br>
            <small id="r-spe" class="pwd-rule ko">✗ 1 caractère spécial (!@#$...)</small>
          </div>

          <div class="mb-3 input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" placeholder="Confirmer le mot de passe" id="pwd2" name="password_confirm" required />
          </div>
          <div id="pwd-match-msg" class="mb-2 ps-1" style="font-size:0.85em;"></div>

          <div class="mb-3 input-group">
            <span class="input-group-text"><i class="bi bi-globe2"></i></span>
            <select class="form-control" name="sexe" required>
              <option value="">-- Sexe --</option>
              <option value="Homme">Homme</option>
              <option value="Femme">Femme</option>
              <option value="Autre">Autre</option>
            </select>
          </div>

          <div class="mb-3 input-group">
            <span class="input-group-text"><i class="bi bi-calendar"></i></span>
            <input type="date" class="form-control" name="date_naissance" required />
          </div>

          <div class="mb-3 input-group">
            <span class="input-group-text"><i class="bi bi-house"></i></span>
            <input type="text" class="form-control" placeholder="Adresse" name="adresse" required />
          </div>

          <div class="mb-3 input-group">
            <span class="input-group-text"><i class="bi bi-file-image"></i></span>
            <input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/gif,image/webp" id="photoInput" />
          </div>

          <div class="row align-items-center mb-2">
            <div class="col-8">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="conditions" required />
                <label class="form-check-label" for="conditions">J'accepte les <a href="#">conditions</a></label>
              </div>
            </div>
            <div class="col-4">
              <button type="submit" class="btn btn-primary w-100" id="submitBtn">S'inscrire</button>
            </div>
          </div>
        </form>

        <div class="text-center mt-2">
          <a href="login.php">Déjà un compte ? Se connecter</a>
        </div>
      </div>
    </div>
  </div>

  <script>
  document.getElementById('photoInput').addEventListener('change', function(e) {
    const f = e.target.files[0];
    const preview = document.getElementById('previewImg');
    const box = document.getElementById('photoPreview');
    if (f) {
      const r = new FileReader();
      r.onload = ev => { preview.src = ev.target.result; box.style.display = 'flex'; };
      r.readAsDataURL(f);
    } else { box.style.display = 'none'; }
  });

  const pwd = document.getElementById('pwd');
  const bar = document.getElementById('pwd-strength');
  const colors = ['#e74c3c','#e67e22','#f1c40f','#2ecc71'];

  function checkPwd(v) {
    const rules = { len: v.length>=8, maj:/[A-Z]/.test(v), num:/[0-9]/.test(v), spe:/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(v) };
    const score = Object.values(rules).filter(Boolean).length;
    const mark = (id, ok) => { const el=document.getElementById(id); el.textContent=(ok?'✓':'✗')+el.textContent.slice(1); el.className='pwd-rule '+(ok?'ok':'ko'); };
    mark('r-len',rules.len); mark('r-maj',rules.maj); mark('r-num',rules.num); mark('r-spe',rules.spe);
    bar.style.width=(score*25)+'%';
    bar.style.background=colors[score-1]||'#eee';
    return rules;
  }

  pwd.addEventListener('input', () => checkPwd(pwd.value));

  const pwd2 = document.getElementById('pwd2');
  const matchEl = document.getElementById('pwd-match-msg');
  pwd2.addEventListener('input', () => {
    if (!pwd2.value) { matchEl.textContent=''; return; }
    const ok = pwd.value === pwd2.value;
    matchEl.textContent = ok ? '✓ Mots de passe identiques' : '✗ Mots de passe différents';
    matchEl.style.color = ok ? 'green' : 'red';
  });

  document.getElementById('registerForm').addEventListener('submit', function(e) {
    const rules = checkPwd(pwd.value);
    if (!Object.values(rules).every(Boolean)) { e.preventDefault(); alert('Le mot de passe ne respecte pas la politique de sécurité.'); return; }
    if (pwd.value !== pwd2.value) { e.preventDefault(); alert('Les mots de passe ne correspondent pas.'); return; }
  });
  </script>
</body>
</html>
<?php
  exit;
}

// ── POST : traiter l'inscription ──────────────────────────────
require 'config.php';

if (!csrf_verify()) { die("Requête invalide."); }

$nom            = mb_substr(trim($_POST['nom'] ?? ''), 0, 100);
$prenom         = mb_substr(trim($_POST['prenom'] ?? ''), 0, 100);
$email          = mb_substr(trim($_POST['email'] ?? ''), 0, 255);
$sexe           = in_array($_POST['sexe'] ?? '', ['Homme','Femme','Autre']) ? $_POST['sexe'] : '';
$contact        = mb_substr(trim($_POST['contact'] ?? ''), 0, 50);
$date_naissance = $_POST['date_naissance'] ?? '';
$adresse        = mb_substr(trim($_POST['adresse'] ?? ''), 0, 500);
$raw_password   = $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { die("Adresse email invalide."); }
if ($date_naissance && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_naissance)) { $date_naissance = null; }

$pwd_errors = [];
if (strlen($raw_password) < 8)                                               $pwd_errors[] = "8 caractères minimum";
if (!preg_match('/[A-Z]/', $raw_password))                                   $pwd_errors[] = "1 majuscule requise";
if (!preg_match('/[0-9]/', $raw_password))                                   $pwd_errors[] = "1 chiffre requis";
if (!preg_match('/[!@#$%^&*()\-_=+\[\]{};:\'"\\|,.<>\/? ]/', $raw_password)) $pwd_errors[] = "1 caractère spécial requis";
if (!empty($pwd_errors)) die("Mot de passe invalide : " . implode(', ', $pwd_errors));

$password = password_hash($raw_password, PASSWORD_BCRYPT);
$code     = random_int(100000, 999999);

$photo = '';
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (validate_upload($_FILES['photo'], array_keys($allowed))) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $ext   = $allowed[$finfo->file($_FILES['photo']['tmp_name'])] ?? 'jpg';
        make_upload_dir('uploads/');
        $photo = 'uploads/' . bin2hex(random_bytes(8)) . '.' . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], $photo);
    }
}

$stmt = $pdo->prepare(
    "INSERT INTO users (photo, nom, prenom, email, sexe, contact, password, code, is_verified, date_naissance, adresse)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, FALSE, ?, ?)"
);

try {
    $stmt->execute([$photo, $nom, $prenom, $email, $sexe, $contact, $password, $code,
                    $date_naissance ?: null, $adresse]);
    require 'send_mail.php';
    sendValidationEmail($email, $code);
    header("Location: verify.php?email=" . urlencode($email));
    exit;
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'unique') || str_contains($e->getMessage(), 'duplicate')) {
        header("Location: register.php?error=email");
        exit;
    }
    error_log("Inscription erreur : " . $e->getMessage());
    die("Erreur d'inscription. Veuillez réessayer.");
}
