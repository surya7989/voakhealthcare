# PHASE 17 IMPLEMENTATION PLAN
## Administration, Auditing & API Verification (Module 19 / ADM-019)

**Document Reference:** `docs/phase-17/01_ADMINISTRATION_AUDITING_AND_API_VERIFICATION_PLAN.md`  
**Phase:** Phase 17 of 31 — Administration, Auditing & API Verification (Final Stage 2 Backend Phase)  
**Baseline Repository State:** Fully verified Phase 16 HEAD (`80c59a3b87fbc47ffac69e0a2e8cd2314c014664`)  
**Target Delivery:** 100% green local test suites, zero regressions, 100% green GitHub Actions CI  

---

### 1. Architectural Scope & Boundary Enforcement

- **Strict Backend-Only Scope**: Phase 17 is the authoritative completion of Stage 2 (Backend Core Services & APIs).
- **No Early Frontend**: Frontend implementation begins in Stage 3 (Phase 18: React Foundation; Phase 25: React Admin Dashboard; Phase 26: Complete Admin Operations including `/admin/audit-logs` UI).
- **Zero New Database Migrations**: `audit_logs` table schema was definitively established in migration 28 (`20261021_000028_create_audit_logs_table.php`). The database schema remains locked at strictly **28 migrations**.
- **No Deployment to Hostinger**: Staging and production deployments remain untouched and deferred.

---

### 2. Canonical Requirements Traceability

| Requirement ID | Canonical Specification | Target Artifacts |
|---|---|---|
| `ADM-019` / `FR-ADM-019` | Immutable Audit Ledger & Telemetry Monitoring | `AuditService.php`, `AdminAuditController.php` |
| `TEST-ADM-019` | Cryptographic SHA-256 Hash Chain Integrity & CLI Verification | `AuditLogger.php`, `AuditService.php`, `cli/verify_audit_chain.php` |
| `ADM-001` / `FR-ADM-001` | Administrative Executive Dashboard & Aggregated KPI Statistics | `AdminDashboardController.php` (`GET /api/v1/admin/dashboard/stats`) |
| `ADM-018` / `SYS-004` | Protected Operational System Telemetry & Diagnostics | `AdminDiagnosticsController.php` (`GET /api/v1/admin/system/diagnostics`) |
| `FR-SEC-004` / `C-14` | Append-Only Guard & Formula Injection Defense on Audit Export | `AuditService::exportCsv`, `AdminAuditController::exportCsv` |
| `Stage 2 Gate` | 100% Comprehensive Backend API Verification | `backend/tests/run_tests.php`, `tests/test_phase17_administration_and_auditing.php` |

---

### 3. Detailed Component Architecture

#### 3.1 `Voak\Health\Services\AuditService`
- Location: `backend/src/Services/AuditService.php`
- Dependencies: `Voak\Health\Database\Connection`, `Voak\Health\Security\AuditLogger`
- Core Responsibilities:
  1. `queryLogs(array $filters = []): array`:
     - Pagination (`page`, `per_page` bounded [1, 100]).
     - Filtering: `domain`, `action`, `user_id`, `actor` (search actor email or name), `entity_type`, `entity_id`, `start_date`, `end_date`, `ip_address`, `search` (full-text search over domain, action, ip_address, user_agent, diff_json).
     - Returns `{ records: [...], pagination: { current_page, per_page, total_records, total_pages } }`.
  2. `getLogById(int $id): ?array`:
     - Fetches record with actor metadata and parsed `diff_json`.
  3. `verifyChainIntegrity(): array`:
     - Executes mathematical verification of the SHA-256 chain from genesis hash to head.
     - Detects tampering, record deletion, or unauthorized row insertion.
     - Returns `{ valid: bool, total_records: int, genesis_hash: string, head_hash: string, tampered_at_id: ?int, error: ?string }`.
  4. `exportCsv(array $filters = []): string`:
     - Generates RFC 4180 CSV export.
     - Injects UTF-8 Byte Order Mark (`\xEF\xBB\xBF`).
     - Neutralizes CSV formula injection on fields starting with `=`, `+`, `-`, `@`, `\t`, `\r`.
  5. Audit Event Recording:
     - Emits `AUDIT_LOGS_ACCESSED` on search/query.
     - Emits `AUDIT_LOGS_EXPORTED` on compliance export.
  6. Immutability Guard:
     - Strictly zero `UPDATE` or `DELETE` repository methods.

#### 3.2 `Voak\Health\Modules\Admin\AdminAuditController`
- Location: `backend/src/Modules/Admin/AdminAuditController.php`
- Actions:
  - `listLogs(Request $request): Response` — `GET /api/v1/admin/audit-logs` (RBAC: `audit:read`)
  - `getLog(Request $request): Response` — `GET /api/v1/admin/audit-logs/:id` (RBAC: `audit:read`)
  - `verifyChain(Request $request): Response` — `GET/POST /api/v1/admin/audit-logs/verify` (RBAC: `audit:read`)
  - `exportCsv(Request $request): Response` — `GET /api/v1/admin/audit-logs/export` (RBAC: `audit:export`)

#### 3.3 `Voak\Health\Modules\Admin\AdminDiagnosticsController`
- Location: `backend/src/Modules/Admin/AdminDiagnosticsController.php`
- Action:
  - `getDiagnostics(Request $request): Response` — `GET /api/v1/admin/system/diagnostics` (RBAC: `system.diagnostics` / Super Admin only)
  - Telemetry: DB latency (`SELECT 1`), applied migrations count, PHP version, memory usage, queue backlog counts, storage path permissions.

#### 3.4 `Voak\Health\Modules\Admin\AdminDashboardController`
- Location: `backend/src/Modules/Admin/AdminDashboardController.php`
- Action:
  - `getStats(Request $request): Response` — `GET /api/v1/admin/dashboard/stats` (RBAC: `dashboard.view`)
  - KPI Metrics: Inbound inquiries (contact, proposals, partnerships), recruitment (active jobs, candidate applications), content (pages, services, articles, case studies), recent audit activity.

#### 3.5 CLI Integrity Tool
- Location: `cli/verify_audit_chain.php`
- CLI verification runner outputting summary and exiting with code 0 on valid, 1 on failure.

---

### 4. RBAC Permission Matrix Updates
- Register aliases in `RbacManager.php`:
  - `'audit.view'` => `['audit:read', 'audit.view']`
  - `'audit.read'` => `['audit:read', 'audit.read']`
  - `'audit.export'` => `['audit:export', 'audit.export']`
  - `'system.diagnostics'` => `['system.diagnostics', 'settings:read', 'audit:read']`
  - `'dashboard.view'` => `['auth:login', 'dashboard.view']`

---

### 5. Verification Plan
1. Unit tests: `backend/tests/Unit/AuditServiceTest.php`
2. Integration tests: `backend/tests/Integration/AdminAuditAndTelemetryApiTest.php`
3. Standalone runner: `tests/test_phase17_administration_and_auditing.php`
4. Root harness & full suite: `tests/test_backend_foundation.php` & `backend/tests/run_tests.php`
5. MySQL 8.0 test: `backend/tests/mysql_security_concurrency_test.php` (Test 17)
6. CI workflow update: `.github/workflows/backend-ci.yml`
7. Static analysis: `php -l`, `composer validate --strict`, `tests/scan_secrets.php`
