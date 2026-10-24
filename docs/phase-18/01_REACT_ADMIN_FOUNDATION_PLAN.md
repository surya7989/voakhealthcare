# Phase 18 — React Admin Foundation Plan
## Voak Health Care Enterprise Web Application

**Document Identifier**: `DOC-PHASE18-PLAN-001`  
**Phase Identifier**: `PHASE-18` / `ADM-FE-001` / `FE-CORE-001`  
**Target Environment**: `frontend/` (React 18+ / TypeScript, Vite, Tailwind CSS v3)  
**Creation Date**: 2026-10-24  
**Author**: Antigravity Autonomous Agent  
**Status**: Ready for Implementation  

---

### 1. Executive Summary & Canonical Discovery

Following completion and full CI verification of Stage 2 (Phases 4 through 17 Backend Foundations, 401 tests / 2,032 assertions / 0 failures), the project transitions into **Stage 3: React Public Frontend and Admin Dashboard (Phases 18–26)**.

According to canonical repository documentation (`docs/phase-00/07_TECHNICAL_ARCHITECTURE.md`, `docs/phase-00/13_COMPLETE_32_PHASE_TRACEABILITY.md`, and `docs/phase-03/14_DESIGN_TO_REACT_COMPONENT_MAPPING.md`):
- **Official Phase Title**: **React Foundation** (Admin Foundation & Core Architecture)
- **Primary Objective**: Bootstrap the production-grade React 18+ / TypeScript frontend application within `frontend/` using Vite, configure Tailwind CSS v3 with the locked Phase 3 corporate design tokens, establish the application shell and routing architecture, build the unified typed API client with CSRF token interceptors and session credentials, implement authentication and RBAC context providers, and construct the generic atomic design system primitives.
- **Strict Scope Boundaries**: Phase 18 builds **reusable foundational infrastructure only**. It strictly **prohibits** premature implementation of the 19 complete business modules (Phase 26), public marketing pages (Phases 20–24), or progressive client mounting hooks (Phase 29).

---

### 2. Comprehensive Requirements Mapping (A through AL)

