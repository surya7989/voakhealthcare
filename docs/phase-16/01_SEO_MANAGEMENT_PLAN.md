# Phase 16: SEO Management Backend (SEO Engine, Redirects & Dynamic Sitemap Backend) — Plan

**Document Reference:** `docs/phase-16/01_SEO_MANAGEMENT_PLAN.md`  
**Phase:** Phase 16 of 32 — SEO Management Backend  
**Module ID:** Module 17 (`Voak\Health\Modules\SEO`)  
**Status:** **ACTIVE / IN IMPLEMENTATION**  
**Executing Role:** Principal Backend Engineer & SEO Systems Architect  
**Authoritative Baseline:** Verified Phase 15 HEAD (`26d489a723b0334e87eb0b13bf1709fdfbdde579`)  
**Deployment Target:** Hostinger PHP 8.3 / Apache Shared Hosting (Production Deferred)

---

## 1. Executive Summary & Architectural Scope

Phase 16 implements the authoritative **SEO Management Backend** for the Voak Health Care platform. In accordance with the 32-Phase Roadmap (`docs/phase-00/00_PHASE_0_EXECUTIVE_SUMMARY.md` and `docs/phase-01/15_REQUIREMENTS_TRACEABILITY_MATRIX.md`), Phase 16 is **strictly backend-only**. Administrative UI screens (`/admin/seo`) are deferred to **Phase 26**, and public frontend client hydration/progressive mount hooks are deferred to **Phase 29**.

This phase establishes the search engine foundation required for crawling, indexing, canonicalization, structured data delivery, automated 301 URL redirects, and soft-404 prevention.

### Non-Negotiable Operational Guarantees:
1. **Zero New Migrations Required:** Database table `url_redirects` was pre-provisioned in migration 26 (`20261021_000026_create_url_redirects_table.php`), `pages` in migration 09, `system_settings` in migration 27, and CMS entities in migrations 12, 13, 20, 22, 23, and 24. Database migrations remain strictly at 28.
2. **Fail-Closed Security & CSRF Enforcement:** All administrative redirect and SEO metadata mutation endpoints require active authentication (`AdminAuthenticateMiddleware`), granular RBAC validation (`seo:update_meta`, `seo:rebuild_sitemap`), and valid CSRF synchronizer tokens (`CsrfProtectionMiddleware`).
3. **Loop-Free 301 Redirect Engine (`FR-WF-006`):** Automatic cycle detection and redirect chain flattening. If route `A -> B` exists and `B -> C` is registered, `A -> B` is atomically updated to `A -> C` (1-hop resolution). If `C -> A` is registered, the circular loop is rejected with an error.
4. **Dynamic XML Sitemap (`FR-SEO-003`):** Dynamic W3C-compliant XML sitemap generation at `GET /sitemap.xml` with ISO 8601 `<lastmod>` dates. Drafts (`is_published = 0`), unverified case studies (`consent_verified = 0`), private tenders, resumes, and admin endpoints are strictly excluded.
5. **Structured Data JSON-LD (`FR-SEO-004`):** Production Schema.org JSON-LD generators for `Organization`, `Service`, `Article`, `FAQPage`, and `JobPosting`.
6. **Semantic Pre-Rendering Helpers (`FR-SEO-001`, `FR-SEO-002`):** Pre-rendering helper building semantic `<article id="ssr-fallback">` blocks and authentic HTTP 404 headers with `<meta name="robots" content="noindex, nofollow">` to eliminate soft-404 search penalties.

---

## 2. Requirements & Traceability Mapping

