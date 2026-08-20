<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = require_auth();
$isOperator = in_array($user['role'], ['admin', 'operator'], true);
$pdo = db();

function load_conversation(PDO $pdo, int $conversationId, array $user, bool $isOperator): array
{
    $stmt = $pdo->prepare('SELECT id, user_id, status FROM support_conversations WHERE id = ?');
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch();

    if (!$conversation) {
        json_response(false, null, 'Conversation not found.', 404);
    }
    // IDOR guard: a customer may only ever touch their own conversation.
    if (!$isOperator && (int) $conversation['user_id'] !== (int) $user['id']) {
        json_response(false, null, 'Conversation not found.', 404);
    }
    return $conversation;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $conversationId = (int) ($_GET['conversation_id'] ?? 0);
    $afterId = (int) ($_GET['after_id'] ?? 0);
    if ($conversationId <= 0) {
        json_response(false, null, 'A conversation is required.', 422);
    }

    load_conversation($pdo, $conversationId, $user, $isOperator);

    $stmt = $pdo->prepare(
        'SELECT m.id, m.sender_id, m.sender_role, m.message, m.created_at, m.read_at, u.name AS sender_name
         FROM support_messages m JOIN users u ON u.id = m.sender_id
         WHERE m.conversation_id = ? AND m.id > ? ORDER BY m.id ASC'
    );
    $stmt->execute([$conversationId, $afterId]);
    $messages = $stmt->fetchAll();

    // Mark messages from the other party as read now that this user has fetched them.
    $otherCondition = $isOperator ? "sender_role = 'customer'" : "sender_role != 'customer'";
    $pdo->prepare("UPDATE support_messages SET read_at = NOW() WHERE conversation_id = ? AND read_at IS NULL AND {$otherCondition}")
        ->execute([$conversationId]);

    json_response(true, ['messages' => $messages], 'ok');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    api_guard();

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $conversationId = (int) ($input['conversation_id'] ?? 0);
    $message = trim((string) ($input['message'] ?? ''));

    if ($conversationId <= 0) {
        json_response(false, null, 'A conversation is required.', 422);
    }
    if ($message === '' || mb_strlen($message) > 4000) {
        json_response(false, null, 'Enter a message (up to 4000 characters).', 422);
    }

    $conversation = load_conversation($pdo, $conversationId, $user, $isOperator);
    if ($conversation['status'] === 'closed') {
        json_response(false, null, 'This conversation is closed.', 422);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO support_messages (conversation_id, sender_id, sender_role, message) VALUES (?, ?, ?, ?)')
            ->execute([$conversationId, $user['id'], $user['role'], $message]);
        $messageId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE support_conversations SET updated_at = NOW() WHERE id = ?')->execute([$conversationId]);

        if ($isOperator) {
            $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "support", "Support reply received", ?)')
                ->execute([$conversation['user_id'], 'A member of our support team replied to your conversation.']);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[support/messages] ' . $e->getMessage());
        json_response(false, null, 'Unable to send your message. Please try again.', 500);
    }

    json_response(true, ['message_id' => $messageId], 'Message sent.', 201);
}

json_response(false, null, 'Method not allowed.', 405);
