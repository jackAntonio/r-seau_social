<?php
// Journalisation des actions + rate limiting (PDO PostgreSQL)

function audit_log(PDO $pdo, string $action, ?int $user_id = null, ?string $target_type = null, ?int $target_id = null): void {
    $ip         = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, target_type, target_id, ip, user_agent)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$user_id, $action, $target_type, $target_id, $ip, $user_agent]);
}

function is_rate_limited(PDO $pdo, string $ip, int $max = 5, int $window_minutes = 60): bool {
    $since = date('Y-m-d H:i:s', strtotime("-{$window_minutes} minutes"));
    $stmt  = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts WHERE ip=? AND attempted_at >= ?"
    );
    $stmt->execute([$ip, $since]);
    return intval($stmt->fetchColumn()) >= $max;
}

function record_attempt(PDO $pdo, string $ip, string $email): void {
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip, email) VALUES (?, ?)");
    $stmt->execute([$ip, $email]);
}

function cleanup_attempts(PDO $pdo): void {
    $cutoff = date('Y-m-d H:i:s', time() - 7200);
    $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < ?")->execute([$cutoff]);
}
