# Phase 6 Admin Authentication, RBAC & MFA Implementation Plan and Readiness Checklist

**Document Reference:** `DOC-PH6-01-IMPLEMENTATION-PLAN`  
**Phase ID:** Phase 6 — Admin Authentication, RBAC, Sessions & MFA Engine  
**Project:** Voak Health Care Corporate B2B Platform  
**Repository:** `https://github.com/surya7989/voakhealthcare.git`  
**Working Branch:** `main`  
**Date:** October 21, 2026  
**Authoring Roles:** Lead Security Architect, Staff PHP Backend Engineer & Application Security Specialist  
**Authoritative Architectural Baselines:**
- `docs/phase-00/10_SECURITY_AND_PRIVACY_BASELINE.md`
- `docs/phase-01/01_MASTER_FUNCTIONAL_REQUIREMENTS.md` (`FR-ADM-001`, `FR-ADM-002`, `FR-ADM-003`, `FR-SEC-001`, `FR-SEC-002`, `FR-SEC-003`)
- `docs/phase-01/06_COMPLETE_RBAC_PERMISSION_MATRIX.md` (45 granular permissions across 5 roles)
- `docs/phase-02/06_AUTHENTICATION_RBAC_AND_SESSION_ARCHITECTURE.md` (Authoritative Security Specification)
- `docs/phase-05/03_PHASE_5_COMPLETION_REPORT.md` (Gate D Business Acceptance Gate)

---

## 1. Executive Summary & Governance Precondition

Phase 6 implements the core administrative security perimeter for the Voak Health Care platform, covering administrative identity, password hashing, secure session management, role-based access control, time-based one-time password (TOTP) multi-factor authentication, failed-login account lockouts, and immutable audit logging.

> [!NOTE]
> **GOVERNANCE VARIANCE ACTIVE: DIRECT-TO-MAIN PHASE 6 DEVELOPMENT AUTHORIZED (GV-PH6-001)**  
> On October 21, 2026, the Business Owner issued an explicit instruction: *"I want Phase 6 developed directly in the existing main branch. Do not create a separate Phase 6 feature branch or separate sub-branches. All verified fixes and completed components must be committed and pushed directly to main, with tests run throughout development."*
>
> Engineering is authorized to implement Phase 6 components directly on `main` under isolated testing environments (SQLite memory and ephemeral MySQL 8.0 CI service containers). Production deployment and Gate E acceptance remain strictly gated.

---

## 2. Prerequisite Checklist & Gate Status

| Prerequisite Item | Required Status | Verified Current State | Prerequisite Satisfied? |
|---|---|---|:---:|
| **Phase 4 Backend Foundation** | Accepted by Business Owner (Gate B) | Signed October 21, 2026 (`06_PHASE_4_ACCEPTANCE_PACKAGE.md`) | **YES** |
| **Phase 3 Governance Items** | OPEN-01, OPEN-02, OPEN-03 Ratified | All 3 resolved and ratified (`00_CENTRAL_GOVERNANCE_REGISTER.md`) | **YES** |
| **Phase 5 Schema Scope Approval** | CR-PH5-001 Formally Approved | Approved October 21, 2026 (`CHANGE_REQUEST_CR_PH5_001_SCHEMA_SCOPE.md`) | **YES** |
| **Phase 5 Commencement Authorization**| Gate C Formally Signed | Signed October 21, 2026 (`PHASE_5_AUTHORIZATION_REQUEST.md`) | **YES** |
| **Phase 5 Technical Deliverables** | CLI Runner, Shim, 28 Blueprints, Tests | 100% complete; 55/55 tests passed (221 assertions) | **YES** |
| **Phase 5 Formal Business Acceptance** | **Gate D Decision Recorded** | **ACCEPTED IN PRINCIPLE** (Business Owner Directive: *"PHASE 5 ACCEPTED, DESPITE OUTSTANDING LIVE MYSQL STAGING VALIDATION."*) | **HELD (Formal Signatory & Staging Validation Outstanding)** |

