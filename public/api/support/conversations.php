<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = require_auth();
$isOperator = in_array($user['role'], ['admin', 'operator'], true);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($isOperator) {
        $sql = "SELECT c.id, c.subject, c.status, c.created_at, c.updated_at, u.id AS user_id, u.name AS user_name, u.email AS user_email,
                       (SELECT message FROM support_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                       (SELECT COUNT(*) FROM support_messages m WHERE m.conversation_id = c.id AND m.sender_role = 'customer' AND m.read_at IS NULL) AS unread_count
                FROM support_conversations c JOIN users u ON u.id = c.user_id
                ORDER BY c.updated_at DESC";
        $stmt = $pdo->query($sql);
    } else {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.subject, c.status, c.created_at, c.updated_at,
                    (SELECT message FROM support_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                    (SELECT COUNT(*) FROM support_messages m WHERE m.conversation_id = c.id AND m.sender_role != 'customer' AND m.read_at IS NULL) AS unread_count
             FROM support_conversations c WHERE c.user_id = ? ORDER BY c.updated_at DESC"
        );
        $stmt->execute([$user['id']]);
    }
    json_response(true, ['conversations' => $stmt->fetchAll()], 'ok');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    api_guard();
    require_role($user, 'customer');

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $subject = trim((string) ($input['subject'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));

    if ($subject === '' || mb_strlen($subject) > 160) {
        json_response(false, null, 'Enter a subject (up to 160 characters).', 422);
    }
    if ($message === '' || mb_strlen($message) > 4000) {
        json_response(false, null, 'Enter a message (up to 4000 characters).', 422);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO support_conversations (user_id, subject, status) VALUES (?, ?, "open")')->execute([$user['id'], $subject]);
        $conversationId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO support_messages (conversation_id, sender_id, sender_role, message) VALUES (?, ?, "customer", ?)')
            ->execute([$conversationId, $user['id'], $message]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[support/conversations] ' . $e->getMessage());
        json_response(false, null, 'Unable to start a new conversation. Please try again.', 500);
    }

    json_response(true, ['conversation_id' => $conversationId], 'Conversation started.', 201);
}

json_response(false, null, 'Method not allowed.', 405);