| Item | Dimension | Scope / Specification | Canonical Status |
|---|---|---|---|
| **A** | **Official Phase Name** | **Phase 18 — React Foundation** (React Admin Foundation) | Requires New Implementation |
| **B** | **Business Objective** | Enterprise React application bootstrap, design tokens, typed API client with CSRF interceptor, session/RBAC state providers, accessible UI primitives, and application shell | Requires New Implementation |
| **C** | **React / Framework** | React 18+ (`react`, `react-dom`) | Requires New Implementation |
| **D** | **TypeScript / JavaScript** | Strict TypeScript (`typescript`, `@types/react`, `@types/react-dom`, `tsconfig.json`) | Requires New Implementation |
| **E** | **Build Tool** | Vite (`vite`, `@vitejs/plugin-react`) | Requires New Implementation |
| **F** | **Package Manager** | `npm` (matching Node.js v26 / npm 11 on system and GitHub Actions CI) | Requires New Implementation |
| **G** | **Routing** | Client-side routing engine supporting browser history, nested layouts, protected routes, and 404 boundaries | Requires New Implementation |
| **H** | **Admin Application Shell** | Reusable layout shell: Sidebar, Header/Topbar, Breadcrumbs, Content Canvas, User Profile Action | Requires New Implementation |
| **I** | **Login / Session Integration** | Consumes backend session API (`/api/v1/admin/auth/login`, `/me`, `/logout`), cookie credentials, automatic session restoration | Requires New Implementation |
| **J** | **Protected Routes** | `ProtectedRoute` wrapper guarding administrative screens against unauthenticated access | Requires New Implementation |
| **K** | **RBAC-Aware Rendering** | `PermissionGuard` and `hasPermission` utility consuming backend 45-permission matrix and 5 canonical roles | Requires New Implementation |
| **L** | **CSRF Integration** | Auto-fetches bootstrap CSRF token (`/api/v1/admin/auth/csrf`), attaches `X-CSRF-Token` to mutating requests | Requires New Implementation |
| **M** | **API Client** | Unified `apiClient` supporting base URL, credentials, JSON/multipart bodies, query parameters, error normalization | Requires New Implementation |
| **N** | **Environment Configuration** | Browser-safe environment loader (`VITE_API_BASE_URL`), zero private server secrets exposed | Requires New Implementation |
| **O** | **Global Error Handling** | `ErrorBoundary` catching rendering exceptions with accessible fallback UI | Requires New Implementation |
| **P** | **Loading States** | `Spinner`, `Skeleton`, and full-screen loading fallback | Requires New Implementation |
| **Q** | **Empty States** | Generic `EmptyState` component with title, description, illustration/icon, and optional action | Requires New Implementation |
| **R** | **Error States** | Generic `ErrorState` component with retry capability and error message formatting | Requires New Implementation |
| **S** | **Design Tokens** | Phase 3 locked tokens: CSS custom properties in `index.css`, mapped into `tailwind.config.ts` | Requires New Implementation |
| **T** | **Typography** | Font stacks: Plus Jakarta Sans (headlines) & Inter (body/admin), responsive scale | Requires New Implementation |
| **U** | **Colors** | Canvas (`#F2F0F8`), Purple CTA (`#665CE9`), Navy (`#252B5E`), Pink, Yellow, Mint, Sky, Muted (`#50567A`) | Requires New Implementation |
| **V** | **Spacing** | 4px scale (`space-1` to `space-24`) from Phase 3 design system specification | Requires New Implementation |
| **W** | **Radius / Shadows** | Radii: `8px` (`sm`), `14px` (`md`), `20px` (`lg`), `28px` (`xl`), `36px` (`2xl`), `9999px` (`full`). Shadows: `sm` to `floating` | Requires New Implementation |
| **X** | **Buttons** | Generic `Button` and `IconButton` with variants (`primary`, `secondary`, `outline`, `ghost`, `danger`) and sizes | Requires New Implementation |
| **Y** | **Inputs / Forms** | `Input`, `Textarea`, `Select`, `Checkbox`, `FormField` with error/label association | Requires New Implementation |
| **Z** | **Cards** | Generic `Card` with header, body, footer, and border/shadow tokens | Requires New Implementation |
| **AA** | **Tables** | Reusable `Table` primitive with accessible headers, responsive overflow wrapper, and pagination shell | Requires New Implementation |
| **AB** | **Badges** | Generic `Badge` and `StatusBadge` supporting semantic variants (`success`, `warning`, `error`, `info`, `neutral`) | Requires New Implementation |
| **AC** | **Dialogs** | Accessible `Modal` / `Dialog` with focus trapping, ESC key handling, backdrop click, and ARIA attributes | Requires New Implementation |
| **AD** | **Alerts / Toasts** | Generic `Alert` and lightweight `Toast` notification system | Requires New Implementation |
| **AE** | **Navigation** | Responsive sidebar and topbar with accessible collapsible drawer, active route indicators | Requires New Implementation |
| **AF** | **Responsive Behavior** | Breakpoint support: Mobile (<768px), Tablet (768-1024px), Desktop (≥1024px), horizontal overflow protection | Requires New Implementation |
| **AG** | **Accessibility** | WCAG 2.2 AA compliance: visible focus rings (`focus-visible`), aria attributes, contrast >= 4.5:1, keyboard operability | Requires New Implementation |
| **AH** | **Security** | Zero stored passwords, zero plaintext secrets in client bundle, strict sanitization, CSRF token attachment | Requires New Implementation |
| **AI** | **Tests** | Comprehensive frontend test suite (Vitest + React Testing Library) covering shell, routing, auth, guards, API client, primitives | Requires New Implementation |
| **AJ** | **Production Build** | `npm run build` generates optimized bundle with zero errors or warnings | Requires New Implementation |
| **AK** | **CI** | GitHub Actions workflow extended with frontend job (install, lint, typecheck, test, build) without weakening backend jobs | Requires Extension |
| **AL** | **Acceptance Criteria** | 100% green on frontend suite, 100% green on backend regression suite, zero secret findings, clean CI execution | Requires New Implementation |