### 2.1 Staging Infrastructure & Live MySQL Prerequisites
Before Phase 6 administrative authentication can be deployed or verified on a staging server:
1. **Staging MySQL 8.0+ Instance Provisioned:** Dedicated database service active on port 3306 with InnoDB and `utf8mb4_unicode_ci`.
2. **Phase 5 MySQL 8.x Staging Validation Plan Executed:** Completion of the 6 validation steps defined in `docs/phase-05/04_PHASE_5_ACCEPTANCE_PACKAGE.md` Section 5.
3. **Dedicated Staging User Privileges:** Staging database user must hold `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`, `DROP`, `INDEX`, `REFERENCES` privileges.
4. **Environment Secret Hygiene:** Staging `.env` configured with cryptographically random 32-byte `APP_KEY` for AES-256-GCM encryption of TOTP secrets in `admin_mfa_secrets`.
5. **Initial Bootstrap Super-Admin Seed Plan:** Controlled CLI seeder script to provision the initial bootstrap administrator account (`users`, `user_roles`, `roles`) with temporary single-use password requiring immediate rotation upon first login.

## 3. Concrete Phase 6 Implementation Checklist

### 3.1 Admin Authentication & Secure Password Verification
- [x] **Password Hashing Engine:**
  - Implemented in `Voak\Health\Security\PasswordHasher` supporting `PASSWORD_ARGON2ID` (with fallback to `PASSWORD_BCRYPT` with cost factor 12).
  - Implemented `password_needs_rehash()` check during authentication to seamlessly upgrade legacy algorithm hashes.
  - Enforces password entropy standards (minimum 12 characters, uppercase, lowercase, numbers, symbols) via `enforceEntropy()` and `validateStrength()`.
- [x] **Credential Verification Service:**
  - Implemented in `Voak\Health\Auth\AuthenticationService::authenticate(\PDO $pdo, string $email, string $password, string $ip, string $ua)`.
  - Constant-time verification using `hash_equals()` and `password_verify()` mitigating side-channel timing attacks.
  - Queries against canonical `users` table: verifies `is_active === 1`, `deleted_at IS NULL`.
- [x] **Account Lockout & Brute-Force Defense (`FR-SEC-002`):**
  - Tracks `failed_login_attempts` in `users` table.
  - Locks account for 15 minutes (`lockout_until = CURRENT_TIMESTAMP + 15m`) upon 5 consecutive failed attempts.
  - Returns generic security error message (`Invalid credentials or account locked`) preventing username enumeration.
  - Resets `failed_login_attempts` to 0 upon successful authenticated login.
- [x] **Password Reset Lifecycle (`FR-ADM-001` / `password_resets`):**
  - `POST /api/v1/admin/auth/password-reset/request`: Generates cryptographically secure 256-bit token (`bin2hex(random_bytes(32))`), stores SHA-256 hash in `password_resets.token_hash` with 1-hour expiration (`expires_at = NOW() + 1h`).
  - Prepares transactional outbox dispatch via `email_queue`.
  - `POST /api/v1/admin/auth/password-reset/confirm`: Validates token hash, enforces new password entropy, updates `users.password_hash`, invalidates token (`used_at = CURRENT_TIMESTAMP`), revokes all active sessions in `admin_sessions`, and logs audit entry.

### 3.2 Session Lifecycle & Cookie Security (`FR-SEC-001`, `FR-SEC-003`)
- [x] **High-Security Session Cookie Configuration:**
  - Cookie name: `voak_admin_session`.
  - Path scope: `Path=/api/v1/admin` (RFC 6265 compliant; completely isolated from public website and APIs).
  - Flags: `Secure` (HTTPS only in production), `HttpOnly` (XSS mitigation), `SameSite=Lax` (CSRF defense-in-depth), `Max-Age=28800`.
  - Absolute session lifetime: `Max-Age=28800` (8 hours).
