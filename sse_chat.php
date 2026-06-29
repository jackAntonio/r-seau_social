<?php
require 'security.php';
session_start();
require 'config.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }

$user_id = intval($_SESSION['user_id']);
$with    = intval($_GET['with'] ?? 0);
$last_id = intval($_GET['last_id'] ?? 0);
if (!$with) exit;

// IDOR : vérifier que l'utilisateur est ami avec $with ou est l'une des deux parties
$chk = $pdo->prepare("SELECT id FROM friends WHERE ((from_user=? AND to_user=?) OR (from_user=? AND to_user=?)) AND status='accepted'");
$chk->execute([$user_id, $with, $with, $user_id]);
if (!$chk->fetch()) { http_response_code(403); exit; }

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
set_time_limit(60);

$end = time() + 55;
while (time() < $end) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, sender_id, receiver_id, content, file, file_type, created_at FROM messages
             WHERE ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) AND id>?
             ORDER BY id ASC LIMIT 20"
        );
        $stmt->execute([$user_id, $with, $with, $user_id, $last_id]);
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
    sleep(2);
}
echo "data: {\"close\":true}\n\n";
ob_flush(); flush();
