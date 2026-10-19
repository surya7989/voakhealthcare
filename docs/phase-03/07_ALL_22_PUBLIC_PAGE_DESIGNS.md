# Complete Public Page-by-Page Specifications (All 22 Public Pages)
## Voak Health Care — Corporate Digital Brand Guidelines & Layout Specifications

**Document Reference:** `docs/phase-03/07_ALL_22_PUBLIC_PAGE_DESIGNS.md`  
**Phase:** Phase 3 of 31 — Professional UI/UX Design (Reconciled Baseline)  
**Total Pages Covered:** Exactly 22 Public Pages (`PAGE-PUB-01` to `PAGE-PUB-22`)  
**Functional Alignment:** Phase 1 Master Functional Requirements (`01_MASTER_FUNCTIONAL_REQUIREMENTS.md`, `02_PUBLIC_PAGE_BY_PAGE_SPECIFICATIONS.md`), Complete Traceability Matrix (`15_REQUIREMENTS_TRACEABILITY_MATRIX.md`), and Design Architecture (`03_REACT_APPLICATION_ARCHITECTURE.md`)  
**Visual Benchmark:** Approved Second Homepage Reference Design (`Screenshot_2026-10-16_073113.png` / `references/approved_homepage_reference_image_2.png`)

---

### Master Public Sitemap & Route Index

```
PUB-01: Home (/) — PAGE-PUB-01 [FR-PUB-001..004]
PUB-02: About Us (/about) — PAGE-PUB-02 [FR-PUB-009]
PUB-03: Services Overview (/services) — PAGE-PUB-03 [FR-PUB-005]
PUB-04: Service Detail (/services/:slug) — PAGE-PUB-04 [FR-PUB-006]
PUB-05: Industries Overview (/industries) — PAGE-PUB-05 [FR-PUB-007]
PUB-06: Industry Detail (/industries/:slug) — PAGE-PUB-06 [FR-PUB-008]
PUB-07: Why Choose Voak (/why-choose-voak) — PAGE-PUB-07 [FR-PUB-010]
PUB-08: How We Work (/how-we-work) — PAGE-PUB-08 [FR-PUB-011]
PUB-09: Quality & Compliance (/quality-compliance) — PAGE-PUB-09 [FR-PUB-012]
PUB-10: Partners & Clients (/partners-clients) — PAGE-PUB-10 [FR-PUB-004]
PUB-11: Case Studies (/case-studies) — PAGE-PUB-11 [FR-PUB-013]
PUB-12: Careers (/careers) — PAGE-PUB-12 [FR-PUB-014]
PUB-13: Job Details (/careers/:slug) — PAGE-PUB-13 [FR-PUB-015, FORM-PUB-04]
PUB-14: Insights & Articles (/insights) — PAGE-PUB-14 [FR-PUB-016]
PUB-15: Individual Article (/insights/:slug) — PAGE-PUB-15 [FR-PUB-017]
PUB-16: Frequently Asked Questions (/faqs) — PAGE-PUB-16 [FR-PUB-018]
PUB-17: Contact Us (/contact) — PAGE-PUB-17 [FR-PUB-019, FORM-PUB-01]
PUB-18: Partner With Us (/partner-with-us) — PAGE-PUB-18 [FR-PUB-020, FORM-PUB-02]
PUB-19: Request a Proposal (/request-proposal) — PAGE-PUB-19 [FR-PUB-021, FORM-PUB-03]
PUB-20: Privacy Policy (/privacy-policy) — PAGE-PUB-20 [FR-PUB-022]
PUB-21: Terms & Legal (/terms) — PAGE-PUB-21 [FR-PUB-022]
PUB-22: Error / 404 / 500 (/error) — PAGE-PUB-22 [FR-PUB-023]
```

---

### Detailed Page-by-Page Specifications

