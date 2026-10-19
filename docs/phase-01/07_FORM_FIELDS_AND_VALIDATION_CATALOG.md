# Form Fields & Validation Catalog
## Complete Specification for All 12 Public & Administrative Forms

**Document Reference:** `docs/phase-01/07_FORM_FIELDS_AND_VALIDATION_CATALOG.md`  
**Phase:** Phase 1 of 31 — Complete Functional Specification (Corrected Baseline)  
**Baseline Authority:** Master Functional Requirements (`01_MASTER_FUNCTIONAL_REQUIREMENTS.md`) and Technical Architecture (`07_TECHNICAL_ARCHITECTURE.md`).

---

### Global Form Architecture & Validation Rules

1. **Backend Authoritative Validation:**  
   Client-side React validation (via React Hook Form + Zod) is strictly an ergonomic UX layer providing instant feedback. **The PHP backend validation pipeline (`src/Validation/Validator.php`) is authoritative for all data persistence and security checks.**
2. **Internationalized Phone & Geographic Validation:**  
   Because Voak Health Care's commercial jurisdictions remain subject to client confirmation, forms do NOT enforce Australian-only phone formatting or state restrictions globally. All telephone inputs support **international calling codes (E.164 standard)** with flexible, robust validation (`^\+[1-9]\d{1,14}$` or `^\+?[0-9\s\-()]{7,25}$`). Country and region selectors default to configurable system baselines with full international option support.
3. **Standardized Error Response Envelope (RFC 7807 inspired):**
   ```json
   {
     "status": "fail",
     "code": 422,
     "message": "Validation failed for one or more submission fields.",
     "errors": {
       "work_email": ["The corporate email address provided is invalid or contains an unpermitted disposable domain."],
       "phone": ["Please provide a valid telephone number including international country calling code if outside primary region."]
     }
   }
   ```
4. **Spam & Abuse Defense:**  
   - All public forms integrate Cloudflare Turnstile token verification (`cf-turnstile-response`).
   - CSS-hidden honeypot fields (`website_verification_code`) detect and silently discard automated bots.
   - IP rate limiting enforced via database/file token bucket.
5. **Data Privacy & Non-Clinical Guardrail:**  
   Under no circumstances shall any form solicit or store individual patient medical records, health histories, or Medicare/insurance card numbers.

---

### The 12 System Forms Catalog

```
Index of Forms:
Form A: General Corporate Contact Form (Public)
Form B: Strategic Partnership Intake Form (Public)
Form C: Multi-Step Request a Proposal (RFP) Engine (Public)
Form D: Careers Candidate Application Form (Public)
Form E: Administrative Login Form (Admin)
Form F: Password Recovery Request Form (Admin)
Form G: Multi-Factor Authentication (TOTP) Challenge Form (Admin)
Form H: Content & Static Page Editor Form (Admin)
Form I: Healthcare Service Management Form (Admin)
Form J: Media & Document Asset Upload Form (Admin)
Form K: Transactional Email Template Editor Form (Admin)
Form L: User Account & Role Provisioning Form (Admin)
```

---

#### Form A: General Corporate Contact Form
- **Form Identifier:** `FORM-PUB-01`
- **Route / Context:** Embedded on `/contact` (`PAGE-PUB-17`).
- **Fields Definition:**
  1. `full_name` | String | Required | Min 2, Max 100 chars | Sanitized via `trim()`, `strip_tags()`
  2. `organization_name` | String | Required | Min 2, Max 150 chars | Corporate/facility entity name
  3. `country_code` | Select | Required | Default configurable (e.g., `AU` / `US` / `GB` / `NZ`) | ISO 3166-1 alpha-2 code
  4. `work_email` | Email | Required | Max 100 chars | Strict RFC 5322 validation; blocking generic disposables
  5. `phone` | String | Required | Min 7, Max 25 chars | International format: `^\+?[0-9\s\-()]{7,25}$` (supports international calling codes)
  6. `inquiry_type` | Enum | Required | Options: `GENERAL`, `SERVICES`, `COMPLIANCE`, `CAREERS`, `MEDIA`
  7. `message` | Textarea | Required | Min 20, Max 2000 chars | Plaintext sanitization
  8. `consent_privacy` | Boolean | Required | Must be `true` (Checkbox)
  9. `website_verification_code` | Honeypot | Optional | Must be empty
  10. `cf_turnstile_token` | Token | Required | Verified via Cloudflare Siteverify API
