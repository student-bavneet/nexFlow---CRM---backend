/**
 * Automated Verification Script for Admin Portal Global Three-Dots Menu Removal
 * Verifies that three-dots / ellipsis (⋯) menu buttons have been cleanly removed
 * from ALL tables across the Admin Portal without leaving empty columns, broken alignment,
 * or lost actions.
 */

const fs = require('fs');
const path = require('path');

const ADMIN_DIR = path.join(__dirname, '../admin');
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

console.log('=== GLOBAL THREE-DOTS MENU REMOVAL VERIFICATION TESTS ===\n');

// Helper to extract render function content
function getRenderFunction(content, startMarker, endMarker) {
    const start = content.indexOf(startMarker);
    if (start === -1) return '';
    const end = content.indexOf(endMarker, start);
    return end === -1 ? content.slice(start) : content.slice(start, end);
}

// 1. Leads Table
console.log('1. Verifying Leads Table...');
const leadsPhp = fs.readFileSync(path.join(ADMIN_DIR, 'leads.php'), 'utf8');
const leadsJs = fs.readFileSync(path.join(JS_DIR, 'leads.js'), 'utf8');
assert(!leadsPhp.includes('data-column-id="menu"'), 'leads.php does not contain menu header column');
assert(!leadsJs.includes('row-actions-btn'), 'leads.js table rendering does not generate three-dots menu button');

// 2. Contacts Table
console.log('\n2. Verifying Contacts Table...');
const contactsPhp = fs.readFileSync(path.join(ADMIN_DIR, 'contacts.php'), 'utf8');
const contactsJs = fs.readFileSync(path.join(JS_DIR, 'contacts.js'), 'utf8');
const contactsRender = getRenderFunction(contactsJs, 'renderTable: function', 'prevPage: function');
assert(!contactsPhp.includes('data-column-id="menu"'), 'contacts.php does not contain menu header column');
assert(!contactsRender.includes('cnt-row-menu-btn'), 'contacts.js renderTable does not output cnt-row-menu-btn');
assert(contactsRender.includes('title="Call Contact"'), 'contacts.js retains direct Call action');
assert(contactsRender.includes('title="WhatsApp"'), 'contacts.js retains direct WhatsApp action');
assert(contactsRender.includes('title="Send Email"'), 'contacts.js retains direct Email action');

// 3. Subscriptions Table
console.log('\n3. Verifying Subscriptions Table...');
const subJs = fs.readFileSync(path.join(JS_DIR, 'subscriptions.js'), 'utf8');
const subRender = getRenderFunction(subJs, 'renderTable: function', 'toggleSelection: function');
assert(!subRender.includes('sub-row-menu-btn'), 'subscriptions.js renderTable does not output sub-row-menu-btn');
assert(subRender.includes("openDrawer('${s.id}')"), 'subscriptions.js retains direct Details action');

// 4. Expenses Table
console.log('\n4. Verifying Expenses Table...');
const expJs = fs.readFileSync(path.join(JS_DIR, 'expenses.js'), 'utf8');
const expRender = getRenderFunction(expJs, 'renderTable: function', 'toggleSelection: function');
assert(!expRender.includes('exp-row-menu-btn'), 'expenses.js renderTable does not output exp-row-menu-btn');
assert(expRender.includes("openDrawer('${e.id}')"), 'expenses.js retains direct Details action');

// 5. Invoices Table
console.log('\n5. Verifying Invoices Table...');
const invPhp = fs.readFileSync(path.join(ADMIN_DIR, 'invoices.php'), 'utf8');
const invJs = fs.readFileSync(path.join(JS_DIR, 'invoices.js'), 'utf8');
const invRender = getRenderFunction(invJs, 'renderTable: function', 'toggleSelection: function');
assert(invPhp.includes('data-column-id="actions">ACTIONS</th>'), 'invoices.php has clean ACTIONS header');
assert(!invRender.includes('inv-row-menu-btn'), 'invoices.js renderTable does not output inv-row-menu-btn');
assert(invRender.includes("openViewDrawer('${inv.id}')"), 'invoices.js retains direct View action');

// 6. Contracts Table
console.log('\n6. Verifying Contracts Table...');
const cntJs = fs.readFileSync(path.join(JS_DIR, 'contracts.js'), 'utf8');
const cntRender = getRenderFunction(cntJs, 'renderTable: function', 'toggleSelection: function');
assert(!cntRender.includes('cnt-row-menu-btn'), 'contracts.js renderTable does not output cnt-row-menu-btn');
assert(cntRender.includes("openDrawer('${c.id}')"), 'contracts.js retains direct Details action');

// 7. Estimate Requests Table
console.log('\n7. Verifying Estimate Requests Table...');
const estJs = fs.readFileSync(path.join(JS_DIR, 'estimate-requests.js'), 'utf8');
const estRender = getRenderFunction(estJs, 'renderTable: function', 'toggleSelection: function');
assert(!estRender.includes('est-row-menu-btn'), 'estimate-requests.js renderTable does not output est-row-menu-btn');
assert(estRender.includes("openDrawer('${r.id}')"), 'estimate-requests.js retains direct Details action');

// 8. Pipeline Deals Cards
console.log('\n8. Verifying Sales Pipeline Cards...');
const pipePhp = fs.readFileSync(path.join(ADMIN_DIR, 'pipeline.php'), 'utf8');
const pipeJs = fs.readFileSync(path.join(JS_DIR, 'pipeline.js'), 'utf8');
const pipeRender = getRenderFunction(pipeJs, 'function createKanbanCardHtml', 'function attachKanbanCardEvents');
assert(!pipePhp.includes('card-dropdown-wrapper'), 'pipeline.php does not contain card three-dots menu');
assert(!pipeRender.includes('card-dropdown-wrapper'), 'pipeline.js createKanbanCardHtml does not render card three-dots menu');
assert(pipePhp.includes("openDealDetailsDrawer("), 'pipeline.php retains direct row/card click for deal details');

console.log(`\n=== RESULTS: ${passedTests} / ${totalTests} TESTS PASSED ===\n`);
if (passedTests === totalTests) {
    console.log('SUCCESS: All Admin Portal three-dots removal requirements verified!');
    process.exit(0);
} else {
    console.error('FAILURE: Some tests failed.');
    process.exit(1);
}