#### 1. PUB-01: Home (`/`)
- **Canonical ID:** `PAGE-PUB-01`
- **Purpose:** Primary digital flagship introducing Voak Health Care's B2B healthcare infrastructure, staffing, and governance.
- **Hero Variant:** Signature Lavender Canvas (`#F2F0F8`), healthcare professional portrait (`hero_doctor.jpg`) framed with pastel pink background collage (`#EEAFD7`) and warm buttercup yellow organic accent (`#FBDC62`), floating illustrated badges (`Clinical Excellence`, `Enterprise Partner`).
- **Layout & Bento Structure:** White rounded main shell (`radius: 36px`), 3 colorful feature cards (Pink, Yellow, Mint), 12-col bento service grid (8+4 and 4+8), split "Why Voak" section with operations coordinator portrait, 4-step workflow journey, partner CTA banner, and accessible footer.
- **CTAs:** Primary: "Explore Our Services" (`btn-primary`), Secondary: "Partner With Us" (`btn-outline`).
- **Traceability:** Aligns with Phase 1 `FR-PUB-001`, `FR-PUB-002`, `FR-PUB-003`, `FR-PUB-004`.

#### 2. PUB-02: About Us (`/about`)
- **Canonical ID:** `PAGE-PUB-02`
- **Purpose:** Communicating institutional mission, clinical leadership, ethical charter, and corporate governance.
- **Hero Variant:** Split editorial layout with healthcare leadership photography.
- **Sections:** 1. Executive Mission, 2. Clinical Leadership Bio Cards, 3. Ethical Governance Charter, 4. Quality Standards Commitment, 5. Institutional Partner Callout.
- **CTAs:** Primary: "Download Capability Deck", Secondary: "Partner With Us".
- **Traceability:** Aligns with Phase 1 `FR-PUB-009`.

#### 3. PUB-03: Services Overview (`/services`)
- **Canonical ID:** `PAGE-PUB-03`
- **Purpose:** Full directory of institutional capabilities and service models.
- **Hero Variant:** Full-width bento header with 4 colored pillar chips.
- **Sections:** 1. Categorized Service Bento Grids (Clinical Workforce, Operational BPO, Clinical Governance, Allied Health), 2. Service Level Agreement (SLA) Matrix comparing Response Times and Tiers, 3. Custom Package Inquiry Card.
- **CTAs:** Primary: "Request Service Consultation", Secondary: "Compare Service Tiers".
- **Traceability:** Aligns with Phase 1 `FR-PUB-005`.

#### 4. PUB-04: Service Detail (`/services/:slug`)
- **Canonical ID:** `PAGE-PUB-04`
- **Purpose:** Deep-dive into a specific service line (e.g. Clinical Workforce, Allied Health Infrastructure).
- **Hero Variant:** Specific clinical domain photography, domain title, and breadcrumb navigation.
- **Sections:** 1. Service Scope & Deliverables, 2. Clinical Credentialing Protocols, 3. Ward Integration Workflow, 4. SLA Framework, 5. Frequently Asked Questions, 6. Direct Service Inquiry Form.
- **CTAs:** Primary: "Enquire for This Service", Secondary: "Book Discovery Call".
- **Traceability:** Aligns with Phase 1 `FR-PUB-006`.

#### 5. PUB-05: Industries Overview (`/industries`)
- **Canonical ID:** `PAGE-PUB-05`
- **Purpose:** Sector-specific solutions for hospitals, aged care, community providers, and clinics.
- **Hero Variant:** 4-quadrant pastel grid (Pink, Yellow, Mint, Sky Blue) showcasing facility environments.
- **Sections:** 1. Sector Solution Matrix, 2. Regulatory Alignment Crosswalk, 3. Sector Impact Stories, 4. Consultation Request.
- **CTAs:** Primary: "Request Sector Briefing", Secondary: "Consult Sector Lead".
- **Traceability:** Aligns with Phase 1 `FR-PUB-007`.

