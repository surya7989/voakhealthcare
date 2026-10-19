# Complete MySQL Database Schema Design & Migration Architecture

**Document Reference:** `docs/phase-02/05_DATABASE_SCHEMA_DESIGN.md`  
**Phase:** Phase 2 of 31 — Complete System Architecture  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Executing Role:** Senior MySQL Database Architect & Database Reliability Engineer  
**Baseline Authority:** Accepted Phase 0 Domain Model (`docs/phase-00/08_DATABASE_DOMAIN_MODEL.md`) and Accepted Phase 1 Mappings (`docs/phase-01/11_API_AND_DATABASE_REQUIREMENT_MAPPING.md`)  
**Status:** **AUTHORITATIVE ARCHITECTURAL SPECIFICATION** (Design Only — SQL Migration Execution Deferred to Phase 5)

---

## 1. Relational Database Engine Standards

The persistence layer is architected as a relational database running on MySQL 8.x under Hostinger shared hosting:
- **Storage Engine:** Strictly `InnoDB` across 100% of tables (enforcing ACID compliance, row-level locking, and foreign key integrity).
- **Default Character Set & Collation:** `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci` (supporting complete 4-byte Unicode characters, emojis, and international healthcare symbols).
- **Primary Keys:** `BIGINT UNSIGNED AUTO_INCREMENT` on high-volume transactional tables; `INT UNSIGNED AUTO_INCREMENT` on standard catalog tables.
- **Timestamps:** Standardized `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP` and `updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` with UTC timezone storage.
- **Soft Deletion Policy:** Applied strictly to operational and editorial records (`deleted_at TIMESTAMP NULL DEFAULT NULL`) to preserve audit history and relational integrity.

---

## 2. Reconciled Database Entity Inventory (28 Tables)

Following the rigorous reconciliation of Phase 0 baseline entities with Phase 1 functional specifications, the database architecture expands from the initial 18 tables to **exactly 28 cohesive relational entities**, systematically categorized into 7 functional clusters. Every entity normalizes real relational data and enforces strict data lifecycle and security boundaries:

| Cluster | Table Name | Purpose & Domain Responsibility | Row Volume Tier |
| :--- | :--- | :--- | :---: |
| **I. Auth & RBAC** | 1. `users` | Administrative staff identity, credentials, lockout counters | Low (< 100) |
| | 2. `roles` | 5 approved administrative roles | Static (5) |
| | 3. `permissions` | 45 granular canonical permission action keys | Static (45) |
| | 4. `role_permissions` | Many-to-many role-to-permission mapping junction | Static (~180) |
| | 5. `user_roles` | User-to-role assignment ledger | Low (< 200) |
| | 6. `admin_sessions` | Active session tokens, IP addresses, 15m idle timeouts | Medium |
| | 7. `admin_mfa_secrets` | Encrypted RFC 6238 TOTP secrets & hashed backup codes | Low (< 100) |
| | 8. `password_resets` | Single-use cryptographically hashed password reset tokens | Low |
| **II. CMS & Pages** | 9. `pages` | Static public page copy (About, Why Choose Voak, etc.) | Low (22) |
| | 10. `content_revisions` | Historical JSON snapshots for rollback & concurrency diffs | Medium |
| | 11. `homepage_configs` | Hero headline, bento grid layout ordering, proof toggles | Single-Row |
| **III. Services & Ind** | 12. `services` | 6 B2B healthcare service pillars, deliverables, SLAs | Low (6–20) |
| | 13. `industries` | 6 healthcare sector verticals, regulatory burden specs | Low (6–20) |
| | 14. `service_industry_mappings`| Many-to-many relational junction between services & sectors | Low (36) |
| **IV. Media & Assets** | 15. `media_assets` | Public WebP image library, dimensions, mandatory alt text | Medium |
| **V. CRM Inbound** | 16. `contact_inquiries` | Inbound contact submissions, status FSM, internal notes | High |
| | 17. `partnership_inquiries` | Healthcare alliance intake, tiering, milestone pipeline | Medium |
| | 18. `proposals` | Enterprise 4-step RFP wizard submissions, budget bands | Medium |
| | 19. `proposal_services` | Selected service pillars associated with each RFP submission | Medium |
| **VI. Recruitment & Content** | 20. `job_postings` | Careers vacancies, department, employment type, status | Low (20–100) |
| | 21. `career_applications`| Candidate submissions, resume path, recruitment FSM | High |
| | 22. `articles` | Insights whitepapers, calculated reading times, tags | Medium (100–500) |
| | 23. `case_studies` | Problem-Intervention-Outcome proof, consent verified flag | Low (20–50) |
| | 24. `faqs` | Procurement & compliance Q&A accordions, categories | Low (50) |
| **VII. System & Ops** | 25. `email_queue` | **Durable transactional email outbox**, claiming, retries | High |
| | 26. `url_redirects` | **Automated 301 redirect engine**, slug rename mapping | Medium |
| | 27. `system_settings` | Dynamic configuration variables (`legal_entity_name`, etc.) | Low (50) |
| | 28. `audit_logs` | **Immutable SHA-256 row hash chained audit ledger** | Very High |

