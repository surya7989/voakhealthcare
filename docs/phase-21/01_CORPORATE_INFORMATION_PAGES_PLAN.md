# Phase 21 Plan: Corporate Information Pages
## Voak Health Care Enterprise Web Application

**Document Identifier**: `PLAN-PHASE21-CORP-001`  
**Phase Identifier**: `PHASE-21` / `PUB-CORP-001`  
**Module Identifiers**: `PAGE-PUB-02`, `PAGE-PUB-07`, `PAGE-PUB-08`, `PAGE-PUB-09`  
**Target Environment**: `frontend/src/apps/public/` (React 18.3.1 / TypeScript strict / Vite 5.4.21 / Tailwind CSS v3)  
**Creation Date**: 2026-10-24  
**Author**: Antigravity Autonomous Agent  
**Verified Phase 20 Base HEAD**: `a8ca3ebb2680fc01a709624a81fb34198066bf9a`  
**Database Migrations Applied**: 28 migrations applied / 0 pending (ZERO new migrations required)  

---

### 1. Executive Summary & Canonical Roadmap Alignment

Phase 21 is officially designated as **Corporate Information Pages** (Canonical: "Public Website: Corporate, Governance & About Pages"). It succeeds the verified Phase 20 Homepage implementation and delivers the 4 canonical public corporate and clinical governance pages for Voak Health Care:

1. **`PAGE-PUB-02` (`/about`)**: Corporate About Us, Mission, Vision & Values Bento, Executive Leadership with Accessible Modal Bios, Operational Footprint, Milestone Timeline, and Non-Clinical Care Boundary Notice (`PUB-006`, `FR-PUB-009`, `TEST-PUB-009`).
2. **`PAGE-PUB-07` (`/why-choose-voak`)**: Strategic Differentiation, 4-Pillar Advantage Bento, Multi-Dimension Comparative Procurement Matrix (Voak vs. Traditional Agency vs. In-House Overhead), Risk Mitigation Protocols (`PUB-011`, `FR-PUB-010`, `TEST-PUB-010`).
3. **`PAGE-PUB-08` (`/how-we-work`)**: 4-Stage Operational Implementation Roadmap (Audit & Diagnostic, Architecture & SLA Design, Deployment & Credentialed Integration, Continuous Quality Governance), Interactive Deliverables Stepper, Client Onboarding Timeline (`PUB-012`, `FR-PUB-011`, `TEST-PUB-011`).
4. **`PAGE-PUB-09` (`/quality-compliance`)**: Clinical Governance Framework, AHPRA Practitioner Credentialing Protocols, NSQHS/Aged Care/NDIS Standards Alignment, Multi-Tier Incident Escalation, and Mandatory High-Contrast Non-Clinical Care Operational Boundary Notice (`PUB-013`, `FR-PUB-012`, `TEST-PUB-012`).

---

### 2. Comprehensive Requirements Matrix (Items A – AE)

| Matrix ID | Requirement Domain | Canonical Specification & Architecture Mapping | Implementation Classification | Target Files / Components |
| :--- | :--- | :--- | :--- | :--- |
| **A** | **Official Phase Name** | **Corporate Information Pages** (Canonical: "Public Website: Corporate, Governance & About Pages") | Already Supported / Ext | `docs/phase-21/01_CORPORATE_INFORMATION_PAGES_PLAN.md` |
| **B** | **Page / Module Identifiers**| `PAGE-PUB-02` (`/about`), `PAGE-PUB-07` (`/why-choose-voak`), `PAGE-PUB-08` (`/how-we-work`), `PAGE-PUB-09` (`/quality-compliance`) | Requires New Implementation | `frontend/src/apps/public/pages/` |
| **C** | **Business Objective** | Instill procurement confidence, eliminate engagement friction, objectively justify Voak selection over conventional staffing agencies, and transparently document clinical governance rigor | Requires New Implementation | Corporate Information Suite |
| **D** | **Target Audience** | Hospital Board Members, Healthcare Procurement Committees, COOs, Clinical Directors, Transition Managers, Hospital Legal Counsel | Requires New Implementation | All 4 Corporate Pages |
| **E** | **Route(s)** | `/about`, `/why-choose-voak`, `/how-we-work`, `/quality-compliance` | Requires Extension | `frontend/src/apps/public/PublicApp.tsx`, `frontend/src/shared/router/Router.tsx` |
| **F** | **Primary User Journey** | Inbound from Header/Footer/Homepage -> Evaluation of Governance & Capability -> Comparative Matrix -> Operational Timeline -> Action via Contact/Proposal CTA | Requires New Implementation | User Navigation Flow |
| **G** | **Header Requirements** | Backward-compatible `PublicHeader` with active route indicator, mobile drawer navigation, and clean deep links | Requires Extension | `frontend/src/apps/public/components/PublicHeader.tsx` |
| **H** | **Hero / Page Header** | Editorial page headers with serif typography, corporate breadcrumb trail (`Home > [Page Name]`), purpose narrative, and category pill | Requires New Implementation | `frontend/src/apps/public/components/CorporatePageHero.tsx` |
| **I** | **Main Content Sections** | 1. About: Mission/Vision/Values Bento, Leadership Grid with Modal Bios, Milestones, Footprint.<br>2. Why Voak: Advantage Bento, Comparative Procurement Table, Risk Architecture.<br>3. How We Work: 4-Stage Roadmap (Audit -> Architecture -> Deployment -> Governance), Stepper Timeline.<br>4. Quality: Mandatory Non-Clinical Care Disclaimer, Credentialing Protocol, Regulatory Alignment, Incident Escalation. | Requires New Implementation | `frontend/src/apps/public/pages/AboutPage.tsx`, `WhyChooseVoakPage.tsx`, `HowWeWorkPage.tsx`, `QualityCompliancePage.tsx` |
| **J** | **CTA Requirements** | Contextual conversion triggers on every page: "Partner With Our Leadership", "Request an Operational Feasibility Review", "Initiate Stage 1 Diagnostic", "Submit Compliance Inquiry" | Requires New Implementation | Reusable CTA Banner & Page Action Buttons |
| **K** | **Backend/Public API Sources**| `GET /api/v1/public/pages/:slug` (`slug` in `about`, `why-choose-voak`, `how-we-work`, `quality-compliance`) returning standard JSON envelope with ETag and Cache-Control | Already Supported | `backend/src/Modules/PublicContent/PublicContentController.php`, `frontend/src/shared/api/apiClient.ts` |
| **L** | **CMS Data Sources** | Database `pages` table (`slug`, `title`, `meta_title`, `meta_description`, `content_json`, `is_published`) with fallback to canonical verified content | Already Supported | `backend/database/migrations/20261021_000004_create_pages_table.php`, `PageService.php` |
| **M** | **Search Requirements** | In-page keyword scanning and filterable accordion sections; global site search integration deferred to designated search phase | Not Applicable | Static Corporate Pages |
| **N** | **Filtering Requirements** | 1. Leadership tab filter (All, Executive, Clinical Advisory).<br>2. Stepper stage selection on `/how-we-work`.<br>3. Interactive Accordions on `/quality-compliance`. | Requires New Implementation | Page-level state controls |
| **O** | **Pagination Requirements** | Corporate pages are single-scroll editorial layouts with no pagination required | Not Applicable | Editorial Layouts |
| **P** | **Detail Navigation** | Accessible modal dialog for executive leadership bios with keyboard trap, focus return, and ESC dismiss | Requires New Implementation | `frontend/src/apps/public/components/LeadershipModal.tsx` |
| **Q** | **Related Content** | Cross-linking across the 4 corporate pages: About cross-links to Quality; Why Voak cross-links to How We Work; Quality cross-links to Contact and RFP | Requires New Implementation | Contextual Cross-link Cards |
| **R** | **Media Requirements** | Authentic healthcare imagery in `frontend/public/images/`, verified SVG icons, and decorative SVG avatars for leadership profiles without broken images | Requires Extension | `frontend/public/images/` |
| **S** | **Loading States** | Shimmer skeleton placeholders matching layout geometry (`Skeleton` primitive) during initial API hydration | Already Supported / Ext | Reusable Skeletons |
| **T** | **Empty States** | Graceful fallback to canonical governance frameworks if CMS data is empty or unpublished | Requires New Implementation | Component Fallback Logic |
| **U** | **Error States** | Graceful error boundary; network errors fall back to verified canonical corporate content without breaking the page layout | Already Supported / Ext | Error Boundary & Fallback |
| **V** | **SEO Metadata** | Title tags, meta descriptions, OpenGraph tags, and Twitter Card tags tailored to each corporate page | Already Supported / Ext | `frontend/src/apps/public/seo/SeoHead.tsx` |
| **W** | **Canonical URL** | Explicit canonical URLs matching each route: `https://voakhealthcare.com.au/{about,why-choose-voak,how-we-work,quality-compliance}` | Already Supported / Ext | `SeoHead.tsx` |
| **X** | **Structured Data** | 1. About: Schema.org `AboutPage` + `MedicalOrganization`.<br>2. Why Voak: Schema.org `WebPage` + `Table`.<br>3. How We Work: Schema.org `HowTo`.<br>4. Quality: Schema.org `WebPage` + Governance metadata. | Requires New Implementation | `SeoHead.tsx` / Page Structured Data |
| **Y** | **ETag / Cache Behavior** | Transparent support for HTTP `ETag` and conditional `304 Not Modified` headers via typed `apiClient` | Already Supported | `backend/src/Services/PageService.php`, `frontend/src/shared/api/apiClient.ts` |
| **Z** | **Accessibility (WCAG 2.2 AA Target)**| WCAG 2.2 AA target maintained; Phase 21 verified semantic landmarks, heading structure, keyboard operation, modal focus management, accessible labels, focus states, and touch-target requirements. Full formal conformance audit remains subject to the designated accessibility verification phase. | Requires New Implementation | All Phase 21 Components |
| **AA**| **Responsive Design** | 0px unwanted horizontal scrollbar from 320px to 1920px; comparative table collapses into stacked cards on mobile (< 768px); 4-stage timeline transitions to vertical layout (< 1024px) | Requires New Implementation | Tailwind responsive utilities |
| **AB**| **Performance** | Zero heavy third-party carousel, chart, or animation libraries; bundle growth contained within budget; deferred runtime FCP benchmarking | Already Supported | Native Tailwind Transitions |
| **AC**| **Security** | DOMPurify / React virtual DOM XSS defense, no exposed personal emails, `rel="noopener noreferrer"` on external links, strict CSP compliance | Already Supported | Security Middleware & React VDOM |
| **AD**| **Testing Requirements** | Dedicated test suite `frontend/tests/corporate.phase21.test.tsx` verifying all 4 pages, routing, modal dialog, comparative table, timeline, accordions, accessibility, and error fallback | Requires New Implementation | `frontend/tests/corporate.phase21.test.tsx` |
| **AE**| **Acceptance Criteria** | 100% green tests (Phase 18, 19, 20, 21), 0 TypeScript errors, 0 ESLint errors/warnings, production build success, 401 backend tests passing, 0 secrets, 0 machine documentation paths | Target Phase | Verification Gate |

---

### 3. Detailed Architecture for the 4 Corporate Pages

#### 1. About Us (`PAGE-PUB-02` / `/about`)
- **Breadcrumbs**: `Home > About Us`
- **Hero**: Serif headline ("Pioneering Operational Reliability in Australian Healthcare"), mission subtext, and trust badge ("Established B2B Healthcare Infrastructure Partner").
- **Mission, Vision & Values Bento**:
  - *Mission*: Augmenting healthcare institutions with resilient workforce architecture and operational stability.
  - *Vision*: The standard of excellence in Australian institutional healthcare operations.
  - *Values (4 Bento Tiles)*:
    1. *Clinical Governance & Rigor* (Deep Navy background, Mint accents)
    2. *Operational Transparency* (Soft Purple background, Purple accents)
    3. *Workforce Resilience & Integrity* (Soft Yellow background, Yellow accents)
    4. *Collaborative Healthcare Partnership* (Soft Pink background, Pink accents)
- **Executive Leadership & Advisory Board**:
  - Profiles with name, role, clinical credentials, avatar/image, and "View Biography" trigger.
  - Interactive accessible modal (`LeadershipModal`) with bio, clinical experience, focus trapping, Escape dismissal, and return of focus to trigger.
- **National Footprint & Capability**:
  - Overview of hospital network, aged care, and regional health service support across NSW, VIC, QLD, WA, SA, and regional Australia.
- **Milestone Timeline**:
  - 2020: Foundation & Clinical Advisory Governance Inception
  - 2022: Multi-Sector Clinical Network Integration
  - 2024: Digital Service Level Agreement Telemetry & Real-Time Roster Relief
  - 2026: Enterprise National B2B Healthcare Network Footprint
- **Non-Clinical Care Boundary Disclaimer**:
  - Clear, prominent disclaimer emphasizing B2B operational support rather than direct primary patient care.
- **CTA**: "Partner With Our Leadership Team" -> `/partner-with-us` or `/request-proposal`.

#### 2. Why Choose Voak (`PAGE-PUB-07` / `/why-choose-voak`)
- **Breadcrumbs**: `Home > Why Choose Voak`
- **Hero**: "Objective Procurement Justification & Operational Superiority"
- **The Voak Advantage Bento (4 Pillars)**:
  1. *Clinical Governance Depth*: Multi-stage AHPRA verification and audit trail.
  2. *Operational Speed & Fill Rates*: 99.8% verified SLA fulfillment rate.
  3. *Cost & Billing Transparency*: Clear fee structure without hidden agency markups.
  4. *Continuity & Retention*: Dedicated workforce pools ensuring patient care stability.
- **Comparative Procurement Matrix**:
  - Table contrasting:
    - *Dimension 1: Practitioner Vetting* (Voak: 5-Tier AHPRA & Police Audit vs. Agency: Basic check vs. In-House: High internal overhead)
    - *Dimension 2: SLA Response Time* (Voak: Under 2 hours guaranteed vs. Agency: Uncommitted vs. In-House: Subject to staff availability)
    - *Dimension 3: Clinical Governance Oversight* (Voak: Dedicated Clinical Advisory Board vs. Agency: None/Brokerage vs. In-House: Limited clinical bandwidth)
    - *Dimension 4: Fee Structure Transparency* (Voak: Fixed transparent rate card vs. Agency: Dynamic surge markups vs. In-House: Complex fixed payroll overheads)
    - *Dimension 5: Technology & Roster Integration* (Voak: Automated REST API & telemetry vs. Agency: Manual phone calls vs. In-House: Disparate internal software)
    - *Dimension 6: Clinical Retention & Continuity* (Voak: 92% retention rate pool vs. Agency: High transient turnover vs. In-House: High burnout/exhaustion)
  - Responsive: Full table on desktop (`min-w-[640px]` or stacked card comparison on mobile `< 768px`) with `scope="col"` and `scope="row"` headers.
- **Risk Mitigation & Continuity Architecture**:
  - Roster surge buffers, crisis back-up pools, and automated incident triage.
- **Executive CTA**: "Request an Operational Feasibility Review" -> `/request-proposal`.

#### 3. How We Work (`PAGE-PUB-08` / `/how-we-work`)
- **Breadcrumbs**: `Home > How We Work`
- **Hero**: "A Structured 4-Stage Operational Integration Roadmap"
- **Visual 4-Stage Stepper & Roadmap**:
  - *Stage 1: Diagnostic Assessment & Operational Audit* (Weeks 1–2)
    - Objectives: Facility workflow analysis, roster bottleneck identification, compliance audit.
    - Deliverables: Comprehensive Diagnostic Report, Risk Gap Register, Recommended Workforce Model.
    - Governance Gate: Joint Executive Scope Sign-off.
  - *Stage 2: Bespoke Solution Architecture & SLA Design* (Weeks 3–4)
    - Objectives: Tailored rate card formulation, SLA matrix definitions, escalation protocol agreement.
    - Deliverables: Service Level Agreement (SLA) Charter, Credentialing Protocol Agreement.
    - Governance Gate: Procurement & Legal Approval.
  - *Stage 3: Phased Integration & Credentialed Deployment* (Weeks 5–8)
    - Objectives: Workforce onboarding, facility orientation, digital roster telemetry sync.
    - Deliverables: Roster Deployment Schedule, 24/7 Dedicated Account Lead Allocation.
    - Governance Gate: Clinical Governance Readiness Certificate.
  - *Stage 4: Continuous Quality Governance & Performance Telemetry* (Ongoing)
    - Objectives: Real-time SLA fulfillment tracking, quarterly clinical audits, annual optimization review.
    - Deliverables: Monthly Telemetry Dashboards, Quarterly Quality Audit Reports.
    - Governance Gate: Executive Performance Review.
  - Features: Interactive stage switching with accordion/tabs, ordered list `<ol>` markup, vertical timeline on mobile `< 1024px`.
- **Client Onboarding Timeline & Transition Expectations**: Clear milestone breakdown.
- **CTA**: "Initiate Stage 1 Diagnostic Consultation" -> `/request-proposal`.

#### 4. Quality & Compliance (`PAGE-PUB-09` / `/quality-compliance`)
- **Breadcrumbs**: `Home > Quality & Compliance`
- **Hero**: "Uncompromising Clinical Governance & Regulatory Compliance"
- **Mandatory High-Contrast Non-Clinical Boundary Notice**:
  > **LEGAL NOTICE & OPERATIONAL BOUNDARY**:  
  > Voak Health Care is a specialized B2B healthcare operational services provider and does not provide direct patient clinical care, emergency medical services, or patient booking channels. All clinical practitioners supplied operate strictly under the operational governance, clinical supervision, and institutional policies of the client healthcare facility.
- **Clinical Credentialing Protocols (WAI-ARIA Expandable Accordions)**:
  1. *AHPRA Registration & Standing Verification* (Live daily registry check, conditions monitoring)
  2. *Identity & Working Rights Certification* (100-point identity verification, VEVO checks)
  3. *Criminal History & Vulnerable Persons Checks* (National Police Check, WWCC, NDIS Worker Screening)
  4. *Immunization & Serology Compliance* (Hep B, MMR, Varicella, dTpa, COVID-19, annual Influenza)
  5. *Multi-Tier Clinical Reference Audits* (Minimum two primary clinical supervisor references)
- **Australian Regulatory Standards Alignment**:
  - NSQHS Standards (National Safety and Quality Health Service Standards)
  - Aged Care Quality Standards
  - NDIS Quality and Safeguards Commission Standards
  - Australian Privacy Principles (APPs, Privacy Act 1988)
- **Clinical Incident Management & Escalation Framework**:
  - Severity Level 1 to 4 multi-tier escalation pathway with mandatory clinical director notification within 1 hour.
- **Compliance Inquiry CTA**: "Submit a Governance or Compliance Inquiry" -> `/contact?topic=compliance`.

---

### 4. Implementation Steps & Verification Strategy

1. **Phase 21 Plan Creation**: Save this canonical plan to `docs/phase-21/01_CORPORATE_INFORMATION_PAGES_PLAN.md`.
2. **Component Architecture**:
   - Create `frontend/src/apps/public/components/CorporateBreadcrumbs.tsx`.
   - Create `frontend/src/apps/public/components/CorporatePageHero.tsx`.
   - Create `frontend/src/apps/public/components/LeadershipModal.tsx`.
   - Create `frontend/src/apps/public/pages/AboutPage.tsx`.
   - Create `frontend/src/apps/public/pages/WhyChooseVoakPage.tsx`.
   - Create `frontend/src/apps/public/pages/HowWeWorkPage.tsx`.
   - Create `frontend/src/apps/public/pages/QualityCompliancePage.tsx`.
3. **Routing Integration**:
   - Update `frontend/src/apps/public/PublicApp.tsx` to mount routes for `/about`, `/why-choose-voak`, `/how-we-work`, `/quality-compliance` alongside `/` (`HomePage`).
   - Update `frontend/src/apps/public/components/PublicHeader.tsx` and `PublicFooter.tsx` with seamless routing links.
4. **Automated Testing Suite**:
   - Create `frontend/tests/corporate.phase21.test.tsx` testing all 4 pages, routing, modal bios, comparative table, timeline stepper, compliance accordions, and API fallback.
5. **Quality & Regression Checks**:
   - `npm run typecheck`
   - `npm run lint`
   - `npm test -- --run`
   - `npm run build`
   - `php backend/tests/run_tests.php`
   - `php tests/scan_secrets.php`
   - `composer validate --strict`
   - Recursive scan of `docs/**/*.md` for 0 machine-specific paths.
6. **Git Commit & CI Verification**:
   - Commit: `feat(phase-21): implement corporate information pages`
   - Push to `origin main`.
   - Monitor GitHub Actions CI matrix on the exact commit SHA until all 4 jobs pass.
7. **Verification Report Creation**:
   - Create `docs/phase-21/02_PHASE_21_VERIFICATION_REPORT.md` and certify Phase 21.
