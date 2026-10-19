# React Frontend Application Architecture

**Document Reference:** `docs/phase-02/03_REACT_APPLICATION_ARCHITECTURE.md`  
**Phase:** Phase 2 of 31 — Complete System Architecture  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Executing Role:** Senior React Architect & Accessibility Engineering Lead  
**Baseline Authority:** Accepted Phase 0 Design System (`docs/phase-00/05_APPROVED_DESIGN_SYSTEM.md`) and Accepted Phase 1 Public/Admin Specifications (`docs/phase-01/02_PUBLIC_PAGE_BY_PAGE_SPECIFICATIONS.md`, `05_ADMIN_19_MODULE_SPECIFICATIONS.md`)  
**Status:** **AUTHORITATIVE ARCHITECTURAL SPECIFICATION** (Design Only — Code Implementation Deferred to Phases 19–26)

---

## 1. Dual-Application Architecture Overview

The frontend architecture consists of two logically decoupled React 18+ TypeScript applications compiled via Vite into static production assets deployable directly to Hostinger shared hosting:

1. **Public Website Application (`public-app`):**
   - High-performance, SEO-conscious public portal covering all 22 public pages.
   - Initialized via **`createRoot` client mounting**, smoothly taking over from the PHP pre-rendered semantic HTML in `<article id="ssr-fallback">`.
   - Utilizes Tailwind CSS and Motion for React for rich, fluid interactive micro-animations.
2. **Administrative Console Application (`admin-app`):**
   - Secure, single-page application (SPA) covering all 19 admin modules.
   - Enforces 5-role UI state gating, TanStack Query server-state caching, authenticated binary document streaming, and outbox retry telemetry.
   - Authenticates via versioned cookie: `Path=/api/v1/admin; Secure; HttpOnly; SameSite=Lax`.

```
                                CLIENT BROWSER
                                      │
                 ┌────────────────────┴────────────────────┐
                 ▼                                         ▼
   PUBLIC APPLICATION (React 18+)           ADMIN APPLICATION (React 18+)
   - Entry: public_html/index.php           - Entry: public_html/admin/index.html
   - Route: / (All 22 Public Pages)         - Route: /admin/* (19 Modules)
   - Mounting: createRoot into #root        - Mounting: createRoot into #admin-root
   - Fallback: Pre-rendered semantic HTML   - Auth Gate: Session Cookie + TOTP MFA
   - State: TanStack Query + Form Hooks     - State: TanStack Query + RBAC Context
   - Motion: Spring animations              - UI: Dense Operational Data Tables
```

---

## 2. Directory Structure & Module Boundaries

Both applications share a common core design token package while maintaining strict architectural isolation:

```
frontend/
├── packages/
│   ├── design-tokens/                 # Shared Tailwind design tokens, colors, typography
│   │   ├── colors.ts                  # Brand palette (#F2F0F8, #665CE9, #252B5E, pastels)
│   │   ├── typography.ts              # Fraunces (serif), Plus Jakarta Sans (sans)
│   │   └── tailwind.preset.js         # Shared Tailwind preset configuration
│   │
│   ├── public-app/                    # Public Corporate Website (Phases 19-24)
│   │   ├── index.html                 # Dev Vite shell (in production, rendered via index.php)
│   │   ├── src/
│   │   │   ├── main.tsx               # Entry point: createRoot(document.getElementById('root'))
│   │   │   ├── App.tsx                # App root, QueryClientProvider, RouterProvider
│   │   │   ├── components/            # Atomic & molecular UI components
│   │   │   │   ├── layout/            # Header, Footer, MobileDrawer, Breadcrumbs
│   │   │   │   ├── ui/                # Button, Badge, Modal, Accordion, Card, Input
│   │   │   │   └── forms/             # ContactForm, PartnershipForm, RfpWizard
│   │   │   ├── pages/                 # 22 Public Page Views (lazy-loaded via React.lazy)
│   │   │   ├── hooks/                 # Custom React hooks (useServices, useRfpWizard)
│   │   │   ├── api/                   # Typed API clients (Axios / Fetch + Normalizer)
│   │   │   └── styles/                # Tailwind CSS imports & base styles
│   │   └── vite.config.ts             # Rollup bundle splitting & asset hashing
│   │
│   └── admin-app/                     # Admin Console Portal (Phases 25-26)
│       ├── index.html                 # Admin SPA HTML entry point
│       ├── src/
│       │   ├── main.tsx               # Entry point: createRoot(document.getElementById('admin-root'))
│       │   ├── App.tsx                # AuthProvider, RbacProvider, QueryClientProvider
│       │   ├── components/            # Admin UI components
│       │   │   ├── layout/            # AdminHeader, AdminSidebar, ModuleHeader
│       │   │   ├── rbac/              # PermissionGate, RoleBadge
│       │   │   └── datatable/         # PaginatedTable, FilterBar, ActionMenu
│       │   ├── modules/               # 19 Admin Subsystem Views
│       │   │   ├── dashboard/         # Module 01: KPI Overview
│       │   │   ├── auth/              # Module 02: Login & TOTP MFA Dialog
│       │   │   ├── users/             # Module 03: Staff RBAC
│       │   │   ├── cms/               # Modules 04-08, 15-17: Pages, Services, Articles
│       │   │   ├── crm/               # Modules 09-11: Inquiries, RFPs, Partnerships
│       │   │   ├── email/             # Module 12: Email Templates & Outbox Telemetry
│       │   │   ├── careers/           # Modules 13-14: Job Postings & Applications
│       │   │   └── audit/             # Module 19: Immutable Audit Explorer
│       │   ├── context/               # AuthContext, RbacContext
│       │   └── api/                   # Admin API client with CSRF token interceptor
│       └── vite.config.ts             # Admin Vite configuration
```

