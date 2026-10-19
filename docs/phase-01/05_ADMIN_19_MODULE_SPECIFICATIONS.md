# Admin Management Console: 19 Module Specifications
## Complete Functional Architecture, Screen Layouts, RBAC Gates, and Data Workflows

**Document Reference:** `docs/phase-01/05_ADMIN_19_MODULE_SPECIFICATIONS.md`  
**Phase:** Phase 1 of 31 — Complete Functional Specification  
**Baseline Authority:** Approved Phase 0 Administrative Modules (`04_ADMIN_MODULES_AND_RBAC.md`) and Technical Architecture (`07_TECHNICAL_ARCHITECTURE.md`).

---

### Executive Architecture: The 19 Administrative Subsystems

The Voak Health Care Admin Portal provides a consolidated operations dashboard for executive oversight, pipeline triage, editorial publishing, and recruitment. Every module enforces:
1. **Server-Side Authorization:** PHP checks role permissions on every request; frontend element hiding is for UX only.
2. **Synchronizer CSRF Protection:** Mandatory token verification on all state mutations (`POST`, `PUT`, `DELETE`).
3. **Immutable Audit Logging:** Every CRUD operation records actor ID, role, action, target entity, timestamp, IP, and User-Agent.
4. **Data Privacy Guardrails:** No patient data; candidate PII and proposal tenders restricted to authorized roles.

---

### Module Specifications (ADM-001 to ADM-019)

---

#### Module 01: Dashboard Overview & System Telemetry
- **Module ID:** `ADM-001`
- **Purpose:** Present an executive real-time overview of inbound business inquiries, RFP proposals, recruitment activity, and system operational health.
- **User Roles:** Super Admin, Website Administrator, Content Editor, BD Manager, HR Manager (Widgets filtered by role).
- **Navigation Structure:** Top-level sidebar item: `Dashboard` (`/admin/dashboard`).
- **Screen List:**
  1. Primary Dashboard View (`/admin/dashboard`)
- **Table Columns / Widgets:**
  - Metric Card 1: New Contact Inquiries (Count last 24h, 7d change %).
  - Metric Card 2: Active Proposals / RFPs (Count under review).
  - Metric Card 3: Pending Job Applications (Count unreviewed).
  - Metric Card 4: Published Articles & Services (Content count).
  - Quick Triage Queue: Top 5 most recent unassigned inquiries (`Reference`, `Organization`, `Type`, `Received At`, `Action`).
  - System Telemetry Widget: Database Status (`Connected`), Disk Space (`OK`), Last Backup Date, Active Admin Sessions.
- **Search & Filter Controls:** Date range selector for metrics (Last 24 Hours, 7 Days, 30 Days, 90 Days).
- **CRUD Operations:** Read-only aggregate view.
- **Bulk Actions:** None.
- **Workflow Transitions:** None directly; clicking queue item navigates to specific inquiry/proposal screen.
- **API Dependencies:** `GET /api/v1/admin/dashboard/metrics`, `GET /api/v1/admin/dashboard/recent-queue`.
- **Validation Requirements:** Date range parameters sanitized.
- **Export Permissions:** None from dashboard (handled in respective CRM modules).
- **Audit Events:** `ADMIN_DASHBOARD_ACCESSED`.
- **Error & Empty States:** If metrics fail to load, cards display retry spinner; queue displays "No pending inquiries requiring immediate triage."
- **Data Privacy Considerations:** BD Manager sees inquiries/proposals; HR Manager sees recruitment; Content Editor sees only content counters.
- **Acceptance Criteria:**
  1. Renders metrics filtered according to active user's RBAC role.
  2. Updates within 500ms API response time.
- **Test Scenarios:**
  - `TEST-ADM-001`: Log in as HR Manager; verify proposal and financial widgets are hidden.

---

#### Module 02: Authentication, Session & Multi-Factor Gate
- **Module ID:** `ADM-002`
- **Purpose:** Secure administrative access through credentials, brute-force throttling, and RFC 6238 TOTP multi-factor verification.
- **User Roles:** All administrative roles.
- **Navigation Structure:** Unauthenticated entry gate (`/admin/login`, `/admin/mfa-verify`, `/admin/password-recover`).
- **Screen List:**
  1. Admin Login Screen (`/admin/login`)
  2. MFA Verification Challenge Screen (`/admin/mfa-verify`)
  3. Password Recovery Request Screen (`/admin/password-recover`)
  4. Password Reset Set-New-Password Screen (`/admin/password-reset/:token`)
- **Table Columns:** N/A (Authentication flows).
- **Search & Filter Controls:** N/A.
- **CRUD Operations:** Create session (`POST /login`), Verify TOTP (`POST /mfa`), Destroy session (`POST /logout`).
- **Bulk Actions:** N/A.
- **Workflow Transitions:** Unauthenticated -> Credentials Verified -> MFA Challenged -> Session Issued -> Inactivity Timeout / Logged Out.
- **API Dependencies:**
  - `POST /api/v1/admin/auth/login`
  - `POST /api/v1/admin/auth/mfa/verify`
  - `POST /api/v1/admin/auth/logout`
  - `POST /api/v1/admin/auth/password-recover`
  - `POST /api/v1/admin/auth/password-reset`
- **Validation Requirements:**
  - Email format validation; password minimum 12 characters with complexity rules; TOTP strictly 6 digits numeric.
  - Brute-force throttle: 5 failed attempts locks IP/Account for 15 minutes.
