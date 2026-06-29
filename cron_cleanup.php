<?php
if (php_sapi_name() !== 'cli') {
    require 'secrets.php';
    $secret = defined('CRON_SECRET') ? CRON_SECRET : null;
    $given  = $_GET['key'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
    if (!$secret || !hash_equals($secret, $given)) { http_response_code(403); exit("Accès refusé."); }
}

require 'config.php';

// Supprimer les stories expirées
$stmt = $pdo->prepare("SELECT id, image FROM stories WHERE expires_at < NOW()");
$stmt->execute();
$expired = $stmt->fetchAll();
$deleted = 0;
foreach ($expired as $s) {
    if ($s['image'] && str_starts_with($s['image'], 'uploads/') && file_exists($s['image'])) @unlink($s['image']);
    $pdo->prepare("DELETE FROM stories WHERE id=?")->execute([$s['id']]);
    $deleted++;
}

// Nettoyer les tentatives de login > 2h
$pdo->exec("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL '2 hours'");

// Nettoyer les logs d'audit > 90 jours
$pdo->exec("DELETE FROM audit_logs WHERE created_at < NOW() - INTERVAL '90 days'");

$log = date('Y-m-d H:i:s') . " | Stories supprimées: {$deleted}\n";
file_put_contents(__DIR__ . '/cron.log', $log, FILE_APPEND);
if (php_sapi_name() === 'cli') echo $log;
else echo json_encode(['ok'=>true,'deleted'=>$deleted]);
