<?php
/**
 * NexFlow CRM — Client Portal Documents API Controller
 * Provides company-isolated document listings, secure details, inline previews,
 * streaming downloads, and transaction-safe client document uploads.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/client-auth.php';
require_once __DIR__ . '/../includes/client-helpers.php';
require_once __DIR__ . '/../includes/client-documents-data.php';

// 1. Session Authentication Guard (HTTP 401 if unauthenticated)
$client = client_current_user();
if (!$client) {
    client_json_response(false, 'Unauthorized access. Please log in.', [], 401);
}

$orgId     = (int)($client['organization_id'] ?? 0);
$companyId = (int)($client['company_id'] ?? 0);
$contactId = (int)($client['contact_id'] ?? 0);

if ($orgId <= 0 || $companyId <= 0 || $contactId <= 0) {
    client_json_response(false, 'Invalid client session context.', [], 400);
}

// 2. HTTP Method Gate
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
    client_json_response(false, "Method Not Allowed. '{$method}' is not supported.", [], 405);
}

$action = trim((string)($_GET['action'] ?? ($_POST['action'] ?? 'list')));

$pdo = nexflow_db();

// ============================================================================
// ACTION: list (GET) — Authorized company documents
// ============================================================================
if ($action === 'list') {
    if ($method !== 'GET') {
        client_json_response(false, "Method Not Allowed. Action 'list' requires GET.", [], 405);
    }

    $filters = [
        'category' => trim((string)($_GET['category'] ?? 'All')),
        'search'   => trim((string)($_GET['search'] ?? '')),
    ];

    $result = client_get_documents_data($pdo, $orgId, $companyId, $filters);

    client_json_response(true, 'Documents loaded successfully.', $result, 200);
}

// ============================================================================
// ACTION: get (GET) — Single document details
// ============================================================================
if ($action === 'get') {
    if ($method !== 'GET') {
        client_json_response(false, "Method Not Allowed. Action 'get' requires GET.", [], 405);
    }

    $rawId = $_GET['id'] ?? null;
    if ($rawId === null || $rawId === '') {
        client_json_response(false, 'Document ID is required.', [], 400);
    }

    $docId = (int)preg_replace('/^doc[-_]?/i', '', (string)$rawId);
    if ($docId <= 0) {
        client_json_response(false, 'Invalid document ID.', [], 400);
    }

    $stmt = $pdo->prepare("
        SELECT 
            d.id, d.organization_id, d.document_code, d.title,
            d.original_filename, d.file_path, d.file_extension,
            d.file_size, d.file_size_bytes, d.mime_type, d.document_type,
            d.category, d.company_id, d.contact_id, d.related_record,
            d.status, d.is_archived, d.is_shared, d.description,
            d.created_at, d.updated_at,
            co.name AS company_name,
            TRIM(CONCAT(COALESCE(own.first_name, ''), ' ', COALESCE(own.last_name, ''))) AS owner_name,
            TRIM(CONCAT(COALESCE(upl.first_name, ''), ' ', COALESCE(upl.last_name, ''))) AS uploader_name
        FROM documents d
        LEFT JOIN companies co ON co.id = d.company_id AND co.organization_id = d.organization_id
        LEFT JOIN users own    ON own.id = d.owner_id   AND own.organization_id = d.organization_id
        LEFT JOIN users upl    ON upl.id = d.uploaded_by AND upl.organization_id = d.organization_id
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
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        client_json_response(false, 'Document not found or access denied.', [], 404);
    }

    client_json_response(true, 'Document details loaded.', [
        'document' => client_format_document_item($row),
    ], 200);
}

// ============================================================================
// ACTION: preview (GET) — Safe inline stream for browser preview
// ============================================================================
if ($action === 'preview') {
    if ($method !== 'GET') {
        client_json_response(false, "Method Not Allowed. Action 'preview' requires GET.", [], 405);
    }

    $rawId = $_GET['id'] ?? null;
    if ($rawId === null || $rawId === '') {
        client_json_response(false, 'Document ID is required.', [], 400);
    }
    $docId = (int)preg_replace('/^doc[-_]?/i', '', (string)$rawId);
    if ($docId <= 0) {
        client_json_response(false, 'Invalid document ID.', [], 400);
    }

    $doc = client_get_document_for_file_access($pdo, $orgId, $companyId, $docId);
    if (!$doc) {
        client_json_response(false, 'Document not found or file is inaccessible.', [], 404);
    }

    $filePath = (string)$doc['resolved_file_path'];
    if (!file_exists($filePath) || !is_readable($filePath)) {
        client_json_response(false, 'Physical document file not found on server.', [], 404);
    }

    // Inspect real MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = $finfo ? finfo_file($finfo, $filePath) : ($doc['mime_type'] ?: 'application/octet-stream');
    if ($finfo) finfo_close($finfo);

    // List of MIME types safe for inline rendering
    $safeInlineMimes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'text/plain',
    ];

    $filename = basename((string)($doc['original_filename'] ?: ($doc['title'] . '.' . ($doc['file_extension'] ?: 'pdf'))));
    $safeFilename = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $filename);

    if (in_array($realMime, $safeInlineMimes, true)) {
        header('Content-Type: ' . $realMime);
        header('Content-Disposition: inline; filename="' . $safeFilename . '"');
    } else {
        header('Content-Type: ' . $realMime);
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    }

    header('Content-Length: ' . (string)filesize($filePath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Clean output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    readfile($filePath);
    exit;
}

// ============================================================================
// ACTION: download (GET) — Authenticated streaming download
// ============================================================================
if ($action === 'download') {
    if ($method !== 'GET') {
        client_json_response(false, "Method Not Allowed. Action 'download' requires GET.", [], 405);
    }

    $rawId = $_GET['id'] ?? null;
    if ($rawId === null || $rawId === '') {
        client_json_response(false, 'Document ID is required.', [], 400);
    }
    $docId = (int)preg_replace('/^doc[-_]?/i', '', (string)$rawId);
    if ($docId <= 0) {
        client_json_response(false, 'Invalid document ID.', [], 400);
    }

    $doc = client_get_document_for_file_access($pdo, $orgId, $companyId, $docId);
    if (!$doc) {
        client_json_response(false, 'Document not found or access denied.', [], 404);
    }

    $filePath = (string)$doc['resolved_file_path'];
    if (!file_exists($filePath) || !is_readable($filePath)) {
        client_json_response(false, 'Physical file not found on disk.', [], 404);
    }

    $mime = !empty($doc['mime_type']) ? (string)$doc['mime_type'] : 'application/octet-stream';
    $filename = basename((string)($doc['original_filename'] ?: ($doc['title'] . '.' . ($doc['file_extension'] ?: 'dat'))));
    $safeFilename = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $filename);

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    header('Content-Length: ' . (string)filesize($filePath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    readfile($filePath);
    exit;
}

// ============================================================================
// ACTION: upload (POST) — Client document upload
// ============================================================================
if ($action === 'upload') {
    if ($method !== 'POST') {
        client_json_response(false, "Method Not Allowed. '{$method}' is not permitted for upload. Use POST.", [], 405);
    }

    // CSRF Token Validation (HTTP 403)
    $submittedCsrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!verify_client_csrf($submittedCsrf)) {
        client_json_response(false, 'CSRF security token missing or invalid.', [], 403);
    }

    if (empty($_FILES['file'])) {
        client_json_response(false, 'No file was submitted.', [], 422);
    }

    try {
        $meta = [
            'title'    => trim((string)($_POST['title'] ?? '')),
            'category' => trim((string)($_POST['category'] ?? 'General')),
        ];

        $uploadedDoc = client_upload_document($pdo, $client, $_FILES['file'], $meta);

        client_json_response(true, "Document '{$uploadedDoc['title']}' uploaded successfully.", [
            'document' => $uploadedDoc,
        ], 201);

    } catch (InvalidArgumentException $e) {
        client_json_response(false, $e->getMessage(), [], 422);
    } catch (Throwable $e) {
        error_log('Client document upload error: ' . $e->getMessage());
        client_json_response(false, 'Failed to upload document: ' . $e->getMessage(), [], 500);
    }
}

// Fallback for unknown action
client_json_response(false, "Unknown action '{$action}'.", [], 400);
