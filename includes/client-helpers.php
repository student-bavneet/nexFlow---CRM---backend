<?php
/**
 * NexFlow CRM — Client Portal Helpers
 * Formatter, response helper, and live sidebar badge counts calculator.
 */

declare(strict_types=1);

/**
 * Send JSON response and exit.
 */
function client_json_response(bool $success, string $message = '', array $data = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Compute real sidebar counts strictly for the authenticated client company and contact.
 */
function client_get_shell_counts(PDO $pdo, int $orgId, int $companyId, int $contactId): array
{
    $counts = [
        'projects'  => 0,
        'deals'     => 0,
        'proposals' => 0,
        'estimates' => 0,
        'contracts' => 0,
        'invoices'  => 0,
        'tasks'     => 0,
        'messages'  => 0,
    ];

    try {
        // 1. Deals count (Strict company-authoritative relationship)
        $stmtDeals = $pdo->prepare("
            SELECT COUNT(*) FROM deals 
            WHERE organization_id = ? 
              AND company = (SELECT name FROM companies WHERE id = ? AND organization_id = ?)
        ");
        $stmtDeals->execute([$orgId, $companyId, $orgId]);
        $counts['deals'] = (int)$stmtDeals->fetchColumn();

        // 2. Projects count (via linked deals for this company/contact)
        $stmtProj = $pdo->prepare("
            SELECT COUNT(*) FROM projects 
            WHERE organization_id = ? 
              AND deal_id IN (
                  SELECT id FROM deals 
                  WHERE organization_id = ? 
                    AND (company = (SELECT name FROM companies WHERE id = ?) OR contact_id = ?)
              )
        ");
        $stmtProj->execute([$orgId, $orgId, $companyId, $contactId]);
        $counts['projects'] = (int)$stmtProj->fetchColumn();

        // 3. Proposals count (non-draft)
        $stmtProp = $pdo->prepare("
            SELECT COUNT(*) FROM proposals 
            WHERE organization_id = ? AND company_id = ? AND status != 'Draft'
        ");
        $stmtProp->execute([$orgId, $companyId]);
        $counts['proposals'] = (int)$stmtProp->fetchColumn();

        // 4. Estimates count (estimate_requests)
        $stmtEst = $pdo->prepare("
            SELECT COUNT(*) FROM estimate_requests 
            WHERE organization_id = ? AND company_id = ?
        ");
        $stmtEst->execute([$orgId, $companyId]);
        $counts['estimates'] = (int)$stmtEst->fetchColumn();

        // 5. Contracts count (non-draft)
        $stmtCont = $pdo->prepare("
            SELECT COUNT(*) FROM contracts 
            WHERE organization_id = ? AND company_id = ? AND status != 'Draft'
        ");
        $stmtCont->execute([$orgId, $companyId]);
        $counts['contracts'] = (int)$stmtCont->fetchColumn();

        // 6. Invoices count (non-draft)
        $stmtInv = $pdo->prepare("
            SELECT COUNT(*) FROM invoices 
            WHERE organization_id = ? AND company_id = ? AND status != 'Draft'
        ");
        $stmtInv->execute([$orgId, $companyId]);
        $counts['invoices'] = (int)$stmtInv->fetchColumn();

        // 7. Pending Client Tasks count (client_visible = 1 and not completed)
        $stmtTasks = $pdo->prepare("
            SELECT COUNT(*) FROM tasks 
            WHERE organization_id = ? 
              AND company_id = ?
              AND company_id IS NOT NULL
              AND client_visible = 1 
              AND status != 'completed'
        ");
        $stmtTasks->execute([$orgId, $companyId]);
        $counts['tasks'] = (int)$stmtTasks->fetchColumn();

        // 8. Unread Messages count (strict company isolation)
        $stmtMsgs = $pdo->prepare("
            SELECT COUNT(*) FROM inbox_conversations 
            WHERE organization_id = ? 
              AND company_id = ?
              AND company_id IS NOT NULL
              AND is_unread = 1
        ");
        $stmtMsgs->execute([$orgId, $companyId]);
        $counts['messages'] = (int)$stmtMsgs->fetchColumn();

    } catch (Throwable $e) {
        error_log('client_get_shell_counts error: ' . $e->getMessage());
    }

    return $counts;
}
