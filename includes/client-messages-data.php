<?php
/**
 * NexFlow CRM — Client Portal Messages Data Loader
 * Authoritative, client-safe message and conversation data provider.
 * Strictly enforces organization and company isolation, and filters out internal notes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/client-auth.php';
require_once __DIR__ . '/client-helpers.php';

/**
 * Format a timestamp into user-friendly client display string.
 */
function client_format_message_time(?string $dateStr): array
{
    if (empty($dateStr)) {
        return ['time' => '', 'dateStr' => '', 'full' => ''];
    }
    $ts = strtotime($dateStr);
    if (!$ts) {
        return ['time' => $dateStr, 'dateStr' => $dateStr, 'full' => $dateStr];
    }

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $msgDate = date('Y-m-d', $ts);

    if ($msgDate === $today) {
        return [
            'time'    => date('g:i A', $ts),
            'dateStr' => 'Today',
            'full'    => 'Today, ' . date('g:i A', $ts)
        ];
    } elseif ($msgDate === $yesterday) {
        return [
            'time'    => 'Yesterday',
            'dateStr' => 'Yesterday',
            'full'    => 'Yesterday, ' . date('g:i A', $ts)
        ];
    } elseif (date('Y', $ts) === date('Y')) {
        return [
            'time'    => date('M j', $ts),
            'dateStr' => date('M j', $ts),
            'full'    => date('M j, g:i A', $ts)
        ];
    } else {
        return [
            'time'    => date('M j, Y', $ts),
            'dateStr' => date('M j, Y', $ts),
            'full'    => date('M j, Y g:i A', $ts)
        ];
    }
}

/**
 * Extract initials for avatar circles.
 */
function client_message_initials(?string $name): string
{
    if (empty($name)) return '??';
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, min(2, strlen($name))));
}

/**
 * Compute the latest client-safe preview for a conversation.
 * Strictly excludes internal notes (sender_type='internal_note', direction='internal', channel='Internal').
 * Never exposes "📌 Internal note: ..." from inbox_conversations.preview.
 */
