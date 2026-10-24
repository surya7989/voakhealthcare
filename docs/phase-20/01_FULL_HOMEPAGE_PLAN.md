# Phase 20 — Full Homepage Implementation Plan
## Voak Health Care Enterprise Web Application

**Document Identifier**: `DOC-PHASE20-PLAN-001`  
**Phase Identifier**: `PHASE-20` / `PUB-HOME-001` / `PAGE-PUB-01`  
**Target Environment**: `frontend/src/apps/public/` (React 18 / TypeScript strict / Tailwind CSS v3)  
**Creation Date**: 2026-10-24  
**Author**: Antigravity Autonomous Agent  
**Baseline HEAD**: `0c8cf788081fa0b5a4c61d1b7add9d41cdc027b3`  
**Status**: Ready for Implementation  

---

### 1. Executive Summary & Canonical Authority

Following full verification and sign-off of **Phase 19 (Complete UI Component System)**, the project advances to **Phase 20: Full Homepage**.

Per authoritative project documentation:
- `docs/phase-00/03_PUBLIC_WEBSITE_SITEMAP.md`
- `docs/phase-00/07_TECHNICAL_ARCHITECTURE.md`
- `docs/phase-00/13_COMPLETE_32_PHASE_TRACEABILITY.md`
- `docs/phase-01/01_MASTER_FUNCTIONAL_REQUIREMENTS.md`
- `docs/phase-01/02_PUBLIC_PAGE_BY_PAGE_SPECIFICATIONS.md` (`PAGE-PUB-01`)
- `docs/phase-03/14_DESIGN_TO_REACT_COMPONENT_MAPPING.md`
- `docs/phase-03/21_HOMEPAGE_VISUAL_PARITY_SIGN_OFF_REPORT.md` (Approved Second Reference Image Benchmark)
- `design/phase-03/index.html` (Master Prototype V10)

**Core Phase 20 Objective**:
Implement the canonical, responsive, accessible, API-integrated, and SEO-optimized public corporate homepage (`PAGE-PUB-01` at `/`), replacing the placeholder in `frontend/src/apps/public/PublicApp.tsx` with the approved Master Prototype layout and corporate design token system.

**Scope Governance**:
- Reusable presentation primitives implemented in Phase 19 (`Button`, `BentoCard`, `Badge`, `Card`, `Drawer`, `Accordion`, `Skeleton`, etc.) will be consumed directly.
- Does **NOT** build full multi-page public portals (Individual Service detail pages, Careers portal, RFP wizard, or Article pages scheduled for Phases 21–24).
- Does **NOT** implement Phase 26 Complete Admin Operations Console.
- Does **NOT** introduce database migrations (database remains locked at 28 migrations).
- Does **NOT** deploy to Hostinger.

---

### 2. Comprehensive Requirements Matrix (Items A through AM)