- **Export Permissions:** None.
- **Audit Events:** `AUTH_LOGIN_SUCCESS`, `AUTH_LOGIN_FAILED`, `AUTH_LOCKOUT_TRIGGERED`, `AUTH_MFA_SUCCESS`, `AUTH_MFA_FAILED`, `AUTH_LOGOUT`.
- **Error & Empty States:** Generic error message: "Invalid email or password" (prevents user enumeration).
- **Data Privacy Considerations:** Passwords never logged; session token delivered via `HttpOnly; Secure; SameSite=Strict` cookie.
- **Acceptance Criteria:**
  1. Throttles after 5 failed login attempts.
  2. Requires 6-digit TOTP for Super Admin accounts.
  3. Terminates session upon 15 minutes of inactivity.
- **Test Scenarios:**
  - `TEST-ADM-002`: Attempt 6 invalid logins; verify 6th attempt returns 429 Too Many Requests.

---

#### Module 03: User & Role-Based Access Control (RBAC) Management
- **Module ID:** `ADM-003`
- **Purpose:** Manage administrative staff accounts, assign granular RBAC roles, enforce MFA requirements, and revoke access.
- **User Roles:** Super Admin only.
- **Navigation Structure:** Settings & Security -> `Users & Permissions` (`/admin/users`).
- **Screen List:**
  1. User Directory Table (`/admin/users`)
  2. Add New Admin User Modal/Screen (`/admin/users/create`)
  3. Edit User & Role Assignment Screen (`/admin/users/:id/edit`)
- **Table Columns:** `Avatar/Name`, `Email`, `Role Badge`, `MFA Status`, `Last Active`, `Status (Active/Suspended)`, `Actions`.
- **Search & Filter Controls:** Search by name/email; filter by Role (`Super Admin`, `Administrator`, `Content Editor`, `BD Manager`, `HR Manager`), filter by Status.
- **CRUD Operations:** Create new user invitation, Read user details, Update user profile/role, Suspend/Activate user, Revoke sessions. (Hard delete disabled; soft deactivation enforced).
- **Bulk Actions:** Bulk suspend selected users.
- **Workflow Transitions:** Invited -> Activated -> Active <-> Suspended.
- **API Dependencies:** `GET /api/v1/admin/users`, `POST /api/v1/admin/users`, `PUT /api/v1/admin/users/:id`, `PATCH /api/v1/admin/users/:id/status`.
- **Validation Requirements:** Email must be unique corporate domain; password set via secure invitation token (expires in 24 hours).
- **Export Permissions:** Super Admin only (CSV export of user roster).
- **Audit Events:** `USER_CREATED`, `USER_ROLE_CHANGED`, `USER_SUSPENDED`, `USER_SESSION_REVOKED`.
- **Error & Empty States:** Empty table displays "No administrative users found matching filter."
- **Data Privacy Considerations:** Passwords and TOTP secrets encrypted; user cannot revoke their own Super Admin status (prevents lockouts).
- **Acceptance Criteria:**
  1. Non-Super Admin roles receive 403 Forbidden when accessing `/api/v1/admin/users`.
  2. System enforces at least 1 active Super Admin account at all times.
- **Test Scenarios:**
  - `TEST-ADM-003`: Verify Content Editor receives 403 when calling `GET /api/v1/admin/users`.

---

#### Module 04: Content & Static Page Management
- **Module ID:** `ADM-004`
- **Purpose:** Manage static and editorial page contents (About Us, Why Choose Voak, How We Work, Quality & Compliance, Legal Policies).
- **User Roles:** Super Admin, Website Administrator, Content Editor.
- **Navigation Structure:** Content Management -> `Pages` (`/admin/pages`).
- **Screen List:**
  1. Pages List Table (`/admin/pages`)
  2. Page Editor Screen with Markdown editor and preview (`/admin/pages/:id/edit`)
  3. Page Revision History Modal (`/admin/pages/:id/history`)
- **Table Columns:** `Page Title`, `Route / Slug`, `Last Updated By`, `Revision Count`, `Status (Published/Draft)`, `Updated At`, `Actions`.
- **Search & Filter Controls:** Search by page title; filter by status.
- **CRUD Operations:** Read page list, Read page content, Update page content & SEO metadata, Rollback to previous revision. (Page creation/deletion restricted to preserve route integrity).
- **Bulk Actions:** None.
- **Workflow Transitions:** Draft -> In Review -> Published.
- **API Dependencies:** `GET /api/v1/admin/pages`, `GET /api/v1/admin/pages/:id`, `PUT /api/v1/admin/pages/:id`, `POST /api/v1/admin/pages/:id/rollback`.
- **Validation Requirements:** Page title required; markdown sanitized via DOMPurify before storage.
- **Export Permissions:** None.
- **Audit Events:** `PAGE_UPDATED`, `PAGE_REVISION_RESTORED`.
- **Error & Empty States:** Conflict warning when concurrent edits detected (`updated_at` timestamp check).
- **Data Privacy Considerations:** Public corporate content only.
- **Acceptance Criteria:**
  1. Saving page creates new entry in `page_revisions` table.
  2. Rollback restores previous content state cleanly.
- **Test Scenarios:**
  - `TEST-ADM-004`: Update About Us content, trigger rollback, verify content matches original state.

---

#### Module 05: Hero & Homepage Layout Management
- **Module ID:** `ADM-005`
- **Purpose:** Configure homepage hero headlines, announcement ribbons, featured bento service ordering, and conversion CTAs.
- **User Roles:** Super Admin, Website Administrator, Content Editor.
- **Navigation Structure:** Content Management -> `Homepage Customizer` (`/admin/homepage`).
- **Screen List:**
  1. Homepage Customizer Tabbed View (`/admin/homepage`):
     - Tab 1: Hero & Announcement Banner
     - Tab 2: Bento Grid Layout & Featured Services
     - Tab 3: Value Propositions & Governance Copy
     - Tab 4: Conversion Banners & Phone Hotlines
