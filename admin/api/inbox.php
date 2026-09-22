<?php
declare(strict_types=1);

/**
 * NexFlow CRM — Inbox API Controller
 * Multi-tenant, organization-scoped controller for Unified Inbox Conversations,
 * Messages, Threads, Assignments, Internal Notes, and CRM Context.
 */

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function inbox_json(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 1. Session Authentication Guard
$currentUser = nexflow_current_user();
if (!$currentUser) {
    inbox_json(false, 'Unauthenticated. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId  = (int)$currentUser['id'];

if ($organizationId <= 0) {
    inbox_json(false, 'Invalid organization context.', [], 400);
}

// 2. Permission Guard
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

if (!hasPermission('inbox') && !is_super_admin($currentUserId)) {
    inbox_json(false, "Forbidden: Missing 'inbox' permission.", [], 403);
}

// Database Connection
try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    inbox_json(false, 'Database connection failed: ' . $e->getMessage(), [], 500);
}

// Support raw JSON input for POST/PUT
$input = $_POST;
if (empty($input) && ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT')) {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = $json;
        }
    }
}

// Helpers
function get_inbox_avatar_color(int $id): string
{
    $colors = ['#7C3AED', '#0284C7', '#10B981', '#F59E0B', '#6366F1', '#EC4899', '#14B8A6', '#8B5CF6'];
    return $colors[$id % count($colors)];
}

function get_inbox_initials(?string $name): string
{
    if (empty($name)) return '??';
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, min(2, strlen($name))));
}

function format_inbox_time(string $dateStr): array
{
    $ts = strtotime($dateStr);
    if (!$ts) return ['time' => $dateStr, 'dateStr' => 'Today'];

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $msgDate = date('Y-m-d', $ts);

    if ($msgDate === $today) {
        return [
            'time' => date('g:i A', $ts),
            'dateStr' => 'Today'
        ];
    } elseif ($msgDate === $yesterday) {
        return [
            'time' => 'Yesterday',
            'dateStr' => 'Yesterday'
        ];
    } else {
        return [
            'time' => date('M j', $ts),
            'dateStr' => date('M j, Y', $ts)
        ];
    }
}

// Log CRM activity into team_activities
function log_inbox_activity(PDO $pdo, int $orgId, ?int $userId, string $type, string $title, ?string $desc = null, ?string $relatedEntity = null, ?int $relatedId = null): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO team_activities 
            (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$orgId, $userId ?? 1, $type, $title, $desc, $relatedEntity, $relatedId, $userId ?? 1]);
    } catch (Throwable $e) {}
}

// Auto-expire snoozed conversations whose snooze timestamp has passed
try {
    $pdo->prepare("UPDATE inbox_conversations SET snoozed_until = NULL WHERE snoozed_until IS NOT NULL AND snoozed_until <= NOW() AND organization_id = ?")->execute([$organizationId]);
} catch (Throwable $e) {}

