# Voak Health Care — Phase 3 V10 UI/UX Design System Review Package
## Reconciled Master Visual Prototype & Evidence Package (V10 Corrective Package)

**Package:** `VOAK_PHASE_3_DESIGN_REVIEW_v10.zip`  
**Phase:** Phase 3 of 31 — Professional UI/UX Design (V10 Corrective Baseline)  
**Date:** October 20, 2026  
**Status:** PHASE 3 NOT ACCEPTED (SUBMITTED FOR INDEPENDENT ACCEPTANCE REVIEW) — PHASE 4 STRICTLY NOT AUTHORIZED  
**Total Scope:** Exactly 32 Phases (Phase 0 through Phase 31)

---

### 1. How to Run & Preview the Prototype

You can inspect the prototype in either of two ways:

#### Option A: Lightweight Local HTTP Server (Recommended)
1. Ensure Node.js is installed.
2. Open a terminal in this extracted directory.
3. Run:
   ```bash
   node serve.js
   ```
4. Open your web browser and navigate to:
   ```
   http://127.0.0.1:4173/
   ```

#### Option B: Direct Browser Inspection
- Simply double-click `index.html` in your file explorer to open it directly in Google Chrome, Microsoft Edge, Mozilla Firefox, or Apple Safari. All styles, fonts, and scripts will load immediately.

---

### 2. V10 Corrective Implementation Summary

Following a strict independent architectural and quality audit of the V7 package, all verified defects have been addressed at root cause:

1. **Elimination of Overflow Suppression at Root Cause:**
   - In V7, `overflow-x: hidden` was applied on `body`, `.admin-shell`, `.admin-main-area`, and responsive media queries to suppress container blowouts.
   - In V8, all `overflow-x: hidden` suppression rules have been removed from `styles.css`. Layouts were fixed at source via proportional grid columns, flexible min-widths, and clamped element widths.
   - Verified across all 22 public pages and 19 admin modules at viewports from 320px to 1920px: **0px horizontal blowout without overflow suppression**.

2. **Synchronized Screenshot Telemetry (0 Discrepancies):**
   - Recaptured all 50 prototype screenshots using a 2-phase viewport reflow CDP engine.
   - True physical image dimensions extracted directly from binary PNG IHDR chunks (`readUInt32BE(16/20)`).
   - `tests/screenshot_capture_manifest.json` and `tests/screenshot_metrics.json` are generated simultaneously from the exact same image buffers, eliminating all 27 manifest height disagreements.
   - Legitimate duplicate documented: `05_admin_dashboard.png` is the canonical overview alias of `admin_modules/admin_adm-01_preview.png`.

3. **Honest Traceability Resolution (`PAGE-PUB-10` vs `FR-PUB-004`):**
   - In accepted Phase 1 `15_REQUIREMENTS_TRACEABILITY_MATRIX.md` line 44, `FR-PUB-004` is explicitly mapped to `PAGE-PUB-01` (Homepage).
   - `PAGE-PUB-10` (/partners-clients) is detailed in `02_PUBLIC_PAGE_BY_PAGE_SPECIFICATIONS.md` but was omitted from the RTM table ledger.
   - In accordance with mandatory governance stop conditions, Phase 0–2 accepted documents were preserved untouched. `tests/reconcile_baseline.py` now honestly reports this mapping as **`BLOCKED (Requires business-owner decision)`** rather than forcing a synthetic PASS.

4. **Business Claims Neutralization & Standardized Demonstration Data:**
   - All operational claims, SLA targets, and competitor statements neutralized or qualified with visible `[Proposed capability — pending formal business confirmation]` tags.
   - All simulated telephone numbers replaced with official ACMA-reserved demonstration numbers (`+61 491 570 006..009`, `+61 2 7010 1111..1112`).
   - All demonstration emails strictly use RFC-reserved `.invalid` top-level domains.

---

### 3. Prototype Features & Key Inspection Views

Use the top preview navigation toolbar inside the prototype to switch between master views:

