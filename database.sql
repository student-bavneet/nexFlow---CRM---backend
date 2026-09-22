-- NexFlow CRM - Onboarding / Authentication Foundation
-- Import this file in phpMyAdmin before opening /admin/onboarding.php.

CREATE DATABASE IF NOT EXISTS `nexflow_crm` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nexflow_crm`;

CREATE TABLE IF NOT EXISTS `industries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(80) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `icon` VARCHAR(20) NOT NULL,
  `description` TEXT NOT NULL,
  `recommendation_title` VARCHAR(255) DEFAULT NULL,
  `recommendation_subtitle` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_industry_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(80) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `icon` VARCHAR(20) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_module_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `industry_modules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `industry_id` INT UNSIGNED NOT NULL,
  `module_id` INT UNSIGNED NOT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_industry_module` (`industry_id`,`module_id`),
  CONSTRAINT `fk_im_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_im_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `industry_terms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `industry_id` INT UNSIGNED NOT NULL,
  `leads_label` VARCHAR(120) NOT NULL,
  `pipeline_label` VARCHAR(120) NOT NULL,
  `contacts_label` VARCHAR(120) NOT NULL,
  `companies_label` VARCHAR(120) NOT NULL,
  `deals_label` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_industry_terms` (`industry_id`),
  CONSTRAINT `fk_terms_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `industry_pipeline_stages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `industry_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `win_rate_pct` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_industry_stage` (`industry_id`,`name`),
  KEY `idx_stage_industry` (`industry_id`),
  CONSTRAINT `fk_stage_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `organizations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(180) NOT NULL,
  `slug` VARCHAR(180) NOT NULL,
  `business_email` VARCHAR(180) NOT NULL,
  `phone` VARCHAR(60) DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `country` VARCHAR(120) DEFAULT NULL,
  `state_region` VARCHAR(120) DEFAULT NULL,
  `city` VARCHAR(120) DEFAULT NULL,
  `postal_code` VARCHAR(30) DEFAULT NULL,
  `street_address` VARCHAR(255) DEFAULT NULL,
  `currency` VARCHAR(30) NOT NULL DEFAULT 'USD ($)',
  `timezone` VARCHAR(80) NOT NULL DEFAULT 'UTC',
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `industry_id` INT UNSIGNED DEFAULT NULL,
  `industry_name` VARCHAR(180) DEFAULT NULL,
  `setup_completed` TINYINT(1) NOT NULL DEFAULT 0,
  `setup_completed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_org_slug` (`slug`),
  CONSTRAINT `fk_org_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(180) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'admin',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_email` (`email`),
  KEY `idx_user_org` (`organization_id`),
  CONSTRAINT `fk_user_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `organization_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `terminology_json` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_org_settings` (`organization_id`),
  CONSTRAINT `fk_settings_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `organization_modules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `module_id` INT UNSIGNED DEFAULT NULL,
  `module_key` VARCHAR(100) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `is_custom` TINYINT(1) NOT NULL DEFAULT 0,
  `custom_name` VARCHAR(150) DEFAULT NULL,
  `custom_description` VARCHAR(255) DEFAULT NULL,
  `custom_icon` VARCHAR(20) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_org_module` (`organization_id`,`module_key`),
  KEY `idx_org_module_id` (`module_id`),
  CONSTRAINT `fk_org_module_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_org_module_catalog` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pipeline_stages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `win_rate_pct` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pipeline_org` (`organization_id`),
  CONSTRAINT `fk_pipeline_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `custom_fields` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `object_name` VARCHAR(100) NOT NULL,
  `field_name` VARCHAR(150) NOT NULL,
  `field_type` VARCHAR(80) NOT NULL DEFAULT 'Short Text',
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_custom_fields_org` (`organization_id`),
  CONSTRAINT `fk_custom_fields_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed industries
INSERT INTO `industries` (`key`,`name`,`icon`,`description`,`recommendation_title`,`recommendation_subtitle`,`sort_order`) VALUES
('software','Technology & Software','💻','Software companies, IT companies, SaaS businesses and technology teams.','✨ Recommended Setup for Technology & Software','Pre-configured for SaaS, IT, and software tech teams.',1),
('services','Business & Professional Services','💼','Consultants, professional service providers, business advisors and client-based businesses.','✨ Recommended Setup for Professional Services','Pre-configured for consultants, advisors, and professional services.',2),
('agency','Agency','🎨','Digital agencies, creative agencies, marketing agencies and client-service agencies.','✨ Recommended Setup for Agency','Pre-configured for creative, digital, and marketing agencies.',3),
('realestate','Real Estate','🏡','Real estate agencies, property consultants and property businesses.','✨ Recommended Setup for Real Estate','Pre-configured for real estate agencies and property teams.',4),
('retail','E-commerce & Retail','🛒','Online stores, retail businesses and product-based businesses.','✨ Recommended Setup for E-commerce & Retail','Pre-configured for online stores and retail product sellers.',5),
('manufacturing','Manufacturing','🏭','Manufacturers, factories and production-based businesses.','✨ Recommended Setup for Manufacturing','Pre-configured for manufacturers and production suppliers.',6),
('construction','Construction','🏗️','Construction companies, contractors and project-based businesses.','✨ Recommended Setup for Construction','Pre-configured for contractors and project construction teams.',7),
('recruitment','Recruitment','🎯','Recruitment agencies, staffing companies and hiring businesses.','✨ Recommended Setup for Recruitment','Pre-configured for recruiters, hiring, and staffing agencies.',8),
('freelance','Freelancer','👤','Freelancers, independent professionals and solo service providers.','✨ Recommended Setup for Freelancers','Pre-configured for solo professionals and independent consultants.',9),
('service','Service Business','🔧','Businesses that provide services, appointments, field work or customer support.','✨ Recommended Setup for Service Business','Pre-configured for service providers, field work, and appointments.',10),
('other','Other / Custom Business','⚙️','Don\'t see your business? Start with a flexible CRM and choose the features you need.','✨ Flexible Custom CRM Setup','Pre-configured with standard essential modules.',11)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`),`icon`=VALUES(`icon`),`description`=VALUES(`description`),`recommendation_title`=VALUES(`recommendation_title`),`recommendation_subtitle`=VALUES(`recommendation_subtitle`),`sort_order`=VALUES(`sort_order`);

