# Complete 19 Admin Module UX/UI Specifications
## Voak Health Care — Administrative Portal Design System

**Document Reference:** `docs/phase-03/08_ALL_19_ADMIN_MODULE_DESIGNS.md`  
**Phase:** Phase 3 of 31 — Professional UI/UX Design (Reconciled Baseline)  
**Total Modules Covered:** Exactly 19 Administrative Modules (`ADM-01` to `ADM-19`)  
**Functional Alignment:** Phase 1 Admin Module Specifications (`05_ADMIN_19_MODULE_SPECIFICATIONS.md`), Complete RBAC Permission Matrix (`06_COMPLETE_RBAC_PERMISSION_MATRIX.md`), Database Schemas (`05_DATABASE_SCHEMA_AND_MIGRATIONS.md`), and Architecture Contracts (`06_AUTHENTICATION_RBAC_AND_SESSION_ARCHITECTURE.md`)  
**Visual Benchmark:** Approved Admin Reference Portal (`Screenshot_2026-10-16_073635.png` / `references/approved_admin_reference_portal.png`)

---

### Master Admin Navigation & RBAC Entitlements

The administrative architecture enforces strict least privilege across exactly **five canonical roles**:
1. **Super Admin (`ROLE_SUPER_ADMIN`)**: Unrestricted authority across all 19 modules, user provisioning, and audit logs.
2. **Website Administrator (`ROLE_WEBSITE_ADMIN`)**: CMS operations, media, SEO, general inquiry triage. Blocked from commercial tenders and candidate resumes.
3. **Content Editor (`ROLE_CONTENT_EDITOR`)**: Editorial copy, articles, static pages, media library. Zero access to CRM pipelines, user management, or system settings.
4. **Business Development Manager (`ROLE_BD_MANAGER`)**: Inbound proposals, partnerships, enterprise CRM. Zero access to candidate resumes or CMS publishing.
5. **HR Manager (`ROLE_HR_MANAGER`)**: Clinical recruitment, job postings, candidate applications, and resume vault. Zero access to commercial proposals or CMS settings.

```
ADM-01: Executive Dashboard & Operational Telemetry [FR-ADM-001]
ADM-02: Authentication, MFA & Active Session Governance [FR-ADM-002, FR-ADM-003]
ADM-03: Users & Role-Based Access Control (RBAC Matrix) [FR-ADM-004, FORM-ADM-08]
ADM-04: Static Page CMS & Revision Management [FR-ADM-005, FORM-ADM-04]
ADM-05: Homepage Section & Visual Bento Configurator [FR-ADM-006]
ADM-06: Clinical & Operational Services Management [FR-ADM-007, FORM-ADM-05]
ADM-07: Healthcare Industries & Sectors Management [FR-ADM-008]
ADM-08: Media Asset Library & File Storage Governance [FR-ADM-009, FORM-ADM-06]
ADM-09: Contact Enquiries & Triage Operations Desk [FR-ADM-010, FR-WF-001]
ADM-10: Institutional Partnerships & Alliance Pipeline [FR-ADM-011, FR-WF-002]
ADM-11: Enterprise Proposal & RFP Management Desk [FR-ADM-012, FR-WF-003]
ADM-12: Email Notification Templates & Outbox Queue [FR-ADM-013, FORM-ADM-07]
ADM-13: Careers Requisitions & Clinical Vacancy Management [FR-ADM-014]
ADM-14: Candidate Applications & Clinician Pipeline [FR-ADM-015, FR-WF-004]
ADM-15: Case Studies & Client Testimonial Governance [FR-ADM-016]
ADM-16: Insights, Articles & Clinical Thought Leadership [FR-ADM-017]
ADM-17: SEO Metadata, Open Graph & Sitemap Engine [FR-ADM-018, FR-WF-006]
ADM-18: System Security Settings & Configurable Entity [FR-ADM-018]
ADM-19: Immutable Audit Logs & Telemetry Monitoring [FR-ADM-019]
```

