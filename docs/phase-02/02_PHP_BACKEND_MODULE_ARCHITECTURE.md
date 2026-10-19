# PHP Backend Architecture & Domain Module Specifications

**Document Reference:** `docs/phase-02/02_PHP_BACKEND_MODULE_ARCHITECTURE.md`  
**Phase:** Phase 2 of 31 — Complete System Architecture  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Executing Role:** Staff PHP Backend Architect & Application Security Engineer  
**Baseline Authority:** Accepted Phase 0 Baseline (`docs/phase-00/07_TECHNICAL_ARCHITECTURE.md`) and Accepted Phase 1 Requirements (`docs/phase-01/01_MASTER_FUNCTIONAL_REQUIREMENTS.md`)  
**Status:** **AUTHORITATIVE ARCHITECTURAL SPECIFICATION** (Design Only — Code Implementation Deferred to Phases 4–17)

---

## 1. Core Engineering Standards & Runtime Baseline

The PHP backend is designed as a modular, decoupled, object-oriented service architecture engineered to operate natively on Hostinger shared hosting without external process managers.

### 1.1 Runtime & Language Standards
- **PHP Version Compatibility:** PHP 8.2+ and PHP 8.3+ (Hostinger confirmed runtime).
- **Strict Typing:** All PHP source files MUST begin with `declare(strict_types=1);`.
- **Typing Strictness:** 100% parameter, property, and return type declarations. Union types, nullable types (`?Type`), and intersection types used where appropriate. No untyped arrays where typed DTOs can be defined.
- **Autoloading:** PSR-4 compliant autoloading mapped under namespace `Voak\Health\`:
  - `Voak\Health\Core\` $\longrightarrow$ `src/Core/`
  - `Voak\Health\Middleware\` $\longrightarrow$ `src/Middleware/`
  - `Voak\Health\Modules\<ModuleName>\` $\longrightarrow$ `src/Modules/<ModuleName>/`
  - `Voak\Health\Database\` $\longrightarrow$ `src/Database/`

### 1.2 Composer Dependency Strategy
To ensure maximum compatibility and zero runtime bloat on shared hosting, external third-party dependencies are kept minimal and audited for security:
- `vlucas/phpdotenv` (v5.x): Environment configuration parsing with fail-fast boot checks.
- `bramus/router` or custom micro-router: Lightweight, zero-dependency REST routing.
- `spomky-labs/otphp` (v11.x) or native RFC 6238 implementation: Cryptographic TOTP MFA generation and verification.
- `ext-pdo`, `ext-pdo_mysql`: Native database driver.
- `ext-fileinfo`: MIME magic byte inspection (`finfo`).
- `ext-gd` or `ext-imagick`: Image conversion to WebP and responsive thumbnail generation.
- `ext-openssl`: Cryptographic token generation (`random_bytes`) and AES-256 encryption.

---

## 2. Architectural Layers & Design Patterns

```
+----------------------------------------------------------------------------------------------------+
|                                      HTTP REQUEST ENTRY POINT                                      |
|                                    (public_html/api/index.php)                                     |
+----------------------------------------------------------------------------------------------------+
                                                  │
                                                  ▼
+----------------------------------------------------------------------------------------------------+
|                                    SECURITY MIDDLEWARE PIPELINE                                    |
|   RateLimiterMiddleware ──► CorsMiddleware ──► CsrfMiddleware ──► SessionMiddleware ──► RbacGate   |
+----------------------------------------------------------------------------------------------------+
                                                  │
                                                  ▼
+----------------------------------------------------------------------------------------------------+
|                                     REST CONTROLLER LAYER (DTOs)                                   |
|   - Request deserialization & validation (Zod-equivalent server-side schema validator)            |
|   - HTTP status code emission & uniform JSON envelope formatting                                   |
+----------------------------------------------------------------------------------------------------+
                                                  │
                                                  ▼
+----------------------------------------------------------------------------------------------------+
|                                      DOMAIN SERVICE LAYER                                          |
|   - 20 Cohesive Domain Services encapsulating business rules, workflows, and state machines       |
|   - Database transaction boundaries (begin / commit / rollBack)                                    |
+----------------------------------------------------------------------------------------------------+
                                                  │
                                                  ▼
+----------------------------------------------------------------------------------------------------+
|                                     REPOSITORY ACCESS LAYER                                        |
|   - Typed Repository Interfaces & PDO Implementations                                              |
|   - 100% Parameterized Prepared Statements (Zero SQL Injection)                                    |
+----------------------------------------------------------------------------------------------------+
                                                  │
                                                  ▼
