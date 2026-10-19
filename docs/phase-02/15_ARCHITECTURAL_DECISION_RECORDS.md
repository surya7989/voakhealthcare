# Architectural Decision Records (ADRs)

**Document Reference:** `docs/phase-02/15_ARCHITECTURAL_DECISION_RECORDS.md`  
**Phase:** Phase 2 of 31 — Complete System Architecture  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Executing Role:** Principal Enterprise Software Architect & Technical Governance Board  
**Baseline Authority:** Accepted Phase 0 & Phase 1 Specifications  
**Status:** **AUTHORITATIVE ARCHITECTURAL RECORDS** (ADR-001 through ADR-010 Accepted)

---

## Index of Architectural Decision Records

| ADR Number | Title | Status | Owning Roadmap Phase |
| :---: | :--- | :---: | :---: |
| **ADR-001** | PHP Backend Architectural Pattern: Modular Service-Repository Engine | **ACCEPTED** | Phase 4 (Backend Boot) |
| **ADR-002** | MySQL Relational Schema & Forward-Only CLI Migration Strategy | **ACCEPTED** | Phase 5 (Database Schema) |
| **ADR-003** | REST API Versioning (`/api/v1`) & Normalized Error Contracts | **ACCEPTED** | Phase 4 / 6 (API Core) |
| **ADR-004** | Session Authentication, Cookie Path Fix (`Path=/api/v1/admin`), CSRF & RBAC | **ACCEPTED** | Phase 6 (Security Core) |
| **ADR-005** | Hybrid Semantic Pre-Rendering (`#ssr-fallback`) & React `createRoot` Mounting | **ACCEPTED** | Phase 16 / 20 / 29 (SEO Core) |
| **ADR-006** | Hostinger-Compatible Durable Email Outbox (`email_queue`) & Dispatch Architecture | **ACCEPTED** | Phase 13 (Email Backend) |
| **ADR-007** | Segregated Storage: Public WebP Assets vs. Quarantined Document Streaming | **ACCEPTED** | Phase 10 (Media & Storage) |
| **ADR-008** | CMS Publishing Lifecycle, Optimistic Concurrency & Safe 301 Redirect Generation | **ACCEPTED** | Phase 7 / 8 / 16 (CMS Core) |
| **ADR-009** | Unified Exception Handling, Correlation Diagnostics & Structured JSON Logging | **ACCEPTED** | Phase 4 / 17 (Core Telemetry) |
| **ADR-010** | Hostinger Shared Hosting Deployment Topology, Security Headers & Release Strategy | **ACCEPTED** | Phase 31 (Hostinger Go-Live) |

---

## ADR-001: PHP Backend Architectural Pattern: Modular Service-Repository Engine

- **Context:** The platform backend must run on Hostinger shared hosting running PHP 8.x, supporting 20 cohesive domain subsystems (Auth, CMS, Inbound CRM, RFPs, Recruitment, SEO, Email, Audit) without requiring heavy frameworks (e.g., full-stack Laravel/Symfony) that introduce file count and memory overhead.
- **Constraints:** Hostinger shared hosting execution limits (30s timeout, memory limits), strict PSR-4 autoloading, native PDO, zero external daemons.
- **Options Evaluated:**
  1. *Heavyweight Framework (Full Laravel):* Rich ecosystem, but exceeds inode limits, introduces vendor bloat, and encourages reliance on background queue daemons.
  2. *Monolithic Script Architecture:* Single procedural scripts; unmaintainable, untestable, severe security risks.
  3. *Modular Service-Repository Engine (Custom Core + Micro-Router):* Lightweight, strict typing, DI container, explicit domain boundaries, fast execution (< 20ms boot).