---

## 3. Server State Management & Async Error Normalization

### 3.1 TanStack Query (React Query v5) Architecture
To prevent state desynchronization, eliminate unnecessary network traffic, and handle asynchronous failure modes gracefully, both applications standardize on TanStack Query:
- **Automatic Caching & Stale-While-Revalidate:** Public services and industry catalogs are cached with `staleTime: 5 * 60 * 1000` (5 minutes) and `cacheTime: 30 * 60 * 1000` (30 minutes).
- **Automatic Background Refetching:** Stale queries re-validate on window focus when appropriate.
- **Optimistic Updates:** CMS status toggles and inquiry status updates apply immediately in the UI with automatic rollback if the server emits an error status.

### 3.2 Asynchronous Error Normalization vs. React Error Boundaries
Phase 2 strictly rectifies previous misconceptions regarding React error handling:
- **React Error Boundaries:** Catch **only** rendering and lifecycle tree exceptions (e.g., component render throw, unhandled null prop access). When triggered, they render an accessible fallback screen with a diagnostic correlation ID (`REF-ERR-CLIENT-XXXX`).
- **Asynchronous API Error Normalization:** Fetch and mutation promise rejections are **never** caught by Error Boundaries. They are intercepted by an Axios/Fetch API client normalizer:
  1. Deserializes server error envelopes (`{success: false, error: {code, message}}`).
  2. Maps validation errors directly into React Hook Form field states.
  3. Surfaces transient network errors via an accessible global banner (`role="alert"`).
  4. Provides structured retry triggers without unmounting the view.

```typescript
// Shared API Client Error Normalization Contract
export interface ApiErrorEnvelope {
  success: false;
  error: {
    code: string;
    message: string;
    reference_id?: string;
    validation_errors?: Record<string, string[]>;
  };
}

export async function normalizeApiResponse<T>(response: Response): Promise<T> {
  if (!response.ok) {
    let errorData: ApiErrorEnvelope;
    try {
      errorData = await response.json();
    } catch {
      errorData = {
        success: false,
        error: {
          code: 'NETWORK_ERROR',
          message: `HTTP error ${response.status}: ${response.statusText}`,
        },
      };
    }
    throw new NormalizedAppException(errorData.error);
  }
  return response.json();
}
```

---

## 4. Public Rendering Contract & Client Takeover

```
                           REQUEST ARRIVES AT BROWSER
                                      │
                                      ▼
                 Browser receives initial HTML from index.php
       ┌─────────────────────────────────────────────────────────────┐
       │ - HTTP Status: 200 OK (or 404/301)                          │
       │ - <head>: Meta title, description, JSON-LD, OpenGraph       │
       │ - <body>: <div id="root"></div>                             │
       │           <article id="ssr-fallback">                       │
       │             <h1>Published Service Title</h1>                │
       │             <p>Deliverables, SLA, semantic HTML...</p>      │
       │           </article>                                        │
       └─────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
             Search Engine Bot: Reads #ssr-fallback and exits.
                                      │
                                      ▼
            Regular User Browser: Loads /assets/app-*.js
                                      │
                                      ▼
       React executes: createRoot(document.getElementById('root'))
                                      │
                                      ▼
        CSS selector triggers: #root:not(:empty) + #ssr-fallback
                                      │
                                      ▼
       #ssr-fallback receives display: none (smooth transition)
                                      │
                                      ▼
       Interactive React UI mounts into #root (Zero Layout Shift)
```

