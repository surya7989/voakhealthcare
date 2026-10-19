# Error States & Edge Cases Specification
## Exhaustive Failure Matrix, Server Behaviors, Asynchronous Client Error Handling, and Recovery Strategies

**Document Reference:** `docs/phase-01/13_ERROR_STATES_AND_EDGE_CASES.md`  
**Phase:** Phase 1 of 31 — Complete Functional Specification (Corrected Baseline)  
**Baseline Authority:** Master Functional Requirements (`01_MASTER_FUNCTIONAL_REQUIREMENTS.md`) and Technical Architecture (`07_TECHNICAL_ARCHITECTURE.md`).

---

### Global Error Handling Directives & Asynchronous Architecture

1. **Zero Information Leakage:**  
   Under no circumstances shall public or administrative error responses emit raw PHP exceptions, stack traces, database schema names, SQL queries, or filesystem paths.
2. **Deterministic Diagnostic Codes:**  
   Every server error logs an encrypted diagnostic incident ID (e.g., `INC-500-A9F82B`) and presents this ID to the user to enable rapid support triangulation.
3. **Accurate Asynchronous React Error Handling Architecture:**  
   - **React Error Boundary Scope:** React Error Boundaries (`ErrorBoundary.tsx`) catch JavaScript errors **strictly during component rendering, in lifecycle methods, and in constructors of class components below them.** They do **NOT** catch asynchronous fetch errors, promise rejections, or event-handler exceptions.
   - **API Client Error Normalization (`src/api/client.ts`):** All HTTP calls are wrapped in an asynchronous fetch client that catches network exceptions and HTTP error status codes, normalizing them into structured `ApiError` instances (`{ status, code, message, fieldErrors, incidentId }`).
   - **Async State Management (TanStack Query / Custom Async Hooks):** Components managing remote data subscribe to reactive state: `isLoading`, `isError`, `error`, and `refetch()`. When an API fails, the component remains mounted and renders a contextual alert banner or inline field errors rather than crashing.
   - **Global Unhandled Promise Rejection Handler:** Uncaught asynchronous promises are captured by `window.addEventListener('unhandledrejection', ...)` which logs client telemetry and triggers a friendly user-facing toast notification.
   - **User Input Preservation:** When form submission fails due to network dropouts or validation errors, form state is preserved in memory; input values are never wiped.

---

### The 23 Edge Case & Failure Specifications

---

#### 01. API Unavailable / Server Offline
- **Trigger:** Web server is offline, PHP gateway timeout, or network disconnect between browser and Hostinger backend.
- **Expected Server Behavior:** Apache returns 502 Bad Gateway or 504 Gateway Timeout, or browser detects `ERR_CONNECTION_REFUSED`.
- **Expected Frontend State:** Asynchronous API client catches fetch rejection; TanStack Query / async hook sets `isError = true`. UI renders a contextual offline recovery banner with "Retry Connection" action. (React Error Boundary remains passive as rendering did not throw).
- **User-Facing Message:** "Unable to connect to Voak Health Care services. Please check your internet connection or try again in a few moments."
- **Logging:** Client-side telemetry logs `FETCH_NETWORK_ERROR`; server-side Apache error log records request failure.
- **Recovery Path:** "Retry Connection" button re-triggers the query using an exponential backoff retry hook.
- **Acceptance Test:** `TEST-PUB-023` / `TEST-ERR-001`: Simulate backend offline via DevTools network block; assert async recovery banner renders with retry action.

---

#### 02. Database Connection Failure
- **Trigger:** MySQL server down, max user connections exceeded on Hostinger shared database, or invalid credentials.
- **Expected Server Behavior:** PHP PDO constructor throws `PDOException`. The central error handler catches the exception, logs full trace securely to `storage/logs/database.log`, and immediately emits `HTTP/1.1 500 Internal Server Error` with JSON incident code.
- **Expected Frontend State:** Normalized API error displays branded 500 Error Modal or Alert with lavender backdrop.
- **User-Facing Message:** "Our systems are momentarily unavailable due to routine database maintenance. Please try again shortly. Reference: [INC-500-XXXXX]."
- **Logging:** `DATABASE_CONNECTION_FAILED: SQLSTATE[...]` logged with server timestamp and PID.
- **Recovery Path:** User advised to wait 60s; backend implements a fail-fast health check.
- **Acceptance Test:** `TEST-SEC-005`: Mock invalid DB password in test environment; assert API emits 500 with sanitized JSON payload.