| Canonical Req ID | Feature / Component | Description | Phase 16 Status | Classification |
| :--- | :--- | :--- | :--- | :--- |
| **ADM-017** / **ADM-018** | SEO & Metadata Administration | Admin endpoints for route meta tag management and dynamic sitemap rebuilding. | Implementing API | Requires New Implementation |
| **FR-ADM-018** | SEO & Metadata Administration | Global and route-level meta title/description overrides, OpenGraph image assignments, XML sitemap cache invalidation. | Implementing API | Requires New Implementation |
| **FR-WF-006** | Safe Slug Renaming & 301 Redirect FSM | Slug renaming automatically registers 301 redirects; circular loop prevention; multi-hop flattening to 1-hop; reserved slug defense. | Implementing Engine | Requires New Implementation |
| **FR-SEO-001** | Hybrid Semantic Content Pre-Rendering | Server-rendered semantic HTML pre-rendering helper (`#ssr-fallback`) and metadata injection for crawlers (`ADR-005`). | Implementing Service | Requires New Implementation |
| **FR-SEO-002** | True HTTP 404 Emission | Emits authentic HTTP 404 status codes and `<meta name="robots" content="noindex, nofollow">` for non-existent entities, eliminating soft-404s. | Implementing Service | Requires New Implementation |
| **FR-SEO-003** | Dynamic XML Sitemap Generation | Dynamic `GET /sitemap.xml` listing active canonical URLs with ISO 8601 `lastmod` timestamps; strictly excludes drafts and quarantined files. | Implementing Endpoint | Requires New Implementation |
| **FR-SEO-004** | Structured Data (JSON-LD) Schemas | Schema.org valid JSON-LD schemas embedded for `Organization`, `Service`, `Article`, `FAQPage`, and `JobPosting`. | Implementing Service | Requires New Implementation |
| **TEST-ADM-018** | SEO Metadata & Sitemap Verification | Automated test validating meta title/description limits, sitemap rebuild, and XML structure. | Implementing Test | Requires New Implementation |
| **TEST-WF-006** | Redirect Loop Prevention & Chain Flattening | Test verifying circular redirect detection, 1-hop chain resolution, and reserved path blocking. | Implementing Test | Requires New Implementation |
| **TEST-SEO-001** | Semantic HTML & Crawler Pre-Rendering | Verifies `#ssr-fallback` semantic HTML emission and escaping. | Implementing Test | Requires New Implementation |
| **TEST-SEO-002** | Authentic 404 Status Header | Verifies HTTP 404 response emission on invalid slugs with `noindex` robots directive. | Implementing Test | Requires New Implementation |
| **TEST-SEO-003** | Dynamic XML Sitemap Schema Validation | Validates sitemap XML against W3C specification and checks draft exclusion. | Implementing Test | Requires New Implementation |
| **TEST-SEO-004** | Structured Data JSON-LD Schema Validation | Validates Schema.org JSON structures across entities. | Implementing Test | Requires New Implementation |

---

## 3. Detailed Architectural Design

### 3.1 Service Layer: `Voak\Health\Services\SeoService.php`
- `generateSitemapXml(): string`:
  - Pulls published pages (`pages` WHERE `is_published = 1`)
  - Pulls published services (`services` WHERE `is_published = 1`)
  - Pulls published industries (`industries` WHERE `is_published = 1`)
  - Pulls published articles (`articles` WHERE `is_published = 1`)
  - Pulls published case studies (`case_studies` WHERE `is_published = 1 AND consent_verified = 1`)
  - Adds core static canonical routes (`/`, `/about`, `/contact`, `/careers`, `/partnerships`, `/proposals`, `/case-studies`, `/insights`, `/faqs`, `/privacy-policy`, `/terms-of-service`)
  - Outputs strictly compliant XML with `<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">`, `<loc>`, `<lastmod>` (W3C ISO 8601 `Y-m-d`), and `<changefreq>` / `<priority>`
  - Excludes draft, archived, or internal administrative paths
  - Caches output in `storage/cache/seo/sitemap.xml` with automatic invalidation.
- `resolveRedirect(string $requestUri): ?array`:
  - Queries `url_redirects` for matching `source_path` where `is_active = 1`
  - Atomically increments `hit_count` and updates `last_accessed_at = CURRENT_TIMESTAMP`
  - Returns `['target_path' => ..., 'http_status_code' => 301]`.
- `registerRedirect(string $source, string $target, int $status = 301, ?int $userId = null): array`:
  - Normalizes source and target paths (ensures leading `/`, trims trailing `/`, lowercases)
  - Validates source does not equal target (self-redirect check)
  - Validates neither source nor target is a reserved system route (`/admin`, `/api`, `/sitemap.xml`, `/robots.txt`, `/health`)
  - Detects circular redirects (e.g. `B -> A` if attempting to add `A -> B`)
  - Flattens redirect chains to 1 hop: queries any existing redirects where `target_path = $source` and updates their `target_path = $target`
  - Inserts or updates in `url_redirects`
  - Writes cryptographic audit log entry (`REDIRECT_REGISTERED`).
- `listRedirects(array $filters = [], int $page = 1, int $perPage = 20): array`:
  - Returns paginated list of redirects for admin management.
- `deleteRedirect(int $id, ?int $userId = null): bool`:
  - Deletes or deactivates redirect by ID and writes audit log entry (`REDIRECT_DELETED`).
- `getStructuredData(string $route, array $context = []): array`:
  - Generates Schema.org JSON-LD data depending on route type:
    - Homepage (`/`): `Organization` and `ProfessionalService`
    - Service Detail (`/services/:slug`): `Service` schema
    - Article Detail (`/insights/:slug`): `Article` schema
    - FAQ (`/faqs`): `FAQPage` schema
    - Job Posting (`/careers/:slug`): `JobPosting` schema
