# Phase 15: Case Studies & Resources Backend Implementation Plan

## Executive Summary
This document defines the formal implementation and verification plan for **Phase 15: Case Studies & Resources Backend** of the VOAK Health Care platform. 

Phase 15 implements the backend services, administrative APIs, public delivery endpoints, and security controls for:
1. **Module 14: Insights & Thought Leadership Articles** (`ADM-016`, `FR-ADM-017`, `TEST-ADM-017`, `TEST-PUB-016`, `TEST-PUB-017`)
2. **Module 15: Case Studies & Social Proof Console** (`ADM-015`, `FR-ADM-016`, `TEST-ADM-016`, `TEST-PUB-013`)
3. **Module 16: FAQ Support Center** (`FR-PUB-018`, `TEST-PUB-018`)
4. **Publishing Workflow State Machine** (`FR-WF-005`, `TEST-WF-005`)

---

## 1. Canonical Requirements & Traceability Matrix

| Requirement ID | Module / Component | Canonical Description | Acceptance Test ID | Phase 15 Classification |
|---|---|---|---|---|
| **`ADM-015`** | Case Studies & Social Proof Console | Manage verified operational case studies, client sectors, and mandatory consent releases | `TEST-ADM-016` | Requires New Implementation |
| **`ADM-016`** | Insights & Articles CMS | Editorial content management with markdown/HTML, tags, reading time, author attribution | `TEST-ADM-017` | Requires New Implementation |
| **`FR-ADM-016`** | Case Studies & Social Proof Manager | Content manager enforcing mandatory verified release attachment before publishing | `TEST-ADM-016` | Requires New Implementation |
| **`FR-ADM-017`** | Insights & Article CMS | Article manager with reading time auto-calc (`ceil(words/200)`), tag JSON, XSS sanitization | `TEST-ADM-017` | Requires New Implementation |
| **`FR-PUB-013`** | Verified Case Studies Directory | Public delivery of published case studies with verified consent; 404 on unverified/draft | `TEST-PUB-013` | Requires New Implementation |
| **`FR-PUB-016`** | Insights Center Search & Categorization | Public delivery of published articles with category/tag filtering and debounced search | `TEST-PUB-016` | Requires New Implementation |
| **`FR-PUB-017`** | Dynamic Article Reader | Full article payload delivery with 3 related articles and HTML sanitization | `TEST-PUB-017` | Requires New Implementation |
| **`FR-PUB-018`** | Interactive FAQ Support Center | Categorized procurement and compliance FAQ accordions with display ordering | `TEST-PUB-018` | Requires New Implementation |
| **`FR-WF-005`** | CMS Publishing State Machine | Explicit transitions between `DRAFT` and `PUBLISHED` with consent and audit gates | `TEST-WF-005` | Requires Extension |

---

## 2. Database Schema Decision: Zero New Migrations Required

All three database tables required for Phase 15 were pre-provisioned during Phase 5 schema cluster creation:
- **Migration 22 (`20261021_000022_create_articles_table.php`):**
  - Columns: `id`, `title`, `slug` (UNIQUE), `summary`, `content_html`, `featured_image_id` (FK NULLABLE), `author_id` (FK), `category`, `tags_json` (JSON), `reading_time_minutes`, `is_published`, `published_at`, `created_at`, `updated_at`.
  - Indexes: `idx_articles_slug_published (slug, is_published, published_at)`, `idx_articles_category (category)`.
- **Migration 23 (`20261021_000023_create_case_studies_table.php`):**
  - Columns: `id`, `title`, `slug` (UNIQUE), `client_sector`, `challenge_summary`, `intervention_summary`, `outcome_metrics_json` (JSON), `consent_verified`, `is_published`, `published_at`, `created_at`, `updated_at`.
  - Indexes: `idx_cases_slug_published (slug, is_published)`.
- **Migration 24 (`20261021_000024_create_faqs_table.php`):**
  - Columns: `id`, `category`, `question`, `answer_html`, `display_order`, `is_published`, `created_at`, `updated_at`.
  - Indexes: `idx_faqs_category_order (category, display_order, is_published)`.

**DECISION:** **ZERO NEW MIGRATIONS REQUIRED.** Exactly 28 migrations remain in the system.

---

## 3. Architecture & Service Design

### A. Domain Services (`backend/src/Services/`)
1. **`ArticleService`**:
   - `getArticles(array $filters, int $page, int $perPage)`: Paginated list for admin.
   - `getArticleById(int $id)`: Retrieve single article for editing.
   - `getPublishedArticles(array $filters, int $page, int $perPage)`: Public feed.
   - `getPublishedBySlug(string $slug)`: Public detail + 3 related articles.
   - `createArticle(array $data, int $authorUserId)`: Validates title, summary, content, generates/validates slug, calculates reading time (`ceil(words / 200)`), sanitizes HTML, audit log `ARTICLE_CREATED`.
   - `updateArticle(int $id, array $data, int $editorUserId)`: Updates fields, recalculates reading time, sets `published_at` on first publish, audit log `ARTICLE_UPDATED` / `ARTICLE_PUBLISHED`.
   - `deleteArticle(int $id, int $editorUserId)`: Deletes article, audit log `ARTICLE_DELETED` / `ARTICLE_ARCHIVED`.
   - `exportCsv(array $filters, int $editorUserId)`: Admin CSV export with formula injection defense.