- **Cross-Field Dependencies:** None.
- **File Uploads:** Disallowed on general contact form.
- **Submission Flow:** POST to `/api/v1/public/contact`. Returns `201 Created` with `reference_number`.
- **Duplicate Submission Prevention:** Client disables submit button on click; server hashes `(ip + email + message)` with 60-second duplicate rejection window.
- **Rate Limiting:** Max 5 submissions per IP per 60 minutes.
- **Spam Protection:** Turnstile + Honeypot.
- **Error Messages:** Inline field alerts below each input (`aria-describedby`).
- **Success Messages:** Card replacement with reference code (`INQ-YYYYMMDD-XXXX`) and estimated 1-business-day response time.
- **Retry Behavior:** Retains user inputs in form state on 422 error; auto-clears on 201 success.
- **Database Persistence:** Inserts record into `contact_inquiries` table with status `NEW`.
- **Notification Events:** Triggers `NOTIF_CONTACT_ACK` (to inquirer) and `NOTIF_CONTACT_INTERNAL` (to triage team).
- **Accessibility:** Labels associated with `for=""`; errors marked `role="alert"`.

---

#### Form B: Strategic Partnership Intake Form
- **Form Identifier:** `FORM-PUB-02`
- **Route / Context:** Embedded on `/partner-with-us` (`PAGE-PUB-18`).
- **Fields Definition:**
  1. `executive_name` | String | Required | Max 100 chars
  2. `executive_title` | String | Required | Max 100 chars (e.g., Chief Executive Officer, Operations Lead)
  3. `organization_name` | String | Required | Max 150 chars
  4. `organization_type` | Enum | Required | Options: `PUBLIC_HOSPITAL`, `PRIVATE_HOSPITAL`, `AGED_CARE_GROUP`, `ALLIED_HEALTH`, `ACADEMIC`, `OTHER`
  5. `headquarters_country` | Select | Required | ISO 3166-1 alpha-2 code
  6. `work_email` | Email | Required | Max 100 chars | Corporate email enforced
  7. `phone` | String | Required | Min 7, Max 25 chars | International format `^\+?[0-9\s\-()]{7,25}$`
  8. `partnership_scope` | Array of Enums | Required | Min 1 selection: `WORKFORCE_DEPLOYMENT`, `FACILITY_MANAGEMENT`, `CO_SOURCING`, `STRATEGIC_ADVISORY`
  9. `estimated_scale` | Enum | Required | Options: `SINGLE_FACILITY`, `2_TO_5_FACILITIES`, `6_TO_15_FACILITIES`, `STATEWIDE_ENTERPRISE`, `NATIONAL_NETWORK`
  10. `collaboration_overview` | Textarea | Required | Min 50, Max 3000 chars
  11. `consent_privacy` | Boolean | Required | Must be `true`
  12. `cf_turnstile_token` | Token | Required
- **Submission Flow:** POST to `/api/v1/public/partnerships/inquire`. Returns `201 Created` with reference `PTN-YYYYMMDD-XXXX`.
- **Rate Limiting:** Max 3 submissions per IP per 60 minutes.
- **Database Persistence:** Inserts into `partnership_inquiries` table with status `NEW`.
- **Notification Events:** High-priority internal notification to BD Manager.

---