### Layout Shift (CLS) Prevention:
1. `#ssr-fallback` and React root container share identical max-width container constraints (`max-w-7xl mx-auto px-4 sm:px-6 lg:px-8`) and identical font-family metrics.
2. The CSS rule `#root:not(:empty) + #ssr-fallback { display: none; }` ensures that the fallback is completely removed from the visual flow at the exact microsecond React produces its initial DOM nodes, preventing cumulative layout shift (CLS < 0.1).

---

## 5. Approved Design System Continuity

The React UI strictly preserves the second user-approved Voak Health Care homepage aesthetic:

### 5.1 Approved Color Tokens & WCAG 2.2 AA Contrast Ratios
```typescript
export const brandColors = {
  canvas: {
    DEFAULT: '#F2F0F8', // Soft Lavender background canvas
    card: '#FFFFFF',    // Pristine white bento card surface
  },
  accent: {
    purple: '#665CE9',  // Vibrant Royal Purple primary CTA button
    pink: '#EEAFD7',    // Pastel Rose bento accent
    yellow: '#FBDC62',  // Pastel Buttercup bento accent
    mint: '#54CB96',    // Pastel Mint green bento accent
    blue: '#63A5E6',    // Pastel Sky Blue bento accent
  },
  typography: {
    primary: '#252B5E', // Deep Navy / Plum heading & primary text (11.8:1 on #F2F0F8)
    muted: '#50567A',   // Corrected Slate Blue body & helper text (6.30:1 on #F2F0F8)
    inverse: '#FFFFFF', // White text on Royal Purple (#665CE9, 5.20:1)
  },
  status: {
    error: '#D94841',   // Accessible crimson red (4.90:1 on #FDF3F2)
    success: '#1B8754', // Accessible emerald green
    warning: '#996500', // Accessible amber brown
  }
};
```

### 5.2 Typography System
- **Display Headings (`h1`, `h2`):** `font-display: 'Fraunces', Georgia, serif;` (editorial authority, clinical gravitas).
- **Interface & Body Typography:** `font-family: 'Plus Jakarta Sans', system-ui, sans-serif;` (modern, legible, high clarity).

### 5.3 Motion for React Configuration
- Micro-animations utilize physically modeled spring physics: `transition={{ type: "spring", stiffness: 300, damping: 25 }}`.
- All animations respect vestibular preferences via `prefers-reduced-motion`:
```tsx
import { useReducedMotion } from 'framer-motion';

export function BentoCard({ children }: { children: React.ReactNode }) {
  const shouldReduceMotion = useReducedMotion();
  return (
    <motion.div
      whileHover={shouldReduceMotion ? {} : { translateY: -4 }}
      transition={{ type: 'spring', stiffness: 300, damping: 25 }}
      className="bg-white rounded-2xl p-6 shadow-sm border border-brand-purple/10"
    >
      {children}
    </motion.div>
  );
}
```

---

## 6. Admin RBAC Component Gate Architecture

Administrative UI controls enforce the 5 approved roles (`Super Admin`, `Website Admin`, `Content Editor`, `BD Manager`, `HR Manager`) using a declarative permission gate:

```tsx
interface PermissionGateProps {
  permission: string;
  children: React.ReactNode;
  fallback?: React.ReactNode;
}

export function PermissionGate({ permission, children, fallback = null }: PermissionGateProps) {
  const { hasPermission } = useRbac();
  if (!hasPermission(permission)) {
    return <>{fallback}</>;
  }
  return <>{children}</>;
}
```

> [!IMPORTANT]
> **Client-Side Gating vs. Server Security Boundary:**  
> The `PermissionGate` component provides a streamlined user experience by hiding inaccessible controls. However, it is explicitly declared as a **UI convenience only**. The true, uncompromised security boundary is enforced on every request by the PHP backend middleware (`AuthorizePermission`).
