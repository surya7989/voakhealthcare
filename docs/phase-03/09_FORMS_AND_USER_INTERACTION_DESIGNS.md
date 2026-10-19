# Complete Form Experience & User Interaction Design Catalog
## Voak Health Care — Corporate Digital Brand Guidelines

**Document Reference:** `docs/phase-03/09_FORMS_AND_USER_INTERACTION_DESIGNS.md`  
**Phase:** Phase 3 of 31 — Professional UI/UX Design (Reconciled Baseline)  
**Total Canonical Forms Covered:** Exactly 12 System Forms (4 Public, 8 Admin)  
**Functional Alignment:** Phase 1 Form Fields & Validation Catalog (`07_FORM_FIELDS_AND_VALIDATION_CATALOG.md`)  
**Accessibility Target:** WCAG 2.2 AA (Labels, Focus Traps, Inline Error Messages, Mobile Keyboard Types)

---

### Master Form Inventory & Canonical Taxonomy

```
PUBLIC FORMS:
FORM-PUB-01: General Corporate Contact Form (/contact, PAGE-PUB-17) [FR-PUB-019]
FORM-PUB-02: Strategic Partnership Intake Form (/partner-with-us, PAGE-PUB-18) [FR-PUB-020]
FORM-PUB-03: Multi-Step Enterprise Proposal / RFP Wizard (/request-proposal, PAGE-PUB-19) [FR-PUB-021]
FORM-PUB-04: Careers Candidate Application Form (/careers/:slug, PAGE-PUB-13) [FR-PUB-015]

ADMINISTRATIVE FORMS:
FORM-ADM-01: Administrative Login Form (/admin/login, ADM-02) [FR-ADM-002]
FORM-ADM-02: Password Recovery Request Form (/admin/password-recover, ADM-02) [FR-ADM-002]
FORM-ADM-03: Multi-Factor Authentication (TOTP) Challenge Form (/admin/mfa, ADM-02) [FR-ADM-003]
FORM-ADM-04: Static Page Content Editor Form (/admin/pages/:id/edit, ADM-04) [FR-ADM-005]
FORM-ADM-05: Healthcare Service Management Form (/admin/services/create, ADM-06) [FR-ADM-007]
FORM-ADM-06: Media & Document Asset Upload Form (/admin/media/upload, ADM-08) [FR-ADM-009]
FORM-ADM-07: Transactional Email Template Editor Form (/admin/email-templates/:id, ADM-12) [FR-ADM-013]
FORM-ADM-08: User Account & Role Provisioning Form (/admin/users/create, ADM-03) [FR-ADM-004]

PROPOSED / FUTURE (OUT OF SCOPE):
[PROPOSED] Healthcare Newsletter Subscription: Marked PROPOSED and kept outside approved Phase 1-3 implementation scope.
```

---

### Detailed Form Specifications & Interaction Flows

#### 1. FORM-PUB-01: General Corporate Contact Form
- **Location:** `/contact` (`PAGE-PUB-17`) and Homepage (`PAGE-PUB-01`).
- **Fields:**
  1. *Full Name:* Text input, required, min 2, max 100 chars. Label: "Full Name *".
  2. *Healthcare Organization:* Text input, required, min 2, max 150 chars. Label: "Organization / Facility *".
  3. *Country Code:* Select dropdown, required (ISO 3166-1 alpha-2 code).
  4. *Corporate Work Email:* Email input (`type="email"`, `inputmode="email"`), required, max 100 chars. Disposable domains blocked.
  5. *Phone Number:* Tel input (`type="tel"`, `inputmode="tel"`), required (International format: `^\+?[0-9\s\-()]{7,25}$`).
  6. *Inquiry Category:* Select dropdown (`GENERAL`, `SERVICES`, `COMPLIANCE`, `CAREERS`, `MEDIA`).
  7. *Message:* Textarea (min 20 chars, max 2000 chars).
  8. *Consent Checkbox:* "I consent to Voak Health Care handling this information pursuant to the Privacy Policy *".
  9. *Anti-Spam:* Cloudflare Turnstile token + hidden honeypot field.
- **States & Micro-interactions:**
  - *Focus:* 3px purple ring (`ring-2 ring-[#665CE9] ring-offset-2`).
  - *Inline Error:* Red border (`#B91C1C`), pink error background (`#FEE2E2`), inline SVG warning icon and text below input (`aria-describedby`).
  - *Submit Button:* Primary Purple button ("Submit Corporate Inquiry"). On submit: text replaced with spinning indicator, button disabled.
  - *Success State:* Replaces form with a white card displaying green checkmark badge: *"Thank you. Your inquiry has been received (Ref: INQ-YYYYMMDD-XXXX). Our triage desk will review your message."* (No invented turnaround promises).

#### 2. FORM-PUB-02: Strategic Partnership Intake Form
- **Location:** `/partner-with-us` (`PAGE-PUB-18`).
- **Fields:** Executive Name, Executive Title, Organization Name, Organization Type (`PUBLIC_HOSPITAL`, `PRIVATE_HOSPITAL`, `AGED_CARE_GROUP`, `ALLIED_HEALTH`, `ACADEMIC`, `OTHER`), Headquarters Country, Work Email, Phone (International Format), Partnership Scope Multi-select, Estimated Facility Scale, Collaboration Overview Textarea, Privacy Consent, Turnstile.
- **Workflow:** Routes directly into `ADM-10` Partnership Pipeline. Restricted to Super Admin and BD Manager.