- **Table Columns:** N/A (Form and ordering controls).
- **Search & Filter Controls:** N/A.
- **CRUD Operations:** Read configuration, Update configuration.
- **Bulk Actions:** None.
- **Workflow Transitions:** Draft Changes -> Live Publish.
- **API Dependencies:** `GET /api/v1/admin/homepage`, `PUT /api/v1/admin/homepage`.
- **Validation Requirements:** Hero headline max 100 chars; announcement text max 120 chars; phone number valid format.
- **Export Permissions:** None.
- **Audit Events:** `HOMEPAGE_CONFIG_UPDATED`.
- **Error & Empty States:** Unsaved changes warning modal when navigating away.
- **Data Privacy Considerations:** Public content.
- **Acceptance Criteria:**
  1. Toggling announcement banner off immediately hides it from the public homepage without layout shift.
- **Test Scenarios:**
  - `TEST-ADM-005`: Update hero headline in CMS; verify public homepage reflects change after cache TTL.

---

#### Module 06: Services Catalog Management Console
- **Module ID:** `ADM-006`
- **Purpose:** Complete CRUD management for the 6 core healthcare service offerings, categories, deliverables, SLA specifications, and publish states.
- **User Roles:** Super Admin, Website Administrator, Content Editor.
- **Navigation Structure:** Services & Solutions -> `Services Catalog` (`/admin/services`).
- **Screen List:**
  1. Services Table View (`/admin/services`)
  2. Create Service Screen (`/admin/services/create`)
  3. Edit Service Screen (`/admin/services/:id/edit`)
- **Table Columns:** `Order`, `Service Title`, `Category`, `Slug`, `Status (Published/Draft)`, `Featured on Home`, `Updated At`, `Actions`.
- **Search & Filter Controls:** Search by service name; filter by Category; filter by Publish Status.
- **CRUD Operations:** Create new service, Read service details, Update service fields/deliverables/SLAs, Reorder display sequence, Unpublish/Publish service, Soft delete service.
- **Bulk Actions:** Bulk publish/unpublish selected services.
- **Workflow Transitions:** Draft <-> Published <-> Archived.
- **API Dependencies:** `GET /api/v1/admin/services`, `POST /api/v1/admin/services`, `PUT /api/v1/admin/services/:id`, `PATCH /api/v1/admin/services/reorder`, `DELETE /api/v1/admin/services/:id`.
- **Validation Requirements:** Title required (max 120); slug unique (max 140); summary required (max 250); markdown content required.
- **Export Permissions:** Super Admin and Website Admin (CSV export).
- **Audit Events:** `SERVICE_CREATED`, `SERVICE_UPDATED`, `SERVICE_SLUG_CHANGED`, `SERVICE_DELETED`.
- **Error & Empty States:** Deleting a service linked to historical proposals soft-deletes and preserves reference integrity.
- **Data Privacy Considerations:** Public service catalog data.
- **Acceptance Criteria:**
  1. Changing a slug automatically creates a 301 entry in `url_redirects`.
  2. Unpublishing removes service from public `/services` instantly.
- **Test Scenarios:**
  - `TEST-ADM-006`: Rename service slug; verify 301 redirect emitted when old slug requested.

---

#### Module 07: Industries Catalog Management Console
- **Module ID:** `ADM-007`
- **Purpose:** Manage healthcare industry sectors, regulatory standards, tailored operational solutions, and service mappings.
- **User Roles:** Super Admin, Website Administrator, Content Editor.
- **Navigation Structure:** Services & Solutions -> `Industries` (`/admin/industries`).
- **Screen List:**
  1. Industries Table View (`/admin/industries`)
  2. Create Industry Sector Screen (`/admin/industries/create`)
  3. Edit Industry Sector Screen (`/admin/industries/:id/edit`)
- **Table Columns:** `Order`, `Sector Name`, `Slug`, `Associated Services Count`, `Status`, `Updated At`, `Actions`.
- **Search & Filter Controls:** Search sector name; filter by publish status.
- **CRUD Operations:** Full CRUD for industry sectors and service relationship mappings.
- **Bulk Actions:** Bulk publish/unpublish.
- **Workflow Transitions:** Draft <-> Published.
- **API Dependencies:** `GET /api/v1/admin/industries`, `POST /api/v1/admin/industries`, `PUT /api/v1/admin/industries/:id`, `DELETE /api/v1/admin/industries/:id`.
- **Validation Requirements:** Sector name required (max 120); slug unique; overview required.
- **Export Permissions:** Standard CSV export.
- **Audit Events:** `INDUSTRY_CREATED`, `INDUSTRY_UPDATED`, `INDUSTRY_DELETED`.
- **Error & Empty States:** Clean table fallback when no industries exist.
- **Data Privacy Considerations:** Public sector data.
- **Acceptance Criteria:**
  1. Updating industry mappings dynamically updates public industry detail page.
- **Test Scenarios:**
  - `TEST-ADM-007`: Create new sector, map to Service 01; verify mapping appears on public page.

---

#### Module 08: Media Library & Asset Optimization Console
- **Module ID:** `ADM-008`
- **Purpose:** Centralized repository for uploading, WebP optimizing, categorizing, and managing images, icons, and public documents.
- **User Roles:** Super Admin, Website Administrator, Content Editor.
- **Navigation Structure:** Content Management -> `Media Library` (`/admin/media`).
- **Screen List:**
  1. Media Grid & List View (`/admin/media`)
  2. Asset Detail & Alt Text Editor Modal (`/admin/media/:id`)
  3. Drag-and-Drop Upload Zone (`/admin/media/upload`)
