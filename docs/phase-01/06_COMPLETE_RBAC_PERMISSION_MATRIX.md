# Complete Role-Based Access Control (RBAC) Permission Matrix
## Granular Roles, Action Taxonomy, PHP Backend Enforcement, and Security Boundaries

**Document Reference:** `docs/phase-01/06_COMPLETE_RBAC_PERMISSION_MATRIX.md`  
**Phase:** Phase 1 of 31 — Complete Functional Specification  
**Baseline Authority:** Approved Phase 0 RBAC Specification (`docs/phase-00/04_ADMIN_MODULES_AND_RBAC.md`) and Technical Architecture (`07_TECHNICAL_ARCHITECTURE.md`).

---

### 1. Fundamental Security Mandates

1. **Backend Authoritative Enforcement:**  
   UI element toggling or route hiding in React is strictly a user-experience enhancement. **Every HTTP mutation and sensitive read request must be verified by the PHP backend authorization middleware** (`src/Middleware/AuthorizeRole.php`).
2. **Fail-Closed Architecture:**  
   Any route, controller action, or endpoint lacking an explicit permission grant defaults immediately to `403 Forbidden`.
3. **Information Leakage Prevention:**  
   Unauthorized requests return a standardized JSON error payload without leaking internal path traces, entity existence flags, or database schemas:
   ```json
   {
     "status": "error",
     "code": 403,
     "message": "Access denied. Your account lacks sufficient privileges for this operational action.",
     "incident_id": "SEC-403-9A8F2E"
   }
   ```
4. **Separation of Duties:**  
   Recruitment data (candidate resumes, personal emails, phone numbers) is accessible solely to HR and Super Admin. Business proposals and commercial tenders are accessible solely to BD and Super Admin. Editorial staff have zero visibility into inbound pipelines.

---

### 2. The 5 Administrative Role Definitions

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        ROLE HIERARCHY & SCOPE                               │
├─────────────────────────────────────────────────────────────────────────────┤
│ 1. SUPER ADMIN        : Unrestricted operational authority, audit, security. │
│ 2. WEBSITE ADMIN      : CMS management, general inquiries, site config.     │
│ 3. CONTENT EDITOR     : Editorial copy, articles, media, SEO copy (no CRM). │
│ 4. BD MANAGER         : Inbound proposals, partnerships, enterprise CRM.    │
│ 5. HR MANAGER         : Careers, job postings, candidate applications/CVs.  │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### Detailed Role Profiles:

##### Role 1: Super Admin (`ROLE_SUPER_ADMIN`)
- **Primary Persona:** Chief Technology Officer / Managing Director.
- **Accessible Screens:** All 19 Administrative Modules without restriction.
- **Allowed Actions:** Full CRUD across all entities; user account provisioning; role assignments; session revocation; viewing and exporting immutable audit logs; editing system settings and SMTP parameters; managing 301 redirects; downloading client tenders and candidate resumes.
- **Restricted Actions:** Cannot delete own Super Admin account if it is the sole active Super Admin; cannot edit or purge immutable audit log records.
- **Sensitive Data Visibility:** Full visibility into all commercial tenders, candidate PII, user session telemetry, and security events.
- **Download Permissions:** Unrestricted binary streaming for all uploaded tenders, candidate resumes, and CSV data exports.
- **Publishing Permissions:** Immediate live publishing authority across all content domains.
- **User-Management Permissions:** Sole role authorized to invite, edit, suspend, or change permissions of other admin users.
- **Audit-Log Visibility:** Full access to view, filter, and export `audit_logs`.

##### Role 2: Website Administrator (`ROLE_WEBSITE_ADMIN`)
- **Primary Persona:** Senior Operations Lead / Digital Marketing Director.
- **Accessible Screens:** Modules 01 (Dashboard), 02 (Auth), 04 (Pages), 05 (Hero), 06 (Services), 07 (Industries), 08 (Media), 09 (Contact CRM), 12 (Email Templates), 13 (Careers Jobs), 15 (Case Studies), 16 (Insights), 17 (SEO).
- **Allowed Actions:** Content CRUD, media optimization, triaging general contact inquiries, managing job openings, updating SEO meta tags, customizing email template copy.
- **Restricted Actions:** Cannot access User Management (Module 03), System Settings (Module 18), Audit Logs (Module 19), Partnership Pipeline (Module 10), Proposal Tender Desk (Module 11), or Candidate Resumes (Module 14).
- **Sensitive Data Visibility:** Public corporate content and general contact inquiries only. Zero access to commercial tenders or candidate resumes.
- **Download Permissions:** Media assets and general contact CSVs.
- **Publishing Permissions:** Full publishing authority for pages, services, industries, and articles.
- **User-Management Permissions:** None (Cannot view or edit user accounts).
- **Audit-Log Visibility:** None.

