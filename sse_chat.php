<?php
require 'security.php';
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$with    = intval($_GET['with'] ?? 0);
$last_id = intval($_GET['last_id'] ?? 0);

if (!$with) { exit; }

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
set_time_limit(60);

$end = time() + 55;
while (time() < $end) {
    $stmt = $conn->prepare(
        "SELECT id, sender_id, receiver_id, content, file, file_type, created_at
         FROM messages
         WHERE ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))
           AND id>?
         ORDER BY id ASC LIMIT 20"
    );
    $stmt->bind_param("iiiii", $user_id, $with, $with, $user_id, $last_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!empty($rows)) {
        foreach ($rows as $row) {
            $last_id = $row['id'];
            $data = json_encode($row);
            echo "id: {$row['id']}\n";
            echo "data: {$data}\n\n";
        }
        ob_flush();
        flush();
    } else {
        echo ": heartbeat\n\n";
        ob_flush();
        flush();
    }

    sleep(2);

    if (!$conn->ping()) break;
}
echo "data: {\"close\":true}\n\n";
ob_flush();
flush();