#### 6. PUB-06: Industry Detail (`/industries/:slug`)
- **Canonical ID:** `PAGE-PUB-06`
- **Purpose:** Tailored operational blueprint for a specific healthcare sector (e.g. Acute Hospitals).
- **Hero Variant:** Sector facility backdrop with specific sector executive quote.
- **Sections:** 1. Sector Challenges vs. Voak Solutions, 2. Staffing Surge Frameworks, 3. Case Evidence, 4. Roster Demand Forecasting, 5. Sector Inquiry Desk.
- **CTAs:** Primary: "Download Sector Blueprint", Secondary: "Request Tailored RFP".
- **Traceability:** Aligns with Phase 1 `FR-PUB-008`.

#### 7. PUB-07: Why Choose Voak (`/why-choose-voak`)
- **Canonical ID:** `PAGE-PUB-07`
- **Purpose:** Competitive differentiation and institutional trust evidence.
- **Hero Variant:** Lavender surface with 4 high-contrast differentiator pillars.
- **Sections:** 1. Credentialing Verification Protocols, 2. Service Level Agreement Framework, 3. Clinical Governance Supervision, 4. Audited Compliance Record, 5. Verified Case Studies.
- **CTAs:** Primary: "Review Quality Charter", Secondary: "Partner With Us".
- **Traceability:** Aligns with Phase 1 `FR-PUB-010`.

#### 8. PUB-08: How We Work (`/how-we-work`)
- **Canonical ID:** `PAGE-PUB-08`
- **Purpose:** Transparent 4-stage B2B partnership methodology.
- **Hero Variant:** Horizontal interactive process roadmap with connecting lines and stage chips.
- **Sections:** 1. Stage 1 Operational Discovery, 2. Stage 2 Solution Architecture, 3. Stage 3 Deployment, 4. Stage 4 Continuous Governance, 5. Escalation Protocols.
- **CTAs:** Primary: "Initiate Discovery Audit", Secondary: "Schedule Briefing".
- **Traceability:** Aligns with Phase 1 `FR-PUB-011`.

#### 9. PUB-09: Quality & Compliance (`/quality-compliance`)
- **Canonical ID:** `PAGE-PUB-09`
- **Purpose:** Comprehensive compliance verification framework and clinical governance standards.
- **Hero Variant:** Authoritative white and lavender grid with verified standards badges.
- **Sections:** 1. Professional Credentialing Standard, 2. National Background Checks Protocol, 3. Mandatory Clinical Competency Matrix, 4. Incident Reporting & Triage Governance.
- **CTAs:** Primary: "Review Governance Framework", Secondary: "Contact Clinical Governance Lead".
- **Traceability:** Aligns with Phase 1 `FR-PUB-012`.

#### 10. PUB-10: Partners & Clients (`/partners-clients`)
- **Canonical ID:** `PAGE-PUB-10`
- **Purpose:** Institutional healthcare network alliances, collaborative delivery models, and accredited ecosystem directory.
- **Hero Variant:** Institutional network matrix and partnership framework overview.
- **Sections:** 1. Healthcare Ecosystem Categories (Public Hospitals & Local Health Networks, Private Hospital Operators, Residential Aged Care Groups, Primary Care & Community Health Alliances), 2. Verified Institutional Directory (with explicit verified-evidence requirements and safe empty states for unverified placeholders to avoid fabricated logos/claims), 3. Partnership Engagement Workflow, 4. Strategic Partnership Enquiry Callout (directing to FORM-PUB-02).
- **CTAs:** Primary: "Initiate Partnership Enquiry", Secondary: "Review Alliance Framework".
- **Traceability:** Aligns with Phase 1 `FR-PUB-004`.

#### 11. PUB-11: Case Studies (`/case-studies`)
- **Canonical ID:** `PAGE-PUB-11`
- **Purpose:** Documented operational transformations and measurable healthcare outcomes.
- **Hero Variant:** Bento case study cards with colored tags (Acute Care, Aged Care, Allied Health).
- **Sections:** 1. Featured Hospital Surge Staffing Case, 2. Aged Care Roster Transformation, 3. Multidisciplinary Allied Health Integration, 4. Verified Institutional Consent Release Badges.
- **CTAs:** Primary: "Read Detailed Case Study", Secondary: "Request Similar Solution".
- **Traceability:** Aligns with Phase 1 `FR-PUB-013`.

