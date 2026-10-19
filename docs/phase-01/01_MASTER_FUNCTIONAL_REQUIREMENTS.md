# Master Functional Requirements Specification
## System-Wide Functional Hierarchy & Requirement Taxonomy

**Document Reference:** `docs/phase-01/01_MASTER_FUNCTIONAL_REQUIREMENTS.md`  
**Phase:** Phase 1 of 31 — Complete Functional Specification  
**Baseline Authority:** Restored 32-Phase Roadmap & Phase 0 Foundation Matrix  

---

### 1. Functional Requirement Classification Taxonomy

Every functional requirement across the Voak Health Care platform is classified under a standardized taxonomy to guarantee 100% bidirectional traceability:

```
[PREFIX]-[CATEGORY]-[NUMBER]
  │        │          └── Sequential 3-digit requirement identifier (001-999)
  │        └───────────── Subsystem domain (PUB, ADM, WF, SEC, SEO, A11Y, PERF)
  └────────────────────── Functional Requirement indicator (FR)
```

- **`FR-PUB-xxx`**: Public Website Functional Requirements (22 pages, navigation, components).
- **`FR-ADM-xxx`**: Administrative Console Requirements (19 modules, dashboard, CRM, CMS).
- **`FR-WF-xxx`**: Business Workflow & State Machine Requirements (Inbound pipelines, approvals).
- **`FR-SEC-xxx`**: Security, Privacy & Compliance Requirements (OWASP ASVS v4.0.3, APPs, RBAC).
- **`FR-SEO-xxx`**: SEO, Crawling & Metadata Requirements (Edge pre-render, XML sitemaps, JSON-LD).
- **`FR-A11Y-xxx`**: Accessibility & Responsive UX Requirements (WCAG 2.2 AA, keyboard, viewports).
- **`FR-SYS-xxx`**: System Integrity, Data & Hosting Requirements (PDO MySQL, Hostinger runbook).

---

### 2. Master System-Wide Functional Requirements Inventory

#### Domain A: Public Website & Inbound Pipeline (`FR-PUB`)

