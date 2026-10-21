# Test Architecture and Verification Strategy

**Document Reference:** `docs/phase-02/14_TEST_ARCHITECTURE_AND_VERIFICATION.md`  
**Phase:** Phase 2 of 31 — Complete System Architecture  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Executing Role:** Software Quality Director & Automated Testing Lead  
**Baseline Authority:** Accepted Phase 1 Acceptance Test Catalog (`docs/phase-01/17_PHASE_1_ACCEPTANCE_TEST_CATALOG.md`)  
**Status:** **AUTHORITATIVE ARCHITECTURAL SPECIFICATION** (Test Strategy Only — Execution Deferred to Phases 27–30)

---

## 1. Quality Architecture & Testing Pyramid

The testing architecture provides a multi-layered verification harness spanning unit, integration, contract, security, accessibility, and end-to-end testing:

```
                                      ▲
                                     / \
                                    /   \
                                   / E2E \   ◄── Playwright (All 22 Public Pages & 19 Admin Modules)
                                  /       \      (Axe-Core Accessibility, Responsive, Cross-Browser)
                                 /─────────\
                                / Integration\  ◄── PHPUnit API Integration & DB Repositories
                               /  & Contract  \     (MySQL Test DB, REST Contracts, State Machines)
                              /────────────────\
                             /   Component &    \  ◄── Vitest & React Testing Library
                            /    Unit Testing    \     (Zod Validators, Spring Animations, Modals)
                           /──────────────────────\
                          /   Static Analysis &    \ ◄── PHPStan Level 8, ESLint, TypeScript Strict,
                         /     Secret Scanning      \    Gitleaks (Zero Secrets in Git)
                        +────────────────────────────+
```

> [!IMPORTANT]
> **Documentation & Architectural Standard:**  
> Tests defined during Phase 2 are structural specifications and quality gates. In accordance with enterprise software engineering standards, no tests are claimed as passing or executed against application code until those application modules are formally implemented in their assigned roadmap phases.

---

## 2. Test Execution Tiers & Framework Specifications

### 2.1 Tier 1: Static Analysis & Secret Scanning (Pre-Commit / CI)
- **PHP Static Analysis:** PHPStan set to Level 8 (enforcing strict types, zero untyped arrays, and verified nullability).
- **TypeScript Static Analysis:** `tsc --noEmit` with `strict: true` across all frontend codebases.
- **Secret Scanning:** `gitleaks` scanner executing in pre-commit hooks and GitHub Actions to verify zero committed credentials (`FR-SEC-006`, `TEST-SEC-006`).

### 2.2 Tier 2: PHP Unit & Integration Testing (PHPUnit 10+)
- **Test Database Isolation:** All database integration tests execute against an isolated test database (`voak_test_db`) running on localhost or CI. **Testing against production or existing Hostinger databases is strictly forbidden.**
- **Transactional Rollbacks:** Integration test suites wrap each test method in an isolated transaction (`PDO::beginTransaction()` / `PDO::rollBack()`), ensuring zero cross-test state pollution.
- **Repository & State Machine Tests:** Validates strict DAG transitions on Inquiry, RFP, Partnership, and Recruitment pipelines.

### 2.3 Tier 3: React Component Testing (Vitest & RTL)
- **Vitest:** Executes fast unit tests on client components, hooks, and utilities.
- **React Testing Library:** Asserts accessible role selection, modal focus traps, keyboard event handlers (`Escape` to dismiss), and form validation error rendering.

### 2.4 Tier 4: End-to-End System Integration Testing (Playwright)
- **Headless Browser Automation:** Chrome, Firefox, and WebKit (Safari).
- **Mobile Viewport Emulation:** iPhone 14 (390x844px), iPad Pro (1024x1366px), Desktop (1440x900px).
- **Accessibility Testing:** Automated Axe-core integration asserting WCAG 2.2 Level AA compliance across all 22 public pages (`TEST-A11Y-001`).

---

## 3. Traceability to the 62 Canonical Acceptance Tests

Every one of the 62 canonical acceptance tests defined in [`17_PHASE_1_ACCEPTANCE_TEST_CATALOG.md`](../phase-01/17_PHASE_1_ACCEPTANCE_TEST_CATALOG.md) maps to an automated testing suite and target execution phase:

| Canonical Test Range | Total Tests | Automated Tooling | Target Execution Phase |
| :--- | :---: | :--- | :---: |
| **`TEST-PUB-001` – `TEST-PUB-023`** | **23** | Playwright E2E / Axe-core / cURL | Phase 27 (Public Website Integration) |
| **`TEST-ADM-001` – `TEST-ADM-019`** | **19** | Playwright E2E / PHPUnit RBAC Suite | Phase 27 (Admin Console Integration) |
| **`TEST-WF-001` – `TEST-WF-006`** | **6** | PHPUnit State Machine Suite | Phase 11, 12, 14, 16 (Workflow Testing) |
| **`TEST-SEC-001` – `TEST-SEC-007`** | **7** | PHPUnit Security Suite / Gitleaks | Phase 30 (Security Pentesting) |
| **`TEST-SEO-001` – `TEST-SEO-004`** | **4** | Playwright / cURL / Schema Validator | Phase 29 (SEO & Performance Auditing) |
| **`TEST-A11Y-001` – `TEST-A11Y-003`** | **3** | Axe-core / Playwright Animation Suite | Phase 28 (Accessibility Verification) |
| **Total Test Scenarios** | **62** | **100% Traceable to Canonical Catalog** | **Phases 4–31** |

---

## 4. Continuous Integration (CI) Workflow Specification

The CI pipeline runs on every pull request to ensure that no regressions reach the deployment branch:

```yaml
# .github/workflows/ci.yml (Architectural Specification)
name: Continuous Integration Quality Pipeline

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  static-analysis:
    name: Static Analysis & Security Scan
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Gitleaks Secret Scan
        uses: gitleaks/gitleaks-action@v2
      - name: PHPStan Static Analysis (Level 8)
        run: vendor/bin/phpstan analyse src tests --level=8
      - name: TypeScript Type Check
        run: npm run type-check

  backend-tests:
    name: PHP Backend Integration Tests
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: voak_test_db
          MYSQL_ROOT_PASSWORD: root
        ports: [3306:3306]
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP 8.3
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo, pdo_mysql, fileinfo, gd, openssl
      - name: Run Database Migrations
        run: php cli/migrate.php up --env=testing
      - name: Run PHPUnit Test Suite
        run: vendor/bin/phpunit --testdox

  frontend-tests:
    name: React Unit & Component Tests
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run Vitest Suite
        run: npm run test:unit
```