+----------------------------------------------------------------------------------------------------+
|                                       MySQL DATABASE ENGINE                                        |
+----------------------------------------------------------------------------------------------------+
```

### 2.1 Dependency Injection & Service Container
A lightweight, compiled PSR-11 compliant Dependency Injection Container (`Voak\Health\Core\Container`) manages service instantiations:
- **Singleton Services:** Database connection (`PDO`), Logger, Audit Service, Event Dispatcher.
- **Transient Services:** Controllers, Command Handlers, Domain Validators.
- **Zero Global State:** Global variables (`$_GET`, `$_POST`, `$_SERVER`, `$_COOKIE`) are encapsulated into a typed immutable `Voak\Health\Core\Http\Request` object upon bootstrap.

### 2.2 Unified Error Handling & Exception Normalization
- All business exceptions inherit from `Voak\Health\Core\Exceptions\AppException`.
- Uncaught exceptions are intercepted by `Voak\Health\Core\Exceptions\GlobalExceptionHandler`.
- In production (`APP_DEBUG=false`), internal database errors, SQL syntax exceptions, and filesystem paths are completely suppressed. The client receives an authentic HTTP error status and a normalized envelope:
```json
{
  "success": false,
  "error": {
    "code": "INTERNAL_SERVER_ERROR",
    "message": "An unexpected error occurred. Please quote the reference code to support.",
    "reference_id": "REF-ERR-653A8B12"
  }
}
```

---

## 3. The 20 Domain Module Specifications

Every domain module adheres to a strict architectural contract specifying responsibilities, public service contracts, database dependencies, API endpoints, security gates, allowed inter-module calls, and owning implementation phases.

---

### Module 01: Core Platform & Infrastructure (`Voak\Health\Modules\Core`)
- **Responsibilities:** Application bootstrapping, `.env` fail-fast validation, DI container, HTTP Request/Response abstractions, REST router, global exception normalizer, database transaction manager.
- **Public Service Contract:**
  - `ContainerInterface::get(string $id): object`
  - `Config::get(string $key, mixed $default = null): mixed`
  - `DatabaseConnection::getPdo(): \PDO`
  - `DatabaseTransaction::run(callable $callback): mixed`
- **Database Dependencies:** N/A (manages underlying PDO pool).
- **API Endpoints:** `GET /api/v1/health` (liveness check).
- **Security Requirements:** Fails fast at boot if `APP_KEY` is default, `APP_DEBUG=true` in production, or database credentials are empty (`FR-SEC-005`).
- **Allowed Inter-Module Dependencies:** Base layer; zero dependencies on other modules.
- **Error Behavior:** Emits fatal HTTP 500 with zero credential leakage; writes to `storage/logs/error.log`.
- **Relevant Tests:** `TEST-SEC-005` (Fail-Fast Boot Test).
- **Owning Implementation Phase:** **Phase 4 (Core Backend & Environment Boot)**.

---

### Module 02: Authentication (`Voak\Health\Modules\Auth`)
- **Responsibilities:** Admin credential verification (Bcrypt cost 12), brute-force defense (5 attempts / 15m lockout), session lifecycle management, RFC 6238 TOTP MFA verification, password recovery.
- **Public Service Contract:**
  - `AuthService::authenticate(string $email, string $password, string $ip): AuthResult`
  - `AuthService::verifyMfa(string $preAuthToken, string $totpCode): SessionResult`
  - `AuthService::validateSession(string $token): ?AdminUser`
  - `AuthService::terminateSession(string $token): void`
- **Database Dependencies:** `users`, `admin_sessions`, `admin_mfa_secrets`, `password_resets`.
- **API Endpoints:**
  - `POST /api/v1/admin/auth/login`
  - `POST /api/v1/admin/auth/mfa/verify`
  - `POST /api/v1/admin/auth/logout`
  - `GET /api/v1/admin/auth/me`
  - `POST /api/v1/admin/auth/password/forgot`
  - `POST /api/v1/admin/auth/password/reset`
- **Security Requirements:** Fixed cookie path: `Path=/api/v1/admin; Secure; HttpOnly; SameSite=Lax`. Timing-safe password checks (`password_verify`). Single-use recovery codes.
- **Allowed Inter-Module Dependencies:** `Core`, `Users`, `Email`, `Audit`.
- **Error Behavior:** Generic HTTP 401 error messages ("Invalid credentials") preventing account enumeration.
- **Relevant Tests:** `TEST-ADM-002`, `TEST-ADM-003`, `TEST-SEC-002`.
- **Owning Implementation Phase:** **Phase 6 (Auth, Session & Security Infrastructure)**.

---

### Module 03: Authorization & RBAC Engine (`Voak\Health\Modules\RBAC`)
- **Responsibilities:** 5-role permission evaluation, granular action key checks (45 canonical permission keys), middleware authorization gates (`AuthorizePermission`), object-level access validation.
- **Public Service Contract:**
  - `RbacService::userHasPermission(int $userId, string $permissionKey): bool`
  - `RbacService::assertPermission(int $userId, string $permissionKey): void`
  - `RbacService::getUserPermissions(int $userId): array`
- **Database Dependencies:** `roles`, `permissions`, `role_permissions`, `user_roles`.
- **API Endpoints:** Internal middleware enforcement; `GET /api/v1/admin/auth/me` (includes permissions).
- **Security Requirements:** Fail-closed default. Zero access without explicit permission row (`FR-ADM-004`).
- **Allowed Inter-Module Dependencies:** `Core`, `Audit`.
- **Error Behavior:** Emits HTTP 403 Forbidden with `{error: "PERMISSION_DENIED", required: "..."}`.
- **Relevant Tests:** `TEST-ADM-004`.
- **Owning Implementation Phase:** **Phase 6 (Auth, Session & Security Infrastructure)**.

---

### Module 04: User Administration (`Voak\Health\Modules\Users`)
- **Responsibilities:** Administrative staff user CRUD, role assignments, account suspension, audit logging of privilege changes.
- **Public Service Contract:**
  - `UserService::createUser(CreateUserDTO $dto): AdminUser`
  - `UserService::updateUser(int $id, UpdateUserDTO $dto): AdminUser`
  - `UserService::deactivateUser(int $id): void`
- **Database Dependencies:** `users`, `user_roles`.
- **API Endpoints:** `GET|POST /api/v1/admin/users`, `GET|PUT|DELETE /api/v1/admin/users/{id}`.
- **Security Requirements:** Restricted strictly to `Super Admin` role (`users.manage`). Cannot deactivate own account.
- **Allowed Inter-Module Dependencies:** `Core`, `RBAC`, `Audit`.
- **Error Behavior:** HTTP 409 on duplicate email; HTTP 422 on password strength failure.
- **Relevant Tests:** `TEST-ADM-004`.
- **Owning Implementation Phase:** **Phase 6 / 26 (User Management)**.

---

### Module 05: Static Pages & Content CMS (`Voak\Health\Modules\Pages`)
- **Responsibilities:** Page content management for static routes (About, Why Choose Voak, How We Work, Quality & Compliance, Privacy, Terms), revision snapshotting, rollback, optimistic concurrency control.
- **Public Service Contract:**
  - `PageService::getPageBySlug(string $slug): ?PageDTO`
  - `PageService::updatePage(int $id, UpdatePageDTO $dto): PageDTO`
  - `PageService::restoreRevision(int $pageId, int $revisionId): PageDTO`
- **Database Dependencies:** `pages`, `content_revisions`.
- **API Endpoints:**
  - Public: `GET /api/v1/public/pages/{slug}`
  - Admin: `GET /api/v1/admin/pages`, `PUT /api/v1/admin/pages/{id}`, `POST /api/v1/admin/pages/{id}/revert`
- **Security Requirements:** HTML sanitization via native PHP `htmlspecialchars()` with UTF-8 flags and PHP HTMLPurifier for rich text fields. Optimistic locking via `updated_at`.
- **Allowed Inter-Module Dependencies:** `Core`, `Audit`, `SEO`.
- **Error Behavior:** HTTP 409 Conflict on concurrency mismatch; HTTP 404 on missing slug.
- **Relevant Tests:** `TEST-PUB-009`, `TEST-ADM-005`, `TEST-WF-005`.
- **Owning Implementation Phase:** **Phase 7 (Content & Pages Backend)**.

---

### Module 06: Homepage Customizer (`Voak\Health\Modules\Homepage`)
- **Responsibilities:** Homepage hero headline customization, bento-grid card ordering, accent color bindings, social proof ribbon toggles.
- **Public Service Contract:**
  - `HomepageService::getPublishedConfig(): HomepageConfigDTO`
  - `HomepageService::updateConfig(HomepageConfigDTO $dto): void`
- **Database Dependencies:** `homepage_configs`, `partners`, `testimonials`.
- **API Endpoints:**
  - Public: `GET /api/v1/public/homepage`
  - Admin: `GET|PUT /api/v1/admin/homepage`
- **Security Requirements:** Strict input string escaping. Cache invalidation on update (`FR-ADM-006`).
- **Allowed Inter-Module Dependencies:** `Core`, `Audit`, `SEO`.
- **Error Behavior:** Falls back to hardcoded default brand copy if database record is unseeded.
- **Relevant Tests:** `TEST-PUB-002`, `TEST-PUB-003`, `TEST-PUB-004`, `TEST-ADM-006`.
- **Owning Implementation Phase:** **Phase 7 (Content & Pages Backend)**.

---

### Module 07: Services Catalog Management (`Voak\Health\Modules\Services`)
- **Responsibilities:** 6 B2B healthcare service pillars CRUD, category filtering, deliverable specifications, SLA matrices, slug management, automated 301 redirect generation on rename.
- **Public Service Contract:**
  - `ServiceService::getAllPublished(array $filters): array`
  - `ServiceService::getBySlug(string $slug): ?ServiceDetailDTO`
  - `ServiceService::createService(ServiceDTO $dto): Service`
  - `ServiceService::updateService(int $id, ServiceDTO $dto): Service`
  - `ServiceService::deleteService(int $id): void`
- **Database Dependencies:** `services`, `service_industry_mappings`, `url_redirects`.
- **API Endpoints:**
  - Public: `GET /api/v1/public/services`, `GET /api/v1/public/services/{slug}`
  - Admin: `GET|POST /api/v1/admin/services`, `GET|PUT|DELETE /api/v1/admin/services/{id}`
- **Security Requirements:** Slug collision prevention against reserved system routes (`/about`, `/admin`, `/api`). Auto-registers 301 redirects upon slug mutation (`FR-ADM-007`, `FR-WF-006`).
- **Allowed Inter-Module Dependencies:** `Core`, `SEO`, `Audit`.
- **Error Behavior:** Authentic HTTP 404 header on missing slug; HTTP 422 on duplicate slug.
- **Relevant Tests:** `TEST-PUB-005`, `TEST-PUB-006`, `TEST-ADM-007`, `TEST-WF-006`.
- **Owning Implementation Phase:** **Phase 8 (Services Domain Backend)**.

---

### Module 08: Industries Catalog Management (`Voak\Health\Modules\Industries`)
- **Responsibilities:** 6 healthcare sector verticals (Hospitals, GP Clinics, Aged Care, Allied Health, Diagnostics, Corporate Health), regulatory burden mapping, service associations.
- **Public Service Contract:**
  - `IndustryService::getAllPublished(): array`
  - `IndustryService::getBySlug(string $slug): ?IndustryDetailDTO`
  - `IndustryService::saveIndustry(IndustryDTO $dto): Industry`
- **Database Dependencies:** `industries`, `service_industry_mappings`.
- **API Endpoints:**
  - Public: `GET /api/v1/public/industries`, `GET /api/v1/public/industries/{slug}`
  - Admin: `GET|POST /api/v1/admin/industries`, `GET|PUT|DELETE /api/v1/admin/industries/{id}`
- **Security Requirements:** Standard CRUD authorization (`industries.manage`). Authentic 404 headers.
- **Allowed Inter-Module Dependencies:** `Core`, `Services`, `Audit`.
- **Error Behavior:** HTTP 404 on missing slug; HTTP 422 on missing required title.
- **Relevant Tests:** `TEST-PUB-007`, `TEST-PUB-008`, `TEST-ADM-008`.
- **Owning Implementation Phase:** **Phase 9 (Industries Domain Backend)**.

---

### Module 09: Media Library & Asset Optimization (`Voak\Health\Modules\Media`)
- **Responsibilities:** Public marketing asset upload, automated WebP conversion (max 1920px, 82% quality), responsive thumbnail generation, mandatory alt-text enforcement, referential deletion protection.
- **Public Service Contract:**
  - `MediaService::uploadImage(UploadedFile $file, string $altText): MediaAssetDTO`
  - `MediaService::deleteImage(int $id): bool`
  - `MediaService::assertAssetNotReferenced(int $id): void`
- **Database Dependencies:** `media_assets`.
- **API Endpoints:**
  - Admin: `GET|POST /api/v1/admin/media`, `DELETE /api/v1/admin/media/{id}`
- **Security Requirements:** MIME magic byte verification (`image/jpeg`, `image/png`, `image/webp`). Rejection of scripts and SVG with executable payloads. File stored under `public_html/assets/uploads/`.
- **Allowed Inter-Module Dependencies:** `Core`, `Audit`.
- **Error Behavior:** Blocks deletion with HTTP 409 Conflict if asset is referenced by any Page or Service (`FR-ADM-009`).
- **Relevant Tests:** `TEST-ADM-009`.
- **Owning Implementation Phase:** **Phase 10 (Media Library Backend)**.

---

### Module 10: Contact Inquiries CRM (`Voak\Health\Modules\Contact`)
- **Responsibilities:** Inbound contact inquiry submission, Cloudflare Turnstile token validation, honeypot trapping, reference code generation (`CNT-YYYYMMDD-XXXX`), triage CRM workflow state machine.
- **Public Service Contract:**
  - `ContactService::submitInquiry(ContactInquiryDTO $dto): SubmissionResult`
  - `ContactService::transitionStatus(int $id, string $newStatus, string $note): void`
- **Database Dependencies:** `contact_inquiries`, `email_queue`.
- **API Endpoints:**
  - Public: `POST /api/v1/public/contact`
  - Admin: `GET /api/v1/admin/inquiries/contact`, `PATCH /api/v1/admin/inquiries/contact/{id}/status`
- **Security Requirements:** Server-authoritative E.164 phone validation, Turnstile verification, honeypot drop, rate limit: max 5 submissions / hour / IP.
- **Allowed Inter-Module Dependencies:** `Core`, `Email`, `Audit`.
- **Error Behavior:** Silent 200 or 400 rejection on honeypot pop; HTTP 422 on invalid status transition.
- **Relevant Tests:** `TEST-PUB-019`, `TEST-ADM-010`, `TEST-WF-001`.
- **Owning Implementation Phase:** **Phase 11 (Contact CRM Backend)**.

---

### Module 11: Strategic Partnerships CRM (`Voak\Health\Modules\Partnerships`)
- **Responsibilities:** Enterprise consortium and subcontracting intake, organization tiering, collaboration scope multi-select, milestone state machine (`NEW_INQUIRY` $\rightarrow$ `ACTIVE`).
- **Public Service Contract:**
  - `PartnershipService::submitInquiry(PartnershipDTO $dto): SubmissionResult`
  - `PartnershipService::updateMilestone(int $id, string $milestone, ?string $notes): void`
- **Database Dependencies:** `partnership_inquiries`, `email_queue`.
- **API Endpoints:**
  - Public: `POST /api/v1/public/partnerships/inquire`
  - Admin: `GET /api/v1/admin/partnerships`, `PATCH /api/v1/admin/partnerships/{id}/status`
- **Security Requirements:** Restricted to `Super Admin` and `BD Manager` roles (`partnerships.manage`). Turnstile verification.
- **Allowed Inter-Module Dependencies:** `Core`, `Email`, `Audit`.
- **Error Behavior:** HTTP 403 on unauthorized access; reference code formatted `PTN-YYYYMMDD-XXXX`.
- **Relevant Tests:** `TEST-PUB-020`, `TEST-ADM-011`, `TEST-WF-002`.
- **Owning Implementation Phase:** **Phase 12 (Proposals & Partnerships CRM Backend)**.

---

### Module 12: Enterprise Proposals Desk & Quarantine (`Voak\Health\Modules\Proposals`)
- **Responsibilities:** 4-step RFP wizard ingestion, tender document attachment (max 15MB, PDF/DOCX), isolated quarantine storage outside web root, authenticated binary streaming proxy, proposal lifecycle FSM.
- **Public Service Contract:**
  - `ProposalService::submitProposal(ProposalDTO $dto, UploadedFile $file): SubmissionResult`
  - `ProposalService::streamAttachment(int $id, AdminUser $user): BinaryStreamResponse`
  - `ProposalService::updateStatus(int $id, string $status, ?float $estimatedValue): void`
- **Database Dependencies:** `proposals`, `proposal_services`, `email_queue`.
- **API Endpoints:**
  - Public: `POST /api/v1/public/proposals/submit`
  - Admin: `GET /api/v1/admin/proposals`, `GET /api/v1/admin/proposals/{id}/download`, `PATCH /api/v1/admin/proposals/{id}/status`
- **Security Requirements:** Strict MIME inspection (`application/pdf`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document`). Stored outside web root in `storage/private/quarantine/`. Streaming requires `proposals.view` permission (`FR-SEC-003`, `FR-SEC-004`).
- **Allowed Inter-Module Dependencies:** `Core`, `Services`, `Email`, `Audit`.
- **Error Behavior:** Direct URL requests receive HTTP 403; HTTP 422 on MIME mismatch or file > 15MB.
- **Relevant Tests:** `TEST-PUB-021`, `TEST-ADM-012`, `TEST-WF-003`, `TEST-SEC-003`, `TEST-SEC-004`.
- **Owning Implementation Phase:** **Phase 12 (Proposals & Partnerships CRM Backend)**.

