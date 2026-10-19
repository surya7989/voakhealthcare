# Email Notification Functional Requirements
## Transactional Email Architecture, Resilient Outbox Dispatch, Hostinger Feasibility, and Legal Compliance

**Document Reference:** `docs/phase-01/10_EMAIL_NOTIFICATION_REQUIREMENTS.md`  
**Phase:** Phase 1 of 31 — Complete Functional Specification (Corrected Baseline)  
**Baseline Authority:** Master Functional Requirements (`01_MASTER_FUNCTIONAL_REQUIREMENTS.md`) and Technical Architecture (`07_TECHNICAL_ARCHITECTURE.md`).

---

### 1. Architectural Philosophy & Delivery Constraints

#### 1.1 Hostinger Shared Hosting Reality & Scheduling Feasibility
1. **No Continuous Background Worker:**  
   Hostinger shared hosting environments do NOT support long-running Node.js, Python, or PHP daemon processes, nor Redis-backed job queues. Any assumption of a continuously executing background daemon is prohibited.
2. **Cron Execution Realities:**  
   While Hostinger cPanel provides Scheduled Cron Jobs, their minimum execution interval, reliability, and concurrency limits vary across hosting tiers (often capped at 15-minute intervals, or subject to strict CPU throttling). **The system must NOT silently depend on 1-minute cron execution for critical notifications.**
3. **Resilient Multi-Path Dispatching Strategy:**  
   To guarantee delivery without blocking public HTTP user requests or dropping emails when cron is delayed:
   - **Path A: Durable Database Outbox Pattern:** All outbound emails are written to an `email_queue` table with payload, retry counter, priority, and deduplication hashes.
   - **Path B: Bounded Synchronous Fast-Path for Urgent Security Events:** High-priority, user-blocking authentication actions (Password Recovery `EMAIL-07`, MFA OTP `EMAIL-08`, and Admin Invitations `EMAIL-06`) execute an immediate bounded synchronous delivery attempt (socket timeout capped at 4 seconds). If the socket succeeds, the outbox record is marked `SENT`. If it fails or times out, it seamlessly falls back to the outbox queue, and the user receives a polite status notice.
   - **Path C: Scheduled Batch Worker (Hostinger Cron):** If cPanel cron is confirmed available, a scheduled runner executes `php cli/process_email_queue.php` at the lowest supported interval (e.g., every 5 to 15 minutes).
   - **Path D: Opportunistic Queue Dispatching (Web-Cron Fallback):** If external cron is unavailable or delayed, authenticated administrative requests (which occur during regular business hours) trigger a lightweight, non-blocking post-response shutdown handler (`register_shutdown_function` / `fastcgi_finish_request`) that claims and processes up to 3 pending outbox items.
   - **Path E: External HTTPS Relay Architecture (Recommended for Phase 2):** To avoid raw SMTP port throttling (`465`/`587`) on shared hosting, Phase 2 architecture will evaluate an authenticated HTTPS API-based transactional relay (e.g., SendGrid, Mailgun, AWS SES, or Brevo) communicating over standard HTTPS port 443.
4. **Phase 1 Boundary Notice:**  
   Under no circumstances shall this document assert that live email delivery has been tested or verified in Phase 1. Live SMTP handshakes, socket timeouts, and inbox deliverability audits are formally scheduled for **Phase 13 (Transactional Email)** and **Phase 27 (End-to-End System Testing)**.

---

### 2. The 8 Transactional Email Workflows

```
Workflow Inventory:
01. Contact Inquiry Acknowledgement (Public Inquirer)
02. Internal Inquiry Alert (Triage Team)
03. Strategic Partnership Acknowledgement (Executive Partner)
04. Proposal / RFP Submission Acknowledgement (Procurement Officer)
05. Recruitment Candidate Application Acknowledgement (Job Applicant)
06. Administrator Account Invitation (New Admin User - Bounded Fast-Path)
07. Password Recovery & Security Reset (Admin User - Bounded Fast-Path)
08. High-Priority Security Telemetry Alert (Super Admin - Bounded Fast-Path)
```

---

