# Phase 23 — Careers Portal Implementation Plan

## A. Official Phase Name
- **Official Phase Name:** Careers Portal & Candidate Application Engine
- **Target Milestones:** `PAGE-PUB-12` (Careers Portal), `PAGE-PUB-13` (Job Details & Application Portal), `FORM-PUB-04` (Candidate Application Intake)

---

## B. Business Objective
Attract high-caliber Australian registered nurses, clinical leads, healthcare operations managers, and medical administrators to Voak Health Care's national workforce network and operational headquarters. Provide a frictionless, transparent, and mobile-responsive careers board with real-time department and employment-type filtering, comprehensive role specifications, and an accessible, privacy-compliant application flow with quarantined resume attachments.

---

## C. Canonical Page & Form Identifiers
1. `PAGE-PUB-12`: Careers Portal (`/careers`)
2. `PAGE-PUB-13`: Job Details & Application Portal (`/careers/:slug`)
3. `FORM-PUB-04`: Public Candidate Job Application & Secure Resume Upload
4. `PAGE-PUB-404`: Semantic 404 Recovery View for missing, archived, or draft job postings

---

## D. Careers Listing Route (`/careers`)
- **Route:** `/careers`
- **Canonical URL:** `https://voakhealthcare.com.au/careers`
- **Component:** `frontend/src/apps/public/pages/CareersPage.tsx`
- **Key Page Sections:**
  1. Semantic Breadcrumbs (`Home > Careers`)
  2. Employer Value Proposition (EVP) Hero: *"Shape the Future of Operational Healthcare"*
  3. Culture & Professional Development Pillars: Clinical Governance Support, Transparent Remuneration, Workforce Resilience, Continuous CPD & Upskilling
  4. Interactive Search & Multi-Facet Filtering Console (Keyword, Department, Employment Type, Location)
  5. Live Openings Directory (Role cards with title, department, employment type, location, closing date, and Apply CTA)
  6. Talent Network Expression of Interest Callout (For candidates seeking general placement or prospective opportunities)
  7. Non-Clinical Care Legal Disclaimer & Employment Boundary Notice

---

## E. Job Detail Route (`/careers/:slug`)
- **Route:** `/careers/:slug` (e.g. `/careers/clinical-nurse-lead`, `/careers/palliative-care-lead`)
- **Canonical URL:** `https://voakhealthcare.com.au/careers/:slug`
- **Component:** `frontend/src/apps/public/pages/JobDetailPage.tsx`
- **Key Page Sections:**
  1. Semantic Breadcrumbs (`Home > Careers > :job_title`)
  2. Role Header with Title, Department badge, Employment Type pill, Location indicator, and Closing Date
  3. Role Overview & Strategic Purpose
  4. Key Responsibilities Checklist
  5. Qualifications, Credentialing & AHPRA Requirements
  6. Compensation, Benefits & Governance Support
  7. Embedded Candidate Application Form with drag-and-drop resume attachment
  8. Sibling Job Openings Recommendation Grid

---

## F. Application Flow
- Embedded on `/careers/:slug` below role specifications with accessible `#apply` smooth-scroll target.
- Single-page application submission that communicates directly with `POST /api/v1/public/careers/apply`.
- Instant inline client-side validation for mandatory fields.
- Accessible submission progress state with disabled controls during network transit.
- High-visibility confirmation screen displaying the generated candidate reference code (e.g. `APP-2026-XXXXXX`), submission timestamp, 6-month configured candidate-data retention policy disclosure (FR-SEC-004), and guidance on interview timelines.

---

## G. Vacancy Data Source & Contracts
- **Primary API Endpoints:**
  - `GET /api/v1/public/jobs` — returns active published jobs (`is_active = 1` and `closing_date >= CURRENT_DATE`).
  - `GET /api/v1/public/jobs/:slug` — returns single published job detail.
- **Graceful Hydration Pattern:**
  - Pages initialize synchronously with verified canonical baseline job fixtures to prevent blank layouts, layout shift, or flashing skeleton loaders.
  - Background `useEffect` hydrates live backend updates asynchronously.
  - Full conditional caching support (`ETag` and HTTP 304).

---

## H. Search & Multi-Facet Filtering
- **Keyword Search:** Instant live filtering matching role title, department, location, summary, and requirements.
- **Department Facets:** `All Departments`, `Clinical Operations`, `Operations`, `Clinical Governance`, `Corporate`.
- **Employment Type Facets:** `All Types`, `Full-Time` (`FULL_TIME`), `Part-Time` (`PART_TIME`), `Casual` (`CASUAL`), `Contract` (`CONTRACT`).
- **Location Facets:** `All Locations`, `Sydney, NSW`, `Melbourne, VIC`, `Brisbane, QLD`, `Adelaide, SA`, `National / Remote`.
- **Accessible Announcements:** Dynamic result counts announced to assistive technologies via `aria-live="polite"`.
- **Filter Recovery:** One-click "Reset Filters" action button when zero vacancies match.