---

### Detailed Module-by-Module Specifications

#### 1. ADM-01: Executive Dashboard & Operational Telemetry
- **Role Access:** Super Admin, Website Administrator, Content Editor, Business Development Manager, HR Manager (Display is role-filtered via backend).
- **Layout & Structure:**
  - 4 Demo Metric Cards `[DEMO DATA ONLY — SIMULATED RECORDS]`:
    1. Active Clinical Deployments (24 Demo)
    2. Pending Triage Enquiries (12 Demo)
    3. Clinical Compliance Status (Satisfactory Demo)
    4. Active Proposals / RFPs (7 Demo)
  - Quick Triage Action Desk: Color-coded status transitions.
  - Live Queue Data Table: Columns (ID, Organization, Type, Sector, Date/Time, Status, Action).
  - System Telemetry Banner: Operational Primary Datacenter status pill.
- **Traceability:** Aligns with Phase 1 `FR-ADM-001`.

#### 2. ADM-02: Authentication, MFA & Active Session Governance
- **Role Access:** All authenticated admin staff (personal session view); Super Admin (system-wide sessions).
- **Layout & Structure:**
  - Active Sessions Data Table: IP address, Browser/OS, Login Timestamp, Idle Expiration Timer (15-minute countdown canonical policy), Revoke button.
  - MFA Enforcement Panel: QR Code setup for TOTP authenticator app, 8 single-use recovery backup codes display with copy/print trigger.
  - Session Termination Confirmation Dialog.
- **Forms Integrated:** `FORM-ADM-01` (Login), `FORM-ADM-02` (Password Recovery), `FORM-ADM-03` (MFA TOTP).
- **Traceability:** Aligns with Phase 1 `FR-ADM-002`, `FR-ADM-003`.

#### 3. ADM-03: Users & Role-Based Access Control (RBAC Matrix)
- **Role Access:** Super Admin only.
- **Layout & Structure:**
  - User Directory Table: Name, Email, Canonical Role Key, MFA Status, Last Active, Actions (Edit, Suspend, Reset MFA).
  - Canonical 5 Admin Roles Enforced: Super Admin, Website Admin, Content Editor, BD Manager, HR Manager.
  - RBAC Permission Matrix Viewer: 45 canonical permissions grouped by category (Content, Operations, Governance, System) with read-only/edit toggles per role.
  - User Provisioning Drawer (`FORM-ADM-08`): Full Name, Email, Role Selection, Status Toggle.
- **Traceability:** Aligns with Phase 1 `FR-ADM-004` and `06_COMPLETE_RBAC_PERMISSION_MATRIX.md`.

#### 4. ADM-04: Static Page CMS & Revision Management
- **Role Access:** Super Admin, Website Administrator, Content Editor.
- **Layout & Structure:**
  - Page Registry Table: Page Title, Slug, Last Modified, Author, Publication Status (Published, Draft).
  - Revision History Drawer: Side-by-side diff comparison, Rollback button.
  - Page Content Editor (`FORM-ADM-04`): Page headline, lead copy, section blocks reordering via drag handle, concurrency lock timestamp (`last_updated_at`).
- **Traceability:** Aligns with Phase 1 `FR-ADM-005`.

#### 5. ADM-05: Homepage Section & Visual Bento Configurator
- **Role Access:** Super Admin, Website Administrator, Content Editor.
- **Layout & Structure:**
  - Reorderable Section Tree: 15 functional sections with visibility toggle switches.
  - Hero Copy & Asset Selector: Update headline, subtitle, and select approved healthcare professional portrait.
  - Bento Layout Grid Configuration: Assign cards to 8-col or 4-col positions and select background tints (Pink, Yellow, Mint, Sky Blue).
- **Traceability:** Aligns with Phase 1 `FR-ADM-006`.