#### Form C: Multi-Step Request a Proposal (RFP) Engine
- **Form Identifier:** `FORM-PUB-03`
- **Route / Context:** Embedded on `/request-proposal` (`PAGE-PUB-19`).
- **Step Architecture:** 4-Step Wizard.
- **Fields Definition:**
  - **Step 1: Facility & Executive Profile**
    1. `organization_name` | String | Required | Max 150 chars
    2. `facility_type` | Enum | Required | Options: `HOSPITAL_ACUTE`, `AGED_CARE`, `PRIMARY_CLINIC`, `DIAGNOSTIC_LAB`, `OTHER`
    3. `facility_country` | Select | Required | Default configurable ISO country
    4. `facility_region_state` | String | Required | Max 100 chars (State, Province, or Region)
    5. `lead_name` | String | Required | Max 100 chars
    6. `lead_title` | String | Required | Max 100 chars
    7. `lead_email` | Email | Required | Max 100 chars
    8. `lead_phone` | String | Required | Min 7, Max 25 chars | International format `^\+?[0-9\s\-()]{7,25}$`
  - **Step 2: Service Scope & Scale**
    9. `service_ids` | Array of Ints | Required | Min 1 valid service ID from `services` table
    10. `estimated_scale_metric` | String | Optional | Max 100 chars (e.g., "120-bed facility, 45 clinical shifts/week")
  - **Step 3: Procurement Parameters**
    11. `target_start_date` | Date | Required | Must be current date or future date (`>= today()`)
    12. `procurement_stage` | Enum | Required | Options: `BUDGETARY_PLANNING`, `ACTIVE_TENDER`, `IMMEDIATE_REPLACEMENT`
    13. `budget_band` | Enum | Required | Options: `TIER_1_UNDER_100K`, `TIER_2_100K_TO_500K`, `TIER_3_500K_TO_2M`, `TIER_4_ABOVE_2M`, `UNDISCLOSED`
  - **Step 4: Project Brief & Attachment**
    14. `project_brief` | Textarea | Required | Min 50, Max 5000 chars
    15. `tender_document` | File Upload | Optional | Max 15MB. Permitted: PDF (`.pdf`), Word (`.docx`), Excel (`.xlsx`)
    16. `nda_required` | Boolean | Optional | Default `false`
    17. `consent_privacy` | Boolean | Required | Must be `true`
    18. `cf_turnstile_token` | Token | Required
