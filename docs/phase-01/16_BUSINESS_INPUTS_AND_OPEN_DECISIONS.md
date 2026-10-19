# Business Inputs & Decision Register
## Formal Governance Ledger of Confirmed Baseline Decisions, Unverified Claims, Blockers, and Resolution Paths

**Document Reference:** `docs/phase-01/16_BUSINESS_INPUTS_AND_OPEN_DECISIONS.md`  
**Phase:** Phase 1 of 31 — Complete Functional Specification  
**Baseline Authority:** Phase 0 Unverified Assumptions & Blockers (`docs/phase-00/15_UNVERIFIED_ASSUMPTIONS_AND_BLOCKERS.md`).

---

### 1. Decision Status Classification Taxonomy

To uphold absolute architectural integrity and prevent speculation from corrupting implementation, every business parameter and architectural assumption is categorized into one of five unambiguous states:

```
┌─────────────────┬────────────────────────────────────────────────────────────┐
│ Status Code     │ Definition & Governance Rule                               │
├─────────────────┼────────────────────────────────────────────────────────────┤
│ CONFIRMED       │ Formally reviewed, agreed, and locked by project authority. │
│ PROPOSED        │ Architecturally sound proposal pending client confirmation.│
│ UNVERIFIED      │ Business data not yet validated against legal reality.     │
│ BLOCKED         │ Downstream work halted until required input is provided.   │
│ NOT APPLICABLE  │ Out-of-scope feature permanently excluded from platform.   │
└─────────────────┴────────────────────────────────────────────────────────────┘
```

> [!CRITICAL]
> **Promotion Rule:** Under no circumstances shall an item marked `PROPOSED` or `UNVERIFIED` be promoted to `CONFIRMED` without written corporate verification from Voak Health Care leadership.

---

### 2. Master Decision Register

