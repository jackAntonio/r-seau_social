<?php
if (php_sapi_name() !== 'cli') {
    // En prod : CRON_SECRET via variable d'environnement
    $secret = getenv('CRON_SECRET') ?: (defined('CRON_SECRET') ? CRON_SECRET : null);
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
$two_hours_ago = date('Y-m-d H:i:s', time() - 7200);
$pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < ?")->execute([$two_hours_ago]);

// Nettoyer les logs d'audit > 90 jours
$ninety_days_ago = date('Y-m-d H:i:s', time() - (90 * 86400));
$pdo->prepare("DELETE FROM audit_logs WHERE created_at < ?")->execute([$ninety_days_ago]);

$log = date('Y-m-d H:i:s') . " | Stories supprimées: {$deleted}\n";
file_put_contents(__DIR__ . '/cron.log', $log, FILE_APPEND);
if (php_sapi_name() === 'cli') echo $log;
else echo json_encode(['ok'=>true,'deleted'=>$deleted]);
