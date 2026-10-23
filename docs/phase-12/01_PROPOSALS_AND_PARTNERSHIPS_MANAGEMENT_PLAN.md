# Phase 12: Proposals & Partnerships CRM Management — Architecture & Implementation Plan

**Feature Code:** `ADM-011`, `ADM-012`, `FR-ADM-011`, `FR-ADM-012`, `FR-WF-002`, `FR-WF-003`, `SYS-005`, `FR-SEC-003`, `FR-SEC-004`, `ADR-007`, `ADR-008`  
**Roadmap Phase:** Phase 12 of 32  
**Implementation Scope:** Proposals & Partnerships CRM Backend  
**Schema Status:** ZERO NEW MIGRATIONS REQUIRED (Reusing Migrations 17, 18, 19, 25, 28)

---

## 1. Executive Summary & Objective

Phase 12 builds upon the verified security and CRM foundations established in Phase 11. It implements two enterprise inbound pipelines and management desks:
1. **Institutional Partnerships & Alliance Pipeline (`ADM-011` / `FR-ADM-011`, `FR-WF-002`, `TEST-ADM-011`, `TEST-WF-002`, `TEST-PUB-020`):**
   - Public intake API (`POST /api/v1/public/partnerships/inquire`) capturing organization tier, alliance type, contact details, and proposed scope.
   - Deterministic reference code formatting: `PTN-YYYYMMDD-XXXX`.
   - Multi-layer abuse defense: IP rate limiting (5/hr/IP), honeypot filtering, and Turnstile integration.
   - Administrative Kanban/table desk with filtering by tier, alliance type, status, and assigned lead.
   - Finite State Machine (`FR-WF-002`): `NEW` $\to$ `EVALUATION` $\to$ `DISCUSSION` $\to$ `LEGAL_REVIEW` $\to$ `ACTIVE_ALLIANCE` (or `DECLINED`).
   - Chronological internal notes ledger and lead assignment.
   - RFC 4180 CSV pipeline export with CSV injection formula neutralization.
   - Fail-closed hard deletion restricted strictly to Super Administrator (`partner:delete`).
2. **Enterprise Proposal & RFP Management Desk (`ADM-012` / `FR-ADM-012`, `FR-WF-003`, `TEST-ADM-012`, `TEST-WF-003`, `TEST-PUB-021`):**
   - Public 4-step RFP wizard ingestion API (`POST /api/v1/public/proposals/submit`) capturing facility profile, contact details, timeline, budget band, multi-service selection, and client tender specification document.
   - Deterministic reference code formatting: `RFP-YYYYMMDD-XXXX`.
   - Dual-enclave file quarantine: Tender documents (PDF/DOCX, max 15MB) inspected via `finfo` magic bytes and stored outside public web root with 0600 permissions in `storage/private/quarantine/`.
   - Relational mapping to services catalog via compound junction table `proposal_services`.
   - Administrative review desk with full proposal inspection and authenticated binary document streaming proxy (`GET /api/v1/admin/proposals/{id}/download`).
   - Finite State Machine (`FR-WF-003`): `SUBMITTED` $\to$ `UNDER_REVIEW` $\to$ `SCOPING` $\to$ `PROPOSAL_SENT` $\to$ `ACCEPTED` (or `REJECTED`, `EXPIRED`).
   - Estimated commercial value attachment and lead assignment.
   - RFC 4180 CSV export and Super Admin deletion cascading to junction mappings and quarantined files.

---

## 2. Requirement Traceability Matrix