| Decision ID | Domain | Business / Technical Parameter | Status | Current Working Baseline | Blocking Phase | Work Permitted Meanwhile |
| :--- | :--- | :--- | :---: | :--- | :---: | :--- |
| **DEC-01** | Core Technology | Frontend: React + TypeScript + Vite | **CONFIRMED** | React 18+ SPA build with Tailwind CSS | None | Full design & component architecture |
| **DEC-02** | Core Technology | Styling: Tailwind CSS | **CONFIRMED** | Tailwind CSS with approved color tokens | None | Component styling & responsive layouts |
| **DEC-03** | Core Technology | Backend: PHP 8.3+ Native REST API | **CONFIRMED** | PHP 8.3+ native OOP, PDO, strict types | None | API contracts, validation, services |
| **DEC-04** | Core Technology | Database: MySQL 8.x InnoDB | **CONFIRMED** | MySQL 8.x with utf8mb4 collation | None | Relational schema & CLI migrations |
| **DEC-05** | Infrastructure | Hostinger Shared Hosting Deployment | **CONFIRMED** | No Node.js daemon; Apache rewrite to PHP | None | Shared hosting configuration |
| **DEC-06** | Visual Authority| Approved 2nd Generated Homepage | **CONFIRMED** | Soft lavender (`#F2F0F8`), pastels, serif | None | Phase 3 UI design & design system |
| **DEC-07** | A11y Standards | WCAG 2.2 Level AA Compliance | **CONFIRMED** | Corrected muted text `#50567A` (6.30:1) | None | Contrast tokens & accessible markup |
| **DEC-08** | Business Model | B2B Healthcare Operations Only | **CONFIRMED** | Corporate B2B; strict non-clinical care | None | Enterprise workflows & RFP engine |
| **DEC-09** | Scope Anti-Goal| Patient Clinical EMR / Telemedicine | **NOT APPLICABLE**| Excluded permanently from platform scope | None | Excluded from all specifications |
| **DEC-10** | Business Scope | 6 Healthcare Service Offerings | **PROPOSED** | 6 configurable service proposals | Phase 22 (Public) | Dynamic CMS catalog & admin CRUD |
| **DEC-11** | Business Scope | 6 Healthcare Industry Verticals | **PROPOSED** | 6 configurable sector profiles | Phase 22 (Public) | Dynamic CMS catalog & admin CRUD |
| **DEC-12** | Corporate Identity| Legal Entity Name & Registration | **UNVERIFIED** | Config variable `{{legal_entity_name}}`; requires client confirmation before production | Phase 24 (Legal) | Architecture, CMS, draft legal text |
| **DEC-13** | Corporate Identity| Business / Tax Registration ID | **UNVERIFIED** | Config variable `{{registration_number}}` via Admin Module 18 (e.g., ABN/Company No.) | Phase 24 (Legal) | Footer component reads from settings |
| **DEC-14** | Corporate Identity| Headquarters Physical Street Address | **UNVERIFIED** | Config variable `{{registered_address}}` via Admin Module 18 | Phase 23 (Contact) | Display regional state/city context |
| **DEC-15** | Commercial Comm| Official Telephone Hotline Number | **UNVERIFIED** | Configurable via Admin Module 18; international E.164 format supported | Phase 23 (Contact) | Fallback to "Inquire Online" CTA |
| **DEC-16** | Commercial Comm| Official Public Inquiries Email | **UNVERIFIED** | Configurable via Admin Module 18 | Phase 23 (Contact) | Form posts to backend DB directly |
| **DEC-17** | Social Proof | Partner Logos & Hospital Clients | **UNVERIFIED** | Strict conditional rendering: 0 = hidden | Phase 20 / 24 | Total DOM collapse; no broken logos |
| **DEC-18** | Social Proof | Client Testimonials & Endorsements| **UNVERIFIED** | Strict conditional rendering: 0 = hidden | Phase 20 / 24 | Total DOM collapse |
| **DEC-19** | Evidence Proof | Case Studies & Numerical Impact | **UNVERIFIED** | Mandatory release consent gate in CMS | Phase 24 (Public) | Case study CMS engine developed |
| **DEC-20** | Recruitment | Corporate & Clinical Vacancies | **PROPOSED** | Careers portal with mock baseline data | Phase 24 (Careers)| Careers engine & resume quarantine |
| **DEC-21** | Infrastructure | Transactional Email & Outbox Architecture | **PROPOSED** | Durable outbox (`email_queue`), atomic claiming, bounded timeouts, Module 12 retries | Phase 13 (Email) | Queue schema & offline development |
| **DEC-22** | Infrastructure | Cloudflare Turnstile Site/Secret Keys| **PROPOSED** | Configurable via server `.env` | Phase 23 (Forms) | Mock validator in local dev environment|
| **DEC-23** | Operating Scope | Confirmed Operating Jurisdictions | **UNVERIFIED** | International phone E.164 support; country selector; conditional regional compliance | Phase 24 (Legal) | Multi-jurisdiction architecture |

---

### 3. Deep-Dive on Unresolved Business Decisions

#### Decision DEC-10: Confirmation of Core Healthcare Service Offerings
- **What Information is Missing:** Formal confirmation from Voak executive leadership that the 6 proposed service pillars (Clinical Workforce Solutions, Healthcare Facility Operations, Health Administration, Clinical Compliance, Logistics & Supply, Consulting & Advisory) represent their actual commercial product matrix.
- **Why It Matters:** Marketing copy, deliverables checklists, and SLA metrics depend on whether Voak actually offers facilities maintenance and logistics, or focuses purely on clinical staffing.
- **Who Should Provide It:** Managing Director / Commercial Director of Voak Health Care.
- **Which Phase Requires It:** **Phase 22 (Public Services Catalog)** and **Phase 31 (Production Launch)**.
- **What Work Can Continue Meanwhile:** The entire technical architecture, database schema, administrative CRUD console, filtering logic, and dynamic routing are 100% agnostic and configurable. All 6 services exist as database records that can be renamed, reordered, or unpublished in seconds via Module 06.
- **What Implementation or Publication is Blocked:** Final marketing copy sign-off for public service detail pages.

