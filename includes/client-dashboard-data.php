<?php
/**
 * NexFlow CRM — Client Portal Dashboard Data Helper
 * Multi-tenant, multi-company contact isolated query engine.
 * Strictly adheres to client-visibility security rules and safe fallbacks.
 */

declare(strict_types=1);

/**
 * Get deterministic avatar initials from a full name.
 */
function client_dash_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, min(2, strlen($name))));
}

/**
 * Fetch complete dashboard dataset for the authenticated client context.
 *
 * @param PDO    $pdo
 * @param int    $orgId        Authenticated organization ID
 * @param int    $companyId    Authenticated company ID
 * @param int    $contactId    Authenticated contact ID
 * @param string $companyName  Authenticated company name
 * @return array
 */
function client_get_dashboard_data(PDO $pdo, int $orgId, int $companyId, int $contactId, string $companyName): array
{
    $data = [
        'counts' => [
            'active_deals'      => 0,
            'pending_tasks'     => 0,
            'upcoming_meetings' => 0,
            'shared_documents'  => 0,
        ],
        'primary_deal'      => null,
        'pipeline_stages'   => [],
        'latest_update'     => 'No recent updates available.',
        'account_team'      => null,
        'pending_tasks'     => [],
        'upcoming_meetings' => [],
        'shared_documents'  => [],
        'recent_messages'   => [],
    ];

    try {
        // =====================================================================
        // 1. KPI COUNTS (Exact Multi-Company Contact Isolation)
        // =====================================================================

        // 1.1 Active & Renewed Deals
        $stmtDealsCount = $pdo->prepare("
            SELECT COUNT(*) 
            FROM deals 
            WHERE organization_id = ? 
              AND status IN ('open', 'active', 'renewed') 
              AND status != 'lost'
              AND (
                  company = ? 
                  OR ((company IS NULL OR company = '') AND contact_id = ?)
              )
        ");
        $stmtDealsCount->execute([$orgId, $companyName, $contactId]);
        $data['counts']['active_deals'] = (int)$stmtDealsCount->fetchColumn();

        // 1.2 Pending Action Tasks (Client visible and not completed/cancelled)
        $stmtTasksCount = $pdo->prepare("
            SELECT COUNT(*) 
            FROM tasks 
            WHERE organization_id = ? 
              AND client_visible = 1 
              AND status NOT IN ('completed', 'cancelled')
              AND (
                  company_id = ? 
                  OR (company_id IS NULL AND contact_id = ?)
              )
        ");
        $stmtTasksCount->execute([$orgId, $companyId, $contactId]);
        $data['counts']['pending_tasks'] = (int)$stmtTasksCount->fetchColumn();

        // 1.3 Upcoming Meetings (Future start_time and active status)
        $stmtMeetingsCount = $pdo->prepare("
            SELECT COUNT(*) 
            FROM calendar_events 
            WHERE organization_id = ? 
              AND start_time >= NOW() 
              AND LOWER(status) NOT IN ('cancelled', 'completed')
              AND (
                  company_id = ? 
                  OR (company_id IS NULL AND contact_id = ?)
              )
        ");
        $stmtMeetingsCount->execute([$orgId, $companyId, $contactId]);
        $data['counts']['upcoming_meetings'] = (int)$stmtMeetingsCount->fetchColumn();

        // 1.4 Shared Documents (Explicitly shared, non-archived, available)
        $stmtDocsCount = $pdo->prepare("
            SELECT COUNT(*) 
            FROM documents 
            WHERE organization_id = ? 
              AND is_shared = 1 
              AND is_archived = 0 
              AND status = 'Available'
              AND (
                  company_id = ? 
                  OR (company_id IS NULL AND contact_id = ?)
              )
        ");
        $stmtDocsCount->execute([$orgId, $companyId, $contactId]);
        $data['counts']['shared_documents'] = (int)$stmtDocsCount->fetchColumn();


        // =====================================================================
        // 2. PIPELINE STAGES (For progression step tracking)
        // =====================================================================
        $stmtStages = $pdo->prepare("
            SELECT id, name, sort_order, probability, color
            FROM pipeline_stages 
            WHERE organization_id = ? AND is_active = 1
            ORDER BY sort_order ASC, id ASC
        ");
        $stmtStages->execute([$orgId]);
        $allStages = $stmtStages->fetchAll(PDO::FETCH_ASSOC);
        $data['pipeline_stages'] = $allStages;


        // =====================================================================
        // 3. PRIMARY DEAL (For \"Key Contract Progress\" card)
        // =====================================================================
        $stmtPrimaryDeal = $pdo->prepare("
            SELECT d.id, d.deal_code, d.name, d.company, d.stage, d.value, d.probability,
                   d.status, d.close_date, d.description, d.assigned_to,
                   u.name AS owner_name, u.email AS owner_email, u.job_title AS owner_job_title
            FROM deals d
            LEFT JOIN users u ON u.id = d.assigned_to AND u.organization_id = d.organization_id
            WHERE d.organization_id = ?
              AND d.status IN ('open', 'active', 'renewed')
              AND d.status != 'lost'
              AND (
                  d.company = ? 
                  OR ((d.company IS NULL OR d.company = '') AND d.contact_id = ?)
              )
            ORDER BY d.id DESC
            LIMIT 1
        ");
        $stmtPrimaryDeal->execute([$orgId, $companyName, $contactId]);
        $primaryDeal = $stmtPrimaryDeal->fetch(PDO::FETCH_ASSOC);

        if ($primaryDeal) {
            $dealStage = trim((string)$primaryDeal['stage']);
            $currentSortOrder = 1;
            $currentProbability = (float)($primaryDeal['probability'] ?? 50.0);

            // Match stage sort order and probability
            foreach ($allStages as $stg) {
                if (strcasecmp($stg['name'], $dealStage) === 0) {
                    $currentSortOrder = (int)$stg['sort_order'];
                    if ($primaryDeal['probability'] === null) {
                        $currentProbability = (float)$stg['probability'];
                    }
                    break;
                }
            }

            // Prepare 4 progression milestones based on active stages
            $displayStages = array_values(array_filter($allStages, function($s) {
                return strcasecmp($s['name'], 'Closed Lost') !== 0;
            }));

            $stepCount = count($displayStages);
            if ($stepCount >= 4) {
                $selectedStages = [
                    $displayStages[0],
                    $displayStages[min(1, $stepCount - 1)],
                    $displayStages[min(2, $stepCount - 1)],
                    $displayStages[$stepCount - 1]
                ];
            } else {
                $selectedStages = $displayStages;
            }

            $trackSteps = [];
            foreach ($selectedStages as $s) {
                $stepOrder = (int)$s['sort_order'];
                $statusClass = '';
                if ($stepOrder < $currentSortOrder) {
                    $statusClass = 'done';
                } elseif ($stepOrder === $currentSortOrder) {
                    $statusClass = 'active';
                }
                $trackSteps[] = [
                    'name'   => $s['name'],
                    'order'  => $stepOrder,
                    'status' => $statusClass,
                ];
            }

            $primaryDeal['probability_val'] = round($currentProbability);
            $primaryDeal['track_steps'] = $trackSteps;
            $data['primary_deal'] = $primaryDeal;
        }


        // =====================================================================
        // 4. LATEST UPDATE (Deterministic Safe Priority)
        // =====================================================================
        $primaryDealId = $primaryDeal ? (int)$primaryDeal['id'] : 0;
        $latestUpdateText = null;

        // Priority 1: Verified Client-Visible Proposal (Non-draft only)
        $stmtPropUpdate = $pdo->prepare("
            SELECT proposal_number, title, scope, status, updated_at
            FROM proposals
            WHERE organization_id = ?
              AND (
                  company_id = ?
                  OR (company_id IS NULL AND contact_id = ?)
              )
              AND (? = 0 OR deal_id = ? OR company_id = ?)
              AND status IN ('Sent', 'Viewed', 'Changes Requested', 'Accepted')
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
        ");
        $stmtPropUpdate->execute([
            $orgId,
            $companyId,
            $contactId,
            $primaryDealId,
            $primaryDealId,
            $companyId,
        ]);
        $propUpdate = $stmtPropUpdate->fetch(PDO::FETCH_ASSOC);

        if ($propUpdate) {
            $scopeSnippet = !empty($propUpdate['scope']) ? ' — ' . mb_substr(strip_tags($propUpdate['scope']), 0, 110) . '...' : '';
            $latestUpdateText = htmlspecialchars("{$propUpdate['title']} ({$propUpdate['proposal_number']}) [Status: {$propUpdate['status']}]{$scopeSnippet}");
        }

        // Priority 2: Verified Client Communication Message (inbox inbound/outbound)
        if (!$latestUpdateText) {
            $stmtMsgUpdate = $pdo->prepare("
                SELECT m.sender_name, m.body, m.sent_at
                FROM inbox_messages m
                JOIN inbox_conversations c ON m.conversation_id = c.id
                WHERE c.organization_id = ?
                  AND (
                      c.company_id = ?
                      OR (c.company_id IS NULL AND c.contact_id = ?)
                  )
                  AND (? = 0 OR c.deal_id = ? OR c.company_id = ?)
                  AND m.direction IN ('inbound', 'outbound')
                ORDER BY m.sent_at DESC, m.id DESC
                LIMIT 1
            ");
            $stmtMsgUpdate->execute([
                $orgId,
                $companyId,
                $contactId,
                $primaryDealId,
                $primaryDealId,
                $companyId,
            ]);
            $msgUpdate = $stmtMsgUpdate->fetch(PDO::FETCH_ASSOC);

            if ($msgUpdate && !empty($msgUpdate['body'])) {
                $bodySnippet = mb_substr(strip_tags($msgUpdate['body']), 0, 140);
                $latestUpdateText = "Latest message from " . htmlspecialchars($msgUpdate['sender_name']) . ": \"" . htmlspecialchars($bodySnippet) . "\"";
            }
        }

        // Priority 3: Safe Deal Description (if present in live deals table)
        if (!$latestUpdateText && $primaryDeal && !empty(trim((string)$primaryDeal['description']))) {
            $descSnippet = mb_substr(strip_tags($primaryDeal['description']), 0, 140);
            $latestUpdateText = htmlspecialchars($descSnippet);
        }

        // Priority 4: Existing empty state fallback
        $data['latest_update'] = $latestUpdateText ?: 'No recent updates available.';


        // =====================================================================
        // 5. DEDICATED ACCOUNT TEAM (companies.owner_id -> users)
        // =====================================================================
        $stmtOwner = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.phone, u.job_title, u.photo_path
            FROM companies c
            LEFT JOIN users u ON u.id = c.owner_id AND u.organization_id = c.organization_id
            WHERE c.id = ? AND c.organization_id = ?
            LIMIT 1
        ");
        $stmtOwner->execute([$companyId, $orgId]);
        $owner = $stmtOwner->fetch(PDO::FETCH_ASSOC);

        if ($owner && !empty($owner['name'])) {
            $data['account_team'] = [
                'name'     => $owner['name'],
                'role'     => $owner['job_title'] ?: 'Account Executive',
                'email'    => $owner['email'] ?: 'team@nexflow.io',
                'phone'    => $owner['phone'] ?: '',
                'initials' => client_dash_initials($owner['name']),
                'avatar'   => $owner['photo_path'] ?: '',
                'quote'    => "I'm here to support your team's expansion and ensure all project, contract, and SLA milestones stay on schedule.",
            ];
        } else {
            $data['account_team'] = [
                'name'     => 'Account Executive',
                'role'     => 'Dedicated Support',
                'email'    => 'support@nexflow.io',
                'phone'    => '',
                'initials' => 'AE',
                'avatar'   => '',
                'quote'    => "Our account management team is here to support your workspace goals.",
            ];
        }


        // =====================================================================
        // 6. PENDING TASKS FOR YOU (Read-only, client_visible = 1)
        // =====================================================================
        $stmtTasks = $pdo->prepare("
            SELECT t.id, t.title, t.priority, t.due_date, t.status, d.name AS deal_name
            FROM tasks t
            LEFT JOIN deals d ON d.id = t.deal_id AND d.organization_id = t.organization_id
            WHERE t.organization_id = ?
              AND t.client_visible = 1
              AND t.status NOT IN ('completed', 'cancelled')
              AND (
                  t.company_id = ?
                  OR (t.company_id IS NULL AND t.contact_id = ?)
              )
            ORDER BY t.due_date ASC, t.id DESC
            LIMIT 5
        ");
        $stmtTasks->execute([$orgId, $companyId, $contactId]);
        $rawTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

        $tasksList = [];
        foreach ($rawTasks as $t) {
            $dueDateText = !empty($t['due_date']) ? date('M j, Y', strtotime($t['due_date'])) : 'No due date';
            $prio = ucfirst(strtolower((string)($t['priority'] ?: 'Medium')));
            $badgeClass = 'gray';
            if ($prio === 'Urgent') {
                $badgeClass = 'red';
            } elseif ($prio === 'High') {
                $badgeClass = 'amber';
            }

            $tasksList[] = [
                'id'             => (int)$t['id'],
                'title'          => $t['title'],
                'dueDate'        => $dueDateText,
                'priority'       => $prio,
                'priority_class' => $badgeClass,
                'deal'           => $t['deal_name'] ?: '',
                'completed'      => false,
            ];
        }
        $data['pending_tasks'] = $tasksList;


        // =====================================================================
        // 7. UPCOMING MEETINGS (Future start_time and active)
        // =====================================================================
        $stmtMeetings = $pdo->prepare("
            SELECT id, title, event_type, start_time, end_time, status, location
            FROM calendar_events
            WHERE organization_id = ?
              AND start_time >= NOW()
              AND LOWER(status) NOT IN ('cancelled', 'completed')
              AND (
                  company_id = ?
                  OR (company_id IS NULL AND contact_id = ?)
              )
            ORDER BY start_time ASC
            LIMIT 4
        ");
        $stmtMeetings->execute([$orgId, $companyId, $contactId]);
        $rawMeetings = $stmtMeetings->fetchAll(PDO::FETCH_ASSOC);

        $meetingsList = [];
        foreach ($rawMeetings as $m) {
            $startTs = strtotime($m['start_time']);
            $endTs = !empty($m['end_time']) ? strtotime($m['end_time']) : ($startTs + 3600);
            $meetingsList[] = [
                'id'       => (int)$m['id'],
                'title'    => $m['title'],
                'date'     => date('M j, Y', $startTs),
                'time'     => date('h:i A', $startTs) . ' - ' . date('h:i A', $endTs),
                'location' => $m['location'] ?: 'Video Meeting',
                'status'   => ucfirst((string)$m['status']),
            ];
        }
        $data['upcoming_meetings'] = $meetingsList;


        // =====================================================================
        // 8. RECENTLY SHARED DOCUMENTS (is_shared = 1, active)
        // =====================================================================
        $stmtDocs = $pdo->prepare("
            SELECT d.id, d.title, d.original_filename, d.document_type, d.file_size,
                   u.name AS uploaded_by_name
            FROM documents d
            LEFT JOIN users u ON u.id = d.uploaded_by AND u.organization_id = d.organization_id
            WHERE d.organization_id = ?
              AND d.is_shared = 1
              AND d.is_archived = 0
              AND d.status = 'Available'
              AND (
                  d.company_id = ?
                  OR (d.company_id IS NULL AND d.contact_id = ?)
              )
            ORDER BY d.id DESC
            LIMIT 4
        ");
        $stmtDocs->execute([$orgId, $companyId, $contactId]);
        $rawDocs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

        $docsList = [];
        foreach ($rawDocs as $doc) {
            $docsList[] = [
                'id'         => (int)$doc['id'],
                'name'       => $doc['title'] ?: ($doc['original_filename'] ?: 'Document'),
                'type'       => $doc['document_type'] ?: 'File',
                'uploadedBy' => $doc['uploaded_by_name'] ?: 'Team Member',
                'size'       => $doc['file_size'] ?: '',
            ];
        }
        $data['shared_documents'] = $docsList;


        // =====================================================================
        // 9. RECENT MESSAGES (Verified client communications)
        // =====================================================================
        $stmtMsg = $pdo->prepare("
            SELECT m.id, m.body, m.sent_at, m.sender_type, m.sender_name,
                   c.subject, c.id AS conv_id
            FROM inbox_messages m
            JOIN inbox_conversations c ON m.conversation_id = c.id
            WHERE c.organization_id = ?
              AND (
                  c.company_id = ?
                  OR (c.company_id IS NULL AND c.contact_id = ?)
              )
              AND m.direction IN ('inbound', 'outbound')
            ORDER BY m.sent_at DESC, m.id DESC
            LIMIT 3
        ");
        $stmtMsg->execute([$orgId, $companyId, $contactId]);
        $rawMsgs = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

        $msgsList = [];
        foreach ($rawMsgs as $msg) {
            $sentTs = strtotime($msg['sent_at']);
            $timeText = (date('Y-m-d') === date('Y-m-d', $sentTs)) 
                ? ('Today, ' . date('h:i A', $sentTs)) 
                : date('M j, h:i A', $sentTs);

            $msgsList[] = [
                'id'       => (int)$msg['id'],
                'conv_id'  => (int)$msg['conv_id'],
                'sender'   => $msg['sender_name'] ?: 'Account Team',
                'initials' => client_dash_initials($msg['sender_name'] ?: 'AT'),
                'time'     => $timeText,
                'text'     => $msg['body'],
            ];
        }
        $data['recent_messages'] = $msgsList;

    } catch (Throwable $e) {
        error_log('client_get_dashboard_data error: ' . $e->getMessage());
    }

    return $data;
}
