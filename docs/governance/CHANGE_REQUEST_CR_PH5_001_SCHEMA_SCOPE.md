# Formal Change Request: Relational Schema Scope Reconciliation

**Change Request ID:** `CR-PH5-001`  
**Governing Gate:** Mandated Phase 5 Scope & Authorization Gate (Project Rules 1, 3, 10)  
**Document Reference:** `docs/governance/CHANGE_REQUEST_CR_PH5_001_SCHEMA_SCOPE.md`  
**Date Submitted:** October 21, 2026  
**Originating Role:** Lead System Architect & Senior Database Reliability Engineer  
**Baseline Authority to Amend:** Accepted Phase 0 Roadmap Summary (`docs/phase-00/13_COMPLETE_32_PHASE_TRACEABILITY.md`)  
**Target Specification:** Accepted Phase 2 Database Schema Architecture (`docs/phase-02/05_DATABASE_SCHEMA_DESIGN.md`)  
**Status:** **PENDING FORMAL BUSINESS OWNER APPROVAL — IMPLEMENTATION HELD**  

---

> [!WARNING]
> **GOVERNANCE STATUS: CHANGE REQUEST PENDING FORMAL BUSINESS OWNER APPROVAL**  
> In accordance with **Rule 1 & Rule 3 of the Project Governance Framework**, the Phase 0 accepted baseline roadmap (`docs/phase-00/13_COMPLETE_32_PHASE_TRACEABILITY.md`) remains strictly preserved at 20 relational tables until this Change Request is formally approved. Engineering will NOT generate migrations or DDL until the Business Owner executes Section 7 of this document.

---

## 1. Executive Summary & Change Description

During Phase 0, a high-level roadmap approximation estimated the persistence layer at "20 relational tables". 

During Phase 1 (Functional Specifications in `docs/phase-01/11_API_AND_DATABASE_REQUIREMENT_MAPPING.md`) and Phase 2 (System Architecture in `docs/phase-02/05_DATABASE_SCHEMA_DESIGN.md`), exhaustive domain modeling established that **exactly 28 relational domain entities** categorized across 7 functional clusters (plus 1 migration tracking table `migrations`) are required to support relational integrity, security isolation, and business workflows without unnormalized JSON anti-patterns.

This Change Request (`CR-PH5-001`) formally petitions the Business Owner to approve the expansion of the Phase 5 database scope from the early 20-table roadmap estimate to the **canonical 28-entity relational schema**.

---

## 2. Business & Engineering Rationale

Implementing strictly 20 tables would leave **8 critical domain and security entities** unrepresented, causing severe architectural defects:
1. **Missing Junction Tables (Data Integrity Breakdown):** In a pure 20-table model, many-to-many relationships (roles-to-permissions, users-to-roles, services-to-industries, proposals-to-services) would have to be collapsed into comma-separated strings or unstructured JSON arrays. This violates Boyce-Codd Normal Form (BCNF), prevents database-level foreign key cascade protection, and prevents SQL query indexing.
2. **Credential & Secret Exposure (Critical Security Risk):** If multi-factor authentication (TOTP) secrets are stored in the core `users` table instead of the isolated `admin_mfa_secrets` table, any administrative query (`SELECT * FROM users`) risks exposing encrypted MFA secret keys and hashed backup codes.
3. **Email Notification Reliability (Outbox Pattern):** Direct synchronous SMTP dispatch during HTTP form submissions causes web request timeouts and lost emails upon network blips. The dedicated `email_queue` table provides a resilient, durable transactional outbox.
4. **SEO & Routing Integrity:** Dynamic page renaming requires automated 301 redirects via `url_redirects` to maintain search engine ranking and prevent HTTP 404 broken links.

---

## 3. Canonical 28-Entity Inventory & Requirements Traceability

The proposed 28 domain entities map 100% to accepted Phase 1 functional requirements:

