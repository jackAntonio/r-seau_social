<?php
require 'security.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'config.php';
require 'functions.php';
require 'roles.php';

$id = intval($_SESSION['user_id']);

// Utilisateur connecté
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) {
    header("Location: logout.php");
    exit;
}

$user_photo = ($user['photo'] && file_exists($user['photo'])) ? $user['photo'] : 'default-user.png';
$user_full_name = htmlspecialchars($user['nom'] . ' ' . $user['prenom']);
$user_first_name = htmlspecialchars($user['prenom'] ?: $user['nom']);

// Ma story active (< 24h)
$stmt = $pdo->prepare("SELECT * FROM stories WHERE user_id=? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$id]);
$user_story = $stmt->fetch();

// Stories des autres utilisateurs (< 24h, compatible MySQL et PostgreSQL)
$stmt = $pdo->prepare(
    "SELECT s.*, u.nom, u.prenom, u.photo
     FROM stories s JOIN users u ON u.id = s.user_id
     WHERE s.user_id != ? AND s.expires_at > NOW()
     ORDER BY s.created_at DESC"
);
$stmt->execute([$id]);
$all_raw_stories = $stmt->fetchAll();
$stories = [];
$seen_story_users = [];
foreach ($all_raw_stories as $st) {
    if (!isset($seen_story_users[$st['user_id']])) {
        $seen_story_users[$st['user_id']] = true;
        $stories[] = $st;
    }
}

// Demandes d'amis reçues (pending) pour le widget latéral droit
$stmt = $pdo->prepare("
    SELECT f.id AS req_id, u.id AS user_id, u.nom, u.prenom, u.photo, f.created_at
    FROM friends f
    JOIN users u ON u.id = f.from_user
    WHERE f.to_user=? AND f.status='pending'
    ORDER BY f.created_at DESC LIMIT 5
");
$stmt->execute([$id]);
$friend_requests = $stmt->fetchAll();

// Liste des amis acceptés pour la barre "Contacts"
$friends = get_friends($pdo, $id);

// Nombre de notifications non lues
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=FALSE");
$stmt->execute([$id]);
$unread_notifs = intval($stmt->fetchColumn());

// 10 dernières notifications pour le popover
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$id]);
$recent_notifs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Forgab</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1877f2">
    <link rel="icon" type="image/png" href="site-logo.png">
    <link rel="apple-touch-icon" href="icons/icon-192.png">

    <!-- Empêcher le flash blanc lors du rechargement avec le thème sombre -->
    <script>
      (function() {
        const theme = localStorage.getItem('fb_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        if (theme === 'dark') document.documentElement.classList.add('dark');
      })();
    </script>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;600;700&display=swap">

    <style>
/* ============================================================
   DESIGN SYSTEM FACEBOOK MODERNE (THEMES CLAIR & SOMBRE)
   ============================================================ */
:root {
    --fb-blue: #1877f2;
    --fb-blue-hover: #166fe5;
    --bg-primary: #f0f2f5;
    --bg-secondary: #ffffff;
    --bg-tertiary: #e4e6eb;
    --bg-hover: #f2f2f2;
    --text-primary: #050505;
    --text-secondary: #65676b;
    --divider: #ced0d4;
    --card-border: rgba(0, 0, 0, 0.08);
    --card-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    --modal-overlay: rgba(244, 244, 244, 0.85);
    --badge-bg: #e41e3f;
    --green-online: #31a24c;
    --topbar-height: 56px;
    --radius-card: 10px;
    --radius-btn: 8px;
}

html.dark {
    --fb-blue: #2e89ff;
    --fb-blue-hover: #2677dd;
    --bg-primary: #18191a;
    --bg-secondary: #242526;
    --bg-tertiary: #3a3b3c;
    --bg-hover: #3a3b3c;
    --text-primary: #e4e6eb;
    --text-secondary: #b0b3b8;
    --divider: #393a3b;
    --card-border: rgba(255, 255, 255, 0.08);
    --card-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
    --modal-overlay: rgba(0, 0, 0, 0.85);
    --badge-bg: #e41e3f;
    --green-online: #31a24c;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    background-color: var(--bg-primary);
    color: var(--text-primary);
    font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
    overflow-x: hidden;
}

a { text-decoration: none; color: inherit; }
button { font-family: inherit; }

/* ============================================================
   TOP NAVIGATION BAR (FACEBOOK STYLE)
   ============================================================ */
.fb-navbar {
    position: sticky;
    top: 0;
    z-index: 1000;
    height: var(--topbar-height);
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--divider);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.fb-nav-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1 1 280px;
}

.fb-logo {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--fb-blue);
    color: #fff;
    font-weight: 800;
    font-size: 1.6rem;
    box-shadow: 0 2px 8px rgba(24, 119, 242, 0.3);
    transition: transform 0.15s;
}
.fb-logo:hover { transform: scale(1.04); }

.fb-search-wrap {
    position: relative;
    max-width: 250px;
    width: 100%;
}
.fb-search-input {
    width: 100%;
    height: 40px;
    border-radius: 50px;
    border: none;
    background: var(--bg-primary);
    color: var(--text-primary);
    padding: 0 16px 0 38px;
    font-size: 0.95rem;
    outline: none;
    transition: background 0.15s, box-shadow 0.15s;
}
.fb-search-input:focus {
    background: var(--bg-secondary);
    box-shadow: 0 0 0 2px var(--fb-blue);
}
.fb-search-icon {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    pointer-events: none;
    font-size: 1rem;
}

/* Onglets Centraux */
.fb-nav-center {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 100%;
    flex: 2 1 auto;
}
.fb-tab-item {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100px;
    height: 100%;
    cursor: pointer;
    position: relative;
    color: var(--text-secondary);
    transition: color 0.15s, background 0.15s;
    border-radius: 8px;
}
.fb-tab-item:hover {
    background: var(--bg-hover);
}
.fb-tab-item.active {
    color: var(--fb-blue);
}
.fb-tab-item.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--fb-blue);
    border-radius: 3px 3px 0 0;
}
.fb-tab-item svg { width: 28px; height: 28px; }

/* Actions Droite */
.fb-nav-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex: 1 1 280px;
}

.fb-circle-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--bg-tertiary);
    color: var(--text-primary);
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    transition: background 0.15s, transform 0.1s;
}
.fb-circle-btn:hover {
    background: var(--divider);
}
.fb-circle-btn:active { transform: scale(0.95); }

.fb-badge {
    position: absolute;
    top: -3px;
    right: -3px;
    background: var(--badge-bg);
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 5px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
    border: 2px solid var(--bg-secondary);
}

.fb-user-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 10px 4px 4px;
    border-radius: 50px;
    cursor: pointer;
    background: transparent;
    transition: background 0.15s;
}
.fb-user-pill:hover { background: var(--bg-hover); }
.fb-user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
}
.fb-user-name {
    font-weight: 600;
    font-size: 0.92rem;
    color: var(--text-primary);
}

/* ============================================================
   DISPOSITION 3 COLONNES FACEBOOK
   ============================================================ */
.fb-main-layout {
    display: flex;
    justify-content: space-between;
    max-width: 1440px;
    margin: 0 auto;
    padding: 16px 8px;
    gap: 24px;
}

.fb-left-col {
    width: 320px;
    position: sticky;
    top: calc(var(--topbar-height) + 16px);
    height: calc(100vh - var(--topbar-height) - 32px);
    overflow-y: auto;
    padding-right: 6px;
}

.fb-center-col {
    flex: 1 1 640px;
    max-width: 680px;
    min-width: 340px;
}

.fb-right-col {
    width: 320px;
    position: sticky;
    top: calc(var(--topbar-height) + 16px);
    height: calc(100vh - var(--topbar-height) - 32px);
    overflow-y: auto;
    padding-left: 6px;
}

/* Menu Latéral Gauche */
.fb-menu-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 12px;
    border-radius: var(--radius-btn);
    cursor: pointer;
    transition: background 0.15s;
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--text-primary);
}
.fb-menu-item:hover { background: var(--bg-hover); }
.fb-menu-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: var(--bg-tertiary);
    color: var(--fb-blue);
    flex-shrink: 0;
}
.fb-menu-item.active .fb-menu-icon {
    background: var(--fb-blue);
    color: #fff;
}
.fb-sidebar-divider {
    height: 1px;
    background: var(--divider);
    margin: 12px 10px;
}
.fb-footer-links {
    font-size: 0.78rem;
    color: var(--text-secondary);
    padding: 12px;
    line-height: 1.5;
}

