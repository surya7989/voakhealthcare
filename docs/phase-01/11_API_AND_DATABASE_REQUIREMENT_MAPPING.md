# API & Database Requirement Mapping
## End-to-End Alignment Between Functional Workflows, REST Endpoints, and Relational Entities

**Document Reference:** `docs/phase-01/11_API_AND_DATABASE_REQUIREMENT_MAPPING.md`  
**Phase:** Phase 1 of 31 — Complete Functional Specification  
**Baseline Authority:** Database Domain Model (`docs/phase-00/08_DATABASE_DOMAIN_MODEL.md`) and API Contract Plan (`docs/phase-00/09_API_CONTRACT_PLAN.md`).

---

### 1. Architectural Scope & Database Alignment

This document establishes the bidirectional traceability between every functional requirement (`FR`), public page, administrative module, and its underlying MySQL tables and REST API endpoints.

#### Critical Architecture Directives:
1. **Zero Raw SQL Exposure:** All interactions occur via parameterized PDO prepared statements using named parameters (`:param_name`).
2. **Explicit Foreign Key Constraints:** Cascading deletions are restricted (`ON DELETE RESTRICT`) for historical audit entities (proposals, applications, audit logs) to prevent accidental data destruction.
3. **Optimistic Locking:** Tables supporting concurrent CMS editing (`pages`, `services`, `articles`) maintain an `updated_at` or `version` column to prevent accidental overwrites.
4. **Phase 1 Implementation Boundary:** Tables and endpoints are formally mapped here; no MySQL DDL executions or PHP controller development occurs in Phase 1.
5. **API Base Path & Versioning Strategy:** Phase 0 established the gateway entry point `/api/` (routed via `public_html/api/index.php`). Phase 1 formalizes production versioned routing under `/api/v1/...` (e.g., `/api/v1/public/...`, `/api/v1/admin/...`). Unversioned utility paths (`/api/health`) are mapped to `/api/v1/health` by the PHP front controller router.

---

### 2. Comprehensive Workflow-to-API & Database Mapping