#### 6. ADM-06: Clinical & Operational Services Management
- **Role Access:** Super Admin, Website Administrator, Content Editor.
- **Layout & Structure:**
  - Service Directory Grid: Service Name, Category, SLA Tier, Status.
  - Service Editor Modal (`FORM-ADM-05`): Service Title, Slug, Clinical Scope Textarea, Deliverables List, Mapped Industries. Automated 301 redirect generation upon slug change.
- **Traceability:** Aligns with Phase 1 `FR-ADM-007`.

#### 7. ADM-07: Healthcare Industries & Sectors Management
- **Role Access:** Super Admin, Website Administrator, Content Editor.
- **Layout & Structure:**
  - Sector Directory Table: Industry Name, Facility Types, Associated Services Count, Status.
  - Regulatory Standard Mapping: Link healthcare quality frameworks to specific sectors.
- **Traceability:** Aligns with Phase 1 `FR-ADM-008`.

#### 8. ADM-08: Media Asset Library & File Storage Governance
- **Role Access:** Super Admin, Website Administrator, Content Editor.
- **Layout & Structure:**
  - Drag-and-Drop Dropzone (`FORM-ADM-06`): File size validation (15MB ceiling), MIME type check (PNG, JPG, WebP, SVG, PDF).
  - Media Grid with Thumbnail Previews: File name, file size, dimensions, upload date, copy URL button.
  - Asset Detail Drawer: Alt text editor (mandatory for WCAG AA compliance), caption editor, delete confirmation.
- **Traceability:** Aligns with Phase 1 `FR-ADM-009`.

#### 9. ADM-09: Contact Enquiries & Triage Operations Desk
- **Role Access:** Super Admin, Website Administrator, Business Development Manager.
- **Layout & Structure:**
  - Triage Queue Table: Inquiry ID, Full Name, Organization, Inquiry Category, Urgency Chip (Normal, Urgent), Status Chip (New, Triaged, Responded, Closed).
  - Inquiry Detail Drawer: Full inquiry body, international phone number, internal triage notes textarea, status transition dropdown button (`FR-WF-001`).
- **Traceability:** Aligns with Phase 1 `FR-ADM-010`, `FR-WF-001`.

#### 10. ADM-10: Institutional Partnerships & Alliance Pipeline
- **Role Access:** Super Admin, Business Development Manager. (Strictly restricted; zero access to Editors or HR).
- **Layout & Structure:**
  - Partner Pipeline Kanban / Table: Organization, Partnership Tier, Agreement Status (Initial, Review, Terms, Active), Assigned BD Lead.
  - SLA Compliance Card: Audited workforce parameters per partner.
- **Traceability:** Aligns with Phase 1 `FR-ADM-011`, `FR-WF-002`.

#### 11. ADM-11: Enterprise Proposal & RFP Management Desk
- **Role Access:** Super Admin, Business Development Manager. (Strictly restricted; zero access to Editors or HR).
- **Layout & Structure:**
  - Proposal Desk Table: RFP Reference ID, Submitting Healthcare Facility, Required Roster Volume, Estimated Value, Attached Documents, Workflow State (`FR-WF-003`).
  - RFP Inspection View: Download attached tender specification files via authenticated binary stream from private quarantine storage (`storage/uploads/proposals/`).
- **Traceability:** Aligns with Phase 1 `FR-ADM-012`, `FR-WF-003`.

#### 12. ADM-12: Email Notification Templates & Outbox Queue
- **Role Access:** Super Admin, Website Administrator.
- **Layout & Structure:**
  - Template Catalog: Inquiry Acknowledgment, RFP Submission Confirmation, Admin Triage Alert, User Password Reset.
  - Template Editor (`FORM-ADM-07`): Subject line, Mandatory placeholder tokens (`{{contact_name}}`, `{{ref_no}}`, `{{legal_entity_name}}`), HTML body preview.
  - Outbox Queue Table: Recipient, Template, Dispatched Timestamp, Delivery Status (Sent, Failed, Queued).
- **Traceability:** Aligns with Phase 1 `FR-ADM-013`.