---

#### 03. Transactional Email Delivery Failure
- **Trigger:** External SMTP server timeout, invalid SMTP authentication credentials, or socket port blocked.
- **Expected Server Behavior:** Bounded synchronous fast-path times out (after 4s) or asynchronous worker catches `SMTPException`; increments `attempt_count` in `email_queue`; logs socket handshake error to `storage/logs/email.log`.
- **Expected Frontend State:** **Public user sees success.** The user's form submission was successfully persisted in the database; email delivery is queued for background retry.
- **User-Facing Message:** "Your inquiry has been successfully submitted. Reference: [REF-CODE]. A confirmation email will arrive shortly."
- **Logging:** `SMTP_DELIVERY_FAILURE: Connection timed out to mail relay`.
- **Recovery Path:** Automated runner retries with exponential backoff. If max attempts (4) exceeded, marked `FAILED` for admin manual retry in Module 12.
- **Acceptance Test:** `TEST-ADM-013`: Shut down SMTP server; submit contact form; verify DB record created and email item marked `PENDING` with retry count.

---

#### 04. Invalid Form Input Payload
- **Trigger:** User submits malformed email, international phone with invalid characters, or skips mandatory required field.
- **Expected Server Behavior:** PHP Validator halts execution before database mutation; emits `HTTP/1.1 422 Unprocessable Entity` with RFC 7807 field error dictionary.
- **Expected Frontend State:** Form remains populated; inline error messages appear below each invalid field; focus moves to the first invalid field.
- **User-Facing Message:** "Please correct the highlighted fields before submitting."
- **Logging:** `VALIDATION_FAILED: [field_list]` (No sensitive inputs logged).
- **Recovery Path:** User edits invalid field; inline error disappears on blur/change.
- **Acceptance Test:** `TEST-PUB-019`: Submit empty contact form; assert 422 status and presence of `aria-invalid="true"` attributes.

---

#### 05. Public Form Rate Limiting Triggered
- **Trigger:** More than 5 contact forms or 3 RFPs submitted from the same IP address within a 60-minute window.
- **Expected Server Behavior:** PHP RateLimiter emits `HTTP/1.1 429 Too Many Requests` with `Retry-After: 3600` header.
- **Expected Frontend State:** Form displays warning alert banner; submit button disabled with countdown timer.
- **User-Facing Message:** "Submission limit exceeded for your network. To protect our systems from automated traffic, please wait [MM:SS] before submitting another inquiry."
- **Logging:** `RATE_LIMIT_EXCEEDED: IP [IP_ADDR] on endpoint [/api/v1/public/contact]`.
- **Recovery Path:** Timer expires and re-enables submit button; user can contact via direct phone number.
- **Acceptance Test:** `TEST-PUB-019`: Script 6 consecutive POST requests; assert 6th request receives 429 status.

---

#### 06. Duplicate Form Submission
- **Trigger:** User rapidly clicks submit button multiple times, or browser re-sends POST payload on refresh.
- **Expected Server Behavior:** Server evaluates deduplication hash `(ip + email + payload_hash)` in a 60-second window. If match exists, returns existing reference code or 200 OK without creating duplicate database row.
- **Expected Frontend State:** Displays original confirmation screen with existing reference code.
- **User-Facing Message:** "Your inquiry has already been received. Reference: [REF-CODE]."
- **Logging:** `DUPLICATE_SUBMISSION_IGNORED`.
- **Recovery Path:** Navigates user to homepage or services overview.
- **Acceptance Test:** `TEST-PUB-019`: Double-click submit in automated test; verify exactly 1 database row exists.

---

#### 07. Expired Administrative Session
- **Trigger:** Administrator inactive for > 15 minutes, or session cookie exceeds 8-hour absolute maximum lifetime.
- **Expected Server Behavior:** PHP Auth Middleware detects expired session timestamp; deletes session row; emits `HTTP/1.1 401 Unauthorized`.
- **Expected Frontend State:** API client interceptor catches 401; displays in-place re-authentication modal over current view without losing current draft inputs.
- **User-Facing Message:** "Your administrative session has expired due to 15 minutes of inactivity. Please re-enter your password to continue."
- **Logging:** `SESSION_EXPIRED: User ID [USER_ID]`.
- **Recovery Path:** In-place login modal restores session; user resumes current work.
- **Acceptance Test:** `TEST-ADM-002`: Fast-forward session timestamp by 16 minutes; assert subsequent API call returns 401.