- [x] **Session Database Entity & Token Generation:**
  - Cryptographically secure 256-bit random tokens via `Crypto::randomToken(32)` represented as 64-character hex strings.
  - Stores session in `admin_sessions` table: `id`, `user_id`, `csrf_token_hash`, `ip_address`, `user_agent`, `created_at`, `last_activity_at`, `expires_at`.
- [x] **Session Expiry & Invalidation Lifecycle:**
  - Idle timeout: 15 minutes of inactivity (`last_activity_at < NOW() - 15 minutes`) invalidates session.
  - Absolute timeout: 8 hours maximum duration (`created_at < NOW() - 8 hours`) terminates session regardless of activity.
  - Explicit logout endpoint `POST /api/v1/admin/auth/logout` deletes active session record and clears cookie via expired `Set-Cookie`.
  - Concurrent session limit: prunes oldest session when active sessions for user exceed 3.

### 3.3 Role-Based Access Control (RBAC) & Permission Enforcement (`FR-ADM-003`)
- [x] **Canonical RBAC Dictionary & Junction Resolution:**
  - Implements the 5 canonical roles: `super_admin`, `website_admin`, `content_editor`, `bd_manager`, `hr_manager`.
  - Implements the complete 45-permission taxonomy across all administrative domains.
  - Loads active permissions from junction tables `user_roles` -> `roles` -> `role_permissions` -> `permissions`.
  - Idempotent database seeder `RbacManager::seedRbac()`.
- [x] **Authorization Middleware (`RequirePermissionMiddleware`):**
  - Parameter-based middleware attached to admin routes: `RequirePermissionMiddleware::with('permission_slug')`.
  - `super_admin` bypass: `super_admin` inherently possesses all canonical permissions.
  - Rejects unauthorized requests with standardized `HTTP 403 Forbidden` carrying error code `ERR_AUTH_FORBIDDEN`.

### 3.4 Multi-Factor Authentication (MFA / TOTP) Engine (`FR-SEC-001`)
- [x] **RFC 6238 TOTP Implementation:**
  - Zero-dependency `Voak\Health\Security\TotpEngine` (HMAC-SHA1, 30-second time-step, 6-digit dynamic codes).
  - Window drift allowance: $\pm 1$ step (verifying $t-1$, $t$, and $t+1$ to tolerate 30-second client clock drift).
- [x] **Encrypted Secret Storage (`admin_mfa_secrets`):**
  - Symmetric encryption: AES-256-GCM authenticated encryption using platform master key (`APP_KEY` / `MFA_ENCRYPTION_KEY`).
  - Storage format: `base64(iv . tag . ciphertext)` isolated in `admin_mfa_secrets` table.
- [x] **MFA Two-Step Login Flow:**
  - `POST /api/v1/admin/auth/login` validates credentials; if MFA is active, issues temporary HMAC-signed pre-auth token (TTL: 5 minutes) without establishing `voak_admin_session`.
  - `POST /api/v1/admin/auth/mfa/verify` accepts pre-auth token + 6-digit TOTP code; upon validation, provisions authenticated session cookie.
- [x] **Emergency Recovery Backup Codes:**
  - Generates 8 single-use 10-character alphanumeric backup codes during MFA enrollment.
  - Stores backup codes as individual SHA-256 hashes in `admin_mfa_secrets.backup_codes_json`.
  - `POST /api/v1/admin/auth/mfa/recovery` validates backup code, burns code as consumed, and provisions session.

### 3.5 Login Rate Limiting, Audit Logging & CSRF Protection
- [x] **Rate Limiting Middleware (`RateLimitMiddleware`):**
  - IP-based rate limiting on `/api/v1/admin/auth/*`: maximum 10 requests per minute per IP address.
  - Returns `HTTP 429 Too Many Requests` with `Retry-After` header.