// ============================================================
// 1. GET action=list
// ============================================================
if ($action === 'list') {
    $folder   = trim((string)($_GET['folder'] ?? 'all'));
    $channel  = trim((string)($_GET['channel'] ?? 'All'));
    $search   = trim((string)($_GET['search'] ?? ''));
    $sort     = trim((string)($_GET['sort'] ?? 'newest'));
    $team     = trim((string)($_GET['team'] ?? ''));

    // Special handling for Drafts folder (database-backed in inbox_drafts)
    if ($folder === 'drafts') {
        $dWhere = ["dr.organization_id = :org_id", "dr.user_id = :my_user_id"];
        $dParams = [
            ':org_id'     => $organizationId,
            ':my_user_id' => $currentUserId
        ];

        if ($channel !== 'All' && !empty($channel)) {
            $dWhere[] = "dr.channel = :channel";
            $dParams[':channel'] = $channel;
        }

        if ($search !== '') {
            $dWhere[] = "CONCAT_WS(' ', dr.subject, dr.body, dr.recipient, ct.name, cp.name) LIKE :search";
            $dParams[':search'] = '%' . $search . '%';
        }

        $dSql = "
            SELECT 
                dr.*,
                ct.name AS contact_name,
                ct.job_title AS contact_title,
                ct.email AS contact_email,
                ct.phone AS contact_phone,
                cp.name AS company_name,
                d.name AS deal_name,
                d.value AS deal_value
            FROM inbox_drafts dr
            LEFT JOIN contacts ct ON ct.id = dr.contact_id AND ct.organization_id = dr.organization_id
            LEFT JOIN companies cp ON cp.id = dr.company_id AND cp.organization_id = dr.organization_id
            LEFT JOIN deals d ON d.id = dr.deal_id AND d.organization_id = dr.organization_id
            WHERE " . implode(' AND ', $dWhere) . "
            ORDER BY dr.updated_at DESC
        ";
        $dStmt = $pdo->prepare($dSql);
        $dStmt->execute($dParams);
        $draftRows = $dStmt->fetchAll(PDO::FETCH_ASSOC);

        $conversations = [];
        foreach ($draftRows as $r) {
            $draftId = (int)$r['id'];
            $recipientText = $r['recipient'] ?: ($r['contact_name'] ?: 'No Recipient');
            $contactTitle = $r['contact_title'] ?: 'Draft';
            $companyName = $r['company_name'] ?: 'N/A';
            $tf = format_inbox_time((string)$r['updated_at']);

            $dealStr = 'N/A';
            if (!empty($r['deal_name'])) {
                $val = !empty($r['deal_value']) ? '$' . number_format((float)$r['deal_value']) : '';
                $dealStr = $val !== '' ? "{$val} ({$r['deal_name']})" : $r['deal_name'];
            }

            $conversations[] = [
                'id'            => 'draft_' . $draftId,
                'db_id'         => $draftId,
                'draft_id'      => $draftId,
                'is_draft'      => true,
                'code'          => "DRAFT-{$draftId}",
                'contact'       => $recipientText,
                'title'         => 'Draft',
                'company'       => $companyName,
                'email'         => $r['contact_email'] ?: ($r['recipient'] ?: ''),
                'phone'         => $r['contact_phone'] ?: '',
                'avatar'        => get_inbox_initials($recipientText),
                'avatarBg'      => '#64748B',
                'channel'       => $r['channel'] ?: 'Email',
                'channelIcon'   => ($r['channel'] === 'WhatsApp') ? '💬' : '✉️',
                'subject'       => $r['subject'] ?: '(Draft - No Subject)',
                'preview'       => $r['body'] ? mb_substr($r['body'], 0, 100) : '(Empty Draft)',
                'timestamp'     => $tf['time'],
                'dateStr'       => $tf['dateStr'],
                'unread'        => false,
                'starred'       => false,
                'priority'      => 'Normal',
                'status'        => 'Draft',
                'snoozed_until' => null,
                'owner'         => $currentUser['name'],
                'owner_id'      => $currentUserId,
                'ownerInitials' => get_inbox_initials($currentUser['name']),
                'ownerColor'    => '#64748B',
                'deals'         => $dealStr,
                'contact_id'    => $r['contact_id'] ? (int)$r['contact_id'] : null,
                'company_id'    => $r['company_id'] ? (int)$r['company_id'] : null,
                'deal_id'       => $r['deal_id'] ? (int)$r['deal_id'] : null,
                'recipient_raw' => $r['recipient'] ?: '',
                'body_raw'      => $r['body'] ?: ''
            ];
        }

        inbox_json(true, 'Drafts retrieved.', [
            'conversations' => $conversations,
            'count'         => count($conversations)
        ]);
    }

    $where = ["c.organization_id = :org_id"];
    $params = [':org_id' => $organizationId];

    // Folder filtering
    if ($folder === 'unread') {
        $where[] = "c.is_unread = 1 AND c.status != 'Archived' AND (c.snoozed_until IS NULL OR c.snoozed_until <= NOW())";
    } elseif ($folder === 'assigned' || $folder === 'assigned_to_me') {
        $where[] = "c.assigned_to = :my_user_id AND c.status != 'Archived' AND (c.snoozed_until IS NULL OR c.snoozed_until <= NOW())";
        $params[':my_user_id'] = $currentUserId;
    } elseif ($folder === 'starred') {
        $where[] = "c.is_starred = 1 AND c.status != 'Archived' AND (c.snoozed_until IS NULL OR c.snoozed_until <= NOW())";
    } elseif ($folder === 'snoozed') {
        $where[] = "c.snoozed_until IS NOT NULL AND c.snoozed_until > NOW() AND c.status != 'Archived'";
    } elseif ($folder === 'archived') {
        $where[] = "c.status = 'Archived'";
    } else {
        // 'all' folder excludes archived and active snoozed conversations
        $where[] = "c.status != 'Archived' AND (c.snoozed_until IS NULL OR c.snoozed_until <= NOW())";
    }

    // Channel filtering
    if ($channel !== 'All' && !empty($channel)) {
        $where[] = "c.channel = :channel";
        $params[':channel'] = $channel;
    }

    // Team filtering
    if (!empty($team) && $team !== 'All') {
        if (is_numeric($team)) {
            $where[] = "c.team_id = :team_id";
            $params[':team_id'] = (int)$team;
        } else {
            $where[] = "LOWER(tm.name) LIKE :team_name";
            $params[':team_name'] = '%' . strtolower($team) . '%';
        }
    }

    // Search query
    if ($search !== '') {
        $where[] = "CONCAT_WS(' ', c.subject, c.preview, ct.name, cp.name, ct.email, ct.phone, l.name, l.email) LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }

    // Sorting
    $orderBy = "c.last_message_at DESC";
    if ($sort === 'oldest') {
        $orderBy = "c.last_message_at ASC";
    } elseif ($sort === 'unread') {
        $orderBy = "c.is_unread DESC, c.last_message_at DESC";
    } elseif ($sort === 'priority') {
        $orderBy = "FIELD(c.priority, 'Urgent', 'High', 'Normal', 'Low'), c.last_message_at DESC";
    }

    $sql = "
        SELECT 
            c.*,
            ct.name AS contact_name,
            ct.job_title AS contact_title,
            ct.email AS contact_email,
            ct.phone AS contact_phone,
            cp.name AS company_name,
            d.name AS deal_name,
            d.value AS deal_value,
            l.name AS lead_name,
            l.company AS lead_company,
            l.email AS lead_email,
            l.phone AS lead_phone,
            u.name AS owner_name,
            u.display_name AS owner_display_name,
            tm.name AS team_name
        FROM inbox_conversations c
        LEFT JOIN contacts ct ON ct.id = c.contact_id AND ct.organization_id = c.organization_id
        LEFT JOIN companies cp ON cp.id = c.company_id AND cp.organization_id = c.organization_id
        LEFT JOIN deals d ON d.id = c.deal_id AND d.organization_id = c.organization_id
        LEFT JOIN leads l ON l.id = c.lead_id AND l.organization_id = c.organization_id
        LEFT JOIN users u ON u.id = c.assigned_to AND u.organization_id = c.organization_id
        LEFT JOIN teams tm ON tm.id = c.team_id AND tm.organization_id = c.organization_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$orderBy}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $conversations = [];
    foreach ($rows as $r) {
        $convId = (int)$r['id'];
        $contactName = $r['contact_id'] ? ($r['contact_name'] ?: 'Unknown Contact') : ($r['lead_name'] ?: 'Unknown Contact');
        $contactTitle = $r['contact_title'] ?: 'Contact';
        $companyName = $r['company_id'] ? ($r['company_name'] ?: 'N/A') : 'N/A';
        $contactEmail = $r['contact_email'] ?: ($r['lead_email'] ?: '');
        $contactPhone = $r['contact_phone'] ?: ($r['lead_phone'] ?: '');
        $ownerName = $r['owner_display_name'] ?: ($r['owner_name'] ?: 'Unassigned');

        $tf = format_inbox_time((string)$r['last_message_at']);

        $dealStr = 'N/A';
        if (!empty($r['deal_id']) && !empty($r['deal_name'])) {
            $val = !empty($r['deal_value']) ? '$' . number_format((float)$r['deal_value']) : '';
            $dealStr = $val !== '' ? "{$val} ({$r['deal_name']})" : $r['deal_name'];
        }

        $conversations[] = [
            'id'            => (string)$convId,
            'db_id'         => $convId,
            'code'          => $r['conversation_code'] ?: "CONV-{$convId}",
            'contact'       => $contactName,
            'title'         => $contactTitle,
            'company'       => $companyName,
            'email'         => $contactEmail,
            'phone'         => $contactPhone,
            'avatar'        => get_inbox_initials($contactName),
            'avatarBg'      => get_inbox_avatar_color($r['contact_id'] ?: $convId),
            'channel'       => $r['channel'] ?: 'Email',
            'channelIcon'   => ($r['channel'] === 'WhatsApp') ? '💬' : '✉️',
            'subject'       => $r['subject'] ?: '(No Subject)',
            'preview'       => $r['preview'] ?: '',
            'timestamp'     => $tf['time'],
            'dateStr'       => $tf['dateStr'],
            'unread'        => (bool)$r['is_unread'],
            'starred'       => (bool)$r['is_starred'],
            'priority'      => $r['priority'] ?: 'Normal',
            'status'        => $r['status'] ?: 'Open',
            'snoozed_until' => $r['snoozed_until'],
            'owner'         => $ownerName,
            'owner_id'      => $r['assigned_to'] ? (int)$r['assigned_to'] : null,
            'ownerInitials' => get_inbox_initials($ownerName),
            'ownerColor'    => get_inbox_avatar_color($r['assigned_to'] ? (int)$r['assigned_to'] : 0),
            'deals'         => $dealStr,
            'contact_id'    => $r['contact_id'] ? (int)$r['contact_id'] : null,
            'company_id'    => $r['company_id'] ? (int)$r['company_id'] : null,
            'deal_id'       => $r['deal_id'] ? (int)$r['deal_id'] : null,
            'lead_id'       => $r['lead_id'] ? (int)$r['lead_id'] : null,
            'team_id'       => $r['team_id'] ? (int)$r['team_id'] : null,
            'team_name'     => $r['team_name'] ?: ''
        ];
    }

    inbox_json(true, 'Conversations retrieved successfully.', [
        'conversations' => $conversations,
        'count'         => count($conversations)
    ]);
}

