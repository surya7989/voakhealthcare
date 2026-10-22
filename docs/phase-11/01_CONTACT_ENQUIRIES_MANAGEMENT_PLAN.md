# Phase 11: Contact Enquiries Management & Lifecycle State Machine — Architecture & Implementation Plan

**Document Reference:** `docs/phase-11/01_CONTACT_ENQUIRIES_MANAGEMENT_PLAN.md`  
**Phase:** Phase 11 of 32 — Contact Enquiry Management  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Status:** **AUTHORITATIVE IMPLEMENTATION PLAN**  
**Traceability References:**
- `ADM-010` / `ADM-009`: Contact Enquiries Management & Inbound Triage CRM Desk
- `FR-ADM-010`: Contact Inquiries Triage CRM Desk (Inbound pipeline, status triage, staff notes, and CSV data export)
- `FR-WF-001`: Contact Inquiry Lifecycle State Machine (`NEW` -> `TRIAGED` -> `IN_REVIEW` -> `CONTACTED` -> `RESOLVED` -> `CLOSED`, `SPAM`)
- `TEST-ADM-010`: Contact Inquiries Triage CRM Desk & Status Progression
- `TEST-WF-001`: Contact Inquiry Lifecycle State Machine Transitions
- `SYS-005`: Inbound Submission Abuse Protection (Rate limiting 5 submissions/hour/IP, honeypot detection, Turnstile validation)
- `ADR-008`: Directed Acyclic Graph (DAG) State Machine for Contact Inquiries Lifecycle

---

## 1. Executive Summary & Boundaries

Phase 11 implements the enterprise inbound contact intake pipeline and administrative CRM triage desk for Voak Health Care:
1. **Public Contact Ingestion Pipeline:**
   - Public submission endpoints (`POST /api/v1/public/contact` and route alias `POST /api/enquiries/contact`).
   - Anti-abuse defense: IP-based persistent rate limiting (maximum 5 submissions per hour per IP) via `RateLimiter`.
   - Honeypot trapping (`hp_company_field` / `hp_title`): Silent drop or rejection of automated bot traffic.
   - Cloudflare Turnstile token verification support.
   - Robust Australian and international phone validation (E.164 compatible).
   - Server-authoritative deterministic reference code generation: `CNT-YYYYMMDD-XXXX`.
   - Transactional asynchronous email outbox queueing (`email_queue`).
2. **Administrative Inbound CRM Triage Desk:**
   - Full search, filter, and pagination console (`GET /api/v1/admin/inquiries/contact`).
   - Single inquiry detail and historical audit ledger inspection (`GET /api/v1/admin/inquiries/contact/:id`).
   - Directed Acyclic Graph (DAG) State Machine (`PATCH /api/v1/admin/inquiries/contact/:id/status`):
     - Governed lifecycle: `NEW` -> `TRIAGED` -> `IN_REVIEW` -> `CONTACTED` -> `RESOLVED` -> `CLOSED`.
     - Invalid transitions (e.g. `NEW` directly to `RESOLVED`, or `CLOSED` to `RESOLVED`) strictly rejected with HTTP 422 Unprocessable Entity (`ERR_INVALID_TRANSITION`).
     - Mandatory resolution note enforcement before transitioning to `CLOSED`.
   - Internal staff audit notes (`POST /api/v1/admin/inquiries/contact/:id/notes`) with author attribution and UTC timestamps.
   - Account lead assignment (`POST /api/v1/admin/inquiries/contact/:id/assign`).
   - RFC 4180 compliant CSV export (`GET /api/v1/admin/inquiries/contact/export`) with audit row tracking.
   - High-privilege deletion guard restricted exclusively to Super Admin (`DELETE /api/v1/admin/inquiries/contact/:id`).

### Scope Boundaries:
- **Zero Hostinger Changes:** Live staging and production environments remain untouched until explicit Business Owner authorization.
- **Zero React UI Implementation:** Admin UI is deferred to Admin Portal UI Sprint (Phase 26); all REST APIs, CSRF tokens, and state machine validation rules are fully provisioned.
- **Zero New Migrations Required:** Reuses canonical `contact_inquiries` (Migration 16), `email_queue` (Migration 25), `users` (Migration 1), and `audit_logs` (Migration 28).

---

## 2. Contact Inquiries State Machine DAG (FR-WF-001)

