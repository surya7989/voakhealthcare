# API Contract Plan & Endpoint Specifications
## Voak Health Care REST API Specification

**Document Reference:** `docs/phase-00/09_API_CONTRACT_PLAN.md`  
**Phase:** Phase 0 — Project Requirements & Hosting Audit  
**Implementation Alignment:** Restored 32-Phase Roadmap (Backend Phases 4–17)  
**Base URL:** `/api/` (Routed via `public_html/api/index.php`)  
**Format:** JSON (UTF-8) / `multipart/form-data` for file uploads  

---

### 1. Global API Standards & Envelope Definitions

#### A. Global Request Headers
- `Accept: application/json`
- `Content-Type: application/json` (or `multipart/form-data` for uploads)
- `X-CSRF-Token: <token>` (Mandatory for all state-changing `POST`, `PUT`, `PATCH`, `DELETE` calls)
- `X-Requested-With: XMLHttpRequest`

#### B. Standard HTTP Status Codes
- `200 OK`: Request succeeded.
- `201 Created`: Resource successfully created.
- `400 Bad Request`: Malformed JSON or request syntax.
- `401 Unauthorized`: Authentication missing, expired, or invalid credentials.
- `403 Forbidden`: Authenticated user lacks permission or CSRF validation failed.
- `404 Not Found`: Resource or slug does not exist.
- `422 Unprocessable Entity`: Input payload failed validation rules.
- `429 Too Many Requests`: Rate limit triggered (e.g. 5 failed logins or 10 forms/hour).
- `500 Internal Server Error`: Server exception logged with `requestId`.

---

### 2. Public API Endpoints

#### `GET /api/health` — Minimal Public Liveness Endpoint (Phase 4)
- **Security Standard:** Does **not** leak PHP version, database engine/name, internal server paths, or environment secrets.
- **Response `200 OK`:**
```json
{
  "status": "ok",
  "timestamp": "2026-10-16T08:45:00Z"
}
```

#### `GET /api/services` (Phase 8)
- **Purpose:** Retrieve list of published B2B healthcare services.
- **Query Params:** `category` (optional)
- **Response `200 OK`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "slug": "healthcare-staffing",
      "title": "Healthcare Staffing & Workforce Support",
      "category": "Workforce",
      "short_summary": "Comprehensive clinical and locum workforce solutions tailored for healthcare institutions.",
      "display_order": 1
    }
  ]
}
```

#### `GET /api/services/{slug}` (Phase 8)
- **Purpose:** Deep-dive service detail page content and scope checklist.
- **Response `200 OK`:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "slug": "healthcare-staffing",
    "title": "Healthcare Staffing & Workforce Support",
    "category": "Workforce",
    "short_summary": "...",
    "content_html": "<h2>Clinical Staffing Capabilities</h2><p>...</p>",
    "meta_title": "Healthcare Staffing Solutions | Voak Health Care",
    "meta_description": "Enterprise clinical staffing and workforce solutions for hospitals."
  }
}
```

#### `GET /api/industries` & `GET /api/industries/{slug}` (Phase 9)
- **Purpose:** Returns industry verticals (Hospitals, Medical Clinics, Aged Care).

#### `POST /api/enquiries/contact` (Phase 11)
- **Purpose:** Inbound general inquiry submission.
- **Request Payload:**
```json
{
  "full_name": "Dr. Sarah Jenkins",
  "organization": "Pacific Healthcare Group",
  "email": "s.jenkins@pacifichealth.com.au",
  "phone": "+61 2 9876 5432",
  "service_interest": "Operational Support",
  "message": "We are seeking operational consulting to streamline clinical workflows across 3 clinics."
}
```
- **Validation Rules:**
  - `full_name`: required, string, max 120 chars.
  - `organization`: required, string, max 150 chars.
  - `email`: required, valid email format.
  - `message`: required, min 15 chars, max 3000 chars.
- **Response `201 Created`:**
```json
{
  "success": true,
  "data": {
    "message": "Thank you for reaching out to Voak Health Care. Our executive team will review your inquiry within one business day.",
    "referenceId": "ENQ-2026-8812"
  }
}
```

#### `POST /api/enquiries/proposal` (Phase 12)
- **Purpose:** High-intent Request for Proposal (RFP) submission with document attachment.
- **Content-Type:** `multipart/form-data`
- **Form Fields:**
  - `organization_name`: string, required
  - `facility_type`: string, required
  - `contact_name`: string, required
  - `contact_title`: string, required
  - `email`: string, required
  - `phone`: string, required
  - `estimated_budget`: string, optional
  - `target_start_date`: string, optional
  - `project_brief`: string, required
  - `rfp_file`: binary file (PDF/DOCX, max 10MB), optional