- **Table Columns (List View):** `Thumbnail`, `Filename`, `Asset Type`, `Dimensions`, `File Size`, `Alt Text Status`, `Uploaded By`, `Date`.
- **Search & Filter Controls:** Search by filename/alt text; filter by Type (Images, Icons, PDFs); filter by Missing Alt Text.
- **CRUD Operations:** Upload asset, Read asset metadata, Update alt text/title, Delete asset (checks usage first).
- **Bulk Actions:** Bulk delete unused assets.
- **Workflow Transitions:** Upload -> Auto-WebP Conversion -> Verified Alt Text -> Published.
- **API Dependencies:** `GET /api/v1/admin/media`, `POST /api/v1/admin/media/upload`, `PUT /api/v1/admin/media/:id`, `DELETE /api/v1/admin/media/:id`.
- **Validation Requirements:**
  - File types: JPG, PNG, WebP, SVG, PDF.
  - Max file size: Images max 5MB, Documents max 15MB.
  - MIME magic byte verification (disallows executable payloads disguised as images).
  - Mandatory alt text enforced before asset can be inserted into published pages.
- **Export Permissions:** None.
- **Audit Events:** `MEDIA_UPLOADED`, `MEDIA_UPDATED`, `MEDIA_DELETED`.
- **Error & Empty States:** Warning if attempting to delete an asset currently referenced by an active page or service.
- **Data Privacy Considerations:** Assets uploaded here are public. No sensitive resumes or RFPs stored in this directory.
- **Acceptance Criteria:**
  1. Uploaded PNG/JPG automatically creates WebP optimized variant.
  2. Rejects malicious files with invalid magic bytes.
- **Test Scenarios:**
  - `TEST-ADM-008`: Upload a valid 2MB PNG; verify WebP conversion is created and served.

---

#### Module 09: Contact Enquiries Triage CRM
- **Module ID:** `ADM-009`
- **Purpose:** Inbound pipeline management for general corporate communications, status triage, notes assignment, and resolution.
- **User Roles:** Super Admin, Website Administrator, BD Manager.
- **Navigation Structure:** Inbound CRM -> `Contact Enquiries` (`/admin/inquiries/contact`).
- **Screen List:**
  1. Enquiries Table View (`/admin/inquiries/contact`)
  2. Enquiry Detail & Activity Ledger (`/admin/inquiries/contact/:id`)
- **Table Columns:** `Ref Number`, `Contact Name`, `Organization`, `Email`, `Type`, `Status Badge`, `Received At`, `Assigned To`, `Actions`.
- **Search & Filter Controls:** Search by reference, name, organization; filter by Status (`NEW`, `IN_PROGRESS`, `QUALIFIED`, `DISQUALIFIED`, `CLOSED`, `SPAM`); filter by Date.
- **CRUD Operations:** Read inquiries, Update inquiry status, Assign staff member, Add internal audit notes. (Delete restricted to Super Admin).
- **Bulk Actions:** Bulk status transition, bulk mark as spam.
- **Workflow Transitions:** `NEW` -> `IN_PROGRESS` -> `QUALIFIED` / `DISQUALIFIED` -> `CLOSED`.
- **API Dependencies:** `GET /api/v1/admin/inquiries/contact`, `GET /api/v1/admin/inquiries/contact/:id`, `PATCH /api/v1/admin/inquiries/contact/:id/status`, `POST /api/v1/admin/inquiries/contact/:id/notes`.
- **Validation Requirements:** Status must be valid enum; internal notes max 2000 chars.
- **Export Permissions:** Super Admin and BD Manager (CSV export of filtered records).
- **Audit Events:** `INQUIRY_VIEWED`, `INQUIRY_STATUS_CHANGED`, `INQUIRY_NOTE_ADDED`, `INQUIRY_EXPORTED`.
- **Error & Empty States:** "No contact inquiries match the current filter criteria."
- **Data Privacy Considerations:** Contact information is corporate B2B PII. Access restricted to authorized CRM roles.
- **Acceptance Criteria:**
  1. Transitioning status to `CLOSED` requires at least 1 internal resolution note.
  2. Exporting records creates an audit log entry with row count.
- **Test Scenarios:**
  - `TEST-ADM-009`: Log in as Content Editor; verify Contact CRM is inaccessible (403 Forbidden).

---

#### Module 10: Strategic Partnership Pipeline Desk
- **Module ID:** `ADM-010`
- **Purpose:** High-touch pipeline management for enterprise healthcare alliances, joint ventures, and consortium inquiries.
- **User Roles:** Super Admin, BD Manager.
- **Navigation Structure:** Inbound CRM -> `Partnership Pipeline` (`/admin/inquiries/partnerships`).
- **Screen List:**
  1. Partnership Pipeline Kanban & Table View (`/admin/inquiries/partnerships`)
  2. Partnership Detail Screen (`/admin/inquiries/partnerships/:id`)
- **Table Columns:** `Ref Number`, `Executive Name`, `Title`, `Organization`, `Sector`, `Scale`, `Status`, `Discovery Date`, `Assigned Lead`.
- **Search & Filter Controls:** Search by organization/executive; filter by Sector, Scale, and Status (`NEW`, `DISCOVERY_SCHEDULED`, `NEGOTIATING`, `PARTNERED`, `DECLINED`).
- **CRUD Operations:** Read pipeline, Update status, Schedule discovery date, Assign lead, Add executive notes.
- **Bulk Actions:** Bulk assign lead.
- **Workflow Transitions:** `NEW` -> `DISCOVERY_SCHEDULED` -> `NEGOTIATING` -> `PARTNERED` / `DECLINED`.
- **API Dependencies:** `GET /api/v1/admin/partnerships`, `GET /api/v1/admin/partnerships/:id`, `PATCH /api/v1/admin/partnerships/:id`, `POST /api/v1/admin/partnerships/:id/notes`.
- **Validation Requirements:** Discovery date must be future date; status transitions validated against state machine.
- **Export Permissions:** Super Admin and BD Manager only.
- **Audit Events:** `PARTNERSHIP_VIEWED`, `PARTNERSHIP_STAGE_CHANGED`, `PARTNERSHIP_DATA_EXPORTED`.
- **Error & Empty States:** Kanban columns render empty state cards cleanly.
- **Data Privacy Considerations:** Highly confidential corporate commercial data. Strictly restricted from HR and Editorial roles.
- **Acceptance Criteria:**
  1. Dragging a card to `PARTNERED` requires confirmation modal with contract start date.