- **File Upload Restrictions:**
  - MIME type check: `application/pdf`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`.
  - File signature (magic bytes) validated in PHP (`finfo_file`).
  - Quarantined in `storage/uploads/proposals/` with UUID filename (`{uuid}.bin`); zero execution permissions (`0600`).
- **Submission Flow:** POST multipart form-data to `/api/v1/public/proposals/submit`. Returns `201 Created` with reference `RFP-YYYYMMDD-XXXX`.
- **Database Persistence:** Inserts record into `proposals` table.

---

#### Form D: Careers Candidate Application Form
- **Form Identifier:** `FORM-PUB-04`
- **Route / Context:** Embedded on `/careers/:slug` (`PAGE-PUB-13`).
- **Fields Definition:**
  1. `job_id` | Integer | Required | Foreign key to `job_postings.id` (Must be active job)
  2. `first_name` | String | Required | Max 50 chars
  3. `last_name` | String | Required | Max 50 chars
  4. `email` | Email | Required | Max 100 chars
  5. `phone` | String | Required | Min 7, Max 25 chars | International format `^\+?[0-9\s\-()]{7,25}$`
  6. `professional_registration_number` | String | Optional | Max 50 chars (e.g., AHPRA registration number for Australian clinical roles, or relevant national medical board registration)
  7. `linkedin_url` | URL | Optional | Max 255 chars
  8. `cover_letter` | Textarea | Optional | Max 3000 chars
  9. `resume_file` | File Upload | Required | Max 10MB. Permitted: PDF (`.pdf`), Word (`.docx`). Magic byte verification.
  10. `consent_privacy` | Boolean | Required | Must be `true`
  11. `cf_turnstile_token` | Token | Required
- **Storage:** Quarantined in `storage/uploads/resumes/{uuid}.bin` with permission `0600`.
- **Submission Flow:** POST multipart to `/api/v1/public/careers/apply`. Returns `201 Created`.
- **Notification Events:** Sends candidate confirmation email and internal alert to HR Manager.

---

#### Form E: Administrative Login Form
- **Form Identifier:** `FORM-ADM-01`
- **Route / Context:** `/admin/login` (`PAGE-ADM-02`).
- **Fields Definition:**
  1. `email` | Email | Required | Max 100 chars
  2. `password` | Password | Required | Min 12, Max 128 chars
  3. `csrf_token` | CSRF Token | Required | Session synchronizer token
- **Validation Constraints:** Rate limited at 5 failed attempts per IP/Account per 15 minutes.
- **Server Response:** Returns `200 OK` with JSON indicating whether MFA is required:
  ```json
  { "status": "mfa_required", "temp_token": "tmp_9a8f..." }
  ```
  or sets auth cookie if MFA already completed/disabled.

---

#### Form F: Password Recovery Request Form
- **Form Identifier:** `FORM-ADM-02`
- **Route / Context:** `/admin/password-recover`.
- **Fields Definition:**
  1. `email` | Email | Required | Max 100 chars
  2. `csrf_token` | CSRF Token | Required
- **Server Response:** Always returns `200 OK` with message: "If an active account exists for this address, password reset instructions have been dispatched." (Prevents account enumeration).

---

#### Form G: Multi-Factor Authentication (TOTP) Challenge Form
- **Form Identifier:** `FORM-ADM-03`
- **Route / Context:** `/admin/mfa-verify`.
- **Fields Definition:**
  1. `temp_token` | String | Required | Ephemeral token issued during credentials verification (expires in 5 mins)
  2. `totp_code` | Numeric String | Required | Exactly 6 digits (`^[0-9]{6}$`)
  3. `recovery_code` | String | Optional | 8-character hex recovery code if TOTP authenticator is unavailable
- **Validation Constraints:** Verifies against RFC 6238 TOTP window (+- 1 time step / 30 seconds).

---

#### Form H: Content & Static Page Editor Form
- **Form Identifier:** `FORM-ADM-04`
- **Route / Context:** `/admin/pages/:id/edit`.
- **Fields Definition:**
  1. `title` | String | Required | Max 120 chars
  2. `headline` | String | Optional | Max 150 chars
  3. `content_markdown` | Text | Required | Sanitized via DOMPurify
  4. `seo_title` | String | Optional | Max 70 chars
  5. `seo_description` | String | Optional | Max 160 chars
  6. `csrf_token` | CSRF Token | Required
- **Concurrency Control:** Requires `last_updated_at` timestamp. Rejects with `409 Conflict` if another editor saved changes in the interim.

---

#### Form I: Healthcare Service Management Form
- **Form Identifier:** `FORM-ADM-05`
- **Route / Context:** `/admin/services/create` and `/admin/services/:id/edit`.
- **Fields Definition:**
  1. `title` | String | Required | Max 120 chars
  2. `slug` | String | Required | Max 140 chars, Regex: `^[a-z0-9]+(?:-[a-z0-9]+)*$`
  3. `category_code` | Enum | Required | Options: `WORKFORCE`, `OPERATIONS`, `ADMINISTRATION`, `GOVERNANCE`, `LOGISTICS`, `ADVISORY`
  4. `summary` | Textarea | Required | Max 250 chars
  5. `content_markdown` | Text | Required
  6. `deliverables` | JSON Array of Strings | Required | Min 1 deliverable
  7. `sla_framework` | JSON Array of Objects | Optional | `[ { "metric": "...", "target": "..." } ]`
  8. `display_order` | Integer | Required | Default 0
  9. `is_featured` | Boolean | Required | Default `false`
  10. `is_published` | Boolean | Required | Default `false`
  11. `industry_ids` | Array of Integers | Optional | Mapped target industries
  12. `csrf_token` | CSRF Token | Required

---

#### Form J: Media & Document Asset Upload Form
- **Form Identifier:** `FORM-ADM-06`
- **Route / Context:** `/admin/media/upload`.
- **Fields Definition:**
  1. `asset_file` | File Upload | Required | Max 5MB (images) / 15MB (documents). Permitted: JPG, PNG, WebP, SVG, PDF.
  2. `title` | String | Required | Max 100 chars
  3. `alt_text` | String | Required for images | Max 150 chars (WCAG 2.2 AA mandate)
  4. `asset_tag` | Enum | Required | Options: `HERO`, `SERVICES`, `INDUSTRIES`, `ARTICLES`, `GOVERNANCE`
  5. `csrf_token` | CSRF Token | Required

---

#### Form K: Transactional Email Template Editor Form
- **Form Identifier:** `FORM-ADM-07`
- **Route / Context:** `/admin/email-templates/:id`.
- **Fields Definition:**
  1. `subject_line` | String | Required | Max 150 chars
  2. `body_html` | Text | Required | Must contain mandatory placeholders (e.g., `{{ref_no}}`, `{{contact_name}}`, `{{legal_entity_name}}`)
  3. `is_active` | Boolean | Required | Toggle template activation
  4. `csrf_token` | CSRF Token | Required

---

#### Form L: User Account & Role Provisioning Form
- **Form Identifier:** `FORM-ADM-08`
- **Route / Context:** `/admin/users/create` and `/admin/users/:id/edit`.
- **Fields Definition:**
  1. `full_name` | String | Required | Max 100 chars
  2. `email` | Email | Required | Max 100 chars, corporate domain required
  3. `role` | Enum | Required | Options: `ROLE_SUPER_ADMIN`, `ROLE_WEBSITE_ADMIN`, `ROLE_CONTENT_EDITOR`, `ROLE_BD_MANAGER`, `ROLE_HR_MANAGER`
  4. `status` | Enum | Required | Options: `ACTIVE`, `SUSPENDED`
  5. `csrf_token` | CSRF Token | Required

---

### Form Validation & Integrity Matrix

| Form Code | Context | Authoritative Validator | File Upload Max | Anti-Spam / Security | Target Phase | Canonical Test ID |
| :--- | :--- | :--- | :---: | :--- | :---: | :--- |
| **FORM-PUB-01** | Contact Us | PHP `ContactValidator` | None | Turnstile + Honeypot + Rate Limit | Phase 23 | `TEST-PUB-019` |
| **FORM-PUB-02** | Partner With Us | PHP `PartnerValidator` | None | Turnstile + Rate Limit | Phase 23 | `TEST-PUB-020` |
| **FORM-PUB-03** | RFP Engine | PHP `ProposalValidator` | 15MB | Turnstile + Magic Bytes + Quarantine | Phase 23 | `TEST-PUB-021` |
| **FORM-PUB-04** | Careers Apply | PHP `CareersValidator` | 10MB | Turnstile + Magic Bytes + Quarantine | Phase 24 | `TEST-PUB-015` |
| **FORM-ADM-01** | Admin Login | PHP `AuthValidator` | None | Bcrypt + 15m Lockout | Phase 6 | `TEST-ADM-002` |
| **FORM-ADM-02** | Password Recover| PHP `AuthValidator` | None | Rate Limit + Generic Response | Phase 6 | `TEST-ADM-002` |
| **FORM-ADM-03** | MFA TOTP | PHP `TotpValidator` | None | RFC 6238 Window Check | Phase 6 | `TEST-ADM-003` |
| **FORM-ADM-04** | Page Editor | PHP `ContentValidator` | None | Concurrency Timestamp + DOMPurify | Phase 7 | `TEST-ADM-005` |
| **FORM-ADM-05** | Service Manager | PHP `ServiceValidator` | None | Slug Collision Check + Redirect Gen | Phase 8 | `TEST-ADM-007` |
| **FORM-ADM-06** | Media Upload | PHP `MediaValidator` | 15MB | Magic Bytes + Auto WebP | Phase 10 | `TEST-ADM-009` |
| **FORM-ADM-07** | Email Templates | PHP `EmailValidator` | None | Placeholder Tag Integrity Check | Phase 13 | `TEST-ADM-013` |
| **FORM-ADM-08** | User Manager | PHP `UserValidator` | None | Super Admin Guard + Minimum 1 Admin | Phase 6 | `TEST-ADM-004` |
