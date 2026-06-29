<?php
require 'security.php';
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$peer_id = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_POST['peer_id'] ?? '');
if (!$peer_id) { http_response_code(400); exit; }

$pdo->prepare("UPDATE users SET peerjs_id=? WHERE id=?")
    ->execute([$peer_id, intval($_SESSION['user_id'])]);

http_response_code(204);
