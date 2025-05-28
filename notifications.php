<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];
$conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$user_id");
$notifs = $conn->query("SELECT * FROM notifications WHERE user_id=$user_id ORDER BY created_at DESC LIMIT 40")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Notifications</title>
   <style>
/* 
  Animated - Minimaliste - Deep Space Theme 
  Utilise le switch JS plus bas pour changer de thème.
*/

:root {
  --bg: #f7fafd;
  --card: #fff;
  --primary: #1a3e72;
  --accent: #00ffe7;
  --text: #18223b;
  --border: #dee4ef;
  --shadow: 0 2px 18px #0002;
  --radius: 16px;
  --loader: #1a3e72;
}

:root[data-theme="deep"] {
  --bg: #090f1b;
  --card: rgba(16,18,40,0.8);
  --primary: #00ffe7;
  --accent: #8be9fd;
  --text: #e7f8fa;
  --border: #293a51;
  --shadow: 0 2px 32px #00ffe733;
  --loader: #00ffe7;
}

html, body {
  background: var(--bg);
  color: var(--text);
  font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
  min-height: 100vh;
  margin: 0;
  transition: background 0.7s cubic-bezier(.87,.01,.17,.99), color 0.5s cubic-bezier(.64,.09,.08,1);
  overflow-x: hidden;
}

