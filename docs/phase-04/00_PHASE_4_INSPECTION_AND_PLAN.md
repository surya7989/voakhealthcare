# Voak Health Care — Phase 4 Pre-Implementation Inspection & Execution Plan

**Document Reference:** `DOC-PH4-00-INSPECTION-AND-PLAN`  
**Phase:** Phase 4 — PHP Backend Foundation  
**Date:** October 20, 2026  
**Status:** **CONDITIONALLY AUTHORIZED** (Ratified via `DOC-PH3-21-PHASE-4-CONDITIONAL-START-REQUEST`)  
**Lead Roles:** Senior Software Architect, PHP Backend Engineer, Application Security Engineer & Test Engineer  

---

## 1. Initial Repository & Environmental State (Task 5.1, 5.5)

### 1.1 Host Environment Preflight
- **PHP CLI Runtime:** PHP `8.3.33` (ZTS Visual C++ 2019 x64) detected at `C:\Users\voakh\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_...`
- **Composer Package Manager:** Composer `2.10.3` detected and verified.
- **Node.js Runtime:** Node.js `v26.7.0` available for cross-tier validation.
- **Operating System:** Windows host environment; target deployment environment is Linux CloudLinux shared hosting (Hostinger).

### 1.2 Repository Architecture Audit
- **Existing Workspaces:**
  - `backend/` — Created during Phase 0–2 architecture baseline with `.gitkeep` (`# Voak Health Care - Backend Workspace # Phase 4+ Target Directory`).
  - `frontend/` — Preserved for Phase 18+ React/TypeScript/Vite application.
  - `deployment/` — Preserved for Phase 31 packaging and Hostinger configuration.
  - `design/phase-03/` — Contains Phase 3 UI/UX design system and master prototype.
  - `docs/` — Authoritative accepted specifications for Phase 0, 1, and 2.
  - `tests/` — Automated test suites.
- **Existing PHP & Composer Files:** Exactly 0 `.php` files and 0 `composer.json` files exist in the repository prior to Phase 4. The workspace is pristine and unpolluted.
- **Starter Archive Status:** `VOAK_PHASE_4_CONDITIONAL_FOUNDATION_STARTER.zip` is not present in workspace. The foundation will be engineered directly from the authoritative Phase 2 architecture specifications (`docs/phase-02/02_PHP_BACKEND_MODULE_ARCHITECTURE.md`, `docs/phase-02/04_API_CONTRACTS_AND_OPENAPI.md`, `docs/phase-02/11_HOSTINGER_DEPLOYMENT_TOPOLOGY.md`).

### 1.3 Active Governance Risk Register
In accordance with `DOC-PH3-21-PHASE-4-CONDITIONAL-START-REQUEST`, the three open items remain tracked and unrelaxed:
- **`OPEN-01` (Responsive Browser Testing):** NOT INDEPENDENTLY VERIFIED (Environment limitation).
- **`OPEN-02` (Visual Reference Parity):** PENDING BUSINESS SIGN-OFF.
- **`OPEN-03` (Requirement Traceability):** BLOCKED pending business-owner decision (`FR-PUB-004` vs `PAGE-PUB-10`).

---

## 2. Architecture Constraints & Scope Boundaries (Section 4, 11)

