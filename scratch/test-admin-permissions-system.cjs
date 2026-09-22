/**
 * Automated Verification Script for Employee Permissions & Access Control System
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ADMIN_DIR = path.join(__dirname, '../admin');
const INCLUDES_DIR = path.join(ADMIN_DIR, 'includes');
const DATA_DIR = path.join(ADMIN_DIR, 'data');
const JS_DIR = path.join(ADMIN_DIR, 'assets/js');
const CSS_DIR = path.join(ADMIN_DIR, 'assets/css');

let totalTests = 0;
let passedTests = 0;

function assert(condition, message) {
    totalTests++;
    if (condition) {
        passedTests++;
        console.log(`  ✓ PASS: ${message}`);
    } else {
        console.error(`  ✕ FAIL: ${message}`);
    }
}

console.log('=== EMPLOYEE PERMISSIONS & ACCESS CONTROL VERIFICATION TESTS ===\n');

// 1. Check PHP Permission Engine File & Server-Side Storage
console.log('1. Testing Backend Permission Engine & Files...');
assert(fs.existsSync(path.join(INCLUDES_DIR, 'permissions.php')), 'permissions.php exists in admin/includes/');
assert(fs.existsSync(path.join(ADMIN_DIR, 'save-permissions.php')), 'save-permissions.php API endpoint exists in admin/');

const permPhp = fs.readFileSync(path.join(INCLUDES_DIR, 'permissions.php'), 'utf8');
assert(permPhp.includes('function hasPermission'), 'permissions.php defines hasPermission()');
assert(permPhp.includes('function canView'), 'permissions.php defines canView()');
assert(permPhp.includes('function canCreate'), 'permissions.php defines canCreate()');
assert(permPhp.includes('function canEdit'), 'permissions.php defines canEdit()');
assert(permPhp.includes('function canDelete'), 'permissions.php defines canDelete()');
assert(permPhp.includes('function canExport'), 'permissions.php defines canExport()');
assert(permPhp.includes('function save_user_permissions'), 'permissions.php defines save_user_permissions()');
assert(permPhp.includes('function requirePermission'), 'permissions.php defines requirePermission()');

// 2. Test Header & Sidebar Permission Enforcement
console.log('\n2. Testing Header & Sidebar Permission Integration...');
const headerPhp = fs.readFileSync(path.join(INCLUDES_DIR, 'header.php'), 'utf8');
assert(headerPhp.includes("require_once __DIR__ . '/permissions.php';"), 'header.php requires permissions.php');
assert(headerPhp.includes('window.userPermissions'), 'header.php exports window.userPermissions to frontend JS');
assert(headerPhp.includes('window.hasPermission'), 'header.php defines window.hasPermission JS helper');

const sidebarPhp = fs.readFileSync(path.join(INCLUDES_DIR, 'sidebar.php'), 'utf8');
assert(sidebarPhp.includes("if (!canView($item['id'])) continue;"), 'sidebar.php filters main & bottom navigation using canView()');
assert(sidebarPhp.includes("if (canView('help'))"), 'sidebar.php filters Help & Support using canView()');

// 3. Test Server-Side Page Route Protection Across All Admin Modules
console.log('\n3. Testing Server-Side Route Protection (requirePermission) Across Admin Pages...');
const pagesToTest = [
    { file: 'index.php', module: 'dashboard' },
    { file: 'leads.php', module: 'leads' },
    { file: 'pipeline.php', module: 'pipeline' },
    { file: 'contacts.php', module: 'contacts' },
    { file: 'companies.php', module: 'companies' },
    { file: 'subscriptions.php', module: 'subscriptions' },
    { file: 'expenses.php', module: 'expenses' },
    { file: 'invoices.php', module: 'invoices' },
    { file: 'contracts.php', module: 'contracts' },
    { file: 'estimate-requests.php', module: 'estimate-requests' },
    { file: 'tasks.php', module: 'tasks' },
    { file: 'calendar.php', module: 'calendar' },
    { file: 'inbox.php', module: 'inbox' },
    { file: 'reports.php', module: 'reports' },
    { file: 'team.php', module: 'team' },
    { file: 'settings.php', module: 'settings' },
    { file: 'help.php', module: 'help' }
];

pagesToTest.forEach(p => {
    const content = fs.readFileSync(path.join(ADMIN_DIR, p.file), 'utf8');
    assert(content.includes(`requirePermission('${p.module}')`), `${p.file} is protected with requirePermission('${p.module}')`);
});

// 4. Test Team Page HTML Structure
console.log('\n4. Testing Team Page (team.php) Permission UI Elements...');
const teamPhp = fs.readFileSync(path.join(ADMIN_DIR, 'team.php'), 'utf8');
assert(teamPhp.includes('Permissions &amp; Access') || teamPhp.includes('Permissions & Access'), 'team.php includes Permissions & Access section title');
assert(teamPhp.includes('id="permSummaryBadge"'), 'team.php includes Live Permission Summary badge');
assert(teamPhp.includes('id="btnToggleFullAccess"'), 'team.php includes Enable Full Access toggle button');
assert(teamPhp.includes('selectAllPermissions()'), 'team.php includes Select All action button');
assert(teamPhp.includes('clearAllPermissions()'), 'team.php includes Clear All action button');
assert(teamPhp.includes('id="permSearchInput"'), 'team.php includes Search permissions input box');
assert(teamPhp.includes('id="permListContainer"'), 'team.php includes dynamic permissions list container');

// 5. Test JavaScript Permission Logic & UI Handlers in team.js
console.log('\n5. Testing JavaScript Logic in team.js...');
const teamJs = fs.readFileSync(path.join(JS_DIR, 'team.js'), 'utf8');
assert(teamJs.includes('permissionModules:'), 'team.js contains permissionModules definition');
assert(teamJs.includes('initPermissionSection:'), 'team.js contains initPermissionSection helper');
assert(teamJs.includes('toggleModuleSwitch:'), 'team.js contains toggleModuleSwitch helper');
assert(teamJs.includes('toggleActionCheckbox:'), 'team.js contains toggleActionCheckbox helper');
assert(teamJs.includes('toggleFullAccess:'), 'team.js contains toggleFullAccess helper');
assert(teamJs.includes('selectAllPermissions:'), 'team.js contains selectAllPermissions helper');
assert(teamJs.includes('clearAllPermissions:'), 'team.js contains clearAllPermissions helper');
assert(teamJs.includes('save-permissions.php'), 'team.js sends permissions payload to save-permissions.php');
assert(teamJs.includes('getAccessBadgeHtml'), 'team.js includes getAccessBadgeHtml for member list access badges');

// 6. Test CSS Rules in team.css
console.log('\n6. Testing CSS Rules in team.css...');
const teamCss = fs.readFileSync(path.join(CSS_DIR, 'team.css'), 'utf8');
assert(teamCss.includes('.perm-section-container'), 'team.css defines .perm-section-container');
assert(teamCss.includes('.perm-summary-badge'), 'team.css defines .perm-summary-badge');
assert(teamCss.includes('.btn-full-access'), 'team.css defines .btn-full-access button');
assert(teamCss.includes('.perm-module-card'), 'team.css defines .perm-module-card');
assert(teamCss.includes('.perm-toggle-switch'), 'team.css defines .perm-toggle-switch switch control');

// 7. Execute PHP CLI Permission Logic Test
console.log('\n7. Executing PHP CLI Test on Backend Permission Engine...');
try {
    const phpTestScript = `<?php
require_once '${INCLUDES_DIR.replace(/\\/g, '/')}/permissions.php';

// Test 1: Full access user (TM-001)
$_SESSION['user_id'] = 'TM-001';
if (!hasPermission('leads', 'edit')) { echo "FAIL: TM-001 full access failed\\n"; exit(1); }

// Test 2: Save custom permissions for TM-999
save_user_permissions('TM-999', [
    'leads' => ['view', 'create'],
    'invoices' => ['view', 'download']
], false);

$_SESSION['user_id'] = 'TM-999';
if (!canView('leads')) { echo "FAIL: TM-999 cannot view leads\\n"; exit(1); }
if (!canCreate('leads')) { echo "FAIL: TM-999 cannot create leads\\n"; exit(1); }
if (canEdit('leads')) { echo "FAIL: TM-999 can edit leads when prohibited\\n"; exit(1); }
if (canDelete('leads')) { echo "FAIL: TM-999 can delete leads when prohibited\\n"; exit(1); }

if (!canView('invoices')) { echo "FAIL: TM-999 cannot view invoices\\n"; exit(1); }
if (!canDownload('invoices')) { echo "FAIL: TM-999 cannot download invoices\\n"; exit(1); }
if (canCreate('invoices')) { echo "FAIL: TM-999 can create invoices when prohibited\\n"; exit(1); }

if (canView('contracts')) { echo "FAIL: TM-999 can view contracts when prohibited\\n"; exit(1); }

echo "PHP_PERM_TEST_SUCCESS";
`;
    fs.writeFileSync(path.join(__dirname, 'temp_perm_test.php'), phpTestScript);
    const output = execSync('php scratch/temp_perm_test.php').toString();
    fs.unlinkSync(path.join(__dirname, 'temp_perm_test.php'));

    assert(output.includes('PHP_PERM_TEST_SUCCESS'), 'PHP Backend Permission Engine verified successfully via PHP CLI');
} catch (err) {
    console.error('PHP CLI execution error:', err.message);
    assert(false, 'PHP Backend Permission Engine CLI test failed');
}

console.log(`\n=== RESULTS: ${passedTests} / ${totalTests} TESTS PASSED ===\n`);
if (passedTests === totalTests) {
    console.log('SUCCESS: All Employee Permissions & Access Control requirements verified!');
    process.exit(0);
} else {
    console.error('FAILURE: Some tests failed.');
    process.exit(1);
}
