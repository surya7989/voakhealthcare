# Phase 5 — Native MySQL CI Validation

**Purpose:** Add repeatable native MySQL 8.0 lifecycle testing to complement the existing SQLite-backed automated suite.

## Scope of the CI job

The `MySQL 8.0 Migration Lifecycle` job runs against an ephemeral GitHub Actions MySQL service and uses only the explicitly named disposable database `voak_staging_ci`. The integration test refuses to run unless its CI guard is enabled, the configured host is local, and the target database has the exact expected disposable name. It also refuses to proceed if the target schema is not empty.

The job is intended to verify:

1. Discovery and fresh application of all 28 canonical migration blueprints.
2. Idempotent repeated migration execution.
3. The InnoDB engine and `utf8mb4_unicode_ci` collation for all 28 domain tables.
4. Creation of relational foreign-key constraints.
5. Single-step rollback and clean reapplication.
6. Full rollback and full reapplication.
7. The Phase 0 root compatibility shim reporting `28 Applied, 0 Pending`.

## Evidence status and governance boundary

**Status (October 21, 2026):** **VERIFIED COMPLETE IN CI**  
- **Verified Workflow Runs:**
  - **PR #2 Verification Run:** [Run #39548173212](https://github.com/surya7989/voakhealthcare/actions/runs/39548173212) (commit `8d71496`) — **SUCCESS (100% Green)**
  - **Branch Push Run:** [Run #39548169013](https://github.com/surya7989/voakhealthcare/actions/runs/39548169013) (commit `8d71496`) — **SUCCESS (100% Green)**
  - **Main Branch Push Run:** [Run #39548600051](https://github.com/surya7989/voakhealthcare/actions/runs/39548600051) (commit `8d71496`) — **SUCCESS (100% Green)**
  - **Prior Validation Runs:** [Run #39547633302](https://github.com/surya7989/voakhealthcare/actions/runs/39547633302) (PR #2, commit `9eb75c1`) / [Run #39547628374](https://github.com/surya7989/voakhealthcare/actions/runs/39547628374) (push) — **SUCCESS**
- **Verified Jobs per Run:**
  - `MySQL 8.0 Migration Lifecycle` (Job `119051006571` on PR #2): **SUCCESS**
  - `Backend Tests (PHP 8.2)` (Job `119051006412` on PR #2): **SUCCESS**
  - `Backend Tests (PHP 8.3)` (Job `119051006558` on PR #2): **SUCCESS**
- **Empirical Results on Native MySQL 8.0:**
  - `[1/7]` All 28 canonical schema blueprints discovered and applied cleanly on MySQL 8.0: **PASS**
  - `[2/7]` Idempotent repeat execution (zero duplicate migration mutations): **PASS**
  - `[3/7]` All 28 domain tables verified with `InnoDB` storage engine and `utf8mb4_unicode_ci` collation: **PASS**
  - `[4/7]` Relational foreign-key constraints verified across domain entities: **PASS**
  - `[5/7]` Single-step rollback (dropping migration 28 `audit_logs`) and clean reapplication: **PASS**
  - `[6/7]` Full rollback of all 28 migrations and clean reapplication: **PASS**
  - `[7/7]` Final schema state verification (all critical tables present): **PASS**
  - `Root Compatibility Shim`: `php database/migrate.php status` executed against live MySQL reporting `Summary: 28 Applied, 0 Pending`: **PASS**

### Critical Governance Disclosures
A passing CI job is native-engine regression evidence against an ephemeral GitHub Actions container, not designated Hostinger staging environment evidence. It demonstrates engine compatibility but does not substitute for the designated live staging validation required by Section 5 of `docs/phase-05/04_PHASE_5_ACCEPTANCE_PACKAGE.md`. It does not prove Hostinger production readiness, supply formal signatory credentials, or automatically authorize Phase 6 deployment.

No production database was targeted or modified by this job. Phase 6 implementation remains governed by the Phase 6 readiness gate and governance framework.
