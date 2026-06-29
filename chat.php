<?php
require 'security.php';
session_start();
require 'config.php';

// Sécurisation & récupération infos utilisateur / ami
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT photo, nom, prenom FROM users WHERE id=?");
$stmt->execute([$user_id]);
$me_row = $stmt->fetch();
$my_photo   = ($me_row['photo'] && file_exists($me_row['photo'])) ? $me_row['photo'] : 'default-user.png';
$my_nom     = $me_row['nom'] ?? '';
$my_prenom  = $me_row['prenom'] ?? '';

$with = intval($_GET['with'] ?? 0);
$stmt = $pdo->prepare("SELECT nom, prenom, photo FROM users WHERE id=?");
$stmt->execute([$with]);
$fr_row = $stmt->fetch();
$friend_nom    = $fr_row['nom'] ?? 'Utilisateur';
$friend_prenom = $fr_row['prenom'] ?? '';
$friend_photo  = ($fr_row['photo'] && file_exists($fr_row['photo'])) ? $fr_row['photo'] : 'default-user.png';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Chat avec <?= htmlspecialchars($friend_nom) ?></title>
  <meta name="viewport" content="width=420, initial-scale=1">
  <a href="users.php" class="back-arrow" title="Retour">
  <svg height="26" width="26" viewBox="0 0 24 24"><path fill="#2e89ff" d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
