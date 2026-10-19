# Phase 2 Architectural Requirements Traceability Matrix

**Document Reference:** `docs/phase-02/16_PHASE_2_REQUIREMENT_TRACEABILITY.md`  
**Phase:** Phase 2 of 31 — Complete System Architecture  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Executing Role:** Requirements Traceability Specialist & Principal Software Architect  
**Baseline Authority:** Master Functional Requirements (`docs/phase-01/01_MASTER_FUNCTIONAL_REQUIREMENTS.md`) and Reconciled Traceability Matrix (`docs/phase-01/15_REQUIREMENTS_TRACEABILITY_MATRIX.md`)  
**Status:** **AUTHORITATIVE TRACEABILITY MATRIX** (100% of 62 Canonical Requirements Mapped)

---

## 1. Traceability Architecture & Linkage Taxonomy

Every one of the **62 canonical functional requirements** established in Phase 1 is tracked across an architectural chain:

$$\text{Req ID} \longrightarrow \text{Domain Module} \longrightarrow \text{Architectural Component} \longrightarrow \text{Database Entity} \longrightarrow \text{API Contract Endpoint} \longrightarrow \text{Target Phase}$$

Where an attribute is not applicable (such as database entities for CSS accessibility tokens), `N/A` is explicitly documented with the technical rationale.

---

## 2. Complete 62-Requirement Architectural Mapping Ledger