/* ============================================================
   CARROUSEL DES STORIES (STYLE FACEBOOK REELS & STORIES)
   ============================================================ */
.fb-stories-wrap {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    overflow-x: auto;
    padding: 4px 2px;
    scrollbar-width: none;
}
.fb-stories-wrap::-webkit-scrollbar { display: none; }

.fb-story-card {
    width: 125px;
    min-width: 125px;
    height: 210px;
    border-radius: 12px;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    box-shadow: var(--card-shadow);
    background: var(--bg-secondary);
    transition: transform 0.2s, box-shadow 0.2s;
    flex-shrink: 0;
}
.fb-story-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.2);
}

/* Carte Créer une story */
.fb-story-create {
    display: flex;
    flex-direction: column;
}
.fb-story-create-img {
    height: 155px;
    width: 100%;
    object-fit: cover;
    transition: transform 0.25s;
}
.fb-story-create:hover .fb-story-create-img { transform: scale(1.05); }
.fb-story-create-bottom {
    flex: 1;
    background: var(--bg-secondary);
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    padding-top: 18px;
}
.fb-story-plus-btn {
    position: absolute;
    top: -18px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--fb-blue);
    color: #fff;
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid var(--bg-secondary);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}
.fb-story-create-text {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-primary);
    text-align: center;
}

/* Cartes Stories des Amis */
.fb-story-friend-bg {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}
.fb-story-card:hover .fb-story-friend-bg { transform: scale(1.06); }
.fb-story-avatar-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 3.5px solid var(--fb-blue);
    object-fit: cover;
    z-index: 2;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}
.fb-story-gradient-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 65%;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.85) 0%, rgba(0, 0, 0, 0.4) 40%, transparent 100%);
    display: flex;
    align-items: flex-end;
    padding: 10px;
    z-index: 1;
}
.fb-story-author-name {
    color: #fff;
    font-size: 0.85rem;
    font-weight: 600;
    line-height: 1.25;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
}

/* ============================================================
   BLOC CRÉATEUR DE PUBLICATION (FACEBOOK COMPOSER)
   ============================================================ */
.fb-composer-card {
    background: var(--bg-secondary);
    border-radius: var(--radius-card);
    box-shadow: var(--card-shadow);
    padding: 12px 16px;
    margin-bottom: 20px;
}
.fb-composer-top {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}
.fb-composer-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
}
.fb-composer-btn {
    flex: 1;
    background: var(--bg-primary);
    border: none;
    border-radius: 50px;
    padding: 11px 18px;
    text-align: left;
    color: var(--text-secondary);
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.15s;
}
.fb-composer-btn:hover { background: var(--bg-tertiary); }
.fb-composer-divider {
    height: 1px;
    background: var(--divider);
    margin: 8px 0;
}
.fb-composer-actions {
    display: flex;
    justify-content: space-around;
    padding-top: 4px;
}
.fb-composer-action-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: none;
    border: none;
    padding: 8px 14px;
    border-radius: var(--radius-btn);
    cursor: pointer;
    font-weight: 600;
    font-size: 0.92rem;
    color: var(--text-secondary);
    transition: background 0.15s;
}
.fb-composer-action-btn:hover { background: var(--bg-hover); }

/* ============================================================
   CARTES DE PUBLICATION (FEED POSTS)
   ============================================================ */
.fb-post-card {
    background: var(--bg-secondary);
    border-radius: var(--radius-card);
    box-shadow: var(--card-shadow);
    margin-bottom: 18px;
    overflow: hidden;
}
.fb-post-header {
    display: flex;
    align-items: center;
    padding: 12px 16px 8px 16px;
    position: relative;
}
.fb-post-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 12px;
}
.fb-post-user-info {
    flex: 1;
}
.fb-post-author {
    font-weight: 700;
    font-size: 0.97rem;
    color: var(--text-primary);
}
.fb-post-meta {
    font-size: 0.8rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 1px;
}
.fb-post-dots {
    background: none;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1.25rem;
    transition: background 0.15s;
}
.fb-post-dots:hover { background: var(--bg-hover); }

.fb-post-content {
    padding: 6px 16px 12px 16px;
    font-size: 0.98rem;
    line-height: 1.45;
    word-break: break-word;
    color: var(--text-primary);
}
.fb-post-content.large-text {
    font-size: 1.4rem;
    font-weight: 500;
    padding: 16px;
}
.fb-post-content.gradient-box {
    min-height: 260px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 30px;
    color: #fff;
    font-weight: 700;
    font-size: 1.6rem;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.4);
}

.fb-post-media-wrap {
    width: 100%;
    background: #000;
}
.fb-post-media-wrap img,
.fb-post-media-wrap video {
    width: 100%;
    max-height: 600px;
    object-fit: contain;
    display: block;
}

/* Barre de statistiques (Likes & Commentaires) */
.fb-post-stats {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    font-size: 0.88rem;
    color: var(--text-secondary);
    border-bottom: 1px solid var(--divider);
    margin: 0 12px;
}
.fb-stats-reactions {
    display: flex;
    align-items: center;
    gap: 6px;
}
.fb-stats-icons {
    display: flex;
    align-items: center;
}
.fb-stats-icon {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: -4px;
    border: 2px solid var(--bg-secondary);
    font-size: 0.85rem;
}

/* Boutons d'Action (Like, Commenter, Partager) */
.fb-post-actions-bar {
    display: flex;
    padding: 4px 12px;
    margin: 0 4px;
}
.fb-post-action-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: none;
    border: none;
    padding: 9px 0;
    border-radius: var(--radius-btn);
    cursor: pointer;
    font-weight: 600;
    font-size: 0.93rem;
    color: var(--text-secondary);
    transition: background 0.15s, color 0.15s;
    position: relative;
}
.fb-post-action-btn:hover { background: var(--bg-hover); }

/* Menu Réactions Flottant (Hover Reaction Dock) */
.fb-reaction-wrap {
    position: relative;
    flex: 1;
    display: flex;
}
.fb-reaction-picker {
    display: none;
    position: absolute;
    bottom: 100%;
    left: 8px;
    margin-bottom: 8px;
    background: var(--bg-secondary);
    border-radius: 50px;
    padding: 6px 12px;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.25);
    border: 1px solid var(--card-border);
    gap: 10px;
    z-index: 200;
    animation: popReaction 0.2s cubic-bezier(0.18, 0.89, 0.32, 1.28);
}
@keyframes popReaction {
    from { opacity: 0; transform: translateY(12px) scale(0.8); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.fb-reaction-wrap:hover .fb-reaction-picker {
    display: flex;
}
.fb-reaction-item {
    font-size: 1.85rem;
    cursor: pointer;
    transition: transform 0.15s cubic-bezier(0.18, 0.89, 0.32, 1.28);
    user-select: none;
}
.fb-reaction-item:hover {
    transform: scale(1.4) translateY(-6px);
}

/* Tiroir de Commentaires */
.fb-comments-section {
    padding: 12px 16px;
    background: var(--bg-secondary);
    border-top: 1px solid var(--divider);
    display: none;
}
.fb-comments-section.open { display: block; }
.fb-comment-form {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}
.fb-comment-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}
.fb-comment-input-wrap {
    flex: 1;
    position: relative;
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 6px 14px;
    display: flex;
    align-items: center;
}
.fb-comment-input {
    width: 100%;
    border: none;
    background: transparent;
    color: var(--text-primary);
    outline: none;
    font-size: 0.93rem;
}
.fb-comment-submit {
    background: none;
    border: none;
    color: var(--fb-blue);
    cursor: pointer;
    font-size: 1.1rem;
    padding: 0 4px;
}

/* ============================================================
   PANNEAU LATÉRAL DROIT (CONTACTS & DEMANDES D'AMI)
   ============================================================ */
.fb-right-widget {
    margin-bottom: 24px;
}
.fb-right-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-secondary);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.fb-friend-req-card {
    display: flex;
    gap: 12px;
    padding: 10px;
    background: var(--bg-secondary);
    border-radius: var(--radius-btn);
    box-shadow: var(--card-shadow);
    margin-bottom: 10px;
}
.fb-friend-req-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
}
.fb-friend-req-info { flex: 1; }
.fb-friend-req-name { font-weight: 600; font-size: 0.92rem; margin-bottom: 6px; }
.fb-friend-req-actions { display: flex; gap: 6px; }
.fb-btn-confirm {
    background: var(--fb-blue);
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 6px 12px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
}
.fb-btn-delete {
    background: var(--bg-tertiary);
    color: var(--text-primary);
    border: none;
    border-radius: 6px;
    padding: 6px 12px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
}