#### 01. Contact Inquiry Acknowledgement
- **Workflow Code:** `EMAIL-01`
- **Trigger Event:** Public user successfully submits Form A (`/contact`).
- **Recipient:** `work_email` submitted in form.
- **Template Key:** `tmpl_contact_ack`
- **Dynamic Placeholders:**
  - `{{contact_name}}` -> User's full name.
  - `{{organization_name}}` -> User's organization.
  - `{{ref_no}}` -> Unique inquiry reference (e.g., `INQ-20261016-8941`).
  - `{{submission_datetime}}` -> Form submission timestamp in configured timezone.
  - `{{inquiry_summary}}` -> Truncated excerpt of inquiry.
  - `{{legal_entity_name}}` -> Registered corporate legal name (from `system_settings`).
  - `{{registration_number}}` -> Business registration identifier (e.g., ABN/ACN/EIN).
  - `{{registered_address}}` -> Official corporate office address.
  - `{{privacy_policy_url}}` -> Canonical URL to privacy policy.
- **Subject Line:** `{{legal_entity_name}} | Inquiry Received [{{ref_no}}]`
- **Legal & Compliance Copy (Configurable Variables):**  
  "This email was sent to {{work_email}} regarding a business inquiry submitted to {{legal_entity_name}}. {{legal_entity_name}} provides enterprise B2B healthcare operations and operational staffing; we do not provide direct patient medical care or emergency services. Corporate Address: {{registered_address}}. Business Registration: {{registration_number}}. To review our privacy practices, visit: {{privacy_policy_url}}."
- **Failure Handling & Retry:** Inserted into `email_queue`. Retried with exponential backoff (2m, 10m, 30m, 2h). After 4 attempts, marked `FAILED` for admin review in Module 12.
- **Deduplication:** Hash of `(template_key + recipient + ref_no)` enforced; duplicate submissions within 10 minutes ignored.
- **Audit Event:** `EMAIL_DISPATCHED_CONTACT_ACK`.

---

#### 02. Internal Inquiry Alert
- **Workflow Code:** `EMAIL-02`
- **Trigger Event:** Public user successfully submits Form A (`/contact`).
- **Recipient:** Configured internal triage routing address from `system_settings` (e.g., `triage@domain.com`).
- **Template Key:** `tmpl_contact_internal`
- **Dynamic Placeholders:**
  - `{{contact_name}}`, `{{organization_name}}`, `{{work_email}}`, `{{phone}}`, `{{country_code}}`, `{{inquiry_type}}`, `{{message}}`, `{{ref_no}}`.
  - `{{admin_crm_url}}` -> Direct deep link to `/admin/inquiries/contact/:id`.
- **Subject Line:** `[NEW INQUIRY] {{inquiry_type}} - {{organization_name}} [{{ref_no}}]`
- **Security:** Inbound message body sanitized via `htmlspecialchars()` to prevent HTML injection in email clients.

---

#### 03. Strategic Partnership Acknowledgement
- **Workflow Code:** `EMAIL-03`
- **Trigger Event:** Public user submits Form B (`/partner-with-us`).
- **Recipient:** `work_email` submitted in partnership form.
- **Template Key:** `tmpl_partner_ack`
- **Dynamic Placeholders:**
  - `{{executive_name}}`, `{{executive_title}}`, `{{organization_name}}`, `{{ref_no}}` (`PTN-YYYYMMDD-XXXX`), `{{partnership_scope}}`, `{{legal_entity_name}}`, `{{registration_number}}`.
- **Subject Line:** `{{legal_entity_name}} | Strategic Partnership Inquiry Acknowledgement [{{ref_no}}]`
- **Legal Copy:** Institutional non-disclosure commitment; confirmation of executive review within 2 business days.

---

#### 04. Proposal / RFP Submission Acknowledgement
- **Workflow Code:** `EMAIL-04`
- **Trigger Event:** Healthcare procurement officer completes Form C (`/request-proposal`).
- **Recipient:** `lead_email` submitted in Step 1 of wizard.
- **Template Key:** `tmpl_proposal_ack`
- **Dynamic Placeholders:**
  - `{{lead_name}}`, `{{organization_name}}`, `{{facility_type}}`, `{{ref_no}}` (`RFP-YYYYMMDD-XXXX`), `{{target_start_date}}`, `{{services_list}}`, `{{legal_entity_name}}`.
