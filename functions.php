<?php
require_once 'config.php';

function get_user_by_id($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res;
}

function get_all_users($exclude_id = null) {
    global $conn;
    $sql = "SELECT * FROM users";
    if ($exclude_id) {
        $sql .= " WHERE id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $exclude_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $res;
    } else {
        $res = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        return $res;
    }
}

function is_friend($user_id, $other_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM friends WHERE ((from_user=? AND to_user=?) OR (from_user=? AND to_user=?)) AND status='accepted'");
    $stmt->bind_param("iiii", $user_id, $other_id, $other_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    $is_friend = $stmt->num_rows > 0;
    $stmt->close();
    return $is_friend;
}

function send_friend_request($from, $to) {
    global $conn;
    // Vérifier si déjà amis
    if (is_friend($from, $to)) return false;
    // Créer la relation directement en "accepted"
    $stmt = $conn->prepare("INSERT IGNORE INTO friends (from_user, to_user, status) VALUES (?, ?, 'accepted')");
    $stmt->bind_param("ii", $from, $to);
    $stmt->execute();
    $stmt->close();
    return true;
}

function get_friends($user_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT u.* FROM users u
        JOIN friends f ON (
            (f.from_user = ? AND f.to_user = u.id) OR 
            (f.to_user = ? AND f.from_user = u.id)
        )
        WHERE f.status = 'accepted'
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}

function get_messages($user_id, $friend_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at ASC");
    $stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}

function send_message($from, $to, $content) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $from, $to, $content);
    $stmt->execute();
    $stmt->close();
}

function send_friend_email($to_user_id, $from_user_id) {
    $to_user = get_user_by_id($to_user_id);
    $from_user = get_user_by_id($from_user_id);
    require_once 'send_mail.php';
    $to = $to_user['email'];
    $subject = "Demande d'amitié sur MonRéseauSocial";
    $message = "{$from_user['nom']} {$from_user['prenom']} souhaite devenir votre ami !"
             . "\nVous êtes maintenant amis et pouvez discuter sur la plateforme.";
    sendValidationEmail($to, '', $subject, $message);
}
?>