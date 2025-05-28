<?php
session_start();
require 'config.php';

function is_friend($user_id, $other_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM friends WHERE ((from_user=? AND to_user=?) OR (from_user=? AND to_user=?)) AND status='accepted'");
    $stmt->bind_param("iiii", $user_id, $other_id, $other_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    $is_friend = $stmt->num_rows > 0;
    $stmt->close();
    return $is_friend;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$res = $conn->prepare("SELECT * FROM users WHERE id != ?");
$res->bind_param("i", $user_id);
$res->execute();
$users = $res->get_result()->fetch_all(MYSQLI_ASSOC);
$res->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Membres</title>
    <meta name="viewport" content="width=420, initial-scale=1">
    <style>
    body {
        background: linear-gradient(110deg, #e6ecf3 60%, #eaf6ff 100%);
        font-family: 'Segoe UI', Arial, sans-serif;
        margin: 0;
        color: #243045;
    }
    .topbar {
        background: #fff;
        box-shadow: 0 2px 12px #2e89ff22;
        padding: 0 0 4px 0;
        position: sticky;
        top: 0;
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .site-logo {
        max-width: 46px;
        border-radius: 13px;
        margin: 8px 12px 8px 16px;
        box-shadow: 0 2px 8px #2e89ff18;
        background: #eaf6ff;
        border: 2px solid #eaf6ff;
    }
    .top-title {
        font-size: 1.4em;
        font-weight: 700;
        color: #2563f8;
        letter-spacing: 0.5px;
        flex: 1 1 auto;
    }
    .container {
        max-width: 520px;
        margin: 36px auto 0 auto;
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 2px 24px #2e89ff18;
        padding: 32px 16px 18px 16px;
        min-height: 70vh;
        position: relative;
    }
    .users-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .user-card {
        display: flex;
        align-items: center;
        gap: 18px;
        padding: 14px 14px 14px 10px;
        background: linear-gradient(90deg, #f5f7fa 70%, #eaf6ff 100%);
        border-radius: 14px;
        box-shadow: 0 3px 18px #2e89ff0d;
        transition: box-shadow 0.18s, transform 0.13s;
        position: relative;
        border: 1px solid #eaf6ff;
    }
    .user-card:hover {
        box-shadow: 0 6px 24px #2e89ff33;
        transform: translateY(-2px) scale(1.012);
        border-color: #2e89ff66;
    }
    .profile-pic {
        width: 62px;
        height: 62px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #2e89ff22;
        background: #f3f6f9;
        box-shadow: 0 2px 8px #2e89ff10;
        transition: border 0.2s;
    }
    .user-card:hover .profile-pic {
        border: 3px solid #2563f8;
    }
    .user-info {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .user-info b {
        font-size: 1.16em;
        color: #1b2e4b;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 2px;
    }
    .user-info .email {
        color: #5c7896;
        font-size: 0.98em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        align-items: flex-end;
        min-width: 114px;
    }
    .actions button, .actions .profile_button {
        padding: 7px 16px;
        border-radius: 9px;
        font-weight: 600;
        font-size: 1em;
        border: none;
        cursor: pointer;
        transition: background .15s, color .15s, box-shadow .2s;
        outline: none;
        margin: 0;
        box-shadow: 0 1px 8px #2e89ff0d;
    }
    .actions button {
        background: linear-gradient(90deg,#2e89ff 70%,#2563f8 100%);
        color: #fff;
        border: none;
    }
    .actions button:hover {
        background: linear-gradient(90deg,#2563f8 80%,#2e89ff 100%);
    }
    .actions .profile_button {
        background: #fff;
        color: #2e89ff;
        border: 1.5px solid #2e89ff;
        text-decoration: none;
        text-align: center;
        box-shadow: none;
    }
    .actions .profile_button:hover {
        background: #2e89ff;
        color: #fff;
        border-color: #2e89ff;
    }
    .return-home {
        display: inline-block;
        margin: 34px 0 0 0;
        padding: 8px 30px;
        font-weight: 600;
        border-radius: 12px;
        background: linear-gradient(90deg,#2e89ff 70%,#2563f8 100%);
        color: #fff;
        text-decoration: none;
        border: none;
        box-shadow: 0 2px 12px #2e89ff22;
        transition: background .17s, color .17s;
    }
    .return-home:hover {
        background: linear-gradient(90deg,#2563f8 80%,#2e89ff 100%);
        color: #fff;
    }
    @media (max-width: 600px) {
        .container { max-width: 99vw; margin: 16px 2vw 0 2vw; }
        .users-list { gap: 13px; }
        .profile-pic { width: 48px; height: 48px; }
        .user-card { padding: 10px 7px 10px 4px; }
        .actions { min-width: 74px; }
    }
    </style>
</head>
<body>
    <div class="topbar">
      <span class="top-title">Membres</span>
    </div>
    
    <div class="container">
        <div class="users-list">
            <?php if (empty($users)): ?>
                <div style="text-align:center;color:#5c7896;font-size:1.1em;padding:40px 0;">
                    Aucun autre membre trouvé.
                </div>
            <?php else: ?>
                <?php foreach ($users as $u): ?>
                    <div class="user-card">
                        <img src="<?= htmlspecialchars($u['photo'] ?: 'default-user.png') ?>" class="profile-pic" alt="">
                        <div class="user-info">
                            <b><?= htmlspecialchars($u['nom']) . " " . htmlspecialchars($u['prenom']) ?></b>
                            <span class="email"><?= htmlspecialchars($u['email']) ?></span>
                        </div>
                        <div class="actions">
                        <?php if (!is_friend($user_id, $u['id'])): ?>
                            <form method="post" action="friend_request.php" style="margin:0;">
                                <input type="hidden" name="to_user" value="<?= $u['id'] ?>">
                                <button type="submit">Ajouter</button>
                            </form>
                        <?php else: ?>
                            <a href="chat.php?with=<?= $u['id'] ?>" class="profile_button">Discuter 💬</a>
                        <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <a href="accueil.php" class="return-home">Retour à l'accueil</a>
    </div>
</body>
</html>