### 2.1 Permitted Phase 4 Work
1. PSR-4 autoloaded PHP 8.3+ application structure under `Voak\Health\` namespace inside `backend/`.
2. Fail-fast environment configuration parser (`Config`) with `.env.example`.
3. Immutable HTTP `Request` and `Response` abstractions with standard JSON envelope formatting.
4. Fast REST `Router` supporting route parameters, method validation, 404 Not Found, and 405 Method Not Allowed with `Allow` header.
5. Extensible `MiddlewarePipeline` implementing Security Headers, CORS, and JSON parsing.
6. Centralized exception handling (`GlobalExceptionHandler`) normalizing all exceptions into safe JSON envelopes without stack trace leakage in production.
7. Lazy `DatabaseConnection` abstraction utilizing `PDO` without connecting on application boot.
8. Safe schema-blueprint and migration infrastructure framework (`MigrationManager`) without executing live mutations.
9. Minimal technical health endpoints: `GET /api/health` (Phase 4 gate requirement returning `{"status":"ok"}`) and `GET /api/v1/health` (Module 01 standard envelope).
10. Comprehensive automated unit, integration, and HTTP smoke test suites.

### 2.2 Strictly Prohibited Phase 4 Work
- No full Phase 5 relational database schema or production table migrations.
- No full Phase 6 administrative authentication, bcrypt user records, or MFA logic.
- No Phase 7–16 content management, enquiry triage, resume storage, or email transports.
- No Phase 18–26 React components, UI modifications, or template coupling.
- No live database mutations or execution against remote servers.
- No alteration of Phase 0, 1, or 2 baseline specifications.

---

## 3. Detailed Implementation Plan (Task 5.6)

```
+----+--------------------------------+----------------------------+-------------------------------------+-----------------------------------+
| ID | TASK TITLE                     | TARGET LOCATION            | IMPLEMENTATION DETAILS              | VERIFICATION METHOD               |
+----+--------------------------------+----------------------------+-------------------------------------+-----------------------------------+
| T1 | Directory & Composer Setup     | backend/composer.json      | PSR-4 namespace Voak\\Health\\,     | composer validate &&              |
|    |                                | backend/src/               | PHP >= 8.2 requirement, autoload   | composer dump-autoload            |
+----+--------------------------------+----------------------------+-------------------------------------+-----------------------------------+
| T2 | Environment Configuration      | backend/src/Core/Config.php| Fail-fast boot checks, safe parsing,| Unit tests: valid, missing, and   |
|    | & Secret Protection            | backend/.env.example       | production debug lockdown           | production invalid configurations |
+----+--------------------------------+----------------------------+-------------------------------------+-----------------------------------+
| T3 | HTTP Request / Response        | backend/src/Core/Http/     | Immutable Request & Response,       | Unit tests: JSON serialization,   |
|    | & JSON Envelope Architecture   | Request.php, Response.php  | Phase 2 API envelope formatting     | correlation IDs, header emission  |
+----+--------------------------------+----------------------------+-------------------------------------+-----------------------------------+
| T4 | REST Router & Route Dispatch   | backend/src/Core/Router/   | Method/path registration, param     | Route matching tests, 404 tests,  |
|    |                                | Router.php, Route.php      | extraction, 404 and 405 handling    | 405 Allow header validation       |
+----+--------------------------------+----------------------------+-------------------------------------+-----------------------------------+
| T5 | Middleware Pipeline            | backend/src/Middleware/    | Security headers, safe CORS,        | Ordering test, preflight test,    |
|    |                                | SecurityHeaders, Cors, Json| JSON body parser, short-circuit     | invalid body test                 |
+----+--------------------------------+----------------------------+-------------------------------------+-----------------------------------+
| T6 | Exception Normalization        | backend/src/Core/Exceptions| GlobalExceptionHandler, typed       | Error envelope test, production   |
|    | & Error Envelope               | AppException, HttpException| error masking, error reference ID   | stack-trace suppression test      |
+----+--------------------------------+----------------------------+-------------------------------------+-----------------------------------+
| T7 | PDO Database Abstraction       | backend/src/Database/      | Lazy connection, PDO options,       | Unit test with mock connection;   |
|    |                                | Connection.php, Transact.  | transaction run() helper            | assertion of zero boot connection |
+----+--------------------------------+----------------------------+-------------------------------------+-----------------------------------+
| T8 | Migration Framework Groundwork | backend/src/Database/Migr. | MigrationInterface, safe discovery, | Migration discovery test, safe    |
|    |                                | MigrationManager.php       | status reporting, zero mutations    | status check without live DB      |
+----+--------------------------------+----------------------------+-------------------------------------+-----------------------------------+
| T9 | Application Kernel & Gateway   | backend/src/Core/App.php   | Unified initialization path, DI     | Full bootstrap integration test,  |
|    |                                | backend/public/api/index.  | container, health route wiring      | HTTP smoke tests on PHP server    |
+----+--------------------------------+----------------------------+-------------------------------------+-----------------------------------+
| T10| Modular Scaffolding for OPEN-03| backend/src/Modules/       | Isolated route registration notes   | Traceability audit, confirmed     |
|    | Traceability Handling          | PartnersClientsScaffolding | without hardcoded requirement ID    | non-resolution of Gate 5          |
+----+--------------------------------+----------------------------+-------------------------------------+-----------------------------------+
| T11| Automated Test Suites          | backend/tests/             | Complete unit & integration suites  | php backend/tests/run_tests.php   |
|    |                                | Unit/, Integration/        | covering all foundation components  | 100% pass across all test cases   |
+----+--------------------------------+----------------------------+-------------------------------------+-----------------------------------+
| T12| Documentation & Deliverables   | docs/phase-04/             | Architecture guide, completion      | Full audit against Section 13     |
|    |                                | 01_ARCH, 02_REPORT         | report, risk register preservation  | and Phase Gate acceptance criteria|
+----+--------------------------------+----------------------------+-------------------------------------+-----------------------------------+
```
