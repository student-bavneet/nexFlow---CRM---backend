<?php
/**
 * NexFlow CRM — Client Portal Calendar Data Loader
 * Server-side loader providing authoritative, client-safe meeting events
 * strictly isolated to the authenticated organization and company.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/client-auth.php';
require_once __DIR__ . '/client-helpers.php';

/**
 * Canonical status normalization matching Admin CRM calendar.
 */
function client_cal_canonical_status(?string $status): string
{
    if ($status === null || trim($status) === '') {
        return 'Scheduled';
    }
    $norm = strtolower(trim($status));
    $map = [
        'scheduled'   => 'Scheduled',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
        'rescheduled' => 'Rescheduled',
        'no show'     => 'No Show',
        'no_show'     => 'No Show',
    ];
    return $map[$norm] ?? ucfirst($status);
}

/**
 * Format a raw database calendar_event row into a client-safe presentation array.
 * Strictly excludes admin internal notes, private user IDs, and task deadlines.
 */
function client_format_calendar_item(array $row): array
{
    $startTs = strtotime($row['start_time']);
    $endTs   = !empty($row['end_time']) ? strtotime($row['end_time']) : ($startTs + 3600);
    if ($endTs < $startTs) {
        $endTs = $startTs + 3600;
    }

    $canonicalStatus = client_cal_canonical_status($row['status'] ?? 'Scheduled');
    $isUpcoming = ($startTs >= time() && !in_array(strtolower($canonicalStatus), ['cancelled', 'completed'], true));

    $hostName = !empty($row['host_name']) ? trim((string)$row['host_name']) : 'Account Team';
    $location = !empty($row['location']) ? trim((string)$row['location']) : 'Video Meeting';

    $idInt = (int)$row['id'];
    $eventCode = 'evt-' . $idInt;

    $dateStr   = date('Y-m-d', $startTs);
    $timeStr   = date('g:i A', $startTs) . ' - ' . date('g:i A', $endTs);
    $startTime = date('g:i A', $startTs);
    $endTime   = date('g:i A', $endTs);

    return [
        'id'          => $eventCode,
        'raw_id'      => $idInt,
        'title'       => (string)$row['title'],
        'description' => (string)($row['description'] ?? ''),
        'event_type'  => (string)($row['event_type'] ?? 'Meeting'),
        'type'        => (string)($row['event_type'] ?? 'Meeting'),
        'date'        => $dateStr,
        'time'        => $timeStr,
        'startTime'   => $startTime,
        'endTime'     => $endTime,
        'start_iso'   => $row['start_time'],
        'end_iso'     => $row['end_time'],
        'host'        => $hostName,
        'location'    => $location,
        'status'      => $canonicalStatus,
        'deal_name'   => (string)($row['deal_name'] ?? ''),
        'is_upcoming' => $isUpcoming,
    ];
}

/**
 * Load server-side meeting records and KPI summary for authenticated client company.
 * Enforces:
 *   - c.organization_id = :org_id
 *   - c.company_id = :company_id
 *   - c.company_id IS NOT NULL
 *   - Excludes internal admin event types ('Team Event', 'Task Deadline')
 */
function client_get_calendar_data(PDO $pdo, int $orgId, int $companyId, ?string $startBound = null, ?string $endBound = null): array
{
    $sql = "
        SELECT 
            c.id,
            c.organization_id,
            c.company_id,
            c.contact_id,
            c.deal_id,
            c.title,
            c.description,
            c.event_type,
            c.start_time,
            c.end_time,
            c.status,
            c.location,
            c.created_at,
            c.updated_at,
            u.name AS host_name,
            d.name AS deal_name
        FROM calendar_events c
        LEFT JOIN users u ON u.id = c.user_id
        LEFT JOIN deals d ON d.id = c.deal_id
        WHERE c.organization_id = :org_id
          AND c.company_id = :company_id
          AND c.company_id IS NOT NULL
          AND c.client_visible = 1
          AND LOWER(c.event_type) NOT IN ('team event', 'task deadline')
    ";

    $params = [
        ':org_id'     => $orgId,
        ':company_id' => $companyId,
    ];

    if (!empty($startBound)) {
        $sql .= " AND c.end_time >= :start_bound";
        $params[':start_bound'] = date('Y-m-d 00:00:00', strtotime($startBound));
    }

    if (!empty($endBound)) {
        $sql .= " AND c.start_time <= :end_bound";
        $params[':end_bound'] = date('Y-m-d 23:59:59', strtotime($endBound));
    }

    $sql .= " ORDER BY c.start_time ASC, c.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $meetings = [];
    $kpis = [
        'total'     => 0,
        'upcoming'  => 0,
        'completed' => 0,
        'cancelled' => 0,
    ];

    foreach ($rawRows as $row) {
        $item = client_format_calendar_item($row);
        $meetings[] = $item;

        $kpis['total']++;
        if ($item['is_upcoming']) {
            $kpis['upcoming']++;
        }
        if (strtolower($item['status']) === 'completed') {
            $kpis['completed']++;
        } elseif (strtolower($item['status']) === 'cancelled') {
            $kpis['cancelled']++;
        }
    }

    return [
        'meetings' => $meetings,
        'kpis'     => $kpis,
    ];
}