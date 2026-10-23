# Phase 14 Plan: Careers & Recruitment Backend

## 1. Official Phase 14 Identification
- **Official Phase Name:** Careers & Recruitment Backend (`ADM-013`, `ADM-014`, `FR-ADM-014`, `FR-ADM-015`, `FR-WF-004`, `FR-SEC-004`, `TEST-ADM-014`, `TEST-ADM-015`, `TEST-WF-004`, `TEST-SEC-004`)
- **Owning Module:** `Voak\Health\Modules\Careers` / `Voak\Health\Services\CareerService`
- **Business Objective:** Provide an enterprise-grade clinical and operational healthcare careers requisition engine and talent acquisition desk. Enables HR management to post, update, close, and publish job vacancies, ingest candidate applications with quarantined resume attachments (PDF/DOCX max 10MB), securely stream resumes with anti-sniff headers under strict HR-only RBAC, advance candidate recruitment through a compliant 6-stage finite state machine (`SUBMITTED` $\rightarrow$ `HIRED`), enforce mandatory rejection reason codes, safeguard referential integrity preventing accidental vacancy deletion, provide RFC 4180 CSV export with formula injection defense, stage candidate confirmation emails, and enforce GDPR / Australian Privacy Principles data retention with automated resume shredding and PII anonymization after 6 months.

---

## 2. Canonical Requirements Traceability

| Requirement ID | Canonical Description | Target Component | Status |
| :--- | :--- | :--- | :--- |
| **`ADM-013`** | Careers Requisitions & Clinical Vacancy Management Console | `CareerService`, `AdminJobController` | Requires New Implementation |
| **`ADM-014`** | Candidate Applications Triage & Clinician Pipeline Review Desk | `CareerService`, `AdminApplicationController` | Requires New Implementation |
| **`FR-ADM-014`** | Careers & Job Openings Management (CRUD, publish toggles, referential deletion guard) | `CareerService`, `job_postings` | Requires New Implementation |
| **`FR-ADM-015`** | Candidate Applications Review Desk & Quarantined Resume Streaming | `CareerService`, `career_applications` | Requires New Implementation |
| **`FR-WF-004`** | Candidate Recruitment State Machine (`SUBMITTED` $\rightarrow$ `HIRED`, `REJECTED`, `WITHDRAWN`) | `CareerService` FSM | Requires New Implementation |
| **`FR-SEC-004`** | Candidate Privacy & 6-Month Data Retention Disposal (automated anonymization & shredding) | `CareerService`, `cleanup_retention.php` | Requires New Implementation |
| **`TEST-ADM-014`**| Job vacancy CRUD, closing dates, active filtering, and deletion blocker on active applicants | `test_phase14_careers_and_recruitment.php` | Requires New Implementation |
| **`TEST-ADM-015`**| Candidate review desk filtering, authenticated resume streaming, HR-only RBAC gate | `test_phase14_careers_and_recruitment.php` | Requires New Implementation |
| **`TEST-WF-004`**| Hiring state machine transition enforcement, mandatory rejection reasons, interview feedback | `test_phase14_careers_and_recruitment.php` | Requires New Implementation |
| **`TEST-SEC-004`**| Dual-enclave resume quarantine, MIME magic bytes, path traversal defense, anti-sniff streaming | `MediaStorageService`, `CareerService` | Already Supported / Integrates |

---

## 3. Database & Schema Verification

### **ZERO NEW MIGRATIONS REQUIRED**
All required tables, constraints, status enums, foreign keys, and indexes are already fully provisioned in the verified migration baseline:
1. **`job_postings` (`20261021_000020_create_job_postings_table.php`):**
   - Columns: `id`, `title`, `slug` (UNIQUE), `department`, `employment_type` (ENUM: `FULL_TIME`, `PART_TIME`, `CASUAL`, `CONTRACT`), `location`, `summary`, `responsibilities_json`, `requirements_json`, `is_active`, `closing_date`, `created_by_user_id` (FK to `users.id`), `created_at`, `updated_at`.
   - Index: `idx_jobs_active_dept (is_active, department)`.