#### 13. ADM-13: Careers Requisitions & Clinical Vacancy Management
- **Role Access:** Super Admin, Website Administrator, HR Manager.
- **Layout & Structure:**
  - Requisition Table: Job Title, Clinical Department, Shift Schedule, Applicants Count, Publishing Status (Active, Draft, Closed).
  - Job Editor: Role Overview, Professional Registration Prerequisites, Shift Schedule, Application Deadline.
- **Traceability:** Aligns with Phase 1 `FR-ADM-014`.

#### 14. ADM-14: Candidate Applications & Clinician Pipeline
- **Role Access:** Super Admin, HR Manager. (Strictly restricted; zero access to BD Manager or Content Editors).
- **Layout & Structure:**
  - Applicant Pipeline: Candidate Name, Clinical Specialty, Professional Registration ID, Application Date, Stage (`FR-WF-004`: Applied, Screening, Credential Verified, Interview, Offer, Rejected).
  - Candidate Drawer: Resume viewer, credential verification checklist, interview notes. Authenticated binary download from private quarantine (`storage/uploads/resumes/`).
- **Traceability:** Aligns with Phase 1 `FR-ADM-015`, `FR-WF-004`.

#### 15. ADM-15: Case Studies & Client Testimonial Governance
- **Role Access:** Super Admin, Website Administrator, Content Editor.
- **Layout & Structure:**
  - Case Study Catalog: Title, Sector, Client Consent Release Status Badge (Approved, Pending Sign-off).
  - Content Editor: Challenge summary, Implemented Solution, Verified Outcomes. Strict policy: Mandatory signed consent release required before publishing.
- **Traceability:** Aligns with Phase 1 `FR-ADM-016`.

#### 16. ADM-16: Insights, Articles & Clinical Thought Leadership
- **Role Access:** Super Admin, Website Administrator, Content Editor.
- **Layout & Structure:**
  - Editorial Grid: Article Title, Author, Reading Time, Category, Publication Date, Status.
  - Rich Text Editor: Markdown/HTML authoring, featured image selector from ADM-08, excerpt summary, citation footnotes.
- **Traceability:** Aligns with Phase 1 `FR-ADM-017`.

#### 17. ADM-17: SEO Management, Open Graph & Sitemap Engine
- **Role Access:** Super Admin, Website Administrator.
- **Layout & Structure:**
  - Page-by-Page SEO Table: URL Slug, Meta Title (60 chars max), Meta Description (155 chars max), Indexing Status (`index, follow`).
  - Google SERP Simulator: Live preview showing desktop and mobile search snippet.
  - Sitemap Generator: Trigger `sitemap.xml` build (`FR-WF-006`).
- **Traceability:** Aligns with Phase 1 `FR-ADM-018`, `FR-WF-006`.

#### 18. ADM-18: System Security Settings & Configurable Entity
- **Role Access:** Super Admin only.
- **Layout & Structure:**
  - Security Controls: Idle Session Timeout (canonical 15 minutes), Rate Limiting Thresholds (5 login attempts / 15 mins), Allowed CORS Origins, Maintenance Mode Toggle.
  - Entity Configuration: Configurable legal corporate name, regional headquarters address, and default country calling code.
- **Traceability:** Aligns with Phase 1 `FR-ADM-018`.

#### 19. ADM-19: Immutable Audit Logs & Telemetry Monitoring
- **Role Access:** Super Admin only.
- **Layout & Structure:**
  - Immutable Log Table: Timestamp (UTC), Actor Email, Event Code (`AUTH_LOGIN_SUCCESS`, `RFP_STATUS_TRANSITION`, `USER_SUSPENDED`), Target Resource ID, Client IP Address, Outcome Chip (Success, Blocked, Failed).
  - Cryptographic Chain: Append-only SHA-256 chain verification. 90-day retention policy.
- **Traceability:** Aligns with Phase 1 `FR-ADM-019`.
