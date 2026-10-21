# Governance Variance Request: GV-PH6-001

**Document Reference:** `DOC-GV-PH6-001`  
**Variance ID:** `GV-PH6-001`  
**Title:** Phase 6 Initial Development Authorization Under Isolated Environment  
**Project:** Voak Health Care Corporate B2B Platform  
**Target Phase:** Phase 6 — Admin Authentication, RBAC, Sessions & MFA  
**Date Submitted:** October 21, 2026  
**Status:** **PROPOSED — PENDING FORMAL BUSINESS OWNER APPROVAL**  
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
   - Under Project Governance Rules 1, 9, and 10, full formal execution of Gate D requires individual signatory identity (name/title) and live validation on the designated Hostinger staging environment.
   - Consequently, Phase 6 commencement on `main` is currently held/blocked pending those requirements.

---

## 2. Proposed Variance Scope & Technical Rationale

To prevent technical schedule stalling while physical signatory execution and Hostinger live staging instance provisioning are completed, this variance requests permission to proceed with Phase 6 initial development under strict isolation controls:

1. **Feature Branch Isolation:** All Phase 6 development will take place on a dedicated feature branch (`feature/phase6-admin-auth`) rather than directly committing incomplete features to `main`.
2. **Isolated Database Persistence:** Unit tests, integration tests, and negative security tests for Phase 6 will run against isolated SQLite memory mode and ephemeral MySQL 8.0 CI service containers.
3. **Zero Production Mutation:** No live Hostinger or production databases will be accessed, configured, or modified.
4. **No Premature Main Merge:** The feature branch will **not** be merged into `main` until all Phase 6 implementation requirements, automated security tests, and CI runs are 100% verified and reviewed.

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
| **RBAC Authorization** | 5 canonical roles (`super_admin`, `compliance_officer`, `operations_manager`, `content_editor`, `clinical_advisor`) and complete 45-permission matrix with `RbacMiddleware`. |
| **MFA / TOTP Engine** | RFC 6238 compliant TOTP verification (30s step, ±1 window drift), AES-256-GCM encryption of MFA secrets with PBKDF2/SHA-256 key derivation in `admin_mfa_secrets`. Single-use emergency recovery codes. |
| **Audit Logging Service** | Tamper-evident SHA-256 cryptographically chained ledger entries in `audit_logs`. |
| **Super Admin CLI Provisioner** | CLI command to provision bootstrap Super Admin without hardcoded credentials. |
| **Automated Test Suite** | Comprehensive unit, integration, and negative security tests verifying all security failure modes. |

---

## 4. Governance Action & Approval Block

> [!IMPORTANT]
> **STATUS:** PROPOSED — This variance request is documented transparently in repository history. Engineering will not treat this variance as approved until the Business Owner explicitly reviews and grants authorization.

```markdown
================================================================================
GOVERNANCE VARIANCE RECORD: GV-PH6-001
================================================================================

[ ] VARIANCE APPROVED:
    Permission is granted to commence Phase 6 implementation on a dedicated
    feature branch using isolated testing environments while formal signatory
    credentials and Hostinger staging validation are finalized.

[ ] VARIANCE REJECTED / DEFERRED:
    Engineering must remain paused on Phase 6 until full Gate D formal signature
    and live Hostinger staging validation are completed.

Business Owner / Authorized Signatory: _________________________________________
Title / Role:                           _________________________________________
Signature:                              _________________________________________
Date:                                   _________________________________________
================================================================================
```
