<?php
/**
 * NexFlow CRM - Personal Profile Management API
 */

require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function profile_json(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) {
    profile_json(false, 'Unauthorized. Please sign in.', [], 401);
}

$userId = (int) $_SESSION['user_id'];

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    profile_json(false, 'Database connection failed.', [], 500);
}

$method = $_SERVER['REQUEST_METHOD'];

// Helper to format user output
function format_user_profile(array $user): array
{
    $photoUrl = null;
    if (!empty($user['photo_path'])) {
        $photoUrl = '/nexFlow/' . ltrim($user['photo_path'], '/');
    }
    
    $nameParts = explode(' ', trim($user['name'] ?? ''));
    $initials = strtoupper(substr($nameParts[0] ?? '', 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
    if (empty($initials)) {
        $initials = 'U';
    }

    return [
        'id'           => (int) $user['id'],
        'first_name'   => $user['first_name'] ?? '',
        'last_name'    => $user['last_name'] ?? '',
        'display_name' => $user['display_name'] ?? $user['name'] ?? '',
        'name'         => $user['name'] ?? '',
        'email'        => $user['email'] ?? '',
        'phone'        => $user['phone'] ?? '',
        'job_title'    => $user['job_title'] ?? '',
        'department'   => $user['department'] ?? '',
        'location'     => $user['location'] ?? '',
        'timezone'     => $user['timezone'] ?? '',
        'language'     => $user['language'] ?? '',
        'short_bio'    => $user['short_bio'] ?? '',
        'linkedin_url' => $user['linkedin_url'] ?? '',
        'photo_path'   => $user['photo_path'] ?? null,
        'photo_url'    => $photoUrl,
        'initials'     => $initials,
        'role'         => $user['role'] ?? 'admin'
    ];
}

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        profile_json(false, 'User account not found.', [], 404);
    }

    profile_json(true, 'Profile retrieved.', format_user_profile($user));
}

if ($method === 'POST') {
    // Fetch current user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        profile_json(false, 'User account not found.', [], 404);
    }

    $firstName   = trim((string)($_POST['first_name'] ?? ''));
    $lastName    = trim((string)($_POST['last_name'] ?? ''));
    $displayName = trim((string)($_POST['display_name'] ?? ''));
    $email       = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone       = trim((string)($_POST['phone'] ?? ''));
    $jobTitle    = trim((string)($_POST['job_title'] ?? ''));
    $department  = trim((string)($_POST['department'] ?? ''));
    $location    = trim((string)($_POST['location'] ?? ''));
    $timezone    = trim((string)($_POST['timezone'] ?? ''));
    $language    = trim((string)($_POST['language'] ?? ''));
    $shortBio    = trim((string)($_POST['short_bio'] ?? ''));
    $linkedinUrl = trim((string)($_POST['linkedin_url'] ?? ''));
    $removePhoto = isset($_POST['remove_photo']) && ($_POST['remove_photo'] === '1' || $_POST['remove_photo'] === 'true');

    // Validation
    if ($firstName === '') {
        profile_json(false, 'First name is required.', [], 422);
    }
    if ($lastName === '') {
        profile_json(false, 'Last name is required.', [], 422);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        profile_json(false, 'Please enter a valid work email address.', [], 422);
    }

    // Email uniqueness check
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? AND id != ? LIMIT 1");
    $checkStmt->execute([$email, $userId]);
    if ($checkStmt->fetch()) {
        profile_json(false, 'The email address is already in use by another account.', [], 422);
    }

    if ($displayName === '') {
        $displayName = trim($firstName . ' ' . $lastName);
    }
    $fullName = trim($firstName . ' ' . $lastName);

    // Photo Handling
    $photoPath = $currentUser['photo_path'] ?? null;
    $baseUploadDir = __DIR__ . '/../../uploads/users';

    if ($removePhoto) {
        if (!empty($photoPath)) {
            $existingFile = __DIR__ . '/../../' . ltrim($photoPath, '/');
            if (file_exists($existingFile) && is_file($existingFile)) {
                @unlink($existingFile);
            }
        }
        $photoPath = null;
    } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        
        // 2MB size check
        if ($file['size'] > 2 * 1024 * 1024) {
            profile_json(false, 'Profile photo size must be less than 2MB.', [], 422);
        }

        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($ext, $allowedExts, true) || !in_array($mime, $allowedMimes, true)) {
            profile_json(false, 'Invalid image format. Only JPG, PNG, GIF, and WEBP files are allowed.', [], 422);
        }

        if (!is_dir($baseUploadDir)) {
            mkdir($baseUploadDir, 0755, true);
        }

        $newFileName = 'user_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetFile = $baseUploadDir . '/' . $newFileName;

        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            // Delete old file if present
            if (!empty($currentUser['photo_path'])) {
                $oldFile = __DIR__ . '/../../' . ltrim($currentUser['photo_path'], '/');
                if (file_exists($oldFile) && is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
            $photoPath = 'uploads/users/' . $newFileName;
        } else {
            profile_json(false, 'Failed to save uploaded photo file.', [], 500);
        }
    }

    // Save to Database
    $sql = "UPDATE users SET 
                name = :name,
                first_name = :first_name,
                last_name = :last_name,
                display_name = :display_name,
                email = :email,
                phone = :phone,
                job_title = :job_title,
                department = :department,
                location = :location,
                timezone = :timezone,
                language = :language,
                photo_path = :photo_path,
                short_bio = :short_bio,
                linkedin_url = :linkedin_url,
                updated_at = NOW()
            WHERE id = :id";

    $upStmt = $pdo->prepare($sql);
    $upStmt->execute([
        ':name'         => $fullName,
        ':first_name'   => $firstName,
        ':last_name'    => $lastName,
        ':display_name' => $displayName,
        ':email'        => $email,
        ':phone'        => $phone,
        ':job_title'    => $jobTitle,
        ':department'   => $department,
        ':location'     => $location,
        ':timezone'     => $timezone,
        ':language'     => $language,
        ':photo_path'   => $photoPath,
        ':short_bio'    => $shortBio,
        ':linkedin_url' => $linkedinUrl,
        ':id'           => $userId
    ]);

    // Fetch updated data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

    profile_json(true, 'Profile updated successfully.', format_user_profile($updatedUser));
}

profile_json(false, 'Invalid request method.', [], 405);
