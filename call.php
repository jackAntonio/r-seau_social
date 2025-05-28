<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Connecte-toi !");
}

$with = intval($_GET['with'] ?? 0);
$type = $_GET['type'] ?? 'video'; // ou 'audio'
require 'config.php';

// Récupérer l'ID PeerJS de l'ami
$friend_peer_id = $friend_nom = $friend_prenom = null;

if ($with > 0) {
    $stmt = $conn->prepare("SELECT peerjs_id, nom, prenom FROM users WHERE id=?");
    if ($stmt === false) {
        die("Erreur SQL (prepare): " . htmlspecialchars($conn->error));
    }
    $stmt->bind_param("i", $with);
    if (!$stmt->execute()) {
        die("Erreur SQL (execute): " . htmlspecialchars($stmt->error));
    }
    $stmt->bind_result($friend_peer_id, $friend_nom, $friend_prenom);
    if (!$stmt->fetch()) {
        die("Utilisateur non trouvé !");
    }
    $stmt->close();
} else {
    die("ID de l'utilisateur à appeler manquant ou invalide.");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Appel <?= htmlspecialchars($type) ?> avec <?= htmlspecialchars($friend_nom) ?> <?= htmlspecialchars($friend_prenom) ?></title>
  <style>
  body { background: #090f1b; color: #e7f8fa; font-family: 'Inter', Arial, sans-serif; margin:0; }
  .container { background:rgba(16,18,40,0.85); border-radius:16px; box-shadow:0 2px 32px #00ffe733; max-width:430px; margin:54px auto; padding:28px 16px; text-align:center; }
  button { background:#00ffe7; color:#090f1b; border:none; border-radius:12px; padding:12px 30px; font-weight:700; cursor:pointer; margin:12px 0; }
  video { border-radius:11px; margin:10px; width:220px; max-width:90vw; height:170px; background:#222; }
  #remote-video { border:2px solid #00ffe7; }
  #my-peer-id { font-size:0.93em; color:#8be9fd;}
  .stars span { position: absolute; border-radius: 50%; background: #fff9; opacity:.7; animation: star-float 8s linear infinite;}
  .stars { z-index: 1; position: fixed; left:0; top:0; width: 100vw; height: 100vh; pointer-events: none; overflow: hidden;}
  .stars span:nth-child(1) { left:10vw; top:7vh; width:2px; height:2px; animation-delay:0s;}
  .stars span:nth-child(2) { left:30vw; top:50vh; width:1.7px; height:1.7px; animation-delay:1.2s;}
  .stars span:nth-child(3) { left:70vw; top:20vh; width:2.3px; height:2.3px; animation-delay:2.8s;}
  .stars span:nth-child(4) { left:90vw; top:80vh; width:2.8px; height:2.8px; animation-delay:4s;}
  .stars span:nth-child(5) { left:40vw; top:15vh; width:2px; height:2px; animation-delay:3.4s;}
  .stars span:nth-child(6) { left:80vw; top:55vh; width:1.5px; height:1.5px; animation-delay:2s;}
  @keyframes star-float { 0% { opacity:.3; transform: translateY(0);} 50% { opacity:1;} 100% { opacity:.3; transform: translateY(-14vh);}}
  </style>
</head>
<body>
<div class="stars">
  <span></span><span></span><span></span><span></span><span></span><span></span>
</div>
<div class="container">
  <h2>Appel <?= htmlspecialchars($type) ?> avec <?= htmlspecialchars($friend_nom) ?> <?= htmlspecialchars($friend_prenom) ?></h2>
  <div>
    <video id="my-video" autoplay muted playsinline></video>
    <video id="remote-video" autoplay playsinline></video>
    <audio id="remote-audio" autoplay style="display:none"></audio>
  </div>
  <div id="my-peer-id"></div>
  <div id="status"></div>
  <button id="call-btn">Appeler</button>
  <button onclick="window.close()">Raccrocher</button>
</div>
<script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
<script>
// 1. Création PeerJS
const peer = new Peer(undefined, {
  host: 'peerjs.com',
  port: 443,
  path: '/',
  secure: true
});
let localStream, callObj, myPeerId = null;

// 2. Obtenir le flux local selon audio ou video
async function getMedia() {
  try {
    localStream = await navigator.mediaDevices.getUserMedia({
      audio: true,
      video: (<?= json_encode($type === 'video') ?>)
    });
    document.getElementById('my-video').srcObject = localStream;
    if ('<?= $type ?>' === 'audio') document.getElementById('my-video').style.display = 'none';
  } catch(e) {
    document.getElementById('status').textContent = "Erreur d'accès caméra/micro : " + e.message;
  }
}
getMedia();

// 3. Enregistre mon PeerJS ID côté serveur pour que l’autre le voie aussi dans le chat
peer.on('open', id => {
  myPeerId = id;
  document.getElementById('my-peer-id').innerHTML = "Mon ID PeerJS : <b>"+id+"</b>";
  fetch('save_peerjs_id.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'peer_id=' + encodeURIComponent(id)
  });
});

// 4. Répondre à un appel entrant
peer.on('call', call => {
  call.answer(localStream);
  callObj = call;
  call.on('stream', stream => {
    if ('<?= $type ?>' === 'audio') {
      document.getElementById('remote-audio').srcObject = stream;
      document.getElementById('remote-audio').style.display = 'block';
    } else {
      document.getElementById('remote-video').srcObject = stream;
      document.getElementById('remote-video').style.display = 'block';
    }
    document.getElementById('status').textContent = "Connecté à l'appel.";
  });
  call.on('close', () => document.getElementById('status').textContent = "Appel terminé.");
});

// 5. Émettre l'appel sortant (si l'autre ID PeerJS est dispo)
document.getElementById('call-btn').onclick = function() {
  const friendPeerId = "<?= addslashes($friend_peer_id) ?>";
  if (!friendPeerId) return alert("L'utilisateur n'est pas connecté à PeerJS.");
  const call = peer.call(friendPeerId, localStream);
  callObj = call;
  call.on('stream', stream => {
    if ('<?= $type ?>' === 'audio') {
      document.getElementById('remote-audio').srcObject = stream;
      document.getElementById('remote-audio').style.display = 'block';
    } else {
      document.getElementById('remote-video').srcObject = stream;
      document.getElementById('remote-video').style.display = 'block';
    }
    document.getElementById('status').textContent = "Connecté à l'appel.";
  });
  call.on('close', () => document.getElementById('status').textContent = "Appel terminé.");
};

// 6. Fin d'appel
window.onbeforeunload = ()=>{ if(callObj) callObj.close(); peer.destroy(); };
</script>
</body>
</html>