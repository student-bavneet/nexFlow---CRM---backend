<?php
/**
 * Migration: Add company_id to contacts table
 * - Adds company_id INT(10) UNSIGNED DEFAULT NULL
 * - Adds index idx_contacts_org_company (organization_id, company_id)
 * - Adds foreign key fk_contacts_company -> companies(id) ON DELETE SET NULL
 * - Updates existing contacts where company_name EXACTLY matches companies.name in same organization
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = nexflow_db();
    echo "Connected to database.\n";

    // 1. Check if company_id column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM contacts LIKE 'company_id'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$col) {
        echo "Adding company_id column to contacts...\n";
        $pdo->exec("ALTER TABLE contacts ADD COLUMN company_id INT(10) UNSIGNED DEFAULT NULL AFTER name");
        echo "Column company_id added successfully.\n";
    } else {
        echo "Column company_id already exists in contacts.\n";
    }

    // 2. Check if index exists
    $stmt = $pdo->query("SHOW INDEX FROM contacts WHERE Key_name = 'idx_contacts_org_company'");
    $idx = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$idx) {
        echo "Adding index idx_contacts_org_company...\n";
        $pdo->exec("ALTER TABLE contacts ADD INDEX idx_contacts_org_company (organization_id, company_id)");
        echo "Index added.\n";
    } else {
        echo "Index idx_contacts_org_company already exists.\n";
    }

    // 3. Check if foreign key exists
    $stmt = $pdo->prepare("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'contacts' 
          AND CONSTRAINT_NAME = 'fk_contacts_company'
    ");
    $stmt->execute();
    $fk = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fk) {
        echo "Adding foreign key fk_contacts_company...\n";
        $pdo->exec("ALTER TABLE contacts ADD CONSTRAINT fk_contacts_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL");
        echo "Foreign key added successfully.\n";
    } else {
        echo "Foreign key fk_contacts_company already exists.\n";
    }

    // 4. Exact, unambiguous mapping for existing data
    echo "Updating existing records where company_name strictly matches companies.name in same organization...\n";
    $upd = $pdo->exec("
        UPDATE contacts co 
        JOIN companies c ON c.organization_id = co.organization_id 
                         AND LOWER(TRIM(co.company_name)) = LOWER(TRIM(c.name))
        SET co.company_id = c.id 
        WHERE co.company_id IS NULL
    ");
    echo "Updated {$upd} contact records.\n";

    echo "Migration completed successfully!\n";
} catch (Throwable $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
    exit(1);
}
