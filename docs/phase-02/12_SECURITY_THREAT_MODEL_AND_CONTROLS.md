# Security Threat Model and Application Security Controls

**Document Reference:** `docs/phase-02/12_SECURITY_THREAT_MODEL_AND_CONTROLS.md`  
**Phase:** Phase 2 of 31 — Complete System Architecture  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Executing Role:** Application Security Architect & Security Auditor  
**Baseline Authority:** Accepted Phase 0 Security Baseline (`docs/phase-00/10_SECURITY_AND_PRIVACY_BASELINE.md`) and Accepted Phase 1 Security Specifications (`docs/phase-01/14_SECURITY_PRIVACY_AND_AUDIT_REQUIREMENTS.md`)  
**Standard Authority:** OWASP Application Security Verification Standard (ASVS) v4.0.3 Level 2 Baseline  
**Status:** **AUTHORITATIVE ARCHITECTURAL SPECIFICATION** (Threat Model Only — Pentesting & Hardening Deferred to Phase 30)

---

## 1. Security Architecture & Threat Model Overview

The Voak Health Care platform handles sensitive B2B commercial healthcare interactions, procurement tender specifications, and healthcare professional recruitment data. The security architecture adheres to **OWASP ASVS v4.0.3 Level 2** as an engineering and verification target across all development phases.

> [!IMPORTANT]
> **Compliance Verification Status Declaration:**  
> Security compliance is an active engineering and verification target, not an accomplished fact at the architecture stage. Full compliance will be empirically verified through automated test suites in Phase 27 and external penetration testing in Phase 30.

---

## 2. Comprehensive 20-Threat Matrix, Controls & Testing Criteria

The threat model evaluates all 20 critical enterprise vulnerability vectors:

| Threat ID | Threat Vector & Description | Architectural Countermeasure / Control | Target Phase | Relevant Test | Residual Risk Level |
| :---: | :--- | :--- | :---: | :---: | :---: |
| **TH-01** | **Credential Stuffing** (Automated credential replay from breach dumps) | Cloudflare Turnstile on auth entry; IP rate limiting; mandatory RFC 6238 TOTP MFA. | Phase 6 | `TEST-ADM-002`, `TEST-ADM-003` | Low |
| **TH-02** | **Brute-Force Attacks** (Dictionary attacks against admin passwords) | 5-attempt threshold triggers automatic 15-minute lockout on both email and IP (`FR-ADM-002`). | Phase 6 | `TEST-ADM-002` | Negligible |
| **TH-03** | **Account Enumeration** (Deducing valid usernames via error/timing diffs) | Timing-safe generic responses ("Invalid email or password"); constant-time password hashing dummy cycles. | Phase 6 | `TEST-ADM-002` | Low |
| **TH-04** | **Session Fixation** (Hijacking pre-established session IDs) | `session_regenerate_id(true)` executed immediately upon MFA verification. Pre-auth token destroyed. | Phase 6 | `TEST-SEC-002` | Negligible |
| **TH-05** | **CSRF** (Cross-Site Request Forgery on administrative mutations) | Cryptographic synchronizer tokens (`X-CSRF-Token`); `SameSite=Lax` on session cookies (`FR-SEC-001`). | Phase 6 | `TEST-SEC-001` | Negligible |
| **TH-06** | **XSS** (Cross-Site Scripting via injected CMS/form content) | Context-aware HTML escaping; native PHP htmlspecialchars() and PHP HTMLPurifier; strict Content-Security-Policy (CSP). | Phase 7, 15 | `TEST-PUB-017` | Low |
| **TH-07** | **SQL Injection** (Manipulating relational database queries) | 100% PDO prepared statements with parameterized binds; zero string concatenation in SQL (`FR-SEC-007`). | Phase 5 | `TEST-PUB-006` | Negligible |
| **TH-08** | **IDOR / Broken Object Authorization** (Accessing other proposals/CVs) | Dedicated object-level authorization checks in PHP controllers (`FR-SEC-004`). | Phase 12, 14 | `TEST-ADM-012`, `TEST-ADM-015` | Low |
| **TH-09** | **Privilege Escalation** (Editor executing Super Admin actions) | Strict 5-role RBAC matrix evaluated server-side by `AuthorizePermission` middleware (`FR-ADM-004`). | Phase 6, 26 | `TEST-ADM-004` | Negligible |
| **TH-10** | **File Upload Attacks** (Uploading web shells `.php` disguised as PDF) | MIME magic byte inspection (`finfo`); stored strictly outside web root in quarantine (`FR-SEC-003`). | Phase 10 | `TEST-SEC-003` | Low |
| **TH-11** | **Path Traversal** (Using `../../` to read arbitrary system files) | Client filenames discarded; UUIDv4 stored filenames; `basename()` path normalization (`FR-SEC-003`). | Phase 10 | `TEST-SEC-003` | Negligible |
| **TH-12** | **Sensitive Document Leakage** (Publicly indexing resumes or tenders) | Quarantine directory located above `public_html/`; `.htaccess` blocks all direct HTTP access (`FR-SEC-004`). | Phase 10, 12 | `TEST-SEC-004` | Negligible |
| **TH-13** | **SMTP Abuse & Open Relay Exploitation** | Authenticated SMTP over port 465 SSL only; outbound rate limiting; zero client-controlled email recipients. | Phase 13 | `TEST-ADM-013` | Low |
| **TH-14** | **Contact Form & Inbound Spam** | Cloudflare Turnstile CAPTCHA; hidden honeypot trap (`website_hp`); IP rate limiting (5 / hr). | Phase 23 | `TEST-PUB-019` | Low |
| **TH-15** | **SSRF** (Server-Side Request Forgery via URL fetching) | Platform executes ZERO server-side fetching of external URLs. External image hotlinking forbidden. | Phase 10 | Architectural Rule | Negligible |
| **TH-16** | **Malicious CMS Content** (Editor embedding phishing or iframes) | Strict HTML tag allowlist in CMS editor; `<iframe>` and `<object>` tags permanently stripped. | Phase 7, 15 | `TEST-ADM-005` | Low |
| **TH-17** | **Secrets Exposure** (Committing API keys or DB passwords to Git) | `.env` file isolated (chmod 0600); `.gitignore` covers all secrets; pre-commit scanner hooks (`FR-SEC-006`). | Phase 4, 31 | `TEST-SEC-006` | Negligible |
| **TH-18** | **Error Information Leakage** (Stack traces revealing paths/passwords) | In production, `APP_DEBUG=false`; stack traces suppressed; diagnostic reference code returned (`FR-PUB-023`). | Phase 4, 24 | `TEST-PUB-023` | Negligible |
| **TH-19** | **Administrative Misuse** (Rogue admin modifying live records) | Dual-control revision history; full before/after JSON diffs recorded in immutable audit log. | Phase 17 | `TEST-ADM-019` | Low |
| **TH-20** | **Audit Log Tampering** (Attacker deleting or modifying audit rows) | Cryptographic SHA-256 row hash chaining; application repository exposes zero `UPDATE` or `DELETE` methods. | Phase 17 | `TEST-ADM-019` | Low |