```
                       ┌──────────────┐
                       │     NEW      │
                       └──────┬───────┘
                              │
               ┌──────────────┴──────────────┐
               │                             │
               ▼                             ▼
        ┌──────────────┐              ┌──────────────┐
        │   TRIAGED    │              │     SPAM     │◄─── (From any state)
        └──────┬───────┘              └──────┬───────┘
               │                             │ (False positive restore)
               ▼                             ▼
        ┌──────────────┐              ┌──────────────┐
        │  IN_REVIEW   │              │     NEW      │
        └──────┬───────┘              └──────────────┘
               │
               ▼
        ┌──────────────┐
        │  CONTACTED   │
        └──────┬───────┘
               │
               ▼
        ┌──────────────┐
        │   RESOLVED   │
        └──────┬───────┘
               │ (Requires internal note)
               ▼
        ┌──────────────┐
        │    CLOSED    │
        └──────────────┘
```

### Transition Validation Matrix:
| From State | Allowed Target States | Enforcement / Business Rules |
| :--- | :--- | :--- |
| `NEW` | `TRIAGED`, `IN_REVIEW`, `SPAM` | Initial intake state; cannot jump directly to `RESOLVED` or `CLOSED`. |
| `TRIAGED` | `IN_REVIEW`, `CONTACTED`, `SPAM` | Staff member assigned; cannot jump directly to `RESOLVED` or `CLOSED`. |
| `IN_REVIEW` | `CONTACTED`, `RESOLVED`, `SPAM` | Commercial evaluation in progress. |
| `CONTACTED` | `RESOLVED`, `CLOSED`, `SPAM` | Direct client dialogue established. |
| `RESOLVED` | `CLOSED`, `CONTACTED` | Resolution reached; transition to `CLOSED` requires at least 1 internal note. |
| `CLOSED` | `IN_REVIEW` | Terminal state; only reopened under formal review. |
| `SPAM` | `NEW` | Terminal drop; can only be restored to `NEW` if flagged in error. |

Any transition not permitted above returns HTTP 422 Unprocessable Entity (`ERR_INVALID_TRANSITION`).

---

## 3. Database Schema Reutilization

**ZERO NEW MIGRATIONS REQUIRED.**  
The Phase 11 implementation fully reuses the existing canonical database schema provisioned in Migration 16:
- `contact_inquiries` (Migration 16):
  - `id`: Primary key (BIGINT UNSIGNED)
  - `reference_code`: Unique formatted inquiry tracking code (`VARCHAR(30)`)
  - `full_name`: Contact person (`VARCHAR(150)`)
  - `organization_name`: Company/facility (`VARCHAR(150)`)
  - `corporate_email`: Email address (`VARCHAR(191)`)
  - `contact_phone`: Phone number (`VARCHAR(25)`)
  - `inquiry_details`: Message body (`TEXT`)
  - `status`: Lifecycle enum (`ENUM('NEW', 'TRIAGED', 'IN_REVIEW', 'CONTACTED', 'RESOLVED', 'CLOSED', 'SPAM')`)
  - `assigned_user_id`: Lead assignee (`BIGINT UNSIGNED NULL`, FK to `users.id`)
  - `internal_notes`: Internal CRM triage notes ledger (`TEXT NULL`)
  - `ip_address`: Submitter IP address (`VARCHAR(45)`)
  - `created_at` / `updated_at`: Timestamps
  - Indexes: `idx_inquiries_status` on `(status, created_at)`, `idx_inquiries_ref` on `(reference_code)`.

---

## 4. REST Endpoints & Route Contracts

### A. Public Inbound Submission (`/api/v1/public/contact` and `/api/enquiries/contact`)
- **HTTP Method:** `POST`
- **Authentication:** Public / Unauthenticated
- **Rate Limit:** 5 requests / 60 minutes / IP (returns HTTP 429 `ERR_RATE_LIMITED`)
- **Honeypot:** `hp_company_field` (silently dropped if populated)
- **Request Body:**
  ```json
  {
    "full_name": "Dr. Sarah Jenkins",
    "organization_name": "Pacific Healthcare Group",
    "corporate_email": "s.jenkins@pacifichealth.com.au",
    "contact_phone": "+61 2 9876 5432",
    "inquiry_details": "Seeking clinical staffing consultancy across 3 regional clinics.",
    "hp_company_field": ""
  }
  ```
- **Validation:**
  - `full_name`: required, 2-150 chars.
  - `organization_name`: required, 2-150 chars.
  - `corporate_email`: required, valid email format, max 191 chars.
  - `contact_phone`: required, valid Australian/E.164 phone format, 8-25 chars.
  - `inquiry_details`: required, 15-3000 chars.
- **Success Response (201 Created):**
  ```json
  {
    "success": true,
    "data": {
      "message": "Thank you for reaching out to Voak Health Care. Our executive team will review your inquiry within one business day.",
      "reference_code": "CNT-20261022-8F2B"
    }
  }
  ```