- `renderSsrFallback(string $route, array $context = []): string`:
  - Generates safe escaped semantic HTML inside `<article id="ssr-fallback">` for crawlers and non-JS clients.

### 3.2 Controller Layer: `Voak\Health\Modules\Admin\AdminSeoController.php`
- `GET /api/v1/admin/seo/routes`: Lists all routes with meta titles, descriptions, and character length status indicators.
- `GET /api/v1/admin/seo/routes/:id`: Returns specific route SEO details.
- `PUT /api/v1/admin/seo/routes/:id`: Updates route meta title, description, and OpenGraph image (writes `SEO_METADATA_UPDATED` audit log).
- `POST /api/v1/admin/seo/rebuild-sitemap`: Triggers live sitemap cache invalidation and rebuild (writes `SITEMAP_REBUILT` audit log).
- `GET /api/v1/admin/seo/redirects`: Lists redirects with hit counts and last accessed dates.
- `POST /api/v1/admin/seo/redirects`: Registers new redirect (validates loop prevention & chain flattening).
- `DELETE /api/v1/admin/seo/redirects/:id`: Removes or deactivates redirect.

### 3.3 Public HTTP Layer in `Voak\Health\Core\App.php`
- `GET /sitemap.xml`: Returns XML sitemap with `Content-Type: application/xml; charset=utf-8`, conditional ETag (`W/"..."`), `Cache-Control: public, max-age=3600`.
- `GET /robots.txt`: Returns robots directives specifying sitemap location (`Sitemap: https://voakhealthcare.com/sitemap.xml`).
- Request interception: In `App::handle()`, checks `SeoService::resolveRedirect()` prior to router dispatch. If active redirect matches, returns HTTP 301/302 response with `Location` header immediately.

---

## 4. Verification & Testing Strategy

1. **Unit Test Suite (`backend/tests/Unit/SeoServiceTest.php`):**
   - W3C XML schema validation for generated sitemap.
   - Exclusion of drafts, unverified case studies, and internal admin URLs.
   - Circular redirect detection (`A -> B -> A` loop rejection).
   - Multi-hop chain flattening (`A -> B` and `B -> C` results in `A -> C`).
   - Reserved system route rejection (`/admin`, `/api`).
   - JSON-LD structured data compliance.
   - SSR semantic fallback generation and HTML escaping.
2. **Integration Test Suite (`backend/tests/Integration/SeoManagementApiTest.php`):**
   - Admin authentication & CSRF validation on all mutations.
   - Granular RBAC validation (`seo:update_meta`, `seo:rebuild_sitemap`).
   - Super Admin & Website Admin authorized; Content Editor, BD Manager, HR Manager denied (HTTP 403).
   - Public `/sitemap.xml` delivery and HTTP 304 ETag caching.
   - Public redirect resolution returning HTTP 301.
3. **Master Regression Runner (`tests/test_phase16_seo_management.php`):**
   - Comprehensive test assertions certifying Phase 16.
4. **MySQL 8.0 Concurrency Test Extension (`backend/tests/mysql_security_concurrency_test.php`):**
   - Test 16: Concurrent redirect resolution, atomic hit counters, and audit logging integrity under MySQL 8.0.
5. **Full Regression Baseline Execution:**
   - Run Phases 6 through 15 regression suites.
   - Run `test_backend_foundation.php` and `run_tests.php`.
   - Run `scan_secrets.php`, `composer validate --strict`, and PHP syntax linting.
6. **Continuous Integration (GitHub Actions):**
   - Ensure all jobs (PHP 8.2, PHP 8.3, MySQL 8.0) pass with zero failures.

---

## 5. Implementation Sequence

1. Write `docs/phase-16/01_SEO_MANAGEMENT_PLAN.md` (this file).
2. Create `backend/src/Services/SeoService.php`.
3. Create `backend/src/Modules/Admin/AdminSeoController.php`.
4. Register SEO routes, controller, permissions, and redirect handling in `backend/src/Core/App.php` and `RbacManager.php`.
5. Create unit and integration test suites:
   - `backend/tests/Unit/SeoServiceTest.php`
   - `backend/tests/Integration/SeoManagementApiTest.php`
   - `tests/test_phase16_seo_management.php`
6. Extend `backend/tests/mysql_security_concurrency_test.php` with Test 16 for `url_redirects`.
7. Register Phase 16 test in `.github/workflows/backend-ci.yml`.
8. Execute local regression testing and static verification.
9. Commit, push, and monitor CI.
10. Generate `docs/phase-16/02_PHASE_16_VERIFICATION_REPORT.md`.