---

### Module 13: Careers & Candidate Recruitment (`Voak\Health\Modules\Careers`)
- **Responsibilities:** Job postings board CRUD, vacancy status toggles, candidate application processing, resume PDF upload quarantine (max 10MB), authenticated resume streaming, hiring state machine (`SUBMITTED` $\rightarrow$ `HIRED`).
- **Public Service Contract:**
  - `CareerService::getActiveJobs(array $filters): array`
  - `CareerService::submitApplication(ApplicationDTO $dto, UploadedFile $resume): SubmissionResult`
  - `CareerService::streamResume(int $id, AdminUser $user): BinaryStreamResponse`
  - `CareerService::advanceCandidate(int $id, string $status, string $feedback): void`
- **Database Dependencies:** `job_postings`, `career_applications`.
- **API Endpoints:**
  - Public: `GET /api/v1/public/jobs`, `POST /api/v1/public/careers/apply`
  - Admin: `GET|POST /api/v1/admin/jobs`, `GET /api/v1/admin/applications`, `GET /api/v1/admin/applications/{id}/download`, `PATCH /api/v1/admin/applications/{id}/status`
- **Security Requirements:** Resumes quarantined in `storage/private/quarantine/`. Streaming requires `careers.manage` (HR / Super Admin). Candidate privacy retention flag (`FR-SEC-004`).
- **Allowed Inter-Module Dependencies:** `Core`, `Email`, `Audit`.
- **Error Behavior:** Rejects executable files disguised as `.pdf` with HTTP 422; logs download events.
- **Relevant Tests:** `TEST-PUB-014`, `TEST-PUB-015`, `TEST-ADM-014`, `TEST-ADM-015`, `TEST-WF-004`.
- **Owning Implementation Phase:** **Phase 14 (Careers & Recruitment Backend)**.