##### Role 3: Content Editor (`ROLE_CONTENT_EDITOR`)
- **Primary Persona:** Copywriter / Marketing Content Specialist.
- **Accessible Screens:** Modules 01 (Content Dashboard), 02 (Auth), 04 (Pages), 05 (Hero Layout), 06 (Services), 07 (Industries), 08 (Media), 15 (Case Studies), 16 (Insights & Articles).
- **Allowed Actions:** Drafting and editing markdown copy for pages, services, industries, and articles; uploading and tagging media assets; drafting case studies.
- **Restricted Actions:** Cannot access any CRM or pipeline module (09, 10, 11, 14); cannot access Settings (18), Users (03), Email Templates (12), or Audit Logs (19). Cannot publish client case studies without verified consent release.
- **Sensitive Data Visibility:** Strictly public marketing and editorial content.
- **Download Permissions:** Public media assets only.
- **Publishing Permissions:** Can publish articles and draft pages; cannot modify site-wide system settings or URL redirects.
- **User-Management Permissions:** None.
- **Audit-Log Visibility:** None.

##### Role 4: Business Development Manager (`ROLE_BD_MANAGER`)
- **Primary Persona:** Commercial Director / Enterprise Healthcare Partnership Lead.
- **Accessible Screens:** Modules 01 (Executive Dashboard), 02 (Auth), 09 (Contact Enquiries), 10 (Partnership Pipeline), 11 (Proposal RFP Review Desk).
- **Allowed Actions:** Triaging contact inquiries, managing the partnership sales pipeline, updating proposal review statuses, downloading client RFP specifications, adding internal commercial notes, exporting pipeline CSVs.
- **Restricted Actions:** Cannot edit public website content, pages, services, or blog articles; cannot access Careers/Applications (13, 14); cannot access Users (03), Settings (18), or Audit Logs (19).
- **Sensitive Data Visibility:** Inbound B2B inquiries, organizational facility profiles, commercial tender documents, and enterprise partnership communications. Zero access to candidate recruitment files.
- **Download Permissions:** Authorized to download client tender attachments and export commercial inquiry data.
- **Publishing Permissions:** None (No CMS publishing rights).
- **User-Management Permissions:** None.
- **Audit-Log Visibility:** None.

##### Role 5: HR Manager (`ROLE_HR_MANAGER`)
- **Primary Persona:** Healthcare Recruitment Director / Talent Acquisition Lead.
- **Accessible Screens:** Modules 01 (HR Dashboard), 02 (Auth), 13 (Careers Job Openings), 14 (Candidate Applications & Recruitment Triage).
- **Allowed Actions:** Creating and managing job openings, reviewing candidate applications, downloading candidate resumes, updating applicant hiring stages, adding candidate interview notes, exporting recruitment applicant reports.
- **Restricted Actions:** Cannot access Inbound CRM modules (09, 10, 11); cannot edit public website content or CMS pages (04, 05, 06, 07, 15, 16); cannot access Users (03), Settings (18), or Audit Logs (19).
- **Sensitive Data Visibility:** Candidate resumes, personal contact details, qualifications, and interview assessments. Zero access to corporate commercial tenders.
- **Download Permissions:** Authorized to download candidate resumes and export applicant CSV rosters.
- **Publishing Permissions:** Can publish and close job vacancy postings.
- **User-Management Permissions:** None.
- **Audit-Log Visibility:** None.

---

### 3. Granular Role-by-Action Permission Matrix

The following matrix documents all granular system actions and their authorized roles:

| Subsystem Domain | Granular Permission Key | Super Admin | Website Admin | Content Editor | BD Manager | HR Manager |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| **Authentication** | `auth:login` | YES | YES | YES | YES | YES |
| | `auth:mfa_verify` | YES | YES | YES | YES | YES |
| | `auth:password_reset` | YES | YES | YES | YES | YES |
| **User Management** | `user:list` | **YES** | NO | NO | NO | NO |
| | `user:invite` | **YES** | NO | NO | NO | NO |
| | `user:update_role` | **YES** | NO | NO | NO | NO |
| | `user:suspend` | **YES** | NO | NO | NO | NO |
| | `user:revoke_session`| **YES** | NO | NO | NO | NO |
| **Pages & Content** | `page:read` | YES | YES | YES | NO | NO |
| | `page:update` | YES | YES | YES | NO | NO |
| | `page:rollback` | YES | YES | NO | NO | NO |
| **Homepage Layout** | `hero:update` | YES | YES | YES | NO | NO |
| | `bento:reorder` | YES | YES | YES | NO | NO |
| **Services Catalog** | `service:create` | YES | YES | YES | NO | NO |
| | `service:update` | YES | YES | YES | NO | NO |
| | `service:publish` | YES | YES | NO | NO | NO |
| | `service:delete` | YES | YES | NO | NO | NO |
| **Industries** | `industry:create` | YES | YES | YES | NO | NO |
| | `industry:update` | YES | YES | YES | NO | NO |
| | `industry:delete` | YES | YES | NO | NO | NO |
| **Media Library** | `media:upload` | YES | YES | YES | NO | NO |
| | `media:update_alt` | YES | YES | YES | NO | NO |
| | `media:delete` | YES | YES | NO | NO | NO |
| **Contact Enquiries** | `contact:list` | YES | YES | NO | **YES** | NO |
| | `contact:update_status`| YES | YES | NO | **YES** | NO |
| | `contact:add_note` | YES | YES | NO | **YES** | NO |
| | `contact:export_csv` | YES | YES | NO | **YES** | NO |
| **Partnership CRM** | `partner:list` | **YES** | NO | NO | **YES** | NO |
| | `partner:update_stage`| **YES** | NO | NO | **YES** | NO |
| | `partner:add_note` | **YES** | NO | NO | **YES** | NO |
| | `partner:export_csv` | **YES** | NO | NO | **YES** | NO |
| **Proposal Desk** | `proposal:list` | **YES** | NO | NO | **YES** | NO |
| | `proposal:update_status`| **YES** | NO | NO | **YES** | NO |
| | `proposal:download_tender`| **YES** | NO | NO | **YES** | NO |
| | `proposal:export_csv` | **YES** | NO | NO | **YES** | NO |
| **Email Templates** | `email:read` | **YES** | YES | NO | NO | NO |
| | `email:update` | **YES** | YES | NO | NO | NO |
| | `email:test_send` | **YES** | YES | NO | NO | NO |
| **Careers & Jobs** | `job:create` | YES | YES | NO | NO | **YES** |
| | `job:update` | YES | YES | NO | NO | **YES** |
| | `job:close` | YES | YES | NO | NO | **YES** |
| **Recruitment Triage**| `candidate:list` | **YES** | NO | NO | NO | **YES** |
| | `candidate:update_stage`| **YES** | NO | NO | NO | **YES** |
| | `candidate:download_cv`| **YES** | NO | NO | NO | **YES** |
| | `candidate:export_csv`| **YES** | NO | NO | NO | **YES** |
| **Case Studies** | `casestudy:create` | YES | YES | YES | NO | NO |
| | `casestudy:publish` | YES | YES | NO | NO | NO |
| **Insights & Blog** | `article:create` | YES | YES | YES | NO | NO |
| | `article:update` | YES | YES | YES | NO | NO |
| | `article:publish` | YES | YES | YES | NO | NO |
| **SEO & Routing** | `seo:update_meta` | **YES** | YES | NO | NO | NO |
| | `seo:rebuild_sitemap`| **YES** | YES | NO | NO | NO |
| **System Settings** | `settings:read` | **YES** | NO | NO | NO | NO |
| | `settings:update` | **YES** | NO | NO | NO | NO |
| | `settings:smtp_test` | **YES** | NO | NO | NO | NO |
| **Audit Logs** | `audit:read` | **YES** | NO | NO | NO | NO |
| | `audit:export` | **YES** | NO | NO | NO | NO |

---