INSERT INTO `modules` (`key`,`name`,`icon`,`description`,`sort_order`) VALUES
('leads','Leads','🎯','Capture & manage potential customers',1),
('contacts','Contacts','👤','Individual client directory',2),
('companies','Companies','🏢','Business accounts & organizations',3),
('deals','Deals','💼','Pipeline deal tracking',4),
('projects','Projects','📁','Plan & manage client deliverables',5),
('tasks','Tasks','✅','To-do items & team tasking',6),
('calendar','Calendar','📅','Meetings & appointment scheduling',7),
('proposals','Proposals','📝','Client quotes & proposals',8),
('estimates','Estimates','📊','Cost estimates & request forms',9),
('contracts','Contracts','📄','Legal agreements & e-signatures',10),
('invoices','Invoices','💳','Create & track customer invoices',11),
('expenses','Expenses','💵','Business & project expenses',12),
('documents','Documents','📂','Shared file store & attachments',13),
('support','Support','🎧','Help desk & customer tickets',14),
('reports','Reports','📈','Analytics & performance dashboards',15),
('team','Team','👥','Employee access & permissions',16)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`),`icon`=VALUES(`icon`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`);

-- Industry terminology
INSERT INTO industry_terms (industry_id,leads_label,pipeline_label,contacts_label,companies_label,deals_label)
SELECT id,'Leads','Sales Pipeline','Contacts','Accounts','Deals' FROM industries WHERE `key`='software'
ON DUPLICATE KEY UPDATE leads_label=VALUES(leads_label),pipeline_label=VALUES(pipeline_label),contacts_label=VALUES(contacts_label),companies_label=VALUES(companies_label),deals_label=VALUES(deals_label);
INSERT INTO industry_terms (industry_id,leads_label,pipeline_label,contacts_label,companies_label,deals_label)
SELECT id,'Inquiries','Consulting Pipeline','Clients','Organizations','Engagements' FROM industries WHERE `key`='services'
ON DUPLICATE KEY UPDATE leads_label=VALUES(leads_label),pipeline_label=VALUES(pipeline_label),contacts_label=VALUES(contacts_label),companies_label=VALUES(companies_label),deals_label=VALUES(deals_label);
INSERT INTO industry_terms (industry_id,leads_label,pipeline_label,contacts_label,companies_label,deals_label)
SELECT id,'Leads','Agency Pipeline','Client Leads','Accounts','Campaigns' FROM industries WHERE `key`='agency'
ON DUPLICATE KEY UPDATE leads_label=VALUES(leads_label),pipeline_label=VALUES(pipeline_label),contacts_label=VALUES(contacts_label),companies_label=VALUES(companies_label),deals_label=VALUES(deals_label);
INSERT INTO industry_terms (industry_id,leads_label,pipeline_label,contacts_label,companies_label,deals_label)
SELECT id,'Buyers','Property Pipeline','Clients','Brokerages','Properties' FROM industries WHERE `key`='realestate'
ON DUPLICATE KEY UPDATE leads_label=VALUES(leads_label),pipeline_label=VALUES(pipeline_label),contacts_label=VALUES(contacts_label),companies_label=VALUES(companies_label),deals_label=VALUES(deals_label);
INSERT INTO industry_terms (industry_id,leads_label,pipeline_label,contacts_label,companies_label,deals_label)
SELECT id,'Shoppers','Order Fulfillment','Customers','Vendors','Orders' FROM industries WHERE `key`='retail'
ON DUPLICATE KEY UPDATE leads_label=VALUES(leads_label),pipeline_label=VALUES(pipeline_label),contacts_label=VALUES(contacts_label),companies_label=VALUES(companies_label),deals_label=VALUES(deals_label);
INSERT INTO industry_terms (industry_id,leads_label,pipeline_label,contacts_label,companies_label,deals_label)
SELECT id,'RFQ Leads','Production Pipeline','Procurement Leads','Distributors','Orders' FROM industries WHERE `key`='manufacturing'
ON DUPLICATE KEY UPDATE leads_label=VALUES(leads_label),pipeline_label=VALUES(pipeline_label),contacts_label=VALUES(contacts_label),companies_label=VALUES(companies_label),deals_label=VALUES(deals_label);
INSERT INTO industry_terms (industry_id,leads_label,pipeline_label,contacts_label,companies_label,deals_label)
SELECT id,'Bids','Project Pipeline','Subcontractors','General Contractors','Site Projects' FROM industries WHERE `key`='construction'
ON DUPLICATE KEY UPDATE leads_label=VALUES(leads_label),pipeline_label=VALUES(pipeline_label),contacts_label=VALUES(contacts_label),companies_label=VALUES(companies_label),deals_label=VALUES(deals_label);
INSERT INTO industry_terms (industry_id,leads_label,pipeline_label,contacts_label,companies_label,deals_label)
SELECT id,'Candidates','Hiring Pipeline','Job Seekers','Hiring Companies','Placements' FROM industries WHERE `key`='recruitment'
ON DUPLICATE KEY UPDATE leads_label=VALUES(leads_label),pipeline_label=VALUES(pipeline_label),contacts_label=VALUES(contacts_label),companies_label=VALUES(companies_label),deals_label=VALUES(deals_label);
INSERT INTO industry_terms (industry_id,leads_label,pipeline_label,contacts_label,companies_label,deals_label)
SELECT id,'Prospects','Gigs Pipeline','Clients','Client Accounts','Projects' FROM industries WHERE `key`='freelance'
ON DUPLICATE KEY UPDATE leads_label=VALUES(leads_label),pipeline_label=VALUES(pipeline_label),contacts_label=VALUES(contacts_label),companies_label=VALUES(companies_label),deals_label=VALUES(deals_label);
INSERT INTO industry_terms (industry_id,leads_label,pipeline_label,contacts_label,companies_label,deals_label)
SELECT id,'Service Leads','Work Order Pipeline','Clients','Service Accounts','Work Orders' FROM industries WHERE `key`='service'
ON DUPLICATE KEY UPDATE leads_label=VALUES(leads_label),pipeline_label=VALUES(pipeline_label),contacts_label=VALUES(contacts_label),companies_label=VALUES(companies_label),deals_label=VALUES(deals_label);
INSERT INTO industry_terms (industry_id,leads_label,pipeline_label,contacts_label,companies_label,deals_label)
SELECT id,'Leads','Sales Pipeline','Contacts','Companies','Deals' FROM industries WHERE `key`='other'
ON DUPLICATE KEY UPDATE leads_label=VALUES(leads_label),pipeline_label=VALUES(pipeline_label),contacts_label=VALUES(contacts_label),companies_label=VALUES(companies_label),deals_label=VALUES(deals_label);

