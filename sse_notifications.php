<?php
require 'security.php';
session_start();
require 'config.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }

$user_id = intval($_SESSION['user_id']);
$last_id = intval($_GET['last_id'] ?? 0);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
set_time_limit(60);

$end = time() + 55;
while (time() < $end) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, content, link, created_at FROM notifications
             WHERE user_id=? AND id>? ORDER BY id ASC LIMIT 10"
        );
        $stmt->execute([$user_id, $last_id]);
        $rows = $stmt->fetchAll();
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $last_id = $row['id'];
                echo "id: {$row['id']}\ndata: " . json_encode($row) . "\n\n";
            }
        } else {
            echo ": heartbeat\n\n";
        }
        ob_flush(); flush();
    } catch (Exception $e) { break; }
    sleep(3);
}
echo "data: {\"close\":true}\n\n";
ob_flush(); flush();