1. **Desktop Homepage (`#hero`):**
   - Lavender hero canvas (`#F2F0F8`) with clinician portrait framed in pastel shapes.
   - White rounded main page shell (`border-radius: 36px`).
   - Editorial serif headlines (`Fraunces`, `#252B5E`).
   - Three colorful feature cards (Pink, Yellow, Mint) with Fraunces headlines, white pill action buttons, and aligned photography cards.
   - 8-tile asymmetric services grid (4 × 2 arrangement at desktop `> 1280px`).
   - 4-column balanced horizontal trust strip at 1440px desktop: clinician portrait on left, trust heading/copy/CTA, three neutral metric placeholders, healthcare photo card on right.
   - All unverified business claims, partner names, and regulatory assertions sanitized or badged as `[Proposed capability — pending formal business confirmation]`.
   - Contact email parameterized as `demo@example.invalid` pending formal confirmation.

2. **Mobile Homepage (`#mobile`):**
   - Single-column responsive composition verified across 320px, 360px, 390px, and 428px.
   - Full content parity: preserves all master sections (Hero with collage, 3 Feature cards with photos, all 8 Service tiles, Trust strip with circular portrait and metrics, Industries, Workflow, Partner CTA, and Footer).
   - Exact 0px horizontal overflow across all mobile viewports without CSS overflow suppression.

3. **All 22 Public Pages (`#pages`):**
   - Reconciled canonical page catalog (`PAGE-PUB-01` to `PAGE-PUB-22`).
   - Full master parity in `renderFullPageScreen('PUB-01')`: renders master hero collage, 3 feature cards, 8 service tiles, and 4-column trust strip.
   - Preserves all 22 public pages including `PAGE-PUB-10 — Partners & Clients`, `/request-proposal`, and `/faqs`.
   - Click **"Inspect Screen →"** on any page card to launch the **Full-Page Screen Inspector**, rendering a complete, full-scale high-fidelity mockup with page-specific hero, body sections, cards, and footer.

4. **All 19 Admin Modules (`#admin`):**
   - Deep slate-navy sidebar (`#0F172A`) containing all 19 canonical modules (`ADM-01` to `ADM-19`).
   - **Least-Privilege RBAC Simulator:** Switch between the 5 canonical roles (`Super Admin`, `Website Admin`, `Content Editor`, `BD Manager`, `HR Manager`). Unauthorized module access triggers a **SIMULATED 403 ACCESS DENIED** card.
   - All records watermarked `[DEMO DATA ONLY — SIMULATED RECORDS]` with simulated identities, ACMA demo telephone numbers, and `*.demo@example.invalid` emails.
   - Admin UI labeled as a **Proposed Design Concept (Not Approved)** awaiting explicit client authorization.

5. **Component & Design Token System (`#components`):**
   - Design tokens, color swatches with contrast ratios, typography scale, buttons, badges, chips, and input fields.

6. **Approved References Comparison (`#refs`):**
   - Explains height difference between 1295px reference upper-fold and 4133px master page.
   - 4 forensic close-up inspections: Hero Composition, 3 Feature Cards, 8 Service Tiles Bento, and 4-Column Trust Strip.
   - Telemetry & Cryptographic Verification Matrix proving exact dimensions, CSS viewports, and SHA-256 hashes.
   - Approved Image 2 benchmark slot confirms **PASS** against authentic verified file (1214 &times; 1295 PNG, SHA-256 `b0483fffc8e2719771c9aa3a587710b67b389b940c21b56d247ace4a2bc971ba`).

7. **Interactive Proposal Wizard Modal (FORM-PUB-03):**
   - Click "Request Proposal" from any header or card to launch the 4-step RFP wizard.

---

### 4. Package File Structure & Screenshot Inventory

The archive contains exactly the following files:

```
VOAK_PHASE_3_DESIGN_REVIEW_v8/
├── index.html                                        # Master navigable visual prototype shell
├── styles.css                                        # Master design tokens & stylesheet (no overflow suppression)
├── app.js                                            # Prototype logic (22 public mockups, 19 admin modules, role simulator)
├── serve.js                                          # Local HTTP preview server (port 4173)
├── README_PREVIEW.md
├── package_manifest.json                             # Canonical archive-to-repo path reconciliation manifest                                 # This preview guide and file manifest
├── assets/
│   └── images/
│       ├── hero_doctor.jpg                           # High-res healthcare professional portrait
│       ├── operations_manager.jpg                    # Clinical operations coordinator portrait
│       ├── hospital_building.jpg                     # Healthcare facility architecture card asset
│       ├── male_clinician.jpg                        # Clinician in scrubs portrait (Card 1)
│       ├── partnership_consultation.jpg              # Healthcare partnership meeting (Card 3)
│       └── patient_care_trust.jpg                    # Dedicated patient care photo card (Trust Section)
├── references/
│   └── approved_homepage_reference_image_2.png       # Authentic Approved Benchmark (1214 × 1295 PNG, SHA-256 b0483fff...)
├── screenshots/
│   ├── 01_desktop_homepage.png                       # Clean Desktop Homepage (1440 × 4133 px)
│   ├── 02_mobile_homepage_390px.png                  # Clean Mobile Homepage (390 × 6474 px)
│   ├── 03_tablet_homepage_768px.png                  # Clean Tablet Homepage (768 × 7383 px)
│   ├── 04_public_pages_gallery.png                   # Clean Public Pages Gallery (1440 × 3566 px)
│   ├── 05_admin_dashboard.png                        # Active ADM-01 Admin Dashboard (1440 × 1273 px)
│   ├── 06_component_system.png                       # Component Design Token System (1440 × 2152 px)
│   ├── 07_approved_reference_comparison.png          # Reference Comparison View (1440 × 3849 px)
│   ├── 08_proposal_wizard_modal.png                  # Clean RFP Wizard Modal (1440 × 960 px)
│   ├── 09_simulated_403_access_denied.png            # Clean Simulated 403 State (1440 × 1273 px)
│   ├── admin_modules/                                # 19 Active Admin Module Workspace Captures:
│   │   ├── admin_adm-01_preview.png                  # ADM-01 Executive Dashboard (1440 × 1273 px)
│   │   ├── admin_adm-02_preview.png                  # ADM-02 Authentication & MFA (1440 × 1273 px)
│   │   ├── admin_adm-03_preview.png                  # ADM-03 Users & RBAC Matrix (1440 × 1273 px)
│   │   ├── admin_adm-04_preview.png                  # ADM-04 Static Pages CMS (1440 × 1273 px)
│   │   ├── admin_adm-05_preview.png                  # ADM-05 Homepage Layout Config (1440 × 1273 px)
│   │   ├── admin_adm-06_preview.png                  # ADM-06 Services Management CMS (1440 × 1273 px)
│   │   ├── admin_adm-07_preview.png                  # ADM-07 Industries Verticals (1440 × 1273 px)
│   │   ├── admin_adm-08_preview.png                  # ADM-08 Media Asset Library (1440 × 1273 px)
│   │   ├── admin_adm-09_preview.png                  # ADM-09 Contact Inquiries CRM (1440 × 1273 px)
│   │   ├── admin_adm-10_preview.png                  # ADM-10 Strategic Partnerships (1440 × 1273 px)
│   │   ├── admin_adm-11_preview.png                  # ADM-11 Proposal / RFP Desk (1440 × 1273 px)
│   │   ├── admin_adm-12_preview.png                  # ADM-12 Email Templates Outbox (1440 × 1273 px)
│   │   ├── admin_adm-13_preview.png                  # ADM-13 Careers Job Postings (1440 × 1273 px)
│   │   ├── admin_adm-14_preview.png                  # ADM-14 Candidate Resumes Vault (1440 × 1273 px)
│   │   ├── admin_adm-15_preview.png                  # ADM-15 Case Studies CMS (1440 × 1273 px)
│   │   ├── admin_adm-16_preview.png                  # ADM-16 Insights & Articles CMS (1440 × 1273 px)
│   │   ├── admin_adm-17_preview.png                  # ADM-17 SEO Console & Redirects (1440 × 1273 px)
│   │   ├── admin_adm-18_preview.png                  # ADM-18 System Settings & Entity (1440 × 1273 px)
│   │   └── admin_adm-19_preview.png                  # ADM-19 Audit Logs & Telemetry (1440 × 1273 px)
│   └── public_pages/                                 # 22 Standalone Public Page Captures:
│       ├── page_pub-01_preview.png                   # PUB-01 Home (1440 × 4504 px)
│       ├── page_pub-02_preview.png                   # PUB-02 About Us (1440 × 2514 px)
│       ├── page_pub-03_preview.png                   # PUB-03 Services Catalog (1440 × 2013 px)
│       ├── page_pub-04_preview.png                   # PUB-04 Service Detail (1440 × 1830 px)
│       ├── page_pub-05_preview.png                   # PUB-05 Industries (1440 × 2165 px)
│       ├── page_pub-06_preview.png                   # PUB-06 Industry Detail (1440 × 1801 px)
│       ├── page_pub-07_preview.png                   # PUB-07 Why Choose Voak (1440 × 2069 px)
│       ├── page_pub-08_preview.png                   # PUB-08 How We Work (1440 × 2259 px)
│       ├── page_pub-09_preview.png                   # PUB-09 Quality & Compliance (1440 × 1907 px)
│       ├── page_pub-10_preview.png                   # PUB-10 Partners & Clients (1440 × 1991 px)
│       ├── page_pub-11_preview.png                   # PUB-11 Case Studies (1440 × 2075 px)
│       ├── page_pub-12_preview.png                   # PUB-12 Careers Hub (1440 × 2208 px)
│       ├── page_pub-13_preview.png                   # PUB-13 Job Detail & App (1440 × 1990 px)
│       ├── page_pub-14_preview.png                   # PUB-14 Insights & Articles (1440 × 2030 px)
│       ├── page_pub-15_preview.png                   # PUB-15 Article Detail (1440 × 2236 px)
│       ├── page_pub-16_preview.png                   # PUB-16 FAQs (1440 × 2168 px)
│       ├── page_pub-17_preview.png                   # PUB-17 Contact Us (1440 × 2095 px)
│       ├── page_pub-18_preview.png                   # PUB-18 Partner With Us (1440 × 2035 px)
│       ├── page_pub-19_preview.png                   # PUB-19 Request a Proposal (1440 × 1687 px)
│       ├── page_pub-20_preview.png                   # PUB-20 Privacy Policy (1440 × 2038 px)
│       ├── page_pub-21_preview.png                   # PUB-21 Terms & Conditions (1440 × 1718 px)
│       └── page_pub-22_preview.png                   # PUB-22 Error States 404/500 (1440 × 1616 px)
├── tests/
│   ├── test_expanded_responsive.js                   # Comprehensive 264-verification responsive test suite
│   ├── test_responsive_overflow.js                   # Portable automated CDP responsive overflow test
│   ├── reconcile_baseline.py                         # Strict ID set and 1-to-1 traceability verifier
│   ├── measure_and_verify_screenshots.py             # Dual-layout screenshot verifier & telemetry generator
│   ├── audit_claims_comprehensive.py                 # Source claims & SLA assertion auditor
│   ├── screenshot_metrics.json                       # Measured physical IHDR telemetry for all 50 screenshots
│   ├── screenshot_capture_manifest.json              # Browser CSS viewport dimensions & device scale factors
│   └── results/
│       ├── baseline_reconciliation.json              # Full baseline reconciliation result
│       ├── phase-03-responsive-results.json          # Full structured 264-test results (100% pass)
│       ├── phase-03-test-output.txt                  # Complete execution console log
│       └── claims_audit_report.json                  # Claims audit report (0 violations)
└── docs/
    ├── 20_FINAL_VISUAL_EVIDENCE_VERIFICATION.md      # Full verification report with telemetry
    ├── GATE_1_REFERENCE_IMAGE_AUDIT_REPORT.md        # Cryptographic verification of authentic Image 2
    ├── GATE_2_BASELINE_RECONCILIATION_REPORT.md      # Accepted Phase 0–2 invariants reconciliation
    └── GATE_4_CLAIM_AUDIT_REPORT.md                  # Neutralized business claims audit
```

---

### 5. Running Independent Verification Scripts

From the clean extracted package root:

```bash
# 1. Baseline reconciliation (verifies exact 32 phases, 62 FRs, 62 tests, 22 pages, 19 modules, 5 roles, 12 forms)
python tests/reconcile_baseline.py

# 2. Screenshot telemetry & reference integrity verifier (50 screenshots + authentic Image 2)
python tests/measure_and_verify_screenshots.py

# 3. Comprehensive business claims audit (4,631 source lines inspected)
python tests/audit_claims_comprehensive.py

# 4. Expanded responsive layout sweep (264 checks across 11 breakpoints)
node tests/test_expanded_responsive.js
```

---

### 6. Governance & Roadmap Boundary Status

- **Phase 0:** ACCEPTED
- **Phase 1:** ACCEPTED
- **Phase 2:** ACCEPTED
- **Phase 3:** NOT ACCEPTED (Corrected V8 package submitted for independent review; Gate 5 BLOCKED on business-owner traceability sign-off)
- **Phase 4:** STRICTLY NOT AUTHORIZED (Halted before start; zero PHP backend code or database migrations)
- **Total Roadmap Scope:** Exactly 32 phases (Phase 0 through Phase 31) strictly preserved.