### Cluster I: Identity, RBAC & Administrative Security (8 Entities)
| # | Table Name | Key Purpose & Fields | Requirement Trace | Justification vs. 20-Table Draft |
|---|---|---|---|---|
| **1** | `users` | Staff identity, credentials, failed attempts, lockout | `FR-ADM-001`, `FR-ADM-002` | Core user identity (included in 20-table draft). |
| **2** | `roles` | 5 approved administrative roles (`super_admin`, etc.) | `FR-ADM-003` | Core RBAC role dictionary. |
| **3** | `permissions` | 45 granular canonical permission action keys | `FR-ADM-003` | Granular permission dictionary. |
| **4** | `role_permissions` | Compound PK (`role_id`, `permission_id`) junction | `FR-ADM-003` | **Essential junction** preventing denormalized JSON. |
| **5** | `user_roles` | Compound PK (`user_id`, `role_id`) junction | `FR-ADM-003` | **Essential junction** supporting staff role assignment. |
| **6** | `admin_sessions` | Session tokens, IP, 15m idle and 8h absolute timeouts | `FR-ADM-002` | High-security session tracking. |
| **7** | `admin_mfa_secrets` | Encrypted RFC 6238 TOTP secrets & hashed backup codes | `FR-ADM-002` | **Dedicated security isolation** preventing leak via `users`. |
| **8** | `password_resets` | Single-use cryptographically hashed reset tokens | `FR-ADM-001` | Secure password reset lifecycle. |

### Cluster II: Corporate CMS & Static Content (3 Entities)
| # | Table Name | Key Purpose & Fields | Requirement Trace | Justification vs. 20-Table Draft |
|---|---|---|---|---|
| **9** | `pages` | 22 static public page copy, meta tags, status | `FR-ADM-004` | Public content management. |
| **10** | `content_revisions` | Historical JSON snapshots for rollback & diffs | `FR-ADM-004` | **Audit compliance** for static page revisions. |
| **11** | `homepage_configs` | Hero headline, bento layout order, social proof toggles | `FR-ADM-005` | Master homepage layout curation. |

### Cluster III: B2B Services & Healthcare Industry Verticals (3 Entities)
| # | Table Name | Key Purpose & Fields | Requirement Trace | Justification vs. 20-Table Draft |
|---|---|---|---|---|
| **12** | `services` | 6 B2B healthcare service pillars, deliverables, SLAs | `FR-ADM-006` | Public service catalog. |
| **13** | `industries` | 6 healthcare sector verticals, regulatory burden specs | `FR-ADM-007` | Healthcare industry verticals. |
| **14** | `service_industry_mappings` | Compound PK (`service_id`, `industry_id`) junction | `FR-ADM-006`, `FR-ADM-007` | **Essential junction** connecting services to sectors. |

### Cluster IV: Media Library & Asset Governance (1 Entity)
| # | Table Name | Key Purpose & Fields | Requirement Trace | Justification vs. 20-Table Draft |
|---|---|---|---|---|
| **15** | `media_assets` | Public WebP image library, dimensions, mandatory alt text | `FR-ADM-008` | Centralized media governance. |

### Cluster V: Inbound B2B Inquiries, Partnerships & Proposals (4 Entities)
| # | Table Name | Key Purpose & Fields | Requirement Trace | Justification vs. 20-Table Draft |
|---|---|---|---|---|
| **16** | `contact_inquiries` | Inbound contact submissions, status FSM, internal notes | `FR-ADM-009` | Inbound lead management. |
| **17** | `partnership_inquiries`| Strategic alliance intake, tiering, pipeline stage | `FR-ADM-010` | Healthcare partnership CRM. |
| **18** | `proposals` | 4-step enterprise RFP wizard submissions, budget bands | `FR-ADM-011` | Enterprise commercial proposals. |
| **19** | `proposal_services` | Compound PK (`proposal_id`, `service_id`) junction | `FR-ADM-011` | **Essential junction** mapping multi-service bids. |

### Cluster VI: Recruitment, Articles & Case Studies (5 Entities)
| # | Table Name | Key Purpose & Fields | Requirement Trace | Justification vs. 20-Table Draft |
|---|---|---|---|---|
| **20** | `job_postings` | Careers vacancies, department, employment type, status | `FR-ADM-013` | Careers board management. |
| **21** | `career_applications`| Candidate submissions, quarantined resume path, FSM | `FR-ADM-014` | Candidate applicant tracking. |
| **22** | `articles` | Insights whitepapers, calculated reading times, tags | `FR-ADM-016` | Thought leadership content CMS. |
| **23** | `case_studies` | Problem-Intervention-Outcome proof, consent flag | `FR-ADM-015` | Client evidence & case studies. |
| **24** | `faqs` | Procurement & compliance Q&A accordions, categories | `FR-PUB-019` | Public procurement FAQs. |

