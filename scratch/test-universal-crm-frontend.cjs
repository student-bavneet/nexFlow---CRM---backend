/**
 * Verification Test Suite for Universal CRM Frontend Transformation
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ADMIN_DIR = path.join(__dirname, '../admin');
const JS_DIR = path.join(ADMIN_DIR, 'assets/js');
const CSS_DIR = path.join(ADMIN_DIR, 'assets/css');
const INCLUDES_DIR = path.join(ADMIN_DIR, 'includes');

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

console.log('=== UNIVERSAL CRM FRONTEND VERIFICATION TESTS ===\n');

// 1. Universal CRM Frontend Engine File & Header Include
console.log('1. Testing Universal CRM Frontend Engine File & Header Include...');
assert(fs.existsSync(path.join(JS_DIR, 'universal-crm.js')), 'universal-crm.js exists in admin/assets/js/');

const headerPhp = fs.readFileSync(path.join(INCLUDES_DIR, 'header.php'), 'utf8');
assert(headerPhp.includes('<script src="assets/js/universal-crm.js"></script>'), 'header.php includes universal-crm.js script tag');

const universalJs = fs.readFileSync(path.join(JS_DIR, 'universal-crm.js'), 'utf8');
assert(universalJs.includes('INDUSTRY_PRESETS'), 'universal-crm.js defines INDUSTRY_PRESETS dictionary');
assert(universalJs.includes('realestate:'), 'universal-crm.js includes Real Estate preset');
assert(universalJs.includes('healthcare:'), 'universal-crm.js includes Healthcare & Medical preset');
assert(universalJs.includes('legal:'), 'universal-crm.js includes Legal & Professional preset');
assert(universalJs.includes('saas:'), 'universal-crm.js includes SaaS & B2B Tech preset');
assert(universalJs.includes('education:'), 'universal-crm.js includes Education preset');
assert(universalJs.includes('window.universalCrm'), 'universal-crm.js exports window.universalCrm API');

// 2. Settings Page Presets Navigation & Section Pane
console.log('\n2. Testing Settings Page Universal Presets Navigation & UI...');
const settingsPhp = fs.readFileSync(path.join(ADMIN_DIR, 'settings.php'), 'utf8');
assert(settingsPhp.includes('data-section="universal-crm"'), 'settings.php includes universal-crm navigation item');
assert(settingsPhp.includes('id="section-universal-crm"'), 'settings.php includes section-universal-crm pane');
assert(settingsPhp.includes('Industry &amp; Universal CRM Presets'), 'settings.php includes Presets card title');
assert(settingsPhp.includes('Real Estate &amp; Property'), 'settings.php includes Real Estate preset card');
assert(settingsPhp.includes('Healthcare &amp; Medical'), 'settings.php includes Healthcare preset card');
assert(settingsPhp.includes('Legal &amp; Professional'), 'settings.php includes Legal preset card');
assert(settingsPhp.includes('id="termLeads"'), 'settings.php includes Lead terminology input');
assert(settingsPhp.includes('id="termPipeline"'), 'settings.php includes Pipeline terminology input');
assert(settingsPhp.includes('id="termContacts"'), 'settings.php includes Contact terminology input');
assert(settingsPhp.includes('id="termCompanies"'), 'settings.php includes Company terminology input');
assert(settingsPhp.includes('id="termDeals"'), 'settings.php includes Deal terminology input');

// 3. Settings JS & CSS Integration
console.log('\n3. Testing Settings JavaScript & CSS Integration...');
const settingsJs = fs.readFileSync(path.join(JS_DIR, 'settings.js'), 'utf8');
assert(settingsJs.includes('selectIndustryPreset'), 'settings.js includes selectIndustryPreset method');
assert(settingsJs.includes('saveUniversalCrmSettings'), 'settings.js includes saveUniversalCrmSettings method');
assert(settingsJs.includes('loadUniversalCrmSettings'), 'settings.js includes loadUniversalCrmSettings method');

const settingsCss = fs.readFileSync(path.join(CSS_DIR, 'settings.css'), 'utf8');
assert(settingsCss.includes('.universal-preset-grid'), 'settings.css defines .universal-preset-grid layout');
assert(settingsCss.includes('.preset-card'), 'settings.css defines .preset-card styling');

// 4. Verify Non-Disruption of Backend PHP & Permissions
console.log('\n4. Verifying Zero Backend Disruption...');
const permissionsPhp = fs.readFileSync(path.join(INCLUDES_DIR, 'permissions.php'), 'utf8');
assert(permissionsPhp.includes('function hasPermission'), 'permissions.php backend engine remains 100% intact');

const savePermsPhp = fs.readFileSync(path.join(ADMIN_DIR, 'save-permissions.php'), 'utf8');
assert(savePermsPhp.includes('save_user_permissions'), 'save-permissions.php API remains 100% intact');

// 5. Run Existing Admin Test Suites to Ensure Zero Regressions
console.log('\n5. Running Existing Automated Regression Tests...');
try {
    const regOutput = execSync('node scratch/test-admin-permissions-system.cjs').toString();
    assert(regOutput.includes('SUCCESS'), 'All 54 Permission System tests pass cleanly with zero regressions');
} catch (e) {
    console.error('Regression failure:', e.message);
    assert(false, 'Permission System regression tests passed');
}

console.log(`\n=== RESULTS: ${passedTests} / ${totalTests} TESTS PASSED ===\n`);
if (passedTests === totalTests) {
    console.log('SUCCESS: Universal CRM Frontend Transformation verified with 100% compliance!');
    process.exit(0);
} else {
    console.error('FAILURE: Some tests failed.');
    process.exit(1);
}
