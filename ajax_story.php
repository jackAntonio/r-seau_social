<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit;
}

$user_id = $_SESSION['user_id'];
$media = null;

if (!empty($_FILES['media']) && $_FILES['media']['error'] == 0) {
    $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm'];
    if (in_array($ext, $allowed)) {
        $mediaDir = 'uploads/stories/';
        if (!is_dir($mediaDir)) mkdir($mediaDir, 0777, true);
        $media = $mediaDir . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['media']['tmp_name'], $media);

        // Insérer la story
        $stmt = $conn->prepare("INSERT INTO stories (user_id, image, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $user_id, $media);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Erreur upload']);