- **Test Scenarios:**
  - `TEST-ADM-010`: Verify only Super Admin and BD Manager can view this module.

---

#### Module 11: Request a Proposal (RFP) Review Desk
- **Module ID:** `ADM-011`
- **Purpose:** Full triage and review desk for healthcare procurement tender briefs, scope specifications, and client RFP attachments.
- **User Roles:** Super Admin, BD Manager.
- **Navigation Structure:** Inbound CRM -> `Proposal Desk` (`/admin/proposals`).
- **Screen List:**
  1. Proposals Table View (`/admin/proposals`)
  2. Proposal Review Screen (`/admin/proposals/:id`)
  3. Tender Document Secure Viewer / Downloader
- **Table Columns:** `RFP Ref`, `Facility Name`, `Sector`, `Requested Services`, `Budget Band`, `Target Start Date`, `Tender Attached (Yes/No)`, `Status Badge`, `Actions`.
- **Search & Filter Controls:** Search by RFP reference, organization; filter by Sector, Services Requested, and Status (`NEW`, `UNDER_REVIEW`, `DRAFTING_PROPOSAL`, `PROPOSAL_SENT`, `CONTRACT_EXECUTED`, `DECLINED`).
- **CRUD Operations:** Read RFP briefs, Update proposal status, Download tender documents via authenticated stream, Assign procurement lead, Add internal scoping notes.
- **Bulk Actions:** Bulk status transition.
- **Workflow Transitions:** `NEW` -> `UNDER_REVIEW` -> `DRAFTING_PROPOSAL` -> `PROPOSAL_SENT` -> `CONTRACT_EXECUTED` / `DECLINED`.
- **API Dependencies:**
  - `GET /api/v1/admin/proposals`
  - `GET /api/v1/admin/proposals/:id`
  - `PATCH /api/v1/admin/proposals/:id/status`
  - `GET /api/v1/admin/proposals/:id/download-tender` (Authenticated binary stream)
- **Validation Requirements:** Notes required on rejection/decline.
- **Export Permissions:** Super Admin and BD Manager only.
- **Audit Events:** `PROPOSAL_VIEWED`, `PROPOSAL_STATUS_CHANGED`, `PROPOSAL_TENDER_DOWNLOADED`.
- **Error & Empty States:** Graceful handling if tender file was deleted or corrupted.
- **Data Privacy Considerations:** Commercial tender documents quarantined in `storage/uploads/proposals/` outside web root. Downloads verify active session and role.
- **Acceptance Criteria:**
  1. Direct file link to tender document returns 403; must stream via authenticated API.
  2. Downloading tender generates immediate audit log entry.
- **Test Scenarios:**
  - `TEST-ADM-011`: Request tender download as Content Editor; assert 403 Forbidden.

---

#### Module 12: Transactional Email Templates & Outbox Console
- **Module ID:** `ADM-012`
- **Purpose:** Manage system transactional email copy, subject lines, dynamic placeholder mappings, durable outbox telemetry, and manual delivery retry controls.
- **User Roles:** Super Admin, Website Administrator.
- **Navigation Structure:** Settings & Security -> `Email & Outbox` (`/admin/email-templates`).
- **Screen List:**
  1. Email Templates List (`/admin/email-templates`)
  2. Edit Template Screen with placeholder guide and preview (`/admin/email-templates/:id`)
  3. Email Outbox Queue & Retry Console (`/admin/email-templates/outbox`)
- **Table Columns (Templates View):** `Template Key`, `Title / Trigger Event`, `Recipient`, `Subject Line`, `Status (Active/Inactive)`, `Updated At`, `Actions`.
- **Table Columns (Outbox View):** `Outbox ID`, `Template`, `Recipient`, `Priority`, `Status (PENDING, PROCESSING, SENT, FAILED)`, `Attempts`, `Claimed By`, `Last Error`, `Created At`, `Actions (Retry)`.
- **Search & Filter Controls:** Search by title/recipient/error; filter by Outbox Status (`PENDING`, `PROCESSING`, `SENT`, `FAILED`).
- **CRUD Operations:** Read templates, Update subject line and body text, Send test email to administrator, View outbox queue, Trigger manual retry for failed emails (`POST /api/v1/admin/email-templates/outbox/:id/retry`). (Template keys cannot be deleted).
- **Bulk Actions:** Bulk retry failed outbox emails.
- **Workflow Transitions:** Active <-> Inactive (Templates); PENDING -> PROCESSING -> SENT / FAILED (Outbox).
- **API Dependencies:** `GET /api/v1/admin/email-templates`, `PUT /api/v1/admin/email-templates/:id`, `POST /api/v1/admin/email-templates/:id/test-send`, `GET /api/v1/admin/email-templates/outbox`, `POST /api/v1/admin/email-templates/outbox/:id/retry`.
- **Validation Requirements:** Mandatory placeholders (e.g., `{{ref_no}}`, `{{legal_entity_name}}`) must remain present in template body.
- **Export Permissions:** None.
- **Audit Events:** `EMAIL_TEMPLATE_UPDATED`, `EMAIL_TEST_SENT`, `EMAIL_MANUAL_RETRY_TRIGGERED`.
- **Error & Empty States:** Validation warning if a required variable tag is missing; outbox empty state displays "No queued emails matching filter."
- **Data Privacy Considerations:** Templates must include corporate contact footer with `{{legal_entity_name}}` and `{{registration_number}}`.
- **Acceptance Criteria:**
  1. "Send Test Email" delivers rendered HTML to current admin's email using test data.
  2. Failed outbox items can be manually reset to `PENDING` via "Retry Send".
