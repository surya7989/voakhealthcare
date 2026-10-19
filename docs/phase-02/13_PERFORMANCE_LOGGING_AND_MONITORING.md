# Performance Architecture, Structured Logging, and Telemetry

**Document Reference:** `docs/phase-02/13_PERFORMANCE_LOGGING_AND_MONITORING.md`  
**Phase:** Phase 2 of 31 — Complete System Architecture  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Executing Role:** Site Reliability Engineer & Web Performance Architect  
**Baseline Authority:** Accepted Phase 0 Audit (`docs/phase-00/06_HOSTINGER_COMPATIBILITY_AUDIT.md`) and Accepted Phase 1 Requirements (`docs/phase-01/12_SEO_ACCESSIBILITY_AND_RESPONSIVENESS.md`)  
**Status:** **AUTHORITATIVE ARCHITECTURAL SPECIFICATION** (Design Targets Only — Benchmarking Deferred to Phase 29)

---

## 1. Google Core Web Vitals Engineering Targets

The frontend and backend architectures are designed to satisfy Google Core Web Vitals thresholds on standard mobile (4G) and desktop connections:

| Metric | Full Metric Name | Architectural Target | Primary Engineering Mechanism |
| :---: | :--- | :---: | :--- |
| **LCP** | **Largest Contentful Paint** | **$\le$ 2.5 seconds** | PHP semantic pre-rendered `#ssr-fallback` markup; WebP hero image compression; preloaded Google fonts. |
| **INP** | **Interaction to Next Paint** | **$\le$ 200 milliseconds** | Lightweight React bundles (< 150KB initial JS); non-blocking micro-tasks; debounced search inputs (300ms). |
| **CLS** | **Cumulative Layout Shift** | **$\le$ 0.1** | Identical container geometry between `#ssr-fallback` and React root; explicit image dimensions; font-display: swap. |

> [!NOTE]
> **Forward-Looking Target Declaration:**  
> These metrics represent future measured architectural targets, not current production benchmarks. Empirical Lighthouse and Web Vitals audits will be conducted during Phase 29 (Performance & Core Web Vitals).

---

## 2. Frontend Performance Engineering Architecture

### 2.1 Route-Level Code Splitting & Chunk Partitioning
The public and admin React applications configure Rollup chunk splitting in `vite.config.ts`:
- **Vendor Chunk:** Shared runtime dependencies (React, React-DOM) partitioned into `vendor-[hash].js` (~45KB gzip).
- **Core Shell Chunk:** Header, Footer, Navigation, Modal primitives in `shell-[hash].js` (~35KB gzip).
- **Route-Level Dynamic Imports:** All 22 public pages and 19 admin modules are loaded dynamically via `React.lazy()`:
  ```tsx
  const ServiceDetailPage = React.lazy(() => import('./pages/ServiceDetailPage'));
  const CareersPage = React.lazy(() => import('./pages/CareersPage'));
  const RfpWizardPage = React.lazy(() => import('./pages/RfpWizardPage'));
  ```
- **Initial Public Bundle Size Ceiling:** The initial JavaScript footprint required for first paint is strictly capped at **< 150 KB (gzip)**.

### 2.2 Asset Optimization & Font Loading Strategy
1. **Typography Optimization:**
   - Fonts (`Fraunces` serif and `Plus Jakarta Sans`) load via Google Fonts with `&display=swap` and `<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>`.
   - CSS defines local fallback system fonts (`font-family: 'Fraunces', Georgia, serif;`) with matched x-heights to eliminate font layout shifts.
2. **Responsive Image Delivery:**
   - Public images use HTML5 `<picture>` tags with responsive `srcset` delivering WebP assets scaled to viewport width (400w, 800w, 1200w, 1920w).
   - Images specify explicit `width` and `height` attributes to preserve aspect ratios before image download completes.

---

## 3. Backend & Database Performance Optimization

### 3.1 Hostinger PHP OPcache Tuning
On Hostinger shared hosting, PHP OPcache compiles PHP bytecode into shared memory, eliminating disk I/O and compilation overhead on every request:
- **Recommended Configuration (`.user.ini`):**
  ```ini
  opcache.enable=1
  opcache.memory_consumption=128
  opcache.interned_strings_buffer=8
  opcache.max_accelerated_files=10000
  opcache.revalidate_freq=60
  opcache.fast_shutdown=1
  ```

### 3.2 Database Indexing & Query Optimization Standards
- **Compound Indexes:** High-frequency filtered queries leverage covering composite indexes:
  - `contact_inquiries (status, created_at)`
  - `services (category, is_published, display_order)`
  - `career_applications (job_id, status)`
  - `email_queue (status, next_retry_at, attempts)`
- **Zero `SELECT *` in Production:** Repositories explicitly project only the required columns.
- **Strict Query Pagination:** All CRM and content list endpoints enforce mandatory pagination (`LIMIT :offset, :per_page`) with a maximum page size ceiling of 50 records.

---

## 4. Structured JSON Logging & Diagnostics

### 4.1 Structured Daily JSON Log Schema
The backend logs all security events, warnings, and unhandled exceptions to daily rotated files (`storage/logs/voak-YYYY-MM-DD.log`) using structured JSON:

```json
{
  "timestamp": "2026-10-16T09:15:30.124Z",
  "level": "ERROR",
  "correlation_id": "REF-ERR-653A8B12",
  "domain": "AUTH",
  "event": "FAILED_LOGIN_LOCKOUT_TRIGGERED",
  "user_id": null,
  "client_ip": "203.0.113.42",
  "request_uri": "/api/v1/admin/auth/login",
  "http_method": "POST",
  "exception": {
    "class": "Voak\\Health\\Core\\Exceptions\\TooManyRequestsException",
    "message": "Account temporarily locked for 15 minutes due to consecutive failed attempts.",
    "file": "src/Modules/Auth/AuthService.php",
    "line": 84
  }
}
```

### 4.2 Diagnostic Correlation Reference Codes
When an error occurs, the global exception handler generates an 8-character hex correlation code:
$$\text{correlation\_id} = \text{'REF-ERR-'} + \text{strtoupper}(\text{substr}(\text{md5}(\text{uniqid}()), 0, 8))$$
- Logged in the structured server log alongside the internal stack trace.
- Returned in the public JSON error response (`reference_id`).
- Allows administrators to locate exact stack traces instantly without exposing sensitive internal details to the client.

---

## 5. Rate Limiting Architecture

To protect Hostinger shared hosting resources from brute-force attacks and denial-of-service abuse, rate limiting is enforced by `src/Middleware/RateLimiterMiddleware.php`:

| Endpoint Domain | Rate Limit Threshold | Window | Action on Violation |
| :--- | :---: | :---: | :--- |
| **Admin Login (`/admin/auth/login`)** | **5 requests** | 15 Minutes | HTTP 429 + 15-Minute Account/IP Lockout |
| **Inbound Forms (`/public/contact`, `/public/proposals/submit`)** | **5 submissions** | 1 Hour | HTTP 429 + "Submission threshold exceeded" |
| **Public Catalog APIs (`/public/services`, `/public/industries`)** | **60 requests** | 1 Minute | HTTP 429 Too Many Requests |
| **General Admin APIs (`/admin/*`)** | **120 requests** | 1 Minute | HTTP 429 Too Many Requests |