-- Seed recommended modules for each industry
INSERT INTO industry_modules (industry_id,module_id,is_default,sort_order)
SELECT i.id,m.id,1,FIELD(m.`key`,'leads','contacts','companies','deals','projects','tasks','calendar','proposals','estimates','contracts','invoices','expenses','documents','support','reports','team')
FROM industries i JOIN modules m ON m.`key` IN ('leads','contacts','companies','deals','projects','tasks','calendar','proposals','estimates','contracts','invoices','documents','support','reports') WHERE i.`key`='software'
ON DUPLICATE KEY UPDATE is_default=VALUES(is_default),sort_order=VALUES(sort_order);

INSERT INTO industry_modules (industry_id,module_id,is_default,sort_order)
SELECT i.id,m.id,1,FIELD(m.`key`,'leads','contacts','companies','deals','projects','tasks','calendar','proposals','estimates','contracts','invoices','expenses','documents','support','reports','team') FROM industries i JOIN modules m ON m.`key` IN ('leads','contacts','companies','deals','projects','tasks','calendar','proposals','estimates','contracts','invoices','expenses','documents','reports') WHERE i.`key`='services' ON DUPLICATE KEY UPDATE is_default=VALUES(is_default),sort_order=VALUES(sort_order);

INSERT INTO industry_modules (industry_id,module_id,is_default,sort_order)
SELECT i.id,m.id,1,FIELD(m.`key`,'leads','contacts','companies','deals','projects','tasks','calendar','proposals','estimates','contracts','invoices','expenses','documents','support','reports','team') FROM industries i JOIN modules m ON m.`key` IN ('leads','contacts','companies','deals','projects','tasks','calendar','proposals','estimates','contracts','invoices','expenses','documents','reports') WHERE i.`key`='agency' ON DUPLICATE KEY UPDATE is_default=VALUES(is_default),sort_order=VALUES(sort_order);

