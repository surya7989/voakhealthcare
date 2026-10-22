# VOAK HEALTH CARE — PHASE 9: HEALTHCARE INDUSTRIES & SECTORS MANAGEMENT
**Architecture, Relational Mapping, Security & Verification Specification**

---

### 1. Phase 9 Overview & Objectives
Phase 9 delivers the enterprise **Healthcare Industries & Customer Verticals Management System** (`ADM-007` / `FR-ADM-008` / `FR-WF-005` / `TEST-ADM-008`).
This module governs the 6 canonical Australian healthcare customer sectors, their regulatory governance frameworks, pain points/challenges, display ordering, public caching with ETags, automated 301 SEO redirects on slug mutation, and bidirectional relational mapping to the 6 core healthcare service pillars via `service_industry_mappings`.

---

### 2. Canonical Healthcare Industry Verticals
The 6 standard Australian healthcare customer sectors configured in Phase 9:

| Code | Industry Sector | Target Facility Profile | Core Sector Governance & Regulatory Frameworks | Canonical Slug |
|:---|:---|:---|:---|:---|
| **IND-01** | Hospitals & Health Services | Public & Private Hospital Networks | ACSQHC; NSQHS Standards 1–8; Mandatory SAC 1 & 2 clinical incident escalation; Hand Hygiene Australia | `hospitals-health-services` |
| **IND-02** | Residential Aged Care Facilities | Aged Care Homes & Retirement Villages | Aged Care Quality and Safety Commission (ACQSC); Mandatory 215 direct care minutes (44 RN minutes); 24/7 on-site RN mandate | `residential-aged-care` |
| **IND-03** | Primary Care & General Practice | Medical Centers & GP Clinics | RACGP Standards for General Practices (5th edition); MBS billing compliance; Cold chain vaccine management | `primary-care-general-practice` |
| **IND-04** | Allied Health Networks | Multi-Disciplinary Rehabilitation Clinics | NDIS Quality and Safeguards Commission; AHPRA Allied Health National Boards; NDIS Worker Screening | `allied-health-networks` |
| **IND-05** | Diagnostic & Pathology Centers | Pathology Labs & Imaging Facilities | NATA / RCPA ISO 15189 Medical Laboratories Accreditation; DIAS Diagnostic Imaging Standards | `diagnostic-pathology-centers` |
| **IND-06** | Corporate & Occupational Health | Corporate Campuses & Industrial Sites | Safe Work Australia & Comcare; WHS Act 2011; National Standard for Health Assessment of Rail Safety Workers | `corporate-occupational-health` |

---

### 3. Bidirectional Relational Mapping Matrix
Bidirectional `m:n` mapping between Services (`services`) and Industries (`industries`) stored in `service_industry_mappings`:

| Service Pillar | Hospitals (IND-01) | Aged Care (IND-02) | Primary Care (IND-03) | Allied Health (IND-04) | Diagnostics (IND-05) | Corporate (IND-06) |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| **SVC-01: Clinical Workforce Solutions** | Primary | Primary | Secondary | Secondary | Secondary | Secondary |
| **SVC-02: Healthcare Facility Operations** | Primary | Primary | Secondary | — | Secondary | — |
| **SVC-03: Health Administration Support** | Primary | Secondary | Primary | Primary | Primary | — |
| **SVC-04: Clinical Compliance & Governance** | Primary | Primary | Secondary | Secondary | Secondary | Secondary |
| **SVC-05: Crisis Turnaround Workforce** | Primary | Primary | Secondary | Secondary | Secondary | Primary |
| **SVC-06: Healthcare Consulting & Advisory** | Primary | Primary | Secondary | Secondary | — | Primary |

---

### 4. Database Schema Alignment
Existing Migration 13 (`industries`) and Migration 14 (`service_industry_mappings`) are canonical:
- `industries`: `id`, `title`, `slug`, `summary`, `regulatory_burden_specs` (JSON), `pain_points_json` (JSON), `is_published`, `display_order`, `created_at`, `updated_at`.
- `service_industry_mappings`: `service_id` (FK cascade), `industry_id` (FK cascade), `created_at`. Composite PK: `(service_id, industry_id)`.
- `url_redirects`: Migration 26, leveraged for automated 301 SEO redirects on industry slug mutations.

---

### 5. API Contracts

#### 5.1 Public Endpoints:
1. `GET /api/v1/public/industries` (and alias `GET /api/industries`):
   - Returns all published industries ordered by `display_order ASC`.
   - Includes deterministic SHA-256 ETag header; responds with `304 Not Modified` on `If-None-Match`.
2. `GET /api/v1/public/industries/:slug` (and alias `GET /api/industries/:slug`):
   - Returns industry details including mapped services (`services` array).
   - If slug has been renamed, emits `301 Moved Permanently` to `/industries/:newSlug` from `url_redirects`.
   - Emits true `404 Not Found` for draft, archived, or non-existent slugs.

#### 5.2 Administrative CMS Endpoints (`/api/v1/admin/industries`):
- `GET /api/v1/admin/industries` — List all industries with pagination, publication status filter, and keyword search. (Requires `industry:update` or `industry:create`).
- `POST /api/v1/admin/industries` — Create industry vertical. (Requires `industry:create` + CSRF).
- `GET /api/v1/admin/industries/:id` — Show single industry with mapped services. (Requires `industry:update`).
- `PUT /api/v1/admin/industries/:id` — Update industry. If slug changes, auto-records 301 redirect in `url_redirects`. (Requires `industry:update` + CSRF).
- `PATCH /api/v1/admin/industries/:id/status` — Toggle publication state. (Requires `industry:delete` for admin-level publishing authority + CSRF).
- `PATCH /api/v1/admin/industries/reorder` — Reorder display sequence. (Requires `industry:update` + CSRF).
- `DELETE /api/v1/admin/industries/:id` — Delete industry vertical and cascade relational mappings. (Requires `industry:delete` + CSRF).

---

### 6. Security Guarantees & Non-Negotiables
1. **Fail-Closed Administrative Identity**: Missing or invalid session strictly throws `AuthenticationException` (HTTP 401). No fallback user IDs (`admin_user_id ?? 1`).
2. **Least-Privilege RBAC**:
   - Super Admin & Website Admin: Full CRUD, publishing, and deletion authority.
   - Content Editor: Can create and update draft verticals, but strictly denied from publishing (`industry:delete` required) or deleting (HTTP 403 `ERR_FORBIDDEN`).
   - BD & HR Managers: Completely denied (HTTP 403 `ERR_FORBIDDEN`).
3. **CSRF Protection**: Enforced on all mutations (`POST`, `PUT`, `PATCH`, `DELETE`).
4. **Audit Logging & Compensating Rollback**: Chained SHA-256 audit logs with `prev_row_hash` and `row_hash` (`INDUSTRY_CREATED`, `INDUSTRY_UPDATED`, `INDUSTRY_SLUG_CHANGED`, `INDUSTRY_STATUS_CHANGED`, `INDUSTRIES_REORDERED`, `INDUSTRY_DELETED`). If audit write fails, database mutations are rolled back.
5. **Stored-XSS Sanitization**: Rich summaries, pain points, and regulatory frameworks pass through `HtmlSanitizer`.
6. **Cross-Database Row Locking**: Dynamic driver detection for `FOR UPDATE` under MySQL 8.0.