| Matrix Item | Dimension | Canonical Specification | Classification |
|---|---|---|---|
| **A** | **Official Phase 20 Name** | Full Homepage (`PHASE-20` / `PAGE-PUB-01`) | Target Phase |
| **B** | **Homepage Business Objective** | Establish immediate executive credibility, articulate Voak's high-reliability B2B healthcare operational capabilities, present configurable service pillars and industry verticals, and drive high-intent RFP submissions and partnership inquiries | Target Phase |
| **C** | **Target Audience** | Healthcare Executives (CEOs, COOs), Hospital Procurement Directors, Clinical Practice Owners, Aged Care Facility Operators | Target Phase |
| **D** | **Primary User Journeys** | 1. Hero -> RFP/Partner Inquiry (`/contact` or `/request-proposal`); 2. Services exploration (`#services`); 3. Industry discovery (`#industries`); 4. Administration portal access (`/admin`) | Requires Implementation |
| **E** | **Header/Navigation** | Semantic `<header>` with sticky blur backdrop, logo lockup, main nav links (Home, About, Services, Industries, How We Work, Contact), "Partner With Us" CTA, mobile hamburger trigger | Requires Implementation |
| **F** | **Hero Section** | Asymmetrical hero layout matching Approved Reference Image 2: left copy block with "B2B HEALTHCARE SERVICES" pill, H1 "Better Healthcare Together", lead copy, dual CTAs; right visual stage with sparkle star, pastel backdrops, clinician portrait, floating heart badge, and facility card | Requires Implementation |
| **G** | **Hero Content Source** | Dynamic API integration with `GET /api/v1/public/homepage` with robust canonical fallback | Requires Implementation |
| **H** | **Primary CTA** | "Explore Our Services" / "Partner With Us" | Already Supported / Ext |
| **I** | **Secondary CTA** | "Watch Video" or "Schedule Consultation" | Already Supported / Ext |
| **J** | **Trust Indicators** | Validated operational metric band derived from Phase 7 canonical CMS contract in `backend/src/Services/HomepageService.php` (120+ Hospital Networks, 450+ Credentialed Specialists, 1.2M+ Clinical Hours Delivered, 99.8% SLA Fulfillment Rate) | Requires Implementation |
| **K** | **Service Presentation** | 3-Card Pastel Feature Band (Services, Industries, Partner) + 8-tile asymmetric Services Grid (Clinical Support, Allied Health, Staffing, Operations, Practice Management, Health Tech, Consulting, Training) | Requires Implementation |
| **L** | **Industry Presentation** | 4-card bento grid for key healthcare verticals (Hospitals, Aged Care, Community Care, Corporate Health) | Requires Implementation |
| **M** | **Statistics / KPI Section** | Horizontal KPI band displaying validated operational metrics | Requires Implementation |
| **N** | **About / Mission Section** | Horizontal trust section with circular operations portrait, value proposition narrative, and "Why Voak" credentials | Requires Implementation |
| **O** | **Differentiators** | Rigorous Clinical Credentialing, Scalable On-Demand Deployment, 24/7 Continuity, Transparent Governance | Requires Implementation |
| **P** | **Process / How It Works** | 4-Stage Operational Delivery Framework (Discovery, Solution Architecture, Deployment, Continuous Governance) | Requires Implementation |
| **Q** | **Case Study / Social Proof** | Conditional spotlight block; collapses cleanly if zero verified/published records exist in CMS (`FR-PUB-004`) | Requires Implementation |
| **R** | **Testimonials** | Zero fake testimonials; collapses cleanly if unpublished in CMS | Requires Implementation |
| **S** | **Partnership / RFP CTA** | Conversion banner with dual CTA buttons ("Request Formal Proposal", "Schedule Discovery Call") | Requires Implementation |
| **T** | **Careers CTA** | Dedicated link in navigation and global footer | Already Supported / Ext |
| **U** | **Contact CTA** | Prominent CTAs in header, hero, conversion banner, and footer | Requires Implementation |
| **V** | **FAQ Preview** | Collapsible accordion preview using Phase 19 `Accordion` primitive | Requires Implementation |
| **W** | **Article Preview** | 3 recent published insights from `GET /api/v1/public/articles` | Requires Implementation |
| **X** | **Footer** | Enterprise 5-column footer with brand logo, address, Services, Industries, Governance, Administration links, copyright, and legal links | Requires Implementation |
| **Y** | **Mobile Navigation** | Slide-over `Drawer` component from Phase 19 with focus trap, ESC listener, and mobile quick actions | Requires Implementation |
| **Z** | **API Integrations** | `GET /api/v1/public/homepage`, `GET /api/v1/public/services`, `GET /api/v1/public/industries`, `GET /api/v1/public/articles`, `GET /api/v1/public/settings` | Requires Implementation |
| **AA**| **Loading States** | Shimmer pulse `Skeleton` cards matching bento layout geometries | Already Supported |
| **AB**| **Error States** | Graceful fallback rendering static server-side default data on network failure; non-blocking recovery | Already Supported |
| **AC**| **Empty States** | Clean DOM collapse of unpublished sections without visual voids or broken image frames | Already Supported |
| **AD**| **SEO Metadata** | Document title, meta description, canonical URL, OpenGraph tags, and Twitter Card tags | Requires Implementation |
| **AE**| **Structured Data** | Schema.org `MedicalOrganization` JSON-LD schema matching Phase 16 contract | Requires Implementation |
| **AF**| **ETag / Caching** | Centralized `apiClient` transparent conditional GET validation | Already Supported |
| **AG**| **Accessibility (WCAG 2.2 AA)**| Single logical H1, landmark elements (`header`, `nav`, `main`, `footer`), 44px touch targets, visible focus rings, skip link | Requires Implementation |
| **AH**| **Responsive Behavior** | 0px horizontal blowout across Mobile (< 768px), Tablet (768–1024px), Desktop (>= 1024px) | Requires Implementation |
| **AI**| **Image / Media Behavior** | Real clinical imagery in `frontend/public/images/`, meaningful alt tags, fixed aspect ratios to prevent layout shift | Requires Implementation |
| **AJ**| **Performance** | Performance-oriented implementation verified through bundle-size/build checks; runtime FCP benchmark deferred until designated browser performance verification phase | Requires Implementation |
| **AK**| **Animation / Motion** | Smooth CSS transitions on hover; respect `prefers-reduced-motion` | Requires Implementation |
| **AL**| **Testing Requirements** | Comprehensive Vitest suite testing component rendering, API integration, loading states, error fallbacks, and accessibility | Requires Implementation |
| **AM**| **Acceptance Criteria** | 100% green tests, 0 lint errors, 0 typecheck errors, successful production build, 100% green backend regression | Target Phase |