---

## 3. Production Security Headers Specification

Security headers are injected at the Apache web server level (`public_html/.htaccess`) and verified via automated test suites:

```apache
# Authoritative Production Security Headers
<IfModule mod_headers.c>
    # 1. Prevent MIME-Type Sniffing
    Header set X-Content-Type-Options "nosniff"

    # 2. Clickjacking Protection (Permit framing only by same origin for CMS previews)
    Header set X-Frame-Options "SAMEORIGIN"

    # 3. HTTP Strict Transport Security (HSTS) - 1 Year + Subdomains
    Header set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"

    # 4. Strict Referrer Policy
    Header set Referrer-Policy "strict-origin-when-cross-origin"

    # 5. Restrict Unused Browser Hardware Features
    Header set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=()"

    # 6. Content Security Policy (CSP)
    Header set Content-Security-Policy "default-src 'self'; \
        script-src 'self' https://challenges.cloudflare.com; \
        style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; \
        font-src 'self' https://fonts.gstatic.com data:; \
        img-src 'self' data: https: blob:; \
        connect-src 'self' https://challenges.cloudflare.com; \
        frame-src 'self' https://challenges.cloudflare.com; \
        object-src 'none'; \
        base-uri 'self'; \
        form-action 'self';"
</IfModule>
```

---

## 4. Cryptographic Row Hash Chained Audit Ledger Architecture

To provide tamper-evident compliance on shared MySQL hosting where write-once hardware storage is unavailable:
1. Every audit entry computes an immutable cryptographic hash:
   $$\text{row\_hash}_n = \text{SHA256}(\text{row\_hash}_{n-1} \parallel \text{timestamp} \parallel \text{user\_id} \parallel \text{action} \parallel \text{diff\_json})$$
2. The initial seed entry (`id = 1`) links to the Genesis Hash recorded in system configuration.
3. If an unauthorized application user attempts an in-place modification of historical audit records, the cryptographic hash chain breaks immediately.
4. The CLI integrity tool (`php cli/verify_audit_chain.php`) runs during deployment and scheduled tasks to verify 100% chain integrity.

### 4.1 Trust Anchor Boundary & Residual Risk Clarification
The SHA-256 row-hash chain guarantees application-level tamper evidence against unauthorized SQL injections, compromised web application accounts, or malicious application-level queries. However, because MySQL operates on shared hosting without hardware write-once (WORM) storage, a privileged database administrator with direct MySQL root or phpMyAdmin access could theoretically rewrite historical rows and recalculate subsequent hashes. 

To establish an independent trust anchor, the platform supports periodic off-site hash anchoring (e.g., daily cryptographic checkpoint exports streamed to off-site archival or external compliance syslog via `GET /api/v1/admin/audit-logs/export`).