- **Decision:** Adopt the **Modular Service-Repository Engine**. Structure backend into 20 domain modules under `Voak\Health\Modules\<Name>\` with strict separation between Controllers, Services, and Repositories.
- **Trade-offs:** Requires hand-crafting standard service contracts and repositories rather than relying on framework scaffolding.
- **Risks:** Developers must adhere strictly to dependency injection rules to avoid cyclic dependencies.
- **Alternatives Rejected:** Full Laravel (rejected due to hosting constraints); procedural scripts (rejected for quality).
- **Verification Strategy:** PHPStan Level 8 static analysis and PHPUnit integration tests in Phase 4.
- **Implementation Phase:** **Phase 4 (Core Backend & Environment Boot)**.

---

## ADR-002: MySQL Relational Schema & Forward-Only CLI Migration Strategy

- **Context:** The platform requires relational data persistence across 28 reconciled entities. MySQL DDL statements execute implicit commits, preventing automatic transactional rollbacks of arbitrary multi-table DDL.
- **Constraints:** MySQL 8.x InnoDB, utf8mb4 collation, shared hosting database user privileges, forward-only migrations.
- **Options Evaluated:**
  1. *Automatic Reversible Multi-Table DDL:* Promising full rollback for any failure; rejected because MySQL DDL commits implicitly.
  2. *Forward-Only Atomic Migration Strategy:* Single-table atomic migration steps with explicit tested `up()` and `down()` scripts, preflight backups, and execution tracking in `schema_migrations`.
- **Decision:** Implement a **Forward-Only CLI Migration Engine** (`php cli/migrate.php`). Web-accessible execution is strictly forbidden.
- **Trade-offs:** Requires careful single-table migration authoring and backup verification before running.
- **Risks:** Unhandled migration failure leaves partial schema if author groups multiple DDLs into a single file. Mitigated by strict single-table per migration rule.
- **Alternatives Rejected:** Web-based migration runners (security vulnerability, timeout risk).
- **Verification Strategy:** CLI migration execution and rollback test in Phase 5 (`TEST-SEC-007`).
- **Implementation Phase:** **Phase 5 (MySQL Database Schema)**.

---

## ADR-003: REST API Versioning (`/api/v1`) & Normalized Error Contracts

- **Context:** The frontend applications (Public React & Admin React) communicate with the PHP backend via REST. A consistent versioning scheme and error envelope are mandatory.
- **Constraints:** Public and protected endpoints coexist under one domain.
- **Options Evaluated:**
  1. *Header Versioning (`Accept: application/vnd.voak.v1+json`):* Clean URLs, but difficult to test via browser and complex to configure with caching.
  2. *URI Path Versioning (`/api/v1/*`):* Standard, explicit, easy to route via Apache, highly discoverable.
- **Decision:** Standardize all endpoints under **URI Path Versioning (`/api/v1/*`)**. All responses adhere to the standard JSON envelopes (`{success: true, data: ...}` or `{success: false, error: {code, message, reference_id}}`).
- **Trade-offs:** Updating major API versions in the future requires creating a new `/api/v2/` namespace.
- **Risks:** None identified.
- **Alternatives Rejected:** Unversioned `/api/*` (causes breaking changes across updates).
- **Verification Strategy:** OpenAPI 3.1 JSON validation and PHPUnit contract tests.
- **Implementation Phase:** **Phase 4 / Phase 6**.

---

## ADR-004: Session Authentication, Cookie Path Fix (`Path=/api/v1/admin`), CSRF & RBAC

- **Context:** Independent review identified that `Path=/api/admin` fails to match requests to `/api/v1/admin/*` under RFC 6265 path-matching rules.
- **Constraints:** Secure, HttpOnly, SameSite=Lax cookie required. 5 roles, 45 canonical permission keys, RFC 6238 TOTP MFA, 15-minute idle timeout.
- **Options Evaluated:**
  1. *Root Cookie Path (`Path=/`):* Transmits administrative session cookies on every public request, static image fetch, and external asset load; severe security exposure.
  2. *Old Path (`Path=/api/admin`):* Broken RFC 6265 matching; blocks all authenticated requests.
  3. *Exact Versioned Path (`Path=/api/v1/admin`):* Matches `/api/v1/admin/*` precisely under RFC 6265; completely isolates public site and public APIs from credential leakage.
- **Decision:** Codify **`Path=/api/v1/admin; Secure; HttpOnly; SameSite=Lax`**. Enforce 15-minute idle session timeout and 8-hour absolute expiry. Enforce CSRF bootstrap (`GET /api/v1/admin/auth/csrf`) and synchronizer tokens on all mutations. Enforce 5-role RBAC server-side via `AuthorizePermission` middleware across all 45 canonical permissions.
- **Trade-offs:** The Admin React app must be hosted or configured to make API calls to `/api/v1/admin/*` with `credentials: 'include'`.
- **Risks:** Misconfigured proxy stripping cookie headers. Mitigated by deployment smoke tests.
- **Alternatives Rejected:** JWT in LocalStorage (vulnerable to XSS theft; rejected).
- **Verification Strategy:** Automated RFC 6265 path matching test and RBAC test suite (`TEST-ADM-002`, `TEST-ADM-004`, `TEST-SEC-001`, `TEST-SEC-002`).
- **Implementation Phase:** **Phase 6 (Auth, Session & Security Infrastructure)**.

---

## ADR-005: Hybrid Semantic Pre-Rendering (`#ssr-fallback`) & Controlled Progressive Takeover

- **Context:** The public website must be discoverable by search engine crawlers without running Node.js SSR on Hostinger shared hosting.
- **Constraints:** No persistent Node.js server; zero hydration mismatch errors; zero cumulative layout shift (CLS < 0.1).
- **Options Evaluated:**
  1. *Pure Client SPA (empty `#root`):* Easy to build, but search engines penalize or fail to index JavaScript-only content properly.
  2. *React `hydrateRoot` with PHP Pre-Render:* Results in severe React hydration mismatch warnings and DOM replacement bugs because PHP markup does not match React SSR byte-for-byte.
  3. *PHP Semantic Pre-Rendering with Controlled Progressive Takeover:* PHP generates semantic HTML directly into `<article id="ssr-fallback">`. React mounts into `<div id="root"></div>`. Critical CSS (`body.react-mounted #ssr-fallback { display: none !important; }`) gracefully transitions fallback only after React signals successful layout mount.
- **Decision:** Implement **PHP Semantic Pre-Rendering with Controlled Progressive Takeover**.
- **Trade-offs:** Both PHP pre-renderer and React components require synchronized design templates.
- **Risks:** Visual flashing if CSS is un-optimized. Mitigated by inlining critical CSS container rules in `<head>` and coupling transition to `body.react-mounted`.
- **Alternatives Rejected:** Node.js SSR (rejected due to hosting constraints); Pure SPA (rejected due to SEO requirements).
- **Verification Strategy:** Playwright crawl tests without JavaScript and Lighthouse CLS audit (`TEST-SEO-001`).
- **Implementation Phase:** **Phase 16 / Phase 20 / Phase 29**.

---

## ADR-006: Hostinger-Compatible Durable Email Outbox (`email_queue`) & Dispatch Architecture

- **Context:** Hostinger shared hosting limits execution times (30s) and does not guarantee continuous 1-minute cron execution.
- **Constraints:** Cannot assume persistent worker daemons; cannot expose unauthenticated public web cron URLs.
- **Options Evaluated:**
  1. *Synchronous SMTP on Every Web Request:* Blocks user HTTP requests, causes 504 timeouts on SMTP latency.
  2. *Non-Blocking Hooks (`fastcgi_finish_request`):* Unreliable across shared hosting FastCGI environments.
  3. *Tri-Tier Delivery Architecture:* Bounded synchronous fast-path (max 4s) for urgent auth; durable `email_queue` outbox with atomic claiming for general mail; Module 12 Outbox Telemetry & manual retry console.
- **Decision:** Adopt the **Tri-Tier Notification Dispatch Architecture**.
- **Trade-offs:** General inquiries may experience a few minutes dispatch latency depending on cron interval.
- **Risks:** Cron job misconfigured on Hostinger. Mitigated by Module 12 manual retry console and admin activity fallback hooks.
- **Alternatives Rejected:** Unauthenticated public web cron (critical security vulnerability; rejected).
- **Verification Strategy:** Outbox atomic claiming test and SMTP socket timeout test (`TEST-ADM-013`).
- **Implementation Phase:** **Phase 13 (Email Notification Backend)**.

---

## ADR-007: Segregated Storage: Public WebP Assets vs. Quarantined Document Streaming

- **Context:** Platform handles public marketing images as well as sensitive candidate resumes and procurement tender files.
- **Constraints:** Private files must never be directly accessible via web browsers.
- **Options Evaluated:**
  1. *All Files in `public_html/uploads/` with Random Names:* Insecure; files can be indexed by search engines or enumerated.
  2. *Segregated Storage Enclaves:* Public WebP images stored in `public_html/assets/uploads/`; sensitive resumes and RFPs stored **outside web root** in `storage/private/quarantine/` with chmod 0600, streamed strictly through authenticated PHP controllers.
- **Decision:** Implement **Segregated Storage Enclaves with Authenticated Binary Streaming Proxies**.
- **Trade-offs:** Downloading resumes or tender files incurs slight PHP processing overhead compared to direct Apache static file serving.
- **Risks:** Server disk space exhaustion if large uploads are unmanaged. Mitigated by strict file size ceilings (15MB RFP, 10MB CV).
- **Alternatives Rejected:** Direct public access to uploads (violates privacy and ASVS Level 2; rejected).
- **Verification Strategy:** Direct URL access denial test and authenticated streaming test (`TEST-SEC-003`, `TEST-SEC-004`).
- **Implementation Phase:** **Phase 10 (Media Library & Secure Document Quarantine)**.

---

## ADR-008: CMS Publishing Lifecycle, Optimistic Concurrency & Safe 301 Redirect Generation

- **Context:** Multiple editors manage services, industries, and articles. Slug modifications can destroy SEO backlinks.
- **Constraints:** Prevent mid-air overwrites and broken backlinks.
- **Options Evaluated:**
  1. *Pessimistic Locking:* Locks database rows while an editor views them; results in abandoned locks and frustrated users.
  2. *Optimistic Concurrency Control (OCC) + Automated 301 Redirects:* Uses `updated_at` timestamps to detect conflicts. Renaming a slug automatically creates a permanent 301 redirect rule in `url_redirects` with circular loop detection.
- **Decision:** Implement **Optimistic Concurrency Control (OCC) and Automated 301 Redirect Engine**.
- **Trade-offs:** Editors must resolve conflicts if two edits occur simultaneously.
- **Risks:** Circular redirect chains if an editor renames A -> B -> A. Mitigated by automated chain flattening.
- **Alternatives Rejected:** Unrestricted slug renaming without redirects (destroys SEO; rejected).
- **Verification Strategy:** Concurrency conflict test (`TEST-ADM-005`) and redirect loop test (`TEST-WF-006`).
- **Implementation Phase:** **Phase 7 / Phase 8 / Phase 16**.

---

## ADR-009: Unified Exception Handling, Correlation Diagnostics & Structured JSON Logging

- **Context:** Production environments must suppress internal stack traces while providing diagnostic traceability for support teams.
- **Constraints:** Shared hosting disk space and error privacy.
- **Options Evaluated:**
  1. *Display Raw Errors (`display_errors = On`):* Leaks database passwords and system paths; critical vulnerability.
  2. *Silent Suppression:* Hides errors completely; impossible to debug support tickets.
  3. *Unified Exception Normalization with Diagnostic Correlation IDs:* Suppresses stack traces; emits uniform JSON error envelope with unique reference code (`REF-ERR-XXXXXX`); writes full trace to structured daily JSON logs.
- **Decision:** Implement **Unified Exception Normalization with Correlation IDs**.
- **Trade-offs:** Developers must check log files using the reference ID rather than viewing stack traces in the browser.
- **Risks:** Log file growth. Mitigated by daily log rotation and 30-day retention cleanup.
- **Alternatives Rejected:** Displaying errors in production (rejected).
- **Verification Strategy:** Simulated 500 error test asserting authentic status and correlation ID (`TEST-PUB-023`).
- **Implementation Phase:** **Phase 4 / Phase 17**.

---

## ADR-010: Hostinger Shared Hosting Deployment Topology, Security Headers & Release Strategy

- **Context:** The platform must deploy safely to Hostinger shared hosting without impacting neighboring addon domains (`atspecialists`, `healthonus`).
- **Constraints:** Multi-site shared hosting account, Git/SFTP deployment options, loop-safe `.htaccess` rewrite rules, security headers, zero committed secrets.
- **Options Evaluated:**
  1. *Live In-Place File Editing:* High risk of serving broken code during updates; rejected.
  2. *Single Account Root (`/public_html/`):* Incompatible with multi-site accounts; risks overwriting other domains; rejected.
  3. *Isolated Addon Domain Root (`/domains/voakhealthcare.com/`) with Deployment Feasibility Matrix:* Strictly bounds Voak files to its dedicated domain directory. Release packaging separates private PHP logic (above web root) from public assets (`public_html/`). Evaluates atomic symlinks (Pending Hostinger Verification) with a verified fallback to Controlled In-Place File Sync with Maintenance Mode.
- **Decision:** Adopt **Isolated Addon Domain Architecture with Deployment Feasibility Governance**.
- **Trade-offs:** Requires SSH or deployment scripts capable of managing symlinks or controlled rsync on Hostinger.
- **Risks:** Hosting plan restrictions on symlinks. Fallback: Controlled in-place sync with `.maintenance` lock (< 5s maintenance window).
- **Alternatives Rejected:** Direct FTP drag-and-drop into live public_html (uncontrolled; rejected).
- **Verification Strategy:** Deployment smoke test, secret scanning audit (`TEST-SEC-006`), and neighbor-domain isolation audit.
- **Implementation Phase:** **Phase 31 (Hostinger Deployment)**.
