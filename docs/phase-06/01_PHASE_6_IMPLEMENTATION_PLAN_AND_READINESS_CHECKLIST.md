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

> [!CAUTION]
> **GOVERNANCE HARD STOP: PHASE 6 IMPLEMENTATION IS BLOCKED PENDING GATE D**  
> In accordance with **Rules 1, 9, and 10 of the Project Governance Framework**, no Phase 6 production source code, login controllers, authentication middleware, or session cookies may be written or committed until **Gate D (Phase 5 Formal Business Acceptance)** is reviewed and signed off by the authorized Business Owner or Steering Committee.
>
> This document establishes the technical blueprint, component interfaces, and verification requirements so that engineering can commence immediately upon receipt of Gate D sign-off.

---

## 2. Prerequisite Checklist & Gate Status

| Prerequisite Item | Required Status | Verified Current State | Prerequisite Satisfied? |
|---|---|---|:---:|
| **Phase 4 Backend Foundation** | Accepted by Business Owner (Gate B) | Signed October 21, 2026 (`06_PHASE_4_ACCEPTANCE_PACKAGE.md`) | **YES** |
| **Phase 3 Governance Items** | OPEN-01, OPEN-02, OPEN-03 Ratified | All 3 resolved and ratified (`00_CENTRAL_GOVERNANCE_REGISTER.md`) | **YES** |
| **Phase 5 Schema Scope Approval** | CR-PH5-001 Formally Approved | Approved October 21, 2026 (`CHANGE_REQUEST_CR_PH5_001_SCHEMA_SCOPE.md`) | **YES** |
| **Phase 5 Commencement Authorization**| Gate C Formally Signed | Signed October 21, 2026 (`PHASE_5_AUTHORIZATION_REQUEST.md`) | **YES** |
| **Phase 5 Technical Deliverables** | CLI Runner, Shim, 28 Blueprints, Tests | 100% complete; 55/55 tests passed (221 assertions) | **YES** |
| **Phase 5 Formal Business Acceptance** | **Gate D Signed by Business Owner** | **PENDING REVIEW** (`docs/phase-05/04_PHASE_5_ACCEPTANCE_PACKAGE.md`) | **NO (BLOCKING)** |

### 2.1 Staging Infrastructure & Live MySQL Prerequisites
Before Phase 6 administrative authentication can be deployed or verified on a staging server:
1. **Staging MySQL 8.0+ Instance Provisioned:** Dedicated database service active on port 3306 with InnoDB and `utf8mb4_unicode_ci`.
2. **Phase 5 MySQL 8.x Staging Validation Plan Executed:** Completion of the 6 validation steps defined in `docs/phase-05/04_PHASE_5_ACCEPTANCE_PACKAGE.md` Section 5.
3. **Dedicated Staging User Privileges:** Staging database user must hold `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`, `DROP`, `INDEX`, `REFERENCES` privileges.
4. **Environment Secret Hygiene:** Staging `.env` configured with cryptographically random 32-byte `APP_KEY` for AES-256-GCM encryption of TOTP secrets in `admin_mfa_secrets`.
5. **Initial Bootstrap Super-Admin Seed Plan:** Controlled CLI seeder script to provision the initial bootstrap administrator account (`users`, `user_roles`, `roles`) with temporary single-use password requiring immediate rotation upon first login.

## 3. Concrete Phase 6 Implementation Checklist

### 3.1 Admin Authentication & Secure Password Verification
- [ ] **Password Hashing Engine:**
  - Implement `Voak\Health\Security\PasswordHasher` supporting `PASSWORD_ARGON2ID` (with fallback to `PASSWORD_BCRYPT` with cost factor 12 if Argon2id is unsupported in environment).
  - Implement `password_needs_rehash()` check during authentication to seamlessly upgrade legacy algorithm hashes.
  - Enforce password entropy standards (minimum 12 characters, uppercase, lowercase, numbers, symbols) rejecting known compromised passwords.
