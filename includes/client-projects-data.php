<?php
/**
 * NexFlow CRM — Client Portal Projects Data Helper
 * Multi-tenant, multi-company contact isolated query engine for projects.
 * Adheres strictly to tenant isolation, manager org scoping, and client-visible boundaries.
 */

declare(strict_types=1);

/**
 * Get organization currency information.
 */
function client_get_projects_currency(PDO $pdo, int $orgId): array
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
 * Format a byte count into human-readable string (KB, MB, GB).
 */
function client_projects_format_bytes(int $bytes): string
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
 * Deterministic avatar initials from user/manager name.
 */
function client_projects_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, min(2, strlen($name))));
}

/**
 * Fetch KPI counts for the authenticated client's authorized projects.
 */
function client_get_projects_kpis(PDO $pdo, int $orgId, string $companyName, int $contactId): array
{
    $kpis = [
        'total'     => 0,
        'active'    => 0,
        'completed' => 0,
        'upcoming'  => 0,
    ];

    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) AS total_projects,
                COUNT(CASE WHEN p.status IN ('In Progress', 'Planning') THEN 1 END) AS active_projects,
                COUNT(CASE WHEN p.status = 'Completed' THEN 1 END) AS completed_projects,
                COUNT(CASE WHEN (p.status != 'Completed' AND p.due_date IS NOT NULL AND p.due_date >= CURDATE()) THEN 1 END) AS upcoming_deadlines
            FROM projects p
            WHERE p.organization_id = ?
              AND p.client_name = ?
              AND (
                  p.deal_id IS NULL
                  OR p.deal_id IN (
                      SELECT d.id FROM deals d
                      WHERE d.organization_id = ?
                        AND (d.company = ? OR ((d.company IS NULL OR d.company = '') AND d.contact_id = ?))
                  )
              )
        ");
        $stmt->execute([$orgId, $companyName, $orgId, $companyName, $contactId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $kpis['total']     = (int)($row['total_projects'] ?? 0);
            $kpis['active']    = (int)($row['active_projects'] ?? 0);
            $kpis['completed'] = (int)($row['completed_projects'] ?? 0);
            $kpis['upcoming']  = (int)($row['upcoming_deadlines'] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('Error fetching client project KPIs: ' . $e->getMessage());
    }

    return $kpis;
}

/**
 * Fetch authorized projects list for the authenticated client context.
 */
function client_get_projects_list(PDO $pdo, int $orgId, string $companyName, int $contactId): array
{
    $projects = [];
    $currency = client_get_projects_currency($pdo, $orgId);
    $currSymbol = $currency['symbol'];

    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.project_code,
                p.name,
                p.client_name,
                p.status,
                p.priority,
                p.start_date,
                p.due_date,
                p.budget,
                p.progress,
                p.description,
                p.deal_id,
                u.name AS manager_name,
                u.photo_path AS manager_avatar
            FROM projects p
            LEFT JOIN users u 
                ON u.id = p.manager_id 
               AND u.organization_id = ?
            WHERE p.organization_id = ?
              AND p.client_name = ?
              AND (
                  p.deal_id IS NULL
                  OR p.deal_id IN (
                      SELECT d.id FROM deals d
                      WHERE d.organization_id = ?
                        AND (d.company = ? OR ((d.company IS NULL OR d.company = '') AND d.contact_id = ?))
                  )
              )
            ORDER BY p.start_date DESC, p.id DESC
        ");
        $stmt->execute([$orgId, $orgId, $companyName, $orgId, $companyName, $contactId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $managerName = !empty($row['manager_name']) ? trim((string)$row['manager_name']) : 'Unassigned';
            $budgetVal = (float)($row['budget'] ?? 0);
            $budgetFormatted = $currSymbol . number_format($budgetVal, 2);

            $startDateFormatted = !empty($row['start_date']) 
                ? date('M j, Y', strtotime((string)$row['start_date'])) 
                : 'Not Set';
            $dueDateFormatted = !empty($row['due_date']) 
                ? date('M j, Y', strtotime((string)$row['due_date'])) 
                : 'Not Set';

            $projects[] = [
                'id'              => (int)$row['id'],
                'projectCode'     => (string)($row['project_code'] ?? 'PRJ-' . str_pad((string)$row['id'], 3, '0', STR_PAD_LEFT)),
                'name'            => (string)($row['name'] ?? ''),
                'client'          => (string)($row['client_name'] ?? $companyName),
                'manager'         => $managerName,
                'managerInitials' => client_projects_initials($managerName),
                'status'          => (string)($row['status'] ?? 'In Progress'),
                'priority'        => (string)($row['priority'] ?? 'Medium'),
                'progress'        => max(0, min(100, (int)($row['progress'] ?? 0))),
                'startDate'       => $startDateFormatted,
                'rawStartDate'    => (string)($row['start_date'] ?? ''),
                'deadline'        => $dueDateFormatted,
                'rawDeadline'     => (string)($row['due_date'] ?? ''),
                'budget'          => $budgetFormatted,
                'budgetValue'     => $budgetVal,
                'description'     => (string)($row['description'] ?? ''),
                'dealId'          => !empty($row['deal_id']) ? (int)$row['deal_id'] : null,
            ];
        }
    } catch (Throwable $e) {
        error_log('Error fetching client project list: ' . $e->getMessage());
    }

    return $projects;
}

/**
 * Fetch verified single project details, milestones, tasks, and shared files.
 * Authorizes that the project strictly belongs to the authenticated organization and company.
 */
function client_get_project_detail(PDO $pdo, int $orgId, string $companyName, int $contactId, int $projectId): ?array
{
    if ($projectId <= 0) {
        return null;
    }

    $currency = client_get_projects_currency($pdo, $orgId);
    $currSymbol = $currency['symbol'];

    try {
        // 1. Authorize and fetch primary project row (Organization-scoped manager JOIN)
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.project_code,
                p.name,
                p.client_name,
                p.status,
                p.priority,
                p.start_date,
                p.due_date,
                p.budget,
                p.progress,
                p.description,
                p.deal_id,
                u.name AS manager_name,
                u.email AS manager_email,
                u.photo_path AS manager_avatar
            FROM projects p
            LEFT JOIN users u 
                ON u.id = p.manager_id 
               AND u.organization_id = ?
            WHERE p.id = ?
              AND p.organization_id = ?
              AND p.client_name = ?
              AND (
                  p.deal_id IS NULL
                  OR p.deal_id IN (
                      SELECT d.id FROM deals d
                      WHERE d.organization_id = ?
                        AND (d.company = ? OR ((d.company IS NULL OR d.company = '') AND d.contact_id = ?))
                  )
              )
            LIMIT 1
        ");
        $stmt->execute([$orgId, $projectId, $orgId, $companyName, $orgId, $companyName, $contactId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $managerName = !empty($row['manager_name']) ? trim((string)$row['manager_name']) : 'Unassigned';
        $budgetVal = (float)($row['budget'] ?? 0);
        $budgetFormatted = $currSymbol . number_format($budgetVal, 2);

        $startDateFormatted = !empty($row['start_date']) 
            ? date('M j, Y', strtotime((string)$row['start_date'])) 
            : 'Not Set';
        $dueDateFormatted = !empty($row['due_date']) 
            ? date('M j, Y', strtotime((string)$row['due_date'])) 
            : 'Not Set';

        $projectDealId = !empty($row['deal_id']) ? (int)$row['deal_id'] : 0;

        // 2. Fetch Milestones
        $stmtMilestones = $pdo->prepare("
            SELECT id, name, status, sort_order
            FROM project_milestones
            WHERE project_id = ? AND organization_id = ?
            ORDER BY sort_order ASC, id ASC
        ");
        $stmtMilestones->execute([$projectId, $orgId]);
        $milestoneRows = $stmtMilestones->fetchAll(PDO::FETCH_ASSOC);

        $milestones = [];
        foreach ($milestoneRows as $m) {
            $milestones[] = [
                'id'     => (int)$m['id'],
                'title'  => (string)($m['name'] ?? ''),
                'status' => (string)($m['status'] ?? 'Pending'),
            ];
        }

        // 3. Fetch Tasks (Strictly scoped: client_visible = 1, status != 'cancelled', related to authorized project or project's deal)
        if ($projectDealId > 0) {
            $stmtTasks = $pdo->prepare("
                SELECT id, title, status, priority, due_date
                FROM tasks
                WHERE organization_id = ?
                  AND client_visible = 1
                  AND status != 'cancelled'
                  AND (
                      (related_type IN ('project', 'projects') AND related_id = ?)
                      OR deal_id = ?
                  )
                ORDER BY due_date ASC, id ASC
            ");
            $stmtTasks->execute([$orgId, $projectId, $projectDealId]);
        } else {
            $stmtTasks = $pdo->prepare("
                SELECT id, title, status, priority, due_date
                FROM tasks
                WHERE organization_id = ?
                  AND client_visible = 1
                  AND status != 'cancelled'
                  AND (related_type IN ('project', 'projects') AND related_id = ?)
                ORDER BY due_date ASC, id ASC
            ");
            $stmtTasks->execute([$orgId, $projectId]);
        }
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

        // 4. Fetch Shared Files (strictly from documents where is_shared = 1, is_archived = 0, status = 'Available')
        $stmtFiles = $pdo->prepare("
            SELECT id, title, original_filename, document_type, file_size, created_at
            FROM documents
            WHERE project_id = ?
              AND organization_id = ?
              AND is_shared = 1
              AND is_archived = 0
              AND status = 'Available'
            ORDER BY id DESC
        ");
        $stmtFiles->execute([$projectId, $orgId]);
        $fileRows = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);

        $files = [];
        foreach ($fileRows as $f) {
            $fileName = !empty($f['original_filename']) ? (string)$f['original_filename'] : (string)$f['title'];
            $fileSize = client_projects_format_bytes((int)($f['file_size'] ?? 0));
            $fileType = strtoupper((string)($f['document_type'] ?? 'PDF'));
            $fileDate = !empty($f['created_at']) ? date('M j, Y', strtotime((string)$f['created_at'])) : '';

            $files[] = [
                'id'   => (int)$f['id'],
                'name' => $fileName,
                'type' => $fileType,
                'size' => $fileSize,
                'date' => $fileDate,
            ];
        }

        // 5. Activity Tab:
        // Enforce Requirement 3: Do NOT use team_activities. Do not expose internal Admin CRM audit activity.
        // Return empty array to trigger the existing-design empty state "No recent project activity recorded."
        $activity = [];

        return [
            'id'              => (int)$row['id'],
            'projectCode'     => (string)($row['project_code'] ?? 'PRJ-' . str_pad((string)$row['id'], 3, '0', STR_PAD_LEFT)),
            'name'            => (string)($row['name'] ?? ''),
            'client'          => (string)($row['client_name'] ?? $companyName),
            'manager'         => $managerName,
            'managerInitials' => client_projects_initials($managerName),
            'managerEmail'    => (string)($row['manager_email'] ?? ''),
            'status'          => (string)($row['status'] ?? 'In Progress'),
            'priority'        => (string)($row['priority'] ?? 'Medium'),
            'progress'        => max(0, min(100, (int)($row['progress'] ?? 0))),
            'startDate'       => $startDateFormatted,
            'rawStartDate'    => (string)($row['start_date'] ?? ''),
            'deadline'        => $dueDateFormatted,
            'rawDeadline'     => (string)($row['due_date'] ?? ''),
            'budget'          => $budgetFormatted,
            'budgetValue'     => $budgetVal,
            'description'     => (string)($row['description'] ?? ''),
            'dealId'          => $projectDealId > 0 ? $projectDealId : null,
            'milestones'      => $milestones,
            'tasks'           => $tasks,
            'files'           => $files,
            'activity'        => $activity,
        ];
    } catch (Throwable $e) {
        error_log('Error fetching client project details: ' . $e->getMessage());
        return null;
    }
}