---

#### 08. Multi-Factor Authentication (TOTP) Failure
- **Trigger:** Admin enters invalid 6-digit code or expired time step.
- **Expected Server Behavior:** PHP TotpValidator verifies RFC 6238 window (+- 1 step). On mismatch, increments failed MFA attempts; emits `HTTP/1.1 422 Unprocessable Entity`.
- **Expected Frontend State:** Code input clears and displays red error outline; focus returns to input.
- **User-Facing Message:** "Invalid verification code. Please check your authenticator application and try again."
- **Logging:** `MFA_VERIFICATION_FAILED: User ID [USER_ID]`.
- **Recovery Path:** Option to use 8-character recovery code; 5 failed MFA attempts locks account for 15 minutes.
- **Acceptance Test:** `TEST-ADM-003`: Submit `000000`; assert 422 error and error message.

---

#### 09. Lost Network Connection (Client Offline)
- **Trigger:** User's device disconnects from WiFi or cellular data while browsing or filling a form.
- **Expected Server Behavior:** N/A (Client-side event).
- **Expected Frontend State:** `window.addEventListener('offline')` detects disconnection; persistent toast notification appears: "Network connection lost. You are currently offline. Changes are saved locally."
- **User-Facing Message:** "You are currently offline. Please reconnect to submit your proposal."
- **Logging:** Client-side console warning.
- **Recovery Path:** `navigator.onLine` event listener detects reconnection; toast changes to green: "Connection restored."
- **Acceptance Test:** `TEST-PUB-023`: Emulate offline state in Playwright; assert offline banner appears.

---

#### 10. Unauthorized Access (Unauthenticated)
- **Trigger:** Visitor attempts to access protected admin endpoint without an active session cookie.
- **Expected Server Behavior:** PHP Auth Middleware returns `HTTP/1.1 401 Unauthorized` with `Location: /admin/login`.
- **Expected Frontend State:** React router guard redirects browser to `/admin/login?return_url=[ENCODED_URL]`.
- **User-Facing Message:** "Please sign in to access the administrative console."
- **Logging:** `UNAUTHENTICATED_ACCESS_ATTEMPT: Path [/api/v1/admin/...]`.
- **Recovery Path:** Successful login redirects back to requested route.
- **Acceptance Test:** `TEST-ADM-002`: Request `/api/v1/admin/users` without cookie; assert 401 status.

---

#### 11. Forbidden Administrative Action (RBAC Insufficient Privileges)
- **Trigger:** Authenticated user attempts action outside their assigned role (e.g., Content Editor calling `DELETE /api/v1/admin/services/1`).
- **Expected Server Behavior:** PHP AuthorizeRole middleware emits `HTTP/1.1 403 Forbidden` with sanitized incident code.
- **Expected Frontend State:** Action button disabled or modal alert: "Access Denied".
- **User-Facing Message:** "Access denied. Your administrative account role does not have permission to execute this operation."
- **Logging:** `UNAUTHORIZED_ACTION_ATTEMPT: User [ID], Role [ROLE], Permission [KEY]`.
- **Recovery Path:** User advised to contact Super Admin for elevated permissions.
- **Acceptance Test:** `TEST-SEC-001`: Call delete service API as Content Editor; assert 403 Forbidden.

---

#### 12. Upload File Size Exceeded
- **Trigger:** User attempts to upload tender document > 15MB or candidate resume > 10MB.
- **Expected Server Behavior:** PHP checks `$_FILES['size']` and server `upload_max_filesize`. Emits `HTTP/1.1 422 Unprocessable Entity`.
- **Expected Frontend State:** File dropzone highlights red; error text appears below file picker.
- **User-Facing Message:** "The selected file exceeds the maximum allowed size of 15MB. Please compress your document or attach an alternative file."
- **Logging:** `UPLOAD_SIZE_EXCEEDED: Size [BYTES]`.
- **Recovery Path:** User removes file and selects a smaller file.
- **Acceptance Test:** `TEST-PUB-021`: Upload a dummy 16MB file; assert client-side validation blocks upload, or server returns 422.