INSERT INTO industry_modules (industry_id,module_id,is_default,sort_order)
SELECT i.id,m.id,1,FIELD(m.`key`,'leads','contacts','companies','deals','projects','tasks','calendar','proposals','estimates','contracts','invoices','documents','support','reports','team') FROM industries i JOIN modules m ON m.`key` IN ('leads','contacts','companies','deals','projects','tasks','calendar','proposals','estimates','contracts','invoices','documents','reports') WHERE i.`key`='realestate' ON DUPLICATE KEY UPDATE is_default=VALUES(is_default),sort_order=VALUES(sort_order);

INSERT INTO industry_modules (industry_id,module_id,is_default,sort_order)
SELECT i.id,m.id,1,FIELD(m.`key`,'leads','contacts','companies','tasks','calendar','invoices','expenses','support','reports','team') FROM industries i JOIN modules m ON m.`key` IN ('leads','contacts','companies','tasks','calendar','invoices','expenses','support','reports') WHERE i.`key`='retail' ON DUPLICATE KEY UPDATE is_default=VALUES(is_default),sort_order=VALUES(sort_order);

INSERT INTO industry_modules (industry_id,module_id,is_default,sort_order)
SELECT i.id,m.id,1,FIELD(m.`key`,'leads','contacts','companies','deals','projects','tasks','calendar','estimates','contracts','invoices','expenses','reports','team') FROM industries i JOIN modules m ON m.`key` IN ('leads','contacts','companies','deals','projects','tasks','calendar','estimates','contracts','invoices','expenses','reports') WHERE i.`key`='manufacturing' ON DUPLICATE KEY UPDATE is_default=VALUES(is_default),sort_order=VALUES(sort_order);