---

### Module 14: Insights & Thought Leadership Articles (`Voak\Health\Modules\Articles`)
- **Responsibilities:** Whitepaper and article publishing, reading time auto-calculation (word count / 200 wpm), tag management, markdown sanitization, public paginated feed.
- **Public Service Contract:**
  - `ArticleService::getPublishedArticles(int $page, int $perPage, ?string $category): PaginatedArticlesDTO`
  - `ArticleService::getBySlug(string $slug): ?ArticleDetailDTO`
  - `ArticleService::saveArticle(ArticleDTO $dto): Article`
- **Database Dependencies:** `articles`.
- **API Endpoints:**
  - Public: `GET /api/v1/public/articles`, `GET /api/v1/public/articles/{slug}`
  - Admin: `GET|POST /api/v1/admin/articles`, `GET|PUT|DELETE /api/v1/admin/articles/{id}`
- **Security Requirements:** Content sanitization via native PHP `htmlspecialchars()` and PHP HTMLPurifier; slug collision prevention.
- **Allowed Inter-Module Dependencies:** `Core`, `SEO`, `Audit`.
- **Error Behavior:** HTTP 404 on draft or non-existent article slug.
- **Relevant Tests:** `TEST-PUB-016`, `TEST-PUB-017`, `TEST-ADM-017`.
- **Owning Implementation Phase:** **Phase 15 (Case Studies & Resources Backend)**.

