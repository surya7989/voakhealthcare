# Architecture Decision Record: OPEN-03 Requirement Traceability

**Record ID:** `ADR-GOV-OPEN-03`  
**Document Reference:** `docs/governance/DECISION_RECORD_OPEN_03_REQUIREMENT_TRACEABILITY.md`  
**Governing Issue:** `OPEN-03`  
**Creation Date:** October 20, 2026  
**Status:** **BLOCKED — BUSINESS DECISION REQUIRED**  
**Decision Authority:** Voak Health Care Business Owner / Project Steering Committee  
**Affected Specifications:**
- `docs/phase-01/01_MASTER_FUNCTIONAL_REQUIREMENTS.md`
- `docs/phase-01/02_PUBLIC_PAGES_SPECIFICATION.md`
- `docs/phase-01/15_REQUIREMENTS_TRACEABILITY_MATRIX.md`

---

## 1. Problem Statement & Context

During the Phase 3 governance and baseline verification audit, an architectural mapping discrepancy was identified regarding `FR-PUB-004` (Configurable Social Proof Ribbon / Unverified Client Proof Omission):

1. **Table 1 (`docs/phase-01/15_REQUIREMENTS_TRACEABILITY_MATRIX.md` Line 44):**  
   Maps `FR-PUB-004` strictly to `PAGE-PUB-01` (`/` - Homepage):
   > *"As a compliance officer, I want unverified client proof omitted so the site makes no unconfirmed claims. Total DOM collapse when unverified."*
2. **Section 4.1 (`docs/phase-01/15_REQUIREMENTS_TRACEABILITY_MATRIX.md` Line 146):**  
   Maps `FR-PUB-004` to **Phase 24 (Remaining Public Pages & Legal Views)**, specifically targeting `PAGE-PUB-10` (`/partners-clients` - Partners & Clients Page).
3. **Missing Deliverable Entry:**  
   `PAGE-PUB-10` does not possess its own dedicated single-requirement row in Table 1 of the accepted Phase 1 Traceability Matrix.

As mandated by **Rule 1 & Rule 3 of the Project Governance Framework**:
> *"Do not silently resolve OPEN-03. Obtain the documented business decision or leave the issue blocked. Do not alter accepted Phase 0–2 baseline specifications without formal change approval."*

---

## 2. Decision Options

### Option A: Formally Ratify Shared Mapping (Recommended)
- **Description:** Formally ratify that `FR-PUB-004` governs the social proof and accreditation omission logic across **both** `PAGE-PUB-01` (Homepage ribbon) and `PAGE-PUB-10` (`/partners-clients` dedicated grid).
- **Architectural Rationale:** Both pages render the same underlying entity models (`partners`, `testimonials`, `accreditations`) and enforce the identical compliance rule: total DOM suppression if partner verification status is false or unverified.
- **Impact on Accepted Baselines:** Zero breaking change to the existing requirement catalog IDs. Requires a single row annotation in `docs/phase-01/15_REQUIREMENTS_TRACEABILITY_MATRIX.md` upon formal sign-off.
- **Development Effort:** Low / Zero regression risk.

### Option B: Allocate Distinct Requirement ID for PAGE-PUB-10
- **Description:** Allocate a new unique identifier (e.g., `FR-PUB-024`: Dedicated Partners & Client Case Directory) specifically for `PAGE-PUB-10`.
- **Architectural Rationale:** Treats the dedicated directory page as a separate business deliverable from the homepage ribbon component.
- **Impact on Accepted Baselines:** Requires formal amendment of Phase 1 functional requirements count (increasing from 62 to 63 requirements), updating traceability matrices, test catalogs, and downstream phase scoping.
- **Development Effort:** Moderate / Requires updating multiple baseline specification documents.

---

## 3. Recommendation & Current Governance Status

- **Architect Recommendation:** **Option A** (Least disruptive; preserves numbering integrity across Phase 0–2 accepted baselines).
- **Action Taken:** Temporary non-breaking backend scaffolding endpoint implemented at `GET /api/v1/public/partners-preview` returning standard envelope with explicit OPEN-03 metadata.
- **Current Status:** **BLOCKED — BUSINESS DECISION REQUIRED**  
  No changes have been made to Phase 1 baseline specification documents. The mapping remains unapplied until the Business Owner or Steering Committee issues a formal written determination.

---

## 4. Formal Decision Sign-off

> [!NOTE]
> **GOVERNANCE STATUS: RATIFIED — OPTION A SELECTED**  
> Formal decision authority was exercised on October 21, 2026. Option A (Ratify shared mapping of REQ-EXT-023 across homepage & directory) is formally approved and ratified.

| Field | Detail |
|---|---|
| **Selected Option:** | **Option A: Ratify shared mapping of REQ-EXT-023 across homepage ribbon & directory** |
| **Authorized Sign-off By:** | Voak Health Care Business Owner / Steering Committee |
| **Role:** | Voak Health Care Business Owner / Steering Committee |
| **Date:** | October 21, 2026 |
| **Resolution Notes:** | Option A adopted. REQ-EXT-023 maps to Homepage discovery preview and Partners dedicated directory. Non-breaking scaffolding endpoint `GET /api/v1/public/partners-preview` ratified. |
