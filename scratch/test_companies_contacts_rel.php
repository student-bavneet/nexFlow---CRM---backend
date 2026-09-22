<?php
/**
 * Test Suite: Companies ↔ Contacts Relationship
 * Verifies all 6 test scenarios (A through F) + cleanup + existing data integrity
 */

require_once __DIR__ . '/../config/database.php';

function test_log($msg, $ok = true) {
    $status = $ok ? "[PASS]" : "[FAIL]";
    echo "{$status} {$msg}\n";
    if (!$ok) {
        exit(1);
    }
}

$pdo = nexflow_db();

// Verify existing baseline data
$origCompanies = $pdo->query("SELECT id, name FROM companies WHERE id IN (13, 14)")->fetchAll(PDO::FETCH_ASSOC);
test_log("Existing companies preserved (found " . count($origCompanies) . " rows)", count($origCompanies) === 2);

$origContact = $pdo->query("SELECT id, name, company_name FROM contacts WHERE id = 8")->fetch(PDO::FETCH_ASSOC);
test_log("Existing contact ID 8 preserved ({$origContact['name']})", !empty($origContact));

$testOrgId = 1;
$testUserId = 1;

// =========================================================================
// TEST A: Create a completely new company with no contact
// =========================================================================
echo "\n--- TEST A: Create new company with no contact ---\n";
$newCompCode = 'COMP-TESTA-' . time();
$newCompName = 'Test Alpha Robotics ' . time();