---

## I. Job Status Visibility & Security Gates
- Only published, active vacancies (`is_active = 1`) with current or future closing dates are presented to public candidates.
- Draft (`DRAFT`) and archived (`ARCHIVED`) internal requisitions are strictly filtered out by the backend and hidden from public view.
- Closed jobs (`is_active = 0` or expired closing date) render an authentic closed notice and strictly disable application intake.
- Invalid or unknown job slugs return semantic 404 views (`NotFoundPage`).

---

## J. Candidate Application Input Fields
1. `full_name` (Text input, required, 2–150 characters)
2. `email` (Email input, required, valid email format)
3. `phone` (Tel input, required, 8–25 digits, international E.164 compatible)
4. `country_code` (Hidden or select, default `'AU'`)
5. `ahpra_number` (Text input, optional for corporate, prominent for clinical roles)
6. `resume_file` (File input via `FileUploadDropzone`, mandatory, PDF or DOCX, max 10MB)
7. `website` (Hidden honeypot input, must remain empty)
8. `privacy_consent` (Checkbox, mandatory, non-pre-checked, aligned with the Privacy Act 1988 and Australian Privacy Principles (APPs))

---

## K. Resume Upload & Quarantine Architecture
- Reuses shared `FileUploadDropzone` primitive from Phase 19.
- Frontend restricts selection to `.pdf,.docx` and validates `file.size <= 10MB`.
- Server-side `MediaStorageService` inspects binary MIME magic bytes (`%PDF-`, `PK\x03\x04`).
- Uploaded resumes are quarantined in private filesystem storage outside the webroot (`storage/private/quarantine/`) with randomized UUID filenames and zero executable permissions.
- Browser MIME validation is documented as client-side UX only; server-side validation acts as the cryptographic security boundary.

---

## L. Anti-Spam & Abuse Defense
- **Honeypot Trap:** Hidden inputs (`website`, `hp_field`). Submissions with populated honeypot fields are dropped silently, returning a synthetic success response without mutating database or email queue (SYS-005).
- **IP Rate Limiting:** Enforces strict ceiling of 5 submissions per hour per IP. Returns HTTP 429 `ERR_RATE_LIMITED` when exceeded.
- **Client Handling:** Displays user-friendly retry timing without locking out the candidate permanently.

---

## M. Candidate Privacy & Data Retention (FR-SEC-004)
- **Privacy Act 1988 & Australian Privacy Principles (APPs):** Explicit consent notice informing candidate that application data is collected solely for employment qualification and recruitment vetting.
- **Configured Candidate-Data Retention Policy:** In accordance with VOAK's recruitment data retention policy under FR-SEC-004, candidate PII is marked with a 6-month retention expiry date (`retention_expiry_date = CURRENT_DATE + 6 months`), after which it is eligible for automated disposal and physical file shredding.
- Zero local browser storage (`localStorage` / `sessionStorage`) of candidate PII, phone numbers, or resume documents.

---

## N. Candidate Acknowledgement Email
- Backend `CareerService` and `EmailService` automatically enqueue an asynchronous acknowledgement email (`tmpl_careers_ack`) in `email_queue`.
- Client application displays instant on-screen reference code and confirmation message without attempting direct browser SMTP communication.

---

## O. Accessibility (WCAG 2.2 AA Target)
- Exactly one logical `<h1>` per page.
- Accessible form fields using `FieldLabel`, `FormField`, `FieldError`, and `aria-describedby`.
- Dropzone supports drag-and-drop and standard keyboard file dialog trigger.
- Error validation summary announced via `role="alert"`.
- Results count announced via `aria-live="polite"`.
- All touch targets meet minimum 44x44px requirements.

---

## P. SEO & JobPosting Structured Data
- Canonical meta titles and descriptions injected via `SeoHead`:
  - `/careers`: `Careers in Healthcare Operations & Clinical Staffing | Voak Health Care`
  - `/careers/:slug`: `{job.title} | Careers | Voak Health Care`
