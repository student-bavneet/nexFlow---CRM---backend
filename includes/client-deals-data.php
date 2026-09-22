<?php
/**
 * NexFlow CRM — Client Portal Deals Data Helper
 * Multi-tenant, multi-company contact isolated query engine for deals.
 * Enforces company-authoritative ownership, organization-scoped owner JOINs, and client-safe data boundaries.
 */

declare(strict_types=1);

/**
 * Get organization currency information.
 */
function client_get_deals_currency(PDO $pdo, int $orgId): array
{
    $stmt = $pdo->prepare("SELECT currency FROM organizations WHERE id = ? LIMIT 1");
    $stmt->execute([$orgId]);
    $curr = $stmt->fetchColumn();
    $currStr = !empty($curr) ? trim((string)$curr) : 'USD ($)';
    $symbol = '$';

    if (preg_match('/\((.*?)\)/', $currStr, $m)) {
        $symbol = $m[1];
    } elseif (in_array(strtoupper($currStr), ['USD', 'CAD', 'AUD', 'NZD', 'SGD', 'HKD'], true)) {
        $symbol = '$';
    } elseif (strtoupper($currStr) === 'EUR') {
        $symbol = '€';
    } elseif (strtoupper($currStr) === 'GBP') {
        $symbol = '£';
    } elseif (strtoupper($currStr) === 'INR') {
        $symbol = '₹';
    } elseif (strtoupper($currStr) === 'JPY') {
        $symbol = '¥';
    }

    return [
        'code'   => $currStr,
        'symbol' => $symbol
    ];
}

/**
 * Format a byte count into human-readable string.
 */
function client_deals_format_bytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 KB';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);
    if ($i === 0) {
        return $bytes . ' B';
    }
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
}

/**
 * Deterministic avatar initials from user name.
 */
function client_deals_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, min(2, strlen($name))));
}

/**
 * Fetch stage probability map for the organization.
 */
