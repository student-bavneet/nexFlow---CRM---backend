<?php
/**
 * NexFlow CRM — Client Portal Tasks Data Loader
 * Server-side loader providing authoritative task data and KPI counts
 * strictly isolated to the authenticated organization and company.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/client-auth.php';
require_once __DIR__ . '/client-helpers.php';

/**
 * Determine task grouping ('Overdue', 'Today', 'Upcoming', 'Completed')
 * using verified status and due date against current date in application timezone.
 */
function client_calculate_task_group(string $status, ?string $dueDate): string
{
    if ($status === 'completed') {
        return 'Completed';
    }

    if (empty($dueDate) || $dueDate === '0000-00-00 00:00:00') {
        return 'Upcoming';
    }

    $dueTs      = strtotime($dueDate);
    $todayStart = strtotime(date('Y-m-d 00:00:00'));
    $todayEnd   = strtotime(date('Y-m-d 23:59:59'));

    if ($dueTs < $todayStart) {
        return 'Overdue';
    }

    if ($dueTs <= $todayEnd) {
        return 'Today';
    }

    return 'Upcoming';
}

/**
 * Format a raw database task row into a client-safe presentation array.
 * Strips all internal admin notes, user IDs, and private details.
 */
function client_format_task_item(array $row): array
{
    $statusMap = [
        'pending'     => 'Pending Action',
        'in_progress' => 'In Progress',
        'waiting'     => 'Waiting',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
    ];
    $displayStatus = $statusMap[$row['status']] ?? ucfirst($row['status']);

    $priorityMap = [
        'urgent' => 'Urgent',
        'high'   => 'High',
        'medium' => 'Medium',
        'low'    => 'Low',
    ];
    $displayPriority = $priorityMap[$row['priority']] ?? ucfirst($row['priority']);

    $group = client_calculate_task_group($row['status'], $row['due_date']);

    $formattedDueDate = '';
    $rawDueDate = '';
    if (!empty($row['due_date']) && $row['due_date'] !== '0000-00-00 00:00:00') {
        $ts = strtotime($row['due_date']);
        $rawDueDate = date('Y-m-d', $ts);
        $isToday    = (date('Y-m-d', $ts) === date('Y-m-d'));
        if ($isToday) {
            $formattedDueDate = 'Today, ' . date('g:i A', $ts);
        } else {
            $formattedDueDate = date('M j, Y', $ts);
        }
    }

    // Determine association (deal, project, or fallback General Task)
    $associationType  = 'general';
    $associationLabel = 'Related';
    $associationName  = 'General Task';

    if (!empty($row['deal_name'])) {
        $associationType  = 'deal';
        $associationLabel = 'Related Deal';
        $associationName  = $row['deal_name'];
    } elseif (!empty($row['project_name'])) {
        $associationType  = 'project';
        $associationLabel = 'Related Project';
        $associationName  = $row['project_name'];
    }

    $idInt = (int)$row['id'];
    $taskCode = sprintf('TASK-%03d', $idInt);

    return [
        'id'                 => $taskCode,
        'raw_id'             => $idInt,
        'title'              => (string)$row['title'],
        'task_type'          => (string)($row['task_type'] ?? 'General Task'),
        'description'        => (string)($row['description'] ?? ''),
        'desc'               => (string)($row['description'] ?? ''),
        'status'             => $displayStatus,
        'status_raw'         => (string)$row['status'],
        'priority'           => $displayPriority,
        'priority_raw'       => (string)$row['priority'],
        'group'              => $group,
        'completed'          => ($row['status'] === 'completed'),
        'done'               => ($row['status'] === 'completed'),
        'dueDate'            => $formattedDueDate,
        'raw_due_date'       => $rawDueDate,
        'due_date_iso'       => $row['due_date'] ?? null,
        'deal'               => $associationName,
        'contract'           => $associationName,
        'association_type'   => $associationType,
        'association_label'  => $associationLabel,
        'association_name'   => $associationName,
        'clientVisible'      => true,
    ];
}

/**
 * Load server-side task records and KPI groupings for authenticated client company.
 * Enforces organization_id + company_id + client_visible = 1, excluding NULL companies.
 */
function client_get_tasks_data(PDO $pdo, int $orgId, int $companyId): array
{
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.organization_id,
            t.company_id,
            t.title,
            t.task_type,
            t.description,
            t.status,
            t.priority,
            t.due_date,
            t.client_visible,
            t.related_type,
            t.related_id,
            COALESCE(d.name, d_rel.name) AS deal_name,
            pr.name AS project_name
        FROM tasks t
        LEFT JOIN deals d ON d.id = t.deal_id
        LEFT JOIN deals d_rel ON (t.related_type = 'deals' AND d_rel.id = t.related_id)
        LEFT JOIN projects pr ON (t.related_type = 'projects' AND pr.id = t.related_id)
        WHERE t.organization_id = :org_id
          AND t.company_id = :company_id
          AND t.company_id IS NOT NULL
          AND t.client_visible = 1
        ORDER BY (t.due_date IS NULL), t.due_date ASC, t.id DESC
    ");

    $stmt->execute([
        ':org_id'     => $orgId,
        ':company_id' => $companyId,
    ]);

    $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tasks = [];
    $kpis = [
        'all'       => 0,
        'overdue'   => 0,
        'today'     => 0,
        'upcoming'  => 0,
        'completed' => 0,
    ];

    foreach ($rawRows as $row) {
        $item = client_format_task_item($row);
        $tasks[] = $item;

        $kpis['all']++;
        if ($item['completed']) {
            $kpis['completed']++;
        } else {
            if ($item['group'] === 'Overdue') {
                $kpis['overdue']++;
            } elseif ($item['group'] === 'Today') {
                $kpis['today']++;
            } else {
                $kpis['upcoming']++;
            }
        }
    }

    return [
        'tasks' => $tasks,
        'kpis'  => $kpis,
    ];
}