---

### 3. Architecture & File Structure

```
frontend/src/apps/public/
├── PublicApp.tsx                 # Public application router & layout wrapper
├── pages/
│   └── HomePage.tsx              # Full Homepage Page Component (PAGE-PUB-01)
├── components/
│   ├── PublicHeader.tsx          # Sticky corporate header & mobile drawer trigger
│   ├── PublicFooter.tsx          # Enterprise 5-column footer
│   ├── HeroSection.tsx           # Approved Image 2 hero with clinical collage
│   ├── FeatureCardsSection.tsx   # 3-card pastel feature banner
│   ├── ServicesBentoSection.tsx  # Asymmetric 8-tile services bento grid
│   ├── TrustSection.tsx          # "Why Voak" operations portrait & metrics
│   ├── IndustriesSection.tsx     # 4-card industry bento grid
│   ├── WorkflowSection.tsx       # 4-stage operational delivery framework
│   ├── InsightsSection.tsx       # Recent articles preview
│   └── CtaBannerSection.tsx      # Pre-footer executive conversion banner
└── seo/
    └── SeoHead.tsx               # Client-side dynamic document title, meta tags, and JSON-LD
```

---

### 4. Implementation Steps

1. **Step 1: Public Shell & SEO Integration**
   - Create `frontend/src/apps/public/seo/SeoHead.tsx` managing `document.title`, meta tags, canonical link, and JSON-LD `MedicalOrganization` structured data.
   - Implement `PublicHeader.tsx` and `PublicFooter.tsx` reusing Phase 19 `Button`, `IconButton`, `Drawer`.
2. **Step 2: Homepage Sections Implementation**
   - Build `HeroSection.tsx` replicating the exact layout, copy, floating cards, and clinician portrait of Approved Reference Image 2.
   - Build `FeatureCardsSection.tsx` (Pastel Pink, Yellow, Mint Green cards).
   - Build `ServicesBentoSection.tsx` (Asymmetric 8-tile bento layout with API data).
   - Build `TrustSection.tsx` (Operations manager circle portrait, copy, KPI metrics).
   - Build `IndustriesSection.tsx` (Public & Private Hospitals, Aged Care, Community Care, Corporate Health).
   - Build `WorkflowSection.tsx` (4-stage Operational Discovery, Solution Architecture, Deployment, Continuous Governance).
   - Build `InsightsSection.tsx` (Articles preview from `/api/v1/public/articles`).
   - Build `CtaBannerSection.tsx` (Dual CTA conversion banner).
   - Assemble `HomePage.tsx` combining all sections with loading skeletons, API fetching, and error fallbacks.
   - Update `PublicApp.tsx` to render `HomePage` at `/` while routing other public paths cleanly.
3. **Step 3: Automated Testing**
   - Create `frontend/tests/homepage.phase20.test.tsx` verifying:
     - Full homepage renders all canonical sections
     - API integration with mocked `apiClient`
     - Loading skeleton behavior during data fetch
     - Error state resilience with fallback markup
     - Mobile navigation drawer toggle and interaction
     - Accessible landmarks and heading hierarchy
     - SEO title, meta tags, and JSON-LD schema
4. **Step 4: Local Full-Stack Verification**
   - `npm run typecheck` (0 errors)
   - `npm run lint` (0 errors, 0 warnings)
   - `npm test` (0 failures)
   - `npm run build` (production bundle SUCCESS)
   - Complete backend regression test harness (401 tests / 2,032 assertions / 0 failures)
   - Secret scan (0 secrets)
   - Composer validate strict
   - PHP syntax check
5. **Step 5: Documentation & Git Release**
   - Create `docs/phase-20/02_PHASE_20_VERIFICATION_REPORT.md` (Sections A through AG).
   - Commit `feat(phase-20): implement full homepage`.
   - Push to `origin main` and monitor GitHub Actions CI until all 4 jobs are green.
