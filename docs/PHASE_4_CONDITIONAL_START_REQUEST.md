# Voak Health Care — Phase 4 Conditional Start Request & Open Issues Register

**Document Reference:** `DOC-PH3-21-PHASE-4-CONDITIONAL-START-REQUEST`  
**Current Phase:** Phase 3 of 31 — Professional UI/UX Design (Reconciled Baseline)  
**Target Phase:** Phase 4 — PHP Backend Foundation & Architecture Setup  
**Date:** October 20, 2026  
**Current Status:** **PHASE 3 NOT ACCEPTED — PHASE 4 CONDITIONALLY AUTHORIZED**  
**Governance Authority:** Business Owner / Steering Committee  

---

## 1. Context & Governance Objective

This document formally records the **Phase 4 Conditional Start Request** in accordance with project governance protocols. Phase 3 deliverables (packaged in [`VOAK_PHASE_3_DESIGN_REVIEW_v10.zip`](file:///c:/Users/voakh/Downloads/voak%20health/VOAK_PHASE_3_DESIGN_REVIEW_v10.zip)) remain **NOT ACCEPTED**.

The business owner has granted approval to begin Phase 4 conditionally. The three critical items below are formally logged into the project risk register with designated owners, explicit impact assessments, and mandatory resolution criteria.

> [!IMPORTANT]
> **STRICT GOVERNANCE NOTICE:**
> - Phase 4 work is authorized strictly on a conditional basis and must respect all unresolved dependencies.
> - Conditional commencement of Phase 4 does **NOT** imply acceptance of Phase 3, does **NOT** indicate that browser tests passed in unequipped environments, does **NOT** imply visual reference acceptance, and does **NOT** resolve requirement traceability.

---

## 2. Register of Open Issues & Dependencies

```
+--------------------------------------------------------------------------------------------------------------------------------+
| ID         | ITEM / DEFECT                  | STATUS               | ASSIGNED OWNER       | MANDATORY RESOLUTION CRITERIA       |
+------------+--------------------------------+----------------------+----------------------+-------------------------------------+
| OPEN-01    | Responsive Browser Testing     | NOT INDEPENDENTLY    | QA Lead &            | Independent browser test execution  |
|            | Environment Tooling Limitation | VERIFIED             | Test Architect       | in verified browser environment OR  |
|            |                                |                      |                      | certified multi-device physical QA. |
+------------+--------------------------------+----------------------+----------------------+-------------------------------------+
| OPEN-02    | Visual Reference Parity        | PENDING BUSINESS     | Lead UI/UX Designer  | Formal visual inspection against    |
|            | Sign-off (Approved Image 2)    | SIGN-OFF             | & Business Owner     | Approved Reference Image 2 and      |
|            |                                |                      |                      | written business acceptance record. |
+------------+--------------------------------+----------------------+----------------------+-------------------------------------+
| OPEN-03    | Requirement Traceability       | BLOCKED (Awaiting    | Business Owner &     | Business-owner decision on          |
|            | Mapping Conflict (FR-PUB-004)  | Business Decision)   | Solutions Architect  | FR-PUB-004 mapping to PAGE-PUB-01   |
|            |                                |                      |                      | vs PAGE-PUB-10; RTM baseline update.|
+--------------------------------------------------------------------------------------------------------------------------------+
```

---

### Detailed Issue Records

### OPEN-01: Automated Responsive Browser Testing Limitation
- **Description:** In environments where a compatible Chromium/Edge browser binary is not auto-detected or accessible by the automated runner, the 310 responsive layout checks cannot execute in that specific environment. Consequently, those 310 checks cannot be reported as independently verified by an external reviewer without a compatible local browser engine.
- **Root Cause:** Environmental and tooling availability limitation on host/CI environments that lack installed or configured browser dependencies.
- **Assigned Owner:** QA Lead / DevOps & Test Infrastructure Engineer
- **Impact on Phase 4:** Minimal on pure backend foundation (PHP routing, autoloaders, environment config, DB domain models), but frontend templates connected during later phases may require layout adjustments if undetected viewport flaws exist.
- **Mandatory Resolution Criteria:**
  1. Provision a certified browser execution environment (Microsoft Edge / Chromium CDP).
  2. Execute `node tests/test_expanded_responsive.js` with all 310 assertions passing independently with 0px overflow and 0 console errors, OR execute a certified physical multi-device manual QA protocol.

---

### OPEN-02: Visual Reference Parity Sign-off (Approved Homepage Reference Image 2)
- **Description:** While technical implementation in `index.html` and `styles.css` has been iteratively aligned to Approved Reference Image 2 (organic clinician collage, squircle heart badge, gold sparkle star, floating facility card, 3 feature cards with pill tags, 8-tile bento, and trust strip), the implementation has not yet received formal client visual acceptance sign-off.
- **Assigned Owner:** Principal UI/UX Designer & Business Owner
- **Impact on Phase 4:** Backend architecture must remain strictly decoupled from visual presentation. No presentation markup may be hard-coded into PHP controllers, service classes, or database schemas.
- **Mandatory Resolution Criteria:**
  1. Business owner and designated brand stakeholders conduct formal visual comparison of [`01_desktop_homepage.png`](file:///c:/Users/voakh/Downloads/voak%20health/design/phase-03/screenshots/01_desktop_homepage.png) and [`02_mobile_homepage_390px.png`](file:///c:/Users/voakh/Downloads/voak%20health/design/phase-03/screenshots/02_mobile_homepage_390px.png) against [`approved_homepage_reference_image_2.png`](file:///c:/Users/voakh/Downloads/voak%20health/references/approved_homepage_reference_image_2.png).
  2. Stakeholders provide written sign-off or an enumerated punch-list of cosmetic adjustments.

---

### OPEN-03: Requirement Traceability Mapping Conflict (`FR-PUB-004` vs `PAGE-PUB-10`)
- **Description:** Accepted Phase 1 documentation contains an unresolved mapping conflict:
  - `docs/phase-01/15_REQUIREMENTS_TRACEABILITY_MATRIX.md` line 44 explicitly maps `FR-PUB-004` to `PAGE-PUB-01` (Homepage).
  - `docs/phase-01/02_PUBLIC_PAGE_BY_PAGE_SPECIFICATIONS.md` details `PAGE-PUB-10` (/partners-clients) as the Partners & Clients page.
  - `PAGE-PUB-10` is omitted from the RTM table ledger.
  - `tests/reconcile_baseline.py` Gate 5 is strictly reported as **`BLOCKED (Requires business-owner decision)`**.
- **Assigned Owner:** Business Owner & Systems Architect
- **Impact on Phase 4:** Phase 4 routing structures and mock data fixtures for Partners & Clients must remain modular and capable of fulfilling either a shared requirement mapping or an independent requirement ID.
- **Mandatory Resolution Criteria:**
  1. Business owner issues formal determination selecting one of:
     - **Option 1:** Ratify that `FR-PUB-004` governs both `PAGE-PUB-01` (home partner strip) and `PAGE-PUB-10` (dedicated partners directory).
     - **Option 2:** Allocate a dedicated requirement identifier (e.g. `FR-PUB-024`) specifically for `PAGE-PUB-10`.
  2. Formal update of accepted baseline documentation in the next planned baseline release.

---

## 3. Strict Boundary Enforcement

To maintain engineering discipline and prevent scope contamination:
1. **Conditional Authorization Only:** Phase 4 backend foundation work is authorized strictly under conditional terms.
2. **Phase 3 Remains Unaccepted:** Acceptance of Phase 3 is not implied and will remain unaccepted until formal sign-off.
3. **Traceability Non-Interference:** Existing Phase 0, 1, and 2 baseline specifications remain 100% frozen and unmodified.

---

## 4. Decision Record & Ratification

The Business Owner has formally made and recorded the following binding governance decision:

- **Decision Ratified:** **CONDITIONAL PHASE 4 COMMENCEMENT APPROVED**
- **Date of Decision:** October 20, 2026 (08:29:34 UTC+5:30)
- **Decision Authority:** Business Owner / Steering Committee
- **Ratification Text:** *"Approve conditional Phase 4 commencement with the 3 open items logged in the risk register (Phase 3 remains unaccepted and dependencies strictly tracked)."*

### Governance Directives for Conditional Execution:
1. **Phase 3 Acceptance Status:** Phase 3 remains explicitly **NOT ACCEPTED**. Approval to proceed conditionally does **NOT** imply acceptance of Phase 3, does **NOT** mean browser tests passed in unequipped environments, does **NOT** imply visual reference acceptance, and does **NOT** resolve requirement traceability.
2. **Phase 4 Operational Boundaries:** Phase 4 work is authorized **strictly on a conditional basis** and must respect all unresolved dependencies:
   - Backend APIs and domain models must remain strictly decoupled from frontend presentation (respecting OPEN-02).
   - Routing and API endpoints for Partners & Clients (`PAGE-PUB-10` / `FR-PUB-004`) must remain modular and capable of fulfilling either a shared mapping or an independent requirement ID (respecting OPEN-03).
   - Layout templates connected in later phases must anticipate independent verification once a certified browser environment is provisioned (respecting OPEN-01).
3. **Tracking & Accountability:** The assigned owners for OPEN-01, OPEN-02, and OPEN-03 must pursue their mandatory resolution criteria concurrently during conditional Phase 4 execution.
