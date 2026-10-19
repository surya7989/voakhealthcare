# Complete Feature & Requirements Matrix
## Voak Health Care Corporate Web Platform & Admin Dashboard

**Document Reference:** `docs/phase-00/02_COMPLETE_FEATURE_MATRIX.md`  
**Phase:** Phase 0 — Project Requirements & Hosting Audit  
**Roadmap Baseline:** Restored 32-Phase Numbering (Phases 0 Through 31)  

---

### 1. Matrix Methodology & Classification Standard

Each requirement in this matrix is assigned:
- **Feature ID:** Unique tracking identifier (`PUB-xxx` for Public Website, `ADM-xxx` for Admin Dashboard, `SYS-xxx` for System Architecture & Security).
- **Owning Module:** Specific architectural subsystem responsible for the capability.
- **Target Phase:** Exact restored roadmap phase (Phases 0 through 31).
- **Priority:** P0 (Mission-Critical / Launch Blocker), P1 (Core Functionality), P2 (Enhancement / Polishing).
- **Verification Status:** `[VERIFIED]` (Architecturally confirmed) or `[UNCONFIRMED - PENDING CLIENT INPUT]` (Subject to company operational input).

---

### 2. Public Website Feature Matrix (22 Pages & Interactive Components)

| Feature ID | Feature Name & Page | Owning Module | Target Phase | Priority | Description & Acceptance Criteria | Verification & Security Controls |
| :--- | :--- | :--- | :---: | :---: | :--- | :--- |
| **PUB-001** | Homepage Hero & Value Proposition (`/`) | Public Frontend / Hero | **Phase 20** | P0 | Asymmetrical editorial layout with expressive serif headline, trust indicators, primary CTA button, and authentic workforce visuals. Must pass WCAG 2.2 AA contrast. | `[VERIFIED]` / CSRF-safe CTA triggers; no unvalidated external links. |
| **PUB-002** | Bento-Grid Services Showcase (`/`) | Public Frontend / Services | **Phase 20** | P0 | Interactive bento-style cards highlighting 6 proposed service pillars with pastel containers, hover micro-interactions, and direct links to detail pages. | `[PROPOSED]` / Sanitized CMS content; fallback state if API offline. |
| **PUB-003** | Social Proof & Metric Counter (`/`) | Public Frontend / Social Proof | **Phase 20** | P1 | Dynamic animated counter for enterprise metrics with client testimonials carousel. Metrics are illustrative templates until verified. | `[UNCONFIRMED - PENDING CLIENT INPUT: Real statistics & signed releases]` |
| **PUB-004** | Global Sticky Header & Mobile Nav | Public Navigation | **Phase 19** | P0 | Responsive navigation bar with logo, dropdown menus, "Request Proposal" pill button, keyboard focus trap, and ARIA expanded attributes. | `[VERIFIED]` / Strict keyboard accessibility; WCAG 2.2 compliant tap targets (48x48px). |
| **PUB-005** | Global Footer & Legal Disclaimers | Public Navigation | **Phase 19** | P0 | 4-column footer containing company info, quick links, service catalog, and explicit clinical non-provider disclaimer. | `[VERIFIED]` / Rel="noopener noreferrer" on external links; strict copyright assertion. |
| **PUB-006** | About Us Page (`/about`) | Public Corporate | **Phase 21** | P0 | Corporate background, mission, leadership structure, clinical governance principles, and organizational values. | `[UNCONFIRMED - PENDING CLIENT INPUT: Executive team bios and history]` |
| **PUB-007** | Services Catalog Overview (`/services`) | Public Services | **Phase 22** | P0 | Filterable grid of all B2B healthcare services categorized by operational type, with search and direct proposal triggers. | `[VERIFIED]` / Clean URL parameter handling; XSS-safe search inputs. |
| **PUB-008** | Dynamic Service Detail Pages (`/services/:slug`) | Public Services | **Phase 22** | P0 | Dedicated deep-dive page for each service pillar: deliverables, methodology, SLA frameworks, and dedicated RFP inquiry modal. | `[VERIFIED]` / Server-rendered semantic HTML fallback, canonical tags, JSON-LD Service schema. |
| **PUB-009** | Industries We Serve Overview (`/industries`) | Public Industries | **Phase 22** | P0 | Comprehensive overview of institutional client verticals (Hospitals, Aged Care, GP Clinics, Diagnostics, Allied Health). | `[VERIFIED]` / Responsive grid layout with custom SVG/PNG iconography. |
| **PUB-010** | Dynamic Industry Detail Pages (`/industries/:slug`) | Public Industries | **Phase 22** | P0 | Tailored industry landing pages addressing sector-specific regulatory challenges and Voak operational solutions. | `[VERIFIED]` / Route-specific OpenGraph metadata; tailored B2B conversion trigger. |
| **PUB-011** | Why Choose Voak (`/why-choose-voak`) | Public Corporate | **Phase 21** | P1 | Comparative differentiation matrix highlighting Voak's clinical credentialing rigor, operational agility, and SLA frameworks. | `[UNCONFIRMED - PENDING CLIENT INPUT: Competitive differentiators]` |
| **PUB-012** | How We Work (`/how-we-work`) | Public Corporate | **Phase 21** | P1 | 4-stage operational engagement roadmap: Discovery & Audit -> Solution Design -> Integrated Deployment -> Continuous Governance. | `[VERIFIED]` / Step-by-step visual timeline component. |
| **PUB-013** | Quality & Compliance (`/quality-compliance`) | Public Compliance | **Phase 21** | P0 | Clinical governance standards, accreditation alignment, infection control rigor, and workforce compliance policies. | `[UNCONFIRMED - PENDING CLIENT INPUT: Exact accreditation bodies]` |
| **PUB-014** | Partners & Clients Showcase (`/partners`) | Public Social Proof | **Phase 24** | P1 | Institutional alliance overview, healthcare consortia frameworks, and partnership tier structures. | `[UNCONFIRMED - PENDING CLIENT INPUT: Requires verified partner releases]` |
| **PUB-015** | Case Studies Index & Dynamic Detail (`/case-studies`, `/:slug`) | Public Case Studies | **Phase 24** | P1 | Structured B2B case studies featuring Problem -> Intervention -> Measured Outcomes format. | `[UNCONFIRMED - PENDING CLIENT INPUT: Real approved case study metrics]` |
| **PUB-016** | Careers Portal & Job Listings (`/careers`) | Public Careers | **Phase 24** | P0 | Dynamic listing of healthcare management, clinical workforce, and administrative corporate openings with department filtering. | `[VERIFIED]` / Direct backend API feed with pagination and status caching. |
| **PUB-017** | Job Detail & Application Submission (`/careers/:slug`) | Public Careers | **Phase 24** | P0 | Detailed job specs and secure application form with resume upload (PDF/DOCX max 10MB) protected by rate-limiting and honeypot. | `[VERIFIED]` / Whitelist MIME & magic bytes verification; files stored in non-public partition. |
| **PUB-018** | Insights & Knowledge Center (`/insights`) | Public Resources | **Phase 24** | P1 | Thought leadership articles, whitepapers, and operational healthcare guides categorized by topic with search functionality. | `[VERIFIED]` / Dynamic XML sitemap integration, JSON-LD Article schema. |
| **PUB-019** | Dynamic Article Detail (`/insights/:slug`) | Public Resources | **Phase 24** | P1 | Full article rendering with reading time, author profile, social sharing buttons, and related service recommendations. | `[VERIFIED]` / Server-rendered semantic fallback, DOMPurify sanitization. |
| **PUB-020** | Frequently Asked Questions (`/faq`) | Public Support | **Phase 24** | P1 | Categorized accordion FAQ addressing contracting models, onboarding speed, compliance vetting, and pricing frameworks. | `[VERIFIED]` / Keyboard accessible accordion tabs; JSON-LD FAQPage schema. |
| **PUB-021** | Contact Us Page (`/contact`) | Public Inbound | **Phase 23** | P0 | General inquiry form, corporate office map/directions, direct phone and department email contacts. | `[VERIFIED]` / CSRF token protection, Cloudflare Turnstile bot mitigation, IP rate limit. |
| **PUB-022** | Partner With Us Portal (`/partner-with-us`) | Public Inbound | **Phase 23** | P0 | Specialized enterprise intake for joint ventures, subcontracting clinical agencies, and institutional consortiums. | `[VERIFIED]` / Multi-field qualification form; triggers high-priority email alert. |
| **PUB-023** | Request a Proposal Engine (`/request-proposal`) | Public Inbound | **Phase 23** | P0 | Multi-step enterprise RFP workflow: organization details, facility type, service requirements, budget band, timeline, and RFP document attachment. | `[VERIFIED]` / MIME validation on attached RFPs; dual DB + SMTP alert dispatch. |
| **PUB-024** | Privacy Policy & Legal Terms (`/privacy-policy`, `/terms`) | Public Legal | **Phase 24** | P0 | Legally compliant statements covering Australian Privacy Principles (APPs) / GDPR, data retention, cookie usage, and B2B terms of service. | `[UNCONFIRMED - PENDING CLIENT INPUT: Legal review of privacy notice]` |
| **PUB-025** | Custom 404 & Error Fallback States | Public Core | **Phase 24** | P0 | Branded, helpful 404 Not Found and 500 Server Error screens with search shortcut and link back to homepage. | `[VERIFIED]` / Returns standard HTTP 404/500 headers; logs diagnostic incident ID. |

