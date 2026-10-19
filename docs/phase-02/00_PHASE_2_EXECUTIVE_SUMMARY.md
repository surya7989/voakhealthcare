# Phase 2 Executive Summary: Complete System Architecture

**Document Reference:** `docs/phase-02/00_PHASE_2_EXECUTIVE_SUMMARY.md`  
**Phase:** Phase 2 of 31 — Complete System Architecture  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Executing Roles:** Principal Full-Stack Software Architect, Staff PHP Engineer, Senior React Architect, Senior MySQL Database Architect, Application Security Architect, SEO Rendering Engineer, Accessibility Engineering Lead, Site Reliability Engineer, and Hostinger Deployment Specialist  
**Baseline Authority:** Accepted Phase 0 Baseline (`docs/phase-00/`) and Accepted Phase 1 Functional Specification (`docs/phase-01/`)  
**Status:** **ACTIVE ARCHITECTURAL SPECIFICATION** (Architecture Only — Implementation Deferred to Subsequent Phases)

---

## 1. Executive Charter & Architectural Mission

Phase 2 establishes the complete, production-grade technical system architecture for the Voak Health Care platform. Following the successful acceptance of Phase 0 (Requirements & Hosting Baseline) and Phase 1 (Complete Functional Specification), this phase transforms the 62 accepted canonical functional requirements into an implementable, modular, resilient, and secure engineering blueprint.

The architecture is specifically engineered to operate within the verified constraints of **Hostinger shared hosting (PHP/HTML deployment model)** without requiring:
- A persistent Node.js background process
- A Redis or Memcached cache daemon
- A Docker container runtime
- A continuously executing background worker daemon
- An unbudgeted upgrade to a Virtual Private Server (VPS)

Every architectural choice in this blueprint is supported by concrete technical rationale, aligns with OWASP ASVS v4.0.3 Level 2 security standards, preserves the approved 32-phase sequential roadmap (Phases 0 through 31), and maintains absolute fidelity to the second user-approved visual design.

---

## 2. Core Architectural Pillars

```
+----------------------------------------------------------------------------------------------------+
|                                    VOAK HEALTH CARE PLATFORM                                       |
+----------------------------------------------------------------------------------------------------+
|                                      PUBLIC ENTRY & ROUTING                                        |
|  - Cloudflare CDN / DNS Proxy (Turnstile, SSL, Edge Caching)                                       |
|  - Hostinger Apache Web Server (.htaccess Front Controller Engine)                                 |
+----------------------------------------------------------------------------------------------------+
|                PUBLIC FRONTEND                                    ADMIN CONSOLE                    |
|  - React 18+ / TypeScript / Vite Build             - React 18+ / TypeScript SPA (Private)          |
|  - Tailwind CSS + Motion for React                 - Role-Based UI Rendering (5 Roles)             |
|  - PHP Semantic Pre-Render (#ssr-fallback)         - TanStack Query / Normalization Layer          |
|  - React createRoot Client Mounting                - Secure Cookie /api/v1/admin Session Auth      |
+----------------------------------------------------------------------------------------------------+
|                                  PHP BACKEND ARCHITECTURE (PHP 8.x)                                 |
|  - Front Controller Gateway (/api/index.php) -> Routing to /api/v1/*                                |
|  - PSR-4 Autoloading, Strict Typing, Dependency Injection Container                                |
|  - Middleware Pipeline: CORS, CSRF Synchronizer, Session Guard, RBAC Gate (AuthorizePermission)     |
|  - 20 Modular Domain Services & Repositories (PDO Prepared Statements)                              |
+----------------------------------------------------------------------------------------------------+
|                                       PERSISTENCE & STORAGE                                        |
|  - MySQL 8.x InnoDB (utf8mb4_unicode_ci) Relational Database (26 Reconciled Entities)              |
|  - Public Asset Storage (WebP Media Library under public_html/assets/uploads/)                     |
|  - Private Quarantined Storage (Resumes, RFPs under storage/private/quarantine/ outside web root)  |
+----------------------------------------------------------------------------------------------------+
|                                  OUTBOX & INTEGRATION PIPELINES                                    |
|  - Durable MySQL Email Outbox (email_queue) with Atomic Claiming & Exponential Backoff             |
|  - Bounded Synchronous Fast-Path (max 4s) for Urgent Auth (MFA, Password Reset)                    |
|  - Module 12 Outbox Telemetry & Manual Admin Retry Console                                         |
+----------------------------------------------------------------------------------------------------+
```