/* Contacts en ligne */
.fb-contact-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 10px;
    border-radius: var(--radius-btn);
    cursor: pointer;
    transition: background 0.15s;
    position: relative;
}
.fb-contact-item:hover { background: var(--bg-hover); }
.fb-contact-avatar-wrap {
    position: relative;
    width: 36px;
    height: 36px;
}
.fb-contact-avatar {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.fb-online-dot {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 10px;
    height: 10px;
    background: var(--green-online);
    border: 2px solid var(--bg-secondary);
    border-radius: 50%;
}
.fb-contact-name {
    font-weight: 600;
    font-size: 0.92rem;
    color: var(--text-primary);
}

/* ============================================================
   MESSENGER FLOATING DOCK (FENÊTRE DE CHAT FLOTTANTE)
   ============================================================ */
.fb-messenger-dock {
    position: fixed;
    bottom: 0;
    right: 70px;
    width: 340px;
    height: 440px;
    background: var(--bg-secondary);
    border-radius: 12px 12px 0 0;
    box-shadow: 0 6px 28px rgba(0, 0, 0, 0.3);
    border: 1px solid var(--divider);
    display: none;
    flex-direction: column;
    z-index: 9999;
    overflow: hidden;
    animation: slideUp 0.25s ease;
}
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }

.fb-messenger-header {
    background: var(--bg-secondary);
    padding: 10px 14px;
    border-bottom: 1px solid var(--divider);
    display: flex;
    align-items: center;
    gap: 10px;
}
.fb-messenger-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    object-fit: cover;
}
.fb-messenger-title {
    flex: 1;
    font-weight: 700;
    font-size: 0.95rem;
    line-height: 1.2;
}
.fb-messenger-title small {
    display: block;
    font-size: 0.75rem;
    font-weight: 400;
    color: var(--green-online);
}
.fb-messenger-tools {
    display: flex;
    align-items: center;
    gap: 6px;
}
.fb-messenger-tool-btn {
    background: none;
    border: none;
    color: var(--fb-blue);
    cursor: pointer;
    font-size: 1.1rem;
    padding: 4px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.fb-messenger-tool-btn:hover { background: var(--bg-hover); }

.fb-messenger-body {
    flex: 1;
    padding: 12px;
    overflow-y: auto;
    background: var(--bg-primary);
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.fb-msg-bubble {
    max-width: 75%;
    padding: 8px 13px;
    border-radius: 18px;
    font-size: 0.93rem;
    word-break: break-word;
    background: var(--bg-tertiary);
    color: var(--text-primary);
    align-self: flex-start;
}
.fb-msg-bubble.me {
    background: var(--fb-blue);
    color: #fff;
    align-self: flex-end;
}

.fb-messenger-footer {
    padding: 8px 10px;
    border-top: 1px solid var(--divider);
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-secondary);
}
.fb-messenger-input {
    flex: 1;
    border: none;
    background: var(--bg-primary);
    color: var(--text-primary);
    padding: 8px 14px;
    border-radius: 20px;
    outline: none;
    font-size: 0.92rem;
}

/* ============================================================
   MODALES (CRÉER UN POST & VISIONNEUSE STORIES)
   ============================================================ */
.fb-modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: var(--modal-overlay);
    backdrop-filter: blur(5px);
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
}
.fb-modal-dialog {
    background: var(--bg-secondary);
    border-radius: var(--radius-card);
    width: 100%;
    max-width: 500px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.35);
    border: 1px solid var(--card-border);
    overflow: hidden;
    animation: scaleModal 0.2s ease;
}
@keyframes scaleModal {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.fb-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    border-bottom: 1px solid var(--divider);
}
.fb-modal-header h3 { font-size: 1.25rem; font-weight: 700; text-align: center; flex: 1; }
.fb-modal-close {
    background: var(--bg-tertiary);
    border: none;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    font-size: 1.2rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
}
.fb-modal-body { padding: 16px; }

/* Palette de gradients de posts */
.fb-gradients-palette {
    display: flex;
    gap: 8px;
    margin: 12px 0;
}
.fb-gradient-circle {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    border: 2px solid transparent;
    transition: transform 0.15s;
}
.fb-gradient-circle:hover { transform: scale(1.15); }
.fb-gradient-circle.active { border-color: var(--fb-blue); }