- **Test Scenarios:**
  - `TEST-ADM-012`: Remove `{{ref_no}}` and attempt to save; verify validation error blocks save.

---

#### Module 13: Careers & Job Openings Management
- **Module ID:** `ADM-013`
- **Purpose:** Create and manage clinical, operational, and corporate job postings, specify credentialing requirements, and manage closing dates.
- **User Roles:** Super Admin, Website Administrator, HR Manager.
- **Navigation Structure:** Careers & Talent -> `Job Openings` (`/admin/careers/jobs`).
- **Screen List:**
  1. Job Postings Table (`/admin/careers/jobs`)
  2. Create Job Posting Screen (`/admin/careers/jobs/create`)
  3. Edit Job Posting Screen (`/admin/careers/jobs/:id/edit`)
- **Table Columns:** `Job Title`, `Department`, `Employment Type`, `Location`, `Applications Count`, `Closing Date`, `Status (Active/Closed)`, `Actions`.
- **Search & Filter Controls:** Search by title; filter by Department, Type, Status.
- **CRUD Operations:** Full CRUD for job postings.
- **Bulk Actions:** Bulk close expired jobs.
- **Workflow Transitions:** Draft <-> Active <-> Closed <-> Archived.
- **API Dependencies:** `GET /api/v1/admin/jobs`, `POST /api/v1/admin/jobs`, `PUT /api/v1/admin/jobs/:id`, `DELETE /api/v1/admin/jobs/:id`.
- **Validation Requirements:** Title required; department required; closing date must be future date; description markdown required.
- **Export Permissions:** HR Manager and Super Admin.
- **Audit Events:** `JOB_POSTING_CREATED`, `JOB_POSTING_UPDATED`, `JOB_POSTING_CLOSED`.
- **Error & Empty States:** Clean empty table state.
- **Data Privacy Considerations:** Internal job posting details.
- **Acceptance Criteria:**
  1. Expired jobs automatically transition to `Closed` status at midnight of closing date.
- **Test Scenarios:**
  - `TEST-ADM-013`: Create a job posting; verify it appears immediately on `/careers`.

---

#### Module 14: Candidate Applications & Recruitment Triage
- **Module ID:** `ADM-014`
- **Purpose:** Manage candidate resumes, credentialing checks, application reviews, and recruitment workflow stages.
- **User Roles:** Super Admin, HR Manager.
- **Navigation Structure:** Careers & Talent -> `Applications` (`/admin/careers/applications`).
- **Screen List:**
  1. Applications Table View (`/admin/careers/applications`)
  2. Candidate Profile & Resume Review Screen (`/admin/careers/applications/:id`)
- **Table Columns:** `Candidate Name`, `Job Applied For`, `Email`, `Phone`, `Resume Attached`, `Status Badge`, `Applied Date`, `Actions`.
- **Search & Filter Controls:** Search candidate name/email; filter by Job Opening, Status (`NEW`, `REVIEWED`, `SHORTLISTED`, `INTERVIEWING`, `OFFER_EXTENDED`, `HIRED`, `REJECTED`).
- **CRUD Operations:** Read applications, Update hiring stage, Download candidate resume via authenticated streaming, Add recruitment notes. (Application records cannot be deleted except via formal Privacy Act erasure request executed by Super Admin).
- **Bulk Actions:** Bulk status transition.
- **Workflow Transitions:** `NEW` -> `REVIEWED` -> `SHORTLISTED` -> `INTERVIEWING` -> `OFFER_EXTENDED` -> `HIRED` / `REJECTED`.
- **API Dependencies:**
  - `GET /api/v1/admin/applications`
  - `GET /api/v1/admin/applications/:id`
  - `PATCH /api/v1/admin/applications/:id/status`
  - `GET /api/v1/admin/applications/:id/download-resume` (Authenticated stream)
- **Validation Requirements:** Status transitions validated.
- **Export Permissions:** HR Manager and Super Admin only.
- **Audit Events:** `CANDIDATE_RESUME_DOWNLOADED`, `APPLICATION_STATUS_UPDATED`, `CANDIDATE_DATA_EXPORTED`.
- **Error & Empty States:** Graceful notice if candidate resume file is missing from storage.
- **Data Privacy Considerations:** Candidate personal information (PII) is strictly confidential under the Privacy Act. Resumes stored in `storage/uploads/resumes/` outside public web root. Access restricted from BD and Content Editor roles.
- **Acceptance Criteria:**
  1. BD Manager receives 403 Forbidden when attempting to view candidate records.
  2. Resume download generates audit record with candidate ID and HR user ID.
- **Test Scenarios:**
  - `TEST-ADM-014`: Verify BD Manager cannot access recruitment endpoints.

---

#### Module 15: Case Studies & Social Proof Console
- **Module ID:** `ADM-015`
- **Purpose:** Manage verified operational case studies, client testimonial quotes, and mandatory verified release consent attachments.
- **User Roles:** Super Admin, Website Administrator, Content Editor.
- **Navigation Structure:** Content Management -> `Case Studies & Proof` (`/admin/case-studies`).
- **Screen List:**
  1. Case Studies & Testimonials Table (`/admin/case-studies`)
  2. Create/Edit Case Study Screen (`/admin/case-studies/:id/edit`)
  3. Create/Edit Testimonial Modal (`/admin/testimonials/:id`)