// ============================================================
// 2. GET action=counts
// ============================================================
if ($action === 'counts') {
    $cStmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN status != 'Archived' AND (snoozed_until IS NULL OR snoozed_until <= NOW()) THEN 1 END) AS count_all,
            COUNT(CASE WHEN is_unread = 1 AND status != 'Archived' AND (snoozed_until IS NULL OR snoozed_until <= NOW()) THEN 1 END) AS count_unread,
            COUNT(CASE WHEN assigned_to = :my_user_id AND status != 'Archived' AND (snoozed_until IS NULL OR snoozed_until <= NOW()) THEN 1 END) AS count_assigned,
            COUNT(CASE WHEN is_starred = 1 AND status != 'Archived' AND (snoozed_until IS NULL OR snoozed_until <= NOW()) THEN 1 END) AS count_starred,
            COUNT(CASE WHEN snoozed_until IS NOT NULL AND snoozed_until > NOW() AND status != 'Archived' THEN 1 END) AS count_snoozed,
            COUNT(CASE WHEN status = 'Archived' THEN 1 END) AS count_archived,
            COUNT(CASE WHEN channel = 'Email' AND status != 'Archived' AND (snoozed_until IS NULL OR snoozed_until <= NOW()) THEN 1 END) AS count_email,
            COUNT(CASE WHEN channel = 'WhatsApp' AND status != 'Archived' AND (snoozed_until IS NULL OR snoozed_until <= NOW()) THEN 1 END) AS count_whatsapp
        FROM inbox_conversations
        WHERE organization_id = :org_id
    ");
    $cStmt->execute([
        ':my_user_id' => $currentUserId,
        ':org_id'     => $organizationId
    ]);
    $counts = $cStmt->fetch(PDO::FETCH_ASSOC);

    // Drafts count (scoped strictly by organization_id AND user_id)
    $drStmt = $pdo->prepare("SELECT COUNT(*) FROM inbox_drafts WHERE organization_id = ? AND user_id = ?");
    $drStmt->execute([$organizationId, $currentUserId]);
    $draftsCount = (int)$drStmt->fetchColumn();

    // Team inboxes count
    $salesCount = 0;
    $supportCount = 0;
    try {
        $tmStmt = $pdo->prepare("
            SELECT tm.name, COUNT(c.id) AS cnt
            FROM teams tm
            LEFT JOIN inbox_conversations c ON c.team_id = tm.id AND c.organization_id = tm.organization_id AND c.status != 'Archived' AND (c.snoozed_until IS NULL OR c.snoozed_until <= NOW())
            WHERE tm.organization_id = ?
            GROUP BY tm.id, tm.name
        ");
        $tmStmt->execute([$organizationId]);
        while ($tRow = $tmStmt->fetch(PDO::FETCH_ASSOC)) {
            $tName = strtolower($tRow['name'] ?? '');
            if (strpos($tName, 'sale') !== false) $salesCount += (int)$tRow['cnt'];
            if (strpos($tName, 'support') !== false) $supportCount += (int)$tRow['cnt'];
        }
    } catch (Throwable $e) {}

    inbox_json(true, 'Counts retrieved.', [
        'all'          => (int)($counts['count_all'] ?? 0),
        'unread'       => (int)($counts['count_unread'] ?? 0),
        'assigned'     => (int)($counts['count_assigned'] ?? 0),
        'starred'      => (int)($counts['count_starred'] ?? 0),
        'snoozed'      => (int)($counts['count_snoozed'] ?? 0),
        'archived'     => (int)($counts['count_archived'] ?? 0),
        'drafts'       => $draftsCount,
        'email'        => (int)($counts['count_email'] ?? 0),
        'whatsapp'     => (int)($counts['count_whatsapp'] ?? 0),
        'sales_team'   => $salesCount,
        'support_team' => $supportCount,
        'total_unread' => (int)($counts['count_unread'] ?? 0)
    ]);
}

