<?php
/**
 * NexFlow CRM — Client Portal Profile & Security Data Layer
 * Authoritative MySQL-backed data loader and mutations for Client Portal Profile.
 * Enforces strict multi-tenant organization and company isolation.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Fetch full live profile data for the authenticated client portal user.
 */
function client_get_full_profile(PDO $pdo, int $orgId, int $companyId, int $contactId, int $portalUserId): ?array
{
    if ($orgId <= 0 || $companyId <= 0 || $contactId <= 0 || $portalUserId <= 0) {
        return null;
    }

    // 1. Fetch Contact, Company, and Account Executive details
    $stmt = $pdo->prepare("
        SELECT 
            cpu.id AS portal_user_id,
            cpu.organization_id,
            cpu.company_id,
            cpu.contact_id,
            cpu.email AS portal_email,
            cpu.status AS portal_status,
            c.first_name,
            c.last_name,
            c.name AS contact_name,
            c.email AS contact_email,
            c.phone AS contact_phone,
            c.job_title,
            c.location AS contact_location,
            comp.company_code,
            comp.name AS company_name,
            comp.legal_name,
            comp.domain AS company_domain,
            comp.industry AS company_industry,
            comp.company_size,
            comp.location AS company_location,
            comp.tax_registration_number,
            comp.owner_id AS ae_user_id,
            u.name AS ae_name,
            u.email AS ae_email,
            u.job_title AS ae_title,
            u.phone AS ae_phone,
            u.photo_path AS ae_photo
        FROM client_portal_users cpu
        JOIN contacts c ON cpu.contact_id = c.id
        JOIN contact_companies cc ON (c.id = cc.contact_id AND cpu.company_id = cc.company_id)
        JOIN companies comp ON cc.company_id = comp.id
        LEFT JOIN users u ON comp.owner_id = u.id
        WHERE cpu.id = ? 
          AND cpu.organization_id = ?
          AND cpu.company_id = ?
          AND cpu.contact_id = ?
          AND cpu.status = 'active'
          AND c.is_active = 1
          AND comp.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$portalUserId, $orgId, $companyId, $contactId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    // 2. Fetch Timezone Preference from crm_settings
    $tzStmt = $pdo->prepare("
        SELECT setting_value 
        FROM crm_settings 
        WHERE organization_id = ? AND setting_key = ? 
        LIMIT 1
    ");
    $tzKey = 'client_user_' . $portalUserId . '_timezone';
    $tzStmt->execute([$orgId, $tzKey]);
    $tzRow = $tzStmt->fetch(PDO::FETCH_ASSOC);

    $validIdentifiers = DateTimeZone::listIdentifiers();
    $timezone = 'America/New_York';
    if ($tzRow && !empty($tzRow['setting_value']) && in_array($tzRow['setting_value'], $validIdentifiers, true)) {
        $timezone = $tzRow['setting_value'];
    }

    // 3. Fetch Notification Preferences from crm_settings
    $notifKey = 'client_user_' . $portalUserId . '_notifications';
    $tzStmt->execute([$orgId, $notifKey]);
    $notifRow = $tzStmt->fetch(PDO::FETCH_ASSOC);

    $emailSummaries = true;
    $inPortal = true;
    if ($notifRow && !empty($notifRow['setting_value'])) {
        $parsed = json_decode($notifRow['setting_value'], true);
        if (is_array($parsed)) {
            $emailSummaries = !empty($parsed['email_summaries']);
            $inPortal = !empty($parsed['in_portal']);
        }
    }

    // 4. Compute Initials for Avatar
    $nameParts = preg_split('/\s+/', trim((string)$row['contact_name']));
    $initials = '';
    if (!empty($nameParts[0])) {
        $initials .= mb_strtoupper(mb_substr($nameParts[0], 0, 1));
    }
    if (!empty($nameParts[1])) {
        $initials .= mb_strtoupper(mb_substr($nameParts[1], 0, 1));
    }

    $aeParts = preg_split('/\s+/', trim((string)($row['ae_name'] ?? '')));
    $aeInitials = '';
    if (!empty($aeParts[0])) {
        $aeInitials .= mb_strtoupper(mb_substr($aeParts[0], 0, 1));
    }
    if (!empty($aeParts[1])) {
        $aeInitials .= mb_strtoupper(mb_substr($aeParts[1], 0, 1));
    }

    // Derive company address and location parts
    $compLoc = trim((string)($row['company_location'] ?? ''));
    $locParts = array_map('trim', explode(',', $compLoc));
    $city = $locParts[0] ?? '';
    $country = (count($locParts) > 1) ? end($locParts) : 'United States';

    // Website normalization
    $rawDomain = trim((string)($row['company_domain'] ?? ''));
    $website = '';
    if ($rawDomain !== '') {
        $website = (strpos($rawDomain, 'http://') === 0 || strpos($rawDomain, 'https://') === 0)
            ? $rawDomain
            : 'https://' . $rawDomain;
    }

    return [
        'personal' => [
            'portal_user_id'  => (int)$row['portal_user_id'],
            'contact_id'      => (int)$row['contact_id'],
            'company_id'      => (int)$row['company_id'],
            'name'            => (string)($row['contact_name'] ?? ''),
            'first_name'      => (string)($row['first_name'] ?? ''),
            'last_name'       => (string)($row['last_name'] ?? ''),
            'email'           => (string)($row['contact_email'] ?? $row['portal_email']),
            'phone'           => (string)($row['contact_phone'] ?? ''),
            'job_title'       => (string)($row['job_title'] ?? ''),
            'company_name'    => (string)($row['company_name'] ?? ''),
            'timezone'        => $timezone,
            'avatar_initials' => $initials ?: 'CP',
        ],
        'company' => [
            'id'             => (int)$row['company_id'],
            'company_code'   => (string)($row['company_code'] ?? ''),
            'name'           => (string)($row['company_name'] ?? ''),
            'legal_name'     => (string)($row['legal_name'] ?? ''),
            'domain'         => $rawDomain,
            'website'        => $website,
            'tax_id'         => (string)($row['tax_registration_number'] ?? ''),
            'industry'       => (string)($row['company_industry'] ?? ''),
            'company_size'   => (string)($row['company_size'] ?? ''),
            'location'       => $compLoc,
            'city'           => $city,
            'state'          => (count($locParts) > 2) ? $locParts[1] : '',
            'country'        => $country,
            'zip'            => '',
            'language'       => 'English',
            'address'        => $compLoc,
            'is_read_only'   => true,
        ],
        'notifications' => [
            'email_summaries' => $emailSummaries,
            'in_portal'       => $inPortal,
        ],
        'account_lead' => [
            'has_lead'        => !empty($row['ae_name']),
            'id'              => $row['ae_user_id'] ? (int)$row['ae_user_id'] : null,
            'name'            => (string)($row['ae_name'] ?? ''),
            'title'           => (string)($row['ae_title'] ?? 'Account Executive'),
            'email'           => (string)($row['ae_email'] ?? ''),
            'phone'           => (string)($row['ae_phone'] ?? ''),
            'avatar_initials' => $aeInitials ?: 'AE',
        ],
    ];
}

/**
 * Update personal profile information.
 * Atomically updates contacts, client_portal_users, and crm_settings within a transaction.
 */
function client_update_personal_profile(PDO $pdo, int $orgId, int $companyId, int $contactId, int $portalUserId, array $data): array
{
    // Sanitize and extract inputs
    $fullName = trim((string)($data['name'] ?? ''));
    $email    = strtolower(trim((string)($data['email'] ?? '')));
    $phone    = trim((string)($data['phone'] ?? ''));
    $jobTitle = trim((string)($data['job_title'] ?? ''));
    $timezone = trim((string)($data['timezone'] ?? ''));

    // Validation
    if ($fullName === '') {
        return ['success' => false, 'message' => 'Full Name is required.', 'status' => 422];
    }
    if (mb_strlen($fullName) > 150) {
        return ['success' => false, 'message' => 'Full Name must not exceed 150 characters.', 'status' => 422];
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid work email address format.', 'status' => 422];
    }
    if (mb_strlen($email) > 180) {
        return ['success' => false, 'message' => 'Email must not exceed 180 characters.', 'status' => 422];
    }

    if (mb_strlen($phone) > 50) {
        return ['success' => false, 'message' => 'Phone number must not exceed 50 characters.', 'status' => 422];
    }
    if (mb_strlen($jobTitle) > 100) {
        return ['success' => false, 'message' => 'Job title must not exceed 100 characters.', 'status' => 422];
    }

    // Timezone validation
    $validTzs = DateTimeZone::listIdentifiers();
    if ($timezone !== '' && !in_array($timezone, $validTzs, true)) {
        return ['success' => false, 'message' => 'Invalid timezone selected. Please select a valid IANA timezone identifier.', 'status' => 422];
    }
    if ($timezone === '') {
        $timezone = 'America/New_York';
    }

    // Email uniqueness check in client_portal_users within the same organization
    $checkCpu = $pdo->prepare("
        SELECT id 
        FROM client_portal_users 
        WHERE organization_id = :org_id 
          AND LOWER(email) = :email 
          AND id != :user_id 
        LIMIT 1
    ");
    $checkCpu->execute([
        ':org_id'  => $orgId,
        ':email'   => $email,
        ':user_id' => $portalUserId,
    ]);
    if ($checkCpu->fetch()) {
        return ['success' => false, 'message' => 'The email address is already in use by another client portal account.', 'status' => 422];
    }

    // Email uniqueness check in contacts within the same organization
    $checkContact = $pdo->prepare("
        SELECT id 
        FROM contacts 
        WHERE organization_id = :org_id 
          AND LOWER(email) = :email 
          AND id != :contact_id 
        LIMIT 1
    ");
    $checkContact->execute([
        ':org_id'     => $orgId,
        ':email'      => $email,
        ':contact_id' => $contactId,
    ]);
    if ($checkContact->fetch()) {
        return ['success' => false, 'message' => 'The email address is already associated with another contact record.', 'status' => 422];
    }

    // Parse first and last name
    $nameParts = preg_split('/\s+/', $fullName);
    $firstName = $nameParts[0] ?? '';
    $lastName = '';
    if (count($nameParts) > 1) {
        array_shift($nameParts);
        $lastName = implode(' ', $nameParts);
    }
    $cleanPhone = preg_replace('/[^\d]/', '', $phone);

    // Atomic Database Transaction
    $pdo->beginTransaction();
    try {
        // 1. Update contacts
        $updateContact = $pdo->prepare("
            UPDATE contacts SET 
                name = :name,
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone = :phone,
                clean_phone = :clean_phone,
                job_title = :job_title,
                updated_at = NOW()
            WHERE id = :contact_id 
              AND organization_id = :org_id 
              AND company_id = :company_id
        ");
        $updateContact->execute([
            ':name'         => $fullName,
            ':first_name'   => $firstName,
            ':last_name'    => $lastName,
            ':email'        => $email,
            ':phone'        => $phone,
            ':clean_phone'  => $cleanPhone,
            ':job_title'    => $jobTitle,
            ':contact_id'   => $contactId,
            ':org_id'       => $orgId,
            ':company_id'   => $companyId,
        ]);

        // 2. Update client_portal_users email
        $updateCpu = $pdo->prepare("
            UPDATE client_portal_users SET 
                email = :email,
                updated_at = NOW()
            WHERE id = :portal_user_id 
              AND organization_id = :org_id 
              AND company_id = :company_id 
              AND contact_id = :contact_id
        ");
        $updateCpu->execute([
            ':email'          => $email,
            ':portal_user_id' => $portalUserId,
            ':org_id'         => $orgId,
            ':company_id'     => $companyId,
            ':contact_id'     => $contactId,
        ]);

        // 3. Upsert Timezone Preference in crm_settings
        $tzKey = 'client_user_' . $portalUserId . '_timezone';
        $upsertTz = $pdo->prepare("
            INSERT INTO crm_settings (organization_id, setting_key, setting_value, created_at, updated_at)
            VALUES (:org_id, :setting_key, :val, NOW(), NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        $upsertTz->execute([
            ':org_id'      => $orgId,
            ':setting_key' => $tzKey,
            ':val'         => $timezone,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('client_update_personal_profile error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to save profile changes. Please try again.', 'status' => 500];
    }

    // Refresh and return full profile
    $refreshed = client_get_full_profile($pdo, $orgId, $companyId, $contactId, $portalUserId);
    return [
        'success' => true,
        'message' => 'Profile updated successfully.',
        'data'    => $refreshed,
        'status'  => 200,
    ];
}

/**
 * Change Client Portal user password.
 * Strictly verifies current password and enforces minimum 6 character length.
 */
function client_change_portal_password(
    PDO $pdo,
    int $orgId,
    int $companyId,
    int $contactId,
    int $portalUserId,
    string $currentPass,
    string $newPass,
    string $confirmPass
): array {
    if ($currentPass === '' || $newPass === '' || $confirmPass === '') {
        return ['success' => false, 'message' => 'All password fields are required.', 'status' => 422];
    }

    if (mb_strlen($newPass) < 6) {
        return ['success' => false, 'message' => 'New password must be at least 6 characters.', 'status' => 422];
    }

    if ($newPass !== $confirmPass) {
        return ['success' => false, 'message' => 'New password and confirmation do not match.', 'status' => 422];
    }

    // Retrieve existing password hash from client_portal_users
    $stmt = $pdo->prepare("
        SELECT password_hash 
        FROM client_portal_users 
        WHERE id = :user_id 
          AND organization_id = :org_id 
          AND company_id = :company_id 
          AND contact_id = :contact_id 
          AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([
        ':user_id'    => $portalUserId,
        ':org_id'     => $orgId,
        ':company_id' => $companyId,
        ':contact_id' => $contactId,
    ]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['password_hash'])) {
        return ['success' => false, 'message' => 'Account not found or inactive.', 'status' => 404];
    }

    if (!password_verify($currentPass, $user['password_hash'])) {
        return ['success' => false, 'message' => 'The current password you entered is incorrect.', 'status' => 422];
    }

    // Hash new password using PASSWORD_DEFAULT
    $newHash = password_hash($newPass, PASSWORD_DEFAULT);

    try {
        $updateStmt = $pdo->prepare("
            UPDATE client_portal_users SET 
                password_hash = :hash,
                updated_at = NOW()
            WHERE id = :user_id 
              AND organization_id = :org_id 
              AND company_id = :company_id 
              AND contact_id = :contact_id
        ");
        $updateStmt->execute([
            ':hash'       => $newHash,
            ':user_id'    => $portalUserId,
            ':org_id'     => $orgId,
            ':company_id' => $companyId,
            ':contact_id' => $contactId,
        ]);

        return ['success' => true, 'message' => 'Password updated successfully.', 'status' => 200];
    } catch (Throwable $e) {
        error_log('client_change_portal_password error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update password. Please try again.', 'status' => 500];
    }
}

/**
 * Update client portal notification preferences.
 * Stored in crm_settings with user-specific isolation.
 */
function client_update_portal_notifications(
    PDO $pdo,
    int $orgId,
    int $portalUserId,
    bool $emailSummaries,
    bool $inPortal
): array {
    $notifKey = 'client_user_' . $portalUserId . '_notifications';
    $payload = json_encode([
        'email_summaries' => $emailSummaries ? 1 : 0,
        'in_portal'       => $inPortal ? 1 : 0,
    ], JSON_UNESCAPED_UNICODE);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO crm_settings (organization_id, setting_key, setting_value, created_at, updated_at)
            VALUES (:org_id, :setting_key, :val, NOW(), NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        $stmt->execute([
            ':org_id'      => $orgId,
            ':setting_key' => $notifKey,
            ':val'         => $payload,
        ]);

        return [
            'success' => true,
            'message' => 'Notification preferences saved.',
            'data'    => [
                'email_summaries' => $emailSummaries,
                'in_portal'       => $inPortal,
            ],
            'status'  => 200,
        ];
    } catch (Throwable $e) {
        error_log('client_update_portal_notifications error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to save notification preferences.', 'status' => 500];
    }
}