### B. Admin Inquiries CRM Console (`/api/v1/admin/inquiries/contact`)
| Method | URI | Permission | CSRF | Description | Error Codes |
| :--- | :--- | :--- | :---: | :--- | :--- |
| `GET` | `/api/v1/admin/inquiries/contact` | `contact:list` | No | List inquiries with search, status filtering, and pagination | `ERR_UNAUTHENTICATED`, `ERR_FORBIDDEN` |
| `GET` | `/api/v1/admin/inquiries/contact/:id` | `contact:list` | No | Retrieve single inquiry record, assignee, and notes | `ERR_UNAUTHENTICATED`, `ERR_NOT_FOUND` |
| `PATCH` | `/api/v1/admin/inquiries/contact/:id/status` | `contact:update_status` | Yes | Advance state machine, optional assignment and note | `ERR_UNAUTHENTICATED`, `ERR_FORBIDDEN`, `ERR_CSRF_INVALID`, `ERR_INVALID_TRANSITION` (422) |
| `POST` | `/api/v1/admin/inquiries/contact/:id/notes` | `contact:add_note` | Yes | Append internal staff note with author and timestamp | `ERR_UNAUTHENTICATED`, `ERR_VALIDATION`, `ERR_NOT_FOUND` |
| `POST` | `/api/v1/admin/inquiries/contact/:id/assign` | `contact:update_status` | Yes | Assign inquiry to account manager | `ERR_UNAUTHENTICATED`, `ERR_VALIDATION`, `ERR_NOT_FOUND` |
| `GET` | `/api/v1/admin/inquiries/contact/export` | `contact:export_csv` | No | Stream filtered inquiries as RFC 4180 CSV attachment | `ERR_UNAUTHENTICATED`, `ERR_FORBIDDEN` |
| `DELETE`| `/api/v1/admin/inquiries/contact/:id` | `contact:delete` (Super Admin) | Yes | Delete inquiry record | `ERR_FORBIDDEN`, `ERR_NOT_FOUND` |

---

## 5. Security & Governance Architecture

### A. Fail-Closed Administrative Identity
- Admin controllers invoke `requireAdminUserId($request)` which extracts `$request->getAttribute('admin_user_id')`.
- If missing, invalid, or non-positive, the controller halts and returns HTTP 401 Unauthorized (`ERR_UNAUTHENTICATED`).
- No fallback identities (`?? 1`) exist anywhere in the codebase.

### B. Least-Privilege RBAC Matrix
- `contact:list`: Super Admin, Website Admin, BD Manager.
- `contact:update_status`: Super Admin, Website Admin, BD Manager.
- `contact:add_note`: Super Admin, Website Admin, BD Manager.
- `contact:export_csv`: Super Admin, BD Manager.
- `contact:delete`: Super Admin only.
- Content Editor and HR Manager receive HTTP 403 `ERR_FORBIDDEN` across all contact inquiries admin routes.

### C. CSRF Protection
- All mutating administrative requests (`POST`, `PATCH`, `DELETE`) require a valid session CSRF token passed via the `X-CSRF-Token` header.
- Rejection of missing or forged CSRF tokens returns HTTP 403 `ERR_CSRF_MISSING` or `ERR_CSRF_INVALID`.

### D. Cryptographically Chained SHA-256 Audit Ledger
- State transitions, internal notes, assignments, exports, and deletions append an immutable record to `audit_logs` anchored by `prev_row_hash` and `row_hash` using HMAC SHA-256.
- Distinct audit actions recorded:
  - `INQUIRY_SUBMITTED`
  - `INQUIRY_STATUS_CHANGED`
  - `INQUIRY_NOTE_ADDED`
  - `INQUIRY_ASSIGNED`
  - `INQUIRY_EXPORTED`
  - `INQUIRY_DELETED`
- Audit failures trigger fail-closed compensating rollbacks, ensuring no untracked state mutations remain committed.

---

## 6. Testing Strategy
- **Unit Tests (`MediaStorageServiceTest` equivalent for Contact):** `ContactServiceTest` testing validation, reference generation, DAG state machine, transition rejection, note formatting, CSV generation, and audit rollback.
- **Integration Tests:** `ContactInquiryApiTest` testing public submission, rate limiting, honeypot dropping, admin listing, state transitions, RBAC enforcement, CSRF protection, and CSV export.
- **Root Suite Integration:** Standalone runner `tests/test_phase11_contact_enquiries_management.php` and integration into `backend/tests/run_tests.php`.
- **MySQL 8.0 Concurrency & Integrity Suite:** Test 10 in `backend/tests/mysql_security_concurrency_test.php` testing real MySQL ENUM constraints, foreign keys, row-level locking, and transactional audit integrity.