- **Subject Line:** `{{legal_entity_name}} | Healthcare Operations Proposal Acknowledged [{{ref_no}}]`
- **Legal Copy:** Clarifies that submission does not create a binding service agreement; affirms confidentiality of attached tender briefs.

---

#### 05. Recruitment Candidate Application Acknowledgement
- **Workflow Code:** `EMAIL-05`
- **Trigger Event:** Candidate applies for an active job vacancy via Form D (`/careers/:slug`).
- **Recipient:** Candidate's email address.
- **Template Key:** `tmpl_careers_ack`
- **Dynamic Placeholders:**
  - `{{first_name}}`, `{{job_title}}`, `{{department}}`, `{{submission_date}}`, `{{legal_entity_name}}`, `{{privacy_policy_url}}`.
- **Subject Line:** `Application Received: {{job_title}} | {{legal_entity_name}} Careers`
- **Legal Copy:** Recruitment privacy consent statement; instructions on talent retention policy (maximum 12 months with consent).

---

#### 06. Administrator Account Invitation (Bounded Fast-Path)
- **Workflow Code:** `EMAIL-06`
- **Trigger Event:** Super Admin creates a new admin user in Module 03.
- **Recipient:** Invited admin user's corporate email.
- **Template Key:** `tmpl_admin_invite`
- **Dispatch Priority:** High (Priority 1) — Executes bounded synchronous fast-path (max 4s); falls back to outbox queue on network timeout.
- **Dynamic Placeholders:**
  - `{{full_name}}`, `{{assigned_role}}`, `{{invitation_link}}` (Contains cryptographically signed 64-char token expiring in 24 hours), `{{legal_entity_name}}`.
- **Subject Line:** `Action Required: Invitation to {{legal_entity_name}} Admin Portal`
- **Security & Integrity:**
  - Invitation token generated via `bin2hex(random_bytes(32))`; hashed via SHA-256 before storage in database.
  - Link expires in 24 hours. Forced password configuration and TOTP MFA setup on first login.

---

#### 07. Password Recovery & Security Reset (Bounded Fast-Path)
- **Workflow Code:** `EMAIL-07`
- **Trigger Event:** Admin submits password recovery form (`/admin/password-recover`).
- **Recipient:** Admin user's registered email.
- **Template Key:** `tmpl_password_reset`
- **Dispatch Priority:** Critical (Priority 1) — Executes bounded synchronous fast-path (max 4s); falls back to outbox queue.
- **Dynamic Placeholders:**
  - `{{full_name}}`, `{{reset_link}}` (Signed token expiring in 15 minutes), `{{request_ip}}`, `{{request_datetime}}`, `{{legal_entity_name}}`.
- **Subject Line:** `Security Notice: {{legal_entity_name}} Password Reset Request`
- **Security & Integrity:**
  - Token lifetime: strictly 15 minutes. Single-use: invalidated immediately upon use.
  - Generic success message emitted on web interface regardless of account existence.

---

#### 08. High-Priority Security Telemetry Alert (Bounded Fast-Path)
- **Workflow Code:** `EMAIL-08`
- **Trigger Event:** System detects critical security threshold breach (e.g., 5 consecutive failed logins triggering account lockout; unauthorized database migration attempt; CSRF token validation spike).
- **Recipient:** Designated security notification email from `system_settings`.
- **Template Key:** `tmpl_security_alert`
- **Dispatch Priority:** Critical (Priority 1) — Bounded fast-path + disk failover log.
- **Dynamic Placeholders:**
  - `{{incident_code}}`, `{{event_type}}`, `{{target_user}}`, `{{source_ip}}`, `{{user_agent}}`, `{{timestamp}}`.
- **Subject Line:** `[CRITICAL SECURITY ALERT] {{event_type}} Detected on Admin Console`
- **Security & Integrity:** Zero sensitive passwords or credentials printed in alert body. Direct failover log to server disk (`storage/logs/security_alerts.log`) if SMTP relay is unreachable.

---

### 3. Durable Outbox Schema & Atomic Claiming Logic