| Requirement ID | Functional Title | Description & System Behavior | Owning Module | Target Phase |
| :--- | :--- | :--- | :--- | :---: |
| **FR-PUB-001** | Global Navigation Header | Fixed/sticky navigation with responsive mobile drawer, branded logo, multi-level dropdowns, and high-visibility "Request Proposal" action. Must maintain visible focus state. | Navigation Shell | Phase 19 |
| **FR-PUB-002** | Asymmetric Hero Composition | Visual hero presenting company value proposition using approved serif display typography (`Fraunces`), pastel background rhythm, and primary royal purple CTA (`#665CE9`). | Homepage | Phase 20 |
| **FR-PUB-003** | Bento-Grid Services Presentation | Interactive asymmetrical cards highlighting 6 proposed service pillars. On hover, cards subtly elevate (`translateY(-4px)`). Clicking routes to individual service detail pages. | Homepage / Services | Phase 20 / 22 |
| **FR-PUB-004** | Configurable Social Proof Ribbon | Modular accreditation and partner logo ribbon. Must gracefully hide without layout shift if client logos are unpublished or unconfirmed. | Trust Proof | Phase 20 / 24 |
| **FR-PUB-005** | Service Pillar Filtering & Discovery | Filterable services catalog allowing healthcare executives to filter offerings by operational category (Workforce, Operations, Administration, Facilities, Consulting, Governance). | Services Catalog | Phase 22 |
| **FR-PUB-006** | Dynamic Service Detail Deep-Dive | Dedicated slug-based pages (`/services/:slug`) rendering comprehensive deliverables, SLA models, and contextual RFP inquiry modals. | Services Catalog | Phase 22 |
| **FR-PUB-007** | Industry Verticals Directory | Dedicated industry catalog showcasing solutions for Hospitals, GP Clinics, Aged Care, Allied Health, Diagnostics, and Corporate Health. | Industries Catalog | Phase 22 |
| **FR-PUB-008** | Dynamic Industry Detail Deep-Dive | Dedicated pages (`/industries/:slug`) addressing sector-specific regulatory burdens and tailored Voak operational capabilities. | Industries Catalog | Phase 22 |
| **FR-PUB-009** | Corporate Governance & About Narrative | Presentation of corporate background, clinical governance principles, executive leadership structure, and operating footprint. | Corporate Overview | Phase 21 |
| **FR-PUB-010** | Differentiation & "Why Choose Voak" | Structured comparative matrix contrasting Voak's operational rigor against standard agency labor and internal clinic overheads. | Corporate Overview | Phase 21 |
| **FR-PUB-011** | 4-Stage Operational Implementation Roadmap | Visual, responsive timeline explaining the 4-stage engagement model: Audit -> Solution Architecture -> Deployment -> Continuous Governance. | Corporate Overview | Phase 21 |
| **FR-PUB-012** | Clinical Compliance & Quality Standards | Detailed overview of clinical audit readiness, credentialing frameworks, and infection control standards. | Compliance | Phase 21 |
| **FR-PUB-013** | Verified Case Studies Index & Detail | Problem-Intervention-Outcome formatted enterprise case studies. Must only render verified, client-approved metrics. | Case Studies | Phase 24 |
| **FR-PUB-014** | Careers Portal & Openings Board | Real-time directory of corporate, clinical, and operational job vacancies with department and employment-type filtering. | Careers Portal | Phase 24 |
| **FR-PUB-015** | Candidate Application & Resume Attachment | Multi-field job application form accepting candidate details, cover letter, and resume file upload (PDF/DOCX max 10MB) with MIME magic byte checks. | Careers Portal | Phase 24 |
| **FR-PUB-016** | Insights & Thought Leadership Center | Articles and whitepapers organized by category, featuring estimated reading times, author attribution, and search filtering. | Insights CMS | Phase 24 |
| **FR-PUB-017** | Dynamic Article Reader | Full article rendering with social sharing triggers, reading progress bar, related content callouts, and DOMPurify sanitization. | Insights CMS | Phase 24 |
| **FR-PUB-018** | Interactive FAQ Accordion | Categorized procurement, contracting, and compliance FAQ accordions with WAI-ARIA keyboard navigation support. | FAQ Support | Phase 24 |
| **FR-PUB-019** | General Contact Inbound Form | Low-friction contact form capturing name, organization, corporate email, phone, and inquiry details with CSRF and Turnstile protection. | Business Inbound | Phase 23 |
| **FR-PUB-020** | Strategic Partnership Inbound Intake | High-touch intake form for healthcare consortia and subcontracting alliances capturing organizational tier and collaboration scope. | Business Inbound | Phase 23 |
| **FR-PUB-021** | Multi-Step Request a Proposal (RFP) Engine | Multi-step enterprise RFP workflow capturing facility profile, required service pillars, budget band, timeline, and tender document upload. | Business Inbound | Phase 23 |
| **FR-PUB-022** | Legal Policies & Disclaimers | Australian Privacy Principles compliant Privacy Policy, Terms of Service, and explicit non-clinical care disclaimers. | Legal & Policy | Phase 24 |
| **FR-PUB-023** | Accessible 404 & Error Boundaries | Custom branded 404 Not Found and 500 Server Error screens that emit authentic HTTP 404/500 headers and log diagnostic reference IDs. | Core Shell | Phase 24 |

---

#### Domain B: Administrative Management Portal (`FR-ADM`)