*(Total Entities: Exactly 28 discrete schema tables providing 100% relational coverage for all Phase 1 requirements).*

---

## 3. Detailed Entity Schema Specifications (All 28 Tables)

### 3.1 Cluster I: Identity, Authentication & RBAC

#### 1. `users`
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    lockout_until TIMESTAMP NULL DEFAULT NULL,
    password_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_users_email (email),
    INDEX idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 2. `roles`
```sql
CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'Super Admin', 'Website Administrator', etc.
    slug VARCHAR(50) NOT NULL UNIQUE, -- 'super_admin', 'website_admin', etc.
    description VARCHAR(255) NOT NULL,
    is_system TINYINT(1) UNSIGNED NOT NULL DEFAULT 1, -- Prevents accidental deletion
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3. `permissions`
```sql
CREATE TABLE permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE, -- 'proposals.download_tender', 'applications.download_cv', etc.
    module VARCHAR(50) NOT NULL, -- 'auth', 'cms', 'proposals', 'careers', etc.
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 4. `role_permissions`
```sql
CREATE TABLE role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_perm FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 5. `user_roles`
```sql
CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    assigned_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_assigner FOREIGN KEY (assigned_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 6. `admin_sessions`
```sql
CREATE TABLE admin_sessions (
    id VARCHAR(64) PRIMARY KEY, -- SHA-256 of session token
    user_id BIGINT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    csrf_token_hash CHAR(64) NOT NULL,
    last_activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL, -- Absolute 8-hour expiry
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_sessions_user_activity (user_id, last_activity_at),
    INDEX idx_sessions_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 7. `admin_mfa_secrets`
```sql
CREATE TABLE admin_mfa_secrets (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    encrypted_secret VARCHAR(255) NOT NULL, -- AES-256-GCM encrypted TOTP secret
    backup_codes_json JSON NOT NULL, -- Hashed backup recovery codes
    is_confirmed TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_mfa_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 8. `password_resets`
```sql
CREATE TABLE password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE, -- SHA-256 hashed single-use token
    ip_address VARCHAR(45) NOT NULL,
    expires_at TIMESTAMP NOT NULL, -- 1-hour expiry
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pwd_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_pwd_resets_hash (token_hash),
    INDEX idx_pwd_resets_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3.2 Cluster II: CMS, Pages & Revisions

#### 9. `pages`
```sql
CREATE TABLE pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    meta_title VARCHAR(150) NOT NULL,
    meta_description VARCHAR(255) NOT NULL,
    content_json JSON NOT NULL,
    is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    published_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pages_slug_published (slug, is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 10. `content_revisions`
```sql
CREATE TABLE content_revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL, -- 'pages', 'services', 'industries', 'articles'
    entity_id BIGINT UNSIGNED NOT NULL,
    editor_user_id BIGINT UNSIGNED NOT NULL,
    payload_snapshot JSON NOT NULL,
    revision_note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_revisions_editor FOREIGN KEY (editor_user_id) REFERENCES users (id),
    INDEX idx_revisions_entity (entity_type, entity_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 11. `homepage_configs`
```sql
CREATE TABLE homepage_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hero_headline VARCHAR(255) NOT NULL,
    hero_subheadline TEXT NOT NULL,
    hero_primary_cta_text VARCHAR(50) NOT NULL,
    hero_primary_cta_url VARCHAR(191) NOT NULL,
    hero_secondary_cta_text VARCHAR(50) NOT NULL,
    hero_secondary_cta_url VARCHAR(191) NOT NULL,
    bento_order_json JSON NOT NULL, -- Card arrangement and layout toggles
    proof_metrics_json JSON NOT NULL, -- Verified client metrics
    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    updated_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_homepage_editor FOREIGN KEY (updated_by_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3.3 Cluster III: Services, Industries & Mappings

#### 12. `services`
```sql
CREATE TABLE services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    tagline VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    sla_metrics_json JSON NOT NULL,
    deliverables_json JSON NOT NULL,
    is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_services_slug_published (slug, is_published),
    INDEX idx_services_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 13. `industries`
```sql
CREATE TABLE industries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    summary TEXT NOT NULL,
    regulatory_burden_specs JSON NOT NULL,
    pain_points_json JSON NOT NULL,
    is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_industries_slug_published (slug, is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 14. `service_industry_mappings`
```sql
CREATE TABLE service_industry_mappings (
    service_id INT UNSIGNED NOT NULL,
    industry_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (service_id, industry_id),
    CONSTRAINT fk_sim_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE,
    CONSTRAINT fk_sim_industry FOREIGN KEY (industry_id) REFERENCES industries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3.4 Cluster IV: Media & Asset Library

#### 15. `media_assets`
```sql
CREATE TABLE media_assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(100) NOT NULL UNIQUE, -- Content hash filename
    file_path VARCHAR(255) NOT NULL, -- Public media path (media/)
    file_size_bytes INT UNSIGNED NOT NULL,
    mime_type VARCHAR(50) NOT NULL, -- image/webp, image/png, etc.
    dimensions VARCHAR(30) NOT NULL, -- '1920x1080'
    alt_text VARCHAR(255) NOT NULL, -- Mandatory accessibility alt text
    uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id),
    INDEX idx_media_mime (mime_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3.5 Cluster V: Inbound CRM, Partnerships & Proposals

#### 16. `contact_inquiries`
```sql
CREATE TABLE contact_inquiries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(30) NOT NULL UNIQUE, -- 'CNT-YYYYMMDD-XXXX'
    full_name VARCHAR(150) NOT NULL,
    organization_name VARCHAR(150) NOT NULL,
    corporate_email VARCHAR(191) NOT NULL,
    contact_phone VARCHAR(25) NOT NULL, -- International E.164
    inquiry_details TEXT NOT NULL,
    status ENUM('NEW', 'TRIAGED', 'IN_REVIEW', 'CONTACTED', 'RESOLVED', 'CLOSED', 'SPAM') NOT NULL DEFAULT 'NEW',
    assigned_user_id BIGINT UNSIGNED NULL,
    internal_notes TEXT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inquiries_assignee FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_inquiries_status (status, created_at),
    INDEX idx_inquiries_ref (reference_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 17. `partnership_inquiries`
```sql
CREATE TABLE partnership_inquiries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(30) NOT NULL UNIQUE, -- 'PTN-YYYYMMDD-XXXX'
    organization_name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(150) NOT NULL,
    corporate_email VARCHAR(191) NOT NULL,
    contact_phone VARCHAR(25) NOT NULL, -- International E.164
    alliance_type VARCHAR(50) NOT NULL, -- 'Clinical Placement', 'Health System Partner', etc.
    proposed_scope TEXT NOT NULL,
    status ENUM('NEW', 'EVALUATION', 'DISCUSSION', 'LEGAL_REVIEW', 'ACTIVE_ALLIANCE', 'DECLINED') NOT NULL DEFAULT 'NEW',
    assigned_user_id BIGINT UNSIGNED NULL,
    internal_notes TEXT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_partnerships_assignee FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_partnerships_status (status, created_at),
    INDEX idx_partnerships_ref (reference_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 18. `proposals` (RFP Submissions)
```sql
CREATE TABLE proposals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(30) NOT NULL UNIQUE, -- 'RFP-YYYYMMDD-XXXX'
    organization_name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(150) NOT NULL,
    contact_email VARCHAR(191) NOT NULL,
    contact_phone VARCHAR(25) NOT NULL, -- International E.164
    facility_type VARCHAR(100) NOT NULL,
    timeline VARCHAR(50) NOT NULL,
    budget_band VARCHAR(50) NOT NULL,
    quarantined_file_path VARCHAR(255) NOT NULL, -- Outside web root (storage/private/quarantine/)
    original_file_name VARCHAR(255) NOT NULL,
    file_size_bytes INT UNSIGNED NOT NULL,
    status ENUM('SUBMITTED', 'UNDER_REVIEW', 'SCOPING', 'PROPOSAL_SENT', 'ACCEPTED', 'REJECTED', 'EXPIRED') NOT NULL DEFAULT 'SUBMITTED',
    estimated_commercial_value DECIMAL(12,2) NULL,
    assigned_lead_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_proposals_lead FOREIGN KEY (assigned_lead_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_proposals_status (status),
    INDEX idx_proposals_ref (reference_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 19. `proposal_services`
```sql
CREATE TABLE proposal_services (
    proposal_id BIGINT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (proposal_id, service_id),
    CONSTRAINT fk_prop_serv_proposal FOREIGN KEY (proposal_id) REFERENCES proposals (id) ON DELETE CASCADE,
    CONSTRAINT fk_prop_serv_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3.6 Cluster VI: Recruitment, Careers & Content Proof

#### 20. `job_postings`
```sql
CREATE TABLE job_postings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    department VARCHAR(100) NOT NULL, -- 'Clinical Nursing', 'Allied Health', etc.
    employment_type ENUM('FULL_TIME', 'PART_TIME', 'CASUAL', 'CONTRACT') NOT NULL,
    location VARCHAR(100) NOT NULL,
    summary TEXT NOT NULL,
    responsibilities_json JSON NOT NULL,
    requirements_json JSON NOT NULL,
    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    closing_date DATE NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_jobs_creator FOREIGN KEY (created_by_user_id) REFERENCES users (id),
    INDEX idx_jobs_active_dept (is_active, department)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 21. `career_applications`
```sql
CREATE TABLE career_applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(30) NOT NULL UNIQUE, -- 'APP-YYYYMMDD-XXXX'
    job_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(191) NOT NULL,
    phone VARCHAR(25) NOT NULL, -- International E.164
    country_code CHAR(2) NOT NULL DEFAULT 'AU',
    ahpra_number VARCHAR(50) NULL, -- Conditional AU medical registration
    quarantined_resume_path VARCHAR(255) NOT NULL, -- Outside web root (storage/private/quarantine/)
    original_resume_name VARCHAR(255) NOT NULL,
    status ENUM('SUBMITTED', 'REVIEWING', 'SCREENING', 'INTERVIEWING', 'OFFER', 'HIRED', 'REJECTED', 'WITHDRAWN') NOT NULL DEFAULT 'SUBMITTED',
    rejection_reason VARCHAR(100) NULL,
    retention_expiry_date DATE NOT NULL, -- GDPR / Privacy statutory retention limit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_applications_job FOREIGN KEY (job_id) REFERENCES job_postings (id),
    INDEX idx_applications_job (job_id, status),
    INDEX idx_applications_retention (retention_expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 22. `articles`
```sql
CREATE TABLE articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    summary TEXT NOT NULL,
    content_html MEDIUMTEXT NOT NULL,
    featured_image_id BIGINT UNSIGNED NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    category VARCHAR(50) NOT NULL, -- 'Clinical Governance', 'Workforce', 'Procurement'
    tags_json JSON NOT NULL,
    reading_time_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    published_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_articles_image FOREIGN KEY (featured_image_id) REFERENCES media_assets (id) ON DELETE SET NULL,
    CONSTRAINT fk_articles_author FOREIGN KEY (author_id) REFERENCES users (id),
    INDEX idx_articles_slug_published (slug, is_published, published_at),
    INDEX idx_articles_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 23. `case_studies`
```sql
CREATE TABLE case_studies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    client_sector VARCHAR(100) NOT NULL, -- 'Public Hospital Network', 'Aged Care Provider'
    challenge_summary TEXT NOT NULL,
    intervention_summary TEXT NOT NULL,
    outcome_metrics_json JSON NOT NULL, -- Quantified KPIs
    consent_verified TINYINT(1) UNSIGNED NOT NULL DEFAULT 0, -- Mandatory legal clearance flag
    is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    published_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cases_slug_published (slug, is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 24. `faqs`
```sql
CREATE TABLE faqs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL, -- 'Procurement & Contracting', 'Clinical Staffing', etc.
    question VARCHAR(255) NOT NULL,
    answer_html TEXT NOT NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_faqs_category_order (category, display_order, is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3.7 Cluster VII: System Operations, Outbox & Audit

#### 25. `email_queue` (Durable Outbox Ledger)
```sql
CREATE TABLE email_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(50) NOT NULL,
    recipient_email VARCHAR(191) NOT NULL,
    recipient_name VARCHAR(150) NOT NULL,
    payload_json JSON NOT NULL,
    status ENUM('QUEUED', 'PROCESSING', 'SENT', 'FAILED') NOT NULL DEFAULT 'QUEUED',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
    error_message TEXT NULL,
    claimed_at TIMESTAMP NULL DEFAULT NULL,
    worker_id VARCHAR(50) NULL DEFAULT NULL,
    next_retry_at TIMESTAMP NULL DEFAULT NULL,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_queue_claiming (status, next_retry_at, attempts),
    INDEX idx_email_queue_claimed_at (claimed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 26. `url_redirects` (Automated 301 Redirect Engine)
```sql
CREATE TABLE url_redirects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_path VARCHAR(191) NOT NULL UNIQUE,
    target_path VARCHAR(191) NOT NULL,
    http_status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_accessed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_redirects_source (source_path, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 27. `system_settings`
```sql
CREATE TABLE system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_group VARCHAR(50) NOT NULL, -- 'corporate', 'smtp', 'seo', 'security'
    setting_key VARCHAR(50) NOT NULL UNIQUE, -- 'legal_entity_name', 'registration_number', etc.
    setting_value TEXT NULL,
    is_secret TINYINT(1) UNSIGNED NOT NULL DEFAULT 0, -- Secret values redacted from general UI
    description VARCHAR(255) NOT NULL,
    updated_by_user_id BIGINT UNSIGNED NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by_user_id) REFERENCES users (id),
    INDEX idx_settings_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 28. `audit_logs` (Cryptographic SHA-256 Chained Ledger)
```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prev_row_hash CHAR(64) NOT NULL,
    row_hash CHAR(64) NOT NULL UNIQUE,
    domain VARCHAR(50) NOT NULL, -- 'AUTH', 'CMS', 'CRM', 'SETTINGS', 'MEDIA', 'PROPOSALS'
    action VARCHAR(100) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id BIGINT UNSIGNED NULL,
    diff_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_audit_domain_action (domain, action),
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Forward-Only CLI Database Migration Architecture

### 4.1 Migration Principles & MySQL DDL Reality
1. **Implicit DDL Commit Behavior:**  
   MySQL executes an implicit `COMMIT` immediately prior to and after any DDL statement (`CREATE TABLE`, `ALTER TABLE`, `DROP TABLE`). Therefore, **arbitrary multi-table DDL cannot be rolled back atomically inside an enclosing `BEGIN ... ROLLBACK` block**.
2. **Forward-Only Migration Strategy:**  
   To prevent catastrophic partial-schema states, migrations are designed as **atomic single-table steps** or strictly verified forward-only scripts:
   - Every migration file has an `up()` method and an explicit, tested `down()` method.
   - Migrations record executed versions in `schema_migrations`.
3. **CLI Execution Only:**  
   Migrations execute strictly via the CLI runner: `php cli/migrate.php up`. Web-accessible execution is permanently blocked (`FR-SEC-007`).
4. **Preflight Checks:**  
   Before running any migration, the CLI runner verifies:
   - MySQL version >= 8.0.
   - Database user possesses required DDL privileges.
   - Database backup timestamp verified within past 24 hours.

---

## 5. Schema Migration & Table Justification Reconciliation

| Proposed Table | Status | Business & Technical Justification |
| :--- | :---: | :--- |
| `email_queue` | **JUSTIFIED** | Essential for durable email outbox, atomic claiming, and Hostinger latency decoupling (`FR-ADM-013`). |
| `url_redirects` | **JUSTIFIED** | Mandatory for safe slug renaming without breaking SEO backlinks or bookmarks (`FR-ADM-007`, `FR-WF-006`). |
| `system_settings` | **JUSTIFIED** | Stores unverified corporate identity configuration variables without hardcoding (`FR-ADM-018`). |
| `admin_mfa_secrets` | **JUSTIFIED** | Stores AES-256 encrypted RFC 6238 TOTP secrets and backup codes separately from credentials (`FR-ADM-003`). |
| `password_resets` | **JUSTIFIED** | Single-use cryptographically hashed password reset tokens with 1-hour expiry (`FR-ADM-001`). |
| `homepage_configs` | **JUSTIFIED** | Stores hero headline, bento grid layout ordering, proof toggles (`FR-ADM-006`). |
| `media_assets` | **JUSTIFIED** | Normalizes public WebP media assets, dimensions, and mandatory alt text (`FR-ADM-009`). |
| `partnership_inquiries` | **JUSTIFIED** | Dedicated intake, tiering, and milestone pipeline for healthcare alliances (`FR-ADM-011`). |
| `proposal_services` | **JUSTIFIED** | Normalizes multi-select service scope associations on enterprise RFPs (`FR-PUB-021`). |
| `job_postings` | **JUSTIFIED** | Manages clinical and allied health career vacancies and closing dates (`FR-ADM-014`). |
| `articles` | **JUSTIFIED** | Manages healthcare insights whitepapers, categories, and reading times (`FR-ADM-017`). |
| `case_studies` | **JUSTIFIED** | Stores verified evidence proof, intervention metrics, and legal consent clearance (`FR-ADM-016`). |
| `faqs` | **JUSTIFIED** | Procurement and clinical staffing Q&A accordions with category ordering (`FR-PUB-019`). |
| `content_revisions` | **JUSTIFIED** | Enables editorial rollback and optimistic concurrency conflict detection (`FR-ADM-005`). |
| `service_industry_mappings`| **JUSTIFIED** | Normalizes bidirectional relationships between services and sectors (`FR-PUB-006`, `FR-PUB-008`). |
| `audit_logs` | **JUSTIFIED** | SHA-256 row-hash chained immutable security audit ledger (`FR-SEC-001`). |