#### 12. PUB-12: Careers (`/careers`)
- **Canonical ID:** `PAGE-PUB-12`
- **Purpose:** Practitioner and healthcare operations recruitment portal.
- **Hero Variant:** Clinician photography with "Build Your Healthcare Career with Voak".
- **Sections:** 1. Why Clinicians Join Voak (Flexible rosters, CPD support, competitive allowances), 2. Clinical Specialties in Demand, 3. Filterable Job Requisition Board (ADM-13/14 integration), 4. General Talent Pool Registration.
- **CTAs:** Primary: "View Open Requisitions", Secondary: "Register Clinical Interest".
- **Traceability:** Aligns with Phase 1 `FR-PUB-014`.

#### 13. PUB-13: Job Details (`/careers/:slug`)
- **Canonical ID:** `PAGE-PUB-13`
- **Purpose:** Specific job opening description, salary package, and application engine.
- **Hero Variant:** Structured job header with location tag, employment type, salary band, and closing date.
- **Sections:** 1. Clinical Responsibilities, 2. Professional Qualifications & Checks, 3. Remuneration & Benefits, 4. Multi-part Application Form (FORM-PUB-04 with quarantined resume dropzone).
- **CTAs:** Primary: "Apply for Position (FORM-PUB-04)", Secondary: "Share Job Link".
- **Traceability:** Aligns with Phase 1 `FR-PUB-015`.

#### 14. PUB-14: Insights & Articles (`/insights`)
- **Canonical ID:** `PAGE-PUB-14`
- **Purpose:** Healthcare industry thought leadership, clinical research articles, and regulatory updates.
- **Hero Variant:** Editorial magazine grid with featured analysis.
- **Sections:** 1. Featured Executive Article, 2. Filterable Article Grid (Clinical Governance, Workforce Planning, Policy), 3. Regulatory Alert Bulletins, 4. Proposed Dispatch Notification [PROPOSED - OUT OF SCOPE].
- **CTAs:** Primary: "Read Analysis", Secondary: "Download White Paper".
- **Traceability:** Aligns with Phase 1 `FR-PUB-016`.

#### 15. PUB-15: Individual Article (`/insights/:slug`)
- **Canonical ID:** `PAGE-PUB-15`
- **Purpose:** High-legibility reading experience for executive articles and clinical white papers.
- **Hero Variant:** Clean reading typography header, author bio, reading time, and publication date.
- **Sections:** 1. Article Narrative with Inset Quotes, 2. Embedded Data Graphics and Infographics, 3. Regulatory References and Footnotes, 4. Author Contact Desk, 5. Related Articles Grid.
- **CTAs:** Primary: "Download Article PDF", Secondary: "Share Analysis".
- **Traceability:** Aligns with Phase 1 `FR-PUB-017`.

#### 16. PUB-16: Frequently Asked Questions (`/faqs`)
- **Canonical ID:** `PAGE-PUB-16`
- **Purpose:** Institutional answers regarding contracting, credentialing, SLAs, and emergency requests.
- **Hero Variant:** Search-focused header with category pills.
- **Sections:** 1. Real-time Search Input, 2. Categorized Accordion Groups (General, Staffing SLAs, Credentialing, Billing), 3. Direct Helpdesk Contact Desk.
- **CTAs:** Primary: "Ask Triage Desk", Secondary: "Contact Operations Desk".
- **Traceability:** Aligns with Phase 1 `FR-PUB-018`.