</a>
  <style>
  body {
    background: #e6ecf3;
    font-family: 'Segoe UI', Arial, sans-serif;
    margin:0;
  }
  .chat-container {
    max-width: 420px;
    margin: 30px auto;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 18px #0001;
    display: flex;
    flex-direction: column;
    min-height: 90vh;
    overflow: hidden;
  }
  .header {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 14px 8px 14px;
    border-bottom: 1px solid #f3f3f3;
    background: #f8fafd;
  }
  .header img {
    border-radius: 50%;
    width: 42px;
    height: 42px;
    object-fit: cover;
    border: 2px solid #e6ecf3;
  }
  .header .name {
    font-weight: 700;
    font-size: 1.08em;
  }
  .header .icons {
    margin-left: auto;
    display: flex;
    gap: 16px;
  }
  .header .icons svg {
    color: #2e89ff;
    font-size: 1.2em;
    cursor: pointer;
  }
  .messages {
    flex: 1 1 auto;
    padding: 20px 12px 0 12px;
    display: flex;
    flex-direction: column;
    gap: 7px;
    overflow-y: auto;
    min-height: 320px;
    background: #e6ecf3;
  }
  .bubble-row {
    display: flex;
    align-items: flex-end;
    margin-bottom: 3px;
  }
  .bubble-row.me {
    flex-direction: row-reverse;
  }
  .bubble-row .avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    margin:0 6px;
    object-fit: cover;
    border: 1.5px solid #e6ecf3;
  }
  .bubble {
    max-width: 72%;
    padding: 8px 13px;
    border-radius: 17px;
    font-size: 1.04em;
    background: #f0f2f8;
    color: #333;
    word-break: break-word;
    margin-bottom: 2px;
    box-shadow: 0 1px 4px #0001;
    border-bottom-left-radius: 4px;
    border-bottom-right-radius: 17px;
    border-top-right-radius: 17px;
  }
  .bubble-row.me .bubble {
    background: #2e89ff;
    color: #fff;
    border-bottom-right-radius: 4px;
    border-bottom-left-radius: 17px;
    border-top-left-radius: 17px;
    border-top-right-radius: 17px;
  }
  .bubble a { color: #1877f2; word-break: break-all; }
  .bubble video,
  .bubble img {
    max-width: 240px;
    max-height: 190px;
    border-radius: 11px;
    margin: 2px 0 0 0;
    display: block;
    background: #111;
    box-shadow: 0 2px 10px #0002;
  }
  .bubble audio {
    width: 190px;
    margin-top: 8px;
    display: block;
  }
  .time {
    font-size: 0.81em;
    color: #888;
    margin: 2px 0 2px 10px;
  }
  .bubble-row.me .time { margin: 2px 10px 2px 0; text-align: right; }
  .input-area {
    background: #f8fafd;
    border-top: 1px solid #e6ecf3;
    padding: 10px 9px;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .input-area input, .input-area textarea {
    border: none;
    border-radius: 21px;
    padding: 7px 14px;
    font-size: 1em;
    width: 100%;
    background: #f0f2f8;
    outline: none;
    resize: none;
    margin-right: 8px;
  }
  .input-area button, .input-area label {
    background: none;
    border: none;
    color: #2e89ff;
    font-size: 1.4em;
    cursor: pointer;
    padding: 0 5px;
  }
  .input-area label input[type="file"] { display: none; }
  ::-webkit-scrollbar { width: 6px; background: #f6f6f6; }
  ::-webkit-scrollbar-thumb { background: #e6ecf3; border-radius:4px; }
  </style>
</head>
<body>
<div class="chat-container">
  <div class="header">
    <a href="users.php" class="back-arrow" title="Retour">
    <svg height="26" width="26" viewBox="0 0 24 24"><path fill="#2e89ff" d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
  </a>
  <img src="<?= htmlspecialchars($friend_photo) ?>" alt="avatar">
  <span class="name"><?= htmlspecialchars($friend_nom) ?> <?= htmlspecialchars($friend_prenom) ?></span>

    <div class="icons">
      <!-- Phone icon -->
      <svg onclick="startCall('audio')" height="24" width="24" viewBox="0 0 24 24"><path fill="currentColor" d="M6.62 10.79a15.056 15.056 0 006.59 6.59l2.2-2.2a1 1 0 011.1-.21 11.05 11.05 0 004.12.78 1 1 0 011 1V20a2 2 0 01-2 2A18 18 0 013 4a2 2 0 012-2h3.5a1 1 0 011 1 11.05 11.05 0 00.78 4.12 1 1 0 01-.21 1.1l-2.2 2.2z"/></svg>
      <!-- Video icon -->
      <svg onclick="startCall('video')" height="24" width="24" viewBox="0 0 24 24"><path fill="currentColor" d="M17 10.5V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-3.5l4 4v-11l-4 4z"/></svg>
    </div>
  </div>
  <div class="messages" id="chat-box"></div>
  <form class="input-area" id="chat-form" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
    <label title="Envoyer un fichier">
      <input type="file" name="file" id="file-input" accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
      📎
    </label>
    <textarea name="message" id="msg" rows="1" placeholder="Aa" style="height:32px;"></textarea>
    <button type="submit" title="Envoyer">➤</button>
  </form>
</div>
<script>
const chatBox  = document.getElementById('chat-box');
const ME_ID    = <?= intval($user_id) ?>;
const WITH_ID  = <?= intval($with) ?>;
const MY_PHOTO = <?= json_encode($my_photo) ?>;
const FR_PHOTO = <?= json_encode($friend_photo) ?>;
const CSRF     = <?= json_encode(csrf_token()) ?>;

let lastId = 0;

function urlify(text) {
  return text.replace(/(https?:\/\/[^\s]+)/g, url => `<a href="${url}" target="_blank" rel="noopener">${url}</a>`);
}
function formatTime(ts) {
  const d = new Date(ts.replace(' ', 'T'));
  const h = d.getHours(), m = d.getMinutes();
  const ampm = h >= 12 ? "PM" : "AM";
  const h12  = h % 12 || 12;
  return `${["Dim","Lun","Mar","Mer","Jeu","Ven","Sam"][d.getDay()]} ${h12}:${String(m).padStart(2,"0")} ${ampm}`;
}
function appendMessage(msg) {
  const isMe   = msg.sender_id == ME_ID;
  const avatar = isMe ? MY_PHOTO : FR_PHOTO;
  const row    = document.createElement('div');
  row.className = `bubble-row${isMe ? ' me' : ''}`;
  let inner = `<img class="avatar" src="${avatar}" alt=""><div><div class="bubble">`;
  if (msg.file_type?.startsWith('image/'))  inner += `<img src="${msg.file}" alt="Image">`;
  else if (msg.file_type?.startsWith('video/')) inner += `<video controls src="${msg.file}"></video>`;
  else if (msg.file_type?.startsWith('audio/')) inner += `<audio controls src="${msg.file}"></audio>`;
  else if (msg.file) inner += `<a href="${msg.file}" target="_blank">📎 Fichier</a>`;
  if (msg.content) inner += urlify(msg.content.replace(/\n/g, '<br>'));
  inner += `</div><div class="time">${formatTime(msg.created_at)}</div></div>`;
  row.innerHTML = inner;
  chatBox.appendChild(row);
  chatBox.scrollTop = chatBox.scrollHeight;
  lastId = Math.max(lastId, msg.id);
}

// Chargement initial
fetch(`ajax_chat.php?with=${WITH_ID}`)
  .then(r => r.json())
  .then(msgs => {
    msgs.forEach(appendMessage);
  });

// SSE pour les nouveaux messages en temps réel
function connectSSE() {
  const es = new EventSource(`sse_chat.php?with=${WITH_ID}&last_id=${lastId}`);
  es.onmessage = e => {
    const data = JSON.parse(e.data);
    if (data.close) { es.close(); setTimeout(connectSSE, 1000); return; }
    appendMessage(data);
  };
  es.onerror = () => { es.close(); setTimeout(connectSSE, 3000); };
}
connectSSE();

document.getElementById('chat-form').onsubmit = function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch(`ajax_chat.php?with=${WITH_ID}`, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        document.getElementById('msg').value = '';
        document.getElementById('file-input').value = '';
      }
    });
}

function startCall(type) {
  window.open('call.php?with=<?= $with ?>&type=' + type, '_blank', 'width=440,height=500');
}
</script>
</body>
</html>