// ============================================================
// 3. GET action=thread
// ============================================================
if ($action === 'thread') {
    $convId = (int)($_GET['id'] ?? 0);
    if ($convId <= 0) {
        inbox_json(false, 'Conversation ID is required.', [], 422);
    }

    $cStmt = $pdo->prepare("
        SELECT 
            c.*,
            ct.name AS contact_name,
            ct.job_title AS contact_title,
            ct.email AS contact_email,
            ct.phone AS contact_phone,
            cp.name AS company_name,
            d.name AS deal_name,
            d.value AS deal_value,
            l.name AS lead_name,
            l.company AS lead_company,
            l.email AS lead_email,
            l.phone AS lead_phone,
            u.name AS owner_name,
            u.display_name AS owner_display_name
        FROM inbox_conversations c
        LEFT JOIN contacts ct ON ct.id = c.contact_id AND ct.organization_id = c.organization_id
        LEFT JOIN companies cp ON cp.id = c.company_id AND cp.organization_id = c.organization_id
        LEFT JOIN deals d ON d.id = c.deal_id AND d.organization_id = c.organization_id
        LEFT JOIN leads l ON l.id = c.lead_id AND l.organization_id = c.organization_id
        LEFT JOIN users u ON u.id = c.assigned_to AND u.organization_id = c.organization_id
        WHERE c.id = ? AND c.organization_id = ?
        LIMIT 1
    ");
    $cStmt->execute([$convId, $organizationId]);
    $c = $cStmt->fetch(PDO::FETCH_ASSOC);

    if (!$c) {
        inbox_json(false, 'Conversation not found or access denied.', [], 404);
    }

    $contactName = $c['contact_id'] ? ($c['contact_name'] ?: 'Unknown Contact') : ($c['lead_name'] ?: 'Unknown Contact');
    $contactTitle = $c['contact_title'] ?: 'Contact';
    $companyName = $c['company_id'] ? ($c['company_name'] ?: 'N/A') : 'N/A';
    $contactEmail = $c['contact_email'] ?: ($c['lead_email'] ?: '');
    $contactPhone = $c['contact_phone'] ?: ($c['lead_phone'] ?: '');
    $ownerName = $c['owner_display_name'] ?: ($c['owner_name'] ?: 'Unassigned');

    $tf = format_inbox_time((string)$c['last_message_at']);

    $dealStr = 'N/A';
    if (!empty($c['deal_id']) && !empty($c['deal_name'])) {
        $val = !empty($c['deal_value']) ? '$' . number_format((float)$c['deal_value']) : '';
        $dealStr = $val !== '' ? "{$val} ({$c['deal_name']})" : $c['deal_name'];
    }

    // Fetch messages
    $mStmt = $pdo->prepare("
        SELECT *
        FROM inbox_messages
        WHERE conversation_id = ? AND organization_id = ?
        ORDER BY sent_at ASC, id ASC
    ");
    $mStmt->execute([$convId, $organizationId]);
    $messagesRaw = $mStmt->fetchAll(PDO::FETCH_ASSOC);

    $messages = [];
    foreach ($messagesRaw as $m) {
        $mt = strtotime($m['sent_at']);
        $timeStr = $mt ? date('M j, g:i A', $mt) : $m['sent_at'];

        $messages[] = [
            'id'         => (string)$m['id'],
            'sender'     => $m['sender_name'],
            'senderType' => $m['sender_type'], // contact, user, internal_note
            'avatar'     => get_inbox_initials($m['sender_name']),
            'avatarBg'   => ($m['sender_type'] === 'contact') ? get_inbox_avatar_color($c['contact_id'] ?: $convId) : '#059669',
            'time'       => $timeStr,
            'content'    => $m['body'],
            'channel'    => $m['channel'],
            'direction'  => $m['direction']
        ];
    }

    $conversation = [
        'id'            => (string)$convId,
        'db_id'         => $convId,
        'code'          => $c['conversation_code'] ?: "CONV-{$convId}",
        'contact'       => $contactName,
        'title'         => $contactTitle,
        'company'       => $companyName,
        'email'         => $contactEmail,
        'phone'         => $contactPhone,
        'avatar'        => get_inbox_initials($contactName),
        'avatarBg'      => get_inbox_avatar_color($c['contact_id'] ?: $convId),
        'channel'       => $c['channel'] ?: 'Email',
        'channelIcon'   => ($c['channel'] === 'WhatsApp') ? '💬' : '✉️',
        'subject'       => $c['subject'] ?: '(No Subject)',
        'preview'       => $c['preview'] ?: '',
        'timestamp'     => $tf['time'],
        'dateStr'       => $tf['dateStr'],
        'unread'        => (bool)$c['is_unread'],
        'starred'       => (bool)$c['is_starred'],
        'priority'      => $c['priority'] ?: 'Normal',
        'status'        => $c['status'] ?: 'Open',
        'snoozed_until' => $c['snoozed_until'],
        'owner'         => $ownerName,
        'owner_id'      => $c['assigned_to'] ? (int)$c['assigned_to'] : null,
        'ownerInitials' => get_inbox_initials($ownerName),
        'ownerColor'    => get_inbox_avatar_color($c['assigned_to'] ? (int)$c['assigned_to'] : 0),
        'deals'         => $dealStr,
        'contact_id'    => $c['contact_id'] ? (int)$c['contact_id'] : null,
        'company_id'    => $c['company_id'] ? (int)$c['company_id'] : null,
        'deal_id'       => $c['deal_id'] ? (int)$c['deal_id'] : null,
        'lead_id'       => $c['lead_id'] ? (int)$c['lead_id'] : null,
        'messages'      => $messages
    ];

    inbox_json(true, 'Thread retrieved.', ['conversation' => $conversation]);
}

// ============================================================
// 4. GET action=contact_context
// ============================================================
if ($action === 'contact_context') {
    $contactId = (int)($_GET['contact_id'] ?? 0);
    $convId = (int)($_GET['conversation_id'] ?? 0);

    if ($contactId <= 0 && $convId > 0) {
        $cStmt = $pdo->prepare("SELECT contact_id FROM inbox_conversations WHERE id = ? AND organization_id = ?");
        $cStmt->execute([$convId, $organizationId]);
        $contactId = (int)$cStmt->fetchColumn();
    }

    if ($contactId <= 0) {
        inbox_json(false, 'Contact not linked.', ['not_linked' => true], 200);
    }

    // Query contact
    $ctStmt = $pdo->prepare("
        SELECT 
            ct.*,
            cp.name AS primary_company_name,
            u.name AS owner_name
        FROM contacts ct
        LEFT JOIN companies cp ON cp.id = ct.company_id AND cp.organization_id = ct.organization_id
        LEFT JOIN users u ON u.id = ct.owner_id AND u.organization_id = ct.organization_id
        WHERE ct.id = ? AND ct.organization_id = ?
        LIMIT 1
    ");
    $ctStmt->execute([$contactId, $organizationId]);
    $ct = $ctStmt->fetch(PDO::FETCH_ASSOC);

    if (!$ct) {
        inbox_json(false, 'Contact not found or access denied.', ['not_linked' => true], 404);
    }

    // Query associated companies via contact_companies
    $cpAssocStmt = $pdo->prepare("
        SELECT DISTINCT cp.id, cp.name
        FROM companies cp
        LEFT JOIN contact_companies cc ON cc.company_id = cp.id
        WHERE (cp.id = ? OR cc.contact_id = ?) AND cp.organization_id = ?
        ORDER BY cp.name ASC
    ");
    $cpAssocStmt->execute([(int)$ct['company_id'], $contactId, $organizationId]);
    $assocCompanies = $cpAssocStmt->fetchAll(PDO::FETCH_ASSOC);

    // Associated deals
    $dStmt = $pdo->prepare("
        SELECT id, name, value, stage
        FROM deals
        WHERE contact_id = ? AND organization_id = ? AND status = 'open'
        ORDER BY value DESC LIMIT 3
    ");
    $dStmt->execute([$contactId, $organizationId]);
    $deals = $dStmt->fetchAll(PDO::FETCH_ASSOC);

    $dealsStr = 'N/A';
    if (!empty($deals)) {
        $first = $deals[0];
        $val = '$' . number_format((float)$first['value']);
        $dealsStr = "{$val} ({$first['name']})";
    }

    $context = [
        'id'              => (int)$ct['id'],
        'name'            => $ct['name'],
        'title'           => $ct['job_title'] ?: 'Contact',
        'company'         => $ct['primary_company_name'] ?: ($ct['company_name'] ?: 'N/A'),
        'company_id'      => $ct['company_id'] ? (int)$ct['company_id'] : null,
        'assoc_companies' => $assocCompanies,
        'email'           => $ct['email'] ?: 'N/A',
        'phone'           => $ct['phone'] ?: 'N/A',
        'channel'         => $ct['preferred_channel'] ?: 'Email',
        'owner'           => $ct['owner_name'] ?: 'Unassigned',
        'owner_id'        => $ct['owner_id'] ? (int)$ct['owner_id'] : null,
        'relationship'    => $ct['relationship'] ?: 'Prospect',
        'location'        => $ct['location'] ?: '—',
        'deals'           => $dealsStr,
        'deals_list'      => $deals,
        'avatar'          => get_inbox_initials($ct['name']),
        'avatarBg'        => get_inbox_avatar_color((int)$ct['id']),
        'tags'            => ['Contact', $ct['relationship'] ?: 'Client']
    ];

    inbox_json(true, 'Contact context retrieved.', ['context' => $context]);
}

// ============================================================
// 4b. GET action=company_detail
// ============================================================
if ($action === 'company_detail') {
    $companyId = (int)($_GET['company_id'] ?? $_GET['id'] ?? 0);
    $convId = (int)($_GET['conversation_id'] ?? 0);

    if ($companyId <= 0 && $convId > 0) {
        $cStmt = $pdo->prepare("SELECT company_id FROM inbox_conversations WHERE id = ? AND organization_id = ?");
        $cStmt->execute([$convId, $organizationId]);
        $companyId = (int)$cStmt->fetchColumn();
    }

    if ($companyId <= 0) {
        inbox_json(false, 'Company not linked.', ['not_linked' => true], 200);
    }

    $cpStmt = $pdo->prepare("
        SELECT 
            cp.*,
            u.name AS owner_name
        FROM companies cp
        LEFT JOIN users u ON u.id = cp.owner_id AND u.organization_id = cp.organization_id
        WHERE cp.id = ? AND cp.organization_id = ?
        LIMIT 1
    ");
    $cpStmt->execute([$companyId, $organizationId]);
    $cp = $cpStmt->fetch(PDO::FETCH_ASSOC);

    if (!$cp) {
        inbox_json(false, 'Company not found or access denied.', ['not_linked' => true], 404);
    }

    // Associated contacts
    $ctStmt = $pdo->prepare("
        SELECT DISTINCT ct.id, ct.name, ct.email, ct.phone, ct.job_title
        FROM contacts ct
        LEFT JOIN contact_companies cc ON cc.contact_id = ct.id
        WHERE (ct.company_id = ? OR cc.company_id = ?) AND ct.organization_id = ? AND ct.is_active = 1
        ORDER BY ct.name ASC LIMIT 10
    ");
    $ctStmt->execute([$companyId, $companyId, $organizationId]);
    $contacts = $ctStmt->fetchAll(PDO::FETCH_ASSOC);

    // Open deals
    $dStmt = $pdo->prepare("
        SELECT id, name, value, stage
        FROM deals
        WHERE (company = ? OR contact_id IN (SELECT id FROM contacts WHERE company_id = ? OR id IN (SELECT contact_id FROM contact_companies WHERE company_id = ?)))
          AND organization_id = ? AND status = 'open'
        ORDER BY value DESC LIMIT 5
    ");
    $dStmt->execute([$cp['name'], $companyId, $companyId, $organizationId]);
    $deals = $dStmt->fetchAll(PDO::FETCH_ASSOC);

    $dealsValue = 0;
    foreach ($deals as $d) {
        $dealsValue += (float)($d['value'] ?? 0);
    }

    $companyData = [
        'id'             => (int)$cp['id'],
        'name'           => $cp['name'],
        'industry'       => $cp['industry'] ?: '—',
        'company_size'   => $cp['company_size'] ?: '—',
        'relationship'   => $cp['relationship'] ?: 'Prospect',
        'domain'         => $cp['domain'] ?: '—',
        'revenue'        => $cp['annual_revenue'] ?: '—',
        'founded_year'   => $cp['founded_year'] ?: '—',
        'location'       => $cp['location'] ?: '—',
        'owner'          => $cp['owner_name'] ?: 'Unassigned',
        'owner_id'       => $cp['owner_id'] ? (int)$cp['owner_id'] : null,
        'tax_reg'        => $cp['tax_registration_number'] ?: '—',
        'linkedin'       => $cp['linkedin'] ?: '',
        'initials'       => get_inbox_initials($cp['name']),
        'avatarBg'       => get_inbox_avatar_color((int)$cp['id']),
        'contacts'       => $contacts,
        'deals'          => $deals,
        'open_deals_cnt' => count($deals),
        'pipeline_value' => '$' . number_format($dealsValue)
    ];

    inbox_json(true, 'Company details retrieved.', ['company' => $companyData]);
}

// ============================================================
// 4c. GET action=deal_detail
// ============================================================
if ($action === 'deal_detail') {
    $dealId = (int)($_GET['deal_id'] ?? $_GET['id'] ?? 0);
    $convId = (int)($_GET['conversation_id'] ?? 0);

    if ($dealId <= 0 && $convId > 0) {
        $cStmt = $pdo->prepare("SELECT deal_id FROM inbox_conversations WHERE id = ? AND organization_id = ?");
        $cStmt->execute([$convId, $organizationId]);
        $dealId = (int)$cStmt->fetchColumn();
    }

    if ($dealId <= 0) {
        inbox_json(false, 'Deal not linked.', ['not_linked' => true], 200);
    }

    $dStmt = $pdo->prepare("
        SELECT 
            d.*,
            ct.name AS contact_person_name,
            ct.email AS contact_email,
            ct.phone AS contact_phone,
            cp.id AS resolved_company_id,
            cp.name AS resolved_company_name,
            u.name AS owner_name
        FROM deals d
        LEFT JOIN contacts ct ON ct.id = d.contact_id AND ct.organization_id = d.organization_id
        LEFT JOIN companies cp ON (cp.name = d.company OR cp.id = ct.company_id) AND cp.organization_id = d.organization_id
        LEFT JOIN users u ON u.id = d.assigned_to AND u.organization_id = d.organization_id
        WHERE d.id = ? AND d.organization_id = ?
        LIMIT 1
    ");
    $dStmt->execute([$dealId, $organizationId]);
    $deal = $dStmt->fetch(PDO::FETCH_ASSOC);

    if (!$deal) {
        inbox_json(false, 'Deal not found or access denied.', ['not_linked' => true], 404);
    }

    $dealData = [
        'id'                  => (int)$deal['id'],
        'name'                => $deal['name'],
        'value'               => (float)($deal['value'] ?? 0),
        'formatted_value'     => '$' . number_format((float)($deal['value'] ?? 0)),
        'stage'               => $deal['stage'] ?: 'Lead',
        'probability'         => !empty($deal['probability']) ? $deal['probability'] . '%' : '—',
        'expected_close_date' => !empty($deal['close_date']) ? date('M j, Y', strtotime($deal['close_date'])) : '—',
        'contact'             => $deal['contact_name'] ?: ($deal['contact_person_name'] ?: '—'),
        'contact_id'          => $deal['contact_id'] ? (int)$deal['contact_id'] : null,
        'company'             => $deal['company'] ?: ($deal['resolved_company_name'] ?: '—'),
        'company_id'          => $deal['resolved_company_id'] ? (int)$deal['resolved_company_id'] : null,
        'owner'               => $deal['owner_name'] ?: 'Unassigned',
        'status'              => $deal['status'] ?: 'open',
        'tag'                 => $deal['priority'] ?: ($deal['product'] ?: 'General'),
        'created_at'          => !empty($deal['created_at']) ? date('M j, Y', strtotime($deal['created_at'])) : '—'
    ];

    inbox_json(true, 'Deal details retrieved.', ['deal' => $dealData]);
}

// ============================================================
// 5. GET action=crm_lookups
// ============================================================
if ($action === 'crm_lookups') {
    // Return active contacts, companies, open deals, and users for compose selectors
    $ctStmt = $pdo->prepare("SELECT id, name, email, phone, company_id FROM contacts WHERE organization_id = ? AND is_active = 1 ORDER BY name ASC LIMIT 100");
    $ctStmt->execute([$organizationId]);
    $contacts = $ctStmt->fetchAll(PDO::FETCH_ASSOC);

    $cpStmt = $pdo->prepare("SELECT id, name FROM companies WHERE organization_id = ? ORDER BY name ASC LIMIT 100");
    $cpStmt->execute([$organizationId]);
    $companies = $cpStmt->fetchAll(PDO::FETCH_ASSOC);

    $dStmt = $pdo->prepare("SELECT id, name, value, contact_id FROM deals WHERE organization_id = ? AND status = 'open' ORDER BY value DESC, name ASC LIMIT 100");
    $dStmt->execute([$organizationId]);
    $deals = $dStmt->fetchAll(PDO::FETCH_ASSOC);

    $uStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE organization_id = ? AND status = 'active' ORDER BY name ASC");
    $uStmt->execute([$organizationId]);
    $users = $uStmt->fetchAll(PDO::FETCH_ASSOC);

    inbox_json(true, 'Lookups retrieved.', [
        'contacts'  => $contacts,
        'companies' => $companies,
        'deals'     => $deals,
        'users'     => $users
    ]);
}

// ============================================================
// 5b. GET action=contact_companies (Authoritative Contact -> Company & Deals)
// ============================================================
if ($action === 'contact_companies') {
    $contactId = (int)($_GET['contact_id'] ?? $input['contact_id'] ?? 0);
    if ($contactId <= 0) {
        inbox_json(false, 'Contact ID is required.', [], 422);
    }

    // Verify contact belongs to current organization
    $cChk = $pdo->prepare("SELECT id, name, email, phone, company_id FROM contacts WHERE id = ? AND organization_id = ?");
    $cChk->execute([$contactId, $organizationId]);
    $contact = $cChk->fetch(PDO::FETCH_ASSOC);
    if (!$contact) {
        inbox_json(false, 'Contact not found or access denied.', [], 404);
    }

    // Authoritative query: contact_companies joined with companies UNION contacts.company_id
    $stmt = $pdo->prepare("
        SELECT DISTINCT cp.id, cp.name
        FROM companies cp
        INNER JOIN contact_companies cc ON cc.company_id = cp.id AND cc.organization_id = cp.organization_id
        WHERE cc.contact_id = :cid AND cc.organization_id = :org_id
        UNION
        SELECT cp.id, cp.name
        FROM companies cp
        INNER JOIN contacts ct ON ct.company_id = cp.id AND ct.organization_id = cp.organization_id
        WHERE ct.id = :cid2 AND ct.organization_id = :org_id2
        ORDER BY name ASC
    ");
    $stmt->execute([
        ':cid'     => $contactId,
        ':org_id'  => $organizationId,
        ':cid2'    => $contactId,
        ':org_id2' => $organizationId
    ]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Associated open deals for this contact
    $dStmt = $pdo->prepare("
        SELECT id, name, value, stage
        FROM deals
        WHERE contact_id = ? AND organization_id = ? AND status = 'open'
        ORDER BY value DESC, name ASC
    ");
    $dStmt->execute([$contactId, $organizationId]);
    $deals = $dStmt->fetchAll(PDO::FETCH_ASSOC);

    inbox_json(true, 'Contact associations retrieved.', [
        'contact'   => $contact,
        'companies' => $companies,
        'deals'     => $deals
    ]);
}

// ============================================================
// 6. POST action=compose
// ============================================================
if ($action === 'compose') {
    $toStr     = trim((string)($input['to'] ?? ''));
    $subject   = trim((string)($input['subject'] ?? ''));
    $body      = trim((string)($input['body'] ?? $input['content'] ?? ''));
    $channel   = trim((string)($input['channel'] ?? 'Email'));
    $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : null;
    $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;
    $dealId    = !empty($input['deal_id']) ? (int)$input['deal_id'] : null;
    $leadId    = !empty($input['lead_id']) ? (int)$input['lead_id'] : null;
    $draftId   = !empty($input['draft_id']) ? (int)$input['draft_id'] : null;

    if ($body === '') {
        inbox_json(false, 'Message content is required.', [], 422);
    }
    if ($channel === 'Email' && $subject === '') {
        $subject = '(No Subject)';
    }

    // Resolve Contact if toStr is an email or phone and contactId is null
    if (!$contactId && !empty($toStr)) {
        if (filter_var($toStr, FILTER_VALIDATE_EMAIL)) {
            $findCt = $pdo->prepare("SELECT id, company_id FROM contacts WHERE email = ? AND organization_id = ? LIMIT 1");
            $findCt->execute([$toStr, $organizationId]);
            $found = $findCt->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                $contactId = (int)$found['id'];
                if (!$companyId && !empty($found['company_id'])) {
                    $companyId = (int)$found['company_id'];
                }
            }
        }
    }

    // Strict tenant validation: contact, company, deal must belong to organization_id
    if ($contactId !== null) {
        $chk = $pdo->prepare("SELECT id FROM contacts WHERE id = ? AND organization_id = ?");
        $chk->execute([$contactId, $organizationId]);
        if (!$chk->fetchColumn()) {
            inbox_json(false, 'Contact does not belong to your organization.', [], 422);
        }
    }

    if ($companyId !== null) {
        $chk = $pdo->prepare("SELECT id FROM companies WHERE id = ? AND organization_id = ?");
        $chk->execute([$companyId, $organizationId]);
        if (!$chk->fetchColumn()) {
            inbox_json(false, 'Company does not belong to your organization.', [], 422);
        }
    }

    if ($dealId !== null) {
        $chk = $pdo->prepare("SELECT id FROM deals WHERE id = ? AND organization_id = ?");
        $chk->execute([$dealId, $organizationId]);
        if (!$chk->fetchColumn()) {
            inbox_json(false, 'Deal does not belong to your organization.', [], 422);
        }
    }

    // ATOMIC TRANSACTION
    $pdo->beginTransaction();
    try {
        $preview = mb_substr($body, 0, 100);

        // 1. Insert Conversation
        $insConv = $pdo->prepare("
            INSERT INTO inbox_conversations (
                organization_id, contact_id, company_id, lead_id, deal_id, assigned_to,
                channel, subject, preview, status, priority, is_unread, is_starred,
                last_message_at, created_by, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, 'Open', 'Normal', 0, 0,
                NOW(), ?, NOW(), NOW()
            )
        ");
        $insConv->execute([
            $organizationId, $contactId, $companyId, $leadId, $dealId, $currentUserId,
            $channel, $subject, $preview, $currentUserId
        ]);
        $newConvId = (int)$pdo->lastInsertId();

        // Update conversation_code
        $code = "CONV-{$newConvId}";
        $pdo->prepare("UPDATE inbox_conversations SET conversation_code = ? WHERE id = ?")->execute([$code, $newConvId]);

        // 2. Insert First Message
        $insMsg = $pdo->prepare("
            INSERT INTO inbox_messages (
                organization_id, conversation_id, sender_type, sender_id,
                sender_name, sender_email, sender_phone, channel, direction,
                body, sent_at, created_at
            ) VALUES (
                ?, ?, 'user', ?,
                ?, ?, ?, ?, 'outbound',
                ?, NOW(), NOW()
            )
        ");
        $insMsg->execute([
            $organizationId, $newConvId, $currentUserId,
            $currentUser['name'], $currentUser['email'], $currentUser['phone'] ?? null, $channel,
            $body
        ]);
        $newMsgId = (int)$pdo->lastInsertId();

        // 3. If composed from a draft, atomically remove the draft
        if ($draftId !== null && $draftId > 0) {
            $delDraft = $pdo->prepare("DELETE FROM inbox_drafts WHERE id = ? AND organization_id = ? AND user_id = ?");
            $delDraft->execute([$draftId, $organizationId, $currentUserId]);
        }

        $pdo->commit();

        // Activity log
        log_inbox_activity($pdo, $organizationId, $currentUserId, 'Email', "Composed conversation: {$subject}", $body, 'contacts', $contactId);

        inbox_json(true, 'Message composed successfully.', [
            'conversation_id' => $newConvId,
            'message_id'      => $newMsgId,
            'code'            => $code,
            'draft_removed'   => ($draftId !== null && $draftId > 0)
        ], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        inbox_json(false, 'Failed to compose message: ' . $e->getMessage(), [], 500);
    }
}

// ============================================================
// 7. POST action=reply
// ============================================================
if ($action === 'reply') {
    $convId = (int)($input['conversation_id'] ?? $input['id'] ?? 0);
    $body   = trim((string)($input['body'] ?? $input['content'] ?? ''));

    if ($convId <= 0) {
        inbox_json(false, 'Conversation ID is required.', [], 422);
    }
    if ($body === '') {
        inbox_json(false, 'Reply message cannot be empty.', [], 422);
    }

    $cStmt = $pdo->prepare("SELECT id, channel, subject, contact_id FROM inbox_conversations WHERE id = ? AND organization_id = ?");
    $cStmt->execute([$convId, $organizationId]);
    $conv = $cStmt->fetch(PDO::FETCH_ASSOC);

    if (!$conv) {
        inbox_json(false, 'Conversation not found or access denied.', [], 404);
    }

    $pdo->beginTransaction();
    try {
        $preview = mb_substr($body, 0, 100);

        // 1. Insert message
        $insMsg = $pdo->prepare("
            INSERT INTO inbox_messages (
                organization_id, conversation_id, sender_type, sender_id,
                sender_name, sender_email, sender_phone, channel, direction,
                body, sent_at, created_at
            ) VALUES (
                ?, ?, 'user', ?,
                ?, ?, ?, ?, 'outbound',
                ?, NOW(), NOW()
            )
        ");
        $insMsg->execute([
            $organizationId, $convId, $currentUserId,
            $currentUser['name'], $currentUser['email'], $currentUser['phone'] ?? null, $conv['channel'],
            $body
        ]);
        $msgId = (int)$pdo->lastInsertId();

        // 2. Update conversation preview and last_message_at
        $updConv = $pdo->prepare("
            UPDATE inbox_conversations 
            SET preview = ?, last_message_at = NOW(), updated_at = NOW()
            WHERE id = ? AND organization_id = ?
        ");
        $updConv->execute([$preview, $convId, $organizationId]);

        $pdo->commit();

        log_inbox_activity($pdo, $organizationId, $currentUserId, 'Email', "Replied to conversation #{$convId}", $body, 'contacts', $conv['contact_id'] ? (int)$conv['contact_id'] : null);

        inbox_json(true, 'Reply saved successfully.', [
            'conversation_id' => $convId,
            'message_id'      => $msgId
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        inbox_json(false, 'Failed to save reply: ' . $e->getMessage(), [], 500);
    }
}

// ============================================================
// 8. POST action=internal_note
// ============================================================
if ($action === 'internal_note') {
    $convId = (int)($input['conversation_id'] ?? $input['id'] ?? 0);
    $body   = trim((string)($input['body'] ?? $input['content'] ?? ''));

    if ($convId <= 0) {
        inbox_json(false, 'Conversation ID is required.', [], 422);
    }
    if ($body === '') {
        inbox_json(false, 'Note content cannot be empty.', [], 422);
    }

    $cStmt = $pdo->prepare("SELECT id, channel FROM inbox_conversations WHERE id = ? AND organization_id = ?");
    $cStmt->execute([$convId, $organizationId]);
    if (!$cStmt->fetchColumn()) {
        inbox_json(false, 'Conversation not found or access denied.', [], 404);
    }

    $pdo->beginTransaction();
    try {
        $preview = "📌 Internal note: " . mb_substr($body, 0, 80);

        $insMsg = $pdo->prepare("
            INSERT INTO inbox_messages (
                organization_id, conversation_id, sender_type, sender_id,
                sender_name, sender_email, channel, direction,
                body, sent_at, created_at
            ) VALUES (
                ?, ?, 'internal_note', ?,
                ?, ?, 'Internal', 'internal',
                ?, NOW(), NOW()
            )
        ");
        $insMsg->execute([
            $organizationId, $convId, $currentUserId,
            $currentUser['name'], $currentUser['email'],
            $body
        ]);
        $msgId = (int)$pdo->lastInsertId();

        $updConv = $pdo->prepare("
            UPDATE inbox_conversations 
            SET preview = ?, last_message_at = NOW(), updated_at = NOW()
            WHERE id = ? AND organization_id = ?
        ");
        $updConv->execute([$preview, $convId, $organizationId]);

        $pdo->commit();

        inbox_json(true, 'Internal note added.', [
            'conversation_id' => $convId,
            'message_id'      => $msgId
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        inbox_json(false, 'Failed to add internal note: ' . $e->getMessage(), [], 500);
    }
}

// ============================================================
// 9. POST action=toggle_read
// ============================================================
if ($action === 'toggle_read') {
    $convId = (int)($input['id'] ?? $input['conversation_id'] ?? 0);
    if ($convId <= 0) {
        inbox_json(false, 'Conversation ID is required.', [], 422);
    }

    $cStmt = $pdo->prepare("SELECT is_unread FROM inbox_conversations WHERE id = ? AND organization_id = ?");
    $cStmt->execute([$convId, $organizationId]);
    $curr = $cStmt->fetch(PDO::FETCH_ASSOC);
    if (!$curr) {
        inbox_json(false, 'Conversation not found.', [], 404);
    }

    $newVal = isset($input['is_unread']) ? ((int)$input['is_unread'] ? 1 : 0) : ($curr['is_unread'] ? 0 : 1);

    $upd = $pdo->prepare("UPDATE inbox_conversations SET is_unread = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
    $upd->execute([$newVal, $convId, $organizationId]);

    inbox_json(true, $newVal ? 'Marked as unread.' : 'Marked as read.', ['is_unread' => (bool)$newVal]);
}

// ============================================================
// 10. POST action=toggle_star
// ============================================================
if ($action === 'toggle_star') {
    $convId = (int)($input['id'] ?? $input['conversation_id'] ?? 0);
    if ($convId <= 0) {
        inbox_json(false, 'Conversation ID is required.', [], 422);
    }

    $cStmt = $pdo->prepare("SELECT is_starred FROM inbox_conversations WHERE id = ? AND organization_id = ?");
    $cStmt->execute([$convId, $organizationId]);
    $curr = $cStmt->fetch(PDO::FETCH_ASSOC);
    if (!$curr) {
        inbox_json(false, 'Conversation not found.', [], 404);
    }

    $newVal = isset($input['is_starred']) ? ((int)$input['is_starred'] ? 1 : 0) : ($curr['is_starred'] ? 0 : 1);

    $upd = $pdo->prepare("UPDATE inbox_conversations SET is_starred = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
    $upd->execute([$newVal, $convId, $organizationId]);

    inbox_json(true, $newVal ? 'Conversation starred.' : 'Star removed.', ['is_starred' => (bool)$newVal]);
}

// ============================================================
// 11. POST action=snooze
// ============================================================
if ($action === 'snooze') {
    $convId = (int)($input['id'] ?? $input['conversation_id'] ?? 0);
    $preset = trim((string)($input['preset'] ?? ''));
    $until  = trim((string)($input['until'] ?? ''));

    if ($convId <= 0) {
        inbox_json(false, 'Conversation ID is required.', [], 422);
    }

    $cStmt = $pdo->prepare("SELECT id FROM inbox_conversations WHERE id = ? AND organization_id = ?");
    $cStmt->execute([$convId, $organizationId]);
    if (!$cStmt->fetchColumn()) {
        inbox_json(false, 'Conversation not found or access denied.', [], 404);
    }

    // Handle presets
    if ($preset === 'later_today') {
        $curHour = (int)date('H');
        if ($curHour < 14) {
            $until = date('Y-m-d 18:00:00');
        } else {
            $until = date('Y-m-d H:i:s', strtotime('+4 hours'));
        }
    } elseif ($preset === 'tomorrow') {
        $until = date('Y-m-d 09:00:00', strtotime('+1 day'));
    } elseif ($preset === 'next_week') {
        $until = date('Y-m-d 09:00:00', strtotime('next monday'));
    } elseif ($until === '') {
        $until = date('Y-m-d 09:00:00', strtotime('+1 day'));
    } else {
        $ts = strtotime($until);
        if ($ts === false || $ts <= time()) {
            inbox_json(false, 'Snooze datetime must be in the future.', [], 422);
        }
        $until = date('Y-m-d H:i:s', $ts);
    }

    $upd = $pdo->prepare("UPDATE inbox_conversations SET snoozed_until = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
    $upd->execute([$until, $convId, $organizationId]);

    inbox_json(true, 'Conversation snoozed.', [
        'snoozed_until' => $until,
        'formatted'     => date('M j, g:i A', strtotime($until))
    ]);
}

// ============================================================
// 11b. POST action=unsnooze
// ============================================================
if ($action === 'unsnooze') {
    $convId = (int)($input['id'] ?? $input['conversation_id'] ?? 0);
    if ($convId <= 0) {
        inbox_json(false, 'Conversation ID is required.', [], 422);
    }

    $cStmt = $pdo->prepare("SELECT id FROM inbox_conversations WHERE id = ? AND organization_id = ?");
    $cStmt->execute([$convId, $organizationId]);
    if (!$cStmt->fetchColumn()) {
        inbox_json(false, 'Conversation not found or access denied.', [], 404);
    }

    $upd = $pdo->prepare("UPDATE inbox_conversations SET snoozed_until = NULL, updated_at = NOW() WHERE id = ? AND organization_id = ?");
    $upd->execute([$convId, $organizationId]);

    inbox_json(true, 'Conversation restored to active.', ['snoozed_until' => null]);
}

// ============================================================
// 11c. POST action=update_relationship
// ============================================================
if ($action === 'update_relationship') {
    $convId    = (int)($input['conversation_id'] ?? $input['id'] ?? 0);
    $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : null;
    $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;
    $dealId    = !empty($input['deal_id']) ? (int)$input['deal_id'] : null;

    if ($convId <= 0) {
        inbox_json(false, 'Conversation ID is required.', [], 422);
    }

    // Verify conversation belongs to org
    $cStmt = $pdo->prepare("SELECT id FROM inbox_conversations WHERE id = ? AND organization_id = ?");
    $cStmt->execute([$convId, $organizationId]);
    if (!$cStmt->fetchColumn()) {
        inbox_json(false, 'Conversation not found or access denied.', [], 404);
    }

    // Validate contact belongs to org
    if ($contactId !== null) {
        $chk = $pdo->prepare("SELECT id FROM contacts WHERE id = ? AND organization_id = ?");
        $chk->execute([$contactId, $organizationId]);
        if (!$chk->fetchColumn()) {
            inbox_json(false, 'Contact does not belong to your organization.', [], 422);
        }
    }

    // Validate company belongs to org
    if ($companyId !== null) {
        $chk = $pdo->prepare("SELECT id FROM companies WHERE id = ? AND organization_id = ?");
        $chk->execute([$companyId, $organizationId]);
        if (!$chk->fetchColumn()) {
            inbox_json(false, 'Company does not belong to your organization.', [], 422);
        }
    }

    // Validate deal belongs to org
    if ($dealId !== null) {
        $chk = $pdo->prepare("SELECT id FROM deals WHERE id = ? AND organization_id = ?");
        $chk->execute([$dealId, $organizationId]);
        if (!$chk->fetchColumn()) {
            inbox_json(false, 'Deal does not belong to your organization.', [], 422);
        }
    }

    $upd = $pdo->prepare("
        UPDATE inbox_conversations 
        SET contact_id = ?, company_id = ?, deal_id = ?, updated_at = NOW()
        WHERE id = ? AND organization_id = ?
    ");
    $upd->execute([$contactId, $companyId, $dealId, $convId, $organizationId]);

    // Fetch updated names
    $resStmt = $pdo->prepare("
        SELECT 
            c.id, c.contact_id, c.company_id, c.deal_id,
            ct.name AS contact_name,
            cp.name AS company_name,
            d.name AS deal_name,
            d.value AS deal_value
        FROM inbox_conversations c
        LEFT JOIN contacts ct ON ct.id = c.contact_id AND ct.organization_id = c.organization_id
        LEFT JOIN companies cp ON cp.id = c.company_id AND cp.organization_id = c.organization_id
        LEFT JOIN deals d ON d.id = c.deal_id AND d.organization_id = c.organization_id
        WHERE c.id = ? AND c.organization_id = ?
    ");
    $resStmt->execute([$convId, $organizationId]);
    $updatedRow = $resStmt->fetch(PDO::FETCH_ASSOC);

    inbox_json(true, 'CRM relationships updated successfully.', [
        'conversation_id' => $convId,
        'contact_id'      => $contactId,
        'company_id'      => $companyId,
        'deal_id'         => $dealId,
        'contact_name'    => $updatedRow['contact_name'] ?? null,
        'company_name'    => $updatedRow['company_name'] ?? null,
        'deal_name'       => $updatedRow['deal_name'] ?? null
    ]);
}

// ============================================================
// 11d. POST action=save_draft (Scoped by organization_id AND user_id)
// ============================================================
if ($action === 'save_draft') {
    $draftId   = !empty($input['draft_id']) ? (int)$input['draft_id'] : null;
    $channel   = trim((string)($input['channel'] ?? 'Email'));
    $recipient = trim((string)($input['recipient'] ?? $input['to'] ?? ''));
    $subject   = trim((string)($input['subject'] ?? ''));
    $body      = trim((string)($input['body'] ?? $input['content'] ?? ''));
    $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : null;
    $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;
    $dealId    = !empty($input['deal_id']) ? (int)$input['deal_id'] : null;

    // Strict tenant validation
    if ($contactId !== null) {
        $chk = $pdo->prepare("SELECT id FROM contacts WHERE id = ? AND organization_id = ?");
        $chk->execute([$contactId, $organizationId]);
        if (!$chk->fetchColumn()) {
            inbox_json(false, 'Contact does not belong to your organization.', [], 422);
        }
    }

    if ($companyId !== null) {
        $chk = $pdo->prepare("SELECT id FROM companies WHERE id = ? AND organization_id = ?");
        $chk->execute([$companyId, $organizationId]);
        if (!$chk->fetchColumn()) {
            inbox_json(false, 'Company does not belong to your organization.', [], 422);
        }
    }

    if ($dealId !== null) {
        $chk = $pdo->prepare("SELECT id FROM deals WHERE id = ? AND organization_id = ?");
        $chk->execute([$dealId, $organizationId]);
        if (!$chk->fetchColumn()) {
            inbox_json(false, 'Deal does not belong to your organization.', [], 422);
        }
    }

    if ($draftId !== null && $draftId > 0) {
        // UPDATE existing draft: must belong to both organization_id and current user
        $dChk = $pdo->prepare("SELECT id FROM inbox_drafts WHERE id = ? AND organization_id = ? AND user_id = ?");
        $dChk->execute([$draftId, $organizationId, $currentUserId]);
        if (!$dChk->fetchColumn()) {
            inbox_json(false, 'Draft not found or access denied.', [], 404);
        }

        $upd = $pdo->prepare("
            UPDATE inbox_drafts 
            SET channel = ?, recipient = ?, contact_id = ?, company_id = ?, deal_id = ?, subject = ?, body = ?, updated_at = NOW()
            WHERE id = ? AND organization_id = ? AND user_id = ?
        ");
        $upd->execute([
            $channel, $recipient, $contactId, $companyId, $dealId, $subject, $body,
            $draftId, $organizationId, $currentUserId
        ]);

        inbox_json(true, 'Draft updated successfully.', ['draft_id' => $draftId]);
    } else {
        // INSERT new draft
        $ins = $pdo->prepare("
            INSERT INTO inbox_drafts 
            (organization_id, user_id, channel, recipient, contact_id, company_id, deal_id, subject, body, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $ins->execute([
            $organizationId, $currentUserId, $channel, $recipient, $contactId, $companyId, $dealId, $subject, $body
        ]);
        $newDraftId = (int)$pdo->lastInsertId();

        inbox_json(true, 'Draft saved successfully.', ['draft_id' => $newDraftId], 201);
    }
}

// ============================================================
// 11e. GET action=load_draft (Scoped by organization_id AND user_id)
// ============================================================
if ($action === 'load_draft') {
    $draftId = (int)($_GET['id'] ?? $input['id'] ?? 0);
    if ($draftId <= 0) {
        inbox_json(false, 'Draft ID is required.', [], 422);
    }

    $dStmt = $pdo->prepare("
        SELECT 
            dr.*,
            ct.name AS contact_name,
            cp.name AS company_name,
            d.name AS deal_name
        FROM inbox_drafts dr
        LEFT JOIN contacts ct ON ct.id = dr.contact_id AND ct.organization_id = dr.organization_id
        LEFT JOIN companies cp ON cp.id = dr.company_id AND cp.organization_id = dr.organization_id
        LEFT JOIN deals d ON d.id = dr.deal_id AND d.organization_id = dr.organization_id
        WHERE dr.id = ? AND dr.organization_id = ? AND dr.user_id = ?
        LIMIT 1
    ");
    $dStmt->execute([$draftId, $organizationId, $currentUserId]);
    $draft = $dStmt->fetch(PDO::FETCH_ASSOC);

    if (!$draft) {
        inbox_json(false, 'Draft not found or access denied.', [], 404);
    }

    inbox_json(true, 'Draft loaded.', ['draft' => $draft]);
}

// ============================================================
// 11f. POST action=delete_draft (Scoped by organization_id AND user_id)
// ============================================================
if ($action === 'delete_draft') {
    $draftId = (int)($input['id'] ?? $input['draft_id'] ?? 0);
    if ($draftId <= 0) {
        inbox_json(false, 'Draft ID is required.', [], 422);
    }

    $del = $pdo->prepare("DELETE FROM inbox_drafts WHERE id = ? AND organization_id = ? AND user_id = ?");
    $del->execute([$draftId, $organizationId, $currentUserId]);

    inbox_json(true, 'Draft deleted.');
}

// ============================================================
// 12. POST action=archive
// ============================================================
if ($action === 'archive') {
    $convId = (int)($input['id'] ?? $input['conversation_id'] ?? 0);
    if ($convId <= 0) {
        inbox_json(false, 'Conversation ID is required.', [], 422);
    }

    $upd = $pdo->prepare("UPDATE inbox_conversations SET status = 'Archived', updated_at = NOW() WHERE id = ? AND organization_id = ?");
    $upd->execute([$convId, $organizationId]);

    inbox_json(true, 'Conversation archived.', ['status' => 'Archived']);
}

// ============================================================
// 13. POST action=unarchive
// ============================================================
if ($action === 'unarchive') {
    $convId = (int)($input['id'] ?? $input['conversation_id'] ?? 0);
    if ($convId <= 0) {
        inbox_json(false, 'Conversation ID is required.', [], 422);
    }

    $upd = $pdo->prepare("UPDATE inbox_conversations SET status = 'Open', updated_at = NOW() WHERE id = ? AND organization_id = ?");
    $upd->execute([$convId, $organizationId]);

    inbox_json(true, 'Conversation restored from archive.', ['status' => 'Open']);
}

// ============================================================
// 14. POST action=update_status
// ============================================================
if ($action === 'update_status') {
    $convId = (int)($input['id'] ?? $input['conversation_id'] ?? 0);
    $status = trim((string)($input['status'] ?? 'Open'));

    $valid = ['Open', 'Pending', 'Waiting for Customer', 'Resolved', 'Archived'];
    if (!in_array($status, $valid, true)) {
        inbox_json(false, 'Invalid conversation status.', [], 422);
    }

    $upd = $pdo->prepare("UPDATE inbox_conversations SET status = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
    $upd->execute([$status, $convId, $organizationId]);

    inbox_json(true, "Status updated to '{$status}'.", ['status' => $status]);
}

// ============================================================
// 15. POST action=assign
// ============================================================
if ($action === 'assign') {
    $convId = (int)($input['id'] ?? $input['conversation_id'] ?? 0);
    $userId = !empty($input['user_id']) ? (int)$input['user_id'] : null;
    $teamId = !empty($input['team_id']) ? (int)$input['team_id'] : null;

    if ($convId <= 0) {
        inbox_json(false, 'Conversation ID is required.', [], 422);
    }

    if ($userId !== null) {
        $uChk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active'");
        $uChk->execute([$userId, $organizationId]);
        if (!$uChk->fetchColumn()) {
            inbox_json(false, 'Assigned user does not belong to your organization.', [], 422);
        }
    }

    if ($teamId !== null) {
        $tChk = $pdo->prepare("SELECT id FROM teams WHERE id = ? AND organization_id = ?");
        $tChk->execute([$teamId, $organizationId]);
        if (!$tChk->fetchColumn()) {
            inbox_json(false, 'Assigned team does not belong to your organization.', [], 422);
        }
    }

    $upd = $pdo->prepare("UPDATE inbox_conversations SET assigned_to = ?, team_id = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
    $upd->execute([$userId, $teamId, $convId, $organizationId]);

    inbox_json(true, 'Conversation assigned successfully.', [
        'assigned_to' => $userId,
        'team_id'     => $teamId
    ]);
}

// Unknown action
inbox_json(false, "Unknown action: '{$action}'", [], 400);
