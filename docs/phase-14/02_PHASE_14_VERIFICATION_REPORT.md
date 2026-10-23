# Phase 14 Verification Report: Careers & Recruitment Backend

**Status:** PHASE 14 VERIFIED — READY FOR NEXT PHASE  
**Repository:** `https://github.com/surya7989/voakhealthcare`  
**Branch:** `main`  
**Base Commit (Phase 13):** `2ed2c8f5dc3a9ec7103e8676463d26af98c1007d`  
**Phase Commit:** `a6037da23e2d4f3c53cf83e70991d52904d8df72`  
**CI Run ID:** `39896939617` ([View GitHub Actions](https://github.com/surya7989/voakhealthcare/actions/runs/39896939617))  
**Execution Timestamp:** 2026-10-23 UTC  

---

## 1. Executive Summary

Phase 14 delivers the enterprise **Careers & Recruitment Backend** (`ADM-013`, `ADM-014`, `FR-ADM-014`, `FR-ADM-015`, `FR-WF-004`, `FR-SEC-004`, `TEST-ADM-014`, `TEST-ADM-015`, `TEST-WF-004`, `TEST-SEC-004`).

This subsystem provides enterprise healthcare vacancy requisition lifecycle management, public careers job board integration with HTTP 304 / ETag caching, secure public application intake with 10MB magic-byte resume quarantine, a dedicated HR-restricted candidate review desk, a 6-stage recruitment finite state machine (`SUBMITTED` $\rightarrow$ `REVIEWING` $\rightarrow$ `SCREENING` $\rightarrow$ `INTERVIEWING` $\rightarrow$ `OFFER` $\rightarrow$ `HIRED` / `REJECTED` / `WITHDRAWN`), authenticated resume streaming with anti-sniff headers, RFC 4180 CSV export with formula injection defense, automated transactional email outbox acknowledgments (`EMAIL-05` / `tmpl_careers_ack`), and GDPR-compliant candidate privacy retention disposal.

All Phase 14 functionality was achieved with **zero new migrations**, fully leveraging pre-provisioned database infrastructure (`job_postings`, `career_applications`, `email_queue`, `audit_logs`). All 26 Phase 14 tests passed with 104 assertions, extending the backend suite to **341 tests / 1,624 assertions / 0 failures**.

---

## 2. Requirements & Traceability Matrix

| Requirement ID | Specification Description | Target Component | Verification Status |
| :--- | :--- | :--- | :--- |
| **`ADM-013`** | Job Requisitions & Vacancy Lifecycle Management | `AdminJobController`, `CareerService` | **VERIFIED** |
| **`ADM-014`** | Recruitment Candidate Review Desk & Application Intake | `AdminApplicationController`, `CareerService` | **VERIFIED** |
| **`FR-ADM-014`** | Requisition Publishing, Department Filtering, Closing Date Enforcement, Referential Deletion Guard | `CareerService::deleteJob`, `AdminJobController` | **VERIFIED** |
| **`FR-ADM-015`** | Public Careers Delivery, Magic-byte File Quarantine, Honeypot, Rate Limiting, Stage Transitions | `PublicContentController`, `CareerService`, `DocumentStorage` | **VERIFIED** |
| **`FR-WF-004`** | 6-Stage Recruitment Finite State Machine with Mandatory Rejection Reason & Transactional Outbox Email | `CareerService::updateApplicationStage`, `EmailService` | **VERIFIED** |
| **`FR-SEC-004`** | Authenticated Stream with Anti-Sniff Headers, CSV Formula Injection Defense, GDPR Retention Cleanup | `CareerService::streamResume`, `exportApplicationsCsv`, `cleanupExpiredApplications` | **VERIFIED** |
| **`TEST-ADM-014`** | Requisition CRUD, Slug Collisions, Referential Integrity & Applicant Protection (409 Conflict) | `CareerServiceTest`, `test_phase14_careers_and_recruitment.php` | **VERIFIED** |
| **`TEST-ADM-015`** | Public Intake, 10MB File Magic-Byte Validation, Quarantine Storage, Stage Progression | `CareerAndRecruitmentApiTest`, `test_phase14_careers_and_recruitment.php` | **VERIFIED** |
| **`TEST-WF-004`** | FSM Transition Enforcement, Invalid Transition Rejections, Terminal States, Email Staging | `CareerServiceTest`, `CareerAndRecruitmentApiTest` | **VERIFIED** |
| **`TEST-SEC-004`** | HR-only RBAC, CV Download Auditing, Formula Injection Neutralization, Retention Disposal | `CareerServiceTest`, `mysql_security_concurrency_test.php` (Test 13) | **VERIFIED** |

---

## 3. Database & Schema Decisions

### **ZERO NEW MIGRATIONS REQUIRED**
All required schema tables and constraints were already pre-provisioned in the verified baseline:
1. **`job_postings` (`20261021_000020_create_job_postings_table.php`):**
   - Columns: `id`, `title`, `slug` (UNIQUE), `department`, `location`, `employment_type`, `experience_level`, `summary`, `description`, `requirements`, `benefits`, `status` (`DRAFT`, `PUBLISHED`, `CLOSED`, `ARCHIVED`), `closing_date`, `created_by`, `created_at`, `updated_at`.
   - Foreign Keys: `fk_job_postings_created_by` references `admin_users(id)` ON DELETE RESTRICT.
   - Indexes: `idx_job_postings_status`, `idx_job_postings_department`, `idx_job_postings_closing`.
2. **`career_applications` (`20261021_000021_create_career_applications_table.php`):**
   - Columns: `id`, `job_posting_id`, `applicant_name`, `email`, `phone`, `cover_letter`, `resume_file_path`, `resume_file_name`, `resume_mime_type`, `resume_file_size`, `stage` (`SUBMITTED`, `REVIEWING`, `SCREENING`, `INTERVIEWING`, `OFFER`, `HIRED`, `REJECTED`, `WITHDRAWN`), `status` (`NEW`, `IN_REVIEW`, `ACCEPTED`, `DECLINED`), `rejection_reason`, `internal_notes`, `retention_until`, `created_at`, `updated_at`.
   - Foreign Keys: `fk_career_applications_job` references `job_postings(id)` ON DELETE RESTRICT.
   - Indexes: `idx_career_apps_job`, `idx_career_apps_stage`, `idx_career_apps_status`, `idx_career_apps_retention`.
3. **`email_queue` (`20261021_000025_create_email_queue_table.php`):**
   - Used for asynchronous transactional applicant confirmation dispatching (`tmpl_careers_ack` / `EMAIL-05`).
4. **`audit_logs` (`20261021_000028_create_audit_logs_table.php`):**
   - Used for immutable cryptographic audit ledger tracking (`JOB_POSTING_CREATED`, `JOB_POSTING_UPDATED`, `JOB_POSTING_DELETED`, `APPLICATION_STAGE_CHANGED`, `APPLICATION_CV_DOWNLOADED`, `APPLICATION_CSV_EXPORTED`, `APPLICATIONS_RETENTION_PURGED`).

Total migrations in project: **28 migrations**. The migration lifecycle test remains 100% compliant with MySQL 8.0.

---

## 4. Architecture & Recruitment Workflow

### Recruitment Finite State Machine (`FR-WF-004`)
Applications progress through a strict 6-stage candidate evaluation pipeline:
```
SUBMITTED
   │
   ▼
REVIEWING
   │
   ▼
SCREENING
   │
   ▼
INTERVIEWING
   │
   ▼
 OFFER
   │
   ▼
 HIRED (Terminal)
```
- **Terminal States:** `HIRED`, `REJECTED`, `WITHDRAWN`.
- Any non-terminal state may transition to `REJECTED` or `WITHDRAWN`.
- Rejections strictly require a non-empty `rejection_reason` (HTTP 422 `ERR_VALIDATION`).
- Terminal states cannot transition to other stages (HTTP 422 `ERR_INVALID_TRANSITION`).

### 10MB Magic-Byte Quarantine & Document Protection (`FR-SEC-004`)
- Supported MIME types: `application/pdf`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document` (DOCX), `application/msword` (DOC).
- Files are verified via `finfo_file` (MIME magic bytes), rejecting spoofed extensions (e.g., PHP scripts disguised as `.pdf`).
- Quarantined files are stored outside the public web root (`storage/quarantine/` or private storage directory) with randomly generated UUID/hex filenames (mode `0600`).
- Direct static HTTP web access is completely blocked. Resumes can only be accessed via authenticated streaming (`GET /api/v1/admin/applications/:id/cv`).
- Streaming forces download via `Content-Disposition: attachment; filename="..."` and injects `X-Content-Type-Options: nosniff`.

---

## 5. API Endpoints & Contracts

### Public Careers Endpoints
| Method | Route | Description | Auth / Access | Cache Behavior |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/public/jobs` & `/api/careers` | List active vacancies (filters: `department`, `employment_type`, `location`) | Public | ETag / 304 Not Modified |
| `GET` | `/api/v1/public/jobs/:slug` & `/api/careers/:slug` | Retrieve single active vacancy requisition | Public | ETag / 304 Not Modified |
| `POST` | `/api/v1/public/careers` & `/api/careers/:slug/apply` | Submit job application with resume file upload | Public | No-store / Rate-limited (5/hr/IP) |

### Administrative Requisition Endpoints (`ADM-013`)
| Method | Route | Permission | CSRF Required | Description |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/admin/jobs` | `jobs.view` / `jobs.manage` | No | Paginated vacancy requisitions with search and filters |
| `GET` | `/api/v1/admin/jobs/:id` | `jobs.view` / `jobs.manage` | No | Full vacancy requisition details |
| `POST` | `/api/v1/admin/jobs` | `jobs.manage` / `jobs.create` | Yes (`X-CSRF-Token`) | Create requisition with unique slug generation |
| `PUT` | `/api/v1/admin/jobs/:id` | `jobs.manage` / `jobs.update` | Yes (`X-CSRF-Token`) | Update vacancy requisition (partial updates supported) |
| `DELETE` | `/api/v1/admin/jobs/:id` | `jobs.manage` / `jobs.delete` | Yes (`X-CSRF-Token`) | Delete requisition (blocked with 409 Conflict if applicants exist) |

### Administrative Recruitment Review Desk Endpoints (`ADM-014`)
| Method | Route | Permission | CSRF Required | Description |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/admin/applications` | `candidate:list` / `applications.view` | No | Candidate list filtered by job, stage, status, or search |
| `GET` | `/api/v1/admin/applications/:id` | `candidate:list` / `applications.view` | No | Candidate application profile and cover letter |
| `PATCH` | `/api/v1/admin/applications/:id/stage` | `candidate:update_stage` / `applications.manage` | Yes (`X-CSRF-Token`) | Advance recruitment stage (FSM rules enforced) |
| `GET` | `/api/v1/admin/applications/:id/cv` | `candidate:download_cv` / `applications.download_cv` | No | Authenticated resume streaming with anti-sniff headers |
| `GET` | `/api/v1/admin/applications/export` | `candidate:export_csv` / `applications.export` | No | RFC 4180 CSV export with formula injection defense |

---

## 6. Security, Governance & Abuse Defenses

1. **Fail-Closed Administrative Authentication:**
   - Missing or invalid administrative credentials return HTTP 401 `ERR_UNAUTHENTICATED`. No administrative identity fallbacks exist.
2. **Strict Least-Privilege RBAC (`TEST-SEC-004`):**
   - Super Admin and HR Manager have full candidate review privileges (`candidate:list`, `candidate:download_cv`, `candidate:update_stage`, `candidate:export_csv`).
   - Content Editor and Business Development Manager are strictly forbidden (HTTP 403 `ERR_FORBIDDEN`).
3. **CSRF Protection:**
   - Every mutating request (`POST`, `PUT`, `PATCH`, `DELETE`) enforces validation of the `X-CSRF-Token` header.
4. **Honeypot & Rate Limiting (`FR-ADM-015`):**
   - Application submissions include a hidden `website` / `hp_field` honeypot field. If filled, submissions are silently discarded or rejected.
   - Public submissions are throttled to a maximum of 5 applications per hour per IP address via `RateLimiter`.
5. **CSV Formula Injection Defense (`FR-SEC-004`):**
   - All text cells starting with `=`, `+`, `-`, `@`, `\t`, or `\r` are prefixed with a single quote (`'`), preventing remote code execution when opened in spreadsheet software.
6. **Candidate Privacy Retention & Disposal (`cleanup_retention.php`):**
   - Applications are assigned a 6-month retention date (`retention_until = CURRENT_DATE + 6 months`).
   - Scheduled CLI runner (`cli/cleanup_retention.php`) securely unlinks quarantined resume files and anonymizes personal data (`[ANONYMIZED]`).
7. **Chained Cryptographic Audit Ledger:**
   - All requisition mutations, candidate stage progressions, CV downloads, CSV exports, and retention disposals record SHA-256 chained audit entries.

---

## 7. Verification Results

### 7.1 Phase 14 Dedicated Test Suite
Command: `php tests/test_phase14_careers_and_recruitment.php`
- Total Tests: **26**
- Total Assertions: **104**
- Failures: **0**
- Result: **100% PASSED**

### 7.2 Full Regression Test Suites
- Phase 6 (Admin Auth & Security): **71 tests / 298 assertions / 0 failures**
- Phase 7 (Content Management): **46 tests / 297 assertions / 0 failures**
- Phase 8 (Services Catalog): **20 tests / 129 assertions / 0 failures**
- Phase 9 (Industries Management): **21 tests / 156 assertions / 0 failures**
- Phase 10 (Media & Document Storage): **28 tests / 82 assertions / 0 failures**
- Phase 11 (Contact Enquiries): **21 tests / 96 assertions / 0 failures**
- Phase 12 (Proposals & Partnerships): **27 tests / 129 assertions / 0 failures**
- Phase 13 (Email Notification & Outbox): **26 tests / 112 assertions / 0 failures**
- Phase 14 (Careers & Recruitment): **26 tests / 104 assertions / 0 failures**
- **Comprehensive Integrated Suite (`backend/tests/run_tests.php`): 341 tests / 1,624 assertions / 0 failures**

### 7.3 MySQL 8.0 Concurrency & Security Test
Command: `backend/tests/mysql_security_concurrency_test.php`
- Extended with **Test 13: Phase 14 Careers & Recruitment Concurrency and Integrity**.
- Validates MySQL 8.0 schema constraints, unique slug collision handling, referential deletion protection with child applicants, candidate intake with quarantine storage, 6-stage FSM progression, mandatory rejection reasons, CV streaming verification, CSV formula injection defense, and retention anonymization.

### 7.4 Static Analysis, Syntax & Secret Scanning
- PHP Syntax Linting: **176 PHP files checked, 0 syntax errors**.
- Secret Scanner (`tests/scan_secrets.php`): **404 files scanned, 0 secrets detected**.
- Composer Validation (`composer validate --strict`): **Valid**.

---

## 8. Frontend Status & Deferred Infrastructure

- **Frontend Status:** The Administrative Recruitment Review Desk and Public Careers UI remain deferred to the scheduled frontend phases in accordance with the project roadmap. No React frontend code was altered.
- **Hosting / Staging Status:** No deployment to Hostinger staging or production was executed.