| Requirement ID | Functional Title | Description & System Behavior | Owning Module | Target Phase |
| :--- | :--- | :--- | :--- | :---: |
| **FR-ADM-001** | Executive Dashboard Overview | Real-time KPI widgets: New Inquiries (last 24h/7d), Active Proposals, Pending Job Applications, System Health, and Audit Activity Ledger. | Dashboard Overview | Phase 25 |
| **FR-ADM-002** | Secure Admin Login & Brute-Force Defense | Email/password authentication via bcrypt (cost 12), throttling after 5 failed attempts with a 15-minute lockout, and generic error messaging. | Authentication | Phase 6 |
| **FR-ADM-003** | RFC 6238 TOTP Multi-Factor Authentication | Mandatory 6-digit TOTP challenge for Super Admin. Stores AES-256-GCM encrypted secrets at rest and manages 8 hashed recovery codes. | Authentication | Phase 6 |
| **FR-ADM-004** | Role-Based Access Control (RBAC) Engine | Granular permission checking across 5 distinct roles (`Super Admin`, `Administrator`, `Content Editor`, `BD Manager`, `HR Recruiter`). | User & Role Engine | Phase 6 / 26 |
| **FR-ADM-005** | Page Content Management System | Rich text / Markdown editor for static pages with version history, revision rollback, and live preview rendering. | Content CMS | Phase 7 / 26 |
| **FR-ADM-006** | Homepage Hero & Section Customizer | Configuration console to update hero headlines, toggle trust metrics, and manage featured bento-grid service ordering. | Content CMS | Phase 7 / 26 |
| **FR-ADM-007** | Services Catalog Management Console | Full CRUD operations for service pillars: title, slug, summary, content, category, icon, display order, publish toggle, and SEO tags. | Services CMS | Phase 8 / 26 |
| **FR-ADM-008** | Industries Catalog Management Console | Full CRUD operations for customer verticals: sector name, slug, overview, challenges, solutions, and custom SVG icons. | Industries CMS | Phase 9 / 26 |
| **FR-ADM-009** | Media Library & Asset Optimization | Upload console auto-converting images to WebP format, enforcing alt text, verifying magic bytes, and organizing assets by tag. | Media Library | Phase 10 / 26 |
| **FR-ADM-010** | Contact Inquiries Triage CRM | Inbound general inquiry manager: status transitions (`New`, `In Progress`, `Qualified`, `Closed`), internal notes, and CSV data export. | Inquiry CRM | Phase 11 / 26 |
| **FR-ADM-011** | Strategic Partnership Pipeline Desk | Dedicated pipeline tracker for enterprise partnerships: logs organization size, sector, assigned partner lead, and discovery call dates. | Partnership CRM | Phase 12 / 26 |
| **FR-ADM-012** | Request a Proposal (RFP) Review Desk | Comprehensive RFP desk: view project briefs, download client tender specs via authenticated streaming, and track proposal milestones. | Proposal Desk | Phase 12 / 26 |
| **FR-ADM-013** | Transactional Email Template Customizer | Management interface for system email notifications: customize body copy and dynamic placeholders (`{{contact_name}}`, `{{ref_no}}`). | Email Engine | Phase 13 / 26 |
| **FR-ADM-014** | Careers & Job Openings Management | Job creation console: specify department, title, employment type, location, closing date, and publish status. | Careers Manager | Phase 14 / 26 |
| **FR-ADM-015** | Candidate Applications Review Desk | Recruitment triage console: review candidate data, download resumes securely, and transition statuses (`Shortlisted`, `Interviewing`, `Hired`). | Careers Manager | Phase 14 / 26 |
| **FR-ADM-016** | Case Studies & Social Proof Manager | Content manager for verified B2B case studies and client testimonial quotes with mandatory verified release attachments. | Content CMS | Phase 15 / 26 |
| **FR-ADM-017** | Insights & Article CMS | Editorial article manager: Markdown/rich text, tags, author attribution, reading time auto-calculation, and publish scheduling. | Content CMS | Phase 15 / 26 |
| **FR-ADM-018** | SEO & Metadata Administration | Global and route-level meta title/description overrides, OpenGraph image assignments, and automated XML sitemap cache invalidation. | SEO Engine | Phase 16 / 26 |
| **FR-ADM-019** | Immutable Audit Ledger & Telemetry | Comprehensive, filterable audit log tracking every user login, create, update, delete, and document download event with IP and User-Agent. | Audit Engine | Phase 17 / 26 |

---

#### Domain C: Business Workflows & State Machines (`FR-WF`)

| Requirement ID | Functional Title | Description & System Behavior | Owning Module | Target Phase |
| :--- | :--- | :--- | :--- | :---: |
| **FR-WF-001** | Contact Inquiry Lifecycle State Machine | Manages status transitions: `New` -> `In Progress` -> `Qualified` / `Disqualified` -> `Closed` -> `Spam`. | Inquiries CRM | Phase 11 |
| **FR-WF-002** | Partnership Pipeline State Machine | Manages status transitions: `New` -> `Discovery Scheduled` -> `Negotiating` -> `Partnered` / `Declined`. | Partnership CRM | Phase 12 |
| **FR-WF-003** | RFP Proposal Lifecycle State Machine | Manages status transitions: `New` -> `Under Review` -> `Drafting Proposal` -> `Proposal Sent` -> `Contract Executed` / `Declined`. | Proposal Desk | Phase 12 |
| **FR-WF-004** | Candidate Application State Machine | Manages hiring stages: `New` -> `Reviewed` -> `Shortlisted` -> `Interview Scheduled` -> `Offer Extended` -> `Hired` / `Rejected`. | Careers Manager | Phase 14 |
| **FR-WF-005** | CMS Content Publishing State Machine | Content lifecycle: `Draft` -> `Pending Review` -> `Published` -> `Unpublished` -> `Archived`. | Content CMS | Phases 7-10, 15 |
| **FR-WF-006** | Safe Slug Renaming & Redirect Trigger | Renaming a published service, industry, or article slug automatically records the previous slug in a redirect table for HTTP 301 emission. | Content CMS / SEO | Phase 16 |