function client_get_stage_probability_map(PDO $pdo, int $orgId): array
{
    $map = [];
    try {
        $stmt = $pdo->prepare("SELECT LOWER(name) AS stage_key, probability FROM pipeline_stages WHERE organization_id = ?");
        $stmt->execute([$orgId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[$row['stage_key']] = (float)$row['probability'];
        }
    } catch (Throwable $e) {
        // Fallback to empty map
    }
    return $map;
}

/**
 * Fetch authorized deals list for the authenticated client company.
 *
 * @param PDO    $pdo
 * @param int    $orgId
 * @param string $companyName
 * @return array
 */
function client_get_deals_list(PDO $pdo, int $orgId, string $companyName): array
{
    $deals = [];
    $currency = client_get_deals_currency($pdo, $orgId);
    $currSymbol = $currency['symbol'];
    $stageMap = client_get_stage_probability_map($pdo, $orgId);

    try {
        $stmt = $pdo->prepare("
            SELECT 
                d.id,
                d.organization_id,
                d.deal_code,
                d.name,
                d.contact_id,
                d.contact_name,
                d.company,
                d.stage,
                d.value,
                d.probability,
                d.source,
                d.priority,
                d.product,
                d.description,
                d.status,
                d.assigned_to,
                d.close_date,
                d.created_at,
                d.updated_at,
                u.name AS owner_name,
                u.email AS owner_email,
                u.job_title AS owner_title,
                u.photo_path AS owner_photo
            FROM deals d
            LEFT JOIN users u 
                ON u.id = d.assigned_to 
               AND u.organization_id = ?
            WHERE d.organization_id = ?
              AND d.company = ?
            ORDER BY d.id DESC
        ");
        $stmt->execute([$orgId, $orgId, $companyName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $val = (float)($row['value'] ?? 0);
            $formattedValue = $currSymbol . number_format($val, 2);

            // Effective probability: deal's explicit probability takes precedence, else stage default, else 50%
            $prob = null;
            if ($row['probability'] !== null && $row['probability'] !== '') {
                $prob = (float)$row['probability'];
            } else {
                $stgKey = strtolower(trim((string)$row['stage']));
                $prob = $stageMap[$stgKey] ?? 50.0;
            }

            $closeDateText = !empty($row['close_date']) 
                ? date('M j, Y', strtotime((string)$row['close_date'])) 
                : 'Not Set';
            $rawCloseDate = (string)($row['close_date'] ?? '');

            $ownerName = !empty($row['owner_name']) ? trim((string)$row['owner_name']) : 'Unassigned';
            $ownerInitials = client_deals_initials($ownerName);

            $dealCode = !empty($row['deal_code']) 
                ? (string)$row['deal_code'] 
                : ('DL-' . str_pad((string)$row['id'], 3, '0', STR_PAD_LEFT));

            $deals[] = [
                'id'            => (int)$row['id'],
                'deal_code'     => $dealCode,
                'name'          => (string)$row['name'],
                'company'       => (string)$row['company'],
                'contact_id'    => !empty($row['contact_id']) ? (int)$row['contact_id'] : null,
                'contact_name'  => (string)($row['contact_name'] ?? ''),
                'value'         => $val,
                'formattedValue'=> $formattedValue,
                'stage'         => (string)$row['stage'],
                'status'        => (string)($row['status'] ?? 'open'),
                'probability'   => round($prob, 1),
                'priority'      => (string)($row['priority'] ?? 'Medium'),
                'description'   => (string)($row['description'] ?? ''),
                'closeDate'     => $closeDateText,
                'rawCloseDate'  => $rawCloseDate,
                'owner'         => $ownerName,
                'ownerEmail'    => (string)($row['owner_email'] ?? ''),
                'ownerTitle'    => (string)($row['owner_title'] ?? 'NexFlow Account Lead'),
                'ownerInitials' => $ownerInitials,
            ];
        }
    } catch (Throwable $e) {
        error_log('Error fetching client deals list: ' . $e->getMessage());
    }

    return $deals;
}

/**
 * Fetch verified single deal detail, tasks, and shared documents.
 * Authorizes that the deal strictly belongs to the authenticated organization and company.
 *
 * @param PDO    $pdo
 * @param int    $orgId
 * @param string $companyName
 * @param int    $dealId
 * @return array|null Null if deal not found or access denied
 */
function client_get_deal_detail(PDO $pdo, int $orgId, string $companyName, int $dealId): ?array
{
    if ($dealId <= 0) {
        return null;
    }

    $currency = client_get_deals_currency($pdo, $orgId);
    $currSymbol = $currency['symbol'];
    $stageMap = client_get_stage_probability_map($pdo, $orgId);

    try {
        // 1. Authorize and fetch primary deal record (Organization-scoped owner JOIN)
        $stmt = $pdo->prepare("
            SELECT 
                d.id,
                d.organization_id,
                d.deal_code,
                d.name,
                d.contact_id,
                d.contact_name,
                d.company,
                d.stage,
                d.value,
                d.probability,
                d.source,
                d.priority,
                d.product,
                d.description,
                d.status,
                d.assigned_to,
                d.close_date,
                d.created_at,
                d.updated_at,
                u.name AS owner_name,
                u.email AS owner_email,
                u.job_title AS owner_title,
                u.photo_path AS owner_photo
            FROM deals d
            LEFT JOIN users u 
                ON u.id = d.assigned_to 
               AND u.organization_id = ?
            WHERE d.id = ?
              AND d.organization_id = ?
              AND d.company = ?
            LIMIT 1
        ");
        $stmt->execute([$orgId, $dealId, $orgId, $companyName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $val = (float)($row['value'] ?? 0);
        $formattedValue = $currSymbol . number_format($val, 2);

        $prob = null;
        if ($row['probability'] !== null && $row['probability'] !== '') {
            $prob = (float)$row['probability'];
        } else {
            $stgKey = strtolower(trim((string)$row['stage']));
            $prob = $stageMap[$stgKey] ?? 50.0;
        }

        $closeDateText = !empty($row['close_date']) 
            ? date('M j, Y', strtotime((string)$row['close_date'])) 
            : 'Not Set';
        $rawCloseDate = (string)($row['close_date'] ?? '');

        $ownerName = !empty($row['owner_name']) ? trim((string)$row['owner_name']) : 'Unassigned';
        $ownerInitials = client_deals_initials($ownerName);

        $dealCode = !empty($row['deal_code']) 
            ? (string)$row['deal_code'] 
            : ('DL-' . str_pad((string)$row['id'], 3, '0', STR_PAD_LEFT));

        // 2. Fetch Tasks (Strictly scoped: client_visible = 1, status != 'cancelled')
        $stmtTasks = $pdo->prepare("
            SELECT id, title, status, priority, due_date
            FROM tasks
            WHERE deal_id = ?
              AND organization_id = ?
              AND client_visible = 1
              AND status != 'cancelled'
            ORDER BY due_date ASC, id ASC
        ");
        $stmtTasks->execute([$dealId, $orgId]);
        $taskRows = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

        $tasks = [];
        foreach ($taskRows as $t) {
            $taskStatus = (string)($t['status'] ?? 'pending');
            $isDone = in_array(strtolower($taskStatus), ['completed', 'done'], true);
            $tasks[] = [
                'id'        => (int)$t['id'],
                'title'     => (string)($t['title'] ?? ''),
                'status'    => ucfirst($taskStatus),
                'completed' => $isDone,
                'priority'  => ucfirst((string)($t['priority'] ?? 'medium')),
                'dueDate'   => !empty($t['due_date']) ? date('M j, Y', strtotime((string)$t['due_date'])) : 'No due date',
            ];
        }

        // 3. Fetch Shared Documents (strictly: is_shared = 1, is_archived = 0, status = 'Available')
        $stmtDocs = $pdo->prepare("
            SELECT id, title, original_filename, document_type, file_size, created_at
            FROM documents
            WHERE deal_id = ?
              AND organization_id = ?
              AND is_shared = 1
              AND is_archived = 0
              AND status = 'Available'
            ORDER BY id DESC
        ");
        $stmtDocs->execute([$dealId, $orgId]);
        $docRows = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

        $documents = [];
        foreach ($docRows as $f) {
            $fileName = !empty($f['original_filename']) ? (string)$f['original_filename'] : (string)$f['title'];
            $fileSize = client_deals_format_bytes((int)($f['file_size'] ?? 0));
            $fileType = strtoupper((string)($f['document_type'] ?? 'PDF'));
            $fileDate = !empty($f['created_at']) ? date('M j, Y', strtotime((string)$f['created_at'])) : '';

            $documents[] = [
                'id'   => (int)$f['id'],
                'name' => $fileName,
                'type' => $fileType,
                'size' => $fileSize,
                'date' => $fileDate,
            ];
        }

        // 4. Activity Tab:
        // Mandatory rule: Do NOT use team_activities. Do not expose internal CRM audit logs.
        // Return empty array to trigger clean empty state "No recent activity recorded for this deal."
        $activity = [];

        // 5. Comments / Notes Tab:
        // Do not store in localStorage or create fake comments.
        $comments = [];

        return [
            'id'            => (int)$row['id'],
            'deal_code'     => $dealCode,
            'name'          => (string)$row['name'],
            'company'       => (string)$row['company'],
            'contact_id'    => !empty($row['contact_id']) ? (int)$row['contact_id'] : null,
            'contact_name'  => (string)($row['contact_name'] ?? ''),
            'value'         => $val,
            'formattedValue'=> $formattedValue,
            'stage'         => (string)$row['stage'],
            'status'        => (string)($row['status'] ?? 'open'),
            'probability'   => round($prob, 1),
            'priority'      => (string)($row['priority'] ?? 'Medium'),
            'description'   => (string)($row['description'] ?? ''),
            'closeDate'     => $closeDateText,
            'rawCloseDate'  => $rawCloseDate,
            'owner'         => $ownerName,
            'ownerEmail'    => (string)($row['owner_email'] ?? ''),
            'ownerTitle'    => (string)($row['owner_title'] ?? 'NexFlow Account Lead'),
            'ownerInitials' => $ownerInitials,
            'tasks'         => $tasks,
            'documents'     => $documents,
            'activity'      => $activity,
            'comments'      => $comments,
        ];
    } catch (Throwable $e) {
        error_log('Error fetching client deal detail: ' . $e->getMessage());
        return null;
    }
}