- [ ] **Credential Verification Service:**
  - Implement `Voak\Health\Auth\AuthenticationService::authenticate(string $email, string $password): AuthResult`.
  - Constant-time verification using `hash_equals()` and `password_verify()` to prevent side-channel timing attacks.
  - Query against canonical `users` table: verify `status === 'active'`, `deleted_at IS NULL`.
- [ ] **Account Lockout & Brute-Force Defense (`FR-SEC-002`):**
  - Track `failed_login_attempts` in `users` table.
  - Lock account for 30 minutes (`locked_until = NOW() + INTERVAL 30 MINUTE`) upon 5 consecutive failed attempts within 15 minutes.
  - Return generic error message (`Invalid credentials or account locked`) to prevent username enumeration.
  - Reset `failed_login_attempts` to 0 upon successful authenticated login.

### 3.2 Session Lifecycle & Cookie Security (`FR-SEC-001`, `FR-SEC-003`)
- [ ] **High-Security Session Cookie Configuration:**
  - Cookie name: `voak_admin_session`.
  - Path scope: `Path=/api/v1/admin` (RFC 6265 compliant; completely isolated from public website and APIs).
  - Flags: `Secure` (HTTPS only), `HttpOnly` (XSS mitigation), `SameSite=Lax` (CSRF defense-in-depth), `Priority=High`.
  - Absolute session lifetime: `Max-Age=28800` (8 hours).
- [ ] **Session Database Entity & Token Generation:**
  - Cryptographically secure 256-bit random tokens via `random_bytes(32)` represented as 64-character hex strings (`tok_...`).
  - Store session in `admin_sessions` table: `id`, `user_id`, `token_hash` (`hash('sha256', $token)`), `ip_address`, `user_agent`, `created_at`, `last_activity_at`, `expires_at`.
- [ ] **Session Expiry & Invalidation Lifecycle:**
  - Idle timeout: 15 minutes of inactivity (`last_activity_at < NOW() - 15 minutes`) invalidates session.
  - Absolute timeout: 8 hours maximum duration (`created_at < NOW() - 8 hours`) terminates session regardless of activity.
  - Explicit logout endpoint `POST /api/v1/admin/auth/logout` deletes active session record and clears cookie via expired `Set-Cookie`.
  - Concurrent session limit: configurable setting (default: 3 concurrent sessions per user; oldest revoked).

### 3.3 Role-Based Access Control (RBAC) & Permission Enforcement (`FR-ADM-003`)
- [ ] **Canonical RBAC Dictionary & Junction Resolution:**
  - Support the 5 canonical roles: `super_admin`, `compliance_officer`, `content_editor`, `lead_manager`, `hr_recruiter`.
  - Load active permissions from junction tables `user_roles` -> `roles` -> `role_permissions` -> `permissions`.
  - Cache user permissions in request attribute pipeline for $O(1)$ lookups during route execution.
- [ ] **Authorization Middleware (`RequirePermissionMiddleware`):**
  - Attribute/parameter-based middleware attached to admin routes: `['middleware' => [AuthMiddleware::class, RequirePermissionMiddleware::with('cms:pages:edit')]]`.
  - `super_admin` bypass: `super_admin` inherently possesses all 45 permissions.
  - Reject unauthorized requests with standardized `HTTP 403 Forbidden` carrying error code `ERR_AUTH_FORBIDDEN` and missing permission keys.

### 3.4 Multi-Factor Authentication (MFA / TOTP) Engine (`FR-SEC-001`)
- [ ] **RFC 6238 TOTP Implementation:**
  - Implement zero-dependency `Voak\Health\Security\TotpEngine` (HMAC-SHA1, 30-second time-step, 6-digit dynamic codes).
  - Window drift allowance: $\pm 1$ step (verifying $t-1$, $t$, and $t+1$ to tolerate 30-second client clock drift).