/* Menu déroulant Popover (Notifications & Profil) */
.fb-popover-menu {
    position: absolute;
    top: calc(var(--topbar-height) + 6px);
    right: 16px;
    width: 360px;
    max-height: 80vh;
    background: var(--bg-secondary);
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
    border: 1px solid var(--card-border);
    padding: 12px;
    display: none;
    z-index: 2000;
    overflow-y: auto;
    animation: popMenu 0.18s ease;
}
@keyframes popMenu { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

/* Toast Notifications */
.fb-toast-container {
    position: fixed;
    bottom: 24px;
    left: 24px;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.fb-toast {
    background: var(--bg-secondary);
    color: var(--text-primary);
    padding: 14px 18px;
    border-radius: 12px;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.25);
    border-left: 5px solid var(--fb-blue);
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    animation: slideInToast 0.3s ease;
}
@keyframes slideInToast { from { opacity: 0; transform: translateX(-20px); } to { opacity: 1; transform: translateX(0); } }

/* Responsive Mobile */
@media (max-width: 1100px) {
    .fb-right-col { display: none; }
    .fb-main-layout { justify-content: center; }
}
@media (max-width: 900px) {
    .fb-left-col { display: none; }
    .fb-center-col { max-width: 100%; }
    .fb-nav-center { display: none; }
}
    </style>
</head>
<body>

<!-- ============================================================
     TOPBAR FACEBOOK
     ============================================================ -->
<header class="fb-navbar">
    <!-- Gauche : Logo + Recherche -->
    <div class="fb-nav-left">
        <a href="accueil.php" class="fb-logo" title="Forgab Accueil">F</a>
        <div class="fb-search-wrap">
            <span class="fb-search-icon">🔍</span>
            <input type="text" class="fb-search-input" id="globalSearch" placeholder="Rechercher sur Forgab..." onkeydown="if(event.key==='Enter') window.location.href='users.php?q='+encodeURIComponent(this.value)">
        </div>
    </div>

    <!-- Centre : Onglets de Navigation Principaux -->
    <nav class="fb-nav-center">
        <a href="accueil.php" class="fb-tab-item active" title="Accueil">
            <svg viewBox="0 0 28 28" fill="currentColor"><path d="M14 2.042 2.658 11.08a1.25 1.25 0 0 0-.408.928V24a2 2 0 0 0 2 2h6.5a1.25 1.25 0 0 0 1.25-1.25v-6.5a1.25 1.25 0 0 1 1.25-1.25h2a1.25 1.25 0 0 1 1.25 1.25v6.5a1.25 1.25 0 0 0 1.25 1.25H24a2 2 0 0 0 2-2V12.008a1.25 1.25 0 0 0-.408-.928L14 2.042z"/></svg>
        </a>
        <a href="users.php" class="fb-tab-item" title="Amis">
            <svg viewBox="0 0 28 28" fill="currentColor"><path d="M19 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0zm-10 9c0-1.99 2.24-3.5 5-3.5s5 1.51 5 3.5V20H9v-1zm14.5-5.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0zm-4.5 6.5c0-.98.67-1.87 1.76-2.43.91.43 2.03.68 3.24.68 1.21 0 2.33-.25 3.24-.68 1.09.56 1.76 1.45 1.76 2.43V20h-10v-1z"/></svg>
        </a>
        <a href="feed.php" class="fb-tab-item" title="Vidéos">
            <svg viewBox="0 0 28 28" fill="currentColor"><path d="M4 6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6zm8 4v8l6-4-6-4z"/></svg>
        </a>
        <a href="users.php" class="fb-tab-item" title="Groupes">
            <svg viewBox="0 0 28 28" fill="currentColor"><path d="M9 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm10 0a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2.5 22c0-2.485 2.91-4.5 6.5-4.5s6.5 2.015 6.5 4.5V23h-13v-1zm14 0c0-1.37.89-2.58 2.28-3.41 1.22.42 2.62.66 4.12.66 1.5 0 2.9-.24 4.12-.66 1.39.83 2.28 2.04 2.28 3.41V23h-12.8z"/></svg>
        </a>
    </nav>

    <!-- Droite : Messenger, Notifs, Menu Profil & Dark Mode -->
    <div class="fb-nav-right">
        <!-- Bouton Messenger -->
        <button class="fb-circle-btn" id="btnMessengerToggle" title="Messenger" onclick="toggleMessengerDrawer()">
            <svg width="20" height="20" viewBox="0 0 28 28" fill="currentColor"><path d="M14 2C7.373 2 2 6.925 2 13c0 3.435 1.72 6.51 4.417 8.528V26l4.283-2.353c1.05.291 2.16.453 3.3.453 6.627 0 12-4.925 12-11S20.627 2 14 2zm1.2 15-3.2-3.4-6.2 3.4 6.8-7.2 3.2 3.4 6.2-3.4-6.8 7.2z"/></svg>
        </button>

        <!-- Bouton Notifications avec Badge -->
        <button class="fb-circle-btn" id="btnNotifToggle" title="Notifications" onclick="toggleNotifDropdown()">
            <svg width="20" height="20" viewBox="0 0 28 28" fill="currentColor"><path d="M14 2a2 2 0 0 0-2 2v.27C8.44 5.12 6 8.35 6 12.25v5.5L4.29 19.46A1 1 0 0 0 5 21h18a1 1 0 0 0 .71-1.54L22 17.75v-5.5c0-3.9-2.44-7.13-6-7.98V4a2 2 0 0 0-2-2zm-3 20a3 3 0 0 0 6 0h-6z"/></svg>
            <?php if ($unread_notifs > 0): ?>
            <span class="fb-badge" id="notifBadge"><?= $unread_notifs ?></span>
            <?php endif; ?>
        </button>

        <!-- Menu Profil -->
        <div class="fb-user-pill" onclick="toggleProfileDropdown()">
            <img src="<?= htmlspecialchars($user_photo) ?>" class="fb-user-avatar" alt="Avatar">
            <span class="fb-user-name"><?= $user_first_name ?></span>
        </div>
    </div>
</header>

<!-- ============================================================
     DROPDOWN NOTIFICATIONS
     ============================================================ -->
<div class="fb-popover-menu" id="notifDropdown">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <h3 style="font-size:1.3rem;font-weight:700;">Notifications</h3>
        <a href="notifications.php" style="font-size:0.85rem;color:var(--fb-blue);font-weight:600;">Tout voir</a>
    </div>
    <div id="notifList">
        <?php if (empty($recent_notifs)): ?>
            <p style="text-align:center;color:var(--text-secondary);padding:20px 0;">Aucune notification récente.</p>
        <?php else: ?>
            <?php foreach ($recent_notifs as $n): ?>
                <div style="display:flex;gap:10px;padding:8px;border-radius:8px;cursor:pointer;background:<?= $n['is_read'] ? 'transparent' : 'var(--bg-hover)' ?>" onclick="window.location.href='<?= htmlspecialchars($n['link'] ?: 'notifications.php') ?>'">
                    <div style="font-size:1.4rem;">🔔</div>
                    <div>
                        <div style="font-size:0.92rem;font-weight:<?= $n['is_read'] ? '400' : '600' ?>;color:var(--text-primary);"><?= htmlspecialchars($n['content']) ?></div>
                        <div style="font-size:0.75rem;color:var(--text-secondary);"><?= htmlspecialchars($n['created_at']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================
     DROPDOWN COMPTE & THEME SOMBRE
     ============================================================ -->
<div class="fb-popover-menu" id="profileDropdown">
    <div style="display:flex;align-items:center;gap:12px;padding:8px;background:var(--bg-hover);border-radius:10px;margin-bottom:12px;cursor:pointer;" onclick="window.location.href='profile.php?id=<?= $id ?>'">
        <img src="<?= htmlspecialchars($user_photo) ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover;" alt="">
        <div>
            <div style="font-weight:700;font-size:1.05rem;"><?= $user_full_name ?></div>
            <div style="font-size:0.85rem;color:var(--text-secondary);">Voir votre profil</div>
        </div>
    </div>
    <div class="fb-sidebar-divider"></div>
    <div class="fb-menu-item" onclick="toggleDarkMode()">
        <div class="fb-menu-icon" id="darkModeIcon">🌙</div>
        <div style="flex:1;">
            <div>Mode sombre</div>
            <div style="font-size:0.75rem;color:var(--text-secondary);" id="darkModeLabel">Désactivé</div>
        </div>
    </div>
    <a href="edit_profile.php" class="fb-menu-item">
        <div class="fb-menu-icon">✏️</div>
        <span>Modifier le profil</span>
    </a>
    <a href="totp_setup.php" class="fb-menu-item">
        <div class="fb-menu-icon">🔐</div>
        <span>Sécurité 2FA</span>
    </a>
    <?php if (($user['role'] ?? 'user') === 'admin'): ?>
    <a href="admin.php" class="fb-menu-item">
        <div class="fb-menu-icon">⚙️</div>
        <span>Panneau d'administration</span>
    </a>
    <?php endif; ?>
    <div class="fb-sidebar-divider"></div>
    <a href="logout.php" class="fb-menu-item" style="color:#e41e3f;">
        <div class="fb-menu-icon" style="color:#e41e3f;background:rgba(228,30,63,0.1);">🚪</div>
        <span>Se déconnecter</span>
    </a>
</div>

<!-- ============================================================
     CONTENEUR PRINCIPAL 3 COLONNES
     ============================================================ -->
<div class="fb-main-layout">

    <!-- COLONNE GAUCHE (RACCOURCIS & NAVIGATION) -->
    <aside class="fb-left-col">
        <a href="profile.php?id=<?= $id ?>" class="fb-menu-item">
            <img src="<?= htmlspecialchars($user_photo) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;" alt="">
            <span><?= $user_full_name ?></span>
        </a>
        <a href="accueil.php" class="fb-menu-item active">
            <div class="fb-menu-icon">🏠</div>
            <span>Fil d'actualité</span>
        </a>
        <a href="users.php" class="fb-menu-item">
            <div class="fb-menu-icon">👥</div>
            <span>Amis (Membres)</span>
        </a>
        <a href="feed.php" class="fb-menu-item">
            <div class="fb-menu-icon">📺</div>
            <span>Vidéos & Médias</span>
        </a>
        <div class="fb-menu-item" onclick="toggleMessengerDrawer()">
            <div class="fb-menu-icon">💬</div>
            <span>Messenger</span>
        </div>
        <a href="notifications.php" class="fb-menu-item">
            <div class="fb-menu-icon">🔔</div>
            <span>Notifications</span>
        </a>
        <a href="edit_profile.php" class="fb-menu-item">
            <div class="fb-menu-icon">⚙️</div>
            <span>Paramètres de profil</span>
        </a>
        <a href="totp_setup.php" class="fb-menu-item">
            <div class="fb-menu-icon">🔐</div>
            <span>Authentification 2FA</span>
        </a>
        <?php if (($user['role'] ?? 'user') === 'admin'): ?>
        <a href="admin.php" class="fb-menu-item">
            <div class="fb-menu-icon">🛡️</div>
            <span>Espace Admin</span>
        </a>
        <?php endif; ?>

        <div class="fb-sidebar-divider"></div>
        <div class="fb-footer-links">
            Confidentialité · Conditions · Publicités · Cookies · Plus · Forgab © 2026
        </div>
    </aside>

    <!-- COLONNE CENTRALE (STORIES + CRÉATEUR DE POST + FLUX) -->
    <main class="fb-center-col">

        <!-- CARROUSEL DES STORIES -->
        <div class="fb-stories-wrap">
            <!-- Carte Créer une story -->
            <div class="fb-story-card fb-story-create" onclick="triggerStoryUpload()">
                <input type="file" id="storyFileInput" accept="image/*,video/*" style="display:none">
                <img src="<?= htmlspecialchars($user_photo) ?>" class="fb-story-create-img" alt="Créer">
                <div class="fb-story-create-bottom">
                    <div class="fb-story-plus-btn">+</div>
                    <span class="fb-story-create-text">Créer une<br>story</span>
                </div>
            </div>

            <!-- Ma story active si existante -->
            <?php if ($user_story): ?>
            <div class="fb-story-card" onclick="viewStoryModal('<?= htmlspecialchars($user_story['image'], ENT_QUOTES) ?>', 'Votre story', '<?= htmlspecialchars($user_photo, ENT_QUOTES) ?>')">
                <img src="<?= htmlspecialchars($user_story['image'] ?: $user_photo) ?>" class="fb-story-friend-bg" alt="Votre story">
                <img src="<?= htmlspecialchars($user_photo) ?>" class="fb-story-avatar-badge" alt="">
                <div class="fb-story-gradient-overlay">
                    <span class="fb-story-author-name">Votre story</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stories des amis -->
            <?php foreach ($stories as $st): ?>
            <div class="fb-story-card" onclick="viewStoryModal('<?= htmlspecialchars($st['image'], ENT_QUOTES) ?>', '<?= htmlspecialchars($st['nom'].' '.$st['prenom'], ENT_QUOTES) ?>', '<?= htmlspecialchars($st['photo'] ?: 'default-user.png', ENT_QUOTES) ?>')">
                <img src="<?= htmlspecialchars($st['image']) ?>" class="fb-story-friend-bg" alt="Story">
                <img src="<?= htmlspecialchars($st['photo'] ?: 'default-user.png') ?>" class="fb-story-avatar-badge" alt="">
                <div class="fb-story-gradient-overlay">
                    <span class="fb-story-author-name"><?= htmlspecialchars($st['nom'].' '.$st['prenom']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- BLOC DE PUBLICATION (FACEBOOK COMPOSER CARD) -->
        <div class="fb-composer-card">
            <div class="fb-composer-top">
                <img src="<?= htmlspecialchars($user_photo) ?>" class="fb-composer-avatar" alt="Avatar">
                <button class="fb-composer-btn" onclick="openPostModal()">Quoi de neuf, <?= $user_first_name ?> ?</button>
            </div>
            <div class="fb-composer-divider"></div>
            <div class="fb-composer-actions">
                <button class="fb-composer-action-btn" onclick="openPostModal('video')">
                    <span style="color:#e41e3f;font-size:1.3rem;">📹</span> Vidéo en direct
                </button>
                <button class="fb-composer-action-btn" onclick="openPostModal('photo')">
                    <span style="color:#45bd62;font-size:1.3rem;">🖼️</span> Photo/vidéo
                </button>
                <button class="fb-composer-action-btn" onclick="openPostModal('feeling')">
                    <span style="color:#f7b125;font-size:1.3rem;">😊</span> Humeur/Activité
                </button>
            </div>
        </div>

        <!-- FLUX D'ACTUALITÉ DYNAMIQUE -->
        <div id="postsFeedContainer">
            <!-- Injecté via api_posts.php -->
        </div>

        <!-- Sentinelle IntersectionObserver -->
        <div id="feedSentinel" style="height:40px;margin:20px 0;text-align:center;color:var(--text-secondary);">
            <div id="feedSpinner" style="display:none;font-weight:600;">Chargement des publications...</div>
        </div>
        <div id="feedEndNotice" style="display:none;text-align:center;padding:24px;color:var(--text-secondary);font-weight:600;">
            Vous êtes à jour dans vos publications 🎉
        </div>

    </main>

    <!-- COLONNE DROITE (DEMANDES D'AMI & CONTACTS MESSENGER) -->
    <aside class="fb-right-col">
        <!-- Widget Demandes d'amis -->
        <?php if (!empty($friend_requests)): ?>
        <div class="fb-right-widget">
            <div class="fb-right-title">
                <span>Demandes d'amis</span>
                <a href="users.php" style="color:var(--fb-blue);font-size:0.85rem;font-weight:600;">Voir tout</a>
            </div>
            <?php foreach ($friend_requests as $fr): ?>
            <div class="fb-friend-req-card" id="req-card-<?= $fr['user_id'] ?>">
                <img src="<?= htmlspecialchars($fr['photo'] ?: 'default-user.png') ?>" class="fb-friend-req-avatar" alt="">
                <div class="fb-friend-req-info">
                    <div class="fb-friend-req-name"><?= htmlspecialchars($fr['nom'].' '.$fr['prenom']) ?></div>
                    <div class="fb-friend-req-actions">
                        <button class="fb-btn-confirm" onclick="respondFriendRequest(<?= $fr['user_id'] ?>, 'accept')">Confirmer</button>
                        <button class="fb-btn-delete" onclick="respondFriendRequest(<?= $fr['user_id'] ?>, 'reject')">Supprimer</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="fb-sidebar-divider"></div>
        <?php endif; ?>

        <!-- Widget Contacts (Amis) -->
        <div class="fb-right-widget">
            <div class="fb-right-title">
                <span>Contacts</span>
                <div style="display:flex;gap:8px;">
                    <span style="cursor:pointer;" onclick="document.getElementById('contactFilterInput').focus()">🔍</span>
                </div>
            </div>
            <input type="text" id="contactFilterInput" placeholder="Filtrer les contacts..." style="width:100%;padding:6px 12px;border-radius:20px;border:1px solid var(--divider);background:var(--bg-primary);color:var(--text-primary);margin-bottom:8px;outline:none;" onkeyup="filterContacts(this.value)">
            
            <div id="contactsList">
                <?php if (empty($friends)): ?>
                    <p style="color:var(--text-secondary);font-size:0.88rem;padding:8px 0;">Aucun ami connecté pour le moment. Trouvez des amis dans <a href="users.php" style="color:var(--fb-blue);">l'annuaire</a> !</p>
                <?php else: ?>
                    <?php foreach ($friends as $f): ?>
                    <div class="fb-contact-item" onclick="openMessengerBox(<?= intval($f['id']) ?>, '<?= htmlspecialchars($f['nom'].' '.$f['prenom'], ENT_QUOTES) ?>', '<?= htmlspecialchars($f['photo'] ?: 'default-user.png', ENT_QUOTES) ?>')">
                        <div class="fb-contact-avatar-wrap">
                            <img src="<?= htmlspecialchars($f['photo'] ?: 'default-user.png') ?>" class="fb-contact-avatar" alt="">
                            <span class="fb-online-dot"></span>
                        </div>
                        <span class="fb-contact-name"><?= htmlspecialchars($f['nom'].' '.$f['prenom']) ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </aside>

</div>

<!-- ============================================================
     MODAL CRÉATION DE PUBLICATION (FACEBOOK STYLE)
     ============================================================ -->
<div class="fb-modal-overlay" id="postCreateModal">
    <div class="fb-modal-dialog">
        <div class="fb-modal-header">
            <h3>Créer une publication</h3>
            <button class="fb-modal-close" onclick="closePostModal()">✕</button>
        </div>
        <div class="fb-modal-body">
            <form id="postCreateForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <img src="<?= htmlspecialchars($user_photo) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;" alt="">
                    <div>
                        <div style="font-weight:700;font-size:0.95rem;"><?= $user_full_name ?></div>
                        <div style="background:var(--bg-tertiary);border-radius:6px;padding:2px 8px;font-size:0.75rem;font-weight:600;display:inline-block;cursor:pointer;">
                            🌐 Public ▼
                        </div>
                    </div>
                </div>

                <!-- Textarea -->
                <textarea id="modalPostContent" name="content" placeholder="Quoi de neuf, <?= $user_first_name ?> ?" style="width:100%;min-height:100px;border:none;background:transparent;color:var(--text-primary);font-size:1.15rem;resize:none;outline:none;" oninput="adjustComposerTextarea(this)"></textarea>

                <!-- Palette Gradients (posts colorés Facebook) -->
                <div class="fb-gradients-palette" id="composerGradients">
                    <div class="fb-gradient-circle active" style="background:#888;" title="Normal" onclick="selectGradient('')"></div>
                    <div class="fb-gradient-circle" style="background:linear-gradient(45deg,#ff512f,#dd2476);" title="Sunset" onclick="selectGradient('linear-gradient(45deg,#ff512f,#dd2476)')"></div>
                    <div class="fb-gradient-circle" style="background:linear-gradient(45deg,#1877f2,#00c6ff);" title="Ocean" onclick="selectGradient('linear-gradient(45deg,#1877f2,#00c6ff)')"></div>
                    <div class="fb-gradient-circle" style="background:linear-gradient(45deg,#8e2de2,#4a00e0);" title="Purple" onclick="selectGradient('linear-gradient(45deg,#8e2de2,#4a00e0)')"></div>
                    <div class="fb-gradient-circle" style="background:linear-gradient(45deg,#11998e,#38ef7d);" title="Mint" onclick="selectGradient('linear-gradient(45deg,#11998e,#38ef7d)')"></div>
                    <div class="fb-gradient-circle" style="background:linear-gradient(45deg,#232526,#414345);" title="Dark" onclick="selectGradient('linear-gradient(45deg,#232526,#414345)')"></div>
                </div>

                <!-- Dropzone Média Prévisualisation -->
                <div id="composerMediaPreview" style="display:none;position:relative;margin:10px 0;max-height:240px;overflow:hidden;border-radius:10px;background:#000;">
                    <img id="composerPreviewImg" src="" style="width:100%;max-height:240px;object-fit:contain;display:none;">
                    <video id="composerPreviewVid" src="" controls style="width:100%;max-height:240px;display:none;"></video>
                    <button type="button" onclick="clearComposerMedia()" style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.6);color:#fff;border:none;border-radius:50%;width:30px;height:30px;cursor:pointer;">✕</button>
                </div>

                <input type="file" id="composerFileInput" name="media" accept="image/*,video/*" style="display:none" onchange="handleComposerMedia(this)">

                <!-- Outils d'enrichissement -->
                <div style="border:1px solid var(--divider);border-radius:10px;padding:8px 14px;display:flex;align-items:center;justify-content:space-between;margin:14px 0;">
                    <span style="font-weight:600;font-size:0.9rem;">Ajouter à votre publication</span>
                    <div style="display:flex;gap:12px;font-size:1.3rem;">
                        <span style="cursor:pointer;" title="Photo/vidéo" onclick="document.getElementById('composerFileInput').click()">🖼️</span>
                        <span style="cursor:pointer;" title="Humeur" onclick="insertFeeling('😊 se sent Heureux')">😊</span>
                        <span style="cursor:pointer;" title="Fête" onclick="insertFeeling('🎉 fait la Fête')">🎉</span>
                        <span style="cursor:pointer;" title="Amour" onclick="insertFeeling('❤️ se sent Aimé')">❤️</span>
                    </div>
                </div>

                <button type="submit" id="btnSubmitPost" style="width:100%;background:var(--fb-blue);color:#fff;border:none;border-radius:8px;padding:12px;font-weight:700;font-size:1rem;cursor:pointer;">
                    Publier
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL VISIONNEUSE DE STORIES (PLEIN ÉCRAN)
     ============================================================ -->
<div class="fb-modal-overlay" id="storyViewerModal" onclick="closeStoryModal()">
    <div style="position:relative;max-width:440px;width:95vw;height:85vh;background:#000;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.8);display:flex;flex-direction:column;" onclick="event.stopPropagation()">
        <!-- Barre de progression -->
        <div style="position:absolute;top:10px;left:10px;right:10px;display:flex;gap:4px;z-index:10;">
            <div style="flex:1;height:3px;background:rgba(255,255,255,0.4);border-radius:2px;overflow:hidden;">
                <div id="storyProgressBar" style="height:100%;background:#fff;width:0%;transition:width 5s linear;"></div>
            </div>
        </div>

        <!-- En-tête Story -->
        <div style="position:absolute;top:20px;left:16px;right:16px;display:flex;align-items:center;justify-content:space-between;z-index:10;">
            <div style="display:flex;align-items:center;gap:10px;">
                <img id="storyViewerAvatar" src="" style="width:36px;height:36px;border-radius:50%;border:2px solid #fff;" alt="">
                <span id="storyViewerAuthor" style="color:#fff;font-weight:700;font-size:0.95rem;text-shadow:0 1px 4px rgba(0,0,0,0.8);"></span>
            </div>
            <button onclick="closeStoryModal()" style="background:rgba(0,0,0,0.5);color:#fff;border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1.1rem;">✕</button>
        </div>

        <!-- Média -->
        <div id="storyViewerMediaContainer" style="flex:1;display:flex;align-items:center;justify-content:center;background:#111;"></div>
    </div>
</div>

<!-- ============================================================
     MESSENGER FLOATING DOCK (FENÊTRE CHAT STYLE DESKTOP)
     ============================================================ -->
<div class="fb-messenger-dock" id="messengerDock">
    <div class="fb-messenger-header">
        <img id="dockFriendAvatar" src="default-user.png" class="fb-messenger-avatar" alt="">
        <div class="fb-messenger-title">
            <span id="dockFriendName">Ami</span>
            <small>En ligne</small>
        </div>
        <div class="fb-messenger-tools">
            <button class="fb-messenger-tool-btn" title="Appel Audio" onclick="startCallFromDock('audio')">📞</button>
            <button class="fb-messenger-tool-btn" title="Appel Vidéo" onclick="startCallFromDock('video')">📹</button>
            <button class="fb-messenger-tool-btn" title="Fermer" onclick="closeMessengerDock()">✕</button>
        </div>
    </div>
    <div class="fb-messenger-body" id="dockMessagesList"></div>
    <form class="fb-messenger-footer" id="dockChatForm">
        <input type="text" id="dockChatInput" class="fb-messenger-input" placeholder="Aa" autocomplete="off">
        <button type="submit" style="background:none;border:none;color:var(--fb-blue);font-size:1.3rem;cursor:pointer;">➤</button>
    </form>
</div>

<!-- Conteneur Toasts Notifications -->
<div class="fb-toast-container" id="toastContainer"></div>

<!-- ============================================================
     SCRIPTS & INTERACTIONS TEMPS RÉEL (SPA-GRADE)
     ============================================================ -->
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const ME_ID = <?= intval($id) ?>;
const EMOJI_MAP = {
    like: { icon: '👍', name: "J'aime", color: 'var(--fb-blue)' },
    love: { icon: '❤️', name: "J'adore", color: '#f02849' },
    haha: { icon: '😂', name: 'Haha', color: '#f7b125' },
    wow:  { icon: '😮', name: 'Wouah', color: '#f7b125' },
    sad:  { icon: '😢', name: 'Triste', color: '#f7b125' },
    angry:{ icon: '😡', name: 'En colère', color: '#e41e3f' },
    dislike:{ icon: '👎', name: 'Je n\'aime pas', color: '#65676b' }
};

/* ===== DARK MODE ===== */
function toggleDarkMode() {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('fb_theme', isDark ? 'dark' : 'light');
    updateThemeUI();
}
function updateThemeUI() {
    const isDark = document.documentElement.classList.contains('dark');
    const lbl = document.getElementById('darkModeLabel');
    const icon = document.getElementById('darkModeIcon');
    if (lbl) lbl.textContent = isDark ? 'Activé' : 'Désactivé';
    if (icon) icon.textContent = isDark ? '☀️' : '🌙';
}
updateThemeUI();

/* ===== DROPDOWNS POPUP ===== */
function toggleNotifDropdown() {
    const p = document.getElementById('notifDropdown');
    const isVisible = p.style.display === 'block';
    closeAllPopovers();
    if (!isVisible) p.style.display = 'block';
}
function toggleProfileDropdown() {
    const p = document.getElementById('profileDropdown');
    const isVisible = p.style.display === 'block';
    closeAllPopovers();
    if (!isVisible) p.style.display = 'block';
}
function closeAllPopovers() {
    document.getElementById('notifDropdown').style.display = 'none';
    document.getElementById('profileDropdown').style.display = 'none';
}
window.addEventListener('click', e => {
    if (!e.target.closest('#btnNotifToggle') && !e.target.closest('#notifDropdown') &&
        !e.target.closest('.fb-user-pill') && !e.target.closest('#profileDropdown')) {
        closeAllPopovers();
    }
});

/* ===== GESTION DU FLUX INFINI DES PUBLICATIONS ===== */
let currentPage = 0;
let isLoadingFeed = false;
let hasMoreFeed = true;
const feedContainer = document.getElementById('postsFeedContainer');
const sentinel = document.getElementById('feedSentinel');
const spinner = document.getElementById('feedSpinner');
const endNotice = document.getElementById('feedEndNotice');

function esc(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function parseFormattedText(text) {
    let t = esc(text);
    // Liens cliquables
    t = t.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener" style="color:var(--fb-blue);word-break:break-all;">$1</a>');
    // Hashtags
    t = t.replace(/(#\w+)/g, '<span style="color:var(--fb-blue);font-weight:600;">$1</span>');
    return t.replace(/\n/g, '<br>');
}

function renderPostCard(p) {
    const isVideo = p.media && /\.(mp4|webm)$/i.test(p.media);
    const hasMedia = Boolean(p.media);

    const totalReactions = (p.r_like||0)+(p.r_love||0)+(p.r_haha||0)+(p.r_wow||0)+(p.r_sad||0)+(p.r_angry||0)+(p.r_dislike||0);
    const commentCount = p.comment_count || 0;

    const myReact = p.my_reaction ? EMOJI_MAP[p.my_reaction] : null;
    const btnLabel = myReact ? `${myReact.icon} ${myReact.name}` : `👍 J'aime`;
    const btnColor = myReact ? myReact.color : 'inherit';

    const card = document.createElement('article');
    card.className = 'fb-post-card';
    card.id = `post-${p.id}`;

    const isAuthor = (parseInt(p.user_id) === ME_ID);

    card.innerHTML = `
        <div class="fb-post-header">
            <a href="profile.php?id=${p.user_id}">
                <img src="${esc(p.photo || 'default-user.png')}" class="fb-post-avatar" alt="">
            </a>
            <div class="fb-post-user-info">
                <a href="profile.php?id=${p.user_id}" class="fb-post-author">${esc(p.nom + ' ' + p.prenom)}</a>
                <div class="fb-post-meta">
                    <span>${esc(p.created_at)}</span> · <span>🌐</span>
                </div>
            </div>
            <button class="fb-post-dots" onclick="togglePostMenu(${p.id})">•••</button>
            <div id="post-menu-${p.id}" class="fb-popover-menu" style="width:200px;top:44px;right:10px;padding:6px;">
                <div class="fb-menu-item" onclick="navigator.clipboard.writeText(window.location.origin+'/accueil.php#post-${p.id}');alert('Lien copié !');">🔗 Copier le lien</div>
                ${isAuthor ? `<div class="fb-menu-item" style="color:#e41e3f;" onclick="deletePost(${p.id})">🗑️ Supprimer</div>` : ''}
            </div>
        </div>

        <div class="fb-post-content">${parseFormattedText(p.content)}</div>

        ${hasMedia ? `
            <div class="fb-post-media-wrap">
                ${isVideo ? `<video src="${esc(p.media)}" controls playsinline></video>` : `<img src="${esc(p.media)}" alt="Média" loading="lazy">`}
            </div>
        ` : ''}

        <div class="fb-post-stats">
            <div class="fb-stats-reactions" id="stats-react-${p.id}">
                <div class="fb-stats-icons">
                    <span class="fb-stats-icon" style="background:#1877f2;">👍</span>
                    <span class="fb-stats-icon" style="background:#f02849;">❤️</span>
                </div>
                <span id="stats-count-${p.id}">${totalReactions > 0 ? totalReactions : ''}</span>
            </div>
            <div style="cursor:pointer;" onclick="toggleCommentsDrawer(${p.id})">
                <span id="stats-comm-${p.id}">${commentCount > 0 ? commentCount + ' commentaires' : ''}</span>
            </div>
        </div>

        <div class="fb-post-actions-bar">
            <div class="fb-reaction-wrap">
                <button class="fb-post-action-btn" id="react-btn-${p.id}" style="color:${btnColor}" onclick="reactPost(${p.id}, 'like')">
                    ${btnLabel}
                </button>
                <div class="fb-reaction-picker">
                    <span class="fb-reaction-item" title="J'aime" onclick="reactPost(${p.id},'like')">👍</span>
                    <span class="fb-reaction-item" title="J'adore" onclick="reactPost(${p.id},'love')">❤️</span>
                    <span class="fb-reaction-item" title="Haha" onclick="reactPost(${p.id},'haha')">😂</span>
                    <span class="fb-reaction-item" title="Wouah" onclick="reactPost(${p.id},'wow')">😮</span>
                    <span class="fb-reaction-item" title="Triste" onclick="reactPost(${p.id},'sad')">😢</span>
                    <span class="fb-reaction-item" title="En colère" onclick="reactPost(${p.id},'angry')">😡</span>
                </div>
            </div>
            <button class="fb-post-action-btn" onclick="toggleCommentsDrawer(${p.id})">
                💬 Commenter
            </button>
            <button class="fb-post-action-btn" onclick="sharePost(${p.id})">
                🔗 Partager
            </button>
        </div>

        <!-- Tiroir de Commentaires -->
        <div class="fb-comments-section" id="comments-drawer-${p.id}">
            <div id="comments-list-${p.id}" style="margin-bottom:10px;"></div>
            <form class="fb-comment-form" onsubmit="return submitComment(this, ${p.id})">
                <img src="<?= htmlspecialchars($user_photo) ?>" class="fb-comment-avatar" alt="">
                <div class="fb-comment-input-wrap">
                    <input type="text" name="comment" class="fb-comment-input" placeholder="Écrivez un commentaire..." required>
                    <button type="submit" class="fb-comment-submit">➤</button>
                </div>
            </form>
        </div>
    `;
    return card;
}

function loadFeedPosts() {
    if (isLoadingFeed || !hasMoreFeed) return;
    isLoadingFeed = true;
    currentPage++;
    spinner.style.display = 'block';

    fetch(`api_posts.php?page=${currentPage}`)
        .then(r => r.json())
        .then(res => {
            spinner.style.display = 'none';
            if (res.posts && res.posts.length > 0) {
                res.posts.forEach(p => feedContainer.appendChild(renderPostCard(p)));
            }
            hasMoreFeed = res.has_more;
            if (!hasMoreFeed) endNotice.style.display = 'block';
            isLoadingFeed = false;
        })
        .catch(() => {
            spinner.style.display = 'none';
            isLoadingFeed = false;
        });
}

const observer = new IntersectionObserver(entries => {
    if (entries[0].isIntersecting) loadFeedPosts();
}, { rootMargin: '200px' });
observer.observe(sentinel);
loadFeedPosts();

/* ===== INTERACTIONS POST (RÉACTION, SUPPRESSION, PARTAGE) ===== */
function reactPost(postId, reaction) {
    fetch('ajax_like.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `post_id=${postId}&reaction=${reaction}&csrf_token=${encodeURIComponent(CSRF)}`
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) return;
        const btn = document.getElementById(`react-btn-${postId}`);
        const countSpan = document.getElementById(`stats-count-${postId}`);
        
        let total = 0;
        for (const [k, v] of Object.entries(res.counts || {})) total += v;
        if (countSpan) countSpan.textContent = total > 0 ? total : '';

        if (res.my_reaction && EMOJI_MAP[res.my_reaction]) {
            const m = EMOJI_MAP[res.my_reaction];
            btn.innerHTML = `${m.icon} ${m.name}`;
            btn.style.color = m.color;
        } else {
            btn.innerHTML = `👍 J'aime`;
            btn.style.color = 'inherit';
        }
    });
}

function deletePost(postId) {
    if (!confirm('Voulez-vous vraiment supprimer cette publication ?')) return;
    fetch('ajax_delete_post.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `post_id=${postId}&csrf_token=${encodeURIComponent(CSRF)}`
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const el = document.getElementById(`post-${postId}`);
            if (el) el.remove();
        } else {
            alert(res.error || 'Erreur lors de la suppression');
        }
    });
}

function sharePost(postId) {
    fetch('ajax_share.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `post_id=${postId}&csrf_token=${encodeURIComponent(CSRF)}`
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) showToast("Publication partagée avec succès sur votre profil !");
    });
}