---

### Module 15: Case Studies & Social Proof (`Voak\Health\Modules\CaseStudies`)
- **Responsibilities:** Enterprise case studies CRUD (Problem-Intervention-Outcome format), client legal consent release attachment verification, total public DOM collapse for unverified proof.
- **Public Service Contract:**
  - `CaseStudyService::getVerifiedCaseStudies(?string $sector): array`
  - `CaseStudyService::publishCaseStudy(int $id, bool $consentVerified): void`
- **Database Dependencies:** `case_studies`, `partners`, `testimonials`.
- **API Endpoints:**
  - Public: `GET /api/v1/public/case-studies`
  - Admin: `GET|POST /api/v1/admin/case-studies`, `PUT /api/v1/admin/case-studies/{id}`
- **Security Requirements:** Mandatory `client_consent_verified = 1` before publication allowed. Unverified case studies return HTTP 404 publicly (`FR-ADM-016`).
- **Allowed Inter-Module Dependencies:** `Core`, `Audit`.
- **Error Behavior:** HTTP 422 if attempting to publish without recorded consent document reference.
- **Relevant Tests:** `TEST-PUB-013`, `TEST-ADM-016`.
- **Owning Implementation Phase:** **Phase 15 (Case Studies & Resources Backend)**.

---

### Module 16: FAQ Support Center (`Voak\Health\Modules\FAQs`)
- **Responsibilities:** Categorized procurement, operational, and clinical compliance FAQ accordions, search indexing, display ordering.
- **Public Service Contract:**
  - `FaqService::getPublishedFaqs(?string $category): array`
  - `FaqService::saveFaq(FaqDTO $dto): Faq`
