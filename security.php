<?php
// ============================================================
// Fichier central de sécurité - inclure avant session_start()
// ============================================================

// Configuration session sécurisée
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
// En production HTTPS uniquement : ini_set('session.cookie_secure', 1);

// Headers de sécurité HTTP
function send_security_headers(): void {
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data: blob: https://chart.googleapis.com; media-src 'self' blob:; connect-src 'self' https://*.peerjs.com wss://*.peerjs.com https://unpkg.com;");
}

// Générer un token CSRF en session
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Valider le token CSRF reçu depuis un formulaire
function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Champ caché CSRF pour les formulaires HTML
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

// Validation MIME réelle pour les uploads
function validate_upload(array $file, array $allowed_mimes): bool {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    return in_array($mime, $allowed_mimes, true);
}

// Créer un dossier upload avec permissions correctes
function make_upload_dir(string $dir): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

send_security_headers();