INSERT INTO industry_modules (industry_id,module_id,is_default,sort_order)
SELECT i.id,m.id,1,FIELD(m.`key`,'leads','contacts','companies','deals','projects','tasks','calendar','estimates','contracts','invoices','documents','reports','team') FROM industries i JOIN modules m ON m.`key` IN ('leads','contacts','companies','deals','projects','tasks','calendar','estimates','contracts','invoices','documents','reports') WHERE i.`key`='construction' ON DUPLICATE KEY UPDATE is_default=VALUES(is_default),sort_order=VALUES(sort_order);

INSERT INTO industry_modules (industry_id,module_id,is_default,sort_order)
SELECT i.id,m.id,1,FIELD(m.`key`,'leads','contacts','companies','deals','tasks','calendar','proposals','contracts','invoices','documents','reports','team') FROM industries i JOIN modules m ON m.`key` IN ('leads','contacts','companies','deals','tasks','calendar','proposals','contracts','invoices','documents','reports') WHERE i.`key`='recruitment' ON DUPLICATE KEY UPDATE is_default=VALUES(is_default),sort_order=VALUES(sort_order);

INSERT INTO industry_modules (industry_id,module_id,is_default,sort_order)
SELECT i.id,m.id,1,FIELD(m.`key`,'leads','contacts','companies','deals','projects','tasks','calendar','proposals','estimates','contracts','invoices','expenses','documents','reports','team') FROM industries i JOIN modules m ON m.`key` IN ('leads','contacts','companies','deals','projects','tasks','calendar','proposals','estimates','contracts','invoices','expenses','documents','reports') WHERE i.`key`='freelance' ON DUPLICATE KEY UPDATE is_default=VALUES(is_default),sort_order=VALUES(sort_order);

INSERT INTO industry_modules (industry_id,module_id,is_default,sort_order)
SELECT i.id,m.id,1,FIELD(m.`key`,'leads','contacts','companies','deals','tasks','calendar','estimates','contracts','invoices','documents','reports','team') FROM industries i JOIN modules m ON m.`key` IN ('leads','contacts','companies','deals','tasks','calendar','estimates','contracts','invoices','documents','reports') WHERE i.`key`='service' ON DUPLICATE KEY UPDATE is_default=VALUES(is_default),sort_order=VALUES(sort_order);

INSERT INTO industry_modules (industry_id,module_id,is_default,sort_order)
SELECT i.id,m.id,1,FIELD(m.`key`,'leads','contacts','companies','deals','projects','tasks','calendar','invoices','reports','team') FROM industries i JOIN modules m ON m.`key` IN ('leads','contacts','companies','deals','projects','tasks','calendar','invoices','reports') WHERE i.`key`='other' ON DUPLICATE KEY UPDATE is_default=VALUES(is_default),sort_order=VALUES(sort_order);

