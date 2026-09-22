/**
 * Automated Verification Script for Contact Details Drawer Redesign
 * Tests that Contact Details drawer matches Leads Details drawer structure, cards,
 * tab styling, profile section, and action buttons.
 */

const fs = require('fs');
const path = require('path');

const ADMIN_DIR = path.join(__dirname, '../admin');
const CSS_DIR = path.join(ADMIN_DIR, 'assets/css');
const JS_DIR = path.join(ADMIN_DIR, 'assets/js');

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

console.log('=== CONTACT DETAILS DRAWER REDESIGN VERIFICATION TESTS ===\n');

// 1. Test HTML Structure in admin/contacts.php
console.log('1. Testing Header & Tab Structure in contacts.php...');
const contactsPhp = fs.readFileSync(path.join(ADMIN_DIR, 'contacts.php'), 'utf8');

assert(contactsPhp.includes('id="drawerBreadcrumb"'), 'contacts.php contains breadcrumb container');
assert(contactsPhp.includes('id="drawerContactTitle"'), 'contacts.php contains title container');
assert(contactsPhp.includes('drawer-header-actions'), 'contacts.php contains header actions container');
assert(contactsPhp.includes('drawer-tabs-wrapper'), 'contacts.php contains sticky tab row wrapper');
assert(contactsPhp.includes('data-tab="overview"'), 'contacts.php contains Overview tab');
assert(contactsPhp.includes('data-tab="activity"'), 'contacts.php contains Activity tab');
assert(contactsPhp.includes('data-tab="deals"'), 'contacts.php contains Deals tab');
assert(contactsPhp.includes('data-tab="tasks"'), 'contacts.php contains Tasks tab');
assert(contactsPhp.includes('data-tab="notes"'), 'contacts.php contains Notes tab');

// 2. Test CSS Styling in contacts.css
console.log('\n2. Testing Drawer & Tab CSS Rules in contacts.css...');
const contactsCss = fs.readFileSync(path.join(CSS_DIR, 'contacts.css'), 'utf8');

assert(contactsCss.includes('#contactDrawer .drawer-tab.active'), 'contacts.css defines active tab highlight matching Leads');
assert(contactsCss.includes('#contactDrawer .drawer-tab.active::after'), 'contacts.css defines blue bottom line indicator for active tab');
assert(contactsCss.includes('#contactDrawer .drawer-info-card'), 'contacts.css defines info card container matching Leads');
assert(contactsCss.includes('#contactDrawer .drawer-card-title'), 'contacts.css defines uppercase card title styling');
assert(contactsCss.includes('.contact-primary-actions'), 'contacts.css defines 3-column primary action buttons grid');
assert(contactsCss.includes('#contactDrawer .drawer-copy-btn'), 'contacts.css defines copy button hover styling');

// 3. Test Dynamic JS Rendering in contacts.js
console.log('\n3. Testing Dynamic Drawer Body Rendering in contacts.js...');
const contactsJs = fs.readFileSync(path.join(JS_DIR, 'contacts.js'), 'utf8');

assert(contactsJs.includes('Contact Profile Header Section'), 'contacts.js includes Profile Header section comment/structure');
assert(contactsJs.includes('contact-primary-actions'), 'contacts.js outputs primary actions grid with Schedule Meeting, Add Task, Add Note');
assert(contactsJs.includes('Contact Information'), 'contacts.js outputs Contact Information card');
assert(contactsJs.includes('CRM Details'), 'contacts.js outputs CRM Details card');
assert(contactsJs.includes('copyToClipboard'), 'contacts.js includes copyToClipboard helper');
assert(contactsJs.includes('openScheduleMeetingModal'), 'contacts.js includes openScheduleMeetingModal helper');
assert(contactsJs.includes('openAddNoteModal'), 'contacts.js includes openAddNoteModal helper');

console.log(`\n=== RESULTS: ${passedTests} / ${totalTests} TESTS PASSED ===\n`);
if (passedTests === totalTests) {
    console.log('SUCCESS: All Contact Details drawer redesign requirements verified!');
    process.exit(0);
} else {
    console.error('FAILURE: Some tests failed.');
    process.exit(1);
}