| Workflow / Feature | Target Data Entity | SQL Mutation / Operation | REST API Endpoint & HTTP Method | Auth Required? | RBAC Permission Key | Validations & Constraints | Status Codes | Emitted Audit Event |
| :--- | :--- | :--- | :--- | :---: | :---: | :--- | :---: | :--- |
| **Public Liveness** | None | Read | `GET /api/v1/health` | No | Public | None | 200 | None |
| **Homepage Data** | `homepage_configs`, `services`, `industries`, `case_studies`, `articles` | Read Aggregation | `GET /api/v1/public/homepage` | No | Public | Cache TTL 300s | 200, 500 | None |
| **Services Catalog** | `services` | `SELECT ... WHERE is_published = 1` | `GET /api/v1/public/services` | No | Public | Category filter string | 200, 422 | None |
| **Service Detail** | `services`, `service_industry_mappings` | `SELECT ... WHERE slug = :slug` | `GET /api/v1/public/services/{slug}` | No | Public | Slug regex | 200, 404 | None |
| **Industries Catalog** | `industries` | `SELECT ... WHERE is_published = 1` | `GET /api/v1/public/industries` | No | Public | None | 200 | None |
| **Industry Detail** | `industries`, `service_industry_mappings` | `SELECT ... WHERE slug = :slug` | `GET /api/v1/public/industries/{slug}` | No | Public | Slug regex | 200, 404 | None |
| **Contact Inbound** | `contact_inquiries`, `email_queue` | `INSERT INTO contact_inquiries` | `POST /api/v1/public/contact` | No | Public | Email, International Phone (E.164), Turnstile, Honeypot | 201, 422, 429 | `INQUIRY_CREATED` |
| **Partnership Intake**| `partnership_inquiries`, `email_queue` | `INSERT INTO partnership_inquiries` | `POST /api/v1/public/partnerships/inquire`| No | Public | Corporate email, Scale, Turnstile | 201, 422, 429 | `PTN_INQUIRY_SUBMITTED`|
| **RFP Tender Submit**| `proposals`, `proposal_services`, `email_queue` | `INSERT INTO proposals` + Quarantined File | `POST /api/v1/public/proposals/submit` | No | Public | 15MB max, MIME magic bytes, Turnstile | 201, 422, 429 | `RFP_SUBMITTED` |
| **Careers Listings** | `job_postings` | `SELECT ... WHERE is_active = 1` | `GET /api/v1/public/jobs` | No | Public | Department, Type, Query | 200 | None |
| **Job Detail View** | `job_postings` | `SELECT ... WHERE slug = :slug` | `GET /api/v1/public/jobs/{slug}` | No | Public | Slug regex | 200, 404 | None |
| **Job Application** | `career_applications`, `email_queue` | `INSERT INTO career_applications` + File | `POST /api/v1/public/careers/apply` | No | Public | 10MB max PDF/DOCX, Turnstile | 201, 422, 429 | `APP_SUBMITTED` |
| **Articles Catalog** | `articles` | `SELECT ... WHERE is_published = 1` | `GET /api/v1/public/articles` | No | Public | Category, Pagination (page, limit) | 200, 422 | None |
| **Article Reader** | `articles` | `SELECT ... WHERE slug = :slug` | `GET /api/v1/public/articles/{slug}` | No | Public | Slug regex | 200, 404 | None |
| **Admin Login** | `users`, `admin_sessions` | Read user, verify bcrypt, create session | `POST /api/v1/admin/auth/login` | No | Public (Admin Gate) | Email, Password min 12, Rate limit 5/15m | 200, 401, 422, 429 | `AUTH_LOGIN_SUCCESS` / `FAILED` |
| **Admin MFA Verify** | `admin_mfa_secrets`, `admin_sessions` | Verify TOTP or recovery code | `POST /api/v1/admin/auth/mfa/verify` | Ephemeral | Active Temp Token | Exactly 6 numeric digits | 200, 401, 422 | `AUTH_MFA_SUCCESS` / `FAILED` |
| **Admin Logout** | `admin_sessions` | `DELETE FROM admin_sessions` | `POST /api/v1/admin/auth/logout` | **YES** | Any Admin Role | Active session cookie | 200, 401 | `AUTH_LOGOUT` |
| **Password Recover** | `password_resets`, `email_queue` | `INSERT INTO password_resets` | `POST /api/v1/admin/auth/password-recover`| No | Public (Admin Gate) | Email format | 200, 422, 429 | `AUTH_PASSWORD_RESET_REQ`|
| **Password Reset** | `users`, `password_resets` | Update password hash, expire token | `POST /api/v1/admin/auth/password-reset` | No | Valid Token | Token 15m expiration, Password min 12 | 200, 400, 422 | `AUTH_PASSWORD_RESET_DONE`|
| **Dashboard Metrics**| Multiple tables | Aggregate counters (last 24h, 7d) | `GET /api/v1/admin/dashboard/metrics` | **YES** | Filtered by Role | Date range sanitized | 200, 401, 403 | `ADMIN_DASHBOARD_ACCESSED`|
| **User Directory** | `users`, `roles`, `user_roles` | `SELECT ... FROM users` | `GET /api/v1/admin/users` | **YES** | `ROLE_SUPER_ADMIN` | Pagination, Search | 200, 401, 403 | None |
| **User Create** | `users`, `user_roles`, `email_queue` | `INSERT INTO users` | `POST /api/v1/admin/users` | **YES** | `ROLE_SUPER_ADMIN` | Unique corporate email, Role valid | 201, 401, 403, 422 | `USER_CREATED` |
| **User Suspend** | `users`, `admin_sessions` | `UPDATE users SET is_active = 0` | `PATCH /api/v1/admin/users/{id}/status` | **YES** | `ROLE_SUPER_ADMIN` | Cannot suspend self if sole admin | 200, 400, 401, 403 | `USER_SUSPENDED` |
| **Page Edit** | `pages`, `content_revisions` | `UPDATE pages` + `INSERT revision` | `PUT /api/v1/admin/pages/{id}` | **YES** | `page:update` | Concurrency check (`updated_at`) | 200, 401, 403, 409, 422 | `PAGE_UPDATED` |
| **Services CRUD** | `services`, `service_industry_mappings` | Full CRUD operations | `/api/v1/admin/services[/{id}]` | **YES** | `service:*` | Slug uniqueness, Mandatory summary | 200, 201, 401, 403, 422 | `SERVICE_CREATED`/`UPDATED` |
| **Media Upload** | `media_assets` | Save file + `INSERT INTO media_assets` | `POST /api/v1/admin/media/upload` | **YES** | `media:upload` | Max 5MB img / 15MB doc, Magic bytes | 201, 401, 403, 422 | `MEDIA_UPLOADED` |
| **Contact CRM List** | `contact_inquiries` | `SELECT ... FROM contact_inquiries` | `GET /api/v1/admin/inquiries/contact` | **YES** | `contact:list` | Status filter, Date filter | 200, 401, 403 | None |
| **Contact Status** | `contact_inquiries` | `UPDATE contact_inquiries SET status` | `PATCH /api/v1/admin/inquiries/contact/{id}/status` | **YES** | `contact:update_status` | Status enum, Resolution notes | 200, 401, 403, 422 | `INQUIRY_STATUS_CHANGED` |
| **Partnership CRM** | `partnership_inquiries` | `SELECT`, `UPDATE status` | `/api/v1/admin/partnerships[/{id}]` | **YES** | `partner:*` | Future discovery date, Valid stage | 200, 401, 403, 422 | `PARTNERSHIP_STAGE_CHANGED`|
| **Proposal Review** | `proposals`, `proposal_services` | `SELECT`, `UPDATE status` | `/api/v1/admin/proposals[/{id}]` | **YES** | `proposal:*` | Valid RFP stage machine | 200, 401, 403, 422 | `PROPOSAL_STATUS_CHANGED`|
| **Stream Tender Doc**| `proposals` | Read file from quarantine & stream | `GET /api/v1/admin/proposals/{id}/download-tender` | **YES** | `proposal:download_tender`| Must be authorized Super Admin or BD | 200, 401, 403, 404 | `PROPOSAL_TENDER_DOWNLOADED`|
| **Jobs Management** | `job_postings` | Full CRUD operations | `/api/v1/admin/jobs[/{id}]` | **YES** | `job:*` | Future closing date, Markdown | 200, 201, 401, 403, 422 | `JOB_POSTING_CREATED` |
| **Applicant Review**| `career_applications` | `SELECT`, `UPDATE status` | `/api/v1/admin/applications[/{id}]` | **YES** | `candidate:*` | Valid hiring stage | 200, 401, 403, 422 | `APPLICATION_STATUS_UPDATED`|
| **Stream Resume** | `career_applications` | Read resume & stream binary | `GET /api/v1/admin/applications/{id}/download-resume`| **YES** | `candidate:download_cv` | Must be authorized Super Admin or HR | 200, 401, 403, 404 | `CANDIDATE_RESUME_DOWNLOADED`|
| **Audit Logs Read** | `audit_logs` | `SELECT ... FROM audit_logs` | `GET /api/v1/admin/audit-logs` | **YES** | `audit:read` | Date range, Actor filter | 200, 401, 403 | `AUDIT_LOGS_ACCESSED` |
| **Audit CSV Export** | `audit_logs` | Stream CSV of audit trail | `GET /api/v1/admin/audit-logs/export` | **YES** | `audit:export` | Signed export token | 200, 401, 403 | `AUDIT_LOGS_EXPORTED` |