| Req ID | Requirement Title | Owning Module | Component ID | Database Entity | API Contract Endpoint | Target Phase |
| :---: | :--- | :--- | :---: | :--- | :--- | :---: |
| **FR-PUB-001** | Global Navigation Header & Mobile Drawer | `Core` / `Settings` | `C-03` | `system_settings` | `GET /api/v1/public/settings/header` | Phase 19 |
| **FR-PUB-002** | Asymmetric Hero Composition & Branding | `Homepage` | `C-03`, `C-12` | `homepage_configs` | `GET /api/v1/public/homepage` | Phase 20 |
| **FR-PUB-003** | Bento-Grid Services Presentation | `Homepage` / `Services` | `C-03`, `C-12` | `services` | `GET /api/v1/public/homepage` | Phase 20 / 22 |
| **FR-PUB-004** | Configurable Social Proof Ribbon Collapse | `Homepage` / `CaseStudies` | `C-03`, `C-12` | `partners`, `testimonials` | `GET /api/v1/public/homepage` | Phase 20 / 24 |
| **FR-PUB-005** | Service Pillar Filtering & Discovery | `Services` | `C-03`, `C-07` | `services` | `GET /api/v1/public/services` | Phase 22 |
| **FR-PUB-006** | Dynamic Service Detail Deep-Dive & SLA | `Services` | `C-03`, `C-12` | `services`, `service_industry_mappings` | `GET /api/v1/public/services/{slug}` | Phase 22 |
| **FR-PUB-007** | Industry Verticals Directory | `Industries` | `C-03`, `C-12` | `industries` | `GET /api/v1/public/industries` | Phase 22 |
| **FR-PUB-008** | Dynamic Industry Detail Deep-Dive | `Industries` | `C-03`, `C-12` | `industries`, `services` | `GET /api/v1/public/industries/{slug}` | Phase 22 |
| **FR-PUB-009** | Corporate Governance & About Narrative | `Pages` | `C-03`, `C-12` | `pages`, `content_revisions` | `GET /api/v1/public/pages/about` | Phase 21 |
| **FR-PUB-010** | Differentiation & "Why Choose Voak" | `Pages` | `C-03`, `C-12` | `pages` | `GET /api/v1/public/pages/why-choose-voak` | Phase 21 |
| **FR-PUB-011** | 4-Stage Operational Roadmap Timeline | `Pages` | `C-03`, `C-12` | `pages` | `GET /api/v1/public/pages/how-we-work` | Phase 21 |
| **FR-PUB-012** | Clinical Compliance & Non-Clinical Scope| `Pages` | `C-03`, `C-12` | `pages` | `GET /api/v1/public/pages/quality-compliance` | Phase 21 |
| **FR-PUB-013** | Verified Case Studies Index & Detail | `CaseStudies` | `C-03`, `C-12` | `case_studies` | `GET /api/v1/public/case-studies` | Phase 24 |
| **FR-PUB-014** | Careers Portal & Openings Board | `Careers` | `C-03`, `C-07` | `job_postings` | `GET /api/v1/public/jobs` | Phase 24 |
| **FR-PUB-015** | Candidate Application & Resume Upload | `Careers` | `C-03`, `C-10` | `career_applications` | `POST /api/v1/public/careers/apply` | Phase 24 |
| **FR-PUB-016** | Insights & Thought Leadership Center | `Articles` | `C-03`, `C-12` | `articles` | `GET /api/v1/public/articles` | Phase 24 |
| **FR-PUB-017** | Dynamic Article Reader & Content Sanitizer | `Articles` | `C-03`, `C-12` | `articles` | `GET /api/v1/public/articles/{slug}` | Phase 24 |
| **FR-PUB-018** | Interactive FAQ Accordion System | `FAQs` | `C-03`, `C-12` | `faqs` | `GET /api/v1/public/faqs` | Phase 24 |
| **FR-PUB-019** | General Contact Inbound Form & Turnstile | `Contact` | `C-03`, `C-07` | `contact_inquiries`, `email_queue` | `POST /api/v1/public/contact` | Phase 23 |
| **FR-PUB-020** | Strategic Partnership Inbound Intake | `Partnerships` | `C-03`, `C-07` | `partnership_inquiries`, `email_queue` | `POST /api/v1/public/partnerships/inquire` | Phase 23 |
| **FR-PUB-021** | Multi-Step RFP Wizard & Tender Upload | `Proposals` | `C-03`, `C-10` | `proposals`, `proposal_services` | `POST /api/v1/public/proposals/submit` | Phase 23 |
| **FR-PUB-022** | Legal Policies, Terms & Dynamic Vars | `Pages` / `Settings` | `C-03`, `C-12` | `pages`, `system_settings` | `GET /api/v1/public/pages/{slug}` | Phase 24 |
| **FR-PUB-023** | Accessible 404 & Error Boundaries | `Core` | `C-03`, `C-05` | N/A (Core Shell / Front Controller) | Global Error Boundary & Front Controller | Phase 24 |
| **FR-ADM-001** | Executive Dashboard KPI Overview | `Core` / `CRM` | `C-04`, `C-07` | Aggregates across CRM tables | `GET /api/v1/admin/dashboard/metrics` | Phase 25 |
| **FR-ADM-002** | Secure Admin Login & Brute-Force Lock | `Auth` | `C-04`, `C-13` | `users`, `admin_sessions` | `POST /api/v1/admin/auth/login` | Phase 6 |
| **FR-ADM-003** | RFC 6238 TOTP Multi-Factor Auth | `Auth` | `C-04`, `C-13` | `admin_mfa_secrets` | `POST /api/v1/admin/auth/mfa/verify` | Phase 6 |
| **FR-ADM-004** | Role-Based Access Control (5 Roles) | `RBAC` | `C-04`, `C-06` | `roles`, `permissions`, `user_roles` | `GET/POST /api/v1/admin/users` | Phase 6 / 26 |
| **FR-ADM-005** | Page Content CMS & Concurrency Control | `Pages` | `C-04`, `C-07` | `pages`, `content_revisions` | `PUT /api/v1/admin/pages/{id}` | Phase 7 / 26 |
| **FR-ADM-006** | Homepage Hero & Bento Customizer | `Homepage` | `C-04`, `C-07` | `homepage_configs` | `PUT /api/v1/admin/homepage` | Phase 7 / 26 |
| **FR-ADM-007** | Services Catalog Management Console | `Services` | `C-04`, `C-07` | `services`, `url_redirects` | `/api/v1/admin/services[/{id}]` | Phase 8 / 26 |
| **FR-ADM-008** | Industries Catalog Management Console | `Industries` | `C-04`, `C-07` | `industries`, `service_industry_mappings` | `/api/v1/admin/industries[/{id}]` | Phase 9 / 26 |
| **FR-ADM-009** | Media Library WebP & Deletion Guard | `Media` | `C-04`, `C-07` | `media_assets` | `GET/POST/DELETE /api/v1/admin/media[/{id}]` | Phase 10 / 26 |
| **FR-ADM-010** | Contact Inquiries Triage CRM Desk | `Contact` | `C-04`, `C-07` | `contact_inquiries` | `/api/v1/admin/inquiries/contact[/{id}]` | Phase 11 / 26 |
| **FR-ADM-011** | Strategic Partnership Pipeline Desk | `Partnerships` | `C-04`, `C-07` | `partnership_inquiries` | `/api/v1/admin/partnerships[/{id}]` | Phase 12 / 26 |
| **FR-ADM-012** | RFP Review Desk & Tender Streaming | `Proposals` | `C-04`, `C-10` | `proposals` | `GET /api/v1/admin/proposals/{id}/download` | Phase 12 / 26 |
| **FR-ADM-013** | Email Templates & Outbox Telemetry | `Email` | `C-04`, `C-11` | `email_templates`, `email_queue` | `GET/PUT /api/v1/admin/email-templates`, `/email-queue` | Phase 13 / 26 |
| **FR-ADM-014** | Careers & Job Openings Management | `Careers` | `C-04`, `C-07` | `job_postings` | `/api/v1/admin/jobs[/{id}]` | Phase 14 / 26 |
| **FR-ADM-015** | Candidate Review Desk & CV Streaming | `Careers` | `C-04`, `C-10` | `career_applications` | `GET /api/v1/admin/applications/{id}/download` | Phase 14 / 26 |
| **FR-ADM-016** | Case Studies & Social Proof Manager | `CaseStudies` | `C-04`, `C-07` | `case_studies`, `testimonials` | `/api/v1/admin/case-studies[/{id}]` | Phase 15 / 26 |
| **FR-ADM-017** | Insights & Article CMS Publishing | `Articles` | `C-04`, `C-07` | `articles` | `/api/v1/admin/articles[/{id}]` | Phase 15 / 26 |
| **FR-ADM-018** | SEO Engine & Dynamic Sitemap Console | `SEO` | `C-04`, `C-07` | `seo_routes`, `url_redirects` | `POST /api/v1/admin/seo/rebuild-sitemap` | Phase 16 / 26 |
| **FR-ADM-019** | Immutable Audit Ledger & Telemetry | `Audit` | `C-04`, `C-14` | `audit_logs` | `GET /api/v1/admin/audit-logs` | Phase 17 / 26 |
| **FR-WF-001** | Contact Inquiry State Machine | `Contact` | `C-07` | `contact_inquiries`, `audit_logs` | `PATCH /api/v1/admin/inquiries/contact/{id}/status` | Phase 11 |
| **FR-WF-002** | Partnership Pipeline State Machine | `Partnerships` | `C-07` | `partnership_inquiries`, `audit_logs` | `PATCH /api/v1/admin/partnerships/{id}/status` | Phase 12 |
| **FR-WF-003** | RFP Proposal Lifecycle State Machine | `Proposals` | `C-07` | `proposals`, `audit_logs` | `PATCH /api/v1/admin/proposals/{id}/status` | Phase 12 |
| **FR-WF-004** | Candidate Recruitment State Machine | `Careers` | `C-07` | `career_applications`, `audit_logs` | `PATCH /api/v1/admin/applications/{id}/status` | Phase 14 |
| **FR-WF-005** | CMS Content Publishing State Machine | `Pages` / `Services` | `C-07` | `pages`, `content_revisions` | `POST /api/v1/admin/content/publish` | Phases 7-10, 15 |
| **FR-WF-006** | Safe Slug Renaming & 301 Redirect FSM | `Services` / `SEO` | `C-07` | `url_redirects`, `services` | `POST /api/v1/admin/seo/redirects` | Phase 16 |
| **FR-SEC-001** | CSRF Synchronizer Token Defense | `Core` / `Middleware`| `C-06` | N/A (Session-bound token) | `X-CSRF-Token` Header Middleware | Phase 6 |
| **FR-SEC-002** | Secure Session Cookie (`/api/v1/admin`)| `Auth` / `Middleware`| `C-06`, `C-13` | `admin_sessions` | `voak_admin_session` Cookie Handling | Phase 6 |
| **FR-SEC-003** | Document Upload Quarantine Outside Web | `Proposals` / `Careers`| `C-10` | `proposals`, `career_applications` | Ingestion into `storage/private/quarantine/` | Phase 10 |
| **FR-SEC-004** | Authenticated Document Streaming Proxy | `Proposals` / `Careers`| `C-07`, `C-10` | `proposals`, `career_applications` | `GET /api/v1/admin/{proposals,applications}/download` | Phase 10 / 12 / 14 |
| **FR-SEC-005** | Production Fail-Fast Boot Security | `Core` | `C-05`, `C-07` | N/A (System Environment) | Boot Validator (`bootstrap/app.php`) | Phase 4 |
| **FR-SEC-006** | Zero Secrets in Version Control | `Core` / Deployment | `C-15` | N/A (Repository Config) | `.gitignore`, Gitleaks Pre-Commit Hook | Phase 4 / 31 |
| **FR-SEC-007** | Safe CLI Database Migrations Only | `Core` / Database | `C-08`, `C-15` | `schema_migrations` | CLI Runner (`php cli/migrate.php`) | Phase 5 |
| **FR-SEO-001** | Hybrid Semantic Pre-Rendering | `SEO` / Pre-Renderer | `C-12` | `pages`, `services`, `articles` | PHP Pre-Renderer (`public_html/index.php`) | Phase 16 / 29 |
| **FR-SEO-002** | Authentic HTTP 404 Status Header | `Core` / `SEO` | `C-02`, `C-12` | N/A (Server Routing) | Front Controller Error Routing | Phase 16 / 29 |
| **FR-SEO-003** | Dynamic XML Sitemap Generation | `SEO` | `C-07` | `services`, `industries`, `articles` | `GET /sitemap.xml` | Phase 16 / 29 |
| **FR-SEO-004** | Structured Data JSON-LD Schemas | `SEO` | `C-12` | `system_settings`, `services` | Front Controller Head Ingestion | Phase 16 / 29 |
| **FR-A11Y-001** | Mathematical WCAG 2.2 AA Contrast | Design Tokens | `C-03`, `C-04` | N/A (CSS Design Tokens) | Tailwind Theme Preset (`colors.ts`) | Phase 3 / 19 / 28 |
| **FR-A11Y-002** | Complete Keyboard Navigation & Focus | UI Components | `C-03`, `C-04` | N/A (React Components) | Focus Trapping & ARIA Event Handlers | Phase 19 / 28 |
| **FR-A11Y-003** | Reduced-Motion Media Query Support | UI Motion | `C-03` | N/A (Motion Config) | `prefers-reduced-motion` Hook in Motion | Phase 19 / 28 |

---

## 3. Mathematical Traceability Reconciliation Summary

| Architectural Dimension | Total Canonical Requirements | Mapped to Phase 2 Architecture | Coverage Metric | Audit Result |
| :--- | :---: | :---: | :---: | :---: |
| **Public Website Requirements (`FR-PUB`)** | 23 | 23 | **100.0%** | **PASS** |
| **Admin Module Requirements (`FR-ADM`)** | 19 | 19 | **100.0%** | **PASS** |
| **Workflow State Machine Requirements (`FR-WF`)** | 6 | 6 | **100.0%** | **PASS** |
| **Platform Security Requirements (`FR-SEC`)** | 7 | 7 | **100.0%** | **PASS** |
| **SEO Engine Requirements (`FR-SEO`)** | 4 | 4 | **100.0%** | **PASS** |
| **Accessibility Requirements (`FR-A11Y`)** | 3 | 3 | **100.0%** | **PASS** |
| **Total Platform Requirements** | **62 Requirements** | **62 Requirements** | **100.0% Mapped** | **PASS** |
