# Unresolved Architectural Decisions, Technical Spikes, and Hosting Dependencies

**Document Reference:** `docs/phase-02/17_UNRESOLVED_ARCHITECTURAL_DECISIONS.md`  
**Phase:** Phase 2 of 31 — Complete System Architecture  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Executing Role:** Principal Enterprise Software Architect & Technical Risk Manager  
**Baseline Authority:** Accepted Phase 1 Open Decisions (`docs/phase-01/16_BUSINESS_INPUTS_AND_OPEN_DECISIONS.md`)  
**Status:** **ACTIVE RISK & DEPENDENCY REGISTER** (Tracking 10 Technical & Hosting Dependencies)

---

## 1. Architectural Risk & Dependency Governance

Phase 2 establishes an implementation-ready system architecture. However, several operational parameters depend on live hosting runtime probes or external business confirmations that can only be verified during implementation phases.

To prevent hidden assumptions from jeopardizing delivery, this register classifies every dependency as:
- **`DESIGN-VERIFIED`:** Architectural design complete; requires runtime integration verification in assigned phase.
- **`PENDING RUNTIME PROBE`:** Feasibility depends on live Hostinger server testing.
- **`UNVERIFIED BUSINESS INPUT`:** Requires client executive confirmation before production deployment.

---

## 2. Master Technical & Hosting Dependency Register

| Item ID | Architectural Domain | Technical / Business Parameter | Current Working Architectural Baseline | Status | Resolution Phase | Fallback Countermeasure |
| :---: | :--- | :--- | :--- | :---: | :---: | :--- |
| **ARCH-DEP-01** | **Email Infrastructure** | Hostinger Scheduled Task (Cron) Granularity & Minimum Interval | CLI runner: `php cli/process_email_queue.php`; assumed possible at 15m or 1h interval. | **PENDING RUNTIME PROBE** | Phase 13 (Email Backend) | Bounded synchronous fast-path for urgent auth; Module 12 manual retry console; admin activity piggyback hook. |
| **ARCH-DEP-02** | **Email Infrastructure** | Hostinger Live SMTP Relay Socket Throughput & Quotas | Hostinger SMTP (`smtp.hostinger.com:465`, SSL); connection timeout 3s, send timeout 4s. | **PENDING RUNTIME PROBE** | Phase 13 (Email Backend) | Fallback to external transactional email API (e.g., Mailgun / SendGrid via HTTPS) if socket latency exceeds 5s. |
| **ARCH-DEP-03** | **Hosting Deployment** | Filesystem Symlink Support on Hostinger Shared Hosting | Atomic deployment symlink: `ln -sfn releases/vX.X.X current`. | **PENDING RUNTIME PROBE** | Phase 31 (Hostinger Go-Live) | Direct directory file overwrite with atomic maintenance mode lock file. |
| **ARCH-DEP-04** | **Hosting Runtime** | PHP OPcache Preloading & Memory Consumption Limit | OPcache enabled via `.user.ini` with 128MB memory allocation. | **DESIGN-VERIFIED** | Phase 4 (Backend Boot) | Standard opcode caching without preloading if memory ceiling is throttled by host. |
| **ARCH-DEP-05** | **Edge Infrastructure** | Cloudflare Edge API Automated Cache Purging | Single-URL purge via Cloudflare REST API upon CMS publication events. | **DESIGN-VERIFIED** | Phase 16 / 31 | Time-to-Live (TTL) cache expiration (max-age=300s) if Cloudflare API credentials omitted. |
| **ARCH-DEP-06** | **Corporate Identity** | Legal Entity Corporate Name, Registration ID & Address | Dynamic configuration variables (`{{legal_entity_name}}`, `{{registration_number}}`, `{{registered_address}}`). | **UNVERIFIED BUSINESS INPUT** | Phase 24 (Legal Views) | Safe fallback text ("Voak Health Care Platform"); values configurable via Admin Module 18. |
| **ARCH-DEP-07** | **Commercial Scope** | Confirmation of 6 B2B Healthcare Service Offerings | 6 configurable service pillars seeded as database records. | **UNVERIFIED BUSINESS INPUT** | Phase 22 (Public Services) | Dynamic CMS allows adding, renaming, or unpublishing pillars instantly without code changes. |
| **ARCH-DEP-08** | **Commercial Scope** | Institutional Social Proof & Hospital Case Studies Consent | Strict conditional rendering: `is_published=0` causes total DOM collapse; consent release required. | **UNVERIFIED BUSINESS INPUT** | Phase 20 / 24 | Complete visual suppression of unverified client logos and testimonials; zero placeholder boxes. |
| **ARCH-DEP-09** | **Client File Security** | Server-Side Antivirus / ClamAV Scanning on Shared Hosting | MIME magic byte inspection (`finfo`) + isolated quarantine outside web root. | **DESIGN-VERIFIED** | Phase 10 (Media & Storage) | External cloud-based virus scanning API (e.g., VirusTotal / AWS GuardDuty) if local ClamAV is unavailable on Hostinger. |
| **ARCH-DEP-10** | **Database Storage** | Shared MySQL Performance Under Concurrent Audit Logging | Append-only SHA-256 chained audit ledger with compound indexes. | **DESIGN-VERIFIED** | Phase 17 (Audit Backend) | Asynchronous buffer / weekly table partitioning if audit ledger exceeds 500,000 rows. |

---

## 3. Deep-Dive on Critical Hosting Spikes

### 3.1 Spike 1: Hostinger Scheduled Task Execution Verification (Phase 13 Spike)
- **Problem:** If the Hostinger shared hosting cPanel lacks shell cron or restricts execution to once per day, background email queue processing could be delayed.
- **Architectural Safeguard:** The architecture deliberately **does not depend** on a 1-minute cron job. All time-critical security notifications (password resets, MFA elevation) bypass the queue via Tier 1 Synchronous Fast-Path (bounded to 4s). Non-critical inquiries wait in the outbox until cron triggers or an administrator initiates manual dispatch from the Module 12 console.
- **Verification Plan:** In Phase 13, execute a probe script via Hostinger cPanel to determine minimum cron frequency and maximum CLI script execution time.

### 3.2 Spike 2: Symlink Switching vs. Directory Sync Deployment (Phase 31 Spike)
- **Problem:** Select shared hosting environments disable PHP's `symlink()` function or Apache `FollowSymLinks` directive for security reasons.
- **Architectural Safeguard:** If symlinking `current/ -> releases/v1.0.0/` is prohibited by Hostinger security policies, the deployment pipeline falls back to an automated **Directory Sync Deployment**:
  1. Activates temporary maintenance page via `.htaccess`.
  2. Syncs updated files to `src/` and `public_html/`.
  3. Executes `php cli/migrate.php up`.
  4. Resets OPcache via `opcache_reset()`.
  5. Deactivates maintenance page. Total downtime is under 10 seconds.