---

### 3. Identified Schema Refinements & Proposed Changes for Phase 2

During the Phase 1 functional specification analysis, the following database and API refinements were identified to ensure complete operational alignment. These represent formal recommendations for Phase 2 System Architecture:

1. **Table `url_redirects` Formalization:**  
   Phase 0 documented 301 redirects conceptually; Phase 2 must explicitly incorporate the `url_redirects` schema into the master migration plan to support the safe slug renaming engine:
   ```sql
   CREATE TABLE url_redirects (
       id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
       old_path VARCHAR(255) NOT NULL UNIQUE,
       new_path VARCHAR(255) NOT NULL,
       http_status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
       hits_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       INDEX idx_old_path (old_path)
   );
   ```

2. **Table `content_revisions` Formalization:**  
   Phase 0 mentioned revision rollback; Phase 2 must add `content_revisions` to the relational migration catalog to support the visual diff and rollback engine for Pages, Services, and Articles.

3. **Table `email_queue` Formalization:**  
   Phase 0 specified transactional emails; Phase 2 must formalize the `email_queue` schema to decouple user HTTP requests from external SMTP network latency on Hostinger shared hosting.

4. **Service-Industry Associative Entity (`service_industry_mappings`):**  
   Formalize the `m:n` bridge table linking `services.id` and `industries.id` with a `priority_weight` column for contextual cross-linking on public detail pages.

---

### 4. Acceptance Criteria & Automated Mapping Verification

| Criteria ID | Target Mapping | Verification Rule | Test Method |
| :--- | :--- | :--- | :--- |
| **AC-MAP-01** | Zero Endpoint Gaps | Every public page and admin module has a corresponding mapped REST endpoint. | Static Spec Audit |
| **AC-MAP-02** | Secure Binary Streaming | Tender documents and resumes stream exclusively through authenticated PHP controllers. | Unit / Integration Test |
| **AC-MAP-03** | Immutable Audit Coverage | Every state-changing API request (`POST`, `PUT`, `PATCH`, `DELETE`) maps to a distinct audit event. | Integration Test |
| **AC-MAP-04** | Parameterized Execution | 100% of database persistence operations utilize PDO prepared statements without string concatenation. | Static Code Analysis |
