<?php
/**
 * NexFlow CRM — Client Portal Documents Data Loader
 * Authoritative data loader and formatting helpers for client-accessible documents,
 * streaming download resolution, and secure client document uploads.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/client-auth.php';
require_once __DIR__ . '/client-helpers.php';

/**
 * Sanitize and format a raw documents row for Client Portal presentation.
 * Excludes server file paths, internal staff notes, and sensitive IDs.
 */
function client_format_document_item(array $row): array
{
    $id = (int)($row['id'] ?? 0);
    $docCode = (string)($row['document_code'] ?? ('DOC-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT)));
    $title = (string)($row['title'] ?? 'Untitled Document');
    $origName = (string)($row['original_filename'] ?? $title);
    $category = (string)($row['category'] ?? 'General');
    $docType = (string)($row['document_type'] ?? 'Other');
    $fileExt = strtolower((string)($row['file_extension'] ?? pathinfo($origName, PATHINFO_EXTENSION)));
    $fileSize = (string)($row['file_size'] ?? '0 KB');
    $status = (string)($row['status'] ?? 'Available');
    $createdAt = (string)($row['created_at'] ?? date('Y-m-d H:i:s'));
    $relatedRecord = (string)($row['display_related_record'] ?? ($row['related_record'] ?? ''));

    // Check physical file existence safely without exposing path
    $filePath = (string)($row['file_path'] ?? '');
    $hasPhysicalFile = false;
    if (!empty($filePath)) {
        $realBase = realpath(__DIR__ . '/../uploads/documents');
        $fullPath = realpath(__DIR__ . '/../' . ltrim($filePath, '/\\'));
        if ($fullPath && $realBase && strpos($fullPath, $realBase) === 0 && is_file($fullPath)) {
            $hasPhysicalFile = true;
        }
    }

    $uploaderName = trim((string)($row['uploader_name'] ?? ''));
    $ownerName = trim((string)($row['owner_name'] ?? ''));
    $displayUploader = $uploaderName ?: ($ownerName ?: 'NexFlow Account Team');

    $formattedDate = date('M j, Y', strtotime($createdAt));
    $isoDate = date('Y-m-d', strtotime($createdAt));

    return [
        'id'                => $id,
        'doc_id'            => 'doc-' . $id,
        'document_code'     => $docCode,
        'title'             => $title,
        'name'              => $title,
        'original_filename' => $origName,
        'category'          => $category,
        'document_type'     => $docType,
        'type'              => $docType,
        'file_extension'    => $fileExt,
        'file_size'         => $fileSize,
        'size'              => $fileSize,
        'mime_type'         => (string)($row['mime_type'] ?? 'application/octet-stream'),
        'status'            => $status,
        'related_record'    => $relatedRecord,
        'deal'              => $relatedRecord ?: ($row['company_name'] ?? 'Enterprise Workspace'),
        'uploaded_by'       => $displayUploader,
        'uploadedBy'        => $displayUploader,
        'date'              => $isoDate,
        'formatted_date'    => $formattedDate,
        'has_file'          => $hasPhysicalFile,
        'description'       => (string)($row['description'] ?? ''),
    ];
}

/**
 * Retrieve client-authorized documents scoped strictly to organization and company.
 * Enforces:
 * - d.organization_id = :org_id
 * - d.company_id = :company_id AND d.company_id IS NOT NULL
 * - d.is_archived = 0
 * - d.status != 'Internal'
 * - (d.is_shared = 1 OR d.status IN ('Shared', 'Signed', 'Available'))
 */
function client_get_documents_data(PDO $pdo, int $orgId, int $companyId, array $filters = []): array
{
    $where = [
        'd.organization_id = :org_id',
        'd.company_id = :company_id',
        'd.company_id IS NOT NULL',
        'd.is_archived = 0',
        "d.status != 'Internal'",
        "(d.is_shared = 1 OR d.status IN ('Shared', 'Signed', 'Available'))"
    ];

    $params = [
        ':org_id'     => $orgId,
        ':company_id' => $companyId,
    ];

    // Optional category filter
    $category = trim((string)($filters['category'] ?? 'All'));
    if ($category !== '' && strtolower($category) !== 'all') {
        $where[] = 'LOWER(d.category) = :category';
        $params[':category'] = strtolower($category);
    }

    // Optional search query
    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(d.title LIKE :s1 OR d.document_code LIKE :s2 OR d.original_filename LIKE :s3 OR d.category LIKE :s4 OR d.related_record LIKE :s5 OR d.description LIKE :s6)';
        $like = '%' . $search . '%';
        $params[':s1'] = $like;
        $params[':s2'] = $like;
        $params[':s3'] = $like;
        $params[':s4'] = $like;
        $params[':s5'] = $like;
        $params[':s6'] = $like;
    }

    $whereSQL = implode(' AND ', $where);

    $sql = "
        SELECT 
            d.id,
            d.organization_id,
            d.document_code,
            d.title,
            d.original_filename,
            d.stored_filename,
            d.file_path,
            d.file_extension,
            d.file_size,
            d.file_size_bytes,
            d.mime_type,
            d.document_type,
            d.category,
            d.company_id,
            d.contact_id,
            d.deal_id,
            d.project_id,
            d.proposal_id,
            d.contract_id,
            d.invoice_id,
            d.related_type,
            d.related_id,
            d.related_record,
            COALESCE(
                d.related_record,
                CASE
                    WHEN d.project_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(prj.project_code, ''), CASE WHEN prj.project_code IS NOT NULL AND prj.project_code != '' THEN ' — ' ELSE '' END, COALESCE(prj.name, '')))
                    WHEN d.deal_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(dl.deal_code, ''), CASE WHEN dl.deal_code IS NOT NULL AND dl.deal_code != '' THEN ' — ' ELSE '' END, COALESCE(dl.name, '')))
                    WHEN d.proposal_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(prop.proposal_number, ''), CASE WHEN prop.proposal_number IS NOT NULL AND prop.proposal_number != '' THEN ' — ' ELSE '' END, COALESCE(prop.title, '')))
                    WHEN d.contract_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(ctr.contract_number, ''), CASE WHEN ctr.contract_number IS NOT NULL AND ctr.contract_number != '' THEN ' — ' ELSE '' END, COALESCE(ctr.title, '')))
                    WHEN d.invoice_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(inv.invoice_number, ''), CASE WHEN inv.invoice_number IS NOT NULL AND inv.invoice_number != '' THEN ' — ' ELSE '' END, COALESCE(inv.title, '')))
                    ELSE NULL
                END
            ) AS display_related_record,
            d.status,
            d.is_archived,
            d.is_shared,
            d.description,
            d.created_at,
            d.updated_at,
            co.name AS company_name,
            TRIM(CONCAT(COALESCE(own.first_name, ''), ' ', COALESCE(own.last_name, ''))) AS owner_name,
            TRIM(CONCAT(COALESCE(upl.first_name, ''), ' ', COALESCE(upl.last_name, ''))) AS uploader_name
        FROM documents d
        LEFT JOIN companies co   ON co.id = d.company_id AND co.organization_id = d.organization_id
        LEFT JOIN users own      ON own.id = d.owner_id   AND own.organization_id = d.organization_id
        LEFT JOIN users upl      ON upl.id = d.uploaded_by AND upl.organization_id = d.organization_id
        LEFT JOIN projects prj   ON prj.id = d.project_id AND prj.organization_id = d.organization_id
        LEFT JOIN deals dl       ON dl.id = d.deal_id AND dl.organization_id = d.organization_id
        LEFT JOIN proposals prop ON prop.id = d.proposal_id AND prop.organization_id = d.organization_id
        LEFT JOIN contracts ctr  ON ctr.id = d.contract_id AND ctr.organization_id = d.organization_id
        LEFT JOIN invoices inv   ON inv.id = d.invoice_id AND inv.organization_id = d.organization_id
        WHERE {$whereSQL}
        ORDER BY d.created_at DESC, d.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedDocs = [];
    $categoryCounts = [];
    foreach ($rows as $row) {
        $item = client_format_document_item($row);
        $formattedDocs[] = $item;
        $cat = $item['category'];
        $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
    }

    return [
        'documents'       => $formattedDocs,
        'total'           => count($formattedDocs),
        'category_counts' => $categoryCounts,
    ];
}