- [ ] **Encrypted Secret Storage (`admin_mfa_secrets`):**
  - Symmetric encryption: AES-256-GCM authenticated encryption using platform master key (`APP_KEY`).
  - Storage format: `base64(iv . tag . ciphertext)` isolated in `admin_mfa_secrets` table.
- [ ] **MFA Two-Step Login Flow:**
  - `POST /api/v1/admin/auth/login` validates credentials; if MFA is active, issues temporary pre-auth token (`preauth_...`, TTL: 5 minutes) without establishing `voak_admin_session`.
  - `POST /api/v1/admin/auth/mfa/verify` accepts pre-auth token + 6-digit TOTP code; upon validation, provisions authenticated session cookie.
- [ ] **Emergency Recovery Backup Codes:**
  - Generate 8 single-use 10-character alphanumeric backup codes during MFA enrollment.
  - Store backup codes as individual Bcrypt/Argon2 hashes in `admin_mfa_secrets.backup_codes_json`.
  - `POST /api/v1/admin/auth/mfa/recovery` validates backup code, marks code as consumed, and dispatches urgent email notification.

### 3.5 Login Rate Limiting, Audit Logging & CSRF Protection
- [ ] **Rate Limiting Middleware (`RateLimiter`):**
  - IP-based rate limiting on `/api/v1/admin/auth/*`: maximum 10 requests per minute per IP address.
  - Return `HTTP 429 Too Many Requests` with `Retry-After` header.
- [ ] **CSRF Token Lifecycle:**
  - Unauthenticated CSRF bootstrap endpoint: `GET /api/v1/admin/auth/csrf`.
  - Verify `X-CSRF-Token` header on all mutation methods (`POST`, `PUT`, `PATCH`, `DELETE`).
- [ ] **Immutable Audit Logging (`audit_logs`):**
  - Record audit entries for all authentication events:
    - `AUTH_LOGIN_SUCCESS`, `AUTH_LOGIN_FAILURE`, `AUTH_ACCOUNT_LOCKED`
    - `AUTH_MFA_SUCCESS`, `AUTH_MFA_FAILURE`, `AUTH_RECOVERY_USED`
    - `AUTH_SESSION_EXPIRED`, `AUTH_LOGOUT`, `AUTH_PASSWORD_RESET_REQUEST`, `AUTH_PASSWORD_RESET_COMPLETE`
  - Record fields: `user_id`, `action`, `resource_type`, `resource_id`, `ip_address`, `user_agent`, `payload_hash`, `created_at`.
  - Compute running SHA-256 hash chain (`previous_hash` -> `record_hash`) guaranteeing tamper-evidence.

### 3.6 Automated Security Test Suite
- [ ] **Unit Tests:**
  - `PasswordHasherTest`: Hash creation, verification, cost tuning, rehash detection.
  - `TotpEngineTest`: Known RFC 6238 test vectors, clock drift window, invalid code rejection.
  - `EncryptedSecretTest`: AES-256-GCM encryption/decryption, tampered ciphertext detection.
- [ ] **Integration Tests:**
  - `AuthenticationFlowTest`: Full login -> pre-auth -> MFA verify -> session cookie issuance.
  - `AccountLockoutTest`: 5 failed attempts trigger 30m lockout; subsequent attempts rejected.
  - `SessionLifecycleTest`: Cookie attributes, 15m idle timeout, 8h max duration, logout revocation.
  - `RbacAuthorizationTest`: Verification of all 5 roles across route permissions (403 Forbidden on violation).
  - `AuditLogChainTest`: Cryptographic hash chain validation across successive auth events.

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

## 5. Formal Gate D Dependency Statement

```
================================================================================
PHASE 6 COMMENCEMENT GATE CONDITION:
STATUS: STRICTLY BLOCKED
DEPENDENCY: Execution of Gate D in docs/phase-05/03_PHASE_5_COMPLETION_REPORT.md
================================================================================
```

Engineering will not commit or deploy Phase 6 source code until the Business Owner completes and signs Gate D.