---

### 3. Frontend Technology Stack & Dependency Justification

Following canonical documentation (`docs/phase-00/07_TECHNICAL_ARCHITECTURE.md`, `docs/phase-03/14_DESIGN_TO_REACT_COMPONENT_MAPPING.md`):
1. **Core Framework**: `react` (^18.3) and `react-dom` (^18.3).
2. **Language**: `typescript` (^5.5) with strict typing.
3. **Build Tool & Bundler**: `vite` (^5.4) and `@vitejs/plugin-react` (^4.3). Fast compilation, ES modules, tree-shaking, production minification.
4. **CSS Engine**: `tailwindcss` (^3.4), `postcss` (^8.4), `autoprefixer` (^10.4). Implements the Phase 3 design token specification without runtime CSS overhead.
5. **Icons**: `lucide-react` (^0.450). Explicitly permitted by `docs/phase-03/13_ASSET_INVENTORY_AND_PROVENANCE.md` (Feather / Lucide stroke geometry, MIT License).
6. **Testing Stack**: `vitest` (^2.1), `@testing-library/react` (^16.0), `@testing-library/jest-dom` (^6.5), `@testing-library/user-event` (^14.5), and `jsdom` (^25.0).
7. **Linting**: `eslint` (^9.0) with `@eslint/js`, `typescript-eslint`, and `eslint-plugin-react-hooks`.

---

### 4. Application Architecture & Directory Layout

```
frontend/
├── public/
│   ├── favicon.svg
│   └── robots.txt
├── src/
│   ├── apps/
│   │   ├── admin/
│   │   │   ├── components/
│   │   │   │   ├── AdminHeader.tsx
│   │   │   │   ├── AdminLayout.tsx
│   │   │   │   ├── AdminSidebar.tsx
│   │   │   │   └── UserDropdown.tsx
│   │   │   ├── context/
│   │   │   │   └── AuthContext.tsx
│   │   │   ├── pages/
│   │   │   │   ├── AdminLoginPage.tsx
│   │   │   │   ├── AdminPlaceholderPage.tsx
│   │   │   │   └── AdminDashboardLanding.tsx
│   │   │   └── AdminApp.tsx
│   │   └── public/
│   │       ├── pages/
│   │       │   └── PublicPlaceholderPage.tsx
│   │       └── PublicApp.tsx
│   ├── shared/
│   │   ├── api/
│   │   │   ├── apiClient.ts
│   │   │   └── types.ts
│   │   ├── components/
│   │   │   ├── Alert.tsx
│   │   │   ├── Badge.tsx
│   │   │   ├── Breadcrumb.tsx
│   │   │   ├── Button.tsx
│   │   │   ├── Card.tsx
│   │   │   ├── Checkbox.tsx
│   │   │   ├── EmptyState.tsx
│   │   │   ├── ErrorBoundary.tsx
│   │   │   ├── ErrorState.tsx
│   │   │   ├── FormField.tsx
│   │   │   ├── Input.tsx
│   │   │   ├── Modal.tsx
│   │   │   ├── PageHeader.tsx
│   │   │   ├── Pagination.tsx
│   │   │   ├── PermissionGuard.tsx
│   │   │   ├── ProtectedRoute.tsx
│   │   │   ├── Select.tsx
│   │   │   ├── Skeleton.tsx
│   │   │   ├── Spinner.tsx
│   │   │   ├── Table.tsx
│   │   │   ├── Textarea.tsx
│   │   │   └── Toast.tsx
│   │   ├── context/
│   │   │   └── ToastContext.tsx
│   │   ├── router/
│   │   │   ├── Router.tsx
│   │   │   └── RouterContext.tsx
│   │   ├── tokens/
│   │   │   └── tokens.ts
│   │   ├── types/
│   │   │   └── auth.ts
│   │   └── utils/
│   │       ├── cn.ts
│   │       └── permissions.ts
│   ├── App.tsx
│   ├── index.css
│   └── main.tsx
├── tests/
│   ├── setup.ts
│   ├── apiClient.test.ts
│   ├── authContext.test.tsx
│   ├── components.test.tsx
│   ├── router.test.tsx
│   └── shell.test.tsx
├── index.html
├── package.json
├── postcss.config.js
├── tailwind.config.ts
├── tsconfig.json
├── tsconfig.node.json
└── vite.config.ts
```