/**
 * Retrieve a single document record for authenticated preview or download.
 * Validates company ownership, client visibility, and ensures file is on disk
 * within the approved directory (blocking traversal).
 */
function client_get_document_for_file_access(PDO $pdo, int $orgId, int $companyId, int $docId): ?array
{
    if ($orgId <= 0 || $companyId <= 0 || $docId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT 
            d.id,
            d.organization_id,
            d.company_id,
            d.document_code,
            d.title,
            d.original_filename,
            d.stored_filename,
            d.file_path,
            d.file_extension,
            d.file_size,
            d.file_size_bytes,
            d.mime_type,
            d.document_type,
            d.category,
            d.status,
            d.is_archived,
            d.is_shared,
            d.description,
            d.created_at
        FROM documents d
        WHERE d.id = :id
          AND d.organization_id = :org_id
          AND d.company_id = :company_id
          AND d.company_id IS NOT NULL
          AND d.is_archived = 0
          AND d.status != 'Internal'
          AND (d.is_shared = 1 OR d.status IN ('Shared', 'Signed', 'Available'))
        LIMIT 1
    ");

    $stmt->execute([
        ':id'         => $docId,
        ':org_id'     => $orgId,
        ':company_id' => $companyId,
    ]);

    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doc || empty($doc['file_path'])) {
        return null;
    }

    // Path traversal validation
    $relPath = ltrim((string)$doc['file_path'], '/\\');
    $appRoot = realpath(__DIR__ . '/..');
    $baseUploadDir = realpath(__DIR__ . '/../uploads/documents');
    $fullPath = realpath($appRoot . DIRECTORY_SEPARATOR . $relPath);

    if (!$fullPath || !$baseUploadDir) {
        return null;
    }

    // Verify full path is strictly inside uploads/documents
    if (strpos($fullPath, $baseUploadDir) !== 0 || !is_file($fullPath)) {
        return null;
    }

    $doc['resolved_file_path'] = $fullPath;
    $doc['resolved_file_size'] = filesize($fullPath);

    return $doc;
}

