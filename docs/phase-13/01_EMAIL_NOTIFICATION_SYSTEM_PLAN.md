# Phase 13: Email Notification Backend & Outbox Telemetry Implementation Plan

**Reference:** `docs/phase-13/01_EMAIL_NOTIFICATION_SYSTEM_PLAN.md`  
**Phase:** Phase 13 of 32 — Transactional Email & Outbox Telemetry  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Executing Role:** Principal PHP Systems Architect & Security Engineer  
**Baseline Authorities:**
- `docs/phase-01/01_MASTER_FUNCTIONAL_REQUIREMENTS.md` (`FR-ADM-013`)
- `docs/phase-01/05_ADMIN_19_MODULE_SPECIFICATIONS.md` (`ADM-012`)
- `docs/phase-01/10_EMAIL_NOTIFICATION_REQUIREMENTS.md` (`EMAIL-01` to `EMAIL-08`)
- `docs/phase-01/15_REQUIREMENTS_TRACEABILITY_MATRIX.md` (Notification Control)
- `docs/phase-01/17_PHASE_1_ACCEPTANCE_TEST_CATALOG.md` (`TEST-ADM-013`, `TEST-WF-001`, `TEST-SEC-005`)
- `docs/phase-02/08_EMAIL_OUTBOX_AND_DELIVERY_ARCHITECTURE.md` (`ADR-006`)
- `docs/phase-02/15_ARCHITECTURAL_DECISION_RECORDS.md` (`ADR-006`)
- `docs/phase-02/openapi/openapi.json` (`/admin/email-templates`, `/admin/email-queue`)

---

## 1. Executive Summary & Objective

Phase 13 delivers the **Transactional Email Notification Backend & Outbox Telemetry Console** (`FR-ADM-013`, `ADR-006`, `ADM-012`). It operationalizes the enterprise tri-tier notification delivery model across 8 canonical transactional workflows (`EMAIL-01` to `EMAIL-08`), decouples user-facing web requests from external SMTP network latency via a durable outbox queue (`email_queue`), enforces mandatory dynamic variable placeholder guards (`TEST-ADM-013`, `TEST-SEC-005`), provides atomic queue batch claiming with crash recovery (`TEST-WF-001`), and exposes administrative telemetry controls for manual retry and test dispatches.

---

## 2. Requirements & Traceability Matrix

| Requirement ID | Canonical Specification | Target Component | Status |
| :--- | :--- | :--- | :--- |
| **`ADM-012`** | Transactional Email Templates & Outbox Console | `EmailService`, `AdminEmailTemplateController`, `AdminEmailQueueController` | **Planned** |
| **`FR-ADM-013`** | Email Template Customizer, Placeholder Guard & Outbox Telemetry | `EmailService` | **Planned** |
| **`ADR-006`** | Tri-Tier Notification Architecture (Fast-path + Durable Outbox + Console) | `EmailService`, `MailerTransportInterface` | **Planned** |
| **`SYS-006`** | Bounded SMTP socket timeouts (3s connect, 4s read/write, 5s ceiling) | `SocketSmtpTransport` | **Planned** |
| **`TEST-ADM-013`** | Email template customizer, placeholder guard, outbox telemetry & retry | `tests/test_phase13_email_notification_system.php` | **Planned** |
| **`TEST-WF-001`** | Atomic claiming concurrency; distinct worker batches; 0 duplicate sends | `mysql_security_concurrency_test.php` (Test 12) | **Planned** |
| **`TEST-SEC-005`** | Legal template placeholders rendered (`{{legal_entity_name}}`, `{{registration_number}}`)| `EmailServiceTest` | **Planned** |

---

## 3. Database Schema & Migration Decision

### **ZERO NEW MIGRATIONS REQUIRED**
All necessary schema infrastructure exists in the verified database baseline:
1. **`email_queue` (`20261021_000025_create_email_queue_table.php`):**
   - Columns: `id`, `template_key`, `recipient_email`, `recipient_name`, `payload_json`, `status` (`QUEUED`, `PROCESSING`, `SENT`, `FAILED`), `attempts`, `max_attempts`, `error_message`, `claimed_at`, `worker_id`, `next_retry_at`, `sent_at`, `created_at`.
   - Pre-indexed for claiming: `idx_email_queue_claiming (status, next_retry_at, attempts)`, `idx_email_queue_claimed_at (claimed_at)`.
2. **`system_settings` (`20261021_000027_create_system_settings_table.php`):**
   - Columns: `id`, `setting_group`, `setting_key`, `setting_value`, `is_secret`, `description`, `updated_by_user_id`, `updated_at`.
   - Utilized with `setting_group = 'email_templates'` to store administrator-customized template subject lines, HTML bodies, and active flags.
3. **`audit_logs` (`20261021_000028_create_audit_logs_table.php`):**
   - Reused for immutable cryptographic SHA-256 audit log chaining on template updates, test dispatches, and manual retry operations.

---

## 4. The 8 Canonical Transactional Email Workflows

1. **`tmpl_contact_ack` / `contact-inquiry-ack` (`EMAIL-01`):**
   - Trigger: Public contact inquiry submission.
   - Recipient: Inquirer work email.
   - Mandatory Placeholders: `{{ref_no}}`, `{{legal_entity_name}}`.