| Requirement ID | Module / Component | Description | Canonical Test ID | Phase Status |
|---|---|---|---|---|
| **`FR-ADM-011`** | `ADM-011` (Partnership CRM) | Strategic partnership pipeline tracker, organization tiering, and discovery notes | `TEST-ADM-011` | Phase 12 (New Backend) |
| **`FR-ADM-012`** | `ADM-012` (Proposal Desk) | RFP review desk, multi-step proposal ingestion, and tender file streaming | `TEST-ADM-012` | Phase 12 (New Backend) |
| **`FR-WF-002`** | `Partnerships` FSM | Partnership pipeline DAG state transitions: `NEW` $\to$ `EVALUATION` $\to$ `DISCUSSION` $\to$ `LEGAL_REVIEW` $\to$ `ACTIVE_ALLIANCE` / `DECLINED` | `TEST-WF-002` | Phase 12 (New Backend) |
| **`FR-WF-003`** | `Proposals` FSM | RFP proposal lifecycle DAG transitions: `SUBMITTED` $\to$ `UNDER_REVIEW` $\to$ `SCOPING` $\to$ `PROPOSAL_SENT` $\to$ `ACCEPTED` / `REJECTED` / `EXPIRED` | `TEST-WF-003` | Phase 12 (New Backend) |
| **`FR-PUB-020`** | `Public Intake` | Inbound strategic partnership submission API with spam defense and reference code | `TEST-PUB-020` | Phase 12 (New Backend) |
| **`FR-PUB-021`** | `Public Intake` | Multi-step RFP proposal submission API with quarantined tender file upload | `TEST-PUB-021` | Phase 12 (New Backend) |
| **`FR-SEC-003`** | `File Quarantine` | MIME inspection (magic bytes), 15MB ceiling, and physical quarantine segregation | `TEST-SEC-003` | Phase 10 / Reused |
| **`FR-SEC-004`** | `Authenticated Streaming` | Memory-safe streaming proxy denying direct URL access and enforcing RBAC | `TEST-SEC-004` | Phase 10 / Reused |
| **`SYS-005`** | `Anti-Abuse` | Rate limiting (5/hr/IP), honeypot traps, and Turnstile verification | Phase 11 / Reused |
| **`ADR-007`** | `Storage Architecture` | Public media enclave vs. private quarantine enclave separation | Phase 10 / Reused |
| **`ADR-008`** | `Audit Ledger` | Chained immutable audit log records with transaction compensating rollback | Phase 6–11 / Reused |

---

## 3. Database Schema Decision: ZERO NEW MIGRATIONS REQUIRED

All database tables, indexes, and foreign key relationships were verified as fully provisioned by earlier architecture phases:
1. **`partnership_inquiries` (Migration 17 — `20261021_000017_create_partnership_inquiries_table.php`):**
   - Columns: `id`, `reference_code` (UNIQUE), `organization_name`, `contact_name`, `corporate_email`, `contact_phone`, `alliance_type`, `proposed_scope`, `status` (ENUM: `NEW`, `EVALUATION`, `DISCUSSION`, `LEGAL_REVIEW`, `ACTIVE_ALLIANCE`, `DECLINED`), `assigned_user_id` (FK `users.id`), `internal_notes`, `ip_address`, `created_at`, `updated_at`.
   - Indexes: `idx_partnerships_status`, `idx_partnerships_ref`.
2. **`proposals` (Migration 18 — `20261021_000018_create_proposals_table.php`):**
   - Columns: `id`, `reference_code` (UNIQUE), `organization_name`, `contact_name`, `contact_email`, `contact_phone`, `facility_type`, `timeline`, `budget_band`, `quarantined_file_path`, `original_file_name`, `file_size_bytes`, `status` (ENUM: `SUBMITTED`, `UNDER_REVIEW`, `SCOPING`, `PROPOSAL_SENT`, `ACCEPTED`, `REJECTED`, `EXPIRED`), `estimated_commercial_value`, `assigned_lead_id` (FK `users.id`), `created_at`, `updated_at`.
   - Indexes: `idx_proposals_status`, `idx_proposals_ref`.
3. **`proposal_services` (Migration 19 — `20261021_000019_create_proposal_services_table.php`):**
   - Compound Primary Key: `(proposal_id, service_id)`.
   - Foreign Keys: `proposal_id` references `proposals(id)` ON DELETE CASCADE, `service_id` references `services(id)` ON DELETE CASCADE.
4. **`email_queue` (Migration 25) & `audit_logs` (Migration 28):**
   - Reused for automated email confirmation staging and immutable audit tracking.

---

## 4. State Machine Transition Architecture

### 4.1 Partnership Pipeline State Machine (`FR-WF-002`)
The partnership pipeline transitions are strictly validated. Any invalid transition immediately throws a `422 Unprocessable Entity` (`ERR_INVALID_TRANSITION`).
- `NEW` $\to$ `EVALUATION`, `DECLINED`
- `EVALUATION` $\to$ `DISCUSSION`, `DECLINED`
- `DISCUSSION` $\to$ `LEGAL_REVIEW`, `DECLINED`
- `LEGAL_REVIEW` $\to$ `ACTIVE_ALLIANCE`, `DECLINED`
- `ACTIVE_ALLIANCE` $\to$ `DECLINED`
- Canonical Aliases: `DISCOVERY_SCHEDULED` $\to$ `DISCUSSION`; `NEGOTIATING` $\to$ `LEGAL_REVIEW`; `PARTNERED` $\to$ `ACTIVE_ALLIANCE`.

### 4.2 RFP Proposal Lifecycle State Machine (`FR-WF-003`)
The proposal state transitions enforce milestones and commercial scoping:
- `SUBMITTED` $\to$ `UNDER_REVIEW`, `REJECTED`
- `UNDER_REVIEW` $\to$ `SCOPING`, `REJECTED`
- `SCOPING` $\to$ `PROPOSAL_SENT`, `REJECTED`
- `PROPOSAL_SENT` $\to$ `ACCEPTED`, `REJECTED`, `EXPIRED`
- Canonical Aliases: `NEW` $\to$ `SUBMITTED`; `DRAFTING_PROPOSAL` $\to$ `SCOPING`; `CONTRACT_EXECUTED` $\to$ `ACCEPTED`; `DECLINED` $\to$ `REJECTED`.