function togglePostMenu(postId) {
    const m = document.getElementById(`post-menu-${postId}`);
    if (m) m.style.display = (m.style.display === 'block') ? 'none' : 'block';
}

/* ===== COMMENTAIRES ===== */
function toggleCommentsDrawer(postId) {
    const drawer = document.getElementById(`comments-drawer-${postId}`);
    drawer.classList.toggle('open');
    if (drawer.classList.contains('open')) {
        loadComments(postId);
    }
}

function loadComments(postId) {
    fetch(`ajax_comment.php?post_id=${postId}`)
        .then(r => r.text())
        .then(html => {
            document.getElementById(`comments-list-${postId}`).innerHTML = html;
        });
}

function submitComment(form, postId) {
    const fd = new FormData(form);
    fd.append('post_id', postId);
    fd.append('csrf_token', CSRF);

    fetch('ajax_comment.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                form.reset();
                loadComments(postId);
                const s = document.getElementById(`stats-comm-${postId}`);
                if (s && !s.textContent) s.textContent = "1 commentaire";
            }
        });
    return false;
}

function deleteComment(commentId, postId) {
    if (!confirm('Supprimer ce commentaire ?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('comment_id', commentId);
    fd.append('csrf_token', CSRF);

    fetch('ajax_comment.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) loadComments(postId);
        });
}

/* ===== COMPOSER MODAL DE PUBLICATION ===== */
function openPostModal(hint) {
    document.getElementById('postCreateModal').style.display = 'flex';
    document.getElementById('modalPostContent').focus();
    if (hint === 'photo' || hint === 'video') {
        document.getElementById('composerFileInput').click();
    }
}
function closePostModal() {
    document.getElementById('postCreateModal').style.display = 'none';
}
function adjustComposerTextarea(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}
function handleComposerMedia(input) {
    const file = input.files[0];
    if (!file) return;
    const isVid = file.type.startsWith('video/');
    const previewBox = document.getElementById('composerMediaPreview');
    const pImg = document.getElementById('composerPreviewImg');
    const pVid = document.getElementById('composerPreviewVid');
    const url = URL.createObjectURL(file);

    previewBox.style.display = 'block';
    if (isVid) {
        pImg.style.display = 'none';
        pVid.style.display = 'block';
        pVid.src = url;
    } else {
        pVid.style.display = 'none';
        pImg.style.display = 'block';
        pImg.src = url;
    }
}
function clearComposerMedia() {
    document.getElementById('composerFileInput').value = '';
    document.getElementById('composerMediaPreview').style.display = 'none';
    document.getElementById('composerPreviewImg').src = '';
    document.getElementById('composerPreviewVid').src = '';
}
function selectGradient(grad) {
    const area = document.getElementById('modalPostContent');
    if (grad) {
        area.style.background = grad;
        area.style.color = '#fff';
        area.style.textAlign = 'center';
        area.style.padding = '30px 14px';
        area.style.fontWeight = '700';
    } else {
        area.style.background = 'transparent';
        area.style.color = 'var(--text-primary)';
        area.style.textAlign = 'left';
        area.style.padding = '0';
        area.style.fontWeight = '400';
    }
}
function insertFeeling(feel) {
    const area = document.getElementById('modalPostContent');
    area.value += (area.value ? ' ' : '') + feel;
    area.focus();
}

document.getElementById('postCreateForm').onsubmit = function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitPost');
    btn.disabled = true;
    btn.textContent = 'Publication en cours...';

    const fd = new FormData(this);
    fetch('ajax_post.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.textContent = 'Publier';
            if (res.success) {
                this.reset();
                clearComposerMedia();
                selectGradient('');
                closePostModal();
                // Recharger le feed
                feedContainer.innerHTML = '';
                currentPage = 0;
                hasMoreFeed = true;
                observer.observe(sentinel);
                loadFeedPosts();
            } else {
                alert(res.error || 'Erreur lors de la publication');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = 'Publier';
            alert('Erreur réseau');
        });
};