---

#### 13. Invalid File Attachment Content / MIME Type Mismatch
- **Trigger:** User renames an executable `.exe` or `.sh` script to `.pdf` and attempts to upload.
- **Expected Server Behavior:** PHP verifies magic bytes using `finfo_file()`. On signature mismatch, immediately unlinks temporary upload; emits `HTTP/1.1 422 Unprocessable Entity`.
- **Expected Frontend State:** File rejected with security warning.
- **User-Facing Message:** "The uploaded file format is not supported or appears corrupted. Only authentic PDF, Word, and Excel documents are permitted."
- **Logging:** `MALICIOUS_MIME_MISMATCH: Detected [REAL_MIME] disguised as [EXT]`.
- **Recovery Path:** User selects authentic document.
- **Acceptance Test:** `TEST-SEC-003`: Upload `.txt` file renamed to `.pdf`; assert server rejects with 422.

---

#### 14. Malicious Upload Attempt (Executable Payloads)
- **Trigger:** Upload contains PHP script tags (`<?php`), double extensions (`doc.php.pdf`), or shell commands.
- **Expected Server Behavior:** File quarantined in directory outside web root with randomized UUID filename and `0600` permissions (execution disabled via `.htaccess` `php_flag engine off`). Security alert dispatched if active payload detected.
- **Expected Frontend State:** Upload rejected.
- **User-Facing Message:** "File upload failed security verification."
- **Logging:** `SECURITY_ALERT: Malicious upload signature detected from IP [IP]`.
- **Recovery Path:** File deleted immediately.
- **Acceptance Test:** `TEST-SEC-003`: Attempt upload containing PHP tags; assert file is rejected and unlinked.

---

#### 15. Non-Existent Service or Dynamic Slug
- **Trigger:** User or crawler requests `/services/non-existent-service-slug`.
- **Expected Server Behavior:** PHP front controller queries DB; record not found; emits authentic `HTTP/1.1 404 Not Found`.
- **Expected Frontend State:** Renders branded `PAGE-PUB-22` 404 Error View with search bar and recovery links.
- **User-Facing Message:** "The healthcare service you are looking for may have been relocated, unpublished, or does not exist."
- **Logging:** `SLUG_NOT_FOUND: [/services/...]`.
- **Recovery Path:** Links to `/services` overview, `/industries`, and `/contact`.
- **Acceptance Test:** `TEST-PUB-006` / `TEST-SEO-002`: Request `/services/fake-slug`; verify response header is exactly 404.

---

#### 16. Unpublished Content Direct Request
- **Trigger:** User requests `/services/:slug` or `/insights/:slug` where `is_published = 0`.
- **Expected Server Behavior:** Query filters `WHERE is_published = 1`; treated identically to non-existent slug; emits authentic `HTTP/1.1 404 Not Found`.
- **Expected Frontend State:** Branded 404 Error View.
- **User-Facing Message:** "This resource is currently unavailable. View our active services or contact our team."
- **Logging:** `UNPUBLISHED_SLUG_ACCESSED: [SLUG]`.
- **Recovery Path:** Navigation to public catalog.
- **Acceptance Test:** `TEST-PUB-006`: Set service `is_published = 0`; assert public request returns 404.

---

#### 17. Concurrent Editing Conflict
- **Trigger:** Two administrators open the same page editor simultaneously; Admin A saves at 10:00:00; Admin B attempts to save at 10:00:05 with stale data.
- **Expected Server Behavior:** PHP checks `last_updated_at` timestamp. Because Admin B's timestamp is older than current DB `updated_at`, server rejects with `HTTP/1.1 409 Conflict`.
- **Expected Frontend State:** Conflict resolution modal appears with side-by-side diff.
- **User-Facing Message:** "Another administrator has modified this page while you were editing. To prevent overwriting their changes, please review the differences below before saving."
- **Logging:** `CONCURRENT_EDIT_CONFLICT: Entity [TYPE], ID [ID]`.
- **Recovery Path:** Admin B can choose to "Overwrite", "Merge", or "Discard My Edits".
- **Acceptance Test:** `TEST-ADM-005`: Simulate simultaneous PUT requests with mismatched timestamps; assert 409 Conflict.