---

## 5. Security & RBAC Enforcements

### 5.1 Role Boundaries
In strict alignment with `docs/phase-01/06_COMPLETE_RBAC_PERMISSION_MATRIX.md`:
- **`Super Admin` (`ROLE_SUPER_ADMIN`):** Unrestricted access across both modules, including CSV export and hard deletion.
- **`BD Manager` (`ROLE_BD_MANAGER`):** Full operational access to triage, transition states, add notes, download client tenders, and export CSVs. Hard deletion denied.
- **`Website Admin` (`ROLE_WEBSITE_ADMIN`):** Strictly restricted (zero access to commercial tenders, partnerships, or proposals).
- **`Content Editor` (`ROLE_CONTENT_EDITOR`):** Strictly restricted (zero CRM access).
- **`HR Manager` (`ROLE_HR_MANAGER`):** Strictly restricted (zero commercial access).

### 5.2 Authentication & CSRF
- All admin endpoints enforce fail-closed authentication (`requireAdminUserId`).
- All state mutations (`PATCH`, `POST`, `DELETE`) require valid CSRF tokens.

### 5.3 File Quarantine & Streaming
- Tender specifications are validated using MIME magic bytes (`application/pdf`, DOCX ZIP header).
- Stored outside the public web root in `storage/private/quarantine/` with UUIDv4 filenames and 0600 permissions.
- Direct web access blocked via `.htaccess` and server configuration.
- Streaming via authenticated PHP proxy with path traversal guards (`str_starts_with`) and audit logging.

---

## 6. API Contracts

### Public Endpoints
1. `POST /api/v1/public/partnerships/inquire` (Alias: `/api/enquiries/partner`):
   - Ingests alliance submission, validates inputs, generates `PTN-YYYYMMDD-XXXX`, stages email confirmation.
2. `POST /api/v1/public/proposals/submit` (Alias: `/api/enquiries/proposal`):
   - Ingests 4-step wizard data, validates inputs, attaches and quarantines tender document, maps service pillars in `proposal_services`, generates `RFP-YYYYMMDD-XXXX`, stages email confirmation.

### Admin Endpoints
1. `GET /api/v1/admin/partnerships` (Permission: `partner:list` / `partnerships.view`)
2. `GET /api/v1/admin/partnerships/{id}` (Permission: `partner:list` / `partnerships.view`)
3. `PATCH /api/v1/admin/partnerships/{id}/status` (Permission: `partner:update_stage` / `partnerships.manage`, CSRF: Yes)
4. `POST /api/v1/admin/partnerships/{id}/notes` (Permission: `partner:add_note` / `partnerships.manage`, CSRF: Yes)
5. `PATCH /api/v1/admin/partnerships/{id}/assign` (Permission: `partner:update_stage` / `partnerships.manage`, CSRF: Yes)
6. `GET /api/v1/admin/partnerships/export` (Permission: `partner:export_csv` / `partnerships.export`)
7. `DELETE /api/v1/admin/partnerships/{id}` (Permission: `partner:delete` / Super Admin, CSRF: Yes)
8. `GET /api/v1/admin/proposals` (Permission: `proposal:list` / `proposals.view`)
9. `GET /api/v1/admin/proposals/{id}` (Permission: `proposal:list` / `proposals.view`)
10. `PATCH /api/v1/admin/proposals/{id}/status` (Permission: `proposal:update_status` / `proposals.manage`, CSRF: Yes)
11. `PATCH /api/v1/admin/proposals/{id}/assign` (Permission: `proposal:update_status` / `proposals.manage`, CSRF: Yes)
12. `GET /api/v1/admin/proposals/{id}/download` (Permission: `proposal:download_tender` / `proposals.manage`)
13. `GET /api/v1/admin/proposals/export` (Permission: `proposal:export_csv` / `proposals.export`)
14. `DELETE /api/v1/admin/proposals/{id}` (Permission: `proposal:delete` / Super Admin, CSRF: Yes)

---

## 7. Deferred Infrastructure
- **Admin Portal Frontend:** Deferred to React frontend implementation phases (Phases 16–22).
- **Public Form Interfaces:** Deferred to React frontend phases.
- **Asynchronous Mail Worker Daemon:** Background cron worker runner deferred to Background Jobs phase.
- **Hostinger Deployment:** Intentionally deferred pending explicit Business Owner authorization.
