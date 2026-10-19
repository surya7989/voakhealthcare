# Authentication, RBAC and Session Security Architecture

**Document Reference:** `docs/phase-02/06_AUTHENTICATION_RBAC_AND_SESSION_ARCHITECTURE.md`  
**Phase:** Phase 2 of 31 — Complete System Architecture  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Executing Role:** Application Security Architect & Staff PHP Security Engineer  
**Baseline Authority:** Accepted Phase 0 Security Baseline (`docs/phase-00/10_SECURITY_AND_PRIVACY_BASELINE.md`) and Accepted Phase 1 RBAC Matrix (`docs/phase-01/06_COMPLETE_RBAC_PERMISSION_MATRIX.md`)  
**Status:** **AUTHORITATIVE ARCHITECTURAL SPECIFICATION** (Security Design Only — Implementation Deferred to Phase 6)

---

## 1. Resolution of the Authentication Cookie Path Inconsistency

### 1.1 The Integration Inconsistency Explained
An independent audit identified a real integration bug in preliminary Phase 0 drafts:
- **Previous Cookie Path Specification:** `Set-Cookie: voak_admin_session=...; Path=/api/admin`
- **Actual Protected API Endpoints:** `/api/v1/admin/*`

Under **RFC 6265 Section 5.1.4 (Path-Match)**, a request path matches a cookie path if and only if:
1. The cookie-path and the request-path are identical, OR
2. The cookie-path is a prefix of the request-path, AND either:
   - The last character of the cookie-path is `%x2F` (`/`), OR
   - The first character of the request-path that is not included in the cookie-path is `%x2F` (`/`).

**Mathematical Proof of RFC 6265 Failure under the Old Specification:**
- Cookie Path: `/api/admin`
- Request Path: `/api/v1/admin/users`
- Prefix Comparison: `/api/admin` is **NOT** a prefix of `/api/v1/admin/users`.
- **Consequence:** The browser would **never send the session cookie** when the React Admin SPA called `/api/v1/admin/*`, causing every authenticated API request to fail with `HTTP 401 Unauthorized`.

---

### 1.2 The Definitive Architectural Resolution
The authentication session cookie path is formally codified as:

$$\mathbf{Path=/api/v1/admin}$$

#### Verification under RFC 6265:
- Cookie Path: `/api/v1/admin`
- Request Path: `/api/v1/admin/dashboard/metrics`
- Prefix Check: `/api/v1/admin` is an exact prefix of `/api/v1/admin/dashboard/metrics`.
- Boundary Character: The character immediately following `/api/v1/admin` in the request path is `/`.
- **Verdict: VALID RFC 6265 MATCH.** The browser sends the session cookie on all protected admin endpoints.

#### Path Scope Isolation Benefits:
1. **Public Website Isolation:** Browser requests to public pages (`/about`, `/services`) and public APIs (`/api/v1/public/*`) will **NEVER** transmit the administrative session cookie, completely eliminating unnecessary credential exposure.
2. **Admin React SPA Coexistence:** The Admin React application is hosted under `/admin/` (compiled as a static SPA). When the React SPA makes API calls via `fetch()` or `axios` with `credentials: 'include'`, the browser includes the session cookie exclusively for requests directed to `/api/v1/admin/*`.

---

### 1.3 Full Administrative Cookie Header Specification

```http
Set-Cookie: voak_admin_session=tok_7f9c2d1e8a4b3f605c8d1a2b4e6f8a0c; 
            Path=/api/v1/admin; 
            Secure; 
            HttpOnly; 
            SameSite=Lax; 
            Max-Age=28800; 
            Priority=High
```

- **`Secure`:** Enforces transmission exclusively over TLS 1.3 encrypted HTTPS.
- **`HttpOnly`:** Prohibits access via JavaScript (`document.cookie`), completely mitigating session theft via Cross-Site Scripting (XSS).
- **`SameSite=Lax`:** Provides defense-in-depth against Cross-Site Request Forgery (CSRF). Cross-origin subrequests (e.g., `<script>`, `fetch`, `POST`) will omit the cookie, while top-level navigation retains session state.
- **`Max-Age=28800`:** Enforces an absolute maximum session lifetime of 8 hours (28,800 seconds).