---

#### 18. Stale Form Data on Re-Hydration
- **Trigger:** User leaves RFP form open for 2 days; returns and clicks submit.
- **Expected Server Behavior:** Turnstile token expires (lifetime ~2 hours) or CSRF token expires. Server returns `422 Unprocessable Entity` or `403 Forbidden` with code `TOKEN_EXPIRED`.
- **Expected Frontend State:** Silently refreshes Turnstile widget and prompts user to click submit once more; preserves all form inputs.
- **User-Facing Message:** "Your security session has refreshed. Please click submit once more to complete your request."
- **Logging:** `TOKEN_EXPIRED_REHYDRATED`.
- **Recovery Path:** Re-submission succeeds with fresh token.
- **Acceptance Test:** `TEST-PUB-021`: Submit form with expired Turnstile token; verify user inputs are retained and re-submission allowed.

---

#### 19. Missing Media Assets (Broken Images)
- **Trigger:** Image referenced in CMS was deleted or filename altered.
- **Expected Server Behavior:** Static asset returns 404.
- **Expected Frontend State:** React `<img>` component uses `onError` handler; replaces broken image with branded SVG placeholder tile in soft lavender.
- **User-Facing Message:** Clean visual placeholder; zero broken image icons or browser error squares.
- **Logging:** Client-side telemetry logs `IMAGE_404: [URL]`.
- **Recovery Path:** Admin can re-link image in CMS.
- **Acceptance Test:** `TEST-PUB-002`: Point image URL to invalid path; assert placeholder SVG renders.

---

#### 20. Missing Corporate Business Configuration
- **Trigger:** Platform booted without required corporate phone or registration number configured in `system_settings`.
- **Expected Server Behavior:** Configuration service falls back to safe system defaults.
- **Expected Frontend State:** Displays general contact form link rather than blank text or broken template variables.
- **User-Facing Message:** Displays "Inquire Online" rather than empty phone string.
- **Logging:** `CONFIG_MISSING_WARNING: Key [KEY]`.
- **Recovery Path:** Super Admin configures setting in Module 18.
- **Acceptance Test:** `TEST-ADM-018`: Delete corporate phone setting; assert UI displays contact link fallback.

---

#### 21. Database Write Failure (Disk Full or Deadlock)
- **Trigger:** Hostinger shared disk quota reached, or transaction deadlock occurs.
- **Expected Server Behavior:** Transaction rolls back (`PDO::rollBack()`); emits `HTTP/1.1 500 Internal Server Error`.
- **Expected Frontend State:** Error notification banner with reference code.
- **User-Facing Message:** "We were unable to save your information due to a temporary storage issue. Please try again shortly. Reference: [INC-500-XXXXX]."
- **Logging:** `DATABASE_WRITE_FAILURE: Disk quota or deadlock`.
- **Recovery Path:** Admin alerted via critical telemetry.
- **Acceptance Test:** `TEST-SEC-005`: Simulate PDO transaction rollback; assert 500 status and zero orphan database rows.

---

#### 22. Scheduled System Maintenance Mode
- **Trigger:** Super Admin toggles maintenance mode in Module 18 or `.maintenance` lockfile created.
- **Expected Server Behavior:** PHP front controller intercepts all non-admin requests; emits `HTTP/1.1 503 Service Unavailable` with `Retry-After: 1800`.
- **Expected Frontend State:** Branded Maintenance Screen explaining scheduled operational upgrades. Admin users with active session bypass maintenance mode.
- **User-Facing Message:** "Voak Health Care is currently undergoing scheduled platform maintenance to enhance our operational systems. We will be back online shortly."
- **Logging:** `MAINTENANCE_MODE_ACTIVE`.
- **Recovery Path:** Deletion of maintenance flag restores public traffic.
- **Acceptance Test:** `TEST-ADM-018`: Create `.maintenance` file; assert public requests receive 503 while `/admin` remains accessible.

---

#### 23. Turnstile CAPTCHA Verification Failure
- **Trigger:** Bot submits form without token, or Cloudflare API rejects token as invalid.
- **Expected Server Behavior:** PHP Validator rejects request with `HTTP/1.1 422 Unprocessable Entity`.
- **Expected Frontend State:** Turnstile widget resets with reload prompt.
- **User-Facing Message:** "Spam verification failed. Please verify the security check and submit again."
- **Logging:** `TURNSTILE_VERIFY_FAILED: Error codes [CODES]`.
- **Recovery Path:** User completes security check and re-submits.
- **Acceptance Test:** `TEST-PUB-019`: Submit form with dummy Turnstile token; assert 422 rejection.

