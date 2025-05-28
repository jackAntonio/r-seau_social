<?php
session_start();
require 'config.php';

// Sécurisation
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success'=>false, 'error'=>'Non connecté']);
    exit;
}
$user_id = $_SESSION['user_id'];
$with = intval($_GET['with'] ?? $_POST['with'] ?? 0);

// Envoi d'un message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['message'] ?? '');
    $file_path = null;
    $file_type = null;

    // Gestion fichier joint
    if (!empty($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $all_types = [
            'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif',
            'mp4'=>'video/mp4','webm'=>'video/webm',
            'mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg',
            'pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        if (isset($all_types[$ext])) {
            $file_type = $all_types[$ext];
            $dir = "uploads/chat/";
            if (!is_dir($dir)) mkdir($dir,0777,true);
            $file_path = $dir . uniqid().".".$ext;
            move_uploaded_file($_FILES['file']['tmp_name'], $file_path);
        }
    }
    if ($content === "" && !$file_path) {
        echo json_encode(['success'=>false, 'error'=>'Message vide']);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, content, file, file_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisss", $user_id, $with, $content, $file_path, $file_type);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success'=>true]);
    exit;
}

// Affichage des messages (GET)
$stmt = $conn->prepare("
    SELECT * FROM messages 
    WHERE 
      (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)
    ORDER BY created_at ASC
    LIMIT 100
");
$stmt->bind_param("iiii", $user_id, $with, $with, $user_id);
$stmt->execute();
$res = $stmt->get_result();
$messages = [];
while($row = $res->fetch_assoc()) {
    $messages[] = [
        'id' => $row['id'],
        'sender_id' => $row['sender_id'],
        'receiver_id' => $row['receiver_id'],
        'content' => $row['content'],
        'file' => $row['file'],
        'file_type' => $row['file_type'],
        'created_at' => $row['created_at']
    ];
}
$stmt->close();
header('Content-Type: application/json');
echo json_encode($messages);