---

## 2. Comprehensive Route, Auth & CSRF Scope Matrix

To eliminate any circular bootstrap requirements (where an unauthenticated user would need an authenticated session to get a CSRF token to log in), the platform provides an explicit unauthenticated CSRF bootstrap endpoint:

| Route Classification | URL Pattern | Transmits `voak_admin_session`? | Requires CSRF Token? | Primary Auth Mechanism |
| :--- | :--- | :---: | :---: | :--- |
| **Public Website Pages** | `/`, `/about`, `/services/*`, etc. | **NO** | No | None (Public Cacheable) |
| **Public API Endpoints** | `/api/v1/public/*` | **NO** | No (Turnstile on forms)| Cloudflare Turnstile + Honeypot |
| **Admin React SPA Shell** | `/admin/`, `/admin/login`, etc. | **NO** (Static HTML/JS)| No | Client Router |
| **CSRF Bootstrap** | `GET /api/v1/admin/auth/csrf` | Conditional | No | Generates unauthenticated CSRF token |
| **Auth Bootstrap (Login)** | `POST /api/v1/admin/auth/login` | **NO** | Yes (`X-CSRF-Token`)| Bcrypt Password Check |
| **MFA Verification** | `POST /api/v1/admin/auth/mfa/verify`| **NO** (Uses Bearer pre-auth)| Yes (`X-CSRF-Token`)| RFC 6238 TOTP (30s step) |
| **MFA Emergency Recovery**| `POST /api/v1/admin/auth/mfa/recovery`| **NO** (Uses Bearer pre-auth)| Yes (`X-CSRF-Token`)| Single-use backup code check |
| **Password Reset Request**| `POST /api/v1/admin/auth/password/forgot`| **NO** | Yes (`X-CSRF-Token`)| Dispatches reset email |
| **Password Reset Confirm**| `POST /api/v1/admin/auth/password/reset`| **NO** | Yes (`X-CSRF-Token`)| Hashed token verification |
| **Protected Admin APIs** | `/api/v1/admin/*` | **YES** (`Path=/api/v1/admin`)| Yes for mutations | Session Cookie + RBAC Gate |
| **Logout Endpoint** | `POST /api/v1/admin/auth/logout` | **YES** | Yes (`X-CSRF-Token`)| Session Revocation in MySQL |

---

## 3. Session Lifecycle & Defense-in-Depth Architecture

```mermaid
stateDiagram-v2
    [*] --> Unauthenticated: User visits /admin/login
    
    Unauthenticated --> CSRFBootstrapped: GET /api/v1/admin/auth/csrf
    note right of CSRFBootstrapped: Receives X-CSRF-Token header\nand voak_csrf cookie (Path=/api/v1/admin)
    
    CSRFBootstrapped --> PreAuthMFA: POST /login (Valid Credentials + CSRF)
    note right of PreAuthMFA: Issues temporary pre-auth token (TTL: 5m)\nZero access to admin API modules
    
    PreAuthMFA --> AuthenticatedActive: POST /mfa/verify (Valid 6-digit TOTP)
    PreAuthMFA --> AuthenticatedActive: POST /mfa/recovery (Valid Backup Code)
    note right of AuthenticatedActive: Session ID regenerated!\nCookie emitted: Path=/api/v1/admin\nIdle Timeout: 15m | Absolute: 8h
    
    AuthenticatedActive --> AuthenticatedActive: Active API Request (Resets 15m idle timer)
    
    AuthenticatedActive --> IdleExpired: 15 Minutes Inactivity
    AuthenticatedActive --> AbsoluteExpired: 8 Hours Total Elapsed
    AuthenticatedActive --> Terminated: POST /logout
    
    IdleExpired --> Unauthenticated: Cookie cleared; DB session deleted
    AbsoluteExpired --> Unauthenticated: Cookie cleared; DB session deleted
    Terminated --> Unauthenticated: Cookie cleared; DB session deleted
```

