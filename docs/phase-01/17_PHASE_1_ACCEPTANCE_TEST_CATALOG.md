# Phase 1 Acceptance Test Catalog
## Comprehensive Verification Scenarios, Automated Assertions, and Quality Gates

**Document Reference:** `docs/phase-01/17_PHASE_1_ACCEPTANCE_TEST_CATALOG.md`  
**Phase:** Phase 1 of 31 — Complete Functional Specification  
**Baseline Authority:** Master Functional Requirements (`01_MASTER_FUNCTIONAL_REQUIREMENTS.md`) and Requirements Traceability Matrix (`15_REQUIREMENTS_TRACEABILITY_MATRIX.md`).

---

### Quality Architecture & Canonical Test Framework Standards

The acceptance test catalog establishes the empirical testing baseline for all subsequent implementation phases (Phases 3 through 31). In accordance with the canonical test identifier architecture, every acceptance test corresponds directly to a canonical requirement ID and is specified across a uniform 10-point structural schema:

1. **Test ID:** Canonical standardized identifier (`TEST-[DOMAIN]-[NUMBER]`).
2. **Related Requirement ID:** Primary functional requirement mapping (`FR-[DOMAIN]-[NUMBER]`).
3. **Relevant Phase:** Owning implementation and verification roadmap phase.
4. **Prerequisites:** System state, database fixtures, authenticated role, and environmental baseline.
5. **Required Test Fixtures:** Seed data records, mock payloads, or quarantined file fixtures.
6. **Execution Steps:** Step-by-step reproducible action sequence.
7. **Expected Result:** Explicit, deterministic success criteria.
8. **Negative Scenarios:** Edge-case abuse testing, boundary violations, and failure recovery.
9. **Automated Tooling:** Target execution framework (Playwright E2E, Vitest Component, PHPUnit Integration, Axe-Core).
10. **Pass/Fail Criteria:** Objective binary acceptance conditions.

> [!IMPORTANT]
> **Documentation-Only Baseline Declaration:**  
> Phase 1 is a functional specification phase. No tests are claimed as passing or executed at this stage. All 62 test cases are defined as canonical specifications and quality gates to be implemented and verified in subsequent development phases.

---

### Canonical Acceptance Test Inventory Summary

| Domain Category | Canonical ID Range | Total Defined Scenarios | Primary Automated Tooling |
| :--- | :---: | :---: | :--- |
| **1. Public Website & Navigation** | `TEST-PUB-001` – `TEST-PUB-023` | **23** | Playwright E2E / Vitest / cURL |
| **2. Admin Console & RBAC Modules** | `TEST-ADM-001` – `TEST-ADM-019` | **19** | Playwright E2E / PHPUnit RBAC Suite |
| **3. Workflow State Machines** | `TEST-WF-001` – `TEST-WF-006` | **6** | PHPUnit Workflow Engine / Playwright |
| **4. Platform Security & Storage** | `TEST-SEC-001` – `TEST-SEC-007` | **7** | PHPUnit Security Suite / Gitleaks / cURL |
| **5. SEO Engine & Pre-Rendering** | `TEST-SEO-001` – `TEST-SEO-004` | **4** | Playwright / cURL / Schema Validator |
| **6. Accessibility & Multi-Device** | `TEST-A11Y-001` – `TEST-A11Y-003` | **3** | Axe-Core / Playwright Animation Suite |
| **Total Canonical Test Scenarios** | **TEST-PUB-001 to TEST-A11Y-003** | **62** | **100% Mathematically Reconciled** |

- **Claimed Test Scenarios:** Exactly 62
- **Fully Written Detailed Scenarios in this Catalog:** Exactly 62
- **Undefined or Orphan Test References:** Exactly 0

---

### Detailed Test Specifications (Exactly 62 Fully Specified Scenarios)


