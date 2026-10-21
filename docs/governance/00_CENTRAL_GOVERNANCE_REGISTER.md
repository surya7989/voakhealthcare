# Voak Health Care — Central Project Governance Register

**Document Reference:** `DOC-GOV-CENTRAL-REGISTER-2026-10-21`  
**Register ID:** `VOAK-GOV-REG-001`  
**Project:** Voak Health Care Corporate B2B Platform  
**Repository:** `https://github.com/surya7989/voakhealthcare.git`  
**Current Branch:** `main`  
**Last Updated:** October 21, 2026  
**Governing Authorities:**
- **Business Owner & Steering Committee:** Project Authority & Commercial Gatekeeper
- **Lead Technical Architect & Application Security Engineer:** Engineering & Quality Gatekeeper

---

## 1. Master Phase Authorization Status (Phases 0 through 31)

| Phase ID | Phase Name | Governance Gate Status | Evidence / Reference Document |
|---|---|---|---|
| **Phase 0** | Foundation, Scope & Strategy | **ACCEPTED** | `docs/phase-00/16_PHASE_0_FINAL_REPORT.md` |
| **Phase 1** | Requirements & Traceability | **ACCEPTED** | `docs/phase-01/18_PHASE_1_FINAL_REPORT.md` |
| **Phase 2** | Technical & System Architecture | **ACCEPTED** | `docs/phase-02/16_PHASE_2_FINAL_REPORT.md` |
| **Phase 3** | UI/UX Design System & Prototypes | **FORMALLY ACCEPTED** | `docs/phase-03/17_PHASE_3_FINAL_COMPLETION_REPORT.md`, `docs/phase-03/21_HOMEPAGE_VISUAL_PARITY_SIGN_OFF_REPORT.md` |
| **Phase 4** | PHP Backend Foundation | **FORMALLY ACCEPTED** | `docs/phase-04/02_PHASE_4_COMPLETION_REPORT.md`, `docs/phase-04/06_PHASE_4_ACCEPTANCE_PACKAGE.md` |
| **Phase 5** | Database Architecture & Production Schema | **ACCEPTED IN PRINCIPLE (Business Owner Directive)**<br>*Note: Live MySQL Staging Validation Outstanding; Formal Signatory Identity Pending* | `docs/phase-05/03_PHASE_5_COMPLETION_REPORT.md`, `docs/phase-05/04_PHASE_5_ACCEPTANCE_PACKAGE.md` |
| **Phase 6** | Admin Authentication, RBAC & MFA | **ACTIVE DEVELOPMENT DIRECTLY ON MAIN (Business Owner Directive)**<br>*Note: Authorized under GV-PH6-001; Gate E & Staging Deployment Remain Gated* | `docs/governance/GOVERNANCE_VARIANCE_GV_PH6_001_INITIAL_DEVELOPMENT.md`, `docs/phase-06/01_PHASE_6_IMPLEMENTATION_PLAN_AND_READINESS_CHECKLIST.md` |
| **Phases 7–31**| Implementation, Integration & Operations | **NOT AUTHORIZED (Precedent Phases Required)** | `docs/phase-00/13_COMPLETE_32_PHASE_TRACEABILITY.md` |

---

## 2. Central Register of Open Items (Phase 3 Governance Blockers)

| Issue ID | Description | Technical State | Governance / Authorization State | Current Status |
|---|---|---|---|---|
| **`OPEN-01`** | Responsive Browser Verification (310 checks: 11 master + 242 public [22×11] + 57 admin [19×3]) | **VERIFIED ON-HOST (100% PASS)**<br>Real Headless Microsoft Edge (v155.0.4283.62): 310/310 passed, 0px blowout, zero JS errors. | **FORMALLY CONFIRMED & ACCEPTED** | Confirmed by Business Owner on October 21, 2026 (`tests/results/phase-03-responsive-results.json`). |
| **`OPEN-02`** | Visual Parity with Approved Reference Image 2 | **EVIDENCE COMPILED**<br>Desktop (1440px), Mobile (390px), Tablet (768px), and Side-by-Side composite generated and verified. | **FORMALLY SIGNED OFF & APPROVED** | Signed off by Business Owner on October 21, 2026 (`docs/phase-03/21_HOMEPAGE_VISUAL_PARITY_SIGN_OFF_REPORT.md`). |
| **`OPEN-03`** | `FR-PUB-004` Traceability Conflict (`PAGE-PUB-01` vs `PAGE-PUB-10`) | **ARCHITECTURAL SCAFFOLDING ACTIVE**<br>`GET /api/v1/public/partners-preview` serves non-breaking modular envelope. | **RATIFIED — OPTION A SELECTED** | Option A ratified by Business Owner on October 21, 2026 (`docs/governance/DECISION_RECORD_OPEN_03_REQUIREMENT_TRACEABILITY.md`). |

---

## 3. Mandatory Governance Controls & Boundary Rules

1. **No Baseline Tampering:** Accepted Phase 0–2 baseline specifications must never be modified to force test passes or paper over conflicts without formal change approval.
2. **No Fabricated Approvals:** Signatures, approvals, browser outputs, deployment results, and client decisions must never be assumed or generated.
3. **Phase 5 Implementation Hard Stop:** No production database migrations, live schema creation, or Phase 5 source code may be committed until the repository holds written Phase 4 acceptance and explicit Phase 5 authorization from the Business Owner. (Condition satisfied October 21, 2026).
4. **Security & Zero Secrets:** Zero credentials, private keys, live database passwords, or unredacted `.env` files may enter git history.

---

## 4. Actionable Business Owner Decision & Approval Checklist

The following seven specific items were reviewed and formally addressed by the Business Owner:

| Item # | Governance Area | Document to Review & Sign | Action Executed by Business Owner | Decision Outcome |
|---|---|---|---|---|
| **1** | **Phase 4 Backend Foundation Acceptance** | [`docs/phase-04/06_PHASE_4_ACCEPTANCE_PACKAGE.md`](../phase-04/06_PHASE_4_ACCEPTANCE_PACKAGE.md) (Gate B) | Signed Gate B to formally accept Phase 4 PHP backend foundation based on 46/46 automated test pass and CI verification. | **[X] FORMALLY ACCEPTED** |
| **2** | **Phase 3 OPEN-01 Responsive Verification Confirmation** | [`docs/governance/00_CENTRAL_GOVERNANCE_REGISTER.md`](00_CENTRAL_GOVERNANCE_REGISTER.md) (Section 2) & [`tests/results/phase-03-responsive-results.json`](../../tests/results/phase-03-responsive-results.json) | Confirmed acceptance of the 310/310 responsive test pass evidence across all 22 public pages and 19 admin modules. | **[X] FORMALLY CONFIRMED** |
| **3** | **Phase 3 OPEN-02 Visual Parity Sign-Off** | [`docs/phase-03/21_HOMEPAGE_VISUAL_PARITY_SIGN_OFF_REPORT.md`](../phase-03/21_HOMEPAGE_VISUAL_PARITY_SIGN_OFF_REPORT.md) (Section 5) | Reviewed desktop (1440px), mobile (390px), and comparison screenshots against Image 2 benchmark; signed approval. | **[X] FORMALLY APPROVED** |
| **4** | **Phase 3 OPEN-03 Requirement Traceability Decision** | [`docs/governance/DECISION_RECORD_OPEN_03_REQUIREMENT_TRACEABILITY.md`](DECISION_RECORD_OPEN_03_REQUIREMENT_TRACEABILITY.md) (Section 4) | Selected **Option A** (Shared mapping across Homepage ribbon & Partners page). | **[X] OPTION A RATIFIED** |
| **5** | **Change Request CR-PH5-001 (Schema Scope Reconciliation)** | [`docs/governance/CHANGE_REQUEST_CR_PH5_001_SCHEMA_SCOPE.md`](CHANGE_REQUEST_CR_PH5_001_SCHEMA_SCOPE.md) (Section 7) | Approved formal expansion from 20-table roadmap estimate to canonical 28-entity relational schema (covering junction tables and isolated credentials). | **[X] CR-PH5-001 APPROVED** |
| **6** | **Phase 5 Commencement Authorization** | [`docs/governance/PHASE_5_AUTHORIZATION_REQUEST.md`](PHASE_5_AUTHORIZATION_REQUEST.md) (Section 4) and [`docs/phase-04/06_PHASE_4_ACCEPTANCE_PACKAGE.md`](../phase-04/06_PHASE_4_ACCEPTANCE_PACKAGE.md) (Gate C) | Signed authorization granting permission to begin Phase 5 database migration runner & production schema implementation. | **[X] FORMALLY AUTHORIZED** |
| **7** | **Phase 5 Database Acceptance (Gate D)** | [`docs/phase-05/04_PHASE_5_ACCEPTANCE_PACKAGE.md`](../phase-05/04_PHASE_5_ACCEPTANCE_PACKAGE.md) (Gate D) | Communicated business directive on October 21, 2026: *"PHASE 5 ACCEPTED, DESPITE OUTSTANDING LIVE MYSQL STAGING VALIDATION."* | **[X] ACCEPTED IN PRINCIPLE**<br>*(Live MySQL Staging Validation Outstanding; Formal Signatory Identity Pending)* |
| **8** | **Governance Variance GV-PH6-001 (Phase 6 Direct-to-Main Development)** | [`docs/governance/GOVERNANCE_VARIANCE_GV_PH6_001_INITIAL_DEVELOPMENT.md`](GOVERNANCE_VARIANCE_GV_PH6_001_INITIAL_DEVELOPMENT.md) | Explicit directive issued October 21, 2026: *"I want Phase 6 developed directly in the existing main branch. Do not create a separate Phase 6 feature branch or separate sub-branches. All verified fixes and completed components must be committed and pushed directly to main, with tests run throughout development."* | **[X] AUTHORIZED VIA DIRECTIVE**<br>*(Direct-to-main incremental development active; Staging Deployment & Gate E Gated)* |