```sql
CREATE TABLE email_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(50) NOT NULL,
    recipient_email VARCHAR(150) NOT NULL,
    recipient_name VARCHAR(100),
    subject VARCHAR(200) NOT NULL,
    payload_json JSON NOT NULL, -- Serialized dynamic placeholders
    priority TINYINT UNSIGNED NOT NULL DEFAULT 5, -- 1 = Critical, 5 = Standard, 9 = Bulk
    status ENUM('PENDING', 'PROCESSING', 'SENT', 'FAILED') DEFAULT 'PENDING',
    attempt_count TINYINT UNSIGNED DEFAULT 0,
    max_attempts TINYINT UNSIGNED DEFAULT 4,
    worker_id VARCHAR(64) NULL, -- Identifies claiming process
    claimed_at DATETIME NULL,
    scheduled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    last_error_message TEXT,
    dedup_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dedup (dedup_hash),
    INDEX idx_queue_dispatch (status, priority, scheduled_at, claimed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.1 Atomic Queue Claiming Query:
To prevent race conditions and duplicate sends when multiple processes run concurrently:
```sql
-- Step 1: Generate unique worker token (e.g., UUID + PID)
-- Step 2: Atomically claim up to 10 pending emails
UPDATE email_queue
SET 
    status = 'PROCESSING',
    worker_id = :worker_token,
    claimed_at = NOW()
WHERE 
    status = 'PENDING'
    AND scheduled_at <= NOW()
    AND (claimed_at IS NULL OR claimed_at < NOW() - INTERVAL 5 MINUTE)
ORDER BY priority ASC, id ASC
LIMIT 10;

-- Step 3: Fetch claimed rows belonging strictly to this worker
SELECT * FROM email_queue WHERE worker_id = :worker_token AND status = 'PROCESSING';
```

#### 3.2 Interrupted Delivery Recovery:
If a worker crashes or is killed by Hostinger resource limits mid-execution:
- Any record in `PROCESSING` whose `claimed_at` is older than **5 minutes** is considered abandoned.
- The next worker invocation automatically resets abandoned claims to `PENDING` and increments the attempt count.

#### 3.3 Bounded SMTP Socket Configuration:
- Connection timeout: `stream_socket_client` timeout = **5 seconds**.
- Stream read/write timeout: `stream_set_timeout` = **10 seconds**.
- Overall execution ceiling per email attempt: **15 seconds max**.

---

### 4. Admin Monitoring & Manual Retry Controls (Module 12)

Admin Module 12 (`ADM-012` Email Templates & Outbox) includes an **Outbox Telemetry Console**:
1. **Outbox Queue Table:** Displays `ID`, `Template`, `Recipient`, `Priority`, `Status` (`PENDING`, `PROCESSING`, `SENT`, `FAILED`), `Attempts`, `Last Error`, and `Created At`.
2. **Filter Controls:** Filter by Status (specifically viewing `FAILED` emails) and Date.
3. **Manual Retry Action:** Super Admin and Website Admin can click "Retry Send" on any `FAILED` email:
   - Resets `status = 'PENDING'`, `attempt_count = 0`, and `scheduled_at = NOW()`.
   - Clears `last_error_message`.
   - Logs audit event `EMAIL_MANUAL_RETRY_TRIGGERED`.
4. **Diagnostic Test Send:** Real-time test dispatch button that performs a live handshake with configured SMTP relay and outputs a structured diagnostic report (connection time, TLS handshake status, relay response code).

---

### 5. Acceptance Criteria & Test Scenarios

| Test ID | Test Target | Verification Standard | Target Phase |
| :--- | :--- | :--- | :---: |
| **TEST-PUB-019** | Contact Form Outbox Write | Record created in `email_queue` with valid placeholders and `dedup_hash` | Phase 23 |
| **TEST-ADM-002** | Password Reset Fast-Path | Attempts bounded synchronous dispatch; falls back cleanly on timeout | Phase 6 |
| **TEST-ADM-013** | Outbox Monitor & Manual Retry | Failed email row can be manually reset to `PENDING` via Admin Module 12 | Phase 13 / 26 |
| **TEST-WF-001** | Atomic Claiming Concurrency | Two concurrent workers claim distinct batches; zero duplicate sends | Phase 13 |
| **TEST-SEC-005** | Legal Template Placeholders | All rendered emails contain `{{legal_entity_name}}` and `{{registration_number}}` | Phase 13 |