- [x] **CSRF Token Lifecycle (`CsrfProtectionMiddleware`):**
  - Unauthenticated CSRF bootstrap endpoint: `GET /api/v1/admin/auth/csrf`.
  - Verifies `X-CSRF-Token` header on all mutation methods (`POST`, `PUT`, `PATCH`, `DELETE`).
- [x] **Immutable Audit Logging (`AuditLogger`):**
  - Records audit entries for all authentication events:
    - `AUTH_LOGIN_SUCCESS`, `AUTH_LOGIN_FAILURE`, `AUTH_ACCOUNT_LOCKED`
    - `AUTH_MFA_SUCCESS`, `AUTH_MFA_FAILURE`, `AUTH_RECOVERY_USED`
    - `AUTH_SESSION_EXPIRED`, `AUTH_LOGOUT`, `AUTH_PASSWORD_RESET_REQUEST`, `AUTH_PASSWORD_RESET_COMPLETE`
  - Record fields: `user_id`, `domain`, `action`, `ip_address`, `user_agent`, `entity_type`, `entity_id`, `diff_json`, `created_at`.
  - Computes running SHA-256 hash chain (`prev_row_hash` -> `row_hash`) starting from genesis hash anchor.
  - Implements `AuditLogger::verifyChain()` mathematical tamper verification.

### 3.6 Automated Security Test Suite
- [x] **Unit Tests:**
  - `PasswordHasherTest`: Hash creation, verification, Bcrypt fallback, rehash detection, entropy validation.
  - `CryptoTest`: AES-256-GCM authenticated encryption/decryption, tampered payload rejection, constant-time comparison.
  - `TotpEngineTest`: Base32 encoding/decoding, RFC 6238 dynamic code generation, clock drift window, invalid code rejection.
  - `AuditLoggerTest`: Chained SHA-256 hash generation, genesis hash link, `verifyChain()` tamper detection.
  - `RbacManagerTest`: 5 canonical roles, full permission matrix, Super Admin authority, least privilege enforcement.
  - `SessionManagerTest`: Token generation, 15m idle timeout, 8h max duration, concurrency limits, cookie headers.
  - `PasswordResetServiceTest`: Token generation, 1h expiration, single-use invalidation, user session revocation.
  - `TotpServiceTest`: MFA enrollment, AES-256-GCM secret encryption, code verification, single-use backup code consumption.
  - `AuthenticationServiceTest`: Active/inactive login, failed attempt tracking, 5-attempt lockout, MFA challenge handoff.
- [x] **Integration Tests:**
  - `AdminAuthApiTest`: End-to-end API testing through `App::handle()` for login, logout, me, CSRF, and rate limiting.
  - `CliSuperAdminTest`: CLI Super Admin provisioner with entropy check, argument security prohibition, and audit trail.
  - `SecurityHardeningTest`: Comprehensive security hardening & abuse defense tests (pre-auth challenge HMAC signing, replay defense, missing production key fail-safe, persistent advisory-locked rate limiting, forwarded IP spoofing defense without trusted proxies, full-row audit chain tamper detection, atomic token & recovery code consumption under concurrency, lockout bypass defense, route-level RBAC middleware enforcement, and test-only reset token concealment).

