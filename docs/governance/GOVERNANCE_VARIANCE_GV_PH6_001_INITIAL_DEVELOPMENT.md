# Governance Variance Request: GV-PH6-001

**Document Reference:** `DOC-GV-PH6-001`  
**Variance ID:** `GV-PH6-001`  
**Title:** Phase 6 Initial Development Authorization Under Isolated Environment  
**Project:** Voak Health Care Corporate B2B Platform  
**Target Phase:** Phase 6 — Admin Authentication, RBAC, Sessions & MFA  
**Date Submitted:** October 21, 2026  
**Status:** **AUTHORIZED BY BUSINESS OWNER DIRECTIVE (Direct-to-Main Development Scope)**  
**Authorization Date:** October 21, 2026  
**Governing Documents:**
- `docs/governance/00_CENTRAL_GOVERNANCE_REGISTER.md`
- `docs/phase-00/13_COMPLETE_32_PHASE_TRACEABILITY.md` (Project Rules 1, 9, 10)
- `docs/phase-05/04_PHASE_5_ACCEPTANCE_PACKAGE.md` (Gate D Business Acceptance)
- `docs/phase-06/01_PHASE_6_IMPLEMENTATION_PLAN_AND_READINESS_CHECKLIST.md`

---

## 1. Background & Context

1. **Phase 5 Technical Completion:**
   - 28 canonical schema blueprints implemented in `backend/database/migrations/`.
   - Canonical CLI runner implemented in `backend/cli/migrate.php` supporting `up`, `down`, `status`, `fresh`, `--sqlite`, `--steps=N`.
   - Root compatibility shim implemented in `database/migrate.php`.
   - Foundation test suite passes 100% (55/55 tests, 221 assertions).
2. **Native MySQL 8.0 CI Verification:**
   - Ephemeral MySQL 8.0 CI integration test executed and verified in GitHub Actions [Run #39548173212](https://github.com/surya7989/voakhealthcare/actions/runs/39548173212) (PR #2) and [Run #39548600051](https://github.com/surya7989/voakhealthcare/actions/runs/39548600051) (`main`).
   - All 28 domain tables verified with `InnoDB` storage engine and `utf8mb4_unicode_ci` collation.
   - Idempotency, foreign-key enforcement, single-step rollback, full rollback, and root compatibility shim status verified cleanly.
3. **Current Governance State:**
   - The Business Owner communicated the directive: *"DECISION: PHASE 5 ACCEPTED, DESPITE OUTSTANDING LIVE MYSQL STAGING VALIDATION."*
   - Phase 5 is recorded as **Accepted in Principle**.
   - On October 21, 2026, the Business Owner issued an explicit instruction: *"I want Phase 6 developed directly in the existing main branch. Do not create a separate Phase 6 feature branch or separate sub-branches. All verified fixes and completed components must be committed and pushed directly to main, with tests run throughout development."*

---

## 2. Authorized Scope & Strict Governance Boundaries

In response to the Business Owner's explicit directive, engineering is authorized to execute Phase 6 development directly on `main` subject to the following non-negotiable boundaries:

1. **Direct-to-Main Incremental Implementation:** Phase 6 components are implemented directly in `main`, but every commit must be verified locally with 100% passing tests, PHP syntax lint, and secret scanning prior to push.
2. **Isolated Database Persistence During Development:** Unit, integration, and security tests run against isolated SQLite memory instances and ephemeral GitHub Actions MySQL 8.0 service containers.
3. **Strict Production & Staging Safeguards:**
   - Zero live production database connections, schema mutations, or deployments are authorized.
   - Dedicated Hostinger live staging validation remains an outstanding requirement and is not bypassed or fabricated.
   - Zero credentials, API tokens, passwords, or `.env` secrets may enter git history.
4. **Gate E & Production Deployment Gating:** Full Phase 6 production deployment and Gate E acceptance remain strictly gated pending formal signatory execution and subsequent live staging validation.

---

## 3. Scope of Phase 6 Implementation Under This Variance

If approved, engineering will implement the complete Phase 6 specification defined in `docs/phase-02/06_AUTHENTICATION_RBAC_AND_SESSION_ARCHITECTURE.md`:

| Component | Technical Scope & Security Standard |
|---|---|
| **Password Hashing** | `PasswordHasher` with `PASSWORD_ARGON2ID` (with fallback to `PASSWORD_BCRYPT`, cost 12), entropy validation, legacy rehash triggers. |
| **Authentication Service** | `AuthenticationService` with timing-attack resistant `hash_equals()`, account state checks (`is_active = 1`), and failed attempt tracking. |
| **Account Lockout Protection** | Brute-force lockout (5 consecutive failed attempts = 15-minute lockout until `lockout_until`). |
| **Password Reset Engine** | Cryptographically secure 64-char single-use tokens, 1-hour expiry, constant-time token lookup (`password_resets` table). |
| **Session Management** | Cryptographically random 64-character session tokens, server-side tracking in `admin_sessions`, idle timeout (30m), absolute timeout (8h), secure cookie attributes (`HttpOnly`, `SameSite=Strict`, `Secure`). |
| **RBAC Authorization** | 5 canonical roles (`super_admin`, `website_admin`, `content_editor`, `bd_manager`, `hr_manager`) and complete 45-permission matrix with `RequirePermissionMiddleware`. |
| **MFA / TOTP Engine** | RFC 6238 compliant TOTP verification (30s step, ±1 window drift), AES-256-GCM encryption of MFA secrets with PBKDF2/SHA-256 key derivation in `admin_mfa_secrets`. Single-use emergency recovery codes. |
| **Audit Logging Service** | Tamper-evident SHA-256 cryptographically chained ledger entries in `audit_logs`. |
| **Super Admin CLI Provisioner** | CLI command to provision bootstrap Super Admin without hardcoded credentials. |
| **Automated Test Suite** | Comprehensive unit, integration, and negative security tests verifying all security failure modes. |

---

## 4. Governance Action & Approval Block

> [!NOTE]
> **STATUS:** AUTHORIZED VIA BUSINESS OWNER DIRECTIVE (October 21, 2026).
> Direct-to-main incremental implementation authorized under isolated testing. Staging deployment and formal Gate E remain gated pending Hostinger staging infrastructure and physical signatory execution.

```markdown
================================================================================
GOVERNANCE VARIANCE RECORD: GV-PH6-001
================================================================================

[X] VARIANCE AUTHORIZED (DIRECT-TO-MAIN DEVELOPMENT SCOPE):
    Permission granted via explicit Business Owner instruction on October 21, 2026:
    "I want Phase 6 developed directly in the existing main branch. Do not create
    a separate Phase 6 feature branch or separate sub-branches. All verified fixes
    and completed components must be committed and pushed directly to main, with
    tests run throughout development."

    Limitations:
    - Development occurs directly on 'main' with tests verified per commit.
    - Testing limited to isolated SQLite and ephemeral MySQL 8.0 CI service containers.
    - Zero production database mutation or Hostinger deployment.
    - Formal Gate D/E physical signatory identity and live Hostinger staging validation
      remain outstanding prerequisites prior to any production deployment.

Authorization Source: Business Owner Directive
Authorization Date:   October 21, 2026
================================================================================
```