- **Schema.org Structured Data:**
  - `/careers`: `CollectionPage` schema with `hasPart` job listings.
  - `/careers/:slug`: Schema.org `JobPosting` schema with verified fields:
    - `@type`: `JobPosting`
    - `title`: Job title
    - `description`: Role summary and responsibilities
    - `datePosted`: Posting timestamp
    - `validThrough`: Closing date
    - `employmentType`: Standard Schema employment type (`FULL_TIME`, `PART_TIME`, `CONTRACTOR`, `TEMPORARY`)
    - `hiringOrganization`: `VOAK Health Care Australia`
    - `jobLocation`: Australian metropolitan region

---

## Q. Traceability Matrix

| Dimension | Specification Item | Implementation Component | Classification |
| :--- | :--- | :--- | :--- |
| A | Official Phase Name | `docs/phase-23/01_CAREERS_PORTAL_PLAN.md` | Requires New Implementation |
| B | Business Objective | Workforce acquisition for Australian healthcare | Requires New Implementation |
| C | Canonical Page IDs | `PAGE-PUB-12`, `PAGE-PUB-13`, `FORM-PUB-04` | Requires New Implementation |
| D | Careers Listing Route | `/careers` | Requires New Implementation |
| E | Job Detail Route | `/careers/:slug` | Requires New Implementation |
| F | Application Route / Flow | Embedded `#apply` form on `/careers/:slug` | Requires New Implementation |
| G | Vacancy Data Source | `GET /api/v1/public/jobs` + Canonical Fallback | Requires New Implementation |
| H | Search Console | Debounced keyword query across roles | Requires New Implementation |
| I | Multi-Facet Filters | Department, Employment Type, Location | Requires New Implementation |
| J | Pagination / Counts | `aria-live` telemetry count announcement | Requires New Implementation |
| K | Job Status Visibility | Only active, published, non-expired jobs | Already Supported (Backend) / Enforced in UI |
| L | Job Detail Fields | Title, department, location, summary, responsibilities, requirements | Requires New Implementation |
| M | Application Fields | `full_name`, `email`, `phone`, `ahpra_number`, `resume_file`, consent | Requires New Implementation |
| N | Resume/CV Upload | `FileUploadDropzone` primitive | Already Supported (Shared Primitive) |
| O | File Restrictions | PDF/DOCX, <=10MB, binary magic bytes | Already Supported (Backend) / UX Enforced |
| P | Honeypot Protection | Hidden `website` trap (SYS-005) | Already Supported (Backend) / Form Integrated |
| Q | Rate Limiting | 5 submissions per hour per IP (SYS-005) | Already Supported (Backend) / UI Error Handling |
| R | Consent / Privacy | Explicit Privacy Act 1988 & APPs consent checkbox, 6-month VOAK retention policy | Requires New Implementation |
| S | Acknowledgement Email | Tier-2 outbox queue (`tmpl_careers_ack`) | Already Supported (Backend) |
| T | Success State | Reference code (`APP-2026-XXXXXX`) display | Requires New Implementation |
| U | Validation Errors | 422 mapping to `FieldError` and `ValidationSummary` | Requires New Implementation |
| V | Duplicate Submission | Debounce submit button, disable during submission | Requires New Implementation |
| W | Closed Vacancy Behavior | High-visibility closed warning banner, form disabled | Requires New Implementation |
| X | Loading States | Card & Detail Skeletons | Requires New Implementation |
| Y | Empty States | Helpful `EmptyState` with filter reset | Requires New Implementation |
| Z | Error States | `ErrorState` with retry action | Requires New Implementation |
| AA | 404 Behavior | Semantic `NotFoundPage` for missing slugs | Already Supported (Phase 22) / Integrated |
| AB | SEO Metadata | Route-specific titles, meta descriptions, Open Graph | Already Supported (`SeoHead`) |
| AC | JobPosting Structured Data | Schema.org `JobPosting` JSON-LD | Requires New Implementation |
| AD | Accessibility | WCAG 2.2 AA target, keyboard navigation, focus management | Requires New Implementation |
| AE | Responsive Design | Mobile, tablet, and desktop viewports | Requires New Implementation |
| AF | Security & PII | Quarantined storage, zero client storage of PII | Already Supported (Backend) / Form Discipline |
| AG | Testing | Dedicated test suite (`careers.phase23.test.tsx`) | Requires New Implementation |
| AH | Acceptance Criteria | 100% green local checks & GitHub Actions CI | Requires New Implementation |

---

## R. Non-Clinical Care Legal Notice & Operational Scope Boundaries
Public careers pages strictly include the mandatory B2B boundary disclaimer:
*"Voak Health Care is an Australian healthcare operational solutions and workforce infrastructure provider. Voak does not provide direct emergency medical triage, direct patient care booking, or public clinical consultations."*
All candidate placement opportunities strictly represent employment opportunities with Voak or deployed institutional healthcare client facilities.