/**
 * Handle secure file upload initiated by an authenticated client.
 * Strictly scopes company_id and contact_id to session.
 * uploaded_by is set to NULL to cleanly honor the foreign key to users(id).
 * owner_id is set to the company's assigned Account Executive.
 */
function client_upload_document(PDO $pdo, array $client, array $file, array $meta): array
{
    $orgId = (int)($client['organization_id'] ?? 0);
    $companyId = (int)($client['company_id'] ?? 0);
    $contactId = (int)($client['contact_id'] ?? 0);
    $ownerId = (int)($client['ae_user_id'] ?? 0) ?: 1;

    if ($orgId <= 0 || $companyId <= 0 || $contactId <= 0) {
        throw new InvalidArgumentException('Invalid client authentication context.');
    }

    // 1. Validate File Upload Status
    if (empty($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $code = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $msg = match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum allowed upload size.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was selected for upload.',
            default => 'Upload error occurred. Please try again.',
        };
        throw new InvalidArgumentException($msg);
    }

    // 2. Validate File Size (25MB limit)
    $maxBytes = 25 * 1024 * 1024;
    $fileSizeBytes = (int)$file['size'];
    if ($fileSizeBytes <= 0 || $fileSizeBytes > $maxBytes) {
        throw new InvalidArgumentException('File size must be between 1 byte and 25 MB.');
    }

    $tmpPath = (string)$file['tmp_name'];
    if (!is_uploaded_file($tmpPath) && !file_exists($tmpPath)) {
        throw new RuntimeException('Invalid temporary upload file.');
    }

    // 3. Extension Validation
    $origName = basename((string)$file['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    $allowedExts = [
        'pdf', 'doc', 'docx', 'txt', 'rtf', 'png', 'jpg', 'jpeg',
        'zip', 'xlsx', 'xls', 'pptx', 'ppt', 'csv', 'fig'
    ];
    $dangerousExts = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe',
        'bat', 'cmd', 'sh', 'vbs', 'js', 'py', 'pl', 'cgi', 'dll', 'com'
    ];

    if (in_array($ext, $dangerousExts, true) || !in_array($ext, $allowedExts, true)) {
        throw new InvalidArgumentException('File extension .' . htmlspecialchars($ext) . ' is not permitted. Allowed: PDF, DOCX, XLSX, Images, ZIP, CSV, TXT.');
    }

    // 4. Real MIME Validation using Fileinfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = $finfo ? finfo_file($finfo, $tmpPath) : 'application/octet-stream';
    if ($finfo) finfo_close($finfo);

    $blockedMimes = [
        'text/x-php', 'application/x-httpd-php', 'application/x-httpd-php-source',
        'application/x-executable', 'application/x-dosexec', 'application/x-sh',
        'text/x-shellscript', 'application/javascript', 'text/javascript'
    ];
    if (in_array(strtolower((string)$realMime), $blockedMimes, true)) {
        throw new InvalidArgumentException('File content type is not permitted for security reasons.');
    }

    // 5. Metadata Sanitization
    $title = trim((string)($meta['title'] ?? ''));
    if ($title === '') {
        $title = pathinfo($origName, PATHINFO_FILENAME);
    }
    if (mb_strlen($title) > 200) {
        $title = mb_substr($title, 0, 200);
    }

    $category = trim((string)($meta['category'] ?? 'General'));
    $validCategories = ['Contracts', 'Projects', 'Proposals', 'Invoices', 'Reports', 'General'];
    $categoryMatched = 'General';
    foreach ($validCategories as $vc) {
        if (strcasecmp($vc, $category) === 0 || stripos($category, $vc) !== false) {
            $categoryMatched = $vc;
            break;
        }
    }

    // Determine document type
    $docType = match ($ext) {
        'pdf' => 'PDF',
        'doc', 'docx', 'rtf', 'txt' => 'DOCX',
        'xls', 'xlsx', 'csv' => 'XLSX',
        'ppt', 'pptx' => 'PPTX',
        'png', 'jpg', 'jpeg', 'gif', 'fig' => 'Image',
        default => 'Other'
    };

    $sizeFormatted = $fileSizeBytes > 1048576
        ? round($fileSizeBytes / 1048576, 1) . ' MB'
        : round($fileSizeBytes / 1024, 1) . ' KB';

    // 6. Generate Sequential Document Code (e.g. DOC-003)
    $stmtMax = $pdo->prepare("SELECT document_code FROM documents WHERE organization_id = ? ORDER BY id DESC LIMIT 200");
    $stmtMax->execute([$orgId]);
    $existingCodes = $stmtMax->fetchAll(PDO::FETCH_COLUMN);
    $maxNum = 0;
    foreach ($existingCodes as $code) {
        if (preg_match('/DOC[-_]?0*(\d+)/i', (string)$code, $m)) {
            $val = (int)$m[1];
            if ($val > $maxNum) $maxNum = $val;
        }
    }
    $docNumber = 'DOC-' . str_pad((string)($maxNum + 1), 3, '0', STR_PAD_LEFT);

    // 7. Atomic DB Insertion & Physical File Persistence
    $createdFileOnDisk = null;
    $pdo->beginTransaction();
    try {
        $description = 'Uploaded via Client Portal by ' . ($client['contact_name'] ?? 'Authorized Client');

        $stmtIns = $pdo->prepare("
            INSERT INTO documents (
                organization_id, document_code, title,
                original_filename, stored_filename, file_path,
                file_size, file_size_bytes, mime_type, file_extension,
                document_type, category, related_record, description,
                company_id, contact_id, lead_id, deal_id, project_id,
                proposal_id, contract_id, invoice_id,
                owner_id, uploaded_by, status, is_archived, is_shared,
                created_at, updated_at
            ) VALUES (
                ?, ?, ?,
                ?, NULL, NULL,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, NULL, NULL, NULL,
                NULL, NULL, NULL,
                ?, NULL, 'Available', 0, 1,
                NOW(), NOW()
            )
        ");

        $stmtIns->execute([
            $orgId, $docNumber, $title,
            $origName,
            $sizeFormatted, $fileSizeBytes, $realMime, $ext,
            $docType, $categoryMatched, $client['company_name'] ?? 'Client Portal', $description,
            $companyId, $contactId,
            $ownerId
        ]);

        $docId = (int)$pdo->lastInsertId();

        // Target storage path
        $uploadDir = __DIR__ . '/../uploads/documents/' . $orgId . '/' . $docId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $origName);
        $storedName = time() . '_' . $safeName;
        $targetPath = $uploadDir . '/' . $storedName;
        $dbRelPath = 'uploads/documents/' . $orgId . '/' . $docId . '/' . $storedName;

        $saved = is_uploaded_file($tmpPath)
            ? move_uploaded_file($tmpPath, $targetPath)
            : copy($tmpPath, $targetPath);

        if (!$saved || !file_exists($targetPath)) {
            throw new RuntimeException('Failed to persist uploaded file to storage.');
        }

        $createdFileOnDisk = $targetPath;

        // Update stored_filename and file_path
        $pdo->prepare("UPDATE documents SET stored_filename = ?, file_path = ? WHERE id = ? AND organization_id = ?")
            ->execute([$storedName, $dbRelPath, $docId, $orgId]);

        // Log Team Activity
        $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description,
                related_entity, related_entity_id, created_by, created_at
            ) VALUES (?, ?, 'created', '', ?, 'documents', ?, 0, NOW())
        ")->execute([
            $orgId,
            $ownerId,
            "Client '{$client['contact_name']}' uploaded document '{$docNumber}' — '{$title}'.",
            $docId
        ]);

        $pdo->commit();

        return [
            'id'            => $docId,
            'document_code' => $docNumber,
            'title'         => $title,
            'file_size'     => $sizeFormatted,
            'category'      => $categoryMatched,
            'document_type' => $docType,
            'status'        => 'Available',
            'created_at'    => date('Y-m-d H:i:s'),
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($createdFileOnDisk && file_exists($createdFileOnDisk)) {
            @unlink($createdFileOnDisk);
            @rmdir(dirname($createdFileOnDisk));
        }
        throw $e;
    }
}