---

#### Domain D: Security, Privacy & Integrity (`FR-SEC`)

| Requirement ID | Functional Title | Description & System Behavior | Owning Module | Target Phase |
| :--- | :--- | :--- | :--- | :---: |
| **FR-SEC-001** | CSRF Synchronizer Token Defense | Cryptographically strong 256-bit token generated per session. Verified on all state-changing HTTP mutation requests (`POST`, `PUT`, `PATCH`, `DELETE`). | Security Core | Phase 6 |
| **FR-SEC-002** | Secure Session Cookie Architecture | Session cookies configured with `HttpOnly; Secure; SameSite=Strict; Path=/api/admin; Max-Age=28800`. Terminated on logout. | Security Core | Phase 6 |
| **FR-SEC-003** | Document Upload Quarantine | Uploaded resumes and RFPs stored outside web root (`storage/uploads/`) with randomized UUID filenames and disabled PHP execution. | Storage Engine | Phase 10 |
| **FR-SEC-004** | Authenticated Document Streaming | Downloads of candidate resumes and client RFPs stream through an authenticated PHP controller verifying role permissions. | Storage Engine | Phase 10 / 12 / 14 |
| **FR-SEC-005** | Production Fail-Fast Validation | Application halts immediately at boot if database credentials, encryption keys, or SMTP configurations are missing in production. | Config Engine | Phase 4 |
| **FR-SEC-006** | Zero Secrets in Version Control | Strict policy ensuring `.env`, private keys, and passwords are never committed to Git. Production secrets provisioned out-of-band at permission `0600`. | Deployment Engine | Phase 4 / 31 |
| **FR-SEC-007** | Safe CLI Migration Process | Database migrations executed strictly via authorized CLI (`php database/migrate.php`) with mutex locking, pre-migration dumps, and no web triggers. | Database Engine | Phase 5 |

---

#### Domain E: SEO & Accessibility Standards (`FR-SEO` & `FR-A11Y`)

| Requirement ID | Functional Title | Description & System Behavior | Owning Module | Target Phase |
| :--- | :--- | :--- | :--- | :---: |
| **FR-SEO-001** | Hybrid Semantic Content Pre-Rendering | Apache rewrites non-file public requests to `index.php`. PHP queries MySQL, injects escaped metadata, and outputs semantic HTML for crawlers. | SEO Engine | Phase 16 / 29 |
| **FR-SEO-002** | True HTTP 404 Emission | Requesting a non-existent service, industry, or article slug emits an authentic `HTTP 404 Not Found` header, eliminating soft-404 penalties. | SEO Engine | Phase 16 / 29 |
| **FR-SEO-003** | Dynamic XML Sitemap Generation | Dynamically generates `/sitemap.xml` listing all published pages, services, industries, and articles with live `lastmod` timestamps. | SEO Engine | Phase 16 / 29 |
| **FR-SEO-004** | Structured Data (JSON-LD) Schemas | Injects Schema.org `Organization`, `Service`, `Article`, `FAQPage`, and `JobPosting` structured data into `<head>`. | SEO Engine | Phase 16 / 29 |
| **FR-A11Y-001** | Mathematical WCAG 2.2 AA Contrast | Normal text maintains >= 4.5:1 contrast (including adjusted muted token `#50567A` at 6.30:1); large text and UI boundaries maintain >= 3:1. | Design / UI | Phase 3 / 19 / 28 |
| **FR-A11Y-002** | Complete Keyboard Navigation | All interactive components accessible via Tab, Shift+Tab, Enter, Space. Focus traps enforced in modals; focus rings visible (`ring-2 ring-[#665CE9]`). | UI Component System | Phase 19 / 28 |
| **FR-A11Y-003** | Reduced-Motion Media Query Support | CSS micro-animations and Motion spring transitions disable or snap instantly when `prefers-reduced-motion: reduce` is detected. | UI Component System | Phase 19 / 28 |