### Cluster VII: System Outbox, Redirects & Immutable Audit Logs (4 Entities)
| # | Table Name | Key Purpose & Fields | Requirement Trace | Justification vs. 20-Table Draft |
|---|---|---|---|---|
| **25** | `email_queue` | Durable transactional email outbox, claiming, retries | `FR-ADM-012` | **Resilient SMTP delivery** via Outbox pattern. |
| **26** | `url_redirects` | Automated 301 SEO redirect engine, slug rename map | `FR-ADM-017` | **SEO link equity protection**. |
| **27** | `system_settings` | Dynamic configuration variables (`legal_entity_name`) | `FR-ADM-018` | Dynamic system settings. |
| **28** | `audit_logs` | Immutable SHA-256 row-hash chained audit ledger | `FR-ADM-019` | Enterprise compliance audit trail. |

*System Infrastructure Table:*
- `migrations`: Idempotent migration runner execution tracking ledger (`id`, `migration_name UNIQUE`, `batch`, `executed_at`).

---

## 4. Security & Risk Analysis

| Scenario | Risk Level | Architectural Consequence | Mitigation Provided by CR-PH5-001 |
|---|---|---|---|
| **Remaining at 20 Tables** | **CRITICAL** | Forces denormalization of permissions, roles, and RFP services into JSON strings; risks MFA secret leakage by combining auth secrets into `users`; loses durable email retry capability. | Expanding to 28 entities normalizes all relationships and enforces strict least-privilege security boundaries. |
| **Approving 28 Entities** | **NEGLIGIBLE** | Requires 28 discrete DDL migration files instead of 20. Hostinger MySQL 8.x handles thousands of InnoDB tables effortlessly. | Standardized versioned migrations running idempotently in ~2 seconds. |

---

## 5. Technical Hosting Compliance (Hostinger CloudLinux MySQL)

- **Engine:** Strictly `InnoDB` across 100% of tables (ACID transactions, row-level locking, foreign key cascades).
- **Collation:** `utf8mb4` / `utf8mb4_unicode_ci` (full 4-byte Unicode for medical symbols).
- **Timezone:** Standardized UTC storage (`CURRENT_TIMESTAMP`).
- **DDL Handling:** MySQL executes implicit commits on DDL; migrations are structured as individual, idempotent versioned steps with explicit rollback definitions.

---

## 6. Implementation Plan Upon Approval
 
Once approved by the Business Owner:
1. Initialize the CLI migration runner (`backend/cli/migrate.php`) and root compatibility shim (`database/migrate.php` delegating directly to the runner to honor the Phase 0 baseline command).
2. Implement 28 migration files in `backend/database/migrations/` ordered by cluster dependencies.
3. Validate against a local isolated test database (migration execution, idempotency, foreign key enforcement, failure handling, and clean rollback).
4. Update `docs/phase-00/13_COMPLETE_32_PHASE_TRACEABILITY.md` to formally reflect the approved 28-entity scope.

---

## 7. Formal Business Owner Decision & Sign-off Gate

*To be executed by the authorized Business Owner or Steering Committee:*

```markdown
================================================================================
CHANGE REQUEST CR-PH5-001 DECISION RECORD
================================================================================

[ ] APPROVED:
    I hereby approve Change Request CR-PH5-001. The Phase 5 database scope
    is formally expanded from the 20-table roadmap estimate to the canonical
    28-entity relational schema (plus migrations tracking table) defined in
    docs/phase-02/05_DATABASE_SCHEMA_DESIGN.md.

[ ] REVISION REQUESTED:
    I do not approve CR-PH5-001. Engineering must provide an alternative
    schema design adhering strictly to the 20-table roadmap estimate.

Business Owner / Authorized Signatory: _________________________________________
Title / Role:                           _________________________________________
Signature:                              _________________________________________
Date:                                   _________________________________________
================================================================================
```
