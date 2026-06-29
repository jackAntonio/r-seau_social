<?php
require_once 'config.php';

function get_user_by_id(int $id): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_all_users(?int $exclude_id = null): array {
    global $pdo;
    if ($exclude_id) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id != ?");
        $stmt->execute([$exclude_id]);
    } else {
        $stmt = $pdo->query("SELECT * FROM users");
    }
    return $stmt->fetchAll();
}

function is_friend(PDO $pdo, int $user_id, int $other_id): bool {
    $stmt = $pdo->prepare(
        "SELECT id FROM friends WHERE ((from_user=? AND to_user=?) OR (from_user=? AND to_user=?)) AND status='accepted'"
    );
    $stmt->execute([$user_id, $other_id, $other_id, $user_id]);
    return (bool)$stmt->fetch();
}

function send_friend_request(PDO $pdo, int $from, int $to): bool {
    if (is_friend($pdo, $from, $to)) return false;
    $stmt = $pdo->prepare(
        "INSERT INTO friends (from_user, to_user, status) VALUES (?, ?, 'accepted') ON CONFLICT (from_user, to_user) DO NOTHING"
    );
    $stmt->execute([$from, $to]);
    return true;
}

function get_friends(PDO $pdo, int $user_id): array {
    $stmt = $pdo->prepare(
        "SELECT u.* FROM users u
         JOIN friends f ON ((f.from_user=? AND f.to_user=u.id) OR (f.to_user=? AND f.from_user=u.id))
         WHERE f.status='accepted'"
    );
    $stmt->execute([$user_id, $user_id]);
    return $stmt->fetchAll();
}

function get_messages(PDO $pdo, int $user_id, int $friend_id): array {
    $stmt = $pdo->prepare(
        "SELECT * FROM messages
         WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)
         ORDER BY created_at ASC"
    );
    $stmt->execute([$user_id, $friend_id, $friend_id, $user_id]);
    return $stmt->fetchAll();
}

function send_message(PDO $pdo, int $from, int $to, string $content): void {
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
    $stmt->execute([$from, $to, $content]);
}
