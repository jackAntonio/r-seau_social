<?php
function get_user_role(PDO $pdo, int $user_id): string {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 'user';
}

function is_admin(PDO $pdo, int $user_id): bool {
    return get_user_role($pdo, $user_id) === 'admin';
}

function is_moderateur(PDO $pdo, int $user_id): bool {
    return in_array(get_user_role($pdo, $user_id), ['admin', 'moderateur']);
}

function require_role(PDO $pdo, int $user_id, string $min_role = 'admin'): void {
    $hierarchy  = ['user' => 0, 'moderateur' => 1, 'admin' => 2];
    $user_level = $hierarchy[get_user_role($pdo, $user_id)] ?? 0;
    $req_level  = $hierarchy[$min_role] ?? 2;
    if ($user_level < $req_level) {
        http_response_code(403);
        die("Accès refusé.");
    }
}

function set_user_role(PDO $pdo, int $target_id, string $role, int $admin_id): bool {
    $allowed = ['user', 'moderateur', 'admin'];
    if (!in_array($role, $allowed, true)) return false;
    $stmt = $pdo->prepare("UPDATE users SET role=? WHERE id=?");
    $stmt->execute([$role, $target_id]);
    return true;
}
