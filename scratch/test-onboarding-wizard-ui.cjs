/**
 * Automated Verification Script for Phase 1 - Universal CRM Onboarding Wizard UI
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

console.log('=== PHASE 1: UNIVERSAL CRM ONBOARDING WIZARD UI TESTS ===\n');

// 1. Check Onboarding Files Existence
console.log('1. Testing Onboarding Files Existence...');
assert(fs.existsSync(path.join(ADMIN_DIR, 'onboarding.php')), 'onboarding.php exists in admin/');
assert(fs.existsSync(path.join(JS_DIR, 'onboarding.js')), 'onboarding.js exists in admin/assets/js/');
assert(fs.existsSync(path.join(CSS_DIR, 'onboarding.css')), 'onboarding.css exists in admin/assets/css/');

// 2. Check Onboarding PHP Template Structure & Stepper Steps
console.log('\n2. Testing Onboarding Template HTML Structure...');
const obPhp = fs.readFileSync(path.join(ADMIN_DIR, 'onboarding.php'), 'utf8');
assert(obPhp.includes('WELCOME TO LEADFLOW CRM'), 'onboarding.php contains Welcome title');
assert(obPhp.includes('01 Business'), 'onboarding.php includes Step 01 Business');
assert(obPhp.includes('02 Industry'), 'onboarding.php includes Step 02 Industry');
assert(obPhp.includes('03 Modules'), 'onboarding.php includes Step 03 Modules');
assert(obPhp.includes('04 Customize'), 'onboarding.php includes Step 04 Customize');
assert(obPhp.includes('05 Finish'), 'onboarding.php includes Step 05 Finish');

// Step 1 Form Fields
assert(obPhp.includes('id="obBizName"'), 'Step 1 includes Business Name field');
assert(obPhp.includes('id="obBizEmail"'), 'Step 1 includes Business Email field');
assert(obPhp.includes('id="obBizPhone"'), 'Step 1 includes Business Phone field');
assert(obPhp.includes('id="obBizWebsite"'), 'Step 1 includes Website field');
assert(obPhp.includes('id="obBizCountry"'), 'Step 1 includes Country field');
assert(obPhp.includes('id="obBizState"'), 'Step 1 includes State field');
assert(obPhp.includes('id="obBizCity"'), 'Step 1 includes City field');
assert(obPhp.includes('id="obBizAddress"'), 'Step 1 includes Address field');
assert(obPhp.includes('id="obBizZip"'), 'Step 1 includes ZIP Code field');
assert(obPhp.includes('id="obBizCurrency"'), 'Step 1 includes Currency field');
assert(obPhp.includes('id="obBizTimezone"'), 'Step 1 includes Time Zone field');
assert(obPhp.includes('ob-logo-dropzone'), 'Step 1 includes Business Logo Upload UI');

// Step 2 & Step 3 Grids
assert(obPhp.includes('id="obIndustryGrid"'), 'Step 2 includes Industry selection grid container');
assert(obPhp.includes('id="obModuleGrid"'), 'Step 3 includes Module selection grid container');

// 3. Check JavaScript Controller & Data Constants
console.log('\n3. Testing JavaScript Onboarding Logic & Data Arrays...');
const obJs = fs.readFileSync(path.join(JS_DIR, 'onboarding.js'), 'utf8');
assert(obJs.includes('INDUSTRIES_DATA'), 'onboarding.js defines INDUSTRIES_DATA array');
assert(obJs.includes('MODULES_DATA'), 'onboarding.js defines MODULES_DATA array');

// Check all 19 industries
const requiredIndustries = [
    'realestate', 'software', 'marketing', 'education', 'healthcare',
    'finance', 'insurance', 'manufacturing', 'construction', 'consultancy',
    'travel', 'ecommerce', 'retail', 'hospitality', 'recruitment',
    'legal', 'freelance', 'agency', 'other'
];
requiredIndustries.forEach(ind => {
    assert(obJs.includes(`key: '${ind}'`), `INDUSTRIES_DATA includes industry option: ${ind}`);
});

// Check all 16 modules
const requiredModules = [
    'leads', 'contacts', 'companies', 'deals', 'projects', 'tasks',
    'calendar', 'proposals', 'estimates', 'contracts', 'invoices',
    'expenses', 'documents', 'support', 'reports', 'team'
];
requiredModules.forEach(mod => {
    assert(obJs.includes(`key: '${mod}'`), `MODULES_DATA includes module option: ${mod}`);
});

assert(obJs.includes('openCustomModuleModal'), 'onboarding.js includes + Create Custom Module feature');

// 4. Check Settings Page Integration Button
console.log('\n4. Testing Settings Page Integration...');
const settingsPhp = fs.readFileSync(path.join(ADMIN_DIR, 'settings.php'), 'utf8');
assert(settingsPhp.includes('href="onboarding.php"'), 'settings.php includes Launch Onboarding Wizard button');

// 5. Verify Zero Backend Disruption & Run Regression Tests
console.log('\n5. Verifying Zero Backend Disruption & Regression Tests...');
try {
    const regOutput = execSync('node scratch/test-universal-crm-frontend.cjs').toString();
    assert(regOutput.includes('SUCCESS'), 'All Universal CRM & Permission System regression tests pass cleanly');
} catch (e) {
    console.error('Regression error:', e.message);
    assert(false, 'Regression tests passed');
}

console.log(`\n=== RESULTS: ${passedTests} / ${totalTests} TESTS PASSED ===\n`);
if (passedTests === totalTests) {
    console.log('SUCCESS: Phase 1 Universal CRM Onboarding Wizard UI verified with 100% compliance!');
    process.exit(0);
} else {
    console.error('FAILURE: Some tests failed.');
    process.exit(1);
}