### 4. PHP Backend Enforcement Implementation Architecture

#### 4.1 Authentication & Session Middleware (`AuthenticateSession.php`):
1. Verifies presence of signed `voak_admin_session` cookie.
2. Checks session expiration (inactivity timeout > 15 mins triggers termination).
3. Resolves user record from `admin_users` table; checks `is_active = 1`.
4. Attaches authenticated `UserContext` object to request container.

#### 4.2 Role & Permission Middleware (`AuthorizePermission.php`):
Every API route definition binds required permission keys:
```php
// Example route definition in PHP Router
$router->post('/api/v1/admin/proposals/{id}/download-tender', [
    ProposalController::class, 
    'downloadTender'
])->middleware([
    AuthenticateSession::class,
    AuthorizePermission::class . ':proposal:download_tender'
]);
```

#### 4.3 Evaluation Logic:
```php
namespace Voak\Middleware;

class AuthorizePermission {
    public function handle($request, $next, string $permission) {
        $user = $request->getAttribute('user');
        
        if (!$user) {
            return Response::json(['error' => 'Unauthenticated'], 401);
        }
        
        if ($user->role === 'ROLE_SUPER_ADMIN') {
            return $next($request); // Super Admin passes all checks
        }
        
        if (!RolePermissions::has($user->role, $permission)) {
            AuditLogger::logSecurityEvent('UNAUTHORIZED_ACTION_ATTEMPT', [
                'user_id' => $user->id,
                'role' => $user->role,
                'attempted_permission' => $permission,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            return Response::json([
                'status' => 'error',
                'code' => 403,
                'message' => 'Access denied. Insufficient privileges.',
                'incident_id' => 'SEC-' . bin2hex(random_bytes(4))
            ], 403);
        }
        
        return $next($request);
    }
}
```

---

### 5. Frontend Permission-Denied UI States

1. **Sidebar Navigation Filtration:**  
   The React Admin layout queries the user's role on boot (`GET /api/v1/admin/auth/me`). Navigation links for unauthorized modules are omitted from the sidebar to maintain a clean workspace.
2. **Direct URL Route Shield:**  
   If a user bookmarks or manually enters an unauthorized path (e.g., Content Editor navigating to `/admin/proposals`):
   - The React Router client-side guard redirects to `/admin/403` or renders the embedded `PermissionDeniedView`.
   - UI displays: Branded soft lavender card, padlock icon, clear headline: "Access Restricted", explanation: "Your account role (Content Editor) does not have authorization to view the Proposal Desk. If you require access, contact a Super Admin.", and a primary button: "Return to Dashboard".
3. **Action Button States:**  
   If an entity is partially viewable but mutation is restricted (e.g., Website Admin viewing proposals if ever granted read-only), action buttons ("Publish", "Delete", "Export") are disabled with a tooltip: "Action requires elevated Super Admin privileges."

---

### 6. Acceptance Criteria & Automated Security Test Scenarios

| Test ID | Related Req ID | Test Scenario | Expected Server Response | Verification Method |
| :--- | :--- | :--- | :--- | :--- |
| **TEST-ADM-004** | `FR-ADM-004` | Content Editor requests `GET /api/v1/admin/users` | HTTP `403 Forbidden` | Vitest / PHPUnit |
| **TEST-ADM-014** | `FR-ADM-015` | BD Manager requests `GET /api/v1/admin/applications/:id/download-resume` | HTTP `403 Forbidden` | Vitest / PHPUnit |
| **TEST-ADM-011** | `FR-ADM-012` | HR Manager requests `GET /api/v1/admin/proposals/:id/download-tender` | HTTP `403 Forbidden` | Vitest / PHPUnit |
| **TEST-ADM-018** | `FR-ADM-019` | Website Admin attempts to edit `PUT /api/v1/admin/settings` | HTTP `403 Forbidden` | Vitest / PHPUnit |
| **TEST-SEC-001** | `FR-SEC-001` | Unauthorized mutation attempt creates record in `audit_logs` | Audit record exists with action `UNAUTHORIZED_ACTION_ATTEMPT` | Database Query |
| **TEST-PUB-021** | `FR-PUB-021` | Super Admin downloads tender document | HTTP `200 OK` (Binary octet-stream) + Audit entry | Playwright E2E |