---

### 5. Implementation Execution Phases

1. **Phase 18.1: Workspace & Build Tooling Initialization**
   - Create `package.json`, `tsconfig.json`, `tsconfig.node.json`, `vite.config.ts`, `postcss.config.js`, `tailwind.config.ts`, `index.html`.
   - Install exact production and development dependencies using `npm install`.
2. **Phase 18.2: Design Tokens & CSS Foundation**
   - Populate `src/index.css` with CSS custom variables matching Phase 3 specifications and Tailwind utility directives.
   - Configure `tailwind.config.ts` mapping `voak` and `admin` semantic color palettes, typography, radii, and shadows.
   - Create `src/shared/tokens/tokens.ts` for type-safe token references.
3. **Phase 18.3: Unified API Client & CSRF Interceptor**
   - Build `src/shared/api/apiClient.ts` handling credentials, automatic CSRF token acquisition and header injection, query serialization, and standardized error normalization matching the Phase 2 error envelope.
4. **Phase 18.4: Session & RBAC Authentication Context**
   - Implement `src/apps/admin/context/AuthContext.tsx` providing `user`, `isAuthenticated`, `isLoading`, `login()`, `logout()`, `refreshSession()`.
   - Implement `src/shared/utils/permissions.ts`, `ProtectedRoute.tsx`, and `PermissionGuard.tsx`.
5. **Phase 18.5: Generic Atomic Design System Primitives**
   - Implement primitives: `Button`, `Input`, `Textarea`, `Select`, `Checkbox`, `FormField`, `Card`, `Badge`, `Modal`, `Alert`, `Toast`, `Spinner`, `Skeleton`, `EmptyState`, `ErrorState`, `Table`, `Pagination`, `Breadcrumb`, `PageHeader`.
   - Implement `ToastContext.tsx` and `ErrorBoundary.tsx`.
6. **Phase 18.6: Core Routing Engine & Admin Application Shell**
   - Implement lightweight, typed router (`Router.tsx` / `RouterContext.tsx`) with History API support, link components, and route matching.
   - Build `AdminLayout.tsx`, `AdminSidebar.tsx`, `AdminHeader.tsx`, `UserDropdown.tsx` with responsive drawer and navigation links.
   - Implement `AdminLoginPage.tsx`, `AdminDashboardLanding.tsx` (scaffolding foundation widgets without prematurely implementing Phase 25/26 business logic), and `AdminPlaceholderPage.tsx` for permitted placeholder routes.
7. **Phase 18.7: Comprehensive Frontend Automated Testing**
   - Establish Vitest test suite with `@testing-library/react` testing components, API client, authentication flow, permission guards, routing, and shell responsiveness.
8. **Phase 18.8: CI Integration & Full Local Regression**
   - Extend `.github/workflows/backend-ci.yml` with a dedicated frontend verification job without weakening any existing backend jobs.
   - Execute all local tests: frontend tests, typecheck, lint, build, and all 12 backend regression test suites.
9. **Phase 18.9: Verification & Documentation**
   - Generate `docs/phase-18/02_PHASE_18_VERIFICATION_REPORT.md`.
   - Commit, push, and verify green GitHub Actions execution.
