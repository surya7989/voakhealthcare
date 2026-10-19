# Public Website Page-by-Page Specifications
## Comprehensive Functional Architecture for All 22 Public Pages

**Document Reference:** `docs/phase-01/02_PUBLIC_PAGE_BY_PAGE_SPECIFICATIONS.md`  
**Phase:** Phase 1 of 31 — Complete Functional Specification  
**Baseline Authority:** Approved Phase 0 Public Sitemap (`docs/phase-00/03_PUBLIC_WEBSITE_SITEMAP.md`), Approved Visual Design System, and Master Functional Requirements.

---

### Executive Overview & Specification Structure

This document provides the exhaustive, implementation-ready functional specification for every public-facing page across the Voak Health Care corporate platform. Each page specification adheres strictly to the standardized 19-point requirement rubric (Items A through S).

#### Standardization Rubric (A–S):
- **A. Page Identifier:** Unique reference code (`PAGE-PUB-01` through `PAGE-PUB-22`).
- **B. Route & URL Pattern:** Static path or dynamic slug pattern, canonical URL structure.
- **C. Page Purpose:** Primary commercial, operational, and organizational function.
- **D. Primary User Personas:** Target healthcare decision-makers (C-suite, Procurement, HR, Practice Managers).
- **E. Required Sections:** Visual and content hierarchy from header to footer.
- **F. Content Model:** Content elements, editorial structures, and data schemas.
- **G. Required Backend Data:** API endpoints, entities fetched, query parameters, cache directives.
- **H. User Interactions:** State transitions, hover behaviors, modal triggers, drawer interactions.
- **I. Navigation Paths:** Inbound referrals, outbound journeys, and breadcrumb structures.
- **J. Form Requirements:** Form fields, validations, CSRF/Turnstile protections (if applicable).
- **K. Empty / Loading / Error States:** Skeleton states, graceful fallbacks, and recovery triggers.
- **L. Responsive Behavior:** Breakpoint adaptations (Mobile < 640px, Tablet 640–1023px, Desktop >= 1024px).
- **M. Accessibility Expectations:** WCAG 2.2 AA compliance, ARIA attributes, semantic structure, focus rules.
- **N. SEO Requirements:** Title tags, meta descriptions, canonical headers, OpenGraph tags, JSON-LD schemas.
- **O. Security & Privacy Requirements:** Data sanitization, XSS mitigation, zero PII logging, non-clinical disclaimers.
- **P. Admin-Managed Fields:** Direct CMS fields editable via the administrative console.
- **Q. Exact Acceptance Criteria:** Binary testable criteria for feature sign-off.
- **R. Automated Test Scenarios:** E2E and component test assertions (Playwright / Vitest).
- **S. Owning Implementation Phase:** Specific target phase in the restored 32-phase roadmap.

---

### Page Specifications (PAGE-PUB-01 to PAGE-PUB-22)

```
Index of Public Pages:
01. Homepage (PAGE-PUB-01)
02. About Us (PAGE-PUB-02)
03. Services Overview (PAGE-PUB-03)
04. Individual Service Detail Page (PAGE-PUB-04)
05. Industries Overview (PAGE-PUB-05)
06. Individual Industry Detail Page (PAGE-PUB-06)
07. Why Choose Voak (PAGE-PUB-07)
08. How We Work (Operational Framework) (PAGE-PUB-08)
09. Quality & Compliance (PAGE-PUB-09)
10. Partners & Clients (PAGE-PUB-10)
11. Case Studies Index (PAGE-PUB-11)
12. Careers Portal (PAGE-PUB-12)
13. Job Details & Application Portal (PAGE-PUB-13)
14. Insights & Resources Hub (PAGE-PUB-14)
15. Individual Article View (PAGE-PUB-15)
16. Frequently Asked Questions (PAGE-PUB-16)
17. Contact Us (PAGE-PUB-17)
18. Partner With Us (PAGE-PUB-18)
19. Request a Proposal (RFP) (PAGE-PUB-19)
20. Privacy Policy (PAGE-PUB-20)
21. Terms of Service & Legal Notices (PAGE-PUB-21)
22. 404 Not Found & Error Boundaries (PAGE-PUB-22)
```

---

#### 01. Homepage
- **A. Page Identifier:** `PAGE-PUB-01`
- **B. Route & URL Pattern:** `/` (Canonical: `https://voakhealthcare.com.au/`)
- **C. Page Purpose:** Establish immediate executive credibility, articulate Voak's high-reliability B2B healthcare operational capabilities, present configurable service pillars and industry verticals, and drive high-intent RFP submissions and partnership inquiries.
- **D. Primary User Personas:** Healthcare Executives (CEOs, COOs), Hospital Procurement Directors, Clinical Practice Owners, Aged Care Facility Operators.
- **E. Required Sections:**
  1. Global Navigation Bar & Announcement Banner (configurable)
  2. Asymmetrical Hero Section with headline, value proposition narrative, primary CTA ("Request a Proposal"), secondary CTA ("Explore Services"), and executive proof ribbon
  3. Interactive Bento-Grid Services Showcase (6 configurable pillars)
  4. Industry Verticals Directory (Hospital, Aged Care, Allied Health, Primary Care, Diagnostics, Corporate)
  5. Operational Excellence Metric & Value Proposition Band
  6. Strategic Differentiation Matrix ("Why Choose Voak")
  7. 4-Stage Operational Delivery Framework
  8. Clinical Governance & Compliance Callout
  9. Verified Case Study Spotlight (or configurable fallback when unpublished)
  10. Thought Leadership & Latest Insights Preview
  11. Executive Conversion Banner (Dual CTA: RFP / Strategic Partnership)
  12. Global Corporate Footer with legal disclaimers and navigation links
- **F. Content Model:** Hero copy (headline, subhead, primary/secondary CTA labels/links), Bento grid cards (title, summary, icon, accent color, link slug), industry cards, governance badges, case study teaser, recent 3 insight articles, global footer data.
- **G. Required Backend Data:**
  - `GET /api/v1/public/homepage` (Cached in memory/file, TTL 300s): Returns hero config, published featured services (limit 6), published industries (limit 6), featured case study, latest published articles (limit 3).
- **H. User Interactions:** Hover lift on bento cards (`translateY(-4px)`), smooth scrolling to section anchors, mobile menu drawer toggle, interactive pillar cards expanding brief scope summaries on focus/hover.
- **I. Navigation Paths:**
  - Inbound: Direct landing, organic search root, external social/ad links.
  - Outbound: `/services`, `/services/:slug`, `/industries`, `/industries/:slug`, `/how-we-work`, `/request-proposal`, `/partner-with-us`, `/insights/:slug`.
- **J. Form Requirements:** None directly embedded on homepage (routes traffic to `/request-proposal` and `/contact`).
- **K. Empty / Loading / Error States:**
  - Loading: Animated skeleton cards reflecting the bento layout in `#E5E0F0` shimmer.
  - Error: Graceful fallback rendering static server-side cached HTML markup generated by the PHP front controller; toasts notification for API fetch retries.
  - Unverified Social Proof: If zero client logos or case studies are marked `is_published = 1`, the entire trust ribbon and case study spotlight collapse cleanly without leaving visual voids or broken image frames.
- **L. Responsive Behavior:**
  - Desktop (>= 1024px): 3-column asymmetrical bento grid; multi-column footer; sticky nav with inline menu.
  - Tablet (640–1023px): 2-column bento grid; 2-column footer; compressed nav with quick-contact icon.
  - Mobile (< 640px): 1-column stacked bento cards; full-screen overlay mobile navigation drawer; sticky bottom quick-action bar for "Request Proposal".
- **M. Accessibility Expectations:**
  - Semantic `<h1>` reserved strictly for the primary hero value proposition.
  - ARIA landmarks: `<header role="banner">`, `<main id="main-content">`, `<nav aria-label="Main Navigation">`, `<footer role="contentinfo">`.
  - Contrast: Text `#252B5E` on `#F2F0F8` canvas (11.73:1); buttons `#665CE9` with `#FFFFFF` text (4.82:1).
  - Skip to main content link (`href="#main-content"`) visible on keyboard focus.
- **N. SEO Requirements:**
  - Title: `Voak Health Care | Enterprise B2B Healthcare Operations & Workforce Solutions`
  - Meta Description: `Voak Health Care partners with hospitals, clinics, and aged care providers across Australia to deliver compliant clinical staffing, healthcare facility management, and operational consulting.`
  - Canonical: `https://voakhealthcare.com.au/`
  - JSON-LD: Schema.org `Organization` with name, URL, logo, contact points, and `MedicalBusiness` non-clinical B2B profile.
- **O. Security & Privacy Requirements:** Zero dynamic user inputs; all CMS strings escaped via `htmlspecialchars()` during PHP pre-rendering and sanitized by React virtual DOM; strict CSP header applied.
- **P. Admin-Managed Fields:** Hero headline, hero subtext, primary CTA destination, secondary CTA destination, featured service IDs, featured industry IDs, featured case study ID, active announcement banner text and toggle.
- **Q. Exact Acceptance Criteria:**
  1. Renders within 1.2s First Contentful Paint (FCP) on simulated 4G.
  2. Displays all 6 configured service pillars with valid routes.
  3. Seamlessly collapses social proof and case study blocks if unpublished in CMS.
  4. Fully navigable via keyboard without focus trap.
  5. Matches approved soft lavender (`#F2F0F8`) and pastel bento visual design.
- **R. Automated Test Scenarios (TEST-PUB-001):**
  - `E2E-HOME-001`: Assert header, hero, bento grid, and footer render with valid text.
  - `E2E-HOME-002`: Click "Request a Proposal" button and verify navigation to `/request-proposal`.
  - `E2E-HOME-003`: Verify mobile hamburger opens drawer and keyboard navigation cycles through links.
  - `E2E-HOME-004`: Simulate API 500 error; assert hybrid SSR fallback displays complete semantic HTML.
- **S. Owning Implementation Phase:** Phase 20 (Public Website: Homepage & Interactive Bento Grid).

---