### Pillar 1: Hybrid Semantic Pre-Rendering & React Client Mounting
To satisfy strict SEO requirements on Hostinger shared hosting without Node.js SSR, public pages leverage **PHP Hybrid Semantic Pre-Rendering**. The Apache server routes public requests to `index.php`, where PHP queries the database and renders full semantic HTML, OpenGraph tags, canonical links, and Schema.org JSON-LD into `<article id="ssr-fallback">`. When the client bundle loads, React executes `createRoot` into `#root`, and CSS immediately suppresses `#ssr-fallback` without visual flashing or hydration mismatches.

### Pillar 2: Fixed Versioned Session Cookie Architecture
Phase 2 resolves the critical integration discrepancy identified in review: the authentication cookie path is codified as `Path=/api/v1/admin; Secure; HttpOnly; SameSite=Lax`. This ensures that all requests to protected administrative endpoints (`/api/v1/admin/*`) automatically include the session cookie under RFC 6265 path-matching rules, while completely shielding public routes and general API endpoints from unintended credential transmission.

### Pillar 3: Modular PHP Backend with 20 Domain Boundaries
The backend is structured into 20 cohesive, decoupled modules with strict unidirectional dependency rules:
`Core`, `Auth`, `RBAC`, `Users`, `Pages`, `Homepage`, `Services`, `Industries`, `Media`, `Contact Enquiries`, `Partnerships`, `Proposals`, `Careers`, `Articles`, `Case Studies`, `Testimonials`, `SEO`, `Email`, `Settings`, and `Audit`. All database access executes through repository abstractions utilizing PHP PDO prepared statements.

### Pillar 4: Hostinger-Compatible Transactional Email Architecture
Acknowledging the operational realities of shared hosting, email dispatching does not rely on a 1-minute continuous daemon. It implements a resilient tri-tier architecture:
1. **Synchronous Fast-Path:** Urgent auth emails (MFA verification, password reset) execute bounded socket delivery (max 4s) directly during the request cycle.
2. **Durable Outbox Queue:** General inquiries, RFPs, and candidate confirmations write to `email_queue` and are claimed atomically (`UPDATE ... claimed_at=NOW(), worker_id=? LIMIT 20`) by Hostinger CLI scheduled tasks.
3. **Module 12 Outbox Telemetry & Retry Console:** Web Administrators monitor outbox health, inspect SMTP error logs, and trigger single-click manual retries.

### Pillar 5: Quarantined Document Security & RBAC Streaming
Public marketing images are optimized to WebP and served publicly. In contrast, sensitive applicant resumes and corporate tender proposals are stored strictly **outside the Apache web root** in `storage/private/quarantine/`. Direct HTTP access is blocked via web server rules; documents stream exclusively through authenticated PHP proxy controllers enforcing object-level authorization and recording cryptographic audit rows.

---

## 3. Scope Boundaries & Strict Enforcement

| In Scope for Phase 2 Architecture | Out of Scope (Deferred to Future Phases) |
| :--- | :--- |
| **System architecture & component diagrams** | Implementing React components or pages (Phases 19–26) |
| **PHP backend directory & module contracts** | Writing PHP controllers, services, or models (Phases 4–17) |
| **Complete MySQL schema & migration strategy** | Executing SQL DDL migrations against live databases (Phase 5) |
| **OpenAPI 3.1 contract specification** | Developing client API query hooks or mock servers (Phase 4) |
| **Authentication & RBAC session cookie policies** | Enrolling live users or configuring production MFA (Phase 6) |
| **SEO rendering & Apache routing blueprints** | Deploying production `.htaccess` or tuning live OPcache (Phase 31) |
| **Hostinger deployment topology & directory layout** | Uploading files to Hostinger or altering live sites (Phase 31) |
| **Threat model & OWASP ASVS control mappings** | Conducting penetration tests or vulnerability scans (Phase 30) |