---

### Failure Matrix Summary & Response Taxonomy

| Code | Failure Scenario | HTTP Status | Frontend Experience | User Privacy Preserved? | Recovery Mechanism | Canonical Test ID |
| :--- | :--- | :---: | :--- | :---: | :--- | :--- |
| **ERR-01** | API Unavailable | 502 / 504 | Offline Recovery Banner | Yes | Auto Exponential Backoff | `TEST-PUB-023` |
| **ERR-02** | DB Connection Failure | 500 | Branded 500 Error Modal | Yes | Fail-Fast Health Check | `TEST-SEC-005` |
| **ERR-03** | Email Delivery Failure | 201 (Async) | Public Success / Outbox Queue | Yes | Bounded Fast-Path + Retry | `TEST-ADM-013` |
| **ERR-04** | Invalid Form Values | 422 | Inline Field Error Messages | Yes | In-Place Correction | `TEST-PUB-019` |
| **ERR-05** | Form Rate Limit | 429 | Countdown Lockout Banner | Yes | Wait for Timer Expiration | `TEST-PUB-019` |
| **ERR-06** | Duplicate Submission | 200 / 409 | Existing Reference Code Display | Yes | Re-Use Existing Record | `TEST-PUB-019` |
| **ERR-07** | Session Expired | 401 | In-Place Re-Auth Modal | Yes | Preserve Draft on Re-Login | `TEST-ADM-002` |
| **ERR-08** | MFA Challenge Failed | 422 | Red Outline / Recovery Code Link | Yes | 5 Attempts Before Lockout | `TEST-ADM-003` |
| **ERR-09** | Client Offline | N/A | Persistent "Offline" Toast | Yes | Local Draft Preservation | `TEST-PUB-023` |
| **ERR-10** | Unauthenticated Admin | 401 | Redirect to `/admin/login` | Yes | Authenticate via Credentials | `TEST-ADM-002` |
| **ERR-11** | Forbidden Action | 403 | Padlock Modal / Disabled Buttons | Yes | Super Admin Permission Request | `TEST-SEC-001` |
| **ERR-12** | Upload Exceeds Size | 422 | Dropzone Red Highlight | Yes | Client-Side File Picker Reset | `TEST-PUB-021` |
| **ERR-13** | MIME Type Mismatch | 422 | Format Support Warning | Yes | Immediate Temp File Unlink | `TEST-SEC-003` |
| **ERR-14** | Malicious Payload | 422 | Security Verification Rejection | Yes | Immediate Quarantine & Purge | `TEST-SEC-003` |
| **ERR-15** | Slug Not Found | 404 | Branded 404 Screen & Search | Yes | True 404 Status Code Emitted | `TEST-PUB-006` |
| **ERR-16** | Unpublished Content | 404 | Branded 404 Screen & Search | Yes | Excluded from Sitemap & Search | `TEST-PUB-006` |
| **ERR-17** | Concurrent Editing | 409 | Visual Diff Conflict Modal | Yes | Compare & Merge Options | `TEST-ADM-005` |
| **ERR-18** | Stale Token | 422 | Silent Token Refresh | Yes | Retain All Form Inputs | `TEST-PUB-021` |
| **ERR-19** | Missing Image | 404 | Soft Lavender SVG Placeholder | Yes | Graceful Visual Fallback | `TEST-PUB-002` |
| **ERR-20** | Missing Config | 200 | Safe Fallback Labels | Yes | Display "Inquire Online" | `TEST-ADM-018` |
| **ERR-21** | DB Write Failure | 500 | Reference Code Notification | Yes | Rollback PDO Transaction | `TEST-SEC-005` |
| **ERR-22** | Maintenance Mode | 503 | Branded Maintenance Screen | Yes | Authenticated Admin Bypass | `TEST-ADM-018` |
| **ERR-23** | Turnstile Failure | 422 | Widget Reset Alert | Yes | Re-Verify Security Challenge | `TEST-PUB-019` |
