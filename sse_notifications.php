<?php
require 'security.php';
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$user_id  = intval($_SESSION['user_id']);
$last_id  = intval($_GET['last_id'] ?? 0);

// Headers SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // Nginx
set_time_limit(60);

// Boucle de streaming (60 secondes max, le client reconnecte)
$end = time() + 55;
while (time() < $end) {
    $stmt = $conn->prepare(
        "SELECT id, content, link, created_at FROM notifications
         WHERE user_id=? AND id>? ORDER BY id ASC LIMIT 10"
    );
    $stmt->bind_param("ii", $user_id, $last_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!empty($rows)) {
        foreach ($rows as $row) {
            $last_id = $row['id'];
            $data = json_encode([
                'id'         => $row['id'],
                'content'    => $row['content'],
                'link'       => $row['link'],
                'created_at' => $row['created_at'],
            ]);
            echo "id: {$row['id']}\n";
            echo "data: {$data}\n\n";
        }
        ob_flush();
        flush();
    } else {
        // Heartbeat pour garder la connexion vivante
        echo ": heartbeat\n\n";
        ob_flush();
        flush();
    }

    sleep(3);

    // Reconnecter la BDD si perdue
    if (!$conn->ping()) {
        $conn->close();
        break;
    }
}
echo "data: {\"close\":true}\n\n";
ob_flush();
flush();