/* ===== GESTION DES STORIES ===== */
function triggerStoryUpload() {
    document.getElementById('storyFileInput').click();
}
document.getElementById('storyFileInput').addEventListener('change', function() {
    if (!this.files.length) return;
    const fd = new FormData();
    fd.append('media', this.files[0]);
    fd.append('csrf_token', CSRF);

    fetch('ajax_story.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast("Votre story a été publiée avec succès !");
                setTimeout(() => window.location.reload(), 1200);
            } else {
                alert(res.error || "Échec de l'ajout de la story");
            }
        });
});

let storyTimer = null;
function viewStoryModal(mediaUrl, author, avatar) {
    const modal = document.getElementById('storyViewerModal');
    const container = document.getElementById('storyViewerMediaContainer');
    const authorEl = document.getElementById('storyViewerAuthor');
    const avatarEl = document.getElementById('storyViewerAvatar');
    const bar = document.getElementById('storyProgressBar');

    authorEl.textContent = author;
    avatarEl.src = avatar;

    const isVid = /\.(mp4|webm)$/i.test(mediaUrl);
    container.innerHTML = isVid
        ? `<video src="${esc(mediaUrl)}" controls autoplay style="max-width:100%;max-height:100%;object-fit:contain;"></video>`
        : `<img src="${esc(mediaUrl)}" style="max-width:100%;max-height:100%;object-fit:contain;" alt="">`;

    modal.style.display = 'flex';
    bar.style.width = '0%';
    setTimeout(() => { bar.style.width = '100%'; }, 50);

    clearTimeout(storyTimer);
    storyTimer = setTimeout(() => { closeStoryModal(); }, 5000);
}
function closeStoryModal() {
    document.getElementById('storyViewerModal').style.display = 'none';
    document.getElementById('storyViewerMediaContainer').innerHTML = '';
    clearTimeout(storyTimer);
}