### 3.7 Formal Phase 6 Acceptance Criteria (Gate E Preview)
| # | Acceptance Criterion | Verification Method | Standard / Threshold | Status |
|:---:|---|---|---|:---:|
| **1** | Password Hashing Security | Unit Test | Argon2id default (fallback Bcrypt cost 12), entropy >= 12 chars | **VERIFIED** |
| **2** | Session Cookie Isolation | HTTP Integration Test | `Path=/api/v1/admin; Secure; HttpOnly; SameSite=Lax; Max-Age=28800` | **VERIFIED** |
| **3** | Session Invalidation | Lifecycle Test | 15m idle timeout, 8h absolute cap, clean DB revocation on logout | **VERIFIED** |
| **4** | RBAC Enforcement | HTTP Smoke Test | All 5 canonical roles validated; unauthorized calls return HTTP 403 | **VERIFIED** |
| **5** | TOTP MFA Engine | Unit & Flow Test | RFC 6238 verified, AES-256-GCM encrypted secrets in `admin_mfa_secrets` | **VERIFIED** |
| **6** | MFA Emergency Recovery | Integration Test | Single-use backup codes hashed, consumed upon verification | **VERIFIED** |
| **7** | Brute-Force Lockout | Security Test | 5 failed attempts in 15m trigger 15m lockout with generic error | **VERIFIED** |
| **8** | Auth Rate Limiting | Rate Limit Test | Persistent `flock` rate limiting, max 10 requests/min per IP on auth routes | **VERIFIED** |
| **9** | Tamper-Evident Audit Logs| Cryptographic Test | Full-row cryptographically chained SHA-256 records logged in `audit_logs` | **VERIFIED** |
| **10** | Automated Test Pass | CI Workflow | 100% test pass on PHP 8.2 and PHP 8.3 with zero secrets | **VERIFIED (115/115 ROOT SUITE PASS, 60/60 PHASE 6 SUITE PASS)** |
| **11** | Pre-Auth HMAC Signing & Anti-Replay | Security Hardening Test | Dynamic challenge ID stored & atomically consumed; signature verified | **VERIFIED** |
| **12** | Secret Exposure Defense | Security & CLI Test | CLI prohibits `--password` argument; dev tokens concealed unless explicit | **VERIFIED** |
| **13** | Live Hostinger Staging Verification | Staging Environment | Validation on live Hostinger staging domain and MySQL database | **PENDING (Hostinger Infrastructure Provisioning Outstanding)** |
| **14** | Formal Gate E Signatory Acceptance | Physical Sign-Off | Formal governance sign-off package with physical signatory identity | **PENDING (Awaiting Live Staging & Stakeholder Review)** |

---

## 4. Phase 6 Delivery Artifacts & Directory Structure

Upon authorization under Gate D, implementation will establish the following structure:

```
backend/
├── src/
│   ├── Auth/
│   │   ├── AuthenticationService.php
│   │   ├── SessionManager.php
│   │   ├── TotpService.php
│   │   ├── PasswordResetService.php
│   │   └── RbacManager.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Admin/
│   │   │       ├── AuthController.php
│   │   │       ├── MfaController.php
│   │   │       └── PasswordResetController.php
│   │   └── Middleware/
│   │       ├── AdminAuthenticate.php
│   │       ├── RequirePermission.php
│   │       ├── CsrfProtection.php
│   │       └── RateLimit.php
│   └── Security/
│       ├── PasswordHasher.php
│       ├── TotpEngine.php
│       ├── Crypto.php
│       └── AuditLogger.php
└── tests/
    └── Unit/
        ├── Auth/
        │   ├── PasswordHasherTest.php
        │   ├── TotpEngineTest.php
        │   ├── SessionManagerTest.php
        │   └── RbacManagerTest.php
        └── Security/
            ├── RateLimitTest.php
            └── AuditLoggerTest.php
```

---

## 5. Formal Gate D Dependency Statement & Variance Scope

```
================================================================================
PHASE 6 COMMENCEMENT GATE CONDITION:
STATUS: ACTIVE IN-FLIGHT DEVELOPMENT ON MAIN (GV-PH6-001)
GOVERNING STATE: Direct-to-main development authorized via Business Owner directive
STAGING / DEPLOYMENT: Strictly gated pending Hostinger staging & Gate E
================================================================================
```

Engineering is authorized by Business Owner directive (October 21, 2026) to implement Phase 6 components directly on `main` under isolated testing. Staging deployment and formal Gate E acceptance remain governed by the project governance framework.