- **Table Columns:** `Title / Client`, `Sector`, `Verified Release Attached?`, `Featured on Home`, `Publish Status`, `Actions`.
- **Search & Filter Controls:** Search by client/title; filter by Sector, Publish Status.
- **CRUD Operations:** Full CRUD for case studies and testimonials.
- **Bulk Actions:** Bulk publish/unpublish.
- **Workflow Transitions:** Draft <-> Verified <-> Published.
- **API Dependencies:** `GET /api/v1/admin/case-studies`, `POST /api/v1/admin/case-studies`, `PUT /api/v1/admin/case-studies/:id`, `DELETE /api/v1/admin/case-studies/:id`.
- **Validation Requirements:**
  - **MANDATORY CONTENT ACCURACY GATE:** Setting `is_published = 1` requires either:
    1. An uploaded client release consent document reference, OR
    2. Explicit confirmation toggle that client entity name has been anonymized.
- **Export Permissions:** Standard CSV export.
- **Audit Events:** `CASE_STUDY_CREATED`, `CASE_STUDY_PUBLISHED`, `TESTIMONIAL_VERIFIED`.
- **Error & Empty States:** Warning if publishing attempted without verified release consent.
- **Data Privacy Considerations:** Enforces client confidentiality agreements.
- **Acceptance Criteria:**
  1. Blocks publishing of identifiable client case studies lacking consent documentation.
- **Test Scenarios:**
  - `TEST-ADM-015`: Attempt to publish an un-anonymized case study without release ref; assert validation failure.

---

#### Module 16: Insights & Articles CMS
- **Module ID:** `ADM-016`
- **Purpose:** Editorial content management for healthcare operational whitepapers, regulatory updates, and industry insights.
- **User Roles:** Super Admin, Website Administrator, Content Editor.
- **Navigation Structure:** Content Management -> `Insights & Articles` (`/admin/insights`).
- **Screen List:**
  1. Articles Directory Table (`/admin/insights`)
  2. Article Editor with Markdown support, live preview, reading time auto-calc, and SEO tags (`/admin/insights/:id/edit`)
- **Table Columns:** `Title`, `Category`, `Author`, `Reading Time`, `Status (Draft/Published)`, `Published Date`, `Actions`.
- **Search & Filter Controls:** Search by title/content; filter by Category, Author, Publish Status.
- **CRUD Operations:** Full CRUD for articles.
- **Bulk Actions:** Bulk publish/unpublish.
- **Workflow Transitions:** Draft -> In Review -> Published -> Archived.
- **API Dependencies:** `GET /api/v1/admin/articles`, `POST /api/v1/admin/articles`, `PUT /api/v1/admin/articles/:id`, `DELETE /api/v1/admin/articles/:id`.
- **Validation Requirements:** Title required; slug unique; content markdown required; reading time auto-calculated on save (`words / 200`).
- **Export Permissions:** Standard CSV export.
- **Audit Events:** `ARTICLE_CREATED`, `ARTICLE_UPDATED`, `ARTICLE_PUBLISHED`.
- **Error & Empty States:** Autosave recovery banner if draft found in local storage.
- **Data Privacy Considerations:** Public editorial content.
- **Acceptance Criteria:**
  1. Auto-calculates reading time accurately.
  2. Sanitizes markdown content before persistence.
- **Test Scenarios:**
  - `TEST-ADM-016`: Save article with 600 words; assert reading time calculates to 3 minutes.

---

#### Module 17: SEO & Metadata Management Console
- **Module ID:** `ADM-017`
- **Purpose:** Manage global and page-specific SEO meta tags, OpenGraph images, robots directives, canonical URLs, and trigger XML sitemap cache rebuilds.
- **User Roles:** Super Admin, Website Administrator.
- **Navigation Structure:** Settings & Security -> `SEO Management` (`/admin/seo`).
- **Screen List:**
  1. Route-Level SEO Meta Manager (`/admin/seo/routes`)
  2. Global SEO Settings & Sitemap Controls (`/admin/seo/global`)
- **Table Columns:** `Page / Route`, `Meta Title (Length check)`, `Meta Description (Length check)`, `OG Image Set?`, `Robots Directive`, `Last Mod`, `Actions`.
- **Search & Filter Controls:** Search route; filter by Missing Description or Title Too Long (> 60 chars).
- **CRUD Operations:** Read route SEO list, Update route meta tags, Trigger sitemap rebuild, View robots.txt.
- **Bulk Actions:** None.
- **Workflow Transitions:** Saved -> Active.
- **API Dependencies:** `GET /api/v1/admin/seo/routes`, `PUT /api/v1/admin/seo/routes/:id`, `POST /api/v1/admin/seo/rebuild-sitemap`.
- **Validation Requirements:** Meta title recommended 50–60 chars (max 70); meta description recommended 120–155 chars (max 160).
- **Export Permissions:** Super Admin and Website Admin.
- **Audit Events:** `SEO_METADATA_UPDATED`, `SITEMAP_REBUILT`.
- **Error & Empty States:** Visual indicator turns amber if title/description exceeds recommended lengths.
- **Data Privacy Considerations:** Public search engine configuration.
- **Acceptance Criteria:**
  1. Clicking "Rebuild XML Sitemap" invalidates cache and updates `/sitemap.xml` with current published slugs.
- **Test Scenarios:**
  - `TEST-ADM-017`: Update homepage meta description; assert public pre-rendered HTML `<head>` updates.

---

#### Module 18: System Settings & Configuration
- **Module ID:** `ADM-018`
- **Purpose:** Manage global corporate identity (registration number, phone, email, headquarters address), operating hours, SMTP connection health, and maintenance toggles.
- **User Roles:** Super Admin only.
- **Navigation Structure:** Settings & Security -> `System Settings` (`/admin/settings`).
- **Screen List:**
  1. General Corporate Settings Tab (`/admin/settings/general`)
  2. SMTP & Notification Settings Tab (`/admin/settings/mail`)
  3. Security & Session Policy Tab (`/admin/settings/security`)
