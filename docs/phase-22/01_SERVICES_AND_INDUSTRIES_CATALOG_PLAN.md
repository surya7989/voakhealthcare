# Phase 22 Plan: Services & Industries Catalog
## Voak Health Care Enterprise Web Application

**Document Identifier**: `PLAN-PHASE22-CATALOG-001`  
**Phase Identifier**: `PHASE-22` / `PUB-CATALOG-001`  
**Module Identifiers**: `PAGE-PUB-03`, `PAGE-PUB-04`, `PAGE-PUB-05`, `PAGE-PUB-06`  
**Target Environment**: `frontend/src/apps/public/` (React 18.3.1 / TypeScript strict / Vite 5.4.21 / Tailwind CSS v3)  
**Creation Date**: 2026-10-24  
**Author**: Antigravity Autonomous Agent  
**Verified Phase 21 Certified HEAD**: `87bd62a600ef38031d3bf09b1a973f51248a62c2` (CI Run `40079588006` — SUCCESS)  
**Database Migrations Applied**: 28 migrations applied / 0 pending (ZERO new migrations required)  

---

### 1. Executive Summary & Canonical Roadmap Alignment

Phase 22 is officially designated as **Services & Industries Catalog** (Canonical: "Public Website: Services & Industries Catalog Pages", `FR-PUB-003`, `FR-PUB-005`, `FR-PUB-006`, `FR-PUB-007`, `FR-PUB-008`). It succeeds the certified Phase 21 Corporate Information Pages and delivers the four canonical public catalog and detail pages for Voak Health Care:

1. **`PAGE-PUB-03` (`/services`)**: Services Overview Catalog — Filterable grid of all 6 core B2B healthcare services categorized by operational type, with category pill filter, keyword search, deliverables summary, SLA pledge banner, and direct proposal triggers (`PUB-007`, `FR-PUB-005`, `TEST-PUB-005`).
2. **`PAGE-PUB-04` (`/services/:slug`)**: Dynamic Service Detail Pages — In-depth architectural breakdown of each service pillar, including deliverables checklist, operational methodology, compliance frameworks, SLA benchmarks, applicable industry cross-links, contextual RFP prompt, and sibling services carousel (`PUB-008`, `FR-PUB-006`, `TEST-PUB-006`).
3. **`PAGE-PUB-05` (`/industries`)**: Industries We Serve Overview Catalog — Comprehensive directory of institutional client verticals (Hospitals & Health Services, Residential Aged Care, Primary Care & General Practice, Allied Health Networks, Diagnostic & Pathology Centers, Corporate & Occupational Health), pain points matrix, and strategic consultation CTA (`PUB-009`, `FR-PUB-007`, `TEST-PUB-007`).
4. **`PAGE-PUB-06` (`/industries/:slug`)**: Dynamic Industry Detail Pages — Tailored industry landing pages addressing sector-specific regulatory challenges (NSQHS, Aged Care Quality Standards, RACGP, NDIS, NATA/ISO 15189, WHS/Comcare), Voak solutions architecture, mapped service packages, and consultation callout (`PUB-010`, `FR-PUB-008`, `TEST-PUB-008`).

---

### 2. Comprehensive Requirements Matrix (Items A – AJ)

