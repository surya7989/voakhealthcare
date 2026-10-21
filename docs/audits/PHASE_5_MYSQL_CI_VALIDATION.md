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

**Status at authoring:** Pending execution of the new CI workflow. Do not report these checks as passed until a specific completed workflow run proves that result.

A passing CI job is native-engine regression evidence, not designated staging evidence. It does not close the required live staging validation plan in `docs/phase-05/04_PHASE_5_ACCEPTANCE_PACKAGE.md`, prove production readiness, supply formal signatory identity, or automatically authorize Phase 6.

No production database is targeted or modified by this job. The staged Phase 6 security implementation remains governed by the Phase 6 readiness gate.