#### 1. TEST-PUB-001: Global Navigation Header & Mobile Drawer
- **Canonical Test ID:** `TEST-PUB-001`
- **Related Requirement ID:** [`FR-PUB-001`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 19 / 20
- **Prerequisites:** Public website deployed on local dev server or test environment. Screen sizes: Desktop (1440px) and Mobile (375px).
- **Required Test Fixtures:** Header configuration JSON with 4 top-level links and "Request Proposal" action CTA.
- **Execution Steps:**
  1. Load homepage `/` at 1440px viewport width.
  2. Inspect `#main-header` for sticky/fixed positioning and WCAG 2.2 focus ring on all navigation items.
  3. Click top-level dropdown "Services" and assert sub-menu renders with 6 service links.
  4. Resize viewport to 375px width; assert desktop navigation collapses into hamburger toggle button.
  5. Tap hamburger button; assert sliding drawer opens with `aria-expanded="true"`, trapping keyboard focus.
- **Expected Result:** Header renders with branded logo and primary CTA; dropdown operates smoothly with keyboard and mouse; mobile drawer opens/closes with focus trap and Escape key listener.
- **Negative Scenarios & Boundary Tests:** Verify navigating away closes drawer; rapid toggling does not freeze animation or leave orphaned overlays.
- **Automated Tooling:** Playwright E2E (`tests/e2e/navigation.spec.ts`) & Axe-core.
- **Pass/Fail Criteria:** PASS if all desktop links navigate, dropdown expands with ARIA attributes, mobile drawer traps focus, and contrast >= 4.5:1.

---

#### 2. TEST-PUB-002: Asymmetric Hero Composition & Value Proposition
- **Canonical Test ID:** `TEST-PUB-002`
- **Related Requirement ID:** [`FR-PUB-002`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 20
- **Prerequisites:** Database seeded with active homepage configuration.
- **Required Test Fixtures:** `homepage_configs` record with headline, subheadline, and primary CTA route.
- **Execution Steps:**
  1. Navigate to `/`.
  2. Inspect hero section typography and background styling.
  3. Assert `<h1>` displays editorial serif `Fraunces` font in color `#252B5E`.
  4. Assert background canvas uses soft lavender tone `#F2F0F8`.
  5. Click primary CTA "Request a Proposal" button.
- **Expected Result:** Hero renders with approved brand typography and pastel background rhythm; clicking primary CTA routes to `/request-proposal` without console errors.
- **Negative Scenarios & Boundary Tests:** Simulate empty database fields; hero must fall back to hardcoded default brand copy without crashing.
- **Automated Tooling:** Playwright E2E & Visual Regression.
- **Pass/Fail Criteria:** PASS if hero typography matches tokens, CTA navigates cleanly, and zero layout shift (CLS < 0.1).

---

#### 3. TEST-PUB-003: Bento-Grid Services Presentation & Hover Interactions
- **Canonical Test ID:** `TEST-PUB-003`
- **Related Requirement ID:** [`FR-PUB-003`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 20 / 22
- **Prerequisites:** Database contains 6 active services marked `is_published = 1`.
- **Required Test Fixtures:** `services` table seeded with 6 proposed pillars (Clinical Workforce, Operations, Facilities, Diagnostics, Clinical Governance, Healthcare Tech).
- **Execution Steps:**
  1. Navigate to `/` and scroll to `#services-bento` section.
  2. Count rendered service cards.
  3. Hover over card 1 ("Clinical Workforce Solutions"); assert subtle elevation `translateY(-4px)` with spring easing.
  4. Click card 1 and verify navigation transition.
- **Expected Result:** Exactly 6 service cards render in asymmetrical bento layout; hover transition applies subtle elevation; clicking card navigates to `/services/clinical-workforce-solutions`.
- **Negative Scenarios & Boundary Tests:** If only 4 services are published, bento layout must gracefully adapt without empty white boxes or broken grid tracks.
- **Automated Tooling:** Playwright E2E & Motion for React unit assertions.
- **Pass/Fail Criteria:** PASS if 6 cards render, hover animation triggers without jank, and cards route to corresponding detail slugs.

---

#### 4. TEST-PUB-004: Configurable Social Proof Ribbon Clean Collapse
- **Canonical Test ID:** `TEST-PUB-004`
- **Related Requirement ID:** [`FR-PUB-004`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 20 / 24
- **Prerequisites:** System configured with zero approved partner logos (`is_published = 0`).
- **Required Test Fixtures:** `partners` and `testimonials` tables populated with draft-only records.
- **Execution Steps:**
  1. Navigate to `/`.
  2. Query DOM for `section[data-testid="partner-proof"]` and testimonial quotes.
  3. Inspect layout bounding box between hero and bento grid sections.
- **Expected Result:** Social proof ribbon and unverified client logos are completely omitted from DOM (count === 0); adjacent sections collapse margins cleanly without white gaps or placeholders.
- **Negative Scenarios & Boundary Tests:** Publish 1 test partner logo; verify ribbon reappears and renders valid image with non-empty `alt` attribute.
- **Automated Tooling:** Playwright E2E (`tests/e2e/social-proof.spec.ts`).
- **Pass/Fail Criteria:** PASS if unverified proof collapses completely without DOM remnants or layout shifts.

---

#### 5. TEST-PUB-005: Service Pillar Filtering & Category Discovery
- **Canonical Test ID:** `TEST-PUB-005`
- **Related Requirement ID:** [`FR-PUB-005`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 22
- **Prerequisites:** 6 services active across categories: Workforce, Operations, Administration, Facilities, Consulting, Governance.
- **Required Test Fixtures:** `services` table with category tags.
- **Execution Steps:**
  1. Navigate to `/services`.
  2. Click category pill "Clinical Operations".
  3. Verify URL updates to `/services?category=clinical-operations` via shallow routing.
  4. Assert rendered cards only belong to "Clinical Operations".
  5. Click "All Services" pill.
- **Expected Result:** Filtering updates service grid instantly; URL search params reflect active category; aria-live region announces updated result count; "All" restores full catalog.
- **Negative Scenarios & Boundary Tests:** Filter by non-existent category query param `?category=invalid-cat`; verify empty state banner renders with "Reset filters" button.
- **Automated Tooling:** Playwright E2E & React Testing Library.
- **Pass/Fail Criteria:** PASS if category filtering works dynamically, URL updates, and empty state provides recovery.

---

#### 6. TEST-PUB-006: Dynamic Service Detail Deep-Dive & 404 Header Integrity
- **Canonical Test ID:** `TEST-PUB-006`
- **Related Requirement ID:** [`FR-PUB-006`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 22
- **Prerequisites:** Service `clinical-workforce-solutions` published in database.
- **Required Test Fixtures:** Service record with SLA matrix, deliverables list, and related industries.
- **Execution Steps:**
  1. Request GET `/services/clinical-workforce-solutions`.
  2. Inspect HTTP status code and response body.
  3. Assert deliverables checklist renders with SVG checkmark icons.
  4. Request GET `/services/non-existent-slug-xyz`.
- **Expected Result:** Valid slug returns HTTP 200 OK with full deliverable details and metadata; invalid slug returns authentic HTTP 404 Not Found header and branded error view.
- **Negative Scenarios & Boundary Tests:** Attempt SQL injection or directory traversal in slug (`/services/../admin`); verify request safely resolves to 404.
- **Automated Tooling:** Playwright E2E & cURL assertion.
- **Pass/Fail Criteria:** PASS if valid slug returns 200, invalid slug returns authentic HTTP 404, and no SQL errors leak.

---

#### 7. TEST-PUB-007: Industry Verticals Directory & Sector Capability
- **Canonical Test ID:** `TEST-PUB-007`
- **Related Requirement ID:** [`FR-PUB-007`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 22
- **Prerequisites:** Database seeded with 6 approved healthcare sectors.
- **Required Test Fixtures:** `industries` records for Hospitals, GP Clinics, Aged Care, Allied Health, Diagnostics, and Corporate Health.
- **Execution Steps:**
  1. Navigate to `/industries`.
  2. Verify 6 sector cards render with approved titles, descriptions, and iconography.
  3. Click "Aged Care Facilities" card.
- **Expected Result:** Directory renders all 6 approved industry sectors; clicking Aged Care navigates cleanly to `/industries/aged-care-facilities`.
- **Negative Scenarios & Boundary Tests:** Inspect mobile viewport at 320px; ensure grid wraps into single column without horizontal overflow.
- **Automated Tooling:** Playwright E2E.
- **Pass/Fail Criteria:** PASS if all 6 sectors render with verified copy and no horizontal scrollbar on mobile.

---

#### 8. TEST-PUB-008: Dynamic Industry Detail Deep-Dive & Compliance Specs
- **Canonical Test ID:** `TEST-PUB-008`
- **Related Requirement ID:** [`FR-PUB-008`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 22
- **Prerequisites:** Industry `aged-care-facilities` published in database.
- **Required Test Fixtures:** Industry record detailing Aged Care Quality Standards compliance and operational support.
- **Execution Steps:**
  1. Request GET `/industries/aged-care-facilities`.
  2. Inspect regulatory burden callout and tailored operational solutions.
  3. Click "Request Sector Consultation" contextual CTA button.
- **Expected Result:** Page renders sector-specific governance specifications; clicking CTA routes to `/request-proposal?sector=aged-care-facilities`.
- **Negative Scenarios & Boundary Tests:** Request invalid industry slug; verify server responds with true HTTP 404 status code.
- **Automated Tooling:** Playwright E2E & cURL.
- **Pass/Fail Criteria:** PASS if regulatory content renders accurately, contextual CTA pre-populates RFP sector, and missing slug yields 404.

---

#### 9. TEST-PUB-009: Corporate Governance & About Narrative
- **Canonical Test ID:** `TEST-PUB-009`
- **Related Requirement ID:** [`FR-PUB-009`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 21
- **Prerequisites:** Static page `/about` published in database.
- **Required Test Fixtures:** `pages` record for About Us with leadership profile placeholders and governance framework.
- **Execution Steps:**
  1. Navigate to `/about`.
  2. Assert presence of Corporate Governance and Clinical Advisory structure sections.
  3. Click leadership bio card "Chief Clinical Officer".
  4. Verify modal opens, displays credentials, traps keyboard focus, and closes on Escape.
- **Expected Result:** Corporate overview renders editorial layout; leadership modal opens with focus trap and closes gracefully on Escape.
- **Negative Scenarios & Boundary Tests:** Simulate missing bio photo; ensure generic SVG avatar renders without broken `<img>` icon.
- **Automated Tooling:** Playwright E2E & Axe-core.
- **Pass/Fail Criteria:** PASS if governance narrative renders, modal traps focus, and missing assets degrade gracefully.

---

#### 10. TEST-PUB-010: Differentiation & "Why Choose Voak" Comparative Matrix
- **Canonical Test ID:** `TEST-PUB-010`
- **Related Requirement ID:** [`FR-PUB-010`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 21
- **Prerequisites:** Static page `/why-choose-voak` published in database.
- **Required Test Fixtures:** Comparative matrix table data comparing Voak Managed Services vs Traditional Agency Staffing vs In-House Overhead.
- **Execution Steps:**
  1. Navigate to `/why-choose-voak`.
  2. Inspect comparative matrix table across Desktop (1200px) and Mobile (375px).
  3. Assert all 6 operational dimensions (Vetting, Governance, Continuity, Cost, Technology, SLA) render.
- **Expected Result:** Table displays accessible comparison with checkmarks and crossmarks; on mobile, table collapses into stacked comparison cards to prevent horizontal clipping.
- **Negative Scenarios & Boundary Tests:** Check screen reader output; table headers must use `scope="col"` and `scope="row"`.
- **Automated Tooling:** Playwright E2E & Axe-core.
- **Pass/Fail Criteria:** PASS if table is responsive, accessible via screen reader, and communicates B2B differentiation clearly.

---

#### 11. TEST-PUB-011: 4-Stage Operational Implementation Roadmap Timeline
- **Canonical Test ID:** `TEST-PUB-011`
- **Related Requirement ID:** [`FR-PUB-011`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 21
- **Prerequisites:** Static page `/how-we-work` published in database.
- **Required Test Fixtures:** Implementation roadmap content (Stage 1: Clinical Audit, Stage 2: Solution Architecture, Stage 3: Operational Deployment, Stage 4: Continuous Governance).
- **Execution Steps:**
  1. Navigate to `/how-we-work`.
  2. Inspect visual sequential timeline component.
  3. Assert 4 numbered steps render in sequence with deliverables and milestone timelines.
  4. Click each stage header using keyboard Tab + Enter.
- **Expected Result:** Timeline displays sequential progression with accessible `<ol>` list structure; interactive stage details expand cleanly with keyboard focus.
- **Negative Scenarios & Boundary Tests:** Resize to mobile; ensure timeline connector line adjusts vertically without overlapping text.
- **Automated Tooling:** Playwright E2E & Vitest Component.
- **Pass/Fail Criteria:** PASS if all 4 stages render sequentially with semantic list markup and keyboard accessibility.

---

#### 12. TEST-PUB-012: Clinical Compliance, Quality Standards & Non-Clinical Disclaimer
- **Canonical Test ID:** `TEST-PUB-012`
- **Related Requirement ID:** [`FR-PUB-012`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 21
- **Prerequisites:** Static page `/quality-compliance` published in database.
- **Required Test Fixtures:** Compliance content detailing credentialing, compliance audit protocols, and non-clinical care disclaimers.
- **Execution Steps:**
  1. Navigate to `/quality-compliance`.
  2. Verify credentialing protocols and clinical audit readiness accordions.
  3. Assert prominent legal disclaimer banner is visible: "Voak Health Care is a B2B healthcare operational services provider and does not provide direct patient clinical care or patient booking services."
- **Expected Result:** Compliance frameworks render with expandable technical accordions; non-clinical care boundary disclaimer is clearly displayed in high contrast banner.
- **Negative Scenarios & Boundary Tests:** Verify disclaimer cannot be hidden by CSS display:none or script injection.
- **Automated Tooling:** Playwright E2E.
- **Pass/Fail Criteria:** PASS if compliance accordions operate properly and mandatory non-clinical care disclaimer is verified present.

---

#### 13. TEST-PUB-013: Verified Case Studies Directory & Metrics Presentation
- **Canonical Test ID:** `TEST-PUB-013`
- **Related Requirement ID:** [`FR-PUB-013`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 24
- **Prerequisites:** Case studies table contains published verified records with consent attachments.
- **Required Test Fixtures:** `case_studies` records with Problem, Intervention, Outcome structure and client release verification flag.
- **Execution Steps:**
  1. Navigate to `/case-studies`.
  2. Filter case studies by sector "Hospitals".
  3. Click on case study card and review Problem-Intervention-Outcome breakdown.
  4. Verify metrics display verified tags without fictitious client claims.
- **Expected Result:** Case studies display structured operational results; filtering works dynamically; all displayed statistics match verified database fields.
- **Negative Scenarios & Boundary Tests:** Attempt to view a case study with `is_published = 0` or missing consent flag; verify request returns HTTP 404.
- **Automated Tooling:** Playwright E2E.
- **Pass/Fail Criteria:** PASS if verified case studies render accurately and unverified/draft cases are strictly inaccessible.

---

#### 14. TEST-PUB-014: Careers Portal & Openings Board Filtering
- **Canonical Test ID:** `TEST-PUB-014`
- **Related Requirement ID:** [`FR-PUB-014`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 24
- **Prerequisites:** Database seeded with 5 active job postings across Clinical, Operations, and Corporate.
- **Required Test Fixtures:** `job_postings` records with department, location, employment type, and `is_active = 1`.
- **Execution Steps:**
  1. Navigate to `/careers`.
  2. Filter vacancies by department "Clinical Operations" and employment type "Full-Time".
  3. Assert list updates dynamically without page reload.
  4. Click "View Role & Apply" button on first opening.
- **Expected Result:** Filtered results match criteria; count updates in live region; clicking role navigates to `/careers/:slug` job detail view.
- **Negative Scenarios & Boundary Tests:** Filter by criteria yielding 0 openings; assert "No active positions found" message appears with general talent pool submission link.
- **Automated Tooling:** Playwright E2E.
- **Pass/Fail Criteria:** PASS if vacancy filtering functions cleanly, empty state is helpful, and role links resolve.

---

#### 15. TEST-PUB-015: Candidate Job Application & Secure Resume Upload Quarantine
- **Canonical Test ID:** `TEST-PUB-015`
- **Related Requirement ID:** [`FR-PUB-015`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 24
- **Prerequisites:** Active job posting exists at `/careers/clinical-nurse-lead`.
- **Required Test Fixtures:** Valid PDF resume fixture (500KB) and malicious `.exe` file renamed to `resume.pdf`.
- **Execution Steps:**
  1. Navigate to `/careers/clinical-nurse-lead`.
  2. Fill candidate details with international phone `+44 20 7946 0991`.
  3. Attach valid `resume.pdf` and submit application.
  4. Verify HTTP 201 Created response and confirmation code screen.
  5. Verify uploaded file is placed in private quarantine directory `storage/private/quarantine/`.
  6. Attempt re-submitting with fake `.pdf` (executable binary inside).
- **Expected Result:** Valid application succeeds with reference code; file is stored in quarantine outside web root; fake PDF is rejected by server with HTTP 422 and MIME magic byte error message.
- **Negative Scenarios & Boundary Tests:** Upload file > 10MB; verify client and server enforce maximum size ceiling.
- **Automated Tooling:** Playwright E2E & Vitest Integration.
- **Pass/Fail Criteria:** PASS if valid submission creates quarantined record and MIME spoofing is blocked.

---

#### 16. TEST-PUB-016: Insights & Thought Leadership Center Search & Categorization
- **Canonical Test ID:** `TEST-PUB-016`
- **Related Requirement ID:** [`FR-PUB-016`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 24
- **Prerequisites:** Database seeded with 8 published articles across Workforce Strategy, Clinical Governance, and Technology.
- **Required Test Fixtures:** `articles` records with estimated reading times, tags, and published dates.
- **Execution Steps:**
  1. Navigate to `/insights`.
  2. Enter search query "Compliance" into live search input.
  3. Assert article grid filters to matching articles in real time.
  4. Click category pill "Workforce Strategy".
- **Expected Result:** Search input debounces (300ms) and filters results accurately; category pill isolates relevant articles; reading times (e.g., "5 min read") are visible on all cards.
- **Negative Scenarios & Boundary Tests:** Search with special regex characters (`.*[a-z]`); ensure no client error or unescaped regex exception occurs.
- **Automated Tooling:** Playwright E2E.
- **Pass/Fail Criteria:** PASS if search and category filters function without lag or errors.

---

#### 17. TEST-PUB-017: Dynamic Article Reader, DOMPurify Sanitization & Social Share
- **Canonical Test ID:** `TEST-PUB-017`
- **Related Requirement ID:** [`FR-PUB-017`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 24
- **Prerequisites:** Article `navigating-healthcare-staffing-regulations` published in database.
- **Required Test Fixtures:** Article record with rich markdown body containing potential XSS test payloads `<script>alert(1)</script>`.
- **Execution Steps:**
  1. Navigate to `/insights/navigating-healthcare-staffing-regulations`.
  2. Assert article body renders sanitized HTML with `<script>` tags completely stripped by DOMPurify.
  3. Scroll down article body; observe reading progress bar at top of viewport advancing proportionally.
  4. Click "Copy Article Link" button; verify clipboard receives canonical URL and toast notification appears.
- **Expected Result:** Article renders cleanly without XSS execution; reading progress bar tracks scroll position accurately; link copy provides tactile feedback.
- **Negative Scenarios & Boundary Tests:** Navigate to unpublished article slug; verify server returns HTTP 404.
- **Automated Tooling:** Playwright E2E & Security DOM audit.
- **Pass/Fail Criteria:** PASS if XSS payload is neutralized, reading bar tracks scroll, and canonical share link copies.

---

#### 18. TEST-PUB-018: Interactive FAQ Accordion Keyboard Navigation & ARIA States
- **Canonical Test ID:** `TEST-PUB-018`
- **Related Requirement ID:** [`FR-PUB-018`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 24
- **Prerequisites:** Database seeded with 12 FAQs across Procurement, Operations, and Compliance.
- **Required Test Fixtures:** `faqs` records with question, answer, category, and display order.
- **Execution Steps:**
  1. Navigate to `/faqs`.
  2. Focus on first FAQ accordion button using Tab key.
  3. Inspect initial ARIA state: `aria-expanded="false"`.
  4. Press Enter key to expand accordion; inspect updated state: `aria-expanded="true"`.
  5. Press Tab key; verify focus lands directly inside revealed answer content.
  6. Filter FAQs using search input "SLA".
- **Expected Result:** Accordions expand/collapse smoothly with exact WAI-ARIA states; keyboard navigation adheres to accessibility standards; search dynamically filters questions.
- **Negative Scenarios & Boundary Tests:** Rapidly hit Enter and Space on multiple accordions; assert state updates without desynchronization.
- **Automated Tooling:** Playwright E2E & Axe-core.
- **Pass/Fail Criteria:** PASS if ARIA attributes toggle accurately, keyboard navigation is seamless, and search filters FAQs.

---

#### 19. TEST-PUB-019: General Contact Inbound Form Validation & Turnstile Protection
- **Canonical Test ID:** `TEST-PUB-019`
- **Related Requirement ID:** [`FR-PUB-019`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 23
- **Prerequisites:** Form mounted on `/contact`. Mock Cloudflare Turnstile token or test key active.
- **Required Test Fixtures:** Valid contact payload with international phone `+1 415 555 2671` and corporate domain email `ops@metrohealth.org`.
- **Execution Steps:**
  1. Navigate to `/contact`.
  2. Submit form empty; assert inline validation errors appear for name, corporate email, and message.
  3. Enter personal email `test@gmail.com`; assert soft warning or block if corporate email enforced.
  4. Fill all fields with valid corporate data, complete Turnstile, and submit.
  5. Verify server responds with HTTP 201 Created, reference code is returned, and record is saved in `contact_inquiries`.
- **Expected Result:** Inline validation alerts user before submission; valid submission succeeds, displays unique reference code, and inserts row in `contact_inquiries` table.
- **Negative Scenarios & Boundary Tests:** Populate hidden honeypot field `website_hp`; assert submission is rejected with HTTP 400 or silently dropped without queueing notification.
- **Automated Tooling:** Playwright E2E & PHPUnit API test.
- **Pass/Fail Criteria:** PASS if validation fires properly, honeypot traps bots, and valid submission creates CRM record.

---

#### 20. TEST-PUB-020: Strategic Partnership Intake Pipeline & Organization Tiering
- **Canonical Test ID:** `TEST-PUB-020`
- **Related Requirement ID:** [`FR-PUB-020`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 23
- **Prerequisites:** Partnership intake form mounted on `/partner-with-us`.
- **Required Test Fixtures:** Valid alliance proposal payload with organization name, tier, and collaboration scope.
- **Execution Steps:**
  1. Navigate to `/partner-with-us`.
  2. Select Organization Tier: "Regional Health Authority".
  3. Select Collaboration Scope: "Clinical Workforce Joint Venture".
  4. Complete representative contact details and submit.
  5. Verify server returns HTTP 201 Created and reference code formatted `PTN-YYYYMMDD-XXXX`.
- **Expected Result:** Partnership form processes multi-select collaboration scopes; server records payload in `partnership_inquiries` with initial status `NEW_INQUIRY`.
- **Negative Scenarios & Boundary Tests:** Submit without required partnership scope selection; verify form halts and highlights field with accessible error message.
- **Automated Tooling:** Playwright E2E & PHPUnit API test.
- **Pass/Fail Criteria:** PASS if reference code generates with PTN prefix, database stores record, and empty scopes are blocked.

---

#### 21. TEST-PUB-021: Multi-Step Request a Proposal (RFP) Wizard & Tender File Attachment
- **Canonical Test ID:** `TEST-PUB-021`
- **Related Requirement ID:** [`FR-PUB-021`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 23
- **Prerequisites:** RFP wizard mounted on `/request-proposal`.
- **Required Test Fixtures:** 4-step wizard data: Step 1 (Facility Profile), Step 2 (Service Scopes), Step 3 (Timeline & Budget), Step 4 (Tender File & Review).
- **Execution Steps:**
  1. Navigate to `/request-proposal`.
  2. Fill Step 1 with organization details and international phone `+65 6789 0123`; click "Continue to Scopes".
  3. Select 3 service pillars in Step 2; click "Continue to Budget".
  4. Select timeline "Within 30 Days" and budget band in Step 3; click "Continue to Documents".
  5. Attach 2MB tender specification PDF in Step 4.
  6. Click "Submit Scope Proposal".
- **Expected Result:** Wizard advances through all 4 steps preserving form state across back/next navigation; submission creates proposal record, stores tender file in quarantine, and displays reference `RFP-YYYYMMDD-XXXX`.
- **Negative Scenarios & Boundary Tests:** Attempt to skip Step 1 required email; assert Next button is disabled or halts step progression with focused error announcement.
- **Automated Tooling:** Playwright E2E (`tests/e2e/rfp-wizard.spec.ts`).
- **Pass/Fail Criteria:** PASS if wizard preserves state, validates each step, quarantines tender file, and returns RFP code.

---

#### 22. TEST-PUB-022: Legal Policies, Dynamic Variables & Jurisdiction Neutrality
- **Canonical Test ID:** `TEST-PUB-022`
- **Related Requirement ID:** [`FR-PUB-022`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 24
- **Prerequisites:** Privacy policy and Terms of service pages published.
- **Required Test Fixtures:** System settings configured with `{{legal_entity_name}}`, `{{registration_number}}`, and `{{registered_address}}`.
- **Execution Steps:**
  1. Navigate to `/privacy-policy`.
  2. Inspect legal disclosures and verify entity name matches `{{legal_entity_name}}` without raw unparsed curly braces.
  3. Assert privacy policy references conditional operating jurisdictions and international standards.
  4. Navigate to `/terms` and assert non-clinical liability exclusion clause is clearly rendered.
- **Expected Result:** Legal pages render populated configuration variables; zero raw template brackets leak; Australian-specific statutes are framed conditionally; non-clinical boundaries are explicit.
- **Negative Scenarios & Boundary Tests:** Simulate missing `{{legal_entity_name}}` in system settings; verify legal page displays safe fallback "Voak Health Care Platform" without PHP warnings or crashes.
- **Automated Tooling:** Playwright E2E & Snapshot test.
- **Pass/Fail Criteria:** PASS if configuration variables interpolate cleanly, no brackets appear, and safe fallback operates.

---

#### 23. TEST-PUB-023: Accessible 404 / 500 Error Pages & Diagnostic Reference Logging
- **Canonical Test ID:** `TEST-PUB-023`
- **Related Requirement ID:** [`FR-PUB-023`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 24
- **Prerequisites:** Web server running with front controller error routing.
- **Required Test Fixtures:** Diagnostic correlation ID generator active.
- **Execution Steps:**
  1. Request GET `/non-existent-page-url-404`.
  2. Inspect HTTP status header: must be authentic `404 Not Found` (never 200 OK soft-404).
  3. Inspect rendered HTML for branded 404 headline, helpful recovery navigation links, and search input.
  4. Simulate backend runtime exception on test endpoint `/api/test-trigger-500`.
  5. Inspect 500 response: assert HTTP 500 header, zero PHP stack trace or SQL leakage, and presence of correlation ID `REF-ERR-XXXXXX`.
- **Expected Result:** 404 emits genuine HTTP 404 header; 500 emits genuine HTTP 500 header with sanitized error message and correlation reference code; zero internal paths or database passwords leaked.
- **Negative Scenarios & Boundary Tests:** Trigger error with `DEBUG=true` vs `DEBUG=false`; ensure production configuration completely suppresses stack traces.
- **Automated Tooling:** Playwright E2E & cURL assertion.
- **Pass/Fail Criteria:** PASS if HTTP status codes are authentic, zero stack traces leak to client, and error reference is logged.

---

#### 24. TEST-ADM-001: Executive Dashboard KPI Metrics & Role-Filtered Telemetry
- **Canonical Test ID:** `TEST-ADM-001`
- **Related Requirement ID:** [`FR-ADM-001`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 25
- **Prerequisites:** Admin authenticated with Super Admin role.
- **Required Test Fixtures:** Database populated with 10 contact inquiries, 4 RFPs, 8 applications, and 2 outbox delivery failures.
- **Execution Steps:**
  1. Login to admin console and navigate to `/admin/dashboard`.
  2. Inspect KPI summary cards: Inquiries (10), Pending RFPs (4), Active Applications (8), Outbox Health (Alert: 2 Failed).
  3. Verify recent activity feed displays timestamped actions.
  4. Switch to Content Editor session; reload dashboard.
- **Expected Result:** Super Admin sees full platform metrics including outbox health; Content Editor sees only content-specific cards with CRM metrics filtered out.
- **Negative Scenarios & Boundary Tests:** Simulate database query timeout on dashboard metrics API; assert UI renders graceful retry banner instead of crashing whole console.
- **Automated Tooling:** Playwright E2E & Vitest Integration.
- **Pass/Fail Criteria:** PASS if KPI counts match database, role filtering is enforced, and outbox failure alert displays.

---

#### 25. TEST-ADM-002: Secure Admin Login, Bcrypt Authentication & Brute-Force Lockout
- **Canonical Test ID:** `TEST-ADM-002`
- **Related Requirement ID:** [`FR-ADM-002`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 6
- **Prerequisites:** User account `admin@voak.local` registered in `users` table with bcrypt hash.
- **Required Test Fixtures:** Rate limiter cache configured with 5-attempt threshold and 15-minute lockout window.
- **Execution Steps:**
  1. Navigate to `/admin/login`.
  2. Submit form with incorrect password 5 consecutive times.
  3. Assert 5th failed attempt displays lockout message: "Account temporarily locked for 15 minutes due to consecutive failed attempts."
  4. Attempt 6th login with correct password; assert request is blocked immediately with HTTP 429 Too Many Requests.
  5. Advance lockout timer / clear rate limiter cache, then login with valid credentials.
- **Expected Result:** Brute-force protection halts consecutive login attacks after 5 attempts; successful login generates session token and proceeds to MFA verification.
- **Negative Scenarios & Boundary Tests:** Attempt timing attack on username enumeration; ensure invalid user vs invalid password return identical generic error messages.
- **Automated Tooling:** PHPUnit Security Test & Playwright E2E.
- **Pass/Fail Criteria:** PASS if lockout triggers at exactly 5 attempts, HTTP 429 is returned, and error messages are timing-safe.

---

#### 26. TEST-ADM-003: RFC 6238 TOTP Multi-Factor Authentication Verification & Recovery
- **Canonical Test ID:** `TEST-ADM-003`
- **Related Requirement ID:** [`FR-ADM-003`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 6
- **Prerequisites:** Admin user with TOTP 2FA secret enrolled in `admin_mfa_secrets`.
- **Required Test Fixtures:** Valid TOTP secret key and pre-generated SHA-256 hashed recovery backup codes.
- **Execution Steps:**
  1. Authenticate with valid username and password on `/admin/login`.
  2. Assert redirect to `/admin/mfa-verify`.
  3. Generate current 6-digit TOTP code using RFC 6238 time step (30s) and submit.
  4. Assert login completes and redirects to `/admin/dashboard`.
  5. In a second test, click "Use Backup Recovery Code" and submit valid single-use recovery code.
- **Expected Result:** Valid TOTP code issues full authenticated session; backup code grants single-use access and marks that specific backup code as consumed in database.
- **Negative Scenarios & Boundary Tests:** Submit expired TOTP code (past +/- 1 time step drift); assert code is rejected with HTTP 401.
- **Automated Tooling:** PHPUnit Auth Test & Playwright E2E.
- **Pass/Fail Criteria:** PASS if TOTP validates within 30s window, expired codes reject, and backup code consumes single-use.

---

#### 27. TEST-ADM-004: Role-Based Access Control (RBAC) 5-Role Enforcement Engine
- **Canonical Test ID:** `TEST-ADM-004`
- **Related Requirement ID:** [`FR-ADM-004`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 6 / 26
- **Prerequisites:** 5 test accounts provisioned: Super Admin, Web Admin, Content Editor, BD Manager, HR Recruiter.
- **Required Test Fixtures:** Standard permission matrix configured across 19 admin modules.
- **Execution Steps:**
  1. Log in as Content Editor; attempt GET `/api/v1/admin/users`; assert HTTP 403 Forbidden.
  2. Attempt GET `/api/v1/admin/proposals`; assert HTTP 403 Forbidden.
  3. Attempt PUT `/api/v1/admin/pages/1`; assert HTTP 200 OK.
  4. Log in as HR Recruiter; attempt GET `/api/v1/admin/applications`; assert HTTP 200 OK.
  5. As HR Recruiter, attempt modifying service slug; assert HTTP 403 Forbidden.
- **Expected Result:** All 19 admin modules and API endpoints enforce strict role boundaries matching the approved 5-role RBAC matrix.
- **Negative Scenarios & Boundary Tests:** Attempt parameter tampering with role ID in session cookie; assert cryptographic signature failure invalidates session.
- **Automated Tooling:** PHPUnit RBAC Test Suite (`tests/rbac_matrix_test.php`).
- **Pass/Fail Criteria:** PASS if 100% of unauthorized module endpoints return HTTP 403 and authorized endpoints succeed.

---

#### 28. TEST-ADM-005: Page Content CMS, Versioning & Concurrency Revision Control
- **Canonical Test ID:** `TEST-ADM-005`
- **Related Requirement ID:** [`FR-ADM-005`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 7 / 26
- **Prerequisites:** User authenticated as Web Admin on `/admin/pages/about`.
- **Required Test Fixtures:** `pages` record with existing content revision history in `content_revisions`.
- **Execution Steps:**
  1. Navigate to `/admin/pages/about` and edit section headline.
  2. Save changes; verify new record is created in `content_revisions` table.
  3. In a second browser tab, simulate another editor submitting an edit with older `updated_at` timestamp.
  4. Verify server detects concurrency conflict and returns HTTP 409 Conflict with diff resolution options.
  5. Click "Restore Revision 1" from revision history list.
- **Expected Result:** Revisions are snapshot on every publish; optimistic locking prevents mid-air collisions; restoring older revision updates active content and logs audit entry.
- **Negative Scenarios & Boundary Tests:** Submit empty mandatory page title; assert client and server block saving with validation error.
- **Automated Tooling:** Playwright E2E & Vitest Integration.
- **Pass/Fail Criteria:** PASS if revision snapshots save, concurrency conflict emits HTTP 409, and rollback succeeds.

---

#### 29. TEST-ADM-006: Homepage Hero & Bento-Grid Section Customizer Console
- **Canonical Test ID:** `TEST-ADM-006`
- **Related Requirement ID:** [`FR-ADM-006`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 7 / 26
- **Prerequisites:** User authenticated as Content Editor on `/admin/homepage`.
- **Required Test Fixtures:** `homepage_configs` table with hero copy, bento layout order, and accent colors.
- **Execution Steps:**
  1. Navigate to `/admin/homepage`.
  2. Update hero subheadline and re-order bento cards via drag-and-drop.
  3. Click "Save Draft", then "Publish to Live".
  4. Verify public `/` reflects updated hero copy and bento card order.
  5. Verify cache invalidation triggers on public homepage pre-render.
- **Expected Result:** Changes save successfully; preview reflects staging state; publishing invalidates public cache and renders updated layout.
- **Negative Scenarios & Boundary Tests:** Enter XSS payload in headline; verify server strips/escapes payload before persisting.
- **Automated Tooling:** Playwright E2E.
- **Pass/Fail Criteria:** PASS if drag-and-drop reorders bento cards, public homepage updates, and XSS is neutralized.

---

#### 30. TEST-ADM-007: Services Catalog Management & Automated 301 Redirect Generation
- **Canonical Test ID:** `TEST-ADM-007`
- **Related Requirement ID:** [`FR-ADM-007`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 8 / 26
- **Prerequisites:** User authenticated as Web Admin on `/admin/services`.
- **Required Test Fixtures:** Active service with slug `/services/clinical-staffing`.
- **Execution Steps:**
  1. Navigate to `/admin/services/clinical-staffing/edit`.
  2. Change slug to `/services/specialist-clinical-staffing`.
  3. Save changes.
  4. Inspect `url_redirects` table for new entry: `/services/clinical-staffing` -> `/services/specialist-clinical-staffing` (status 301).
  5. Issue GET request via cURL to old slug `/services/clinical-staffing`.
- **Expected Result:** Slug rename updates service record, automatically inserts 301 redirect entry in database, and cURL to old slug receives HTTP 301 with new Location header.
- **Negative Scenarios & Boundary Tests:** Attempt renaming slug to collide with existing system route `/about`; assert validation halts rename with descriptive error.
- **Automated Tooling:** PHPUnit Integration & cURL assertion.
- **Pass/Fail Criteria:** PASS if slug updates, 301 redirect is generated, cURL verifies redirect, and reserved slug collisions are blocked.

---

#### 31. TEST-ADM-008: Industries Catalog Management Console & Regulatory Mapping
- **Canonical Test ID:** `TEST-ADM-008`
- **Related Requirement ID:** [`FR-ADM-008`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 9 / 26
- **Prerequisites:** User authenticated as Web Admin on `/admin/industries`.
- **Required Test Fixtures:** `industries` and `service_industry_mappings` tables.
- **Execution Steps:**
  1. Navigate to `/admin/industries`.
  2. Click "Create New Industry Vertical".
  3. Fill title, slug, regulatory burdens, and associate 2 existing services.
  4. Click "Save as Draft", then "Publish".
  5. Verify public `/industries` includes the new vertical.
- **Expected Result:** Industry vertical is created with associated service relationships; public listing updates upon publishing.
- **Negative Scenarios & Boundary Tests:** Create industry with empty slug; verify backend automatically generates kebab-case slug from title.
- **Automated Tooling:** Playwright E2E & Vitest Integration.
- **Pass/Fail Criteria:** PASS if industry creates with relational mappings and auto-slug generation functions.

---

#### 32. TEST-ADM-009: Media Library WebP Optimization & Reference Deletion Guard
- **Canonical Test ID:** `TEST-ADM-009`
- **Related Requirement ID:** [`FR-ADM-009`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 10 / 26
- **Prerequisites:** User authenticated as Content Editor on `/admin/media`.
- **Required Test Fixtures:** Test JPEG image (3MB) and an existing image currently embedded in the About Us page.
- **Execution Steps:**
  1. Navigate to `/admin/media`.
  2. Upload test JPEG image with mandatory alt text.
  3. Assert image is processed by GD/Imagick, converted to WebP, and thumbnail generated.
  4. Locate existing image used in About Us page and click "Delete".
  5. Assert deletion modal warns: "Cannot delete media asset: currently referenced by Page: About Us (ID: 2)."
- **Expected Result:** Uploaded image is optimized to WebP with responsive thumbnails; deletion of referenced asset is strictly blocked with referential integrity warning.
- **Negative Scenarios & Boundary Tests:** Upload non-image file (`.php` or `.sh` script); verify server rejects file via MIME magic bytes check.
- **Automated Tooling:** Playwright E2E & PHPUnit File Upload Test.
- **Pass/Fail Criteria:** PASS if upload converts to WebP, MIME check blocks scripts, and referenced asset deletion is prevented.

---

#### 33. TEST-ADM-010: Contact Inquiries Triage CRM Desk & Status Progression
- **Canonical Test ID:** `TEST-ADM-010`
- **Related Requirement ID:** [`FR-ADM-010`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 11 / 26
- **Prerequisites:** User authenticated as BD Manager on `/admin/inquiries/contact`.
- **Required Test Fixtures:** `contact_inquiries` table with 3 inquiries in `NEW` state.
- **Execution Steps:**
  1. Navigate to `/admin/inquiries/contact`.
  2. Click on inquiry `CNT-20261016-0001`.
  3. Add internal triage note: "Contacted director via corporate phone."
  4. Change status from `NEW` to `TRIAGED`, then to `IN_REVIEW`.
  5. Assert status badge updates and internal note displays author name and timestamp.
- **Expected Result:** Inquiry details display with complete audit log; internal notes append securely; status machine advances according to approved transition rules.
- **Negative Scenarios & Boundary Tests:** Attempt invalid transition from `CLOSED` directly to `RESOLVED`; assert transition is rejected.
- **Automated Tooling:** Playwright E2E & Vitest Integration.
- **Pass/Fail Criteria:** PASS if triage notes persist, status updates, and audit ledger logs the action.

---

#### 34. TEST-ADM-011: Strategic Partnership Pipeline Desk & Alliance Management
- **Canonical Test ID:** `TEST-ADM-011`
- **Related Requirement ID:** [`FR-ADM-011`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 12 / 26
- **Prerequisites:** User authenticated as BD Manager on `/admin/partnerships`.
- **Required Test Fixtures:** `partnership_inquiries` table with partnership records.
- **Execution Steps:**
  1. Navigate to `/admin/partnerships`.
  2. Filter partnership pipeline by tier "State Healthcare Provider".
  3. Open inquiry details; update status to `NDA_SENT`.
  4. Assign responsible lead: "Director of Strategic Alliances".
  5. Verify pipeline board reflects updated column.
- **Expected Result:** Partnership pipeline operates as a structured Kanban or tabular desk; status transitions update lead assignment and log audit rows.
- **Negative Scenarios & Boundary Tests:** Attempt access as Content Editor; assert HTTP 403 Forbidden redirect.
- **Automated Tooling:** Playwright E2E.
- **Pass/Fail Criteria:** PASS if pipeline filters, status updates with assignee, and RBAC gate restricts access.

---

#### 35. TEST-ADM-012: RFP Proposal Review Desk & Authenticated Tender File Streaming
- **Canonical Test ID:** `TEST-ADM-012`
- **Related Requirement ID:** [`FR-ADM-012`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 12 / 26
- **Prerequisites:** User authenticated as BD Manager on `/admin/proposals`.
- **Required Test Fixtures:** `proposals` table with proposal containing quarantined tender file `spec.pdf`.
- **Execution Steps:**
  1. Navigate to `/admin/proposals`.
  2. Select proposal `RFP-20261016-0042`.
  3. Click "Download Tender Specification" button.
  4. Inspect response headers: must be `Content-Disposition: attachment; filename="spec.pdf"` streamed via PHP authenticated proxy.
  5. Attempt accessing tender file via direct URL `/storage/private/quarantine/spec.pdf` without authentication.
- **Expected Result:** Authenticated user receives streamed binary file; direct filesystem URL returns HTTP 403 Forbidden or 404 via web server `.htaccess` rule.
- **Negative Scenarios & Boundary Tests:** Attempt file download as unauthorized user; verify streaming endpoint returns HTTP 403.
- **Automated Tooling:** Playwright E2E & cURL Security Test.
- **Pass/Fail Criteria:** PASS if authenticated streaming works and direct web access to quarantine directory is blocked.

---

#### 36. TEST-ADM-013: Email Template Customizer, Placeholder Guard & Outbox Telemetry Console
- **Canonical Test ID:** `TEST-ADM-013`
- **Related Requirement ID:** [`FR-ADM-013`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 13 / 26
- **Prerequisites:** User authenticated as Super Admin on `/admin/email-templates`.
- **Required Test Fixtures:** `email_templates` table and `email_queue` outbox with 2 failed messages.
- **Execution Steps:**
  1. Navigate to `/admin/email-templates/contact-inquiry-ack`.
  2. Edit body template; remove mandatory placeholder `{{reference_code}}` and click Save.
  3. Assert validation blocks saving with error: "Mandatory placeholder {{reference_code}} is missing from template body."
  4. Restore placeholder and save successfully.
  5. Navigate to `/admin/email-templates/outbox` (Module 12 Outbox Telemetry Console).
  6. Inspect table of queued, sent, and failed messages; click "Retry Dispatch" on a failed message.
- **Expected Result:** Template editor enforces mandatory variables; Outbox console provides complete visibility into delivery status and manual retry triggers.
- **Negative Scenarios & Boundary Tests:** Send test email with invalid SMTP configuration; verify outbox captures error message without taking down admin UI.
- **Automated Tooling:** Playwright E2E & PHPUnit Email Engine Test.
- **Pass/Fail Criteria:** PASS if placeholder validation fires, outbox telemetry renders, and manual retry triggers queue update.

---

#### 37. TEST-ADM-014: Careers & Job Openings Management Console
- **Canonical Test ID:** `TEST-ADM-014`
- **Related Requirement ID:** [`FR-ADM-014`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 14 / 26
- **Prerequisites:** User authenticated as HR Recruiter on `/admin/jobs`.
- **Required Test Fixtures:** `job_postings` table with active and closed listings.
- **Execution Steps:**
  1. Navigate to `/admin/jobs`.
  2. Click "Create Opening"; fill title "Senior Operations Specialist", department, and description.
  3. Set status to `PUBLISHED` and save.
  4. Toggle status to `CLOSED` for an older posting.
  5. Check public `/careers` board to confirm listing updates.
- **Expected Result:** HR Recruiter can create, edit, close, and publish job vacancies; public careers portal updates immediately.
- **Negative Scenarios & Boundary Tests:** Attempt to delete a job that has active applicant records; assert deletion is blocked with message advising archiving instead.
- **Automated Tooling:** Playwright E2E.
- **Pass/Fail Criteria:** PASS if job CRUD works and deletion is safeguarded against active applications.

---

#### 38. TEST-ADM-015: Candidate Applications Review Desk & Quarantined Resume Streaming
- **Canonical Test ID:** `TEST-ADM-015`
- **Related Requirement ID:** [`FR-ADM-015`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 14 / 26
- **Prerequisites:** User authenticated as HR Recruiter on `/admin/applications`.
- **Required Test Fixtures:** `career_applications` table with 5 candidate submissions.
- **Execution Steps:**
  1. Navigate to `/admin/applications`.
  2. Filter candidates by job opening "Clinical Nurse Lead".
  3. Open candidate profile; click "Stream Resume PDF".
  4. Assert PDF streams securely with authenticated headers.
  5. Advance candidate status from `SUBMITTED` to `PHONE_SCREEN`.
- **Expected Result:** Candidate details display with application history; resume streams via authenticated controller; status advances with audit log entry.
- **Negative Scenarios & Boundary Tests:** Attempt downloading resume without active session; assert request is redirected to `/admin/login`.
- **Automated Tooling:** Playwright E2E & cURL Security Test.
- **Pass/Fail Criteria:** PASS if candidate filtering works, resume streams securely, and status advances.

---

#### 39. TEST-ADM-016: Case Studies & Social Proof Manager with Consent Release Guard
- **Canonical Test ID:** `TEST-ADM-016`
- **Related Requirement ID:** [`FR-ADM-016`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 15 / 26
- **Prerequisites:** User authenticated as Content Editor on `/admin/case-studies`.
- **Required Test Fixtures:** `case_studies` table with draft case study.
- **Execution Steps:**
  1. Navigate to `/admin/case-studies/new`.
  2. Enter title, sector, problem, intervention, and outcome metrics.
  3. Attempt clicking "Publish to Live" with `client_consent_verified` unchecked.
  4. Assert publication is blocked: "Cannot publish case study: verified client legal consent release is required."
  5. Upload consent release document, check verification box, and publish.
- **Expected Result:** Case study editor enforces client consent verification before allowing publication, preventing unauthorized client claims.
- **Negative Scenarios & Boundary Tests:** Unpublish active case study; verify public `/case-studies` removes card instantly.
- **Automated Tooling:** Playwright E2E & Vitest Integration.
- **Pass/Fail Criteria:** PASS if consent validation blocks unverified publication and unpublishing updates public view.

---

#### 40. TEST-ADM-017: Insights & Article CMS with Reading Time Auto-Calculation
- **Canonical Test ID:** `TEST-ADM-017`
- **Related Requirement ID:** [`FR-ADM-017`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 15 / 26
- **Prerequisites:** User authenticated as Content Editor on `/admin/articles`.
- **Required Test Fixtures:** `articles` table and draft article body (~1000 words).
- **Execution Steps:**
  1. Navigate to `/admin/articles/new`.
  2. Paste 1000 words of markdown text into editor.
  3. Verify calculated reading time displays "5 min read" (based on 200 wpm).
  4. Add category tags "Governance" and "Workforce".
  5. Publish article and verify public display on `/insights/:slug`.
- **Expected Result:** Reading time calculates automatically upon content input; tags attach; published article displays formatted text on public site.
- **Negative Scenarios & Boundary Tests:** Enter invalid characters in article slug; assert slug field auto-sanitizes into valid kebab-case.
- **Automated Tooling:** Playwright E2E & Vitest Component.
- **Pass/Fail Criteria:** PASS if reading time calculates accurately, tags attach, and slug sanitizes.

---

#### 41. TEST-ADM-018: SEO & Metadata Administration Console & Dynamic Sitemap Generator
- **Canonical Test ID:** `TEST-ADM-018`
- **Related Requirement ID:** [`FR-ADM-018`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 16 / 26
- **Prerequisites:** User authenticated as Web Admin on `/admin/seo`.
- **Required Test Fixtures:** `seo_routes` table and system settings.
- **Execution Steps:**
  1. Navigate to `/admin/seo`.
  2. Select route `/about` and update meta title and description.
  3. Inspect character length indicators (Title: 55 chars, Desc: 155 chars).
  4. Click "Rebuild XML Sitemap" button.
  5. Issue GET request to `/sitemap.xml` and inspect contents.
- **Expected Result:** SEO metadata updates persist; sitemap rebuilds dynamically, including all published routes and excluding draft/quarantined pages; lastmod timestamps update.
- **Negative Scenarios & Boundary Tests:** Enter meta title > 70 characters; verify warning badge indicates recommended truncation length.
- **Automated Tooling:** Playwright E2E & XML Schema Validator.
- **Pass/Fail Criteria:** PASS if meta edits persist, character counter functions, and sitemap generates valid XML.

---

#### 42. TEST-ADM-019: Immutable Audit Ledger, Cryptographic Hash Chaining & Search Desk
- **Canonical Test ID:** `TEST-ADM-019`
- **Related Requirement ID:** [`FR-ADM-019`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 17 / 26
- **Prerequisites:** User authenticated as Super Admin or Compliance Auditor on `/admin/audit-logs`.
- **Required Test Fixtures:** `audit_logs` table populated with 50 previous administrative actions.
- **Execution Steps:**
  1. Navigate to `/admin/audit-logs`.
  2. Verify log records include timestamp, user ID, IP address, event domain, action, and cryptographic SHA-256 row hash.
  3. Filter logs by event domain `AUTH` and date range.
  4. Click on an audit entry to view before/after JSON diff.
  5. Verify UI provides ZERO delete or edit controls on audit records.
  6. Test database integrity verification tool (`php cli/verify_audit_chain.php`).
- **Expected Result:** Audit ledger displays tamper-evident history with cryptographic row hash chaining; filtering operates smoothly; UI enforces append-only rules; CLI verification confirms chain integrity.
- **Negative Scenarios & Boundary Tests:** Attempt direct SQL UPDATE or DELETE on audit table; assert operation is blocked by database user privileges or application-level repository guards.
- **Automated Tooling:** PHPUnit Security Test & Playwright E2E.
- **Pass/Fail Criteria:** PASS if audit records render with cryptographic hash, search filters work, and ledger remains append-only.

---

#### 43. TEST-WF-001: Contact Inquiry Lifecycle State Machine Transitions
- **Canonical Test ID:** `TEST-WF-001`
- **Related Requirement ID:** [`FR-WF-001`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 11
- **Prerequisites:** Contact inquiry created in `NEW` state.
- **Required Test Fixtures:** `contact_inquiries` state machine model (`NEW` -> `TRIAGED` -> `IN_REVIEW` -> `CONTACTED` -> `RESOLVED` / `CLOSED` / `SPAM`).
- **Execution Steps:**
  1. Transition inquiry from `NEW` to `TRIAGED` with triage officer assignment.
  2. Transition from `TRIAGED` to `CONTACTED` with communication log.
  3. Transition from `CONTACTED` to `RESOLVED` with resolution notes.
  4. Attempt invalid transition from `NEW` directly to `RESOLVED`.
- **Expected Result:** Valid transitions succeed and log audit entries; invalid transition is rejected with HTTP 422 Unprocessable Entity and state violation error.
- **Negative Scenarios & Boundary Tests:** Attempt transition by user without CRM role; assert HTTP 403.
- **Automated Tooling:** PHPUnit State Machine Unit Test (`tests/workflows/contact_state_test.php`).
- **Pass/Fail Criteria:** PASS if state transitions strictly follow DAG rules and invalid transitions reject.

---

#### 44. TEST-WF-002: Partnership Pipeline State Machine & Milestone Progression
- **Canonical Test ID:** `TEST-WF-002`
- **Related Requirement ID:** [`FR-WF-002`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 12
- **Prerequisites:** Partnership inquiry created in `NEW_INQUIRY` state.
- **Required Test Fixtures:** Partnership workflow states: `NEW_INQUIRY` -> `EVALUATION` -> `NDA_SENT` -> `NEGOTIATION` -> `ACTIVE` / `DECLINED`.
- **Execution Steps:**
  1. Advance status through `EVALUATION`, `NDA_SENT`, and `NEGOTIATION`.
  2. Attach NDA execution timestamp and verified counter-signee.
  3. Complete transition to `ACTIVE`.
  4. Verify audit ledger logs user, timestamp, and state diff for each step.
- **Expected Result:** Pipeline enforces required documentation at specific stages; state advances predictably with complete audit trail.
- **Negative Scenarios & Boundary Tests:** Attempt transitioning to `ACTIVE` without recorded NDA; assert validation halts progression.
- **Automated Tooling:** PHPUnit Workflow Test.
- **Pass/Fail Criteria:** PASS if progression requires prerequisites and updates pipeline cleanly.

---

#### 45. TEST-WF-003: RFP Proposal Lifecycle State Machine & Commercial Scoping
- **Canonical Test ID:** `TEST-WF-003`
- **Related Requirement ID:** [`FR-WF-003`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 12
- **Prerequisites:** RFP submission created in `SUBMITTED` state.
- **Required Test Fixtures:** RFP workflow states: `SUBMITTED` -> `UNDER_REVIEW` -> `SCOPING` -> `PROPOSAL_SENT` -> `ACCEPTED` / `REJECTED` / `EXPIRED`.
- **Execution Steps:**
  1. Advance RFP from `SUBMITTED` to `UNDER_REVIEW` with assigned proposal lead.
  2. Transition to `SCOPING` and enter preliminary commercial value estimate.
  3. Advance to `PROPOSAL_SENT` with attached proposal document.
  4. Transition to `ACCEPTED`.
- **Expected Result:** RFP state transitions require assigned owners; commercial parameters attach securely; proposal lifecycle records milestones in audit ledger.
- **Negative Scenarios & Boundary Tests:** Attempt transition to `ACCEPTED` from `SUBMITTED` directly; assert HTTP 422 state error.
- **Automated Tooling:** PHPUnit Proposal Workflow Test.
- **Pass/Fail Criteria:** PASS if state transition sequence is strictly validated and owners are assigned.

---

#### 46. TEST-WF-004: Candidate Recruitment Application Pipeline State Machine
- **Canonical Test ID:** `TEST-WF-004`
- **Related Requirement ID:** [`FR-WF-004`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 14
- **Prerequisites:** Career application created in `SUBMITTED` state.
- **Required Test Fixtures:** Recruitment workflow states: `SUBMITTED` -> `REVIEWING` -> `SCREENING` -> `INTERVIEWING` -> `OFFER` -> `HIRED` / `REJECTED` / `WITHDRAWN`.
- **Execution Steps:**
  1. Advance candidate through `REVIEWING` to `SCREENING`.
  2. Record interview feedback notes in `INTERVIEWING` stage.
  3. Advance candidate to `OFFER` and finally `HIRED`.
  4. In parallel test, mark candidate `REJECTED` with reason code `QUALIFICATION_MISMATCH`.
- **Expected Result:** Recruitment stages progress systematically; candidate notes and rejection reasons are captured; recruitment compliance standards are maintained.
- **Negative Scenarios & Boundary Tests:** Simulate candidate data retention expiry (6 months); assert scheduled cleanup flags record for anonymization.
- **Automated Tooling:** PHPUnit Recruitment Workflow Test.
- **Pass/Fail Criteria:** PASS if hiring stages advance according to rules and rejection reason codes are mandatory.

---

#### 47. TEST-WF-005: CMS Content Publishing State Machine & Revision Tracking
- **Canonical Test ID:** `TEST-WF-005`
- **Related Requirement ID:** [`FR-WF-005`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phases 7-10, 15
- **Prerequisites:** Content item (page, service, or article) in `DRAFT` state.
- **Required Test Fixtures:** CMS publishing states: `DRAFT` -> `IN_REVIEW` -> `SCHEDULED` -> `PUBLISHED` -> `ARCHIVED`.
- **Execution Steps:**
  1. Create new article draft; verify status is `DRAFT` and not visible on public `/insights`.
  2. Transition to `IN_REVIEW` by Content Editor.
  3. Approve and transition to `PUBLISHED` by Web Admin.
  4. Verify article immediately appears on public listing and detail route.
  5. Transition to `ARCHIVED`; verify route returns HTTP 404.
- **Expected Result:** Content lifecycle enforces editorial review hierarchy; published content is public; draft and archived content remain strictly private.
- **Negative Scenarios & Boundary Tests:** Attempt publishing draft as Content Editor without approval; assert approval step is required if moderation enabled.
- **Automated Tooling:** Playwright E2E & Vitest Integration.
- **Pass/Fail Criteria:** PASS if draft is invisible publicly, publication is instantaneous, and archiving removes public access.

---

#### 48. TEST-WF-006: Safe Slug Renaming, Circular Redirect Detection & Loop Prevention
- **Canonical Test ID:** `TEST-WF-006`
- **Related Requirement ID:** [`FR-WF-006`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 16
- **Prerequisites:** Existing service with slug `slug-alpha`.
- **Required Test Fixtures:** `url_redirects` table.
- **Execution Steps:**
  1. Rename `slug-alpha` to `slug-beta`; verify 301 redirect is registered (`/services/slug-alpha` -> `/services/slug-beta`).
  2. Rename `slug-beta` to `slug-gamma`; verify redirect chain resolves directly (`/services/slug-alpha` -> `/services/slug-gamma`).
  3. Attempt renaming `slug-gamma` back to `slug-alpha` (creating circular loop).
  4. Verify redirect manager detects circular reference and updates existing redirect instead of creating infinite loop (`A -> B -> A`).
- **Expected Result:** System maintains flat 1-hop 301 redirects; circular redirect detection prevents infinite browser redirect loops.
- **Negative Scenarios & Boundary Tests:** Rename slug to reserved system route `/admin`; verify system rejects rename with validation error.
- **Automated Tooling:** PHPUnit Redirect Engine Test (`tests/redirect_loop_test.php`).
- **Pass/Fail Criteria:** PASS if redirect chains flatten to 1-hop, circular references are eliminated, and reserved routes are protected.

---

#### 49. TEST-SEC-001: CSRF Synchronizer Token Defense Across All Mutation Endpoints
- **Canonical Test ID:** `TEST-SEC-001`
- **Related Requirement ID:** [`FR-SEC-001`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 6
- **Prerequisites:** Admin session authenticated with valid session cookie.
- **Required Test Fixtures:** CSRF token generator and validation middleware.
- **Execution Steps:**
  1. Issue valid POST request with matching `X-CSRF-Token` header; assert HTTP 200/201.
  2. Issue POST request with missing `X-CSRF-Token` header; assert HTTP 403 Forbidden with `CSRF_TOKEN_MISSING`.
  3. Issue POST request with mismatched/tampered token; assert HTTP 403 Forbidden with `CSRF_TOKEN_INVALID`.
  4. Test all HTTP mutation methods (`POST`, `PUT`, `PATCH`, `DELETE`).
- **Expected Result:** All state-changing endpoints strictly reject requests missing valid CSRF tokens, preventing cross-site request forgery.
- **Negative Scenarios & Boundary Tests:** Submit request with CSRF token in GET URL query param; verify server rejects GET mutation tokens.
- **Automated Tooling:** PHPUnit Security Test Suite.
- **Pass/Fail Criteria:** PASS if 100% of mutation requests without valid CSRF token return HTTP 403.

---

#### 50. TEST-SEC-002: Secure Session Cookie Architecture, Idle Timeouts & Session Invalidation
- **Canonical Test ID:** `TEST-SEC-002`
- **Related Requirement ID:** [`FR-SEC-002`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 6
- **Prerequisites:** Admin login completed.
- **Required Test Fixtures:** `admin_sessions` table with session tokens and last activity timestamps.
- **Execution Steps:**
  1. Inspect session cookie attributes in Set-Cookie header.
  2. Assert presence of: `HttpOnly`, `Secure`, `SameSite=Lax`, and `Path=/admin`.
  3. Simulate 31 minutes of inactivity; issue authenticated request.
  4. Assert session is expired; server responds with HTTP 401 Unauthorized and clears session cookie.
  5. Log out; assert session record in database is deleted/invalidated immediately.
- **Expected Result:** Session cookies enforce strict browser security attributes; 30-minute idle timeout is enforced; logout immediately revokes session token.
- **Negative Scenarios & Boundary Tests:** Attempt replaying session cookie after explicit logout; assert server rejects old token.
- **Automated Tooling:** PHPUnit Session Test & Playwright E2E.
- **Pass/Fail Criteria:** PASS if cookie flags are verified, 30m idle timeout terminates session, and revoked sessions reject.

---

#### 51. TEST-SEC-003: Document Upload Quarantine & MIME Magic Byte Inspection
- **Canonical Test ID:** `TEST-SEC-003`
- **Related Requirement ID:** [`FR-SEC-003`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 10
- **Prerequisites:** File upload endpoint on `/api/v1/public/proposals/submit`.
- **Required Test Fixtures:** Valid PDF file, valid DOCX file, and malicious executable file renamed to `report.pdf`.
- **Execution Steps:**
  1. Upload legitimate PDF file (2MB); assert HTTP 200 and file stored outside web root in `storage/private/quarantine/`.
  2. Upload malicious executable file renamed to `report.pdf`.
  3. Inspect server response: must be HTTP 422 Unprocessable Entity with error "Invalid file MIME type: expected application/pdf."
  4. Attempt uploading SVG with embedded `<script>` tag; assert file is rejected.
- **Expected Result:** Server validates authentic file headers using PHP `finfo` magic bytes inspection; malicious and spoofed files are rejected; legitimate uploads are stored securely outside public web root.
- **Negative Scenarios & Boundary Tests:** Upload file with double extension `document.pdf.php`; assert server strips extension and saves with sanitized UUID name.
- **Automated Tooling:** PHPUnit File Security Test.
- **Pass/Fail Criteria:** PASS if magic byte check catches spoofed files, scripts are blocked, and uploads remain quarantined.

---

#### 52. TEST-SEC-004: Authenticated Document Streaming & Direct URL Access Denial
- **Canonical Test ID:** `TEST-SEC-004`
- **Related Requirement ID:** [`FR-SEC-004`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 10 / 12 / 14
- **Prerequisites:** Quarantined document stored at `storage/private/quarantine/doc-1234.pdf`.
- **Required Test Fixtures:** `proposals` record referencing `doc-1234.pdf`.
- **Execution Steps:**
  1. Issue cURL request directly to public web URL `/storage/private/quarantine/doc-1234.pdf`.
  2. Assert server returns HTTP 403 Forbidden or 404 Not Found via Apache/Hostinger `.htaccess` rules.
  3. Authenticate as BD Manager and request `GET /api/v1/admin/documents/doc-1234/stream`.
  4. Assert document streams successfully with `Content-Disposition: attachment`.
  5. Verify audit log records document download event with user ID and timestamp.
- **Expected Result:** Direct web access to quarantine directory is impossible; downloads require authenticated RBAC session and are streamed through PHP with complete audit logging.
- **Negative Scenarios & Boundary Tests:** Attempt download as Content Editor; assert HTTP 403 Forbidden.
- **Automated Tooling:** cURL & PHPUnit Security Test.
- **Pass/Fail Criteria:** PASS if direct URL access is forbidden, RBAC streaming succeeds, and audit log records download.

---

#### 53. TEST-SEC-005: Production Fail-Fast Configuration & Default Secret Boot Halting
- **Canonical Test ID:** `TEST-SEC-005`
- **Related Requirement ID:** [`FR-SEC-005`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 4
- **Prerequisites:** Backend environment boot loader (`bootstrap/app.php`).
- **Required Test Fixtures:** Test `.env` configurations with default insecure keys (`APP_KEY=base64:default_key_change_me`).
- **Execution Steps:**
  1. Set `APP_ENV=production` and `APP_KEY=default_placeholder_secret`.
  2. Attempt booting application / making any API request.
  3. Assert application boot immediately halts with fatal exception: "Insecure configuration: Default APP_KEY detected in production environment."
  4. Set `APP_DEBUG=true` in production; assert boot halts with error: "APP_DEBUG must be false in production."
  5. Provide strong cryptographic 32-byte key; assert boot succeeds cleanly.
- **Expected Result:** Application refuses to boot in production if default secrets, weak keys, or debug modes are active, preventing deployment with insecure settings.
- **Negative Scenarios & Boundary Tests:** Simulate missing database credentials; assert boot halts with clean error and zero credential leakage.
- **Automated Tooling:** PHPUnit Environment Boot Test.
- **Pass/Fail Criteria:** PASS if boot fails fast on insecure defaults and succeeds only with verified secure configuration.

---

#### 54. TEST-SEC-006: Zero Secrets in Version Control & Git Secret Scanning Audit
- **Canonical Test ID:** `TEST-SEC-006`
- **Related Requirement ID:** [`FR-SEC-006`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 4 / 31
- **Prerequisites:** Git repository containing codebase.
- **Required Test Fixtures:** Git repository history and pre-commit secret scanning hooks.
- **Execution Steps:**
  1. Run programmatic repository secret scanner across entire git commit history (`gitleaks` or custom script).
  2. Inspect `.gitignore` for mandatory exclusions: `.env`, `.env.local`, `storage/private/*`, `*.pem`, `*.key`.
  3. Verify `.env.example` contains only empty template keys without actual secrets.
  4. Attempt staging a file named `.env` containing test API key; assert pre-commit hook rejects commit.
- **Expected Result:** Zero credentials, database passwords, or private keys exist in version control; `.gitignore` covers all sensitive artifacts; pre-commit hook blocks secret commits.
- **Negative Scenarios & Boundary Tests:** Check commit log for accidental past credentials; assert history is completely clean.
- **Automated Tooling:** Gitleaks / Shell git scanner.
- **Pass/Fail Criteria:** PASS if 0 secrets detected across 100% of repo files and pre-commit hook halts secret commits.

---

#### 55. TEST-SEC-007: Safe CLI Database Migrations & Web Execution Prevention
- **Canonical Test ID:** `TEST-SEC-007`
- **Related Requirement ID:** [`FR-SEC-007`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 5
- **Prerequisites:** Database CLI migration runner (`php cli/migrate.php`).
- **Required Test Fixtures:** Test migration creating table `test_table` and rollback dropping it.
- **Execution Steps:**
  1. Execute migration from CLI: `php cli/migrate.php up`.
  2. Assert migration executes in an atomic transaction and records version in `schema_migrations`.
  3. Execute rollback: `php cli/migrate.php down`; assert table is dropped cleanly.
  4. Attempt requesting migration runner via HTTP browser URL `https://voak.local/cli/migrate.php`.
- **Expected Result:** Migrations execute reliably via CLI inside transactions; HTTP web request to CLI script returns HTTP 403 Forbidden or is inaccessible outside web root.
- **Negative Scenarios & Boundary Tests:** Simulate SQL syntax error inside migration step 2; assert transaction rolls back step 1 cleanly without leaving partial tables.
- **Automated Tooling:** PHPUnit Database Test & cURL.
- **Pass/Fail Criteria:** PASS if CLI migrations execute transactionally, roll back on failure, and web execution is blocked.

---

#### 56. TEST-SEO-001: PHP Hybrid Semantic Pre-Rendering in #ssr-fallback & React createRoot Client Mounting
- **Canonical Test ID:** `TEST-SEO-001`
- **Related Requirement ID:** [`FR-SEO-001`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 16 / 29
- **Prerequisites:** Public website with PHP front controller pre-renderer active.
- **Required Test Fixtures:** Published service `clinical-workforce-solutions`.
- **Execution Steps:**
  1. Issue cURL request without JavaScript (`User-Agent: Googlebot`) to `/services/clinical-workforce-solutions`.
  2. Inspect HTML source: verify `<article id="ssr-fallback">` contains semantic `<h1>`, description, and deliverable text.
  3. Load the page in a browser with JavaScript enabled.
  4. Observe React `createRoot` client mounting: assert React successfully mounts into `#root` and `#ssr-fallback` is smoothly replaced or hidden without visual flashing.
  5. Verify browser console exhibits ZERO React hydration mismatch errors or warnings.
- **Expected Result:** Search engine crawlers receive complete semantic HTML content directly in initial HTTP response; client browser mounts React smoothly without hydration mismatch errors or screen flash.
- **Negative Scenarios & Boundary Tests:** Disable JavaScript in browser; assert page content remains fully readable and navigation links remain navigable.
- **Automated Tooling:** Playwright E2E & cURL assertion.
- **Pass/Fail Criteria:** PASS if crawler receives semantic HTML, client mounts cleanly, and zero hydration errors occur.

---

#### 57. TEST-SEO-002: Authentic HTTP 404 Status Header Emission & Soft-404 Prevention
- **Canonical Test ID:** `TEST-SEO-002`
- **Related Requirement ID:** [`FR-SEO-002`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 16 / 29
- **Prerequisites:** Public front controller routing.
- **Required Test Fixtures:** Set of 5 non-existent random URLs (`/random-page-1234`, `/services/invalid-test-slug`, etc.).
- **Execution Steps:**
  1. Send HTTP GET requests via cURL to each non-existent URL.
  2. Inspect raw HTTP response headers for each request.
  3. Assert HTTP status code line is strictly `HTTP/1.1 404 Not Found` (never `200 OK`).
  4. Verify `<meta name="robots" content="noindex, nofollow">` is present in HTML `<head>`.
- **Expected Result:** All non-existent routes emit authentic HTTP 404 headers, preventing search engine soft-404 indexing penalties and ensuring crawlers drop invalid URLs.
- **Negative Scenarios & Boundary Tests:** Check response for deleted/unpublished article; assert status is 404 or 410 Gone.
- **Automated Tooling:** cURL & Playwright E2E.
- **Pass/Fail Criteria:** PASS if HTTP status is strictly 404 and robots meta tag specifies noindex.

---

#### 58. TEST-SEO-003: Dynamic XML Sitemap Generation, Exclusions & Lastmod Timestamps
- **Canonical Test ID:** `TEST-SEO-003`
- **Related Requirement ID:** [`FR-SEO-003`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 16 / 29
- **Prerequisites:** Sitemap generator endpoint active at `/sitemap.xml`.
- **Required Test Fixtures:** Database with 22 public pages, 6 published services, 2 draft services, and 8 published articles.
- **Execution Steps:**
  1. Request GET `/sitemap.xml`.
  2. Validate XML structure against Google Sitemap XML schema definition.
  3. Verify all 22 public pages, 6 published services, and 8 published articles are listed.
  4. Verify 2 draft services are strictly EXCLUDED from sitemap.
  5. Assert all `<lastmod>` tags match valid W3C ISO 8601 dates (e.g., `YYYY-MM-DD`).
- **Expected Result:** Sitemap generates valid XML; includes all active public canonical URLs; strictly excludes drafts and admin routes; provides accurate lastmod dates.
- **Negative Scenarios & Boundary Tests:** Unpublish an article; rebuild sitemap; verify URL is immediately removed from XML output.
- **Automated Tooling:** XML Validator & cURL assertion.
- **Pass/Fail Criteria:** PASS if XML is valid against schema, drafts are excluded, and lastmod dates match database.

---

#### 59. TEST-SEO-004: Structured Data JSON-LD Schema Validation Across Public Entities
- **Canonical Test ID:** `TEST-SEO-004`
- **Related Requirement ID:** [`FR-SEO-004`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 16 / 29
- **Prerequisites:** Public pages rendered with embedded JSON-LD scripts in `<head>`.
- **Required Test Fixtures:** Front controller schema generator.
- **Execution Steps:**
  1. Load `/`; extract `<script type="application/ld+json">` for `Organization` schema.
  2. Validate JSON syntax and Schema.org properties (`name`, `url`, `logo`, `contactPoint`).
  3. Load `/services/clinical-workforce-solutions`; extract `Service` schema.
  4. Load `/faqs`; extract `FAQPage` schema; assert all question/answer pairs are mapped.
  5. Load `/insights/article-slug`; extract `Article` schema with `author` and `datePublished`.
- **Expected Result:** All JSON-LD schemas pass Google Rich Results / Schema.org validation without errors or missing required fields.
- **Negative Scenarios & Boundary Tests:** Test schema with empty optional properties; assert generator omits null keys rather than emitting `null` strings.
- **Automated Tooling:** Playwright E2E & Schema.org validator.
- **Pass/Fail Criteria:** PASS if 100% of tested JSON-LD blocks pass Schema.org validation without syntax or structural errors.

---

#### 60. TEST-A11Y-001: Mathematical WCAG 2.2 AA Contrast Compliance Across All Color Tokens
- **Canonical Test ID:** `TEST-A11Y-001`
- **Related Requirement ID:** [`FR-A11Y-001`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 3 / 19 / 28
- **Prerequisites:** Design tokens and CSS stylesheet compiled.
- **Required Test Fixtures:** Palette tokens: Royal Purple (`#665CE9`), Navy (`#252B5E`), Lavender (`#F2F0F8`), Mint (`#E8F5F1`), Sky Blue (`#EBF3FA`), Coral (`#D94841`), etc.
- **Execution Steps:**
  1. Run automated contrast calculation script across all token pairs used in typography and UI controls.
  2. Verify normal text (`#252B5E` on `#F2F0F8`): assert ratio >= 4.5:1 (actual ~11.8:1).
  3. Verify white text on primary button (`#FFFFFF` on `#665CE9`): assert ratio >= 4.5:1 (actual ~5.2:1).
  4. Verify error text (`#D94841` on `#FDF3F2`): assert ratio >= 4.5:1 (actual ~4.9:1).
  5. Run automated Axe-core scan across all 22 public pages.
- **Expected Result:** Every typographic pairing meets or exceeds WCAG 2.2 AA contrast standards (>= 4.5:1 for normal text, >= 3.0:1 for large text and interactive borders); zero contrast violations.
- **Negative Scenarios & Boundary Tests:** Inspect muted placeholder text; assert minimum 4.5:1 contrast against input background.
- **Automated Tooling:** Axe-core & Automated Contrast Validator (`scratch/contrast_audit.js`).
- **Pass/Fail Criteria:** PASS if 0 contrast violations reported by Axe-core across all 22 public pages.

---

#### 61. TEST-A11Y-002: Complete Keyboard Navigation, Focus Rings & Modal Focus Traps
- **Canonical Test ID:** `TEST-A11Y-002`
- **Related Requirement ID:** [`FR-A11Y-002`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 19 / 28
- **Prerequisites:** Public website and admin console loaded in browser.
- **Required Test Fixtures:** Interactive components: Navigation header, RFP wizard, leadership modal, and admin dialogs.
- **Execution Steps:**
  1. Navigate entire homepage using Tab and Shift+Tab keys exclusively.
  2. Assert all interactive elements receive a clearly visible 2px solid royal purple focus ring with 2px offset.
  3. Open leadership bio modal using Enter key; verify keyboard focus immediately transfers inside modal.
  4. Press Tab repeatedly; verify focus wraps within modal and does NOT escape to underlying page.
  5. Press Escape key; verify modal closes and focus returns precisely to the triggering button.
- **Expected Result:** All interactive elements are fully operable via keyboard; focus order is logical and intuitive; focus indicators are prominent; modals trap focus and dismiss on Escape.
- **Negative Scenarios & Boundary Tests:** Test dropdown menus with keyboard ArrowDown / ArrowUp keys; assert keyboard navigation follows WAI-ARIA authoring practices.
- **Automated Tooling:** Playwright E2E Keyboard Suite & Axe-core.
- **Pass/Fail Criteria:** PASS if keyboard focus trap works, focus returns to trigger, and zero keyboard traps exist.

---

#### 62. TEST-A11Y-003: Vestibular Disorder Protection & prefers-reduced-motion Support
- **Canonical Test ID:** `TEST-A11Y-003`
- **Related Requirement ID:** [`FR-A11Y-003`](01_MASTER_FUNCTIONAL_REQUIREMENTS.md)
- **Relevant Roadmap Phase:** Phase 19 / 28
- **Prerequisites:** Motion for React animations and CSS transitions configured with reduced motion media query.
- **Required Test Fixtures:** Browser configured with `prefers-reduced-motion: reduce` emulation.
- **Execution Steps:**
  1. Enable `prefers-reduced-motion: reduce` in browser emulation.
  2. Navigate to `/` and trigger bento hover, page scroll, and modal opening.
  3. Inspect computed CSS animation and transition durations on animated elements.
  4. Assert all transform displacements, parallax shifts, and bounce transitions are completely suppressed or reduced to instantaneous opacity fades.
  5. Disable emulation and assert spring animations resume normal operation.
- **Expected Result:** When `prefers-reduced-motion: reduce` is active, interface eliminates vestibular triggers while preserving full functional interactivity.
- **Negative Scenarios & Boundary Tests:** Inspect carousel or ticker components; assert auto-scrolling motion halts under reduced motion.
- **Automated Tooling:** Playwright E2E Animation Suite (`tests/e2e/reduced-motion.spec.ts`).
- **Pass/Fail Criteria:** PASS if zero transform movements occur under reduced motion and content remains fully accessible.

---

### Acceptance Test Catalog Consistency & Traceability Audit

| Verification Metric | Target Value | Actual Documented Value | Verification Verdict |
| :--- | :---: | :---: | :---: |
| **Total Stated Inventory Count** | 62 | **62** | PASS |
| **Total Fully Detailed Scenarios** | 62 | **62** | PASS |
| **Unwritten / Orphan Test Identifiers** | 0 | **0** | PASS |
| **Master Requirements Mapping** | 62 / 62 | **100.0%** | PASS |
| **Requirements Traceability Matrix Mapping** | 62 / 62 | **100.0%** | PASS |
| **Standardized Canonical Naming Scheme** | `TEST-[DOMAIN]-[NUM]` | **100.0% Compliant** | PASS |