| Matrix ID | Requirement Domain | Canonical Specification & Architecture Mapping | Implementation Classification | Target Files / Components |
| :--- | :--- | :--- | :--- | :--- |
| **A** | **Official Phase Name** | **Services & Industries Catalog** (Canonical: "Public Website: Services & Industries Catalog Pages", `PHASE-22`) | Already Supported / Ext | `docs/phase-22/01_SERVICES_AND_INDUSTRIES_CATALOG_PLAN.md` |
| **B** | **Business Objective** | Enable healthcare decision-makers to evaluate clinical workforce and operational capabilities, review SLA benchmarks, and initiate contextual procurement RFPs | Requires New Implementation | Services & Industries Suite |
| **C** | **Canonical Page IDs** | `PAGE-PUB-03` (`/services`), `PAGE-PUB-04` (`/services/:slug`), `PAGE-PUB-05` (`/industries`), `PAGE-PUB-06` (`/industries/:slug`) | Requires New Implementation | `frontend/src/apps/public/pages/` |
| **D** | **Service Catalog Route** | `/services` (Canonical: `https://voakhealthcare.com.au/services`) | Requires Extension | `PublicApp.tsx`, `Router.tsx` |
| **E** | **Service Detail Route** | `/services/:slug` (Dynamic slug, e.g., `/services/clinical-workforce-solutions`) | Requires New Implementation | `PublicApp.tsx`, `ServiceDetailPage.tsx` |
| **F** | **Industry Catalog Route** | `/industries` (Canonical: `https://voakhealthcare.com.au/industries`) | Requires Extension | `PublicApp.tsx`, `Router.tsx` |
| **G** | **Industry Detail Route** | `/industries/:slug` (Dynamic slug, e.g., `/industries/hospitals-health-services`) | Requires New Implementation | `PublicApp.tsx`, `IndustryDetailPage.tsx` |
| **H** | **Listing Layout** | Services: Hero, Category Filter Bar, SearchInput, 6-pillar card grid, SLA Pledge Banner, Proposal CTA.<br>Industries: Hero, 6-sector bento grid, Sector capabilities matrix, Consultation CTA banner | Requires New Implementation | `ServicesPage.tsx`, `IndustriesPage.tsx` |
| **I** | **Detail Layout** | Service Detail: Hero, Deliverables checklist, Methodology, Governance, SLA table, Related industries, Contextual RFP card, Sibling services.<br>Industry Detail: Sector hero, Regulatory frameworks, Solutions architecture, Mapped services, Engagement workflow | Requires New Implementation | `ServiceDetailPage.tsx`, `IndustryDetailPage.tsx` |
| **J** | **Search** | Client-side keyword search across titles, summaries, and deliverables/pain points | Requires New Implementation | `SearchInput.tsx`, Catalog Pages |
| **K** | **Filters** | Services: Category Filter Bar (`All`, `Clinical Workforce`, `Facility Operations`, `Health Administration`, `Clinical Governance`, `Logistics & Supply`, `Consulting & Advisory`). URL query parameter synchronization (`?category=...`).<br>Industries: Sector category filtering and reset filters | Requires New Implementation | `FilterBar.tsx`, Catalog Pages |
| **L** | **Sorting** | Canonical `display_order` ASC, `id` ASC matching backend database order | Already Supported | API response order |
| **M** | **Pagination** | Single-scroll view for 6 canonical pillars; accessible `Pagination` component integration when filtered count exceeds pagination threshold | Requires Extension | `Pagination.tsx` |
| **N** | **Category / Taxonomy** | 6 verified service pillars and 6 verified industry verticals seeded in Phase 8 & 9 | Already Supported | Backend DB & API |
| **O** | **Related Services** | Mapped services displayed on Industry Detail pages and sibling service recommendations on Service Detail pages | Requires New Implementation | `ServiceDetailPage.tsx`, `IndustryDetailPage.tsx` |
| **P** | **Related Industries** | Mapped sectors displayed on Service Detail pages from `service_industry_mappings` | Requires New Implementation | `ServiceDetailPage.tsx` |
| **Q** | **CTA Behavior** | Contextual deep links: `/request-proposal?service=:id` and `/request-proposal?sector=:slug` pre-populating proposal parameters | Requires New Implementation | All 4 catalog/detail pages |
| **R** | **Breadcrumbs** | Accessible breadcrumb trails: `Home > Services`, `Home > Services > :service_title`, `Home > Industries`, `Home > Industries > :industry_name` | Already Supported / Ext | `Breadcrumb.tsx` |
| **S** | **Header / Footer** | Shared `PublicHeader` and `PublicFooter` with contextual route links and active navigation state | Requires Extension | `PublicHeader.tsx`, `PublicFooter.tsx` |
| **T** | **API Integration** | `GET /api/v1/public/services`, `GET /api/v1/public/services/:slug`, `GET /api/v1/public/industries`, `GET /api/v1/public/industries/:slug` via typed `apiClient` | Already Supported | `apiClient.ts` |
| **U** | **CMS Source** | Dynamic database tables `services`, `industries`, `service_industry_mappings` with verified fallback data | Already Supported | Phase 8 & 9 Backend Services |
| **V** | **Loading States** | 6-card shimmer skeleton grid for catalogs; full-page structured skeleton for detail views | Already Supported / Ext | `Skeleton.tsx` |
| **W** | **Empty States** | Standardized `EmptyState` component with "Reset Filters" action button when zero items match search/filter | Already Supported / Ext | `EmptyState.tsx` |
| **X** | **Error States** | Graceful fallback rendering canonical static data on network failure; error retry banner | Already Supported / Ext | `ErrorState.tsx` |
| **Y** | **404 Behavior** | Authentic semantic 404 view for unknown, inactive, or unpublished service/industry slugs | Requires New Implementation | `NotFoundPage.tsx` / 404 View |
| **Z** | **SEO Metadata** | Unique title, description, canonical URL, OpenGraph tags, Twitter Card tags via `SeoHead` | Already Supported / Ext | `SeoHead.tsx` |
| **AA**| **Canonical URLs** | Standardized `https://voakhealthcare.com.au/{services,services/:slug,industries,industries/:slug}` | Already Supported / Ext | `SeoHead.tsx` |
| **AB**| **Structured Data** | Schema.org `ItemList` on `/services`, `Service` on `/services/:slug`, `CollectionPage` on `/industries`, `MedicalWebPage` on `/industries/:slug` | Requires New Implementation | `SeoHead.tsx` |
| **AC**| **ETag / Caching** | Transparent `If-None-Match` and `304 Not Modified` handling via `apiClient` | Already Supported | `apiClient.ts` |
| **AD**| **Accessibility (WCAG 2.2 AA Target)** | WCAG 2.2 AA target maintained; Phase 22 verified semantic landmarks, heading structure, keyboard operation, accessible filter chips, live regions, focus states, and touch-target requirements. Full formal conformance audit remains subject to the designated accessibility verification phase. | Requires New Implementation | All Phase 22 Components |
| **AE**| **Responsive Design** | 0px unwanted horizontal scrollbar from 320px to 1920px; responsive grid reflow (1-col mobile, 2-col tablet, 3-col desktop); side-by-side detail sidebar collapses beneath content on mobile | Requires New Implementation | Tailwind responsive utilities |
| **AF**| **Media / Images** | Authentic healthcare imagery, verified SVG clinical icons, decorative visual accents | Already Supported / Ext | `frontend/public/images/` |
| **AG**| **Performance** | Zero heavy third-party carousel, chart, or animation libraries; bundle growth tightly controlled | Already Supported | Native Tailwind Transitions |
| **AH**| **Security** | DOMPurify / React virtual DOM XSS defense, URL query param sanitization, safe external links (`rel="noopener noreferrer"`) | Already Supported | Frontend Security Controls |
| **AI**| **Testing Requirements** | Dedicated test suite `frontend/tests/services_industries.phase22.test.tsx` verifying all 4 routes, category filtering, search, dynamic slug resolution, 404 handling, mapped relationships, and accessibility | Requires New Implementation | `services_industries.phase22.test.tsx` |
| **AJ**| **Acceptance Criteria** | 100% green tests (Phase 18-22), 0 TypeScript errors, 0 ESLint errors/warnings, production build success, 401 backend tests passing, 0 secrets, 0 machine documentation paths | Target Phase | Verification Gate |