---

### 3. Admin Dashboard Feature Matrix (19 Core Modules)

| Feature ID | Admin Module | Target Phase | Priority | Operational Scope & Acceptance Criteria | RBAC Permissions Required |
| :--- | :--- | :---: | :---: | :--- | :--- |
| **ADM-001** | Dashboard Overview & KPI Metrics | **Phase 25** | P0 | Real-time executive snapshot: new inquiries, active proposals, job applicant count, recent audit events, and system health status. | All Authenticated Admin Roles |
| **ADM-002** | Secure Admin Authentication | **Phase 6** | P0 | Email/password login with bcrypt (cost 12), brute-force rate limiting (5 failed attempts = 15m lockout), secure HttpOnly session cookies. | Public Admin Route (`/ash` or `/admin`) |
| **ADM-003** | Multi-Factor Authentication (MFA) | **Phase 6** | P0 | Time-based One-Time Password (TOTP RFC 6238) with QR code setup, 8 emergency recovery codes, and AES-256-GCM encrypted secrets. | Mandatory for Super Admin; Optional for Editors |
| **ADM-004** | User Management & RBAC | **Phase 6** / **26** | P0 | Provision, edit, deactivate admin accounts. Assign roles (`Super Admin`, `Content Editor`, `Partner Manager`, `HR Recruiter`, `Auditor`). | `users.manage` (Super Admin Only) |
| **ADM-005** | Page Content Management | **Phase 7** / **26** | P1 | Manage static page text blocks, mission statements, and corporate disclosures with version history. | `content.edit`, `content.publish` |
| **ADM-006** | Homepage Hero & Layout Manager | **Phase 7** / **26** | P1 | Configure homepage headline copy, toggle trust metric ribbons, update featured service ordering. | `content.edit`, `content.publish` |
| **ADM-007** | Services Catalog Management | **Phase 8** / **26** | P0 | Full CRUD for service pillars: title, slug, summary, detailed body, icon, order index, SEO tags, publish status. | `services.create`, `services.edit`, `services.delete` |
| **ADM-008** | Industries Catalog Management | **Phase 9** / **26** | P0 | Full CRUD for industry verticals: title, challenges, solutions, icon, ordering, SEO metadata. | `industries.create`, `industries.edit`, `industries.delete` |
| **ADM-009** | Media Library & Asset Manager | **Phase 10** / **26** | P0 | Upload, crop, tag, and organize media files. Auto-generates WebP formats. Validates magic bytes against malicious scripts. | `media.upload`, `media.delete` |
| **ADM-010** | Contact Enquiries Triage CRM | **Phase 11** / **26** | P0 | Review inbound inquiries: status triage (`New`, `In Progress`, `Qualified`, `Closed`), internal notes, CSV export. | `enquiries.view`, `enquiries.manage` |
| **ADM-011** | Partnership Enquiries Pipeline | **Phase 12** / **26** | P0 | Dedicated pipeline for institutional partnerships. Tracks organization tier, engagement lead, and discovery call dates. | `partnerships.view`, `partnerships.manage` |
| **ADM-012** | Proposal Requests (RFP) Desk | **Phase 12** / **26** | P0 | High-touch RFP desk: view project briefs, download attached client specs (authenticated streaming), assign proposal lead. | `proposals.view`, `proposals.manage` |
| **ADM-013** | Careers & Job Openings Manager | **Phase 14** / **26** | P0 | Post new job listings, set closing dates, manage job descriptions and qualification criteria. | `careers.manage` |
| **ADM-014** | Candidate Applications Triage | **Phase 14** / **26** | P0 | Review candidate submissions, download resumes securely (with strict virus/type checks), update hiring stages. | `careers.manage`, `applications.view` |
| **ADM-015** | Case Studies & Social Proof Manager | **Phase 15** / **26** | P1 | Create, edit, and feature verified client case studies and signed testimonial quotes. | `casestudies.manage`, `testimonials.manage` |
| **ADM-016** | Insights & Article CMS | **Phase 15** / **26** | P1 | Rich text article editor with featured image, tag taxonomy, author attribution, and auto-generated slug/SEO preview. | `articles.create`, `articles.publish` |
| **ADM-017** | SEO & Meta Tag Management | **Phase 16** / **26** | P0 | Global and per-page meta title, description, OpenGraph image, robots directives, and dynamic XML sitemap invalidation. | `seo.manage` |
| **ADM-018** | Transactional Email Templates | **Phase 13** / **26** | P1 | Configure automated email notifications for client inquiries and admin dispatch alerts with dynamic placeholders. | `settings.manage` |
| **ADM-019** | Audit Logs & Operational Reporting | **Phase 17** / **26** | P0 | Immutable ledger of all administrative logins, password changes, data updates, file downloads, and failed access attempts. | `audit.view` (Super Admin & Auditor Only) |