> [!CAUTION]
> **Strict Stop Condition Notice:**  
> Phase 2 is an **architectural specification milestone only**. In accordance with enterprise governance rules, no application features, database tables, or production deployments will be executed during this phase.

---

## 4. Phase 2 Deliverables Suite Overview

The Phase 2 documentation suite is organized across 19 authoritative specifications in `docs/phase-02/`:

```
docs/phase-02/
├── 00_PHASE_2_EXECUTIVE_SUMMARY.md               (This Document — Scope, Pillars & Charter)
├── 01_COMPLETE_SYSTEM_ARCHITECTURE.md            (Mermaid Diagrams, Component Blueprints, Topologies)
├── 02_PHP_BACKEND_MODULE_ARCHITECTURE.md         (PSR-4, Container, 20 Domain Modules & Contracts)
├── 03_REACT_APPLICATION_ARCHITECTURE.md          (Public & Admin React Apps, Routing, State, Motion)
├── 04_API_CONTRACTS_AND_OPENAPI.md               (REST Contracts, Schemas, Status Envelopes, OpenAPI 3.1)
├── 05_DATABASE_SCHEMA_DESIGN.md                  (MySQL 8.x InnoDB, 28 Entities, CLI Migrations, DDL)
├── 06_AUTHENTICATION_RBAC_AND_SESSION_ARCHITECTURE.md (Cookie Path Fix, 5 Roles, TOTP MFA, CSRF)
├── 07_SEO_RENDERING_AND_ROUTING_ARCHITECTURE.md     (PHP Pre-Rendering, createRoot Mount, Apache Rules)
├── 08_EMAIL_OUTBOX_AND_DELIVERY_ARCHITECTURE.md     (Durable Outbox, Atomic Claiming, Module 12 Retries)
├── 09_MEDIA_STORAGE_AND_SECURITY.md              (Public WebP vs Private Quarantined Document Streaming)
├── 10_CMS_WORKFLOWS_AND_CACHE_INVALIDATION.md     (Draft/Publish FSM, Safe 301 Slugs, Optimistic Locks)
├── 11_HOSTINGER_DEPLOYMENT_TOPOLOGY.md           (Filesystem Layout, .htaccess, Release Packaging)
├── 12_SECURITY_THREAT_MODEL_AND_CONTROLS.md      (OWASP ASVS v4.0.3 L2, Threat Matrix, Security Headers)
├── 13_PERFORMANCE_LOGGING_AND_MONITORING.md      (Core Web Vitals, Bundle Splitting, Audit Logging)
├── 14_TEST_ARCHITECTURE_AND_VERIFICATION.md      (PHPUnit, Vitest, Playwright, 62 Test Traceability)
├── 15_ARCHITECTURAL_DECISION_RECORDS.md          (ADR-001 through ADR-010 Formal Records)
├── 16_PHASE_2_REQUIREMENT_TRACEABILITY.md        (Bidirectional Traceability from 62 Requirements)
├── 17_UNRESOLVED_ARCHITECTURAL_DECISIONS.md      (Technical Spikes, Hosting Limits & Open Business Items)
├── 18_PHASE_2_FINAL_COMPLETION_REPORT.md         (Governance Audit, Verification Evidence & Sign-Off)
└── openapi/
    └── openapi.json                              (Machine-Readable OpenAPI 3.1 JSON Specification)
```

---

## 5. Architectural Verification & Handoff Standard

Upon completion of Phase 2, the architecture will be subjected to the **26-Point Architectural Validation Gate** documented in Section 21 of the specification. Progression to **Phase 3 (Professional UI/UX Design)** will proceed only after formal user review and explicit authorization.