2. **`CaseStudyService`**:
   - `getCaseStudies(array $filters, int $page, int $perPage)`: Paginated list for admin.
   - `getCaseStudyById(int $id)`: Retrieve single case study.
   - `getVerifiedPublishedCaseStudies(array $filters, int $page, int $perPage)`: Public feed (enforces `is_published = 1 AND consent_verified = 1`).
   - `getVerifiedPublishedBySlug(string $slug)`: Public detail.
   - `createCaseStudy(array $data, int $editorUserId)`: Validates Problem-Intervention-Outcome format, `outcome_metrics_json`, slug, and consent verification. If `is_published = 1` requested without `consent_verified = 1`, throws `ValidationException` (HTTP 422). Audit log `CASE_STUDY_CREATED`.
   - `updateCaseStudy(int $id, array $data, int $editorUserId)`: Updates fields. Enforces consent release guard if `is_published = 1`. Audit log `CASE_STUDY_UPDATED` / `CASE_STUDY_PUBLISHED`.
   - `deleteCaseStudy(int $id, int $editorUserId)`: Deletes case study, audit log `CASE_STUDY_ARCHIVED`.
   - `exportCsv(array $filters, int $editorUserId)`: Admin CSV export with formula injection defense.

3. **`FaqService`**:
   - `getFaqs(array $filters, int $page, int $perPage)`: Paginated list for admin.
   - `getFaqById(int $id)`: Single FAQ item.
   - `getPublishedFaqs(?string $category, ?string $search)`: Public categorized listing ordered by `display_order`.
   - `createFaq(array $data, int $editorUserId)`: Validates category, question, sanitizes `answer_html`, audit log `FAQ_CREATED`.
   - `updateFaq(int $id, array $data, int $editorUserId)`: Updates fields, audit log `FAQ_UPDATED`.
   - `deleteFaq(int $id, int $editorUserId)`: Deletes item, audit log `FAQ_DELETED`.
   - `reorderFaqs(array $orderedIds, int $editorUserId)`: Updates `display_order` sequentially.

### B. Administrative Controllers (`backend/src/Modules/Admin/`)
- `AdminArticleController`: REST endpoints `/api/v1/admin/articles[/:id]`.
- `AdminCaseStudyController`: REST endpoints `/api/v1/admin/case-studies[/:id]`.
- `AdminFaqController`: REST endpoints `/api/v1/admin/faqs[/:id]`.

### C. Public Controller Extensions (`backend/src/Modules/PublicContent/PublicContentController.php`)
- `getArticles`, `getArticle`
- `getCaseStudies`, `getCaseStudy`
- `getFaqs`

---

## 4. Security, RBAC & Consent Enforcements

1. **Authentication & Session Gate:**
   - All admin endpoints fail closed. Unauthenticated requests return HTTP 401 `ERR_UNAUTHENTICATED`.
   - Never use fallback default admin IDs (`?? 1`).
2. **Strict Least-Privilege RBAC:**
   - Articles: `articles.view`, `articles.manage`
   - Case Studies: `case_studies.view`, `case_studies.manage`
   - FAQs: `faqs.view`, `faqs.manage`
   - Allowed roles: Super Admin, Website Admin, Content Editor.
   - Forbidden roles (e.g. unprivileged HR or BD): return HTTP 403 `ERR_FORBIDDEN`.
3. **CSRF Protection:**
   - All mutating admin requests (`POST`, `PUT`, `PATCH`, `DELETE`) require `X-CSRF-Token`.
4. **Mandatory Consent Release Guard (`FR-ADM-016` / `TEST-ADM-016`):**
   - Attempting to set `is_published = 1` when `consent_verified = 0` throws HTTP 422 `ERR_VALIDATION`:
     `"Cannot publish case study: verified client legal consent release is required."`
5. **Reading Time Auto-Calculation (`FR-ADM-017` / `TEST-ADM-017`):**
   - `reading_time_minutes` is automatically calculated as `max(1, (int)ceil($wordCount / 200))` from content.
6. **XSS & Content Sanitization:**
   - All HTML content passed through `HtmlSanitizer`.
7. **Audit Logging:**
   - Every mutation produces cryptographically linked audit event (`ARTICLE_CREATED`, `CASE_STUDY_PUBLISHED`, etc.).
   - Compensating rollback or fail-closed behavior on audit failure.

---

## 5. Verification Strategy & Test Suites

1. **`backend/tests/Unit/ArticleServiceTest.php`:**
   - Slug generation, collision avoidance, reading time calculation, tag JSON handling, publishing timestamp, sanitization, CSV export formula injection defense.
2. **`backend/tests/Unit/CaseStudyServiceTest.php`:**
   - Problem-Intervention-Outcome metrics JSON validation, **consent release guard test** (HTTP 422 when `consent_verified = 0`), public unverified exclusion test, audit logging.
3. **`backend/tests/Unit/FaqServiceTest.php`:**
   - Category filtering, display ordering, reordering, sanitization, audit logging.
4. **`backend/tests/Integration/CaseStudiesAndResourcesApiTest.php`:**
   - HTTP end-to-end testing for admin and public endpoints: authentication failures (401), RBAC failures (403), CSRF failures, validation errors (422), conditional ETag / 304 caching.
5. **`tests/test_phase15_case_studies_and_resources.php`:**
   - Master runner for Phase 15.
6. **`backend/tests/mysql_security_concurrency_test.php` (Test 14):**
   - MySQL 8.0 schema constraints, unique slugs, JSON outcome metrics, consent verification gate, reading time calculation, transactional updates, audit ledger integrity.
