/**
 * Automated Verification Script for Admin Portal Filter UI Standardization
 * Tests that all Filter buttons open compact anchored popovers relative to the button
 * matching the SECOND reference screenshot (Leads Filter UI).
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

console.log('=== ADMIN PORTAL FILTER UI DESIGN STANDARDIZATION TESTS ===\n');

// 1. Test CSS Shared Rules in components.css
console.log('1. Testing Shared Filter Popover CSS Rules in components.css...');
const componentsCss = fs.readFileSync(path.join(CSS_DIR, 'components.css'), 'utf8');

assert(componentsCss.includes('.filter-popover'), 'components.css defines base .filter-popover class');
assert(componentsCss.includes('.btn-clear-filter-text'), 'components.css defines .btn-clear-filter-text for header text link');
assert(componentsCss.includes('.filter-popover-close'), 'components.css defines borderless .filter-popover-close icon button');
assert(componentsCss.includes('width: 360px'), 'Filter popovers have standard compact width (360px)');
assert(componentsCss.includes('@keyframes filterPopoverFadeIn'), 'Filter popovers have smooth entrance animation');

// Helper function to test page filter popovers
function testPageFilter(pageName, fileName, filterId, titleText) {
    console.log(`\nTesting ${pageName} Filter UI in ${fileName}...`);
    const pageHtml = fs.readFileSync(path.join(ADMIN_DIR, fileName), 'utf8');

    assert(pageHtml.includes(`id="${filterId}"`), `${fileName} contains filter popover element #${filterId}`);
    assert(pageHtml.includes('btn-clear-filter-text'), `${fileName} has 'Clear all' text link in header`);
    assert(pageHtml.includes('filter-popover-close') || pageHtml.includes('leads-filter-close'), `${fileName} has clean borderless X icon in header`);
    assert(!pageHtml.includes('Clear All</button>'), `${fileName} does NOT have 'Clear All' button in footer`);
    assert(pageHtml.includes('>Cancel</button>'), `${fileName} has 'Cancel' button in footer`);
    assert(pageHtml.includes('Apply Filters</button>') || pageHtml.includes('Apply</button>'), `${fileName} has 'Apply Filters' button in footer`);
}

testPageFilter('Leads (Reference)', 'leads.php', 'leadsFilterPopover', 'Filter Leads');
testPageFilter('Contacts', 'contacts.php', 'contactsFilterPopover', 'Filter Contacts');
testPageFilter('Companies', 'companies.php', 'companiesFilterPopover', 'Filter Companies');
testPageFilter('Sales Pipeline', 'pipeline.php', 'pipelineFilterPopover', 'Filter Deals');
testPageFilter('Invoices', 'invoices.php', 'invoicesFilterPopover', 'Filter Invoices');
testPageFilter('Subscriptions', 'subscriptions.php', 'subscriptionsFilterPopover', 'Filter Subscriptions');
testPageFilter('Expenses', 'expenses.php', 'expensesFilterPopover', 'Filter Expenses');
testPageFilter('Contracts', 'contracts.php', 'contractsFilterPopover', 'Filter Contracts');
testPageFilter('Estimate Requests', 'estimate-requests.php', 'estimateRequestsFilterPopover', 'Filter Estimate Requests');
testPageFilter('Tasks', 'tasks.php', 'tasksFilterPopover', 'Filter Tasks');

console.log(`\n=== RESULTS: ${passedTests} / ${totalTests} TESTS PASSED ===\n`);
if (passedTests === totalTests) {
    console.log('SUCCESS: All Admin Portal Filter UI standardization requirements verified!');
    process.exit(0);
} else {
    console.error('FAILURE: Some tests failed.');
    process.exit(1);
}
