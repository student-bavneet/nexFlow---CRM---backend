/**
 * Automated Verification Script for Admin Portal Filter UI Standardization
 * Tests that all Filter buttons open compact anchored popovers relative to the button
 * (matching the reference Leads filter UI), and that modal/drawer filters have been converted.
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

console.log('=== ADMIN PORTAL FILTER UI STANDARDIZATION TESTS ===\n');

// 1. Test CSS Shared Rules in components.css
console.log('1. Testing Shared Filter Popover CSS Rules in components.css...');
const componentsCss = fs.readFileSync(path.join(CSS_DIR, 'components.css'), 'utf8');

assert(componentsCss.includes('.filter-popover'), 'components.css defines base .filter-popover class');
assert(componentsCss.includes('.leads-filter-popover'), 'components.css supports .leads-filter-popover');
assert(componentsCss.includes('.pipeline-filter-popover'), 'components.css supports .pipeline-filter-popover');
assert(componentsCss.includes('.companies-filter-popover'), 'components.css supports .companies-filter-popover');
assert(componentsCss.includes('.tasks-filter-popover'), 'components.css supports .tasks-filter-popover');
assert(componentsCss.includes('width: 360px') || componentsCss.includes('width: 380px'), 'Filter popovers have standard compact width (~360px)');
assert(componentsCss.includes('@keyframes filterPopoverFadeIn'), 'Filter popovers have smooth entrance animation');

// 2. Test Leads Filter (Reference)
console.log('\n2. Testing Reference Leads Filter UI in leads.php...');
const leadsPhp = fs.readFileSync(path.join(ADMIN_DIR, 'leads.php'), 'utf8');
assert(leadsPhp.includes('id="btnLeadFilter"'), 'leads.php has Filter button');
assert(leadsPhp.includes('class="leads-filter-popover"'), 'leads.php uses anchored leads-filter-popover');
assert(leadsPhp.includes('onclick="toggleLeadFilterPopover(event)"'), 'leads.php calls popover toggle on click');

// 3. Test Sales Pipeline Filter (Converted from modal to popover)
console.log('\n3. Testing Pipeline Filter UI in pipeline.php...');
const pipelinePhp = fs.readFileSync(path.join(ADMIN_DIR, 'pipeline.php'), 'utf8');
const pipelineJs = fs.readFileSync(path.join(JS_DIR, 'pipeline.js'), 'utf8');

assert(pipelinePhp.includes('id="pipelineFilterBtn"'), 'pipeline.php has Filter button');
assert(pipelinePhp.includes('class="pipeline-filter-popover"'), 'pipeline.php uses anchored pipeline-filter-popover panel');
assert(!pipelinePhp.includes('id="pipelineFilterModal"'), 'pipeline.php no longer uses centered modal for filtering');
assert(pipelineJs.includes('togglePipelineFilterPanel'), 'pipeline.js implements togglePipelineFilterPanel');
assert(pipelineJs.includes('closePipelineFilterPanel'), 'pipeline.js implements closePipelineFilterPanel');

// 4. Test Companies Filter (Converted from drawer to popover)
console.log('\n4. Testing Companies Filter UI in companies.php...');
const companiesPhp = fs.readFileSync(path.join(ADMIN_DIR, 'companies.php'), 'utf8');
const companiesJs = fs.readFileSync(path.join(JS_DIR, 'companies.js'), 'utf8');

assert(companiesPhp.includes('id="btnCompanyFilter"'), 'companies.php has Filter button');
assert(companiesPhp.includes('class="companies-filter-popover"'), 'companies.php uses anchored companies-filter-popover panel');
assert(!companiesPhp.includes('class="companies-filter-drawer" id="companiesFilterDrawer"'), 'companies.php no longer uses side drawer for filtering');
assert(companiesJs.includes('toggleFilterPopover:'), 'companies.js implements toggleFilterPopover');
assert(companiesJs.includes('closeFilterPopover:'), 'companies.js implements closeFilterPopover');

// 5. Test Tasks Filter (Converted from drawer to popover)
console.log('\n5. Testing Tasks Filter UI in tasks.php...');
const tasksPhp = fs.readFileSync(path.join(ADMIN_DIR, 'tasks.php'), 'utf8');
const tasksJs = fs.readFileSync(path.join(JS_DIR, 'tasks.js'), 'utf8');

assert(tasksPhp.includes('id="btnTaskFilter"'), 'tasks.php has Filter button');
assert(tasksPhp.includes('class="tasks-filter-popover"'), 'tasks.php uses anchored tasks-filter-popover panel');
assert(!tasksPhp.includes('class="tasks-filter-drawer" id="tasksFilterDrawer"'), 'tasks.php no longer uses side drawer for filtering');
assert(tasksJs.includes('toggleFilterPopover:'), 'tasks.js implements toggleFilterPopover');
assert(tasksJs.includes('closeFilterPopover:'), 'tasks.js implements closeFilterPopover');

// 6. Test Subscriptions Filter
console.log('\n6. Testing Subscriptions Filter UI in subscriptions.php...');
const subscriptionsPhp = fs.readFileSync(path.join(ADMIN_DIR, 'subscriptions.php'), 'utf8');
const subscriptionsJs = fs.readFileSync(path.join(JS_DIR, 'subscriptions.js'), 'utf8');

assert(subscriptionsPhp.includes('id="btnSubFilter"'), 'subscriptions.php has Filter button');
assert(subscriptionsPhp.includes('class="subscriptions-filter-popover"'), 'subscriptions.php uses anchored subscriptions-filter-popover panel');
assert(subscriptionsJs.includes('toggleFilterPopover:'), 'subscriptions.js implements toggleFilterPopover');

// 7. Test Expenses Filter
console.log('\n7. Testing Expenses Filter UI in expenses.php...');
const expensesPhp = fs.readFileSync(path.join(ADMIN_DIR, 'expenses.php'), 'utf8');
const expensesJs = fs.readFileSync(path.join(JS_DIR, 'expenses.js'), 'utf8');

assert(expensesPhp.includes('id="btnExpFilter"'), 'expenses.php has Filter button');
assert(expensesPhp.includes('class="expenses-filter-popover"'), 'expenses.php uses anchored expenses-filter-popover panel');
assert(expensesJs.includes('toggleFilterPopover:'), 'expenses.js implements toggleFilterPopover');

// 8. Test Contracts Filter
console.log('\n8. Testing Contracts Filter UI in contracts.php...');
const contractsPhp = fs.readFileSync(path.join(ADMIN_DIR, 'contracts.php'), 'utf8');
const contractsJs = fs.readFileSync(path.join(JS_DIR, 'contracts.js'), 'utf8');

assert(contractsPhp.includes('id="btnCntFilter"'), 'contracts.php has Filter button');
assert(contractsPhp.includes('class="contracts-filter-popover"'), 'contracts.php uses anchored contracts-filter-popover panel');
assert(contractsJs.includes('toggleFilterPopover:'), 'contracts.js implements toggleFilterPopover');

// 9. Test Estimate Requests Filter
console.log('\n9. Testing Estimate Requests Filter UI in estimate-requests.php...');
const estimateRequestsPhp = fs.readFileSync(path.join(ADMIN_DIR, 'estimate-requests.php'), 'utf8');
const estimateRequestsJs = fs.readFileSync(path.join(JS_DIR, 'estimate-requests.js'), 'utf8');

assert(estimateRequestsPhp.includes('id="btnEstReqFilter"'), 'estimate-requests.php has Filter button');
assert(estimateRequestsPhp.includes('class="estimate-requests-filter-popover"'), 'estimate-requests.php uses anchored estimate-requests-filter-popover panel');
assert(estimateRequestsJs.includes('toggleFilterPopover:'), 'estimate-requests.js implements toggleFilterPopover');

console.log(`\n=== RESULTS: ${passedTests} / ${totalTests} TESTS PASSED ===\n`);
if (passedTests === totalTests) {
    console.log('SUCCESS: All Admin Portal Filter UI standardization requirements verified!');
    process.exit(0);
} else {
    console.error('FAILURE: Some tests failed.');
    process.exit(1);
}