- **Database Dependencies:** `faqs`.
- **API Endpoints:**
  - Public: `GET /api/v1/public/faqs`
  - Admin: `GET|POST /api/v1/admin/faqs`, `PUT|DELETE /api/v1/admin/faqs/{id}`
- **Security Requirements:** Content escaping; XSS protection.
- **Allowed Inter-Module Dependencies:** `Core`, `Audit`.
- **Error Behavior:** Empty array on non-existent category query.
- **Relevant Tests:** `TEST-PUB-018`.
- **Owning Implementation Phase:** **Phase 7 / 24 (Content & Public FAQ Views)**.

---

### Module 17: SEO Engine & Redirect Architecture (`Voak\Health\Modules\SEO`)
- **Responsibilities:** Dynamic XML sitemap generator (`/sitemap.xml`), automated 301 redirect engine (`url_redirects`), circular loop detection, Schema.org JSON-LD structured data generators, meta tag management.
- **Public Service Contract:**
  - `SeoService::generateSitemapXml(): string`
  - `SeoService::resolveRedirect(string $requestUri): ?RedirectRule`
  - `SeoService::registerRedirect(string $source, string $target, int $status = 301): void`
  - `SeoService::getStructuredData(string $route, array $context): array`
- **Database Dependencies:** `url_redirects`, `seo_routes`, `services`, `industries`, `articles`, `pages`.
- **API Endpoints:**
  - Public: `GET /sitemap.xml`
  - Admin: `GET|POST /api/v1/admin/seo/routes`, `POST /api/v1/admin/seo/rebuild-sitemap`