#### 3. FORM-PUB-03: Multi-Step Enterprise Proposal / RFP Wizard
- **Location:** `/request-proposal` (`PAGE-PUB-19`) and global modal trigger.
- **Canonical 4-Step Architecture + Review:**
  - **Step 1: Facility & Executive Profile:** Organization Legal Name, Facility Type (`HOSPITAL_ACUTE`, `AGED_CARE`, `PRIMARY_CLINIC`, `DIAGNOSTIC_LAB`, `OTHER`), Country, Region/State, Lead Name, Lead Title, Lead Email, Lead Phone (International Format).
  - **Step 2: Service Scope & Scale:** Primary Clinical Needs Checkboxes, Estimated Scale Metric (e.g. shifts/week, ward bed count).
  - **Step 3: Procurement Parameters:** Target Deployment Start Date (must be `>= today`), Procurement Stage (`BUDGETARY_PLANNING`, `ACTIVE_TENDER`, `IMMEDIATE_REPLACEMENT`), Estimated Budget Band.
  - **Step 4: Project Brief & Tender Attachment:** Project Summary Brief Textarea (min 50, max 5000 chars), Optional Tender File Upload (Max 15MB: PDF, DOCX, XLSX with magic-byte validation and private quarantine storage), Privacy Consent Checkbox.
  - **Confirmation Screen:**
    - Unique generated Reference ID (e.g. `RFP-YYYYMMDD-XXXX`).
    - Confirmation notification: *"Your proposal has been logged to the Operations Desk. Our team will review your specifications according to standard procurement triage procedures."*

#### 4. FORM-PUB-04: Careers Candidate Application Form
- **Location:** `/careers/:slug` (`PAGE-PUB-13`).
- **Fields:** First Name, Last Name, Email, Phone (International Format), Professional Registration Number (Optional / where applicable to specific clinical role and confirmed jurisdiction), LinkedIn Profile URL (Optional), Cover Note (Optional), Resume File Upload (Mandatory, max 10MB PDF/DOCX, magic byte verified, stored in quarantined resume vault), Privacy Consent Checkbox, Turnstile.
- **Workflow:** Routes into `ADM-14` Candidate Applications Pipeline. Accessible solely to Super Admin and HR Manager.

#### 5. FORM-ADM-01: Administrative Login Form
- **Location:** `/admin/login` (`ADM-02`).
- **Fields:** Corporate Email Address, Password (min 12 chars), CSRF Synchronizer Token.
- **Rate Limiting:** 5 failed attempts locks the IP for 15 minutes. Bcrypt cost 12 verification.

#### 6. FORM-ADM-02: Password Recovery Request Form
- **Location:** `/admin/password-recover` (`ADM-02`).
- **Fields:** Corporate Email Address, CSRF Token.
- **Security Envelope:** Always returns HTTP 200 with generic notification to prevent account enumeration.

#### 7. FORM-ADM-03: Multi-Factor Authentication (TOTP) Challenge Form
- **Location:** `/admin/mfa` (`ADM-02`).
- **Fields:** Ephemeral temporary token, 6-digit numeric TOTP code (`inputmode="numeric"`, `pattern="^[0-9]{6}$"`), Optional 8-character recovery code. RFC 6238 time-step window validation (+- 30s).

#### 8. FORM-ADM-04: Static Page Content Editor Form
- **Location:** `/admin/pages/:id/edit` (`ADM-04`).
- **Fields:** Title, Headline, Content Markdown (Sanitized via DOMPurify), SEO Title, SEO Description, `last_updated_at` Concurrency Timestamp, CSRF Token.

#### 9. FORM-ADM-05: Healthcare Service Management Form
- **Location:** `/admin/services/create` and `/admin/services/:id/edit` (`ADM-06`).
- **Fields:** Title, URL Slug (Regex `^[a-z0-9]+(?:-[a-z0-9]+)*$`), Category Code, Summary Textarea, Content Markdown, Deliverables JSON list, Display Order, Published Toggle, CSRF Token. Automated 301 permanent redirect generation upon slug change.

#### 10. FORM-ADM-06: Media & Document Asset Upload Form
- **Location:** `/admin/media/upload` (`ADM-08`).
- **Fields:** Asset File (Images max 5MB, Documents max 15MB), Title, Mandatory Alt Text (Max 150 chars, WCAG 2.2 AA mandate), Asset Tag, CSRF Token. Auto-converts images to WebP.

#### 11. FORM-ADM-07: Transactional Email Template Editor Form
- **Location:** `/admin/email-templates/:id` (`ADM-12`).
- **Fields:** Subject Line, Body HTML with mandatory template placeholder integrity check (`{{ref_no}}`, `{{contact_name}}`, `{{legal_entity_name}}`), Is Active Toggle, CSRF Token.

#### 12. FORM-ADM-08: User Account & Role Provisioning Form
- **Location:** `/admin/users/create` and `/admin/users/:id/edit` (`ADM-03`).
- **Role Dropdown:** Strictly limited to the 5 Canonical Administrative Roles:
  1. `ROLE_SUPER_ADMIN` (Super Admin)
  2. `ROLE_WEBSITE_ADMIN` (Website Administrator)
  3. `ROLE_CONTENT_EDITOR` (Content Editor)
  4. `ROLE_BD_MANAGER` (Business Development Manager)
  5. `ROLE_HR_MANAGER` (HR Manager)
- **Status Options:** `ACTIVE`, `SUSPENDED`.
- **Security Guard:** Super Admin role required; system protects against removing the sole active Super Admin account.