- **Response `201 Created`:**
```json
{
  "success": true,
  "data": {
    "referenceNo": "RFP-2026-4401",
    "message": "Your proposal request has been logged. An executive lead has been assigned."
  }
}
```

#### `POST /api/careers/{slug}/apply` (Phase 14)
- **Purpose:** Submit job application with resume attachment.
- **Content-Type:** `multipart/form-data`
- **Form Fields:** `full_name`, `email`, `phone`, `cover_letter`, `resume_file` (PDF/DOCX max 10MB).

---

### 3. Admin Authentication & Session Endpoints (Phase 6)

#### `GET /api/admin/auth/csrf`
- **Purpose:** Issues CSRF token and sets secure session cookie.
- **Response `200 OK`:**
```json
{
  "success": true,
  "data": {
    "csrfToken": "136d483d1a96c5e87c5e188f319392eb6b64821104f936f943369691f1d15b35"
  }
}
```

#### `POST /api/admin/auth/login`
- **Purpose:** Authenticate administrative user.
- **Request Payload:**
```json
{
  "email": "admin@voakhealthcare.com",
  "password": "SecurePassword123!"
}
```
- **Response `200 OK` (MFA Not Required):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Super Admin",
      "email": "admin@voakhealthcare.com",
      "roles": ["super_admin"],
      "permissions": ["*"]
    }
  }
}
```
- **Response `200 OK` (MFA Required):**
```json
{
  "success": true,
  "data": {
    "mfaRequired": true,
    "tempToken": "mfa_challenge_token_string"
  }
}
```

#### `POST /api/admin/auth/mfa/verify`
- **Request Payload:** `{ "tempToken": "...", "totpCode": "123456" }`

#### `POST /api/admin/auth/logout`
- **Purpose:** Invalidate session token in database and expire browser cookie.

---

### 4. Admin Management Endpoints (Phases 6–17)

| Method | Endpoint | Description | Phase | Required RBAC Permission |
| :--- | :--- | :--- | :---: | :--- |
| `GET` | `/api/admin/dashboard/stats` | Aggregated KPI counts & recent activity | **Phase 17** | `dashboard.view` |
| `GET` | `/api/admin/system/diagnostics` | Protected telemetry (DB latency, storage) | **Phase 17** | `system.diagnostics` (Super Admin) |
| `GET` | `/api/admin/users` | List all administrative staff | **Phase 6** | `users.manage` |
| `POST` | `/api/admin/users` | Create new admin account | **Phase 6** | `users.manage` |
| `PUT` | `/api/admin/users/{id}` | Update user details or roles | **Phase 6** | `users.manage` |
| `DELETE`| `/api/admin/users/{id}` | Deactivate user account | **Phase 6** | `users.manage` |
| `GET` | `/api/admin/services` | List all services (published & drafts) | **Phase 8** | `services.manage` |
| `POST` | `/api/admin/services` | Create new service pillar | **Phase 8** | `services.manage` |
| `PUT` | `/api/admin/services/{id}` | Update service content & SEO tags | **Phase 8** | `services.manage` |
| `DELETE`| `/api/admin/services/{id}` | Delete service pillar | **Phase 8** | `services.manage` |
| `GET` | `/api/admin/enquiries/contact` | Paginated contact inquiries | **Phase 11** | `enquiries.view` |
| `PATCH`| `/api/admin/enquiries/contact/{id}`| Update status or internal notes | **Phase 11** | `enquiries.manage` |
| `GET` | `/api/admin/enquiries/proposals` | Paginated RFP proposals | **Phase 12** | `proposals.manage` |
| `GET` | `/api/admin/enquiries/proposals/{id}/document` | Authenticated streaming download of RFP | **Phase 12** | `proposals.manage` |
| `GET` | `/api/admin/careers/applications` | List candidate applications | **Phase 14** | `applications.view` |
| `GET` | `/api/admin/careers/applications/{id}/resume` | Authenticated streaming download of resume | **Phase 14** | `applications.view` |
| `POST` | `/api/admin/media/upload` | Upload media image with WebP conversion | **Phase 10** | `media.manage` |
| `GET` | `/api/admin/audit-logs` | Filterable, read-only audit records | **Phase 17** | `audit.view` |
| `GET` | `/api/admin/settings` | Get site settings | **Phase 7** | `settings.manage` |
| `PUT` | `/api/admin/settings` | Update site settings | **Phase 7** | `settings.manage` |