### 3.1 Authoritative Session Security Policies
1. **Session ID Regeneration:** Immediately upon successful TOTP MFA elevation, `session_regenerate_id(true)` is executed. The pre-auth token is destroyed, completely preventing Session Fixation attacks.
2. **Authoritative Idle Timeout (15 Minutes):** In strict accordance with the approved Phase 0 and Phase 1 security baseline, if `NOW() - last_activity_at > 900` (15 minutes), the session is expired. Any subsequent request receives `HTTP 401 Unauthorized` with `{error: {code: "SESSION_IDLE_TIMEOUT", message: "Session expired due to 15 minutes of inactivity"}}`.
3. **Absolute Expiry (8 Hours):** Regardless of continuous activity, all sessions terminate after 8 hours (`created_at + 28800`), forcing fresh credential and MFA re-authentication.
4. **Brute-Force Lockout Defense:** 5 consecutive failed login attempts lock the targeted email and originating IP address for 15 minutes (`FR-ADM-002`).

---

## 4. Role-Based Access Control (RBAC) Architecture

### 4.1 The 5 Administrative Roles & Operational Scopes

| Role Identifier | Role Name | High-Level Operational Scope | Typical Persona |
| :---: | :--- | :--- | :--- |
| **ROLE_1** | **Super Admin** | Full root authority: user provisioning, RBAC roles, security settings, SMTP socket testing, audit ledger access. | Managing Director, Head of IT |
| **ROLE_2** | **Website Administrator** | Technical CMS management: page publishing, slug renaming & 301 redirects, dynamic sitemap rebuilding, SEO meta tags. | Digital Operations Lead, Webmaster |
| **ROLE_3** | **Content Editor** | Editorial authoring: services, articles, case studies (consent required), media library WebP uploads, FAQ accordions. | Marketing Specialist, Communications Officer |
| **ROLE_4** | **BD Manager** | Commercial pipeline: contact inquiries triage, partnership pipeline, enterprise RFP tender download & review. | Business Development Director, Commercial Lead |
| **ROLE_5** | **HR Manager** | Talent acquisition: careers board vacancies CRUD, candidate resume download & screening, recruitment state machine. | Talent Acquisition Lead, HR Recruiter |

---

### 4.2 Complete 45-Permission Action Key Catalog

Every administrative operation is guarded by a granular canonical permission key evaluated server-side. The catalog contains **exactly 45 discrete canonical permissions** organized across 5 administrative subsystems:

```
AUTHENTICATION & USERS (4):
- users.view                 : View staff accounts list and profiles
- users.manage               : Create, edit, invite, or suspend staff accounts
- roles.manage               : Assign or reassign roles to staff users
- mfa.manage                 : Reset or manage staff TOTP MFA secrets

CONTENT & CMS (15):
- pages.view                 : View static CMS pages
- pages.manage               : Edit and publish static CMS pages and revisions
- homepage.manage            : Update hero copy, bento layout, and proof toggles
- services.view              : View service catalog pillars and SLAs
- services.manage            : Create, update, archive service pillars
- industries.view            : View healthcare sector verticals
- industries.manage          : Create, update, archive sector verticals
- media.view                 : View media library assets
- media.manage               : Upload WebP media assets and delete unreferenced assets
- articles.view              : View insights articles and whitepapers
- articles.manage            : Create, edit, publish, and archive articles
- case_studies.view          : View case studies proof catalog
- case_studies.manage        : Create, update, archive case studies (consent verified)
- faqs.view                  : View FAQ categories and accordions
- faqs.manage                : Create, update, reorder, delete FAQ items

CRM, PARTNERSHIPS & COMMERCIAL (10):
- inquiries.view             : View inbound contact inquiries
- inquiries.manage           : Update contact inquiry status and internal notes
- inquiries.export           : Export contact inquiries to CSV
- partnerships.view          : View strategic alliance proposals
- partnerships.manage        : Update partnership stage, notes, and assignee
- partnerships.export        : Export partnerships register to CSV
- proposals.view             : View enterprise RFP submissions
- proposals.manage           : Update proposal status, scope, and estimated value
- proposals.download_tender  : Dedicated object-level permission to stream tender files
- proposals.export           : Export proposals register to CSV

CAREERS & RECRUITMENT (6):
- careers.view               : View job vacancies and postings
- careers.manage             : Create, update, close, and archive job postings
- applications.view          : View candidate applications list
- applications.manage        : Advance candidate recruitment stage and notes
- applications.download_cv   : Dedicated object-level permission to stream candidate resumes
- applications.export        : Export candidate applications register to CSV

SYSTEM, GOVERNANCE & TELEMETRY (10):
- seo.view                   : View SEO route metadata and canonical mappings
- seo.manage                 : Override route meta tags and rebuild sitemap.xml
- settings.view              : View global system settings and entity variables
- settings.manage            : Update system configuration variables
- settings.smtp_test         : Dedicated permission to execute live SMTP socket test
- email_templates.manage     : Update transactional email HTML templates
- email_queue.retry          : Dedicated permission to trigger manual outbox retry
- audit.view                 : Read-only access to cryptographic SHA-256 audit ledger
- audit.export               : Export verified audit ledger for compliance
- telemetry.view             : View dashboard operational metrics and queue counters
```