2. **`career_applications` (`20261021_000021_create_career_applications_table.php`):**
   - Columns: `id`, `reference_code` (UNIQUE), `job_id` (FK to `job_postings.id`), `full_name`, `email`, `phone`, `country_code`, `ahpra_number`, `quarantined_resume_path`, `original_resume_name`, `status` (ENUM: `SUBMITTED`, `REVIEWING`, `SCREENING`, `INTERVIEWING`, `OFFER`, `HIRED`, `REJECTED`, `WITHDRAWN`), `rejection_reason`, `retention_expiry_date`, `created_at`, `updated_at`.
   - Indexes: `idx_applications_job (job_id, status)`, `idx_applications_retention (retention_expiry_date)`.
3. **`email_queue` (`20261021_000025_create_email_queue_table.php`):**
   - Staging candidate application acknowledgment transactional email.
4. **`audit_logs` (`20261021_000028_create_audit_logs_table.php`):**
   - SHA-256 chained audit events: `JOB_POSTED`, `JOB_UPDATED`, `JOB_DELETED`, `CANDIDATE_STATUS_UPDATED`, `RESUME_DOWNLOADED`, `APPLICATIONS_EXPORTED`, `RECRUITMENT_RECORD_ANONYMIZED`.

Total migrations remain exactly **28**.

---

## 4. Architectural Design & Components

### 4.1 Service Layer: `Voak\Health\Services\CareerService`
- **Job Management:**
  - `listJobs(array $filters, int $page = 1, int $perPage = 20): array`
  - `getJobById(int $id): ?array`
  - `getPublishedJobBySlug(string $slug): ?array`
  - `createJob(array $data, int $adminUserId, ?string $clientIp = null): array`
  - `updateJob(int $id, array $data, int $adminUserId, ?string $clientIp = null): array`
  - `deleteJob(int $id, int $adminUserId, ?string $clientIp = null): bool` (Enforces referential integrity guard: blocks deletion if candidate applications exist for the job).
- **Candidate Application Management:**
  - `submitApplication(array $payload, ?array $file, ?string $clientIp = null, ?string $userAgent = null): array` (Validates input, checks honeypot, applies 5/hr/IP rate limit, quarantines resume via `MediaStorageService`, sets `retention_expiry_date = +6 months`, stages `EMAIL-01`/`EMAIL-08` notification, writes audit record).
  - `listApplications(array $filters, int $page = 1, int $perPage = 20): array`
  - `getApplicationById(int $id): ?array`
  - `advanceCandidateStage(int $id, string $targetStatus, ?string $feedback, ?string $rejectionReason, int $adminUserId, ?string $clientIp = null): array` (Validates FSM transition; requires `rejection_reason` for `REJECTED`; writes `CANDIDATE_STATUS_UPDATED` audit record).
  - `streamCandidateResume(int $id, int $adminUserId, ?string $clientIp = null): Response` (Streams quarantined resume with security headers via `MediaStorageService`).
  - `exportApplicationsCsv(array $filters, int $adminUserId, ?string $clientIp = null): string` (RFC 4180 CSV with formula injection defense).
  - `cleanupExpiredApplications(): array` (GDPR retention disposal: shreds physical files, anonymizes PII, logs `RECRUITMENT_RECORD_ANONYMIZED`).

### 4.2 Candidate Recruitment Finite State Machine (`FR-WF-004`)
- **States:**
  - `SUBMITTED` (Initial state upon public submission)
  - `REVIEWING` (HR initial review)
  - `SCREENING` (Shortlisted for preliminary screening)
  - `INTERVIEWING` (Interview scheduled and conducted)
  - `OFFER` (Formal employment offer extended)
  - `HIRED` (Contract signed, onboarding initiated)
  - `REJECTED` (Disqualified at any stage; requires mandatory `rejection_reason`)
  - `WITHDRAWN` (Candidate voluntarily withdrew)