- **Security Requirements:** Excludes private, draft, and administrative routes from sitemap. Validates canonical URLs (`FR-SEO-001..004`).
- **Allowed Inter-Module Dependencies:** `Core`, `Audit`.
- **Error Behavior:** Circular redirect detection flattens redirect chains to 1 hop; emits HTTP 301.
- **Relevant Tests:** `TEST-SEO-001`, `TEST-SEO-002`, `TEST-SEO-003`, `TEST-SEO-004`, `TEST-WF-006`.
- **Owning Implementation Phase:** **Phase 16 (SEO & Dynamic Sitemap Backend)**.

---

### Module 18: Transactional Email & Outbox Telemetry (`Voak\Health\Modules\Email`)
- **Responsibilities:** Durable outbox (`email_queue`), atomic batch claiming, exponential backoff, bounded synchronous fast-path (max 4s) for urgent auth, template variable interpolation, Module 12 Outbox Telemetry & manual retry console.
- **Public Service Contract:**
  - `EmailService::enqueue(string $templateKey, string $recipientEmail, string $recipientName, array $payload): int`
  - `EmailService::sendUrgentFastPath(string $templateKey, string $recipientEmail, array $payload): bool`
  - `EmailService::processQueueBatch(int $limit = 20): ProcessResult`
  - `EmailService::retryMessage(int $queueId): void`
- **Database Dependencies:** `email_queue`, `email_templates`.
- **API Endpoints:**
  - Admin: `GET|PUT /api/v1/admin/email-templates[/{id}]`, `GET /api/v1/admin/email-queue`, `POST /api/v1/admin/email-queue/{id}/retry`
- **Security Requirements:** Socket timeouts: 5s connection, 10s write. Mandatory placeholder checks (`{{legal_entity_name}}`, `{{reference_code}}`). Zero raw credential exposure (`FR-ADM-013`).
- **Allowed Inter-Module Dependencies:** `Core`, `Audit`.
- **Error Behavior:** Records SMTP errors in `error_message`; increments attempts up to 3; flags as `FAILED`.
- **Relevant Tests:** `TEST-ADM-013`.
- **Owning Implementation Phase:** **Phase 13 (Email Notification Backend)**.

---

### Module 19: System Configuration & Identity (`Voak\Health\Modules\Settings`)
- **Responsibilities:** Global key-value system settings management, configuration variables (`{{legal_entity_name}}`, `{{registration_number}}`, `{{registered_address}}`), SMTP socket testing tool, international phone format settings.
- **Public Service Contract:**
  - `SettingsService::getPublicSettings(): array`
  - `SettingsService::getAllSettings(): array`
  - `SettingsService::updateSettings(array $settings): void`
  - `SettingsService::testSmtpConnection(): SmtpTestResult`