#### 02. About Us
- **A. Page Identifier:** `PAGE-PUB-02`
- **B. Route & URL Pattern:** `/about` (Canonical: `https://voakhealthcare.com.au/about`)
- **C. Page Purpose:** Articulate Voak Health Care's corporate mission, vision, clinical governance ethos, Australian operational capability, and executive leadership structure to instill procurement confidence.
- **D. Primary User Personas:** Hospital Board Members, Healthcare Procurement Committees, Prospective Joint-Venture Partners, Institutional Investors.
- **E. Required Sections:**
  1. Breadcrumb navigation (`Home > About Us`)
  2. Editorial Page Header with serif headline and purpose narrative
  3. Mission, Vision, and Values Bento Matrix (Integrity, Clinical Rigor, Operational Resilience, Transparent Partnerships)
  4. Corporate Governance & Quality Commitment Framework
  5. Operational Capability & National Footprint Map (Configurable geographical scope)
  6. Executive Leadership & Advisory Board Profiles (Configurable profiles with modal bios)
  7. Corporate History & Milestone Timeline (Configurable roadmap)
  8. Non-Clinical Care Operational Boundary Notice (Explicit B2B disclaimer)
  9. CTA Section: "Partner With Our Leadership Team"
- **F. Content Model:** Header copy, mission statement, 4 corporate value blocks (title, description, icon), governance narrative, leadership array (name, role, credentials, bio, image_url, linkedin_url), milestone array (year, title, description).
- **G. Required Backend Data:** `GET /api/v1/public/about` — returns about page content blocks, published leadership profiles, and corporate values.
- **H. User Interactions:** Leadership card click opens accessible modal dialog with comprehensive executive bio; tabbed switching between Mission, Governance, and Values.
- **I. Navigation Paths:**
  - Inbound: Header navigation "About", footer link.
  - Outbound: `/quality-compliance`, `/how-we-work`, `/partner-with-us`, `/careers`.
- **J. Form Requirements:** None.
- **K. Empty / Loading / Error States:**
  - If leadership profiles are unconfirmed/unpublished, the section displays the organizational governance framework rather than blank cards.
  - Skeleton placeholders for leadership cards in loading state.
- **L. Responsive Behavior:** Leadership grid flows from 3 columns (desktop) to 2 columns (tablet) to 1 column (mobile). Modals open full-width on mobile with sticky close button.
- **M. Accessibility Expectations:** Leadership bios opened via dialog implement `role="dialog"`, `aria-modal="true"`, focus trapped within dialog, Escape key dismisses and returns focus to triggering card.
- **N. SEO Requirements:**
  - Title: `About Voak Health Care | B2B Healthcare Governance & Operational Leadership`
  - Meta Description: `Discover Voak Health Care's commitment to clinical governance, operational reliability, and institutional healthcare partnership across Australia.`
  - JSON-LD: Schema.org `AboutPage` with `mainEntity` set to `Organization`.
- **O. Security & Privacy Requirements:** No personal email addresses exposed in DOM; LinkedIn links set with `rel="noopener noreferrer"`.
- **P. Admin-Managed Fields:** About headline, mission statement, vision statement, values list, leadership team member CRUD, milestone timeline items.
- **Q. Exact Acceptance Criteria:**
  1. All executive bios open and close cleanly with keyboard and screen reader support.
  2. Displays non-clinical service disclaimer prominently in compliance section.
  3. Gracefully hides leadership section if zero members are approved for publication.
- **R. Automated Test Scenarios (TEST-PUB-002):**
  - `E2E-ABOUT-001`: Verify leadership modal triggers, traps focus, and closes on Escape.
  - `E2E-ABOUT-002`: Verify breadcrumbs navigate accurately back to `/`.
- **S. Owning Implementation Phase:** Phase 21 (Public Website: Corporate, Governance & About Pages).

---

#### 03. Services Overview
- **A. Page Identifier:** `PAGE-PUB-03`
- **B. Route & URL Pattern:** `/services` (Canonical: `https://voakhealthcare.com.au/services`)
- **C. Page Purpose:** Provide an exhaustive, filterable index of Voak Health Care's 6 core service pillars, enabling healthcare decision-makers to rapidly discover relevant operational solutions.
- **D. Primary User Personas:** Hospital Operations Managers, Clinical Directors, Procurement Officers, Nursing Home Administrators.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Services`)
  2. Section Hero with headline and categorization overview
  3. Category Filter Bar (All, Clinical Workforce, Facility Operations, Health Admin, Consulting & Advisory, Digital Infrastructure, Compliance & Auditing)
  4. Services Grid (Dynamic service cards displaying title, category badge, excerpt, key deliverables summary, and "View Service Details" action)
  5. Service Delivery SLA & Governance Pledge Banner
  6. Configurable Proposal Prompt CTA Box
- **F. Content Model:** Filter tabs, service array: `id`, `slug`, `title`, `category_code`, `excerpt`, `features_list` (JSON array of 3 bullet points), `icon_key`, `display_order`, `is_published`.
- **G. Required Backend Data:** `GET /api/v1/public/services` — returns array of active, published services sorted by `display_order` ASC.
- **H. User Interactions:** Client-side instantaneous category filtering (URL updated with query parameter `?category=workforce`); card hover subtle elevation and icon color shift.
- **I. Navigation Paths:**
  - Inbound: Header "Services" dropdown, homepage bento cards.
  - Outbound: `/services/:slug`, `/request-proposal?service=:id`.
- **J. Form Requirements:** None.
- **K. Empty / Loading / Error States:**
  - Empty: When a filter matches 0 services, displays: "No services currently listed under this category. Contact our advisory team for bespoke requirements." with a button to "Reset Filters".
  - Loading: 6-card shimmer skeleton grid.
- **L. Responsive Behavior:** Filter bar switches to a horizontally scrollable pill-list or native select dropdown on mobile; cards stack vertically on `< 640px`.
- **M. Accessibility Expectations:** Filter buttons implement `aria-pressed="true|false"`; live region (`aria-live="polite"`) announces the number of filtered services visible (e.g., "Showing 4 services").
- **N. SEO Requirements:**
  - Title: `Healthcare Services & Operational Solutions | Voak Health Care`
  - Meta Description: `Explore Voak Health Care's enterprise services: healthcare workforce staffing, clinical facility management, regulatory compliance, and health administration.`
  - JSON-LD: Schema.org `ItemList` containing `Service` references.
- **O. Security & Privacy Requirements:** URL query parameters sanitized before state evaluation.
- **P. Admin-Managed Fields:** Services category definitions, service card order, service publish toggles, hero introductory text.
- **Q. Exact Acceptance Criteria:**
  1. Renders all published services from database.
  2. Category filtering updates URL query without full page reload.
  3. Clicking card navigates directly to `/services/:slug`.
  4. Each card includes a direct deep link to `/request-proposal?service=:id`.
- **R. Automated Test Scenarios (TEST-PUB-003):**
  - `E2E-SRV-001`: Filter by category and assert only matching service cards remain visible.
  - `E2E-SRV-002`: Click service card link and verify transition to `/services/:slug`.
- **S. Owning Implementation Phase:** Phase 22 (Public Website: Services & Industries Catalog Pages).

---

#### 04. Individual Service Detail Page
- **A. Page Identifier:** `PAGE-PUB-04`
- **B. Route & URL Pattern:** `/services/:slug` (Dynamic slug, e.g., `/services/clinical-workforce-solutions`)
- **C. Page Purpose:** Provide an authoritative, in-depth architectural breakdown of a specific service pillar, including operational scope, compliance certifications, SLA standards, case references, and contextual inquiry capture.
- **D. Primary User Personas:** Department Heads, Chief Nursing Officers, Clinical Procurement Evaluators.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Services > :service_title`)
  2. Service Detail Hero with category badge, serif title, summary, and primary "Request Scope Proposal" button
  3. Scope of Service & Core Deliverables (Structured checklist)
  4. Operational Methodology & Workflow Integration
  5. Compliance, Credentialing & Governance Standards
  6. Service Level Agreement (SLA) & Performance Metrics (Response times, fill rates, audit intervals)
  7. Applicable Industries (Cross-links to relevant `/industries/:slug`)
  8. Contextual RFP Prompt Card pre-populated with this service
  9. Related Services Carousel / Grid
- **F. Content Model:** `slug`, `title`, `category_code`, `headline`, `overview_markdown`, `deliverables` (JSON array of strings), `sla_framework` (JSON array: title, metric, description), `governance_notes`, `seo_meta_title`, `seo_meta_description`, `is_published`.
- **G. Required Backend Data:** `GET /api/v1/public/services/:slug` — returns full service detail payload, related industries, and 2 sibling services.
- **H. User Interactions:** Accordion toggle for deep SLA breakdowns; sticky sidebar CTA on desktop scrolling with the user; clicking "Request Proposal for this Service" navigates to `/request-proposal?service_id=:id`.
- **I. Navigation Paths:**
  - Inbound: `/services`, homepage bento grid, cross-links from industry pages.
  - Outbound: `/request-proposal?service_id=:id`, `/contact`, `/industries/:slug`.
- **J. Form Requirements:** None embedded directly (CTA routes to RFP form with pre-filled context).
- **K. Empty / Loading / Error States:**
  - 404 Behavior: If `:slug` does not exist or has `is_published = 0`, PHP front controller emits authentic `HTTP 404 Not Found` header and renders the branded `PAGE-PUB-22` error boundary.
  - Loading: Full-page layout skeleton with hero and content blocks.
- **L. Responsive Behavior:** Desktop layout features 2/3 content width and 1/3 sticky RFP summary card; mobile layout stacks the summary card below the deliverables.
- **M. Accessibility Expectations:** Headings strictly structured (`h1` for service title, `h2` for deliverables, SLAs, governance); expandable accordions implement `aria-expanded` and `aria-controls`.
- **N. SEO Requirements:**
  - Title: `:service_title | Healthcare Operations | Voak Health Care`
  - Meta Description: Dynamic from `seo_meta_description` or auto-truncated excerpt.
  - Canonical: `https://voakhealthcare.com.au/services/:slug`
  - JSON-LD: Schema.org `Service` schema with `provider`, `serviceType`, and `areaServed`.
- **O. Security & Privacy Requirements:** Markdown content sanitized via DOMPurify before rendering to prevent stored XSS.
- **P. Admin-Managed Fields:** Full service CRUD: title, slug, summary, markdown content, deliverables list, SLA specifications, related industries, publish status, meta tags.
- **Q. Exact Acceptance Criteria:**
  1. Emits authentic HTTP 404 if invalid slug requested.
  2. Pre-populates service ID when following CTA to `/request-proposal`.
  3. Pre-renders complete semantic HTML via PHP front controller for crawler discovery.
- **R. Automated Test Scenarios (TEST-PUB-004):**
  - `E2E-SRVDET-001`: Request valid slug `/services/clinical-workforce-solutions`; assert 200 OK and correct title.
  - `E2E-SRVDET-002`: Request `/services/invalid-fake-slug`; assert 404 Not Found response code.
  - `E2E-SRVDET-003`: Verify "Request Scope Proposal" button passes `?service_id` query param.
- **S. Owning Implementation Phase:** Phase 22 (Public Website: Services & Industries Catalog Pages).

---

#### 05. Industries Overview
- **A. Page Identifier:** `PAGE-PUB-05`
- **B. Route & URL Pattern:** `/industries` (Canonical: `https://voakhealthcare.com.au/industries`)
- **C. Page Purpose:** Categorize and display the institutional healthcare sectors served by Voak Health Care, demonstrating sector-specific regulatory understanding and operational fit.
- **D. Primary User Personas:** Public & Private Hospital Executives, Aged Care Providers, General Practice Networks, Allied Health Consortia.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Industries`)
  2. Editorial Section Header
  3. Industry Sector Bento Grid (Hospitals & Health Networks, Residential Aged Care, Primary & Community Care, Diagnostic & Pathology Centers, Specialist Medical Clinics, Corporate Healthcare Facilities)
  4. Cross-Sector Operational Capabilities Matrix
  5. Regulatory Compliance & Accreditation Standards Alignment
  6. Strategic Consultation CTA Banner
- **F. Content Model:** Sector array: `id`, `slug`, `name`, `headline`, `summary`, `challenges_list` (JSON array), `solutions_list` (JSON array), `icon_key`, `is_published`.
- **G. Required Backend Data:** `GET /api/v1/public/industries` — returns active sectors ordered by `display_order`.
- **H. User Interactions:** Hover card interaction displaying sector challenge summary and transition arrow; quick link to schedule discovery.
- **I. Navigation Paths:**
  - Inbound: Header "Industries" navigation, homepage industry section.
  - Outbound: `/industries/:slug`, `/partner-with-us`, `/request-proposal`.
- **J. Form Requirements:** None.
- **K. Empty / Loading / Error States:**
  - Loading: 6-card shimmer skeleton.
  - Error: Inline retry banner with friendly corporate message.
- **L. Responsive Behavior:** Grid reflows from 3-column (desktop) to 2-column (tablet) to 1-column (mobile).
- **M. Accessibility Expectations:** Card titles marked as `<h3>` within semantic `<article>` tags; interactive links have descriptive `aria-label="Explore Voak solutions for Hospitals and Health Networks"`.
- **N. SEO Requirements:**
  - Title: `Healthcare Industries & Sectors Served | Voak Health Care`
  - Meta Description: `Voak Health Care delivers tailored operational and workforce solutions across Australian hospitals, aged care, primary clinics, and medical centers.`
  - JSON-LD: Schema.org `CollectionPage` referencing healthcare industries.
- **O. Security & Privacy Requirements:** Standard sanitized content pipelines.
- **P. Admin-Managed Fields:** Industry sector CRUD: title, slug, summary, icon, display order, publish toggle.
- **Q. Exact Acceptance Criteria:**
  1. Displays all published industries with valid links to detail pages.
  2. Conforms to soft lavender and pastel accent color system.
- **R. Automated Test Scenarios (TEST-PUB-005):**
  - `E2E-IND-001`: Verify all 6 approved industry sectors render with correct headlines and routes.
- **S. Owning Implementation Phase:** Phase 22 (Public Website: Services & Industries Catalog Pages).

---

#### 06. Individual Industry Detail Page
- **A. Page Identifier:** `PAGE-PUB-06`
- **B. Route & URL Pattern:** `/industries/:slug` (Dynamic slug, e.g., `/industries/residential-aged-care`)
- **C. Page Purpose:** Provide deep, industry-tailored analysis of operational bottlenecks, regulatory standards (e.g., Aged Care Quality Standards, NSQHS), and Voak's turnkey service integration.
- **D. Primary User Personas:** Sector-specific Operations Directors, Facility Managers, Quality Managers.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Industries > :industry_name`)
  2. Sector Hero Section with specific challenges overview
  3. Industry Regulatory Landscape & Compliance Frameworks
  4. Voak Solutions Architecture for this Sector
  5. Recommended Service Packages (Cross-links to `/services/:slug`)
  6. Operational Transformation Workflow (How we engage with facilities in this sector)
  7. Industry Inquiry Callout Box ("Consult with an Industry Specialist")
- **F. Content Model:** `slug`, `name`, `headline`, `overview_markdown`, `regulatory_frameworks` (JSON array: title, authority, compliance_notes), `tailored_solutions` (JSON array: title, description, service_slug), `is_published`.
- **G. Required Backend Data:** `GET /api/v1/public/industries/:slug` — returns sector detail and mapped services.
- **H. User Interactions:** Tabbed view between "Operational Challenges" and "Voak Interventions"; quick consultation button triggering pre-filtered contact route.
- **I. Navigation Paths:**
  - Inbound: `/industries`, homepage industry grid.
  - Outbound: `/services/:slug`, `/request-proposal?industry=:id`, `/partner-with-us`.
- **J. Form Requirements:** None embedded directly.
- **K. Empty / Loading / Error States:** Authentic 404 emitted for unpublished or invalid industry slugs.
- **L. Responsive Behavior:** Side-by-side challenge/solution matrix stacks sequentially on mobile devices.
- **M. Accessibility Expectations:** Tables or lists of regulatory standards properly labeled with `scope="col"` and accessible ARIA descriptions.
- **N. SEO Requirements:**
  - Title: `:industry_name Operations & Workforce Solutions | Voak Health Care`
  - Meta Description: Tailored meta description focusing on sector compliance and staffing models.
  - Canonical: `https://voakhealthcare.com.au/industries/:slug`
- **O. Security & Privacy Requirements:** Content sanitization via DOMPurify.
- **P. Admin-Managed Fields:** Industry title, slug, hero copy, challenges, solutions, regulatory mappings, publish status.
- **Q. Exact Acceptance Criteria:**
  1. Returns authentic 404 for invalid slug.
  2. Properly cross-links to recommended service detail pages.
- **R. Automated Test Scenarios (TEST-PUB-006):**
  - `E2E-INDDET-001`: Request valid industry slug; verify regulatory compliance section renders.
  - `E2E-INDDET-002`: Request non-existent industry slug; verify 404 error page is returned.
- **S. Owning Implementation Phase:** Phase 22 (Public Website: Services & Industries Catalog Pages).

---

#### 07. Why Choose Voak
- **A. Page Identifier:** `PAGE-PUB-07`
- **B. Route & URL Pattern:** `/why-choose-voak` (Canonical: `https://voakhealthcare.com.au/why-choose-voak`)
- **C. Page Purpose:** Provide objective, procurement-focused justification for selecting Voak Health Care over conventional staffing agencies, fragmented facility vendors, or internal operational overheads.
- **D. Primary User Personas:** CFOs, Procurement Committees, Board Governance Members.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Why Choose Voak`)
  2. Strategic Differentiation Header
  3. The Voak Advantage Bento Matrix (4 Pillars: Clinical Rigor, Operational Speed, Transparent SLA Pricing, Enterprise Governance)
  4. Comparative Procurement Matrix (Voak vs. Traditional Staffing Agencies vs. In-House Overhead)
  5. Risk Mitigation & Continuity Architecture
  6. Quality Assurance & Independent Audit Protocols
  7. Direct Executive Action Banner ("Request an Operational Feasibility Review")
- **F. Content Model:** Differentiation matrix headers, comparative table rows (criteria, Voak approach, agency approach, in-house approach), pillar cards.
- **G. Required Backend Data:** `GET /api/v1/public/pages/why-choose-voak` — returns structured page content.
- **H. User Interactions:** Interactive table highlighting Voak column on row hover; expandable details for risk mitigation protocols.
- **I. Navigation Paths:**
  - Inbound: Header "About" dropdown, footer, homepage differentiation preview.
  - Outbound: `/how-we-work`, `/quality-compliance`, `/request-proposal`.
- **J. Form Requirements:** None.
- **K. Empty / Loading / Error States:** Shimmer skeleton for comparative table.
- **L. Responsive Behavior:** Comparison table converts to a swipeable card carousel or stacked comparison cards on mobile screens (`< 768px`) to prevent horizontal clipping.
- **M. Accessibility Expectations:** Comparative table contains complete `<caption>`, `<th scope="col">`, `<th scope="row">` headers and accessible contrast on highlighted cells.
- **N. SEO Requirements:**
  - Title: `Why Choose Voak | Enterprise Healthcare Operational Excellence`
  - Meta Description: `Compare Voak Health Care's verified clinical governance, SLA transparency, and workforce reliability against conventional agency models.`
  - JSON-LD: Schema.org `WebPage` with `Table` schema.
- **O. Security & Privacy Requirements:** Standard sanitized content pipeline.
- **P. Admin-Managed Fields:** Headline, subheadline, comparative table criteria and descriptions, value pillar cards.
- **Q. Exact Acceptance Criteria:**
  1. Comparative table is fully legible and accessible across all device breakpoints.
  2. No unverified statistical claims (e.g., no fabricated "99.8% satisfaction" claims).
- **R. Automated Test Scenarios (TEST-PUB-007):**
  - `E2E-WHY-001`: Verify table renders all comparative rows and collapses gracefully on mobile viewport (375px).
- **S. Owning Implementation Phase:** Phase 21 (Public Website: Corporate, Governance & About Pages).

---

#### 08. How We Work (Operational Framework)
- **A. Page Identifier:** `PAGE-PUB-08`
- **B. Route & URL Pattern:** `/how-we-work` (Canonical: `https://voakhealthcare.com.au/how-we-work`)
- **C. Page Purpose:** Demystify the engagement lifecycle by detailing the 4-stage operational roadmap: Audit, Architecture, Deployment, and Continuous Governance.
- **D. Primary User Personas:** COOs, Project Managers, Transition Directors, Clinical Operations Leads.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > How We Work`)
  2. Methodology Header
  3. Visual 4-Stage Operational Roadmap:
     - Stage 1: Diagnostic Assessment & Operational Audit
     - Stage 2: Bespoke Solution Architecture & SLA Design
     - Stage 3: Phased Integration & Credentialed Deployment
     - Stage 4: Continuous Quality Governance & Performance Telemetry
  4. Governance Milestones & Sign-Off Checkpoints
  5. Client Onboarding Timeline & Transition Expectations
  6. Escalation & Account Management Architecture
  7. CTA: "Initiate Stage 1 Diagnostic Consultation"
- **F. Content Model:** 4 stage items: `stage_number`, `title`, `duration_estimate`, `objectives` (array), `deliverables` (array), `governance_gates` (array).
- **G. Required Backend Data:** `GET /api/v1/public/pages/how-we-work` — returns roadmap stages and timeline copy.
- **H. User Interactions:** Stepper navigation allowing user to click stages to expand detailed deliverables; animated progress indicator tracking scroll position through the 4 stages.
- **I. Navigation Paths:**
  - Inbound: Header, homepage roadmap section, `/why-choose-voak`.
  - Outbound: `/request-proposal`, `/quality-compliance`, `/contact`.
- **J. Form Requirements:** None.
- **K. Empty / Loading / Error States:** Shimmer skeleton for timeline stages.
- **L. Responsive Behavior:** Timeline renders as a horizontal connected track on desktop (>= 1024px) and transitions to an accessible vertical timeline on tablet/mobile (< 1024px).
- **M. Accessibility Expectations:** Timeline steps structured as an ordered list (`<ol>`); current active step marked with `aria-current="step"`; animations obey `prefers-reduced-motion`.
- **N. SEO Requirements:**
  - Title: `How We Work | 4-Stage Healthcare Operational Framework | Voak Health Care`
  - Meta Description: `Explore Voak Health Care's structured 4-stage framework for healthcare workforce and operational integration, from diagnostic audit to continuous governance.`
  - JSON-LD: Schema.org `HowTo` schema outlining the 4 stages.
- **O. Security & Privacy Requirements:** Standard sanitized content pipeline.
- **P. Admin-Managed Fields:** Stage titles, stage duration copy, bulleted deliverables, governance gates copy.
- **Q. Exact Acceptance Criteria:**
  1. Vertical timeline renders correctly without horizontal scrollbars on mobile viewports.
  2. All step deliverables expand and collapse with keyboard Enter/Space.
- **R. Automated Test Scenarios (TEST-PUB-008):**
  - `E2E-HWW-001`: Verify 4 stages render in sequence and keyboard focus activates stage detail drawers.
- **S. Owning Implementation Phase:** Phase 21 (Public Website: Corporate, Governance & About Pages).

---

#### 09. Quality & Compliance
- **A. Page Identifier:** `PAGE-PUB-09`
- **B. Route & URL Pattern:** `/quality-compliance` (Canonical: `https://voakhealthcare.com.au/quality-compliance`)
- **C. Page Purpose:** Provide rigorous documentation of Voak Health Care's clinical credentialing protocols, regulatory compliance mechanisms, safety audits, and data privacy safeguards.
- **D. Primary User Personas:** Clinical Governance Committees, Compliance Directors, Hospital Legal Counsel.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Quality & Compliance`)
  2. Compliance Charter Header
  3. Clinical Credentialing Protocol (AHPRA verification, police checks, working with children, vaccination status, reference audits)
  4. Standards Alignment (NSQHS Standards, Aged Care Quality Standards, NDIS Practice Standards alignment)
  5. Clinical Incident Management & Escalation Framework
  6. Data Privacy & Health Information Governance (Non-clinical operational boundaries)
  7. Continuous Improvement & Clinical Audit Schedule
  8. Compliance Inquiry & Incident Reporting Channel Link
- **F. Content Model:** Credentialing steps array, regulatory alignment badges, audit schedule table, incident escalation flowchart items.
- **G. Required Backend Data:** `GET /api/v1/public/pages/quality-compliance` — returns compliance charter and verified standards.
- **H. User Interactions:** Expandable accordions for credentialing checklists; downloadable compliance charter PDF link (authenticated stream or public asset).
- **I. Navigation Paths:**
  - Inbound: Header "About" menu, footer compliance link.
  - Outbound: `/about`, `/contact?topic=compliance`, `/privacy-policy`.
- **J. Form Requirements:** None embedded directly (links to contact form with topic pre-selected).
- **K. Empty / Loading / Error States:** Skeleton loading for compliance accordions.
- **L. Responsive Behavior:** Flowcharts convert to linear numbered lists on mobile viewports.
- **M. Accessibility Expectations:** Accordions fully compliant with WAI-ARIA 1.2 Accordion pattern; flowchart diagrams accompanied by full text descriptions for screen readers.
- **N. SEO Requirements:**
  - Title: `Clinical Quality & Regulatory Compliance Standards | Voak Health Care`
  - Meta Description: `Read Voak Health Care's clinical governance, AHPRA credentialing standards, and compliance framework aligning with Australian national healthcare standards.`
  - JSON-LD: Schema.org `WebPage` with clinical governance metadata.
- **O. Security & Privacy Requirements:** Explicit statement regarding non-collection of individual patient medical histories; compliance contact email protected from scrapers.
- **P. Admin-Managed Fields:** Credentialing criteria list, regulatory alignment descriptions, compliance download document attachments.
- **Q. Exact Acceptance Criteria:**
  1. Credentialing checklist clearly details verification procedures without claiming unverified third-party certifications.
  2. All accordions pass automated accessibility audits.
- **R. Automated Test Scenarios (TEST-PUB-009):**
  - `E2E-QUAL-001`: Verify compliance accordions expand on click and keyboard Enter.
- **S. Owning Implementation Phase:** Phase 21 (Public Website: Corporate, Governance & About Pages).

---

#### 10. Partners & Clients
- **A. Page Identifier:** `PAGE-PUB-10`
- **B. Route & URL Pattern:** `/partners-clients` (Canonical: `https://voakhealthcare.com.au/partners-clients`)
- **C. Page Purpose:** Highlight approved institutional relationships, clinical education affiliations, and healthcare network collaborations while upholding strict content accuracy (no unverified logos).
- **D. Primary User Personas:** Prospective Enterprise Partners, Healthcare Consortia, Subcontracting Vendors.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Partners & Clients`)
  2. Collaboration Header
  3. Strategic Partnership Models (Health System Alliances, Academic Affiliations, Technology Partners)
  4. Verified Partner Directory (Configurable logo grid—strictly verified assets only)
  5. Institutional Testimonial Carousel (Rendered only if client quotes are formally approved)
  6. Strategic Partnership Intake CTA ("Explore Institutional Collaboration")
- **F. Content Model:** Partnership tier categories, partner entity array: `id`, `name`, `logo_url`, `partnership_type`, `description`, `is_published`. Testimonial array: `author_name`, `author_title`, `organization`, `quote`, `verified_release_ref`.
- **G. Required Backend Data:** `GET /api/v1/public/partners` — returns published partner entities and approved testimonials.
- **H. User Interactions:** Partner card hover reveals collaboration description; testimonial slider with pause/play controls.
- **I. Navigation Paths:**
  - Inbound: Header, footer.
  - Outbound: `/partner-with-us`, `/case-studies`.
- **J. Form Requirements:** None.
- **K. Empty / Loading / Error States:**
  - Unverified Fallback: If zero partner logos or testimonials are marked `is_published = 1`, the directory and slider sections are cleanly omitted; the page displays the "Partnership Models" framework and direct CTA to `/partner-with-us`.
- **L. Responsive Behavior:** Logo grid flows from 4 columns (desktop) to 2 columns (tablet/mobile).
- **M. Accessibility Expectations:** Testimonial slider includes pause button, `aria-roledescription="carousel"`, live announcements on slide change; partner logos include meaningful `alt` text (e.g., `alt="St. Jude Health Network - Strategic Partner"`).
- **N. SEO Requirements:**
  - Title: `Partners & Institutional Collaborations | Voak Health Care`
  - Meta Description: `Discover Voak Health Care's strategic partnerships with Australian healthcare providers, clinical networks, and health systems.`
- **O. Security & Privacy Requirements:** All external partner links configured with `rel="noopener noreferrer"`.
- **P. Admin-Managed Fields:** Partner logo CRUD, testimonial quote CRUD with mandatory verified release document reference.
- **Q. Exact Acceptance Criteria:**
  1. Never renders placeholder partner logos or unconfirmed client names.
  2. Gracefully falls back to partnership framework when no clients are published.
- **R. Automated Test Scenarios (TEST-PUB-010):**
  - `E2E-PART-001`: Assert zero broken image frames when partners database is empty.
  - `E2E-PART-002`: Assert partner logos render with non-empty `alt` attributes when populated.
- **S. Owning Implementation Phase:** Phase 24 (Public Website: Remaining Public Pages & Legal Views).

---

#### 11. Case Studies Index
- **A. Page Identifier:** `PAGE-PUB-11`
- **B. Route & URL Pattern:** `/case-studies` (Canonical: `https://voakhealthcare.com.au/case-studies`)
- **C. Page Purpose:** Present evidence-based, Problem-Intervention-Outcome operational case studies demonstrating Voak's measurable impact on workforce stability, compliance, and facility efficiency.
- **D. Primary User Personas:** Healthcare Decision-Makers evaluating procurement ROI and risk.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Case Studies`)
  2. Case Studies Header
  3. Sector & Service Filter Tabs (All, Hospitals, Aged Care, Workforce, Operations)
  4. Case Studies Grid (Cards with facility type, intervention summary, key verified metric, read case study link)
  5. Operational ROI Consultation CTA Card
- **F. Content Model:** Case study array: `id`, `slug`, `title`, `client_sector`, `challenge_summary`, `intervention_summary`, `outcome_metrics` (JSON array: metric, label), `featured_image_url`, `is_published`.
- **G. Required Backend Data:** `GET /api/v1/public/case-studies` — returns published case studies.
- **H. User Interactions:** Filter by sector; card hover zooms thumbnail slightly; clicking card navigates to `/case-studies/:slug`.
- **I. Navigation Paths:**
  - Inbound: Header "Insights", homepage case study spotlight.
  - Outbound: `/case-studies/:slug`, `/request-proposal`.
- **J. Form Requirements:** None.
- **K. Empty / Loading / Error States:**
  - Empty: If zero case studies are published, displays: "Detailed operational case studies are currently being finalized for publication. Contact our advisory team for anonymized reference briefs." with CTA to `/contact`.
- **L. Responsive Behavior:** Cards stack vertically on `< 768px`.
- **M. Accessibility Expectations:** Cards wrapped in `<article>` tags; outcomes presented as accessible key-value lists.
- **N. SEO Requirements:**
  - Title: `Healthcare Operational Case Studies | Voak Health Care`
  - Meta Description: `Read real-world case studies demonstrating Voak Health Care's impact on hospital workforce stability, aged care compliance, and clinical facility efficiency.`
- **O. Security & Privacy Requirements:** Client facility names anonymized where non-disclosure agreements apply.
- **P. Admin-Managed Fields:** Case study CRUD: title, slug, sector, challenge, intervention, outcome metrics, publish toggle.
- **Q. Exact Acceptance Criteria:**
  1. Only displays case studies with `is_published = 1`.
  2. Displays empty state message cleanly without visual breakage.
- **R. Automated Test Scenarios (TEST-PUB-011):**
  - `E2E-CS-001`: Filter case studies by sector; verify grid updates.
- **S. Owning Implementation Phase:** Phase 24 (Public Website: Remaining Public Pages & Legal Views).

---

#### 12. Careers Portal
- **A. Page Identifier:** `PAGE-PUB-12`
- **B. Route & URL Pattern:** `/careers` (Canonical: `https://voakhealthcare.com.au/careers`)
- **C. Page Purpose:** Attract high-caliber clinical, administrative, and corporate healthcare professionals to join Voak's workforce network and corporate headquarters.
- **D. Primary User Personas:** Registered Nurses, Clinical Specialists, Practice Managers, Healthcare Administrators, Corporate Operations Specialists.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Careers`)
  2. Employer Value Proposition Hero ("Shape the Future of Healthcare Operations")
  3. Culture & Professional Development Bento Matrix (Workplace flexibility, clinical governance support, continuous CPD training, competitive remuneration)
  4. Job Search & Filtering Console (Search keyword, Department filter: Clinical, Operations, Corporate; Employment Type filter: Full-time, Part-time, Casual, Contract; Location filter: State/City)
  5. Live Openings Directory (Job cards: title, department, location, employment type, closing date, "Apply Now" button)
  6. Talent Network Expression of Interest Callout (For candidates with general inquiries)
- **F. Content Model:** EVP copy, culture pillars, job array: `id`, `slug`, `title`, `department`, `employment_type`, `location`, `closing_date`, `is_active`.
- **G. Required Backend Data:** `GET /api/v1/public/jobs` (Query params: `department`, `type`, `q`) — returns active job listings.
- **H. User Interactions:** Real-time search query input debounced at 300ms; filter dropdown selections updating list dynamically.
- **I. Navigation Paths:**
  - Inbound: Header, footer.
  - Outbound: `/careers/:slug`, `/contact?topic=careers`.
- **J. Form Requirements:** Quick Talent Expression of Interest modal (Name, Email, Discipline, Resume upload).
- **K. Empty / Loading / Error States:**
  - Empty: When no jobs match filter, displays: "No vacancies currently match your search criteria. Submit your details to our Talent Network to be considered for upcoming roles."
- **L. Responsive Behavior:** Filters switch from inline flex-wrap (desktop) to stacked inputs (mobile).
- **M. Accessibility Expectations:** Search input has associated `<label for="job-search">`; filter changes announce count via `aria-live="polite"`.
- **N. SEO Requirements:**
  - Title: `Careers in Healthcare Operations & Clinical Staffing | Voak Health Care`
  - Meta Description: `Explore rewarding clinical, operational, and corporate healthcare careers at Voak Health Care across Australia.`
- **O. Security & Privacy Requirements:** Candidate data collected via Talent Network adheres to strict recruitment confidentiality.
- **P. Admin-Managed Fields:** EVP content, culture blocks, job posting CRUD.
- **Q. Exact Acceptance Criteria:**
  1. Only active jobs past the current date are displayed.
  2. Search filtering operates smoothly without full-page refreshes.
- **R. Automated Test Scenarios (TEST-PUB-012):**
  - `E2E-CAR-001`: Search for "Clinical"; verify only clinical roles render.
- **S. Owning Implementation Phase:** Phase 24 (Public Website: Remaining Public Pages & Legal Views).

---

#### 13. Job Details & Application Portal
- **A. Page Identifier:** `PAGE-PUB-13`
- **B. Route & URL Pattern:** `/careers/:slug` (Dynamic slug, e.g., `/careers/senior-clinical-governance-lead`)
- **C. Page Purpose:** Provide detailed job specifications, responsibilities, requirements, and provide a secure, accessible application form for candidates.
- **D. Primary User Personas:** Prospective Job Applicants.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Careers > :job_title`)
  2. Job Header with title, department, location, employment type, and "Apply Now" anchor
  3. Role Overview & Strategic Purpose
  4. Key Responsibilities (Bulleted list)
  5. Qualifications, Credentialing & Experience Requirements (AHPRA registration requirements if clinical)
  6. Compensation, Benefits & Support Model
  7. Embedded Application Form (Full candidate capture and resume attachment)
- **F. Content Model:** `slug`, `title`, `department`, `employment_type`, `location`, `description_markdown`, `requirements_markdown`, `benefits_markdown`, `closing_date`.
- **G. Required Backend Data:** `GET /api/v1/public/jobs/:slug` — returns job details.
- **H. User Interactions:** Smooth scroll to `#apply-form` on clicking "Apply Now"; file drag-and-drop zone for resume upload.
- **I. Navigation Paths:**
  - Inbound: `/careers`.
  - Outbound: `/careers`, `/privacy-policy`.
- **J. Form Requirements (Application Form):**
  - Fields: `first_name`, `last_name`, `email`, `phone`, `current_employer` (optional), `linkedin_url` (optional), `cover_letter` (textarea), `resume_file` (file upload: PDF/DOCX, max 10MB), `privacy_consent` (checkbox).
  - Validation: Email regex, International E.164 phone format, mandatory resume upload with MIME verification (`application/pdf`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document`).
  - Protection: Cloudflare Turnstile CAPTCHA widget, rate limit: max 3 applications per IP per hour.
  - Persistence: `POST /api/v1/public/careers/apply`.
- **K. Empty / Loading / Error States:**
  - Expired / Invalid Job: Returns HTTP 404 with message: "This vacancy is no longer accepting applications. View current openings on our Careers Portal."
- **L. Responsive Behavior:** 2-column layout (job description 60%, application form 40%) stacks vertically on mobile.
- **M. Accessibility Expectations:** File input has accessible keyboard activation; error messages linked via `aria-describedby`; upload progress announced to screen readers.
- **N. SEO Requirements:**
  - Title: `:job_title | Careers | Voak Health Care`
  - JSON-LD: Schema.org `JobPosting` schema (title, description, hiringOrganization, jobLocation, datePosted, validThrough).
- **O. Security & Privacy Requirements:** Resumes quarantined outside web root in `storage/uploads/resumes/` with UUID naming; zero PHP execution in upload directory; virus scanning hook.
- **P. Admin-Managed Fields:** Job details CRUD, application deadline.
- **Q. Exact Acceptance Criteria:**
  1. Emits 404 if job slug is expired or inactive.
  2. Rejects files exceeding 10MB or containing invalid MIME magic bytes with clear inline error.
  3. Dispatches candidate acknowledgement email upon successful submission.
- **R. Automated Test Scenarios (TEST-PUB-013):**
  - `E2E-JOBAPP-001`: Submit valid application with PDF resume; assert 201 Created and success screen.
  - `E2E-JOBAPP-002`: Attempt upload of `.exe` renamed to `.pdf`; assert 422 Unprocessable Entity error.
- **S. Owning Implementation Phase:** Phase 24 (Public Website: Remaining Public Pages & Legal Views).

---

#### 14. Insights & Resources Hub
- **A. Page Identifier:** `PAGE-PUB-14`
- **B. Route & URL Pattern:** `/insights` (Canonical: `https://voakhealthcare.com.au/insights`)
- **C. Page Purpose:** Establish healthcare thought leadership, publish clinical operational whitepapers, regulatory updates, and workforce strategy insights.
- **D. Primary User Personas:** Healthcare Executives, Clinical Directors, Policy Analysts.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Insights`)
  2. Insights Hub Header
  3. Featured Lead Article Banner (Prominent display of top editorial piece)
  4. Category Navigation Tabs (All, Clinical Governance, Workforce Strategy, Health Tech & Facilities, Policy & Regulation)
  5. Search Bar (Search across article titles and excerpts)
  6. Article Directory Grid (Cards: category badge, title, excerpt, author, publication date, reading time estimate, thumbnail)
  7. Newsletter / Executive Briefing Subscription Card
- **F. Content Model:** Article array: `id`, `slug`, `title`, `category_code`, `excerpt`, `author_name`, `published_at`, `reading_time_minutes`, `featured_image_url`, `is_featured`, `is_published`.
- **G. Required Backend Data:** `GET /api/v1/public/articles` (Params: `category`, `search`, `page`, `limit`) — returns paginated article summaries.
- **H. User Interactions:** Category tab filtering; search input with 300ms debounce; pagination controls (`Previous`, `Page 1, 2, ...`, `Next`).
- **I. Navigation Paths:**
  - Inbound: Header "Insights", homepage insights preview.
  - Outbound: `/insights/:slug`.
- **J. Form Requirements:** Executive Briefing Subscription (Email input, Turnstile, consent checkbox).
- **K. Empty / Loading / Error States:**
  - Empty: Displays "No articles found matching your query. Browse all categories or contact us for specific research requests."
- **L. Responsive Behavior:** Featured article layout switches from horizontal 50/50 banner (desktop) to stacked vertical card (mobile).
- **M. Accessibility Expectations:** Pagination links have `aria-label="Go to page 2"`, current page marked `aria-current="page"`.
- **N. SEO Requirements:**
  - Title: `Healthcare Insights & Operational Resources | Voak Health Care`
  - Meta Description: `In-depth articles, clinical governance whitepapers, and healthcare workforce strategies from Voak Health Care's executive team.`
  - JSON-LD: Schema.org `Blog` or `CollectionPage`.
- **O. Security & Privacy Requirements:** Search queries sanitized; subscriber emails stored securely.
- **P. Admin-Managed Fields:** Article categories, featured article selection, article CRUD.
- **Q. Exact Acceptance Criteria:**
  1. Pagination operates correctly across multiple pages.
  2. Search filtering returns only matching published articles.
- **R. Automated Test Scenarios (TEST-PUB-014):**
  - `E2E-INS-001`: Filter by category "Workforce Strategy"; verify only relevant articles display.
- **S. Owning Implementation Phase:** Phase 24 (Public Website: Remaining Public Pages & Legal Views).

---

#### 15. Individual Article View
- **A. Page Identifier:** `PAGE-PUB-15`
- **B. Route & URL Pattern:** `/insights/:slug` (Dynamic slug, e.g., `/insights/clinical-governance-aged-care-2026`)
- **C. Page Purpose:** Deliver rich, long-form editorial content, thought leadership whitepapers, and regulatory guidance with social sharing and contextual lead generation.
- **D. Primary User Personas:** Healthcare Decision-Makers seeking authoritative guidance.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Insights > :article_title`)
  2. Article Header with category, serif headline, author byline, publication date, and reading time
  3. Reading Progress Bar (Fixed top indicator tracking scroll depth)
  4. Main Editorial Content (Rich text/Markdown with headings, blockquotes, callout boxes, and figures)
  5. Author Bio Card (Name, title, credentials, LinkedIn profile)
  6. Social Sharing Actions (LinkedIn, Twitter/X, Email, Copy Link)
  7. Related Articles Carousel (3 contextual articles)
  8. Contextual Operational Advisory CTA ("Discuss this topic with our specialists")
- **F. Content Model:** `slug`, `title`, `category_code`, `excerpt`, `content_markdown`, `author_name`, `author_title`, `author_image_url`, `published_at`, `reading_time_minutes`, `seo_meta_title`, `seo_meta_description`.
- **G. Required Backend Data:** `GET /api/v1/public/articles/:slug` — returns full article payload and 3 related articles.
- **H. User Interactions:** Top reading progress bar animates on scroll; "Copy Link" button copies current URL to clipboard and triggers a brief tooltip ("Copied!"); LinkedIn share opens sharing dialog in new window.
- **I. Navigation Paths:**
  - Inbound: `/insights`, homepage, external social shares.
  - Outbound: `/insights`, `/contact`, `/request-proposal`.
- **J. Form Requirements:** None.
- **K. Empty / Loading / Error States:**
  - 404: Returns authentic HTTP 404 for invalid or unpublished slugs.
- **L. Responsive Behavior:** Editorial content centered with max-width `68ch` for optimal reading ergonomics; table of contents sidebar hides on `< 1024px`.
- **M. Accessibility Expectations:** Markdown parsed with semantic HTML (`<h2>`, `<h3>`, `<blockquote>`, `<figure>`, `<figcaption>`); images include descriptive alt text; reading progress bar has `role="progressbar"` and `aria-valuenow`.
- **N. SEO Requirements:**
  - Title: `:seo_meta_title | Voak Health Care Insights`
  - Meta Description: Dynamic excerpt.
  - Canonical: `https://voakhealthcare.com.au/insights/:slug`
  - JSON-LD: Schema.org `Article` schema with `headline`, `author`, `publisher`, `datePublished`, `image`.
- **O. Security & Privacy Requirements:** Content strictly sanitized with DOMPurify to eliminate stored XSS.
- **P. Admin-Managed Fields:** Article title, slug, content markdown, author, category, publication date, meta tags, publish toggle.
- **Q. Exact Acceptance Criteria:**
  1. Emits HTTP 404 for non-existent or unpublished articles.
  2. Pre-renders complete semantic article text via PHP front controller for search crawlers.
  3. Social sharing links function accurately.
- **R. Automated Test Scenarios (TEST-PUB-015):**
  - `E2E-ARTDET-001`: Request valid article; assert 200 OK and reading progress bar updates on scroll.
  - `E2E-ARTDET-002`: Click "Copy Link" and verify clipboard content matches URL.
- **S. Owning Implementation Phase:** Phase 24 (Public Website: Remaining Public Pages & Legal Views).

---

#### 16. Frequently Asked Questions (FAQs)
- **A. Page Identifier:** `PAGE-PUB-16`
- **B. Route & URL Pattern:** `/faqs` (Canonical: `https://voakhealthcare.com.au/faqs`)
- **C. Page Purpose:** Address common procurement, contracting, credentialing, SLA, and operational queries to reduce sales friction.
- **D. Primary User Personas:** Healthcare Procurement Leads, Practice Managers, Compliance Officers.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > FAQs`)
  2. FAQ Header
  3. Category Filter Tabs (General & Onboarding, Clinical Workforce & Credentialing, SLAs & Billing, Compliance & Governance, Facility Operations)
  4. Search Filter (Real-time keyword filtering across questions and answers)
  5. Interactive Accordion List (Grouped by category)
  6. Unresolved Question Help Card ("Can't find your answer? Contact our procurement team directly")
- **F. Content Model:** FAQ array: `id`, `category_code`, `question`, `answer_markdown`, `display_order`, `is_published`.
- **G. Required Backend Data:** `GET /api/v1/public/faqs` — returns all published FAQs.
- **H. User Interactions:** Click/Enter toggles accordion expansion; typing in search filters questions dynamically; "Expand All / Collapse All" toggle.
- **I. Navigation Paths:**
  - Inbound: Header, footer, contextual links in RFP/Services pages.
  - Outbound: `/contact`, `/request-proposal`.
- **J. Form Requirements:** None.
- **K. Empty / Loading / Error States:**
  - Empty: Displays "No questions match your search. Contact our support team for assistance."
- **L. Responsive Behavior:** Full-width accordion layout across all viewports.
- **M. Accessibility Expectations:** Accordions implement WAI-ARIA standards (`aria-expanded="false"`, `aria-controls="faq-desc-id"`, unique IDs on headers and panels); full keyboard navigation via Space/Enter.
- **N. SEO Requirements:**
  - Title: `Frequently Asked Questions | Healthcare Procurement & Operations | Voak Health Care`
  - Meta Description: `Find answers to common questions regarding Voak Health Care's staffing credentialing, SLA models, contracting, and compliance frameworks.`
  - JSON-LD: Schema.org `FAQPage` schema containing all questions and answers.
- **O. Security & Privacy Requirements:** Markdown answers sanitized.
- **P. Admin-Managed Fields:** FAQ CRUD: category, question, answer, display order, publish toggle.
- **Q. Exact Acceptance Criteria:**
  1. All accordions operate cleanly via keyboard.
  2. Schema.org FAQPage structured data valid in Google Rich Results testing.
- **R. Automated Test Scenarios (TEST-PUB-016):**
  - `E2E-FAQ-001`: Filter FAQs by search term; assert matching accordions remain visible.
- **S. Owning Implementation Phase:** Phase 24 (Public Website: Remaining Public Pages & Legal Views).

---

#### 17. Contact Us
- **A. Page Identifier:** `PAGE-PUB-17`
- **B. Route & URL Pattern:** `/contact` (Canonical: `https://voakhealthcare.com.au/contact`)
- **C. Page Purpose:** Provide a secure, general corporate communication channel for inquiries, administrative requests, and media relations.
- **D. Primary User Personas:** Healthcare Administrators, General Public, Media, Vendors.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Contact Us`)
  2. Contact Header
  3. 2-Column Layout:
     - Left Column: Corporate Information (Official corporate entity name, operating hours, telephone, official inquiry email, headquarters location/state, specialized contact directory)
     - Right Column: General Contact Inbound Form
  4. Interactive Response Time Commitment Notice ("Our administrative team acknowledges all inquiries within 1 business day")
  5. Non-Clinical Emergency Disclaimer ("Voak Health Care provides B2B healthcare operational services. If you require medical emergency assistance, please call 000 immediately.")
- **F. Content Model:** Corporate contact metadata, form state model.
- **G. Required Backend Data:** `GET /api/v1/public/settings/contact` — returns official business contact details.
- **H. User Interactions:** Form input focus states, inline validation on blur, submit action with loading spinner, success toast and confirmation card.
- **I. Navigation Paths:**
  - Inbound: Header "Contact", footer.
  - Outbound: `/privacy-policy`, `/request-proposal`.
- **J. Form Requirements (General Contact Form):**
  - Fields: `full_name` (string, max 100), `organization_name` (string, max 150), `work_email` (email, max 100), `phone` (string, max 30), `inquiry_type` (select: General Inquiries, Services, Compliance, Careers, Media), `message` (textarea, max 2000), `consent_privacy` (boolean).
  - Validation: Email syntax validation (RFC 5322), phone character validation, required privacy consent.
  - Anti-Spam: Cloudflare Turnstile token, honeypot field (`website_url_check` hidden via CSS).
  - Rate Limiting: Max 5 submissions per IP per hour.
  - Endpoint: `POST /api/v1/public/contact`.
- **K. Empty / Loading / Error States:**
  - Loading: Button displays spinner and disabled state during API call.
  - Error: Inline field error banners; toast notification for network/server errors.
  - Success: Form replaced with confirmation card displaying unique Inquiry Reference Number (`INQ-YYYYMMDD-XXXX`).
- **L. Responsive Behavior:** 2-column layout stacks on `< 1024px` (corporate details first, form second).
- **M. Accessibility Expectations:** All fields have explicit `<label>` tags; invalid fields marked `aria-invalid="true"` with `aria-describedby` pointing to error message IDs; success alert has `role="status"`.
- **N. SEO Requirements:**
  - Title: `Contact Voak Health Care | Corporate Healthcare Inquiries`
  - Meta Description: `Get in touch with Voak Health Care for corporate healthcare operations, clinical staffing partnerships, and general procurement inquiries.`
  - JSON-LD: Schema.org `ContactPage`.
- **O. Security & Privacy Requirements:** Zero logging of sensitive messages in plain server error logs; CSRF synchronizer token verified on POST.
- **P. Admin-Managed Fields:** Corporate address, operating hours, public phone, public email, inquiry type options.
- **Q. Exact Acceptance Criteria:**
  1. Submitting valid form creates database record in `contact_inquiries` table with status `NEW`.
  2. Dispatches transactional confirmation email to user and notification email to triage team.
  3. Rejects submissions if honeypot is populated or Turnstile fails.
- **R. Automated Test Scenarios (TEST-PUB-017):**
  - `E2E-CNT-001`: Submit valid contact form; assert success view with reference code.
  - `E2E-CNT-002`: Submit with empty required fields; assert inline validation messages appear.
  - `E2E-CNT-003`: Verify honeypot submission fails with silent 200 or 400 rejection.
- **S. Owning Implementation Phase:** Phase 23 (Public Website: Business Enquiries & Inbound Channels).

---

#### 18. Partner With Us
- **A. Page Identifier:** `PAGE-PUB-18`
- **B. Route & URL Pattern:** `/partner-with-us` (Canonical: `https://voakhealthcare.com.au/partner-with-us`)
- **C. Page Purpose:** Capture high-touch strategic partnership inquiries from healthcare networks, joint-venture partners, academic institutions, and subcontracting consortia.
- **D. Primary User Personas:** Healthcare CEOs, Business Development Executives, Health System Alliances Leads.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Partner With Us`)
  2. Strategic Collaboration Header
  3. Partnership Models Overview (Network Provider Alliance, Workforce Consortium, Clinical Training Partnership, Facility Management Co-Sourcing)
  4. Strategic Partnership Inbound Intake Form (High-touch organizational qualification)
  5. Executive Discovery Process (What happens after you submit)
  6. Confidentiality & Non-Disclosure Commitment Banner
- **F. Content Model:** Partnership models copy, intake form fields.
- **G. Required Backend Data:** `GET /api/v1/public/services` (to populate relevant service interest options).
- **H. User Interactions:** Form multi-select for collaboration interests; interactive checklist; submission with immediate reference generation.
- **I. Navigation Paths:**
  - Inbound: Header "Partner", homepage conversion section.
  - Outbound: `/request-proposal`, `/privacy-policy`.
- **J. Form Requirements (Strategic Partnership Intake Form):**
  - Fields: `executive_name` (string, max 100), `executive_title` (string, max 100), `organization_name` (string, max 150), `organization_type` (select: Public Hospital Network, Private Hospital Group, Aged Care Consortium, Allied Health Group, Academic Institution, Other), `work_email` (email), `phone` (string), `partnership_scope` (multi-select: Workforce Deployment, Facilities Management, Operational Co-Sourcing, Strategic Advisory), `estimated_scale` (select: Single Facility, 2-5 Facilities, 6-15 Facilities, 15+ Facilities / Statewide), `collaboration_overview` (textarea, max 3000), `privacy_consent` (boolean).
  - Validation: Strict corporate email validation (blocking common disposable domains); mandatory organizational scope.
  - Anti-Spam: Cloudflare Turnstile token.
  - Rate Limiting: Max 3 submissions per IP per hour.
  - Endpoint: `POST /api/v1/public/partnerships/inquire`.
- **K. Empty / Loading / Error States:** Similar to Contact Us; emits Partnership Reference (`PTN-YYYYMMDD-XXXX`).
- **L. Responsive Behavior:** Form features 2-column input layout on desktop, condensing to single-column on mobile.
- **M. Accessibility Expectations:** Multi-select options implemented as accessible checkbox groups with `<fieldset>` and `<legend>`.
- **N. SEO Requirements:**
  - Title: `Partner With Us | Strategic Healthcare Alliances | Voak Health Care`
  - Meta Description: `Establish an enterprise partnership with Voak Health Care. Discover collaborative workforce alliances, facility co-sourcing, and health system joint ventures.`
- **O. Security & Privacy Requirements:** Data encrypted in transit via TLS 1.3; submissions stored in `partnership_inquiries` table with restricted RBAC access (Super Admin and BD Manager only).
- **P. Admin-Managed Fields:** Partnership types, organization size options, confirmation email copy.
- **Q. Exact Acceptance Criteria:**
  1. Creates inquiry in `partnership_inquiries` with status `NEW`.
  2. Dispatches internal high-priority email notification to BD lead.
- **R. Automated Test Scenarios (TEST-PUB-018):**
  - `E2E-PTN-001`: Complete partnership intake form; verify record creation and reference code display.
- **S. Owning Implementation Phase:** Phase 23 (Public Website: Business Enquiries & Inbound Channels).

---

#### 19. Request a Proposal (RFP)
- **A. Page Identifier:** `PAGE-PUB-19`
- **B. Route & URL Pattern:** `/request-proposal` (Canonical: `https://voakhealthcare.com.au/request-proposal`)
- **C. Page Purpose:** Provide an enterprise-grade, multi-step proposal intake engine allowing healthcare procurement managers to submit tender briefs, facility profiles, service requirements, and tender documents.
- **D. Primary User Personas:** Healthcare Procurement Directors, Hospital COOs, Practice Operations Leads.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Request a Proposal`)
  2. RFP Header with procurement SLA notice ("Proposals scoped and acknowledged within 48 business hours")
  3. Multi-Step RFP Intake Wizard:
     - Step 1: Organization & Facility Profile (Organization name, sector, facility locations, contact executive details)
     - Step 2: Scope & Service Selection (Select service pillars, operational scope, estimated FTE or bed count)
     - Step 3: Timeline & Budgetary Parameters (Anticipated start date, procurement stage: Budgeting / Active Tender / Emergency Replacement, budget range)
     - Step 4: Documentation Upload & Project Brief (Detailed brief textarea, optional tender document attachment: PDF/DOCX/XLSX max 15MB, privacy consent)
  4. Step Progress Indicator (Visual wizard tracker: 1. Organization -> 2. Scope -> 3. Timeline -> 4. Review & Submit)
  5. Security & Non-Disclosure Notice ("All tender briefs are held under strict institutional confidentiality")
- **F. Content Model:** Multi-step wizard state machine, dynamic service list from database.
- **G. Required Backend Data:** `GET /api/v1/public/services` (to populate service options in Step 2).
- **H. User Interactions:** Multi-step navigation ("Next Step", "Previous Step"); state preserved across step navigation; drag-and-drop file upload with live progress bar; submit button triggers final review modal.
- **I. Navigation Paths:**
  - Inbound: Primary header CTA, service detail CTAs, homepage conversion banners.
  - Outbound: `/services`, `/privacy-policy`.
- **J. Form Requirements (Multi-Step RFP Engine):**
  - Fields:
    - Step 1: `organization_name`, `facility_type`, `facility_country`, `facility_region_state`, `lead_name`, `lead_title`, `lead_email`, `lead_phone`.
    - Step 2: `service_ids` (array of integers), `estimated_scale_metrics` (e.g., bed count / clinical shifts).
    - Step 3: `target_start_date` (date), `procurement_stage` (select), `budget_band` (select).
    - Step 4: `project_brief` (textarea, max 5000), `tender_document` (file upload: PDF/DOCX/XLSX max 15MB), `nda_required` (boolean), `consent_privacy` (boolean).
  - Validation: Per-step validation preventing advancement if required fields fail; file verification for MIME magic bytes (`application/pdf`, `application/vnd.openxmlformats-officedocument.*`).
  - Protection: Cloudflare Turnstile CAPTCHA on final step; rate limiting: max 3 RFP submissions per IP per 24 hours.
  - Endpoint: `POST /api/v1/public/proposals/submit` (Multipart form-data).
- **K. Empty / Loading / Error States:**
  - Loading: Step navigation disables while validating; final submission presents animated progress indicator.
  - Success: Generates official Tender Reference Number (`RFP-YYYYMMDD-XXXX`) with downloadable submission summary.
- **L. Responsive Behavior:** Wizard progress bar condenses to "Step X of 4" on mobile; file drop zone adapts for touch file pickers.
- **M. Accessibility Expectations:** Wizard step changes announced via `aria-live="assertive"`; keyboard focus automatically set to the first input of the newly activated step; all error states linked via `aria-describedby`.
- **N. SEO Requirements:**
  - Title: `Request a Healthcare Operations Proposal | Voak Health Care`
  - Meta Description: `Submit a Request for Proposal (RFP) or tender brief to Voak Health Care. Rapid, compliant operational and clinical workforce proposals.`
- **O. Security & Privacy Requirements:** Uploaded tender files immediately quarantined outside public root (`storage/uploads/proposals/`); encrypted at rest; accessible only via authenticated streaming for authorized roles (`Super Admin`, `BD Manager`).
- **P. Admin-Managed Fields:** Budget band options, procurement stage options, RFP acknowledgement email template.
- **Q. Exact Acceptance Criteria:**
  1. Multi-step wizard validates each step independently before allowing progression.
  2. File uploads strictly enforce 15MB ceiling and approved MIME types.
  3. Inserts record into `proposals` table with status `NEW`.
  4. Dispatches confirmation email containing reference code.
- **R. Automated Test Scenarios (TEST-PUB-019):**
  - `E2E-RFP-001`: Complete all 4 wizard steps and submit; assert RFP reference code generated.
  - `E2E-RFP-002`: Try advancing from Step 1 to Step 2 with missing email; assert progression blocked and error displayed.
  - `E2E-RFP-003`: Attach an executable `.bat` renamed to `.pdf`; verify server rejects upload with 422 error.
- **S. Owning Implementation Phase:** Phase 23 (Public Website: Business Enquiries & Inbound Channels).

---

#### 20. Privacy Policy
- **A. Page Identifier:** `PAGE-PUB-20`
- **B. Route & URL Pattern:** `/privacy-policy` (Canonical: `https://voakhealthcare.com.au/privacy-policy`)
- **C. Page Purpose:** Articulate Voak Health Care's strict privacy governance adhering to the Australian Privacy Principles (APPs) under the Privacy Act 1988 (Cth), clarifying handling of B2B business inquiries, candidate data, and explicitly disclaiming collection of patient health records.
- **D. Primary User Personas:** Legal Counsel, Compliance Officers, Job Applicants, Website Visitors.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Privacy Policy`)
  2. Policy Header with last updated date and version number
  3. Scope & Non-Clinical Disclaimer (Clarifying Voak does not store patient medical records via this site)
  4. Categories of Personal Information Collected (Corporate contact details, candidate resumes)
  5. Purpose of Data Collection & Processing
  6. Disclosure & Third-Party Service Providers (Hosting, email delivery, Turnstile spam protection)
  7. Data Storage, Security & Cross-Border Disclosure Governance
  8. Data Retention & Right to Erasure / Access Procedures
  9. Cookie & Analytics Policy (Privacy-first telemetry)
  10. Privacy Officer Contact Details & Complaints Mechanism
- **F. Content Model:** Version metadata, policy content structured in markdown with anchored sections.
- **G. Required Backend Data:** `GET /api/v1/public/pages/privacy-policy` — returns active policy text and revision date.
- **H. User Interactions:** Quick navigation table of contents sidebar linking to internal section anchors (`#collection`, `#security`, `#cookies`).
- **I. Navigation Paths:**
  - Inbound: Global footer, all form consent disclaimers.
  - Outbound: `/contact`, `/terms`.
- **J. Form Requirements:** None.
- **K. Empty / Loading / Error States:** Standard content loading shimmer.
- **L. Responsive Behavior:** Sticky sidebar TOC collapses to a dropdown selector on mobile viewports.
- **M. Accessibility Expectations:** Semantic heading hierarchy (`h1` -> `h2` -> `h3`); anchored links maintain proper focus states.
- **N. SEO Requirements:**
  - Title: `Privacy Policy | Australian Privacy Principles Compliance | Voak Health Care`
  - Meta Description: `Read Voak Health Care's Privacy Policy outlining our compliance with the Privacy Act 1988 (Cth) and our handling of corporate and recruitment data.`
  - Robots: `index, follow`.
- **O. Security & Privacy Requirements:** Contains formal contact details for privacy inquiries; zero third-party tracking scripts loaded without user knowledge.
- **P. Admin-Managed Fields:** Policy markdown content, effective date, version identifier.
- **Q. Exact Acceptance Criteria:**
  1. Displays explicit non-clinical care disclaimer.
  2. Table of contents smoothly scrolls to target anchor sections.
- **R. Automated Test Scenarios (TEST-PUB-020):**
  - `E2E-PRIV-001`: Click anchor links in TOC; verify viewport scrolls to correct heading ID.
- **S. Owning Implementation Phase:** Phase 24 (Public Website: Remaining Public Pages & Legal Views).

---

#### 21. Terms of Service & Legal Notices
- **A. Page Identifier:** `PAGE-PUB-21`
- **B. Route & URL Pattern:** `/terms` (Canonical: `https://voakhealthcare.com.au/terms`)
- **C. Page Purpose:** Document the legal conditions governing website usage, intellectual property rights, non-clinical corporate boundaries, and liability limitations under Australian law.
- **D. Primary User Personas:** Corporate Legal Teams, Procurement Officers, Website Users.
- **E. Required Sections:**
  1. Breadcrumbs (`Home > Terms of Service`)
  2. Legal Header with effective date and jurisdiction statement
  3. Acceptance of Terms & Website Scope
  4. Intellectual Property & Brand Usage Rights (Voak Health Care trademarks, content copyright)
  5. Non-Clinical Care Disclaimer (Explicit exclusion of patient care advice or medical treatment liability)
  6. Inbound RFP & Business Proposal Submissions (Non-binding nature until formal contract execution)
  7. User Conduct & Acceptable Use Policy (Prohibition of automated scraping, reverse engineering, malicious uploads)
  8. Limitation of Liability & Warranties Disclaimer
  9. Governing Law & Jurisdiction (State of Victoria / Commonwealth of Australia)
  10. Corporate Legal Contact Information
- **F. Content Model:** Legal terms markdown content, version metadata.
- **G. Required Backend Data:** `GET /api/v1/public/pages/terms` — returns active terms content.
- **H. User Interactions:** Section anchor navigation.
- **I. Navigation Paths:**
  - Inbound: Global footer, RFP submission review.
  - Outbound: `/privacy-policy`, `/contact`.
- **J. Form Requirements:** None.
- **K. Empty / Loading / Error States:** Standard content skeleton.
- **L. Responsive Behavior:** Full-width readable text column (`68ch` max width).
- **M. Accessibility Expectations:** High-contrast text, clear visual anchors, semantic typography.
- **N. SEO Requirements:**
  - Title: `Terms of Service & Legal Notices | Voak Health Care`
  - Meta Description: `Review the Terms of Service governing the use of Voak Health Care's website, proposal desk, and corporate operational services.`
- **O. Security & Privacy Requirements:** Legal terms explicitly restrict unauthorized penetration testing or vulnerability exploitation against shared hosting infrastructure.
- **P. Admin-Managed Fields:** Terms markdown copy, version number, effective date.
- **Q. Exact Acceptance Criteria:**
  1. Clarifies that website submissions do not constitute binding service agreements until executed contracts are finalized.
- **R. Automated Test Scenarios (TEST-PUB-021):**
  - `E2E-TERMS-001`: Verify legal terms render with active version date.
- **S. Owning Implementation Phase:** Phase 24 (Public Website: Remaining Public Pages & Legal Views).

---

#### 22. 404 Not Found & Error Boundaries
- **A. Page Identifier:** `PAGE-PUB-22`
- **B. Route & URL Pattern:** Wildcard catch-all (`*`) for unmapped public routes; dynamic triggers for missing slugs (`/services/invalid-slug`).
- **C. Page Purpose:** Provide an informative, branded recovery environment when a user or crawler requests a non-existent URL, emitting authentic HTTP 404 status codes while guiding users back to active services.
- **D. Primary User Personas:** Any visitor encountering a broken, mistyped, or de-indexed link.
- **E. Required Sections:**
  1. Global Navigation Bar
  2. Branded Error Hero with soft lavender canvas, pastel accent illustrations, and clear headline ("Page Not Found")
  3. Diagnostic Explanation ("The operational page or resource you requested may have been relocated, unpublished, or does not exist.")
  4. Quick Recovery Navigation Links:
     - Explore Our Services (`/services`)
     - Industry Solutions (`/industries`)
     - Request a Proposal (`/request-proposal`)
     - Return to Homepage (`/`)
  5. Search Bar (Allowing direct search for active services or insights)
  6. Diagnostic Reference Code (For 500 error variants, displays sanitized error reference ID, e.g., `ERR-500-XXXXX`)
  7. Global Footer
- **F. Content Model:** Error copy, quick link array, search input configuration.
- **G. Required Backend Data:** PHP Front Controller intercepts non-matching URLs and emits `header("HTTP/1.1 404 Not Found")` before rendering the semantic fallback.
- **H. User Interactions:** Quick search bar triggers query to `/services?q=:query`; recovery buttons navigate smoothly.
- **I. Navigation Paths:**
  - Outbound: `/`, `/services`, `/industries`, `/request-proposal`, `/contact`.
- **J. Form Requirements:** Recovery search input.
- **K. Empty / Loading / Error States:** Static fallback guarantee—this component contains zero remote data dependencies to prevent recursive error loops.
- **L. Responsive Behavior:** Centered single-column layout across all viewports.
- **M. Accessibility Expectations:** Error headline marked as `<h1>`; error announced via `role="alert"`; immediate focus placed on the primary recovery action ("Return to Homepage").
- **N. SEO Requirements:**
  - HTTP Status: Strictly `404 Not Found` (never `200 OK` soft-404).
  - Robots: `noindex, nofollow`.
  - Title: `Page Not Found (404) | Voak Health Care`
- **O. Security & Privacy Requirements:** Never displays PHP stack traces, SQL syntax errors, database credentials, or server internal file paths.
- **P. Admin-Managed Fields:** Branded 404 helpful message copy, featured recovery links.
- **Q. Exact Acceptance Criteria:**
  1. Emits authentic HTTP 404 header verified via curl/DevTools.
  2. Eliminates soft-404 search engine indexing penalties.
  3. Contains no broken recovery links.
- **R. Automated Test Scenarios (TEST-PUB-022 / TEST-PUB-023):**
  - `E2E-404-001`: Request non-existent URL `/this-page-does-not-exist`; assert response status is exactly 404 and error heading is visible.
  - `E2E-404-002`: Click "Return to Homepage" button; assert browser lands on `/`.
- **S. Owning Implementation Phase:** Phase 24 (Public Website: Remaining Public Pages & Legal Views).

---

### Verification & Governance Matrix

| Page ID | Route | Primary Persona | SEO Status | Security Classification | Phase Owner |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **PAGE-PUB-01** | `/` | C-Suite / Procurement | Index, Follow | Public Cacheable | Phase 20 |
| **PAGE-PUB-02** | `/about` | Board / Investors | Index, Follow | Public Cacheable | Phase 21 |
| **PAGE-PUB-03** | `/services` | Ops / Procurement | Index, Follow | Public Cacheable | Phase 22 |
| **PAGE-PUB-04** | `/services/:slug` | Clinical Procurement | Index, Follow | Dynamic / Sanitized | Phase 22 |
| **PAGE-PUB-05** | `/industries` | Sector Directors | Index, Follow | Public Cacheable | Phase 22 |
| **PAGE-PUB-06** | `/industries/:slug` | Facility Managers | Index, Follow | Dynamic / Sanitized | Phase 22 |
| **PAGE-PUB-07** | `/why-choose-voak` | CFOs / Procurement | Index, Follow | Public Cacheable | Phase 21 |
| **PAGE-PUB-08** | `/how-we-work` | COOs / Transitions | Index, Follow | Public Cacheable | Phase 21 |
| **PAGE-PUB-09** | `/quality-compliance`| Clinical Governance | Index, Follow | Public Cacheable | Phase 21 |
| **PAGE-PUB-10** | `/partners-clients` | Strategic Consortia | Index, Follow | Verified Assets Only | Phase 24 |
| **PAGE-PUB-11** | `/case-studies` | Procurement Evaluators| Index, Follow | Verified Metrics Only | Phase 24 |
| **PAGE-PUB-12** | `/careers` | Job Candidates | Index, Follow | Public Cacheable | Phase 24 |
| **PAGE-PUB-13** | `/careers/:slug` | Applicants | Index, Follow | Quarantined Uploads | Phase 24 |
| **PAGE-PUB-14** | `/insights` | Thought Leaders | Index, Follow | Public Cacheable | Phase 24 |
| **PAGE-PUB-15** | `/insights/:slug` | Policy / Operations | Index, Follow | Sanitized Markdown | Phase 24 |
| **PAGE-PUB-16** | `/faqs` | Procurement Leads | Index, Follow | FAQPage Schema | Phase 24 |
| **PAGE-PUB-17** | `/contact` | General Inquirers | Index, Follow | Rate-Limited / Turnstile| Phase 23 |
| **PAGE-PUB-18** | `/partner-with-us` | Strategic Alliances | Index, Follow | RBAC Restricted CRM | Phase 23 |
| **PAGE-PUB-19** | `/request-proposal` | Procurement Directors| Index, Follow | Quarantined RFP Storage| Phase 23 |
| **PAGE-PUB-20** | `/privacy-policy` | Legal / Candidates | Index, Follow | APP Compliance | Phase 24 |
| **PAGE-PUB-21** | `/terms` | Corporate Counsel | Index, Follow | Legal Governing Terms | Phase 24 |
| **PAGE-PUB-22** | `/*` (404 Error) | All Visitors | NoIndex, NoFollow| True HTTP 404 Header | Phase 24 |