#### Decision DEC-12 to DEC-16: Corporate Registration & Contact Identifiers
- **What Information is Missing:** Formal Certificate of Incorporation, registered business number (`{{registration_number}}`), physical corporate office address (`{{registered_address}}`), official public telephone number, and official corporate email addresses (`@voakhealthcare.com`).
- **Why It Matters:** Required for legal compliance footers, public transparency, and outbound SMTP sender verification (DKIM/SPF alignment). Operating jurisdiction is not yet confirmed.
- **Who Should Provide It:** Corporate Legal Counsel / Executive Leadership.
- **Which Phase Requires It:** **Phase 21 (Corporate Pages)**, **Phase 23 (Contact Channels)**, and **Phase 31 (Hostinger Go-Live)**.
- **What Work Can Continue Meanwhile:** Full application development. The contact and footer components query `system_settings` dynamically using configuration variables (`{{legal_entity_name}}`, `{{registration_number}}`, `{{registered_address}}`). If values are empty, the UI displays sensible fallbacks ("Inquire Online") without breaking layouts. Form validation accepts international E.164 phone numbers. Technical specifications require zero final contact details to complete.
- **What Implementation or Publication is Blocked:** Public launch of `/contact` with official phone numbers and legal terms corporate registration publication.

#### Decision DEC-17 to DEC-19: Institutional Social Proof & Case Studies
- **What Information is Missing:** Executed client consent release forms permitting the public display of hospital network logos, partner names, executive testimonials, and measurable case study statistics.
- **Why It Matters:** Displaying unconfirmed client affiliations or fabricated satisfaction percentages violates Australian Consumer Law (Competition and Consumer Act 2010 - misleading and deceptive conduct) and breaches healthcare non-disclosure agreements.
- **Who Should Provide It:** Commercial Partnerships Lead / Legal Officer.
- **Which Phase Requires It:** **Phase 20 (Homepage)** and **Phase 24 (Case Studies & Partners)**.
- **What Work Can Continue Meanwhile:** The architectural engine, CMS modules, and database schemas for case studies and testimonials will be fully built. The conditional rendering engine ensures that until an admin explicitly attaches verified consent and marks `is_published = 1`, all social proof sections collapse cleanly from the public DOM.
- **What Implementation or Publication is Blocked:** Displaying real client logos or identifying hospital case studies.

#### Decision DEC-21: Hostinger Production SMTP Authentication Credentials
- **What Information is Missing:** Live production mailbox credentials (`smtp.hostinger.com:465`, username, and application password) with verified SPF/DKIM DNS records on the primary domain.
- **Why It Matters:** Hostinger shared hosting restricts local PHP `mail()`. Outbound transactional email delivery requires authenticated socket SMTP.
- **Who Should Provide It:** System Administrator / Hostinger Account Owner.
- **Which Phase Requires It:** **Phase 13 (Transactional Email)** and **Phase 31 (Production Deployment)**.
- **What Work Can Continue Meanwhile:** Architecture, email queue database schema, template compiler, and offline mock email testing in local environment.
- **What Implementation or Publication is Blocked:** Live outbound email delivery testing to real inboxes.

---

### 4. Risk Mitigation & Project Acceleration Protocol

To ensure uninterrupted progress through the 32-phase sequence without waiting for external business inputs:
1. **Configurable-First Principle:** Every unverified parameter is decoupled from source code and managed via MySQL database tables and Admin Module 18 (`system_settings`).
2. **Safe Fallback Guarantees:** Components render gracefully when settings are blank (e.g., hiding telephone badges, omitting unpublished partner logos).
3. **No Blocker to Technical Phases:** Phases 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19 can all proceed to 100% completion using configurable fixtures and mock baselines.