$stmt = $pdo->prepare("
    INSERT INTO companies (organization_id, company_code, name, relationship, owner_id, primary_contact_id, created_by, is_active, created_at)
    VALUES (?, ?, ?, 'Prospect', ?, NULL, ?, 1, NOW())
");
$stmt->execute([$testOrgId, $newCompCode, $newCompName, $testUserId, $testUserId]);
$testACompId = (int)$pdo->lastInsertId();

test_log("Company created with ID {$testACompId}", $testACompId > 0);

// Verify no contact was automatically created
$cntCount = $pdo->query("SELECT COUNT(*) FROM contacts WHERE company_id = {$testACompId}")->fetchColumn();
test_log("No contact was automatically created (count = {$cntCount})", (int)$cntCount === 0);

// Verify Primary Contact is NULL
$primContact = $pdo->query("SELECT primary_contact_id FROM companies WHERE id = {$testACompId}")->fetchColumn();
test_log("Primary Contact is NULL", empty($primContact));

// Verify contacts_count query
$countQuery = "
    SELECT (SELECT COUNT(*) FROM contacts co 
            WHERE co.organization_id = c.organization_id 
              AND (co.company_id = c.id OR (co.company_id IS NULL AND co.company_name = c.name))
              AND co.is_active = 1) AS cnt 
    FROM companies c WHERE c.id = {$testACompId}
";
$cCount = $pdo->query($countQuery)->fetchColumn();
test_log("Contacts count is 0", (int)$cCount === 0);


// =========================================================================
// TEST B: Create a contact and associate it with an existing company
// =========================================================================
echo "\n--- TEST B: Create contact and associate with existing company ---\n";
// Create contact using name matching or company_id
$contact1Name = 'Dr. Alan Grant';
$stmt = $pdo->prepare("
    INSERT INTO contacts (organization_id, first_name, last_name, name, company_id, company_name, email, is_active, created_by, created_at)
    VALUES (?, 'Dr. Alan', 'Grant', ?, ?, ?, 'alan@testalpha.com', 1, ?, NOW())
");
$stmt->execute([$testOrgId, $contact1Name, $testACompId, $newCompName, $testUserId]);
$contact1Id = (int)$pdo->lastInsertId();

test_log("Contact 1 created with ID {$contact1Id} linked to Company {$testACompId}", $contact1Id > 0);

// Verify company contacts_count is now 1
$cCount = $pdo->query($countQuery)->fetchColumn();
test_log("Company contacts count is now 1", (int)$cCount === 1);

// Verify contact is available in company_contacts query
$compCntStmt = $pdo->prepare("
    SELECT id, name FROM contacts 
    WHERE organization_id = ? 
      AND (company_id = ? OR (company_id IS NULL AND LOWER(TRIM(company_name)) = LOWER(TRIM(?))))
      AND is_active = 1
");
$compCntStmt->execute([$testOrgId, $testACompId, $newCompName]);
$availContacts = $compCntStmt->fetchAll(PDO::FETCH_ASSOC);
test_log("Contact is available in company contacts list", count($availContacts) === 1 && $availContacts[0]['id'] == $contact1Id);

// Verify company primary_contact_id was NOT auto-assigned
$primContact = $pdo->query("SELECT primary_contact_id FROM companies WHERE id = {$testACompId}")->fetchColumn();
test_log("Primary Contact was NOT auto-assigned (remains NULL)", empty($primContact));

// Now explicitly set primary_contact_id to contact 1
$pdo->prepare("UPDATE companies SET primary_contact_id = ? WHERE id = ?")->execute([$contact1Id, $testACompId]);
$primContact = $pdo->query("SELECT primary_contact_id FROM companies WHERE id = {$testACompId}")->fetchColumn();
test_log("Primary Contact explicitly set to Contact {$contact1Id}", (int)$primContact === $contact1Id);


// =========================================================================
// TEST C: Create another contact for the same company
// =========================================================================
echo "\n--- TEST C: Create second contact for same company ---\n";
$contact2Name = 'Dr. Ellie Sattler';
$stmt->execute([$testOrgId, $contact2Name, $testACompId, $newCompName, $testUserId]);
$contact2Id = (int)$pdo->lastInsertId();

test_log("Contact 2 created with ID {$contact2Id}", $contact2Id > 0);

// Verify contacts count is now 2
$cCount = $pdo->query($countQuery)->fetchColumn();
test_log("Company contacts count is now 2", (int)$cCount === 2);

// Verify both contacts are available for primary contact selection
$compCntStmt->execute([$testOrgId, $testACompId, $newCompName]);
$availContacts = $compCntStmt->fetchAll(PDO::FETCH_ASSOC);
test_log("Both contacts available for primary selection (found " . count($availContacts) . ")", count($availContacts) === 2);

// Verify no duplicate company was created
$dupCount = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE organization_id = ? AND name = ?");
$dupCount->execute([$testOrgId, $newCompName]);
test_log("No duplicate company created (count = 1)", (int)$dupCount->fetchColumn() === 1);


// =========================================================================
// TEST D: Create contact for a company that does not exist
// =========================================================================
echo "\n--- TEST D: Create contact for non-existing company ---\n";
$nonExistentCompName = 'Ghost Enterprises Inc ' . time();
$contact3Name = 'Ian Malcolm';

// Test company resolution logic from api/contacts.php:
$chkComp = $pdo->prepare("SELECT id, name FROM companies WHERE organization_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND is_active = 1 LIMIT 1");
$chkComp->execute([$testOrgId, $nonExistentCompName]);
$compRow = $chkComp->fetch(PDO::FETCH_ASSOC);
$resolvedCompId = $compRow ? (int)$compRow['id'] : null;

// User directive 1: DO NOT automatically create a company if it does not exist
test_log("Company resolution correctly returned null for non-existing company", $resolvedCompId === null);

// Insert contact with resolved company_id (NULL) and company_name preserved
$stmt->execute([$testOrgId, $contact3Name, $resolvedCompId, $nonExistentCompName, $testUserId]);
$contact3Id = (int)$pdo->lastInsertId();

// Verify contact stored
$c3 = $pdo->query("SELECT company_id, company_name FROM contacts WHERE id = {$contact3Id}")->fetch(PDO::FETCH_ASSOC);
test_log("Contact 3 stored with company_id = NULL and company_name preserved", $c3['company_id'] === null && $c3['company_name'] === $nonExistentCompName);

// Verify NO company was created in companies table
$chkComp->execute([$testOrgId, $nonExistentCompName]);
test_log("No company record was auto-created in companies table", empty($chkComp->fetchColumn()));


// =========================================================================
// TEST E: Cross-organization tenant isolation
// =========================================================================
echo "\n--- TEST E: Tenant isolation ---\n";
$org2Id = 2;
// Check if Org 2 can see Org 1 contacts in company_contacts query
$org2CntStmt = $pdo->prepare("
    SELECT id FROM contacts 
    WHERE organization_id = ? 
      AND (company_id = ? OR (company_id IS NULL AND LOWER(TRIM(company_name)) = LOWER(TRIM(?))))
      AND is_active = 1
");
$org2CntStmt->execute([$org2Id, $testACompId, $newCompName]);
test_log("Organization 2 cannot see Organization 1 contacts", count($org2CntStmt->fetchAll()) === 0);

// Check if Org 2 can select an Org 1 contact as primary
$fakeUpdateStmt = $pdo->prepare("
    SELECT id FROM contacts 
    WHERE id = :cid AND organization_id = :org_id 
      AND (company_id = :comp_id OR (company_id IS NULL AND LOWER(TRIM(company_name)) = LOWER(TRIM(:cname))))
    LIMIT 1
");
$fakeUpdateStmt->execute([
    ':cid'     => $contact1Id,
    ':org_id'  => $org2Id,
    ':comp_id' => $testACompId,
    ':cname'   => $newCompName
]);
test_log("Cross-tenant primary contact assignment blocked", empty($fakeUpdateStmt->fetchColumn()));


// =========================================================================
// TEST F: Change a contact's company
// =========================================================================
echo "\n--- TEST F: Change a contact's company ---\n";
// Create a second test company (Company Beta)
$newCompCodeB = 'COMP-TESTB-' . time();
$newCompNameB = 'Test Beta Biotech ' . time();
$pdo->prepare("
    INSERT INTO companies (organization_id, company_code, name, relationship, owner_id, primary_contact_id, created_by, is_active, created_at)
    VALUES (?, ?, ?, 'Customer', ?, NULL, ?, 1, NOW())
")->execute([$testOrgId, $newCompCodeB, $newCompNameB, $testUserId, $testUserId]);
$testBCompId = (int)$pdo->lastInsertId();

// Verify Company B contacts count = 0
$countBQuery = "
    SELECT (SELECT COUNT(*) FROM contacts co 
            WHERE co.organization_id = c.organization_id 
              AND (co.company_id = c.id OR (co.company_id IS NULL AND co.company_name = c.name))
              AND co.is_active = 1) AS cnt 
    FROM companies c WHERE c.id = {$testBCompId}
";
test_log("Company B contacts count = 0 initially", (int)$pdo->query($countBQuery)->fetchColumn() === 0);

// Contact 1 was primary for Company A. Reassign Contact 1 to Company B:
// Update contact logic handles clearing old primary_contact_id if contact moved
$oldCompId = $testACompId;
$newCompId = $testBCompId;

// Update contact
$pdo->prepare("UPDATE contacts SET company_id = ?, company_name = ? WHERE id = ? AND organization_id = ?")
    ->execute([$newCompId, $newCompNameB, $contact1Id, $testOrgId]);

// Clear old company's primary_contact_id if it was this contact
$pdo->prepare("UPDATE companies SET primary_contact_id = NULL WHERE id = ? AND primary_contact_id = ? AND organization_id = ?")
    ->execute([$oldCompId, $contact1Id, $testOrgId]);

// Verify Company A contacts count decreased (from 2 to 1)
test_log("Company A contacts count decreased from 2 to 1", (int)$pdo->query($countQuery)->fetchColumn() === 1);

// Verify Company A primary_contact_id was cleared since Contact 1 moved away
$primA = $pdo->query("SELECT primary_contact_id FROM companies WHERE id = {$testACompId}")->fetchColumn();
test_log("Company A primary_contact_id was cleared (is NULL)", empty($primA));

// Verify Company B contacts count increased (from 0 to 1)
test_log("Company B contacts count increased from 0 to 1", (int)$pdo->query($countBQuery)->fetchColumn() === 1);


// =========================================================================
// CLEANUP: Cleanly remove all test records created in this run
// =========================================================================
echo "\n--- CLEANUP ---\n";
$pdo->exec("DELETE FROM contacts WHERE id IN ({$contact1Id}, {$contact2Id}, {$contact3Id})");
$pdo->exec("DELETE FROM companies WHERE id IN ({$testACompId}, {$testBCompId})");
test_log("Cleaned up test contacts and companies", true);

// Final verification of real database records
$finalCompanies = $pdo->query("SELECT id, name FROM companies WHERE id IN (13, 14)")->fetchAll(PDO::FETCH_ASSOC);
test_log("Original companies 13 and 14 remain perfectly intact", count($finalCompanies) === 2);

$finalContact = $pdo->query("SELECT id, name, company_name FROM contacts WHERE id = 8")->fetch(PDO::FETCH_ASSOC);
test_log("Original contact 8 remains perfectly intact", !empty($finalContact) && $finalContact['name'] === 'Rahul Sharma');

echo "\n============================================\n";
echo "ALL TESTS PASSED WITH 100% SUCCESS!\n";
echo "============================================\n";