---

### 4. Cross-Cutting Architectural & Security Requirements

| Feature ID | System Domain | Target Phase | Priority | Requirement & Acceptance Standard |
| :--- | :--- | :---: | :---: | :--- |
| **SYS-001** | Database Transactions & Prepared Statements | **Phase 5** | P0 | 100% of SQL interactions executed via PDO prepared statements with strict parameter binding. Zero raw SQL concatenation permitted. |
| **SYS-002** | Cross-Site Request Forgery (CSRF) | **Phase 6** | P0 | Synchronizer token pattern enforced on all state-changing endpoints (`POST`, `PUT`, `DELETE`). Tokens cryptographically generated per session. |
| **SYS-003** | Content Security Policy (CSP) & Headers | **Phase 4** / **30** | P0 | Strict HTTP response headers: `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`. |
| **SYS-004** | File Upload Quarantine & Protection | **Phase 10** | P0 | Uploaded documents stored outside public root or in protected directories with `.htaccess` execution disabled (`php_flag engine off`). |
| **SYS-005** | Production Fail-Fast & Env Protection | **Phase 4** | P0 | App throws immediate fail-fast exception if database credentials, encryption keys, or mail configurations are absent in production. No production secrets committed to Git. |
| **SYS-006** | Safe Database Migrations | **Phase 5** | P0 | Migration process uses mutex locking, preflight checks, pre-migration backups, and additive schema changes. No public migration endpoint. |
| **SYS-007** | Minimal Public Health Endpoint | **Phase 4** | P0 | `GET /api/health` returns only `{ "status": "ok" }`. Zero server paths, PHP versions, or database internal details exposed publicly. |