-- Seed pipeline stages
INSERT INTO industry_pipeline_stages (industry_id,name,win_rate_pct,sort_order)
SELECT id, 'Lead Captured', 20, 1 FROM industries WHERE `key`='software'
UNION ALL
SELECT id, 'Discovery Call', 40, 2 FROM industries WHERE `key`='software'
UNION ALL
SELECT id, 'Product Demo', 60, 3 FROM industries WHERE `key`='software'
UNION ALL
SELECT id, 'Trial / POC', 80, 4 FROM industries WHERE `key`='software'
UNION ALL
SELECT id, 'Proposal Sent', 100, 5 FROM industries WHERE `key`='software'
UNION ALL
SELECT id, 'Closed Won', 100, 6 FROM industries WHERE `key`='software'
UNION ALL
SELECT id, 'Inquiry Received', 20, 1 FROM industries WHERE `key`='services'
UNION ALL
SELECT id, 'Initial Consult', 40, 2 FROM industries WHERE `key`='services'
UNION ALL
SELECT id, 'Proposal Delivered', 60, 3 FROM industries WHERE `key`='services'
UNION ALL
SELECT id, 'Negotiation', 80, 4 FROM industries WHERE `key`='services'
UNION ALL
SELECT id, 'Retainer Signed', 100, 5 FROM industries WHERE `key`='services'
UNION ALL
SELECT id, 'Project Active', 100, 6 FROM industries WHERE `key`='services'
UNION ALL
SELECT id, 'New Pitch', 20, 1 FROM industries WHERE `key`='agency'
UNION ALL
SELECT id, 'Briefing Call', 40, 2 FROM industries WHERE `key`='agency'
UNION ALL
SELECT id, 'Proposal Sent', 60, 3 FROM industries WHERE `key`='agency'
UNION ALL
SELECT id, 'Contract Signed', 80, 4 FROM industries WHERE `key`='agency'
UNION ALL
SELECT id, 'Onboarding', 100, 5 FROM industries WHERE `key`='agency'
UNION ALL
SELECT id, 'Active Client', 100, 6 FROM industries WHERE `key`='agency'
UNION ALL
SELECT id, 'New Inquiry', 20, 1 FROM industries WHERE `key`='realestate'
UNION ALL
SELECT id, 'Contacted', 40, 2 FROM industries WHERE `key`='realestate'
UNION ALL
SELECT id, 'Site Visit Scheduled', 60, 3 FROM industries WHERE `key`='realestate'
UNION ALL
SELECT id, 'Negotiation', 80, 4 FROM industries WHERE `key`='realestate'
UNION ALL
SELECT id, 'Booking Fee', 100, 5 FROM industries WHERE `key`='realestate'
UNION ALL
SELECT id, 'Closed Deal', 100, 6 FROM industries WHERE `key`='realestate'
UNION ALL
SELECT id, 'New Order', 20, 1 FROM industries WHERE `key`='retail'
UNION ALL
SELECT id, 'Payment Verified', 40, 2 FROM industries WHERE `key`='retail'
UNION ALL
SELECT id, 'Processing', 60, 3 FROM industries WHERE `key`='retail'
UNION ALL
SELECT id, 'Shipped', 80, 4 FROM industries WHERE `key`='retail'
UNION ALL
SELECT id, 'Delivered', 100, 5 FROM industries WHERE `key`='retail'
UNION ALL
SELECT id, 'Completed', 100, 6 FROM industries WHERE `key`='retail'
UNION ALL
SELECT id, 'RFQ Received', 20, 1 FROM industries WHERE `key`='manufacturing'
UNION ALL
SELECT id, 'Cost Estimate', 40, 2 FROM industries WHERE `key`='manufacturing'
UNION ALL
SELECT id, 'Sample Approved', 60, 3 FROM industries WHERE `key`='manufacturing'
UNION ALL
SELECT id, 'Contract Signed', 80, 4 FROM industries WHERE `key`='manufacturing'
UNION ALL
SELECT id, 'In Production', 100, 5 FROM industries WHERE `key`='manufacturing'
UNION ALL
SELECT id, 'Dispatched', 100, 6 FROM industries WHERE `key`='manufacturing'
UNION ALL
SELECT id, 'Bid Invited', 20, 1 FROM industries WHERE `key`='construction'
UNION ALL
SELECT id, 'Estimate Submitted', 40, 2 FROM industries WHERE `key`='construction'
UNION ALL
SELECT id, 'Contract Awarded', 60, 3 FROM industries WHERE `key`='construction'
UNION ALL
SELECT id, 'Site Setup', 80, 4 FROM industries WHERE `key`='construction'
UNION ALL
SELECT id, 'Construction Active', 100, 5 FROM industries WHERE `key`='construction'
UNION ALL
SELECT id, 'Handover', 100, 6 FROM industries WHERE `key`='construction'
UNION ALL
SELECT id, 'Sourced Candidate', 20, 1 FROM industries WHERE `key`='recruitment'
UNION ALL
SELECT id, 'Initial Screen', 40, 2 FROM industries WHERE `key`='recruitment'
UNION ALL
SELECT id, 'Client Interview', 60, 3 FROM industries WHERE `key`='recruitment'
UNION ALL
SELECT id, 'Shortlisted', 80, 4 FROM industries WHERE `key`='recruitment'
UNION ALL
SELECT id, 'Job Offer', 100, 5 FROM industries WHERE `key`='recruitment'
UNION ALL
SELECT id, 'Placed / Hired', 100, 6 FROM industries WHERE `key`='recruitment'
UNION ALL
SELECT id, 'Initial Inquiry', 20, 1 FROM industries WHERE `key`='freelance'
UNION ALL
SELECT id, 'Estimate Sent', 40, 2 FROM industries WHERE `key`='freelance'
UNION ALL
SELECT id, 'Deposit Paid', 60, 3 FROM industries WHERE `key`='freelance'
UNION ALL
SELECT id, 'Work In Progress', 80, 4 FROM industries WHERE `key`='freelance'
UNION ALL
SELECT id, 'Review / Edits', 100, 5 FROM industries WHERE `key`='freelance'
UNION ALL
SELECT id, 'Final Invoice Paid', 100, 6 FROM industries WHERE `key`='freelance'
UNION ALL
SELECT id, 'Service Request', 20, 1 FROM industries WHERE `key`='service'
UNION ALL
SELECT id, 'Estimate Provided', 40, 2 FROM industries WHERE `key`='service'
UNION ALL
SELECT id, 'Appointment Booked', 60, 3 FROM industries WHERE `key`='service'
UNION ALL
SELECT id, 'Technician Assigned', 80, 4 FROM industries WHERE `key`='service'
UNION ALL
SELECT id, 'Service Completed', 100, 5 FROM industries WHERE `key`='service'
UNION ALL
SELECT id, 'Invoiced', 100, 6 FROM industries WHERE `key`='service'
UNION ALL
SELECT id, 'New Inquiry', 20, 1 FROM industries WHERE `key`='other'
UNION ALL
SELECT id, 'Contacted', 40, 2 FROM industries WHERE `key`='other'
UNION ALL
SELECT id, 'Qualified', 60, 3 FROM industries WHERE `key`='other'
UNION ALL
SELECT id, 'Proposal Sent', 80, 4 FROM industries WHERE `key`='other'
UNION ALL
SELECT id, 'Negotiation', 100, 5 FROM industries WHERE `key`='other'
UNION ALL
SELECT id, 'Closed Won', 100, 6 FROM industries WHERE `key`='other'
ON DUPLICATE KEY UPDATE name=VALUES(name),win_rate_pct=VALUES(win_rate_pct),sort_order=VALUES(sort_order);