/* ===== CONTACTS & DEMANDES D'AMI ===== */
function filterContacts(query) {
    const q = query.toLowerCase();
    const items = document.querySelectorAll('.fb-contact-item');
    items.forEach(it => {
        const name = it.querySelector('.fb-contact-name').textContent.toLowerCase();
        it.style.display = name.includes(q) ? 'flex' : 'none';
    });
}

function respondFriendRequest(fromUserId, action) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('from_user', fromUserId);
    fd.append('csrf_token', CSRF);

    fetch('friend_request.php', { method: 'POST', body: fd })
        .then(() => {
            const card = document.getElementById(`req-card-${fromUserId}`);
            if (card) card.remove();
            showToast(action === 'accept' ? 'Demande d\'ami acceptée !' : 'Demande refusée.');
        });
}

/* ===== MESSENGER FLOATING DOCK (CHAT LIVE DOCK) ===== */
let activeMessengerWith = null;
let messengerSSE = null;

function openMessengerBox(friendId, friendName, friendPhoto) {
    activeMessengerWith = friendId;
    document.getElementById('dockFriendName').textContent = friendName;
    document.getElementById('dockFriendAvatar').src = friendPhoto;
    document.getElementById('messengerDock').style.display = 'flex';

    loadMessengerMessages(friendId);
    connectMessengerSSE(friendId);
}