body[data-theme="deep"]::before {
  content: '';
  position: fixed;
  top: 0; left: 0; width: 100vw; height: 100vh;
  z-index: 0;
  pointer-events: none;
  background:
    radial-gradient(ellipse 60vw 40vh at 90vw 10vh, #00ffe711 0%, transparent 100%),
    radial-gradient(ellipse 50vw 80vh at 10vw 100vh, #8be9fd22 0%, transparent 100%),
    radial-gradient(circle 30vw at 60vw 70vh, #00ffe72c 0%, transparent 100%);
  animation: spaceGlow 8s ease-in-out infinite alternate;
  opacity: .9;
}
@keyframes spaceGlow {
  0% { filter: blur(0px) brightness(1);}
  100% { filter: blur(8px) brightness(1.2);}
}

/* Animated floating stars (deep space mode) */
body[data-theme="deep"] .stars {
  z-index: 1;
  position: fixed;
  left:0; top:0; width: 100vw; height: 100vh; pointer-events: none; overflow: hidden;
}
.stars span {
  position: absolute; border-radius: 50%; background: #fff9; opacity:.7;
  animation: star-float 8s linear infinite;
}
.stars span:nth-child(1) { left:10vw; top:7vh; width:2px; height:2px; animation-delay:0s;}
.stars span:nth-child(2) { left:30vw; top:50vh; width:1.7px; height:1.7px; animation-delay:1.2s;}
.stars span:nth-child(3) { left:70vw; top:20vh; width:2.3px; height:2.3px; animation-delay:2.8s;}
.stars span:nth-child(4) { left:90vw; top:80vh; width:2.8px; height:2.8px; animation-delay:4s;}
.stars span:nth-child(5) { left:40vw; top:15vh; width:2px; height:2px; animation-delay:3.4s;}
.stars span:nth-child(6) { left:80vw; top:55vh; width:1.5px; height:1.5px; animation-delay:2s;}
@keyframes star-float {
  0% { opacity:.3; transform: translateY(0);}
  50% { opacity:1;}
  100% { opacity:.3; transform: translateY(-14vh);}
}

/* --------- Minimaliste --------- */
.container, .card, .chat-box {
  background: var(--card);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 2.2rem 1.2rem;
  margin: 2rem auto;
  max-width: 480px;
  text-align: center;
  border: 1.5px solid var(--border);
  position: relative;
}
.card, .chat-box { padding: 1.2rem 0.7rem; }
.profile-pic {
  width: 88px; height: 88px; border-radius: 50%; object-fit: cover;
  border: 2px solid var(--primary); margin-bottom: 7px; background: var(--border);
}
h1, h2, h3 {
  color: var(--primary);
  font-weight: 800;
  font-size: 1.35em;
  margin: 12px 0 12px 0;
  letter-spacing: .5px;
}
[data-theme="deep"] h1, [data-theme="deep"] h2, [data-theme="deep"] h3 { color: var(--accent); }

button, .profile_button, .btn {
  background: var(--primary);
  color: #fff;
  border: none;
  border-radius: 12px;
  padding: 0.7em 1.5em;
  font-size: 1.08em;
  font-weight: 700;
  cursor: pointer;
  margin: 8px 3px 0 3px;
  transition: background 0.22s, color 0.22s, transform 0.18s;
  box-shadow: 0 1px 6px var(--primary)22;
}
button:hover, .btn:hover, .profile_button:hover {
  background: var(--accent);
  color: var(--primary-dark);
  transform: translateY(-2px) scale(1.03);
}

/* Champs */
input, select, textarea {
  width: 100%;
  padding: 0.72em 1em;
  border-radius: 7px;
  border: 1.5px solid var(--border);
  background: var(--bg);
  font-size: 1em;
  color: var(--text);
  margin-bottom: 11px;
  box-sizing: border-box;
  transition: border 0.22s;
}
input:focus, select:focus, textarea:focus { border-color: var(--primary); outline: none; }

a { color: var(--primary); text-decoration: none; font-weight: 600;}
a:hover { color: var(--accent); text-decoration: underline; }

::-webkit-scrollbar { width: 9px; background: var(--bg);}
::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 9px;}
/* --------- Chat Box --------- */
.chat-box {
  min-height: 210px;
  max-height: 320px;
  overflow-y: auto;
  text-align: left;
  margin-bottom: 12px;
}
.chat-message {
  margin-bottom: 13px;
  padding: 7px 1.2rem;
  border-radius: 9px;
  background: var(--border);
  color: inherit;
  display: inline-block;
  max-width: 80%;
  font-size: 1em;
  word-break: break-word;
  box-shadow: 0 1px 6px #1a3e7220;
  position: relative;
  animation: msgFadeIn 0.7s cubic-bezier(.7,.01,.32,1.09);
}
@keyframes msgFadeIn { from { opacity:0; transform: translateY(14px);} to { opacity:1; transform:none;}}
.chat-message.me {
  background: var(--primary);
  color: #fff;
  margin-left: 20%;
  text-align: right;
  animation-delay: .12s;
}
.chat-message img, .chat-message video {
  max-width: 180px; max-height: 110px; display: block; margin: 6px 0; border-radius: 7px;
  box-shadow: 0 0 8px var(--primary)11;
}
.chat-message audio { width: 140px; }
.chat-message .seen { color: var(--accent); margin-left: 5px;}
.chat-message .notseen { color: #aaa; margin-left: 4px;}
.chat-message button { background:none; border:none; color:var(--danger); font-size:1.1em; cursor:pointer; }

/* --------- Animated loader (CSS only, deep space style) --------- */
.loader {
  display: none;
  position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
  background: radial-gradient(circle at 60vw 30vh, var(--accent)11 0%, transparent 90%),
              radial-gradient(circle at 30vw 80vh, var(--primary)22 0%, transparent 90%);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(3px) brightness(0.8);
}
.loader.show { display: flex; }
.loader .orbit {
  width: 70px; height: 70px; position: relative; border-radius: 50%;
  border: 2.5px dashed var(--primary);
  border-top: 2.5px solid var(--accent);
  animation: orbitSpin 1.1s infinite linear;
}
.loader .sparkle {
  position: absolute; top: 30px; left: 30px; width: 10px; height: 10px; border-radius: 50%;
  background: var(--accent);
  box-shadow: 0 0 18px 5px var(--accent), 0 0 2px 2px #fff;
  animation: sparklePulse 1.5s infinite alternate;
}
@keyframes orbitSpin { 100% {transform: rotate(360deg);} }
@keyframes sparklePulse { 0%{opacity:0.6;} 100%{opacity:1; box-shadow:0 0 32px 11px var(--accent);} }

/* --------- Switch/Toggle Theme (minimal js: html.setAttribute('data-theme','deep')) --------- */
.theme-switch {
  position: fixed;
  right: 14px; bottom: 18px; background: var(--primary);
  color: #fff; border-radius: 20px; font-size: 1.1em;
  padding: 10px 16px; cursor: pointer; z-index: 10;
  box-shadow: 0 2px 12px var(--primary)33;
  user-select: none;
  transition: background 0.2s;
}
.theme-switch:hover { background: var(--accent); color: var(--primary-dark);}
.theme-switch::after { content: attr(data-label); margin-left: 8px; font-size: 0.93em; }

@media (max-width: 650px) {
  .container, .card, .chat-box { max-width: 99vw; padding: 7vw 2vw;}
  .profile-pic { width: 54px; height: 54px; }
  .theme-switch { right: 10px; bottom: 8px; font-size: 1em;}
}
    </style>

</head>
<body>
    <div style="text-align:center;margin-top:12px;">
      <img src="site-logo.png" alt="Logo du site" style="max-width:90px;">
    </div>
    <div class="container">
        <h2>Vos notifications</h2>
        <?php foreach($notifs as $n): ?>
            <div style="margin-bottom:16px;padding:9px;background:#f7f7f7;border-radius:6px;">
                <?= htmlspecialchars($n['content']) ?>
                <?php if($n['link']): ?>
                    <a href="<?= htmlspecialchars($n['link']) ?>">Voir</a>
                <?php endif; ?>
                <span style="float:right;color:#999;"><?= $n['created_at'] ?></span>
            </div>
        <?php endforeach; ?>
        <a href="feed.php" class="profile_button">Retour au fil</a>
    </div>
</body>
</html>