---

### 4.3 Server-Side Middleware Enforcement (`AuthorizePermission`)

PHP enforces role boundaries using router middleware. Hiding a React UI element is treated strictly as a visual convenience; the backend enforces mandatory fail-closed authorization:

```php
namespace Voak\Health\Middleware;

use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Core\Exceptions\ForbiddenException;
use Voak\Health\Core\Exceptions\UnauthorizedException;
use Voak\Health\Modules\RBAC\RbacService;

class AuthorizePermission
{
    public function __construct(
        private RbacService $rbacService
    ) {}

    public function handle(Request $request, string $requiredPermission, callable $next): Response
    {
        $user = $request->getAttribute('authenticated_user');
        
        if (!$user) {
            throw new UnauthorizedException('Authentication session required.');
        }

        if (!$this->rbacService->userHasPermission($user->id, $requiredPermission)) {
            // Fail-closed security logging
            $this->logSecurityViolation($user->id, $requiredPermission, $request->getUri());
            
            throw new ForbiddenException(
                "Access denied: Missing required permission [{$requiredPermission}]."
            );
        }

        return $next($request);
    }
}
```

---

## 5. Object-Level Authorization & Sensitive Document Streaming

Public marketing images are served directly by Apache. In contrast, sensitive candidate resumes (`storage/private/quarantine/resumes/`) and enterprise RFP tender attachments (`storage/private/quarantine/proposals/`) require **dedicated object-level authorization** (`proposals.download_tender` and `applications.download_cv`):

```mermaid
sequenceDiagram
    autonumber
    actor Recruiter as HR Manager (User ID: 12)
    participant APIGateway as /api/index.php
    participant RBAC as AuthorizePermission ('applications.download_cv')
    participant Controller as ApplicationStreamController
    participant DB as MySQL Database
    participant Storage as storage/private/quarantine/

    Recruiter->>APIGateway: GET /api/v1/admin/applications/45/download
    APIGateway->>RBAC: Validate permission 'applications.download_cv'
    RBAC-->>APIGateway: Authorized
    APIGateway->>Controller: streamResume(applicationId: 45)
    
    Controller->>DB: Query application record (ID: 45)
    DB-->>Controller: Return record {quarantined_resume_path, original_resume_name}
    
    Controller->>Controller: Verify file exists on disk outside web root
    Controller->>DB: INSERT INTO audit_logs (domain: 'CAREERS', action: 'RESUME_DOWNLOADED', user_id: 12, entity_id: 45)
    
    Controller-->>Recruiter: HTTP 200 OK<br/>Content-Type: application/pdf<br/>Content-Disposition: attachment; filename="Candidate_Resume.pdf"<br/>X-Content-Type-Options: nosniff
```

---

## 6. Super Admin Provisioning & Emergency Account Recovery

To guarantee that the platform can be initialized securely without hardcoding default credentials in migration files:
1. **Initial Provisioning:** Executed strictly via CLI on Hostinger SSH or local development:
   ```bash
   php cli/provision_super_admin.php --email="admin@voakhealthcare.com" --name="Executive Administrator"
   ```
   The script generates a cryptographically random 20-character temporary password, prints the TOTP provisioning QR code in ASCII/SVG format, and commits the initial Super Admin account into `users` and `user_roles`.
2. **Zero Default Passwords:** The system contains zero default or hardcoded credentials (`FR-SEC-005`, `FR-SEC-006`).
