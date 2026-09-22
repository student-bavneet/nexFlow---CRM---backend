<?php
/**
 * NexFlow CRM - Centralized Role & Access Control Permission Engine
 */

require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get current active user ID
 */
function get_current_user_id() {
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (string)$_SESSION['user_id'];
    }
    return 1; // Default active admin user
}

/**
 * Check if a given user is a Super Administrator
 */
function is_super_admin($user_id = null): bool {
    if ($user_id === null) $user_id = get_current_user_id();

    if ($user_id === 'TM-001' || (is_numeric($user_id) && (int)$user_id === 1 && empty($_SESSION['user_custom_perms'][$user_id]))) {
        return true;
    }

    // Session fast path
    if ($user_id == ($_SESSION['user_id'] ?? null)) {
        $roleStr = strtolower((string)($_SESSION['user_role'] ?? ''));
        if ($roleStr === 'super_admin' || $roleStr === 'super administrator' || $roleStr === 'admin' || $roleStr === 'administrator') {
            return true;
        }
    }

    try {
        $pdo = nexflow_db();
        $stmt = $pdo->prepare("SELECT u.role, r.slug AS role_slug FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1");
        $stmt->execute([(int)$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return false;

        $r = strtolower($row['role'] ?? '');
        $rs = strtolower($row['role_slug'] ?? '');
        return ($r === 'super_admin' || $r === 'admin' || $r === 'administrator' || $rs === 'super_admin' || $rs === 'admin' || $rs === 'administrator');
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Check if active user has full access
 */
function is_full_access($user_id = null): bool {
    return is_super_admin($user_id);
}

/**
 * Get permissions map for a given user ID from Database
 */
function get_user_permissions($user_id = null): array {
    if ($user_id === null) $user_id = get_current_user_id();

    if (is_super_admin($user_id)) {
        return [
            'full_access' => true,
            'permissions' => []
        ];
    }

    try {
        $pdo = nexflow_db();
        $stmt = $pdo->prepare("SELECT p.module_key, p.action 
                               FROM users u 
                               JOIN roles r ON r.id = u.role_id 
                               JOIN role_permissions rp ON rp.role_id = r.id 
                               JOIN permissions p ON p.id = rp.permission_id 
                               WHERE u.id = ?");
        $stmt->execute([(int)$user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $permMap = [];
        foreach ($rows as $row) {
            $mod = strtolower($row['module_key']);
            $act = strtolower($row['action']);
            if (!isset($permMap[$mod])) {
                $permMap[$mod] = [];
            }
            if (!in_array($act, $permMap[$mod], true)) {
                $permMap[$mod][] = $act;
            }
        }

        // Also merge direct user_permissions from database
        $upStmt = $pdo->prepare("SELECT p.module_key, p.action 
                                 FROM user_permissions up 
                                 JOIN permissions p ON p.id = up.permission_id 
                                 WHERE up.user_id = ?");
        $upStmt->execute([(int)$user_id]);
        $upRows = $upStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($upRows as $row) {
            $mod = strtolower($row['module_key']);
            $act = strtolower($row['action']);
            if (!isset($permMap[$mod])) {
                $permMap[$mod] = [];
            }
            if (!in_array($act, $permMap[$mod], true)) {
                $permMap[$mod][] = $act;
            }
        }

        // Check session custom override if set
        if (!empty($_SESSION['user_custom_perms'][$user_id])) {
            $sessPerm = $_SESSION['user_custom_perms'][$user_id];
            if (!empty($sessPerm['full_access'])) {
                return ['full_access' => true, 'permissions' => []];
            }
            if (isset($sessPerm['permissions']) && is_array($sessPerm['permissions'])) {
                $permMap = $sessPerm['permissions'];
            }
        }

        return [
            'full_access' => false,
            'permissions' => $permMap
        ];
    } catch (Throwable $e) {
        if (!empty($_SESSION['user_custom_perms'][$user_id])) {
            return $_SESSION['user_custom_perms'][$user_id];
        }
        return [
            'full_access' => false,
            'permissions' => []
        ];
    }
}

/**
 * Save user custom permissions
 */
function save_user_permissions($user_id, $permissions, $full_access = false): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_custom_perms'])) {
        $_SESSION['user_custom_perms'] = [];
    }
    $_SESSION['user_custom_perms'][$user_id] = [
        'full_access' => (bool)$full_access,
        'permissions' => is_array($permissions) ? $permissions : []
    ];

    if (is_numeric($user_id)) {
        try {
            $pdo = nexflow_db();
            $hasTx = $pdo->inTransaction();
            if (!$hasTx) {
                $pdo->beginTransaction();
            }

            $delStmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
            $delStmt->execute([(int)$user_id]);

            if (!$full_access && is_array($permissions)) {
                $findPerm = $pdo->prepare("SELECT id FROM permissions WHERE LOWER(module_key) = ? AND LOWER(action) = ? LIMIT 1");
                $insPerm = $pdo->prepare("INSERT IGNORE INTO user_permissions (user_id, permission_id, created_at) VALUES (?, ?, NOW())");

                foreach ($permissions as $mod => $actions) {
                    if (is_numeric($mod) && is_string($actions) && strpos($actions, '.') !== false) {
                        [$modClean, $actClean] = explode('.', strtolower(trim($actions)), 2);
                        if ($modClean === 'estimate-requests') $modClean = 'estimates';
                        $findPerm->execute([$modClean, $actClean]);
                        $permId = $findPerm->fetchColumn();
                        if ($permId) {
                            $insPerm->execute([(int)$user_id, (int)$permId]);
                        }
                        continue;
                    }

                    $modClean = strtolower(trim((string)$mod));
                    if ($modClean === 'estimate-requests') $modClean = 'estimates';
                    $actionList = is_array($actions) ? $actions : [$actions];
                    foreach ($actionList as $act) {
                        $actClean = strtolower(trim((string)$act));
                        $findPerm->execute([$modClean, $actClean]);
                        $permId = $findPerm->fetchColumn();
                        if ($permId) {
                            $insPerm->execute([(int)$user_id, (int)$permId]);
                        }
                    }
                }
            }

            if (!$hasTx) {
                $pdo->commit();
            }
            return true;
        } catch (Throwable $e) {
            if (isset($hasTx) && !$hasTx && $pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    return true;
}

/**
 * Primary Permission Check Function
 */
function hasPermission($module, $action = 'view', $user_id = null): bool {
    if ($user_id === null) $user_id = get_current_user_id();

    if (is_super_admin($user_id)) {
        return true;
    }

    $user_perm = get_user_permissions($user_id);
    if (!empty($user_perm['full_access'])) {
        return true;
    }

    $moduleKey = strtolower(trim((string)$module));
    $actionKey = strtolower(trim((string)$action));
    $perms = $user_perm['permissions'] ?? [];

    if (!isset($perms[$moduleKey])) {
        if ($moduleKey === 'estimate-requests' && isset($perms['estimates'])) {
            $moduleKey = 'estimates';
        } elseif ($moduleKey === 'estimates' && isset($perms['estimate-requests'])) {
            $moduleKey = 'estimate-requests';
        } else {
            return false;
        }
    }

    return in_array($actionKey, array_map('strtolower', (array)$perms[$moduleKey]), true);
}

function has_permission($permission, $user_id = null): bool {
    if (strpos($permission, '.') !== false) {
        list($mod, $act) = explode('.', $permission, 2);
        return hasPermission($mod, $act, $user_id);
    }
    return hasPermission($permission, 'view', $user_id);
}

function canView($module, $user_id = null): bool { return hasPermission($module, 'view', $user_id); }
function canCreate($module, $user_id = null): bool { return hasPermission($module, 'create', $user_id); }
function canEdit($module, $user_id = null): bool { return hasPermission($module, 'edit', $user_id); }
function canDelete($module, $user_id = null): bool { return hasPermission($module, 'delete', $user_id); }
function canExport($module, $user_id = null): bool { return hasPermission($module, 'export', $user_id); }
function canDownload($module, $user_id = null): bool { return hasPermission($module, 'download', $user_id); }
function canSend($module, $user_id = null): bool { return hasPermission($module, 'send', $user_id); }
function canManage($module, $user_id = null): bool { return hasPermission($module, 'manage', $user_id); }

/**
 * Enforce Permission on Server-Side Page Route
 */
function requirePermission($module, $action = 'view'): void {
    if (!hasPermission($module, $action)) {
        http_response_code(403);
        $page_title = "Access Denied";
        $current_page = $module;
        include __DIR__ . '/header.php';
        include __DIR__ . '/sidebar.php';
        include __DIR__ . '/topbar.php';
        ?>
        <main class="main-content">
            <div class="page-container" style="display: flex; align-items: center; justify-content: center; min-height: 60vh;">
                <div style="max-width: 500px; text-align: center; background: #FFFFFF; border: 1px solid var(--border-card); border-radius: var(--radius-lg); padding: 40px 30px; box-shadow: var(--shadow-md);">
                    <div style="width: 56px; height: 56px; background-color: #FEF2F2; color: #EF4444; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                        <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    </div>
                    <h2 style="font-size: 20px; font-weight: 700; color: var(--text-heading); margin: 0 0 8px 0;">Access Denied</h2>
                    <p style="font-size: 13.5px; color: var(--text-secondary); margin: 0 0 24px 0; line-height: 1.5;">
                        You do not have permission to access the <strong><?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $module))); ?></strong> module. Please contact your system administrator to request access.
                    </p>
                    <a href="index.php" class="btn btn-primary btn-sm">
                        Return to Dashboard
                    </a>
                </div>
            </div>
        </main>
        <?php
        include __DIR__ . '/footer.php';
        exit();
    }
}

function require_permission($permission): void {
    if (strpos($permission, '.') !== false) {
        list($mod, $act) = explode('.', $permission, 2);
        requirePermission($mod, $act);
    } else {
        requirePermission($permission, 'view');
    }
}