- **Database Dependencies:** `system_settings`.
- **API Endpoints:**
  - Public: `GET /api/v1/public/settings/header`
  - Admin: `GET|PUT /api/v1/admin/settings`, `POST /api/v1/admin/settings/test-smtp`
- **Security Requirements:** Restricted strictly to `Super Admin` role (`settings.manage`).
- **Allowed Inter-Module Dependencies:** `Core`, `Email`, `Audit`.
- **Error Behavior:** HTTP 403 on non-Super Admin; safe fallback rendering when entity variables empty.
- **Relevant Tests:** `TEST-ADM-018`.
- **Owning Implementation Phase:** **Phase 7 / 26 (Settings Management)**.

---

### Module 20: Immutable Audit Ledger & Telemetry (`Voak\Health\Modules\Audit`)
- **Responsibilities:** Cryptographic SHA-256 row hash chained audit logging (`audit_logs`), before/after JSON diff tracking, tamper-evident verification, compliance search and filtering desk.
- **Public Service Contract:**
  - `AuditService::log(string $domain, string $action, ?int $userId, ?string $entityType, ?int $entityId, array $diff): void`
  - `AuditService::verifyChainIntegrity(): AuditVerificationReport`
  - `AuditService::queryLogs(AuditFilterDTO $filter): PaginatedAuditDTO`
- **Database Dependencies:** `audit_logs`.
- **API Endpoints:**
  - Admin: `GET /api/v1/admin/audit-logs`, `GET /api/v1/admin/audit-logs/{id}`
- **Security Requirements:** Append-only ledger. Zero `UPDATE` or `DELETE` methods exposed in repository. Row hash:
  $$\text{row\_hash} = \text{SHA256}(\text{prev\_hash} \parallel \text{timestamp} \parallel \text{user\_id} \parallel \text{action} \parallel \text{diff\_json})$$
- **Allowed Inter-Module Dependencies:** `Core`.
- **Error Behavior:** Direct SQL tampering breaks chain verification and flags alert in Module 19 console (`FR-ADM-019`).
- **Relevant Tests:** `TEST-ADM-019`.
- **Owning Implementation Phase:** **Phase 17 (Administrative Audit Backend)**.

---

## 4. Module Dependency Matrix & Verification

```
+====================================================================================================+
| DEPENDENCY MATRIX: All 20 PHP Backend Modules Adhere Strictly to Zero-Cycle Rules                  |
+====================================================================================================+
| Module Name          | Depends On Modules                       | Depended On By                    |
+----------------------+------------------------------------------+-----------------------------------+
| 01. Core             | None (Base Kernel Layer)                 | All Modules (02 - 20)             |
| 02. Auth             | Core, Users, Email, Audit                | RBAC, Admin Controllers           |
| 03. RBAC             | Core, Audit                              | Auth, Admin Controllers           |
| 04. Users            | Core, RBAC, Audit                        | Auth, Admin Controllers           |
| 05. Pages            | Core, SEO, Audit                         | Pre-Renderer, Admin Controllers   |
| 06. Homepage         | Core, SEO, Audit                         | Pre-Renderer, Admin Controllers   |
| 07. Services         | Core, SEO, Audit                         | Pre-Renderer, Industries, Admin   |
| 08. Industries       | Core, Services, Audit                    | Pre-Renderer, Admin Controllers   |
| 09. Media            | Core, Audit                              | Pages, Services, Articles, Admin  |
| 10. Contact          | Core, Email, Audit                       | Public & Admin Controllers        |
| 11. Partnerships     | Core, Email, Audit                       | Public & Admin Controllers        |
| 12. Proposals        | Core, Services, Email, Audit             | Public & Admin Controllers        |
| 13. Careers          | Core, Email, Audit                       | Public & Admin Controllers        |
| 14. Articles         | Core, SEO, Audit                         | Public & Admin Controllers        |
| 15. Case Studies     | Core, Audit                              | Public & Admin Controllers        |
| 16. FAQs             | Core, Audit                              | Public & Admin Controllers        |
| 17. SEO              | Core, Audit                              | Pages, Services, Articles, Admin  |
| 18. Email            | Core, Audit                              | Auth, Contact, Proposals, etc.    |
| 19. Settings         | Core, Email, Audit                       | Pre-Renderer, Admin Controllers   |
| 20. Audit            | Core                                     | All Domain Modules (02 - 19)      |
+====================================================================================================+
```
