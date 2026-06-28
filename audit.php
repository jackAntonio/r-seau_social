<?php
// Journalisation des actions utilisateurs
function audit_log(mysqli $conn, string $action, ?int $user_id = null, ?string $target_type = null, ?int $target_id = null): void {
    $ip         = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmt = $conn->prepare(
        "INSERT INTO audit_logs (user_id, action, target_type, target_id, ip, user_agent)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("ississ", $user_id, $action, $target_type, $target_id, $ip, $user_agent);
    $stmt->execute();
    $stmt->close();
}

// Vérifier si l'IP/email a dépassé le plafond de tentatives
function is_rate_limited(mysqli $conn, string $ip, string $email = '', int $max = 5, int $window_minutes = 60): bool {
    $since = date('Y-m-d H:i:s', strtotime("-{$window_minutes} minutes"));
    $stmt  = $conn->prepare(
        "SELECT COUNT(*) as cnt FROM login_attempts WHERE ip=? AND attempted_at >= ?"
    );
    $stmt->bind_param("ss", $ip, $since);
    $stmt->execute();
    $cnt = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return intval($cnt) >= $max;
}

// Enregistrer une tentative de connexion
function record_attempt(mysqli $conn, string $ip, string $email): void {
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip, email) VALUES (?, ?)");
    $stmt->bind_param("ss", $ip, $email);
    $stmt->execute();
    $stmt->close();
}

// Nettoyer les tentatives expirées (appelé occasionnellement)
function cleanup_attempts(mysqli $conn): void {
    $conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
}