- **Rules:**
  - Valid forward progressions: `SUBMITTED` $\rightarrow$ `REVIEWING` $\rightarrow$ `SCREENING` $\rightarrow$ `INTERVIEWING` $\rightarrow$ `OFFER` $\rightarrow$ `HIRED`.
  - Allowed branch to `REJECTED` from any non-terminal state (`SUBMITTED`, `REVIEWING`, `SCREENING`, `INTERVIEWING`, `OFFER`).
  - Transition to `REJECTED` strictly requires a non-empty `rejection_reason`.
  - `HIRED`, `REJECTED`, and `WITHDRAWN` are terminal states.
  - Illegal skips (e.g., `SUBMITTED` $\rightarrow$ `HIRED` or `SUBMITTED` $\rightarrow$ `OFFER`) throw HTTP 422 `ERR_INVALID_TRANSITION`.

---

## 5. Security & Governance

1. **Fail-Closed Authentication:**
   - Unauthenticated admin access returns HTTP 401 `ERR_UNAUTHENTICATED`.
   - Never falls back to dummy identity ($request->getAttribute('admin_user_id') ?? 1 forbidden).
2. **Strict Least-Privilege RBAC:**
   - **Job Postings (`ADM-013`):** Accessible to `ROLE_SUPER_ADMIN`, `ROLE_WEBSITE_ADMIN`, `ROLE_HR_MANAGER` (`careers.manage`, `careers.view`, `job:create`, `job:update`, `job:close`). Blocked for `ROLE_CONTENT_EDITOR` and `ROLE_BD_MANAGER` (HTTP 403 `ERR_FORBIDDEN`).
   - **Candidate Applications & Resumes (`ADM-014`):** Strictly restricted to `ROLE_SUPER_ADMIN` and `ROLE_HR_MANAGER` (`applications.view`, `applications.manage`, `applications.download_cv`, `applications.export`, `candidate:list`, `candidate:update_stage`, `candidate:download_cv`, `candidate:export_csv`). Content Editor and BD Manager are completely forbidden (HTTP 403 `ERR_FORBIDDEN`).
3. **CSRF Protection:**
   - All state-changing admin requests (`POST`, `PUT`, `PATCH`, `DELETE`) require verified `X-CSRF-Token`.
4. **Quarantine & Resume File Security (`FR-SEC-004` / `TEST-SEC-004`):**
   - Strictly validates 10MB ceiling for candidate resumes.
   - Extension allowlist: strictly `.pdf`, `.docx`.
   - Double extension detection (e.g. `resume.pdf.php` rejected).
   - Magic byte verification (`%PDF-` for PDF, `PK\x03\x04` for DOCX).
   - Stored outside web root with randomized UUIDv4 filenames and `chmod 0600`.
   - Authenticated streaming sets `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, and `Cache-Control: private, no-store`.
5. **Data Retention & Privacy Act Compliance (`FR-SEC-004`):**
   - Automatic 6-month retention expiry stamp (`retention_expiry_date = NOW() + INTERVAL 6 MONTH`).
   - Automated anonymization and physical resume file shredding via `cleanup_retention.php`.

---

## 6. Implementation Checklist
- [x] Canonical Phase 14 documentation analysis.
- [x] Verify database schema: Zero new migrations required.
- [ ] Create `backend/src/Services/CareerService.php`.
- [ ] Create `backend/src/Modules/Admin/AdminJobController.php`.
- [ ] Create `backend/src/Modules/Admin/AdminApplicationController.php`.
- [ ] Update `backend/src/Modules/PublicContent/PublicContentController.php` with public career methods.
- [ ] Update `backend/src/Auth/RbacManager.php` with alias mappings for careers and applications.
- [ ] Update `backend/src/Core/App.php` with container bindings and routes.
- [ ] Create CLI retention cleanup script: `backend/cli/cleanup_retention.php` and root shim `cli/cleanup_retention.php`.
- [ ] Create unit tests: `backend/tests/Unit/CareerServiceTest.php`.
- [ ] Create integration tests: `backend/tests/Integration/CareerAndRecruitmentApiTest.php`.
- [ ] Create root test runner: `tests/test_phase14_careers_and_recruitment.php`.
- [ ] Extend MySQL security concurrency test: `backend/tests/mysql_security_concurrency_test.php` (Test 13).
- [ ] Run full regression suite (Phases 6–14 + foundation + secret scan + composer validate + PHP linting).
- [ ] Commit, push, and verify 100% green CI on GitHub Actions across PHP 8.2, PHP 8.3, and MySQL 8.0.