- **Table Columns:** N/A (Configuration forms).
- **Search & Filter Controls:** N/A.
- **CRUD Operations:** Read settings, Update settings, Test SMTP connectivity.
- **Bulk Actions:** None.
- **Workflow Transitions:** Saved -> Active.
- **API Dependencies:** `GET /api/v1/admin/settings`, `PUT /api/v1/admin/settings`, `POST /api/v1/admin/settings/test-smtp`.
- **Validation Requirements:** Registration number format validation based on configured region; valid email syntax; SMTP port and host validation.
- **Export Permissions:** Super Admin only (System configuration export).
- **Audit Events:** `SYSTEM_SETTINGS_UPDATED`, `SMTP_TEST_TRIGGERED`.
- **Error & Empty States:** Inline SMTP test status (displays detailed handshake error if connection fails).
- **Data Privacy Considerations:** SMTP passwords stored in `.env` outside web root, never displayed in cleartext in the UI.
- **Acceptance Criteria:**
  1. Non-Super Admin roles cannot view or edit system settings.
  2. "Test SMTP" executes real socket handshake and reports success/failure.
- **Test Scenarios:**
  - `TEST-ADM-018`: Verify Website Administrator receives 403 Forbidden on `/admin/settings`.

---

#### Module 19: Immutable Audit Logs & Telemetry
- **Module ID:** `ADM-019`
- **Purpose:** Provide an irrevocable, read-only audit ledger recording every administrative authentication, data access, mutation, download, and export event for regulatory compliance.
- **User Roles:** Super Admin only.
- **Navigation Structure:** Settings & Security -> `Audit Logs` (`/admin/audit-logs`).
- **Screen List:**
  1. Audit Logs Ledger Table (`/admin/audit-logs`)
  2. Audit Event Detail Modal (`/admin/audit-logs/:id`)
- **Table Columns:** `Timestamp (UTC & AEST)`, `Actor Name / ID`, `Role`, `Action Code`, `Target Entity`, `Target ID`, `IP Address`, `User-Agent Summary`, `Details`.
- **Search & Filter Controls:** Search by actor, IP, target ID; filter by Action Category (Auth, CRUD, Export, Security); filter by Date Range.
- **CRUD Operations:** Strictly Read-Only. (No update or delete operations exist in code or database schema).
- **Bulk Actions:** None.
- **Workflow Transitions:** Append-Only Immutable Ledger.
- **API Dependencies:** `GET /api/v1/admin/audit-logs`, `GET /api/v1/admin/audit-logs/:id`, `GET /api/v1/admin/audit-logs/export`.
- **Validation Requirements:** Read query parameters validated.
- **Export Permissions:** Super Admin only (Signed CSV export for external compliance auditors).
- **Audit Events:** `AUDIT_LOGS_ACCESSED`, `AUDIT_LOGS_EXPORTED`.
- **Error & Empty States:** "No audit events recorded for the selected filter window."
- **Data Privacy Considerations:** Contains administrative IP addresses and activity traces; strictly restricted to Super Admin.
- **Acceptance Criteria:**
  1. No API endpoint or database trigger allows updating or deleting rows in `audit_logs`.
  2. Every login, document download, and data export creates an unalterable log record.
- **Test Scenarios:**
  - `TEST-ADM-019`: Attempt SQL `DELETE FROM audit_logs`; assert database privilege restriction or absence of application deletion mechanism.

---

### Module Summary & RBAC Authority Matrix

| Module ID | Module Title | Super Admin | Website Admin | Content Editor | BD Manager | HR Manager | Phase Owner |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **ADM-001** | Dashboard Overview | FULL | FULL | CONTENT ONLY | CRM ONLY | HR ONLY | Phase 25 |
| **ADM-002** | Auth & Session Gate | FULL | FULL | FULL | FULL | FULL | Phase 6 |
| **ADM-003** | User & RBAC Manager | **FULL** | NO | NO | NO | NO | Phase 6 / 26 |
| **ADM-004** | Page Management | FULL | FULL | FULL | NO | NO | Phase 7 / 26 |
| **ADM-005** | Hero & Homepage | FULL | FULL | FULL | NO | NO | Phase 7 / 26 |
| **ADM-006** | Services Catalog | FULL | FULL | FULL | NO | NO | Phase 8 / 26 |
| **ADM-007** | Industries Catalog | FULL | FULL | FULL | NO | NO | Phase 9 / 26 |
| **ADM-008** | Media Library | FULL | FULL | FULL | NO | NO | Phase 10 / 26 |
| **ADM-009** | Contact Enquiries | FULL | FULL | NO | **FULL** | NO | Phase 11 / 26 |
| **ADM-010** | Partnership Pipeline| FULL | NO | NO | **FULL** | NO | Phase 12 / 26 |
| **ADM-011** | Proposal Review Desk| FULL | NO | NO | **FULL** | NO | Phase 12 / 26 |
| **ADM-012** | Email Templates | **FULL** | FULL | NO | NO | NO | Phase 13 / 26 |
| **ADM-013** | Careers & Postings | FULL | FULL | NO | NO | **FULL** | Phase 14 / 26 |
| **ADM-014** | Applications & Resumes| FULL | NO | NO | NO | **FULL** | Phase 14 / 26 |
| **ADM-015** | Case Studies & Proof| FULL | FULL | FULL | NO | NO | Phase 15 / 26 |
| **ADM-016** | Insights & Articles | FULL | FULL | FULL | NO | NO | Phase 15 / 26 |
| **ADM-017** | SEO Management | **FULL** | FULL | NO | NO | NO | Phase 16 / 26 |
| **ADM-018** | System Settings | **FULL** | NO | NO | NO | NO | Phase 7 / 26 |
| **ADM-019** | Audit Logs & Telemetry| **FULL** | NO | NO | NO | NO | Phase 17 / 26 |