function closeMessengerDock() {
    document.getElementById('messengerDock').style.display = 'none';
    activeMessengerWith = null;
    if (messengerSSE) { messengerSSE.close(); messengerSSE = null; }
}

function toggleMessengerDrawer() {
    const contacts = document.querySelectorAll('.fb-contact-item');
    if (contacts.length > 0) {
        contacts[0].click();
    } else {
        window.location.href = 'chat.php';
    }
}

function loadMessengerMessages(friendId) {
    const box = document.getElementById('dockMessagesList');
    box.innerHTML = '<div style="text-align:center;color:var(--text-secondary);font-size:0.8rem;padding:10px;">Chargement...</div>';

    fetch(`ajax_chat.php?with=${friendId}`)
        .then(r => r.json())
        .then(msgs => {
            box.innerHTML = '';
            msgs.forEach(appendDockMessage);
            box.scrollTop = box.scrollHeight;
        });
}

function appendDockMessage(msg) {
    const box = document.getElementById('dockMessagesList');
    const isMe = (parseInt(msg.sender_id) === ME_ID);
    const bubble = document.createElement('div');
    bubble.className = `fb-msg-bubble ${isMe ? 'me' : ''}`;
    bubble.innerHTML = esc(msg.content);
    box.appendChild(bubble);
    box.scrollTop = box.scrollHeight;
}

function connectMessengerSSE(friendId) {
    if (messengerSSE) messengerSSE.close();
    messengerSSE = new EventSource(`sse_chat.php?with=${friendId}`);
    messengerSSE.onmessage = e => {
        const data = JSON.parse(e.data);
        if (data.content) appendDockMessage(data);
    };
}

document.getElementById('dockChatForm').onsubmit = function(e) {
    e.preventDefault();
    const input = document.getElementById('dockChatInput');
    const val = input.value.trim();
    if (!val || !activeMessengerWith) return;

    const fd = new FormData();
    fd.append('message', val);
    fd.append('with', activeMessengerWith);
    fd.append('csrf_token', CSRF);

    fetch(`ajax_chat.php?with=${activeMessengerWith}`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                input.value = '';
            }
        });
};

function startCallFromDock(type) {
    if (!activeMessengerWith) return;
    window.open(`call.php?with=${activeMessengerWith}&type=${type}`, '_blank', 'width=460,height=520');
}

/* ===== NOTIFICATIONS TOAST EN TEMPS RÉEL (SSE) ===== */
(function() {
    let lastId = 0;
    function connectNotifStream() {
        const es = new EventSource(`sse_notifications.php?last_id=${lastId}`);
        es.onmessage = e => {
            const data = JSON.parse(e.data);
            if (data.close) { es.close(); setTimeout(connectNotifStream, 1500); return; }
            lastId = Math.max(lastId, data.id);
            showToast(data.content, data.link);
            const b = document.getElementById('notifBadge');
            if (b) b.textContent = parseInt(b.textContent || 0) + 1;
        };
        es.onerror = () => { es.close(); setTimeout(connectNotifStream, 5000); };
    }
    connectNotifStream();
})();

function showToast(msg, link) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'fb-toast';
    toast.innerHTML = `<span>🔔</span> <span style="font-size:0.92rem;font-weight:600;">${esc(msg)}</span>`;
    if (link) toast.onclick = () => window.location.href = link;
    container.appendChild(toast);
    setTimeout(() => { toast.remove(); }, 5000);
}

/* Service Worker PWA */
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(()=>{});
}
</script>
</body>
</html>
