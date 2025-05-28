<?php
session_start();
require 'config.php';

function is_friend($user_id, $other_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM friends WHERE ((from_user=? AND to_user=?) OR (from_user=? AND to_user=?)) AND status='accepted'");
    $stmt->bind_param("iiii", $user_id, $other_id, $other_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    $res = $stmt->num_rows > 0;
    $stmt->close();
    return $res;
}

$me = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { echo "Profil introuvable."; exit; }

$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) { echo "Utilisateur inconnu."; exit; }

$is_friend = ($me && $id && is_friend($me, $id));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Profil de <?= htmlspecialchars($user['nom']) ?></title>
    <link rel="stylesheet" href="profile-card.css">
</head>
<body>
    <div style="text-align:center;margin-top:12px;">
      <img src="site-logo.png" alt="Logo du site" style="max-width:90px;">
    </div>
    <div class="container d-flex justify-content-center">
        <div class="card p-3 py-4">
            <div class="text-center">
                <img src="<?= htmlspecialchars($user['photo'] ?: 'default-user.png') ?>" class="profile-pic" alt="Photo de profil">
                <h3 class="mt-2"><?= htmlspecialchars($user['nom']) . ' ' . htmlspecialchars($user['prenom']) ?></h3>
                <span class="mt-1 clearfix"><?= htmlspecialchars($user['email']) ?></span>
                <div class="row mt-3 mb-3" style="display: flex; justify-content: center;">
                    <div class="col-md-4"><h5>Sexe</h5><span class="num"><?= htmlspecialchars($user['sexe']) ?></span></div>
                    <div class="col-md-4"><h5>Naissance</h5><span class="num"><?= htmlspecialchars($user['date_naissance']) ?></span></div>
                    <div class="col-md-4"><h5>Contact</h5><span class="num"><?= htmlspecialchars($user['contact']) ?></span></div>
                </div>
                <hr class="line">
                <?php if ($me && $me != $id): ?>
                    <?php if (!$is_friend): ?>
                        <form method="post" action="friend_request.php" style="display:inline;">
                            <input type="hidden" name="to_user" value="<?= $id ?>">
                            <button type="submit" class="profile_button">Demander en ami</button>
                        </form>
                    <?php else: ?>
                        <a href="chat.php?with=<?= $id ?>" class="profile_button">Discuter 💬</a>
                    <?php endif; ?>
                <?php endif; ?>
                <br><br>
                <a href="<?= $me ? 'users.php' : 'login.php' ?>" class="profile_button">Retour</a>
            </div>
        </div>
    </div>
</body>
</html>