---

### 3. Detailed Architecture for the 4 Catalog & Detail Pages

#### 1. Services Overview (`PAGE-PUB-03` / `/services`)
- **Breadcrumbs**: `Home > Services`
- **Hero**: Serif headline ("Enterprise Healthcare Services & Clinical Solutions"), purpose narrative, and category pill ("Comprehensive Healthcare Offerings").
- **Search & Category Filter Bar**:
  - Search input with clear button and live result count.
  - Categories: `All Services`, `Clinical Workforce`, `Facility Operations`, `Health Administration`, `Clinical Governance`, `Logistics & Supply`, `Consulting & Advisory`.
  - Filter updates URL query parameter (`?category=...`) via shallow routing.
  - Screen reader announcement via `aria-live="polite"` ("Showing X services").
- **Services Grid**:
  - 6 service cards displaying title, category badge, tagline, key deliverables summary checklist, and "Explore Service Details" button.
  - Direct contextual link to `/request-proposal?service=:id`.
- **SLA & Governance Pledge Banner**:
  - Highlights Voak's 100% credential audit guarantee, rapid shift turnaround, and clinical advisory governance.
- **Configurable Proposal Prompt**:
  - High-impact B2B callout guiding procurement officers to custom operational feasibility scoping.

#### 2. Individual Service Detail Page (`PAGE-PUB-04` / `/services/:slug`)
- **Breadcrumbs**: `Home > Services > :service_title`
- **Hero**:
  - Category badge, serif H1 title, summary tagline, and dual CTAs ("Request Scope Proposal", "Schedule Discovery Call").
- **Deliverables & Scope Checklist**:
  - Detailed list of core clinical and operational deliverables with checkmark icons.
- **Operational Methodology & Workflow Integration**:
  - 3-step procedural framework describing how Voak deploys and integrates this service within hospital/facility environments.
- **Compliance, Credentialing & Standards**:
  - Explicit AHPRA, NSQHS, or relevant statutory oversight standards for this service.
- **Service Level Agreements (SLA) & Benchmarks**:
  - Structured SLA metrics table/cards (e.g., Urgent Fill Rate >= 98.5%, Credential Audit 100%, Escalation Window < 15 Min).
- **Applicable Industries**:
  - Badges and cards cross-linking to relevant sector pages (e.g., `/industries/hospitals-health-services`, `/industries/residential-aged-care`).
- **Contextual RFP Prompt Card**:
  - Pre-populates proposal route: `/request-proposal?service_id=:id&service=:slug`.
- **Sibling / Related Services**:
  - Grid showing other service pillars with quick navigation links.
- **Semantic 404 Handling**:
  - If service slug is invalid, missing, or unpublished, renders semantic branded 404 state with recovery action.

#### 3. Industries Overview (`PAGE-PUB-05` / `/industries`)
- **Breadcrumbs**: `Home > Industries`
- **Hero**:
  - Headline ("Healthcare Sectors & Institutional Verticals"), narrative overview of tailored operational models.
- **Sector Search & Filter Bar**:
  - Search by sector name or challenge keyword; reset button.
- **6-Sector Bento Grid**:
  - *Hospitals & Health Services*: Acute care, tertiary networks, emergency departments.
  - *Residential Aged Care Facilities*: ACQSC compliance, 24/7 RN mandates, 215 care minutes.
  - *Primary Care & General Practice*: RACGP standards, MBS billing, practice nursing.
  - *Allied Health Networks*: NDIS Practice Standards, therapy coordination, multi-clinic operations.
  - *Diagnostic & Pathology Centers*: ISO 15189, NATA accreditation, specimen logistics.
  - *Corporate & Occupational Health*: WHS Act, onsite clinical surveillance, flu programs.
- **Cross-Sector Capabilities Matrix**:
  - Highlighting shared clinical governance, automated timesheet verification, and 24/7 clinical coordinator support.
- **Strategic Consultation CTA Banner**:
  - Guidance to schedule an institutional briefing with Voak sector leads.

#### 4. Individual Industry Detail Page (`PAGE-PUB-06` / `/industries/:slug`)
- **Breadcrumbs**: `Home > Industries > :industry_name`
- **Sector Hero**:
  - Industry name, regulatory overview, and key operational focus.
- **Regulatory Landscape & Statutory Burdens**:
  - Governing body (e.g., ACSQHC, ACQSC, RACGP, NDIS, NATA, Safe Work Australia).
  - Specific legislative and accreditation standards.
- **Sector Pain Points vs. Voak Solutions**:
  - Structured comparative cards detailing industry challenges (e.g., 24/7 RN mandate, bed-block, NATA audits) paired with Voak turnkey operational interventions.
- **Recommended Service Packages**:
  - Cards rendering mapped services from `service_industry_mappings` with direct deep links to `/services/:slug`.
- **Operational Transformation Workflow**:
  - Phased facility onboarding pathway (Diagnostic Assessment -> Protocol Calibration -> Workforce Integration).
- **Consultation Callout**:
  - Direct contextual link to `/request-proposal?sector=:slug` or `/contact?topic=industries`.
- **Semantic 404 Handling**:
  - If industry slug is invalid, missing, or unpublished, renders semantic branded 404 state.

---

### 4. Implementation Steps & Verification Strategy

1. **Plan Documentation**: Save this canonical plan to `docs/phase-22/01_SERVICES_AND_INDUSTRIES_CATALOG_PLAN.md`.
2. **Component & Page Architecture**:
   - `frontend/src/apps/public/pages/ServicesPage.tsx` (`PAGE-PUB-03`)
   - `frontend/src/apps/public/pages/ServiceDetailPage.tsx` (`PAGE-PUB-04`)
   - `frontend/src/apps/public/pages/IndustriesPage.tsx` (`PAGE-PUB-05`)
   - `frontend/src/apps/public/pages/IndustryDetailPage.tsx` (`PAGE-PUB-06`)
   - `frontend/src/apps/public/pages/NotFoundPage.tsx` (Reusable semantic 404 page)
3. **Routing Integration**:
   - Update `frontend/src/apps/public/PublicApp.tsx` to handle:
     - `/services` -> `<ServicesPage />`
     - `/services/:slug` -> `<ServiceDetailPage />`
     - `/industries` -> `<IndustriesPage />`
     - `/industries/:slug` -> `<IndustryDetailPage />`
   - Update `PublicHeader.tsx` and `PublicFooter.tsx` with seamless routing links.
4. **Automated Testing Suite**:
   - Create `frontend/tests/services_industries.phase22.test.tsx` verifying all 4 pages, category filtering, search, dynamic slug resolution, 404 handling, mapped relationships, and accessibility landmarks.
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
   - Commit: `feat(phase-22): implement service and industry catalogs`
   - Push to `origin main`.
   - Monitor GitHub Actions CI matrix on the exact commit SHA until all 4 jobs pass.
7. **Verification Report Creation**:
   - Create `docs/phase-22/02_PHASE_22_VERIFICATION_REPORT.md` and certify Phase 22.