2. **`tmpl_contact_internal` / `contact-inquiry-internal` (`EMAIL-02`):**
   - Trigger: Public contact inquiry submission.
   - Recipient: Internal triage team routing address.
   - Mandatory Placeholders: `{{ref_no}}`, `{{contact_name}}`, `{{inquiry_type}}`.
3. **`tmpl_partner_ack` / `partnership-ack` (`EMAIL-03`):**
   - Trigger: Strategic partnership submission.
   - Recipient: Executive partner email.
   - Mandatory Placeholders: `{{ref_no}}`, `{{legal_entity_name}}`.
4. **`tmpl_proposal_ack` / `proposal-ack` (`EMAIL-04`):**
   - Trigger: Commercial RFP proposal submission.
   - Recipient: Procurement lead email.
   - Mandatory Placeholders: `{{ref_no}}`, `{{legal_entity_name}}`.
5. **`tmpl_careers_ack` / `careers-application-ack` (`EMAIL-05`):**
   - Trigger: Candidate career job application.
   - Recipient: Candidate email.
   - Mandatory Placeholders: `{{job_title}}`, `{{legal_entity_name}}`.
6. **`tmpl_admin_invite` / `admin-invitation` (`EMAIL-06`):**
   - Trigger: Admin account invitation (Tier 1 Bounded Fast-Path).
   - Recipient: Invited administrator email.
   - Mandatory Placeholders: `{{invitation_link}}`, `{{legal_entity_name}}`.
7. **`tmpl_password_reset` / `password-reset` (`EMAIL-07`):**
   - Trigger: Admin password recovery request (Tier 1 Bounded Fast-Path).
   - Recipient: Administrator registered email.
   - Mandatory Placeholders: `{{reset_link}}`, `{{legal_entity_name}}`.
8. **`tmpl_security_alert` / `security-alert` (`EMAIL-08`):**
   - Trigger: Critical security threshold breach (Tier 1 Bounded Fast-Path).
   - Recipient: Designated security notification email.
   - Mandatory Placeholders: `{{incident_code}}`, `{{event_type}}`.

---

## 5. Security & Governance Architecture

1. **Mandatory Placeholder Guard (`TEST-ADM-013`):**
   - If an administrator updates a template and strips out a required placeholder tag (e.g., `{{ref_no}}`, `{{legal_entity_name}}`), the request fails closed with HTTP 422 (`ERR_VALIDATION`).
2. **Template Injection & XSS Defense:**
   - All variable substitutions undergo strict HTML escaping via `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
3. **SMTP Header Injection Defense (CRLF):**
   - Subject lines are strictly sanitized by stripping all `\r` and `\n` characters before dispatch.
4. **Fast-Path Fault Isolation:**
   - Tier 1 urgent notifications (Password Reset, Invite, Security Alert) execute synchronous dispatch bounded to a strict 4.0s socket timeout. If the socket times out or errors, it seamlessly writes to `email_queue` for background delivery without crashing the web request.
5. **Atomic Queue Claiming (`TEST-WF-001`):**
   - Background workers atomically claim batches of up to 20 emails using MySQL row locking:
     `UPDATE email_queue SET status='PROCESSING', worker_id=:wid, claimed_at=NOW() WHERE (status='QUEUED' OR (status='FAILED' AND attempts < max_attempts AND (next_retry_at IS NULL OR next_retry_at <= NOW()))) LIMIT :limit`
   - Stale claims older than 5 minutes are recovered back to `QUEUED` to heal crashes.
6. **Exponential Backoff & Dead-Letter:**
   - Max 3 attempts (`max_attempts = 3`).
   - Backoff schedule: `next_retry_at = NOW() + (attempts * 5) MINUTE`.
   - Permanent dead-letter at 3 attempts: visible in outbox console for manual triage.

---

## 6. API Endpoints & Contracts

- `GET /api/v1/admin/email-templates` (`email:read`): List all 8 templates with metadata and trigger definitions.
- `GET /api/v1/admin/email-templates/:id` (`email:read`): Retrieve template details, default body, and allowed/mandatory placeholders.
- `PUT|PATCH /api/v1/admin/email-templates/:id` (`email:update`): Save customized subject line and HTML body. Requires CSRF.
- `POST /api/v1/admin/email-templates/:id/test-send` (`email:test_send`): Send sample rendered test email to administrator. Requires CSRF.
- `GET /api/v1/admin/email-queue` (`email:read`): Outbox telemetry listing with status, template, search, and pagination.
- `GET /api/v1/admin/email-queue/:id` (`email:read`): Single outbox record detail with JSON payload and delivery timestamps.
- `POST /api/v1/admin/email-queue/:id/retry` (`email:update`): Reset failed or queued message. Requires CSRF.
- `POST /api/v1/admin/email-queue/retry-all-failed` (`email:update`): Bulk reset all failed messages. Requires CSRF.
- `POST /api/v1/admin/email-queue/dispatch` (`email:update`): Webhook / cron triggered queue processing batch. Requires CSRF or signature.

---

## 7. Deferred Infrastructure

1. **Frontend Admin Portal UI:** Outbox telemetry dashboard and template editor UI remain deferred to the React Admin Portal frontend phase.
2. **Hostinger Production Deployment:** Production staging, live DNS, and production migrations remain strictly deferred pending explicit Business Owner authorization.