function client_get_safe_conversation_preview(PDO $pdo, int $convId, int $orgId): array
{
    $stmt = $pdo->prepare("
        SELECT body, sent_at, sender_name, direction
        FROM inbox_messages
        WHERE conversation_id = :conv_id
          AND organization_id = :org_id
          AND direction IN ('inbound', 'outbound')
          AND sender_type != 'internal_note'
          AND LOWER(channel) != 'internal'
        ORDER BY sent_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([
        ':conv_id' => $convId,
        ':org_id'  => $orgId,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['body'])) {
        $cleanBody = trim(strip_tags((string)$row['body']));
        $preview = mb_substr($cleanBody, 0, 90);
        if (mb_strlen($cleanBody) > 90) {
            $preview .= '...';
        }
        return [
            'preview'        => $preview,
            'last_message_at'=> (string)$row['sent_at'],
            'last_sender'    => (string)$row['sender_name'],
            'direction'      => (string)$row['direction'],
            'has_messages'   => true
        ];
    }

    return [
        'preview'        => 'No messages yet',
        'last_message_at'=> null,
        'last_sender'    => '',
        'direction'      => '',
        'has_messages'   => false
    ];
}

/**
 * Format an individual message row for safe client presentation.
 * Identifies incoming vs outgoing, excludes private employee email/phone.
 */
function client_format_message_item(array $m): array
{
    $tf = client_format_message_time((string)($m['sent_at'] ?? ''));
    $direction = strtolower(trim((string)($m['direction'] ?? 'inbound')));
    $isOutgoing = ($direction === 'inbound'); // Inbound to CRM = Outgoing from Client

    return [
        'id'         => 'msg-' . ((int)$m['id']),
        'raw_id'     => (int)$m['id'],
        'sender'     => $isOutgoing ? 'You' : (string)($m['sender_name'] ?? 'Account Team'),
        'sender_raw' => (string)($m['sender_name'] ?? ''),
        'is_outgoing'=> $isOutgoing,
        'direction'  => $direction,
        'time'       => $tf['full'] ?: ($tf['time'] ?: (string)($m['sent_at'] ?? '')),
        'timestamp'  => (string)($m['sent_at'] ?? ''),
        'body'       => (string)($m['body'] ?? ''),
        'channel'    => (string)($m['channel'] ?? 'Email')
    ];
}

/**
 * Retrieve all client-safe conversations for the authenticated company.
 * Strictly enforces:
 *   c.organization_id = :org_id
 *   AND c.company_id = :company_id
 *   AND c.company_id IS NOT NULL
 */
function client_get_conversations_data(PDO $pdo, int $orgId, int $companyId): array
{
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.organization_id,
            c.conversation_code,
            c.contact_id,
            c.company_id,
            c.deal_id,
            c.assigned_to,
            c.channel,
            c.subject,
            c.status,
            c.priority,
            c.is_unread,
            c.last_message_at,
            c.created_at,
            u.name AS staff_name,
            u.job_title AS staff_role,
            ct.name AS contact_name
        FROM inbox_conversations c
        LEFT JOIN users u ON u.id = c.assigned_to
        LEFT JOIN contacts ct ON ct.id = c.contact_id
        WHERE c.organization_id = :org_id
          AND c.company_id = :company_id
          AND c.company_id IS NOT NULL
        ORDER BY c.last_message_at DESC, c.id DESC
    ");
    $stmt->execute([
        ':org_id'     => $orgId,
        ':company_id' => $companyId,
    ]);
    $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $conversations = [];
    $unreadCount = 0;

    foreach ($rawRows as $r) {
        $convId = (int)$r['id'];
        $safePreview = client_get_safe_conversation_preview($pdo, $convId, $orgId);
        
        $displayTime = $safePreview['last_message_at'] ?: (string)$r['last_message_at'];
        $tf = client_format_message_time($displayTime);

        $staffName = !empty($r['staff_name']) ? trim((string)$r['staff_name']) : 'Account Team';
        $staffRole = !empty($r['staff_role']) ? trim((string)$r['staff_role']) : 'Account Manager';
        $subject   = !empty($r['subject']) ? trim((string)$r['subject']) : '(No Subject)';
        $avatar    = client_message_initials($staffName);

        $isUnread = (bool)$r['is_unread'];
        if ($isUnread) {
            $unreadCount++;
        }

        $conversations[] = [
            'id'              => 'conv-' . $convId,
            'raw_id'          => $convId,
            'code'            => (string)($r['conversation_code'] ?: ('CONV-' . $convId)),
            'name'            => $staffName,
            'role'            => $staffRole,
            'avatar'          => $avatar,
            'subject'         => $subject,
            'preview'         => $safePreview['preview'],
            'has_messages'    => $safePreview['has_messages'],
            'last_message_at' => $displayTime,
            'time'            => $tf['time'],
            'date_full'       => $tf['full'],
            'channel'         => (string)($r['channel'] ?: 'Email'),
            'status'          => (string)($r['status'] ?: 'Open'),
            'priority'        => (string)($r['priority'] ?: 'Normal'),
            'unread'          => $isUnread
        ];
    }

    return [
        'conversations' => $conversations,
        'total'         => count($conversations),
        'unread_count'  => $unreadCount
    ];
}

/**
 * Retrieve a specific conversation thread and its messages.
 * Enforces company ownership and filters out internal notes.
 */
function client_get_conversation_thread(PDO $pdo, int $orgId, int $companyId, int $convId): ?array
{
    // 1. Verify Conversation Header with strict company isolation
    $stmtConv = $pdo->prepare("
        SELECT 
            c.id,
            c.organization_id,
            c.conversation_code,
            c.company_id,
            c.contact_id,
            c.subject,
            c.status,
            c.priority,
            c.channel,
            c.assigned_to,
            c.last_message_at,
            c.created_at,
            u.name AS staff_name,
            u.job_title AS staff_role,
            ct.name AS contact_name
        FROM inbox_conversations c
        LEFT JOIN users u ON u.id = c.assigned_to
        LEFT JOIN contacts ct ON ct.id = c.contact_id
        WHERE c.id = :id
          AND c.organization_id = :org_id
          AND c.company_id = :company_id
          AND c.company_id IS NOT NULL
        LIMIT 1
    ");
    $stmtConv->execute([
        ':id'         => $convId,
        ':org_id'     => $orgId,
        ':company_id' => $companyId,
    ]);
    $c = $stmtConv->fetch(PDO::FETCH_ASSOC);

    if (!$c) {
        return null;
    }

    // 2. Fetch Client-Safe Messages strictly excluding internal notes
    $stmtMsgs = $pdo->prepare("
        SELECT 
            m.id,
            m.organization_id,
            m.conversation_id,
            m.sender_type,
            m.sender_name,
            m.channel,
            m.direction,
            m.body,
            m.sent_at
        FROM inbox_messages m
        WHERE m.conversation_id = :conv_id
          AND m.organization_id = :org_id
          AND m.direction IN ('inbound', 'outbound')
          AND m.sender_type != 'internal_note'
          AND LOWER(m.channel) != 'internal'
        ORDER BY m.sent_at ASC, m.id ASC
    ");
    $stmtMsgs->execute([
        ':conv_id' => $convId,
        ':org_id'  => $orgId,
    ]);
    $rawMsgs = $stmtMsgs->fetchAll(PDO::FETCH_ASSOC);

    $messages = [];
    foreach ($rawMsgs as $m) {
        $messages[] = client_format_message_item($m);
    }

    $staffName = !empty($c['staff_name']) ? trim((string)$c['staff_name']) : 'Account Team';
    $staffRole = !empty($c['staff_role']) ? trim((string)$c['staff_role']) : 'Account Manager';
    $avatar    = client_message_initials($staffName);

    return [
        'id'          => 'conv-' . $convId,
        'raw_id'      => $convId,
        'code'        => (string)($c['conversation_code'] ?: ('CONV-' . $convId)),
        'name'        => $staffName,
        'role'        => $staffRole,
        'avatar'      => $avatar,
        'subject'     => (string)($c['subject'] ?: '(No Subject)'),
        'status'      => (string)($c['status'] ?: 'Open'),
        'channel'     => (string)($c['channel'] ?: 'Email'),
        'messages'    => $messages,
        'total_msgs'  => count($messages)
    ];
}

/**
 * Send a reply in an existing authorized conversation.
 * Strictly verifies company ownership, prevents duplicate submissions,
 * inserts the message, and updates conversation preview and unread status.
 */
function client_send_message_reply(PDO $pdo, array $client, int $convId, string $body): array
{
    $orgId     = (int)($client['organization_id'] ?? 0);
    $companyId = (int)($client['company_id'] ?? 0);
    $contactId = (int)($client['contact_id'] ?? 0);

    $senderName = !empty($client['contact_name']) ? (string)$client['contact_name'] : trim((string)($client['first_name'] ?? '') . ' ' . (string)($client['last_name'] ?? ''));
    if ($senderName === '') {
        $senderName = 'Client Contact';
    }
    $senderEmail = (string)($client['contact_email'] ?? ($client['portal_email'] ?? ''));
    $senderPhone = (string)($client['contact_phone'] ?? '');

    // 1. Verify conversation ownership
    $stmtConv = $pdo->prepare("
        SELECT id, channel, subject, status
        FROM inbox_conversations
        WHERE id = :conv_id
          AND organization_id = :org_id
          AND company_id = :company_id
          AND company_id IS NOT NULL
        LIMIT 1
    ");
    $stmtConv->execute([
        ':conv_id'    => $convId,
        ':org_id'     => $orgId,
        ':company_id' => $companyId,
    ]);
    $conv = $stmtConv->fetch(PDO::FETCH_ASSOC);

    if (!$conv) {
        return [
            'success'   => false,
            'message'   => 'Conversation not found or access denied.',
            'http_code' => 404
        ];
    }

    // 2. Prevent identical duplicate submissions within 2 seconds
    $stmtDup = $pdo->prepare("
        SELECT id
        FROM inbox_messages
        WHERE conversation_id = :conv_id
          AND organization_id = :org_id
          AND sender_type = 'contact'
          AND sender_id = :contact_id
          AND body = :body
          AND sent_at >= (NOW() - INTERVAL 2 SECOND)
        LIMIT 1
    ");
    $stmtDup->execute([
        ':conv_id'    => $convId,
        ':org_id'     => $orgId,
        ':contact_id' => $contactId,
        ':body'       => $body,
    ]);
    if ($stmtDup->fetchColumn()) {
        return [
            'success'   => false,
            'message'   => 'Duplicate message detected. Please wait a moment before sending again.',
            'http_code' => 422
        ];
    }

    // 3. Database transaction
    $pdo->beginTransaction();
    try {
        $preview = mb_substr($body, 0, 100);

        // A. Insert message
        $stmtMsg = $pdo->prepare("
            INSERT INTO inbox_messages (
                organization_id, conversation_id, sender_type, sender_id,
                sender_name, sender_email, sender_phone, channel, direction,
                body, sent_at, created_at
            ) VALUES (
                :org_id, :conv_id, 'contact', :contact_id,
                :sender_name, :sender_email, :sender_phone, :channel, 'inbound',
                :body, NOW(), NOW()
            )
        ");
        $stmtMsg->execute([
            ':org_id'       => $orgId,
            ':conv_id'      => $convId,
            ':contact_id'   => $contactId,
            ':sender_name'  => $senderName,
            ':sender_email' => $senderEmail,
            ':sender_phone' => $senderPhone,
            ':channel'      => $conv['channel'] ?: 'Email',
            ':body'         => $body,
        ]);
        $msgId = (int)$pdo->lastInsertId();

        // B. Update conversation preview, unread state, status, and timestamps
        $stmtUpdate = $pdo->prepare("
            UPDATE inbox_conversations
            SET preview = :preview,
                is_unread = 1,
                status = 'Open',
                last_message_at = NOW(),
                updated_at = NOW()
            WHERE id = :conv_id AND organization_id = :org_id AND company_id = :company_id
        ");
        $stmtUpdate->execute([
            ':preview'    => $preview,
            ':conv_id'    => $convId,
            ':org_id'     => $orgId,
            ':company_id' => $companyId,
        ]);

        $pdo->commit();

        $formattedMsg = [
            'id'          => 'msg-' . $msgId,
            'raw_id'      => $msgId,
            'sender'      => 'You',
            'sender_raw'  => $senderName,
            'is_outgoing' => true,
            'direction'   => 'inbound',
            'time'        => 'Just now',
            'timestamp'   => date('Y-m-d H:i:s'),
            'body'        => $body,
            'channel'     => $conv['channel'] ?: 'Email'
        ];

        return [
            'success'   => true,
            'message'   => 'Message sent successfully.',
            'http_code' => 201,
            'data'      => [
                'message'         => $formattedMsg,
                'preview'         => $preview,
                'conversation_id' => $convId
            ]
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'success'   => false,
            'message'   => 'Failed to send message: ' . $e->getMessage(),
            'http_code' => 500
        ];
    }
}

/**
 * Start a new conversation with the assigned account team.
 * Derives Account Executive from company owner or active organization admin,
 * inserts the conversation, creates conversation code, and inserts the initial message.
 */
function client_start_new_conversation(PDO $pdo, array $client, string $subject, string $body): array
{
    $orgId     = (int)($client['organization_id'] ?? 0);
    $companyId = (int)($client['company_id'] ?? 0);
    $contactId = (int)($client['contact_id'] ?? 0);

    $senderName = !empty($client['contact_name']) ? (string)$client['contact_name'] : trim((string)($client['first_name'] ?? '') . ' ' . (string)($client['last_name'] ?? ''));
    if ($senderName === '') {
        $senderName = 'Client Contact';
    }
    $senderEmail = (string)($client['contact_email'] ?? ($client['portal_email'] ?? ''));
    $senderPhone = (string)($client['contact_phone'] ?? '');

    // 1. Resolve Account Executive / Assignee for this company
    $stmtOwner = $pdo->prepare("
        SELECT c.owner_id, u.id AS user_id, u.name AS user_name, u.job_title AS user_role
        FROM companies c
        LEFT JOIN users u ON u.id = c.owner_id AND u.organization_id = c.organization_id AND u.status = 'active'
        WHERE c.id = :company_id AND c.organization_id = :org_id
        LIMIT 1
    ");
    $stmtOwner->execute([
        ':company_id' => $companyId,
        ':org_id'     => $orgId,
    ]);
    $ownerRow = $stmtOwner->fetch(PDO::FETCH_ASSOC);

    $assignedTo = null;
    $staffName  = 'Account Team';
    $staffRole  = 'Account Manager';

    if ($ownerRow && !empty($ownerRow['user_id'])) {
        $assignedTo = (int)$ownerRow['user_id'];
        $staffName  = !empty($ownerRow['user_name']) ? (string)$ownerRow['user_name'] : 'Account Team';
        $staffRole  = !empty($ownerRow['user_role']) ? (string)$ownerRow['user_role'] : 'Account Manager';
    } else {
        // Fallback: discover any active administrator in this organization
        $stmtFallback = $pdo->prepare("
            SELECT id, name, job_title
            FROM users
            WHERE organization_id = :org_id AND status = 'active'
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmtFallback->execute([':org_id' => $orgId]);
        $fallback = $stmtFallback->fetch(PDO::FETCH_ASSOC);
        if ($fallback) {
            $assignedTo = (int)$fallback['id'];
            $staffName  = !empty($fallback['name']) ? (string)$fallback['name'] : 'Account Team';
            $staffRole  = !empty($fallback['job_title']) ? (string)$fallback['job_title'] : 'Account Manager';
        }
    }

    // 2. Transaction
    $pdo->beginTransaction();
    try {
        $preview = mb_substr($body, 0, 100);

        // A. Insert conversation
        $stmtConv = $pdo->prepare("
            INSERT INTO inbox_conversations (
                organization_id, contact_id, company_id, assigned_to,
                channel, subject, preview, status, priority,
                is_unread, is_starred, last_message_at, created_at, updated_at
            ) VALUES (
                :org_id, :contact_id, :company_id, :assigned_to,
                'Email', :subject, :preview, 'Open', 'Normal',
                1, 0, NOW(), NOW(), NOW()
            )
        ");
        $stmtConv->execute([
            ':org_id'      => $orgId,
            ':contact_id'  => $contactId,
            ':company_id'  => $companyId,
            ':assigned_to' => $assignedTo,
            ':subject'     => $subject,
            ':preview'     => $preview,
        ]);
        $newConvId = (int)$pdo->lastInsertId();

        // Update code
        $code = 'CONV-' . $newConvId;
        $pdo->prepare("UPDATE inbox_conversations SET conversation_code = ? WHERE id = ?")->execute([$code, $newConvId]);

        // B. Insert initial message
        $stmtMsg = $pdo->prepare("
            INSERT INTO inbox_messages (
                organization_id, conversation_id, sender_type, sender_id,
                sender_name, sender_email, sender_phone, channel, direction,
                body, sent_at, created_at
            ) VALUES (
                :org_id, :conv_id, 'contact', :contact_id,
                :sender_name, :sender_email, :sender_phone, 'Email', 'inbound',
                :body, NOW(), NOW()
            )
        ");
        $stmtMsg->execute([
            ':org_id'       => $orgId,
            ':conv_id'      => $newConvId,
            ':contact_id'   => $contactId,
            ':sender_name'  => $senderName,
            ':sender_email' => $senderEmail,
            ':sender_phone' => $senderPhone,
            ':body'         => $body,
        ]);
        $msgId = (int)$pdo->lastInsertId();

        $pdo->commit();

        $formattedMsg = [
            'id'          => 'msg-' . $msgId,
            'raw_id'      => $msgId,
            'sender'      => 'You',
            'sender_raw'  => $senderName,
            'is_outgoing' => true,
            'direction'   => 'inbound',
            'time'        => 'Just now',
            'timestamp'   => date('Y-m-d H:i:s'),
            'body'        => $body,
            'channel'     => 'Email'
        ];

        $formattedConv = [
            'id'              => 'conv-' . $newConvId,
            'raw_id'          => $newConvId,
            'code'            => $code,
            'name'            => $staffName,
            'role'            => $staffRole,
            'avatar'          => client_message_initials($staffName),
            'subject'         => $subject,
            'preview'         => $preview,
            'has_messages'    => true,
            'last_message_at' => date('Y-m-d H:i:s'),
            'time'            => 'Just now',
            'date_full'       => 'Today, ' . date('g:i A'),
            'channel'         => 'Email',
            'status'          => 'Open',
            'priority'        => 'Normal',
            'unread'          => false
        ];

        return [
            'success'   => true,
            'message'   => 'Conversation started successfully.',
            'http_code' => 201,
            'data'      => [
                'conversation' => $formattedConv,
                'message'      => $formattedMsg
            ]
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'success'   => false,
            'message'   => 'Failed to start conversation: ' . $e->getMessage(),
            'http_code' => 500
        ];
    }
}