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
| **Phase 3** | UI/UX Design System & Prototypes | **NOT ACCEPTED (Pending Business Sign-Off)** | `docs/phase-03/17_PHASE_3_FINAL_COMPLETION_REPORT.md`, `docs/phase-03/21_HOMEPAGE_VISUAL_PARITY_SIGN_OFF_REPORT.md` |
| **Phase 4** | PHP Backend Foundation | **CONDITIONALLY AUTHORIZED — REMEDIATED & READY FOR FORMAL ACCEPTANCE** | `docs/phase-04/02_PHASE_4_COMPLETION_REPORT.md`, `docs/phase-04/06_PHASE_4_ACCEPTANCE_PACKAGE.md` |
| **Phase 5** | Database Architecture & Production Schema | **PLANNING COMPLETE — IMPLEMENTATION HELD PENDING AUTHORIZATION** | `docs/phase-05/01_DATABASE_ARCHITECTURE_AND_PRODUCTION_SCHEMA_PLAN.md`, `docs/phase-05/02_PHASE_5_READINESS_REPORT.md` |
| **Phases 6–31**| Implementation, Integration & Operations | **NOT AUTHORIZED** | `docs/phase-00/13_COMPLETE_32_PHASE_TRACEABILITY.md` |

---

## 2. Central Register of Open Items (Phase 3 Governance Blockers)

| Issue ID | Description | Technical State | Governance / Authorization State | Action Required to Unblock |
|---|---|---|---|---|
| **`OPEN-01`** | Responsive Browser Verification (310 checks: 11 master + 242 public [22×11] + 57 admin [19×3]) | **VERIFIED ON-HOST (100% PASS)**<br>Real Headless Microsoft Edge (v155.0.4283.62): 310/310 passed, 0px blowout, zero JS errors. | **Awaiting Formal Business Acceptance** | Business Owner confirms acceptance of test evidence (`tests/results/phase-03-responsive-results.json`). |
| **`OPEN-02`** | Visual Parity with Approved Reference Image 2 | **EVIDENCE COMPILED**<br>Desktop (1440px), Mobile (390px), Tablet (768px), and Side-by-Side composite generated and verified. | **PENDING BUSINESS SIGN-OFF** | Business Owner reviews `docs/phase-03/21_HOMEPAGE_VISUAL_PARITY_SIGN_OFF_REPORT.md` and provides written sign-off or punch-list. |
| **`OPEN-03`** | `FR-PUB-004` Traceability Conflict (`PAGE-PUB-01` vs `PAGE-PUB-10`) | **ARCHITECTURAL SCAFFOLDING ACTIVE**<br>`GET /api/v1/public/partners-preview` serves non-breaking modular envelope. | **BLOCKED — BUSINESS DECISION REQUIRED** | Business Owner selects Option A (Shared Mapping - Recommended) or Option B (Allocate new ID) in `docs/governance/DECISION_RECORD_OPEN_03_REQUIREMENT_TRACEABILITY.md`. |

---

## 3. Mandatory Governance Controls & Boundary Rules

1. **No Baseline Tampering:** Accepted Phase 0–2 baseline specifications must never be modified to force test passes or paper over conflicts without formal change approval.
2. **No Fabricated Approvals:** Signatures, approvals, browser outputs, deployment results, and client decisions must never be assumed or generated.
3. **Phase 5 Implementation Hard Stop:** No production database migrations, live schema creation, or Phase 5 source code may be committed until the repository holds written Phase 4 acceptance and explicit Phase 5 authorization from the Business Owner.
4. **Security & Zero Secrets:** Zero credentials, private keys, live database passwords, or unredacted `.env` files may enter git history.

---

## 4. Actionable Business Owner Decision & Approval Checklist

The following four specific items require formal Business Owner review and action:

| Item # | Governance Area | Document to Review & Sign | Action Required by Business Owner | Engineering Recommendation |
|---|---|---|---|---|
| **1** | **Phase 4 Backend Foundation Acceptance** | [`docs/phase-04/06_PHASE_4_ACCEPTANCE_PACKAGE.md`](../phase-04/06_PHASE_4_ACCEPTANCE_PACKAGE.md) (Gate B) | Sign off Gate B to formally accept Phase 4 PHP backend foundation based on 46/46 automated test pass and CI verification. | **RECOMMENDED FOR FORMAL ACCEPTANCE** |
| **2** | **Phase 3 OPEN-02 Visual Parity Sign-Off** | [`docs/phase-03/21_HOMEPAGE_VISUAL_PARITY_SIGN_OFF_REPORT.md`](../phase-03/21_HOMEPAGE_VISUAL_PARITY_SIGN_OFF_REPORT.md) (Section 5) | Review desktop (1440px), mobile (390px), and comparison screenshots against Image 2 benchmark; sign approval or provide punch-list. | **READY FOR BUSINESS SIGN-OFF** |
| **3** | **Phase 3 OPEN-03 Requirement Traceability Decision** | [`docs/governance/DECISION_RECORD_OPEN_03_REQUIREMENT_TRACEABILITY.md`](DECISION_RECORD_OPEN_03_REQUIREMENT_TRACEABILITY.md) (Section 4) | Select **Option A** (Shared mapping across Homepage ribbon & Partners page) or **Option B** (Allocate new requirement ID). | **RECOMMEND OPTION A** (Least disruptive; zero regression risk) |
| **4** | **Phase 5 Commencement Authorization** | [`docs/governance/PHASE_5_AUTHORIZATION_REQUEST.md`](PHASE_5_AUTHORIZATION_REQUEST.md) (Section 4) and [`docs/phase-04/06_PHASE_4_ACCEPTANCE_PACKAGE.md`](../phase-04/06_PHASE_4_ACCEPTANCE_PACKAGE.md) (Gate C) | Sign authorization granting permission to begin Phase 5 database migration runner & production schema implementation. | **RECOMMENDED FOR AUTHORIZATION (Contingent on Item 1)** |

