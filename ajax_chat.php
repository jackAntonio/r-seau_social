<?php
require 'security.php';
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$with    = intval($_GET['with'] ?? $_POST['with'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!csrf_verify()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token CSRF invalide']);
        exit;
    }

    $content   = trim($_POST['message'] ?? '');
    $file_path = null;
    $file_type = null;

    if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $allowed_mimes = [
            'image/jpeg'    => 'jpg',
            'image/png'     => 'png',
            'image/gif'     => 'gif',
            'video/mp4'     => 'mp4',
            'video/webm'    => 'webm',
            'audio/mpeg'    => 'mp3',
            'audio/wav'     => 'wav',
            'audio/ogg'     => 'ogg',
            'application/pdf' => 'pdf',
        ];

        if (validate_upload($_FILES['file'], array_keys($allowed_mimes))) {
            $finfo     = new finfo(FILEINFO_MIME_TYPE);
            $mime      = $finfo->file($_FILES['file']['tmp_name']);
            $file_type = $mime;
            $ext       = $allowed_mimes[$mime] ?? 'bin';
            $dir       = "uploads/chat/";
            make_upload_dir($dir);
            $file_path = $dir . bin2hex(random_bytes(8)) . '.' . $ext;
            move_uploaded_file($_FILES['file']['tmp_name'], $file_path);
        }
    }

    if ($content === "" && !$file_path) {
        echo json_encode(['success' => false, 'error' => 'Message vide']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, content, file, file_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisss", $user_id, $with, $content, $file_path, $file_type);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
    exit;
}

// Affichage des messages (GET)
$stmt = $conn->prepare("
    SELECT * FROM messages
    WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)
    ORDER BY created_at ASC
    LIMIT 100
");
$stmt->bind_param("iiii", $user_id, $with, $with, $user_id);
$stmt->execute();
$res      = $stmt->get_result();
$messages = [];
while ($row = $res->fetch_assoc()) {
    $messages[] = [
        'id'          => $row['id'],
        'sender_id'   => $row['sender_id'],
        'receiver_id' => $row['receiver_id'],
        'content'     => $row['content'],
        'file'        => $row['file'],
        'file_type'   => $row['file_type'],
        'created_at'  => $row['created_at'],
    ];
}
$stmt->close();

header('Content-Type: application/json');
echo json_encode($messages);