#### 17. PUB-17: Contact Us (`/contact`)
- **Canonical ID:** `PAGE-PUB-17`
- **Purpose:** Direct business inquiries, configurable regional headquarters details, and operational triage desk.
- **Hero Variant:** Split card layout: Office Locations & Regional Details on Left, Direct Contact Form (FORM-PUB-01) on Right.
- **Sections:** 1. Operational Triage Email & International Phone, 2. Configurable Corporate Headquarters Details, 3. Contact Form (FORM-PUB-01) with International Phone Validation, 4. Privacy Consent Indicator.
- **CTAs:** Primary: "Submit Corporate Inquiry (FORM-PUB-01)", Secondary: "Contact Triage Desk".
- **Traceability:** Aligns with Phase 1 `FR-PUB-019`.

#### 18. PUB-18: Partner With Us (`/partner-with-us`)
- **Canonical ID:** `PAGE-PUB-18`
- **Purpose:** Dedicated institutional B2B partnership proposal landing page.
- **Hero Variant:** High-impact purple and lavender gradient with executive engagement focus.
- **Sections:** 1. Enterprise Service Models, 2. Custom SLA Tier Frameworks, 3. Governance Sign-off Workflow, 4. Dedicated Partnership Inquiry Form (FORM-PUB-02).
- **CTAs:** Primary: "Initiate Partnership Discussion (FORM-PUB-02)", Secondary: "Submit Tender / RFP".
- **Traceability:** Aligns with Phase 1 `FR-PUB-020`.

#### 19. PUB-19: Request a Proposal (`/request-proposal`)
- **Canonical ID:** `PAGE-PUB-19`
- **Purpose:** The core 4-step RFP wizard allowing healthcare executives to scope staffing specifications.
- **Hero Variant:** Interactive progress bar header with step indicator chips (1 to 4 + Review).
- **Sections:** 1. Organization Information, 2. Service Scope & Volume, 3. Procurement Parameters, 4. Tender Document Upload (15MB ceiling), 5. Review & Confirmation.
- **CTAs:** Primary: "Submit RFP Proposal to Triage Desk (FORM-PUB-03)".
- **Traceability:** Aligns with Phase 1 `FR-PUB-021` and `FORM-PUB-03`.

#### 20. PUB-20: Privacy Policy (`/privacy-policy`)
- **Canonical ID:** `PAGE-PUB-20`
- **Purpose:** Statutory corporate privacy disclosures, data protection principles, and configurable entity variables.
- **Hero Variant:** Formal legal document header with effective date and revision history badge.
- **Sections:** 1. Sticky Table of Contents, 2. Handling of Sensitive Healthcare Data, 3. Purpose of Processing, 4. Data Security & Quarantined Storage, 5. Cookie & Telemetry Disclosures, 6. Privacy Officer Contact Details.
- **CTAs:** Primary: "Contact Privacy Officer", Secondary: "Print / Download PDF".
- **Traceability:** Aligns with Phase 1 `FR-PUB-022`.

#### 21. PUB-21: Terms & Legal (`/terms`)
- **Canonical ID:** `PAGE-PUB-21`
- **Purpose:** Master commercial terms, website conditions, and intellectual property governance.
- **Hero Variant:** Formal commercial terms layout with sticky navigation.
- **Sections:** 1. Master Services Terms, 2. Website Terms of Use, 3. Intellectual Property Rights, 4. Limitation of Liability, 5. Configurable Governing Law and Jurisdiction Notice.
- **CTAs:** Primary: "Download Commercial Terms PDF", Secondary: "Contact Legal Counsel".
- **Traceability:** Aligns with Phase 1 `FR-PUB-022`.

#### 22. PUB-22: Error / 404 / 500 (`/error`)
- **Canonical ID:** `PAGE-PUB-22`
- **Purpose:** Accessible recovery state for non-existent routes or unexpected system faults.
- **Hero Variant:** Friendly pastel illustration card with empathetic copy ("Page Not Found").
- **Sections:** 1. Clear Error Status Code (404/500), 2. Diagnostic Correlation Incident ID (Zero stack trace leak), 3. Helpful Quick Links to Core Services, 4. Operations Helpdesk Contact.
- **CTAs:** Primary: "Return to Homepage", Secondary: "Contact Helpdesk".
- **Traceability:** Aligns with Phase 1 `FR-PUB-023`.
