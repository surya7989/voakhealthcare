# Phase 19 — Complete UI Component System Plan
## Voak Health Care Enterprise Web Application

**Document Identifier**: `DOC-PHASE19-PLAN-001`  
**Phase Identifier**: `PHASE-19` / `UI-CORE-001` / `FE-COMP-001`  
**Target Environment**: `frontend/src/shared/components/` (React 18 / TypeScript strict / Tailwind CSS v3)  
**Creation Date**: 2026-10-24  
**Author**: Antigravity Autonomous Agent  
**Baseline HEAD**: `72bc72d3deb9a0e603e50bea957f51a886e65895`  
**Status**: Ready for Implementation  

---

### 1. Executive Summary & Canonical Discovery

Following completion and full CI verification of **Phase 18 (React Admin Foundation)**, the project advances into **Phase 19: Complete UI Component System (Atomic UI Component Library)**.

Per `docs/phase-00/07_TECHNICAL_ARCHITECTURE.md`, `docs/phase-00/13_COMPLETE_32_PHASE_TRACEABILITY.md`, `docs/phase-01/01_MASTER_FUNCTIONAL_REQUIREMENTS.md`, `docs/phase-01/15_REQUIREMENTS_TRACEABILITY_MATRIX.md`, and `docs/phase-03/14_DESIGN_TO_REACT_COMPONENT_MAPPING.md`:
- **Official Phase Name**: **Complete UI Component System** (Atomic UI Component Library)
- **Primary Business Objective**: Construct and harden the comprehensive, domain-neutral, accessible master UI component library within `frontend/src/shared/components/`. This system will serve as the shared presentation foundation for all upcoming public web experiences (Phases 20–24) and administrative consoles (Phases 25–26).
- **Strict Boundary Governance**:
  - Phase 19 implements **reusable presentation primitives only**.
  - Does **NOT** implement Phase 20 full homepage or marketing pages.
  - Does **NOT** implement complete administrative business desks (Contact CRM, Proposals, Recruitment, Media, SEO, or Audit consoles scheduled for Phase 26).
  - Does **NOT** implement progressive client-side mounting hooks (Phase 29).
  - Does **NOT** alter backend services, database migrations (remains 28 applied / 0 pending), or deploy to Hostinger.

---

### 2. Comprehensive Requirements Matrix (Items A through AG)

| Matrix Item | Specification Dimension | Requirement & Canonical Scope | Classification |
|---|---|---|---|
| **A** | **Official Phase Title** | Complete UI Component System (`PHASE-19`) | Target Phase |
| **B** | **Business Objective** | Production-grade master UI component library implementing Phase 3 design tokens, WCAG 2.2 AA accessibility, responsive design, and keyboard navigation | Target Phase |
| **C** | **Required Categories** | Form controls, Data display, Navigation, Overlays, Feedback, Table system, Search/Filters, Tabs/Accordions | Requires Implementation |
| **D** | **Existing Phase 18 Primitives** | `Button`, `Input`, `Textarea`, `Select`, `Checkbox`, `FormField`, `Card`, `Badge`, `StatusBadge`, `Modal`, `Alert`, `Toast`, `Spinner`, `Skeleton`, `EmptyState`, `ErrorState`, `Table`, `Pagination`, `Breadcrumb`, `PageHeader`, `ErrorBoundary` | Already Supported |
| **E** | **Components Requiring Extension** | `Button` (pill variants), `Input` (icons/sizes), `Badge` (accents & dots), `FormField` (composed sub-components), `Table` (sorting indicators) | Requires Extension |
| **F** | **New Components Required** | `RadioGroup`, `Switch`, `SearchInput`, `FilterBar`, `BentoCard`, `ConfirmationDialog`, `Drawer`, `DropdownMenu`, `Tooltip`, `Popover`, `Tabs`, `Accordion`, `FileUploadDropzone`, `DateInput`, `ProgressBar`, `DataTable` | Requires New Implementation |
| **G** | **Form Components** | `Input`, `Textarea`, `Select`, `Checkbox`, `RadioGroup`, `Switch`, `FormField`, `FieldLabel`, `FieldDescription`, `FieldError` | Requires Extension / New |
| **H** | **Data-Display Components** | `Card`, `BentoCard`, `Badge`, `StatusBadge`, `Table`, `DataTable`, `Breadcrumb`, `PageHeader`, `DateTimeDisplay` | Requires Extension / New |
| **I** | **Navigation Components** | `Breadcrumb`, `Pagination`, `Tabs`, `Drawer`, `DropdownMenu` | Requires Extension / New |
| **J** | **Overlay Components** | `Modal`, `ConfirmationDialog`, `Drawer` (Sheet), `DropdownMenu`, `Tooltip`, `Popover` | Requires Extension / New |
| **K** | **Feedback Components** | `Alert`, `Toast`, `InlineError`, `SuccessMessage`, `ProgressBar`, `Spinner`, `Skeleton` | Requires Extension / New |
| **L** | **Loading Components** | `Spinner`, `Skeleton`, `ProgressBar`, button loading states | Requires Extension / New |
| **M** | **Empty/Error Components** | `EmptyState`, `ErrorState`, `ErrorBoundary`, form inline errors | Already Supported / Ext |
| **N** | **Table / Data-Grid** | Reusable `Table` primitive with sortable headers, responsive container, column definitions, and integrated `DataTable` | Requires Extension / New |
| **O** | **Search / Filter Components** | `SearchInput`, `FilterBar`, `FilterSelect`, `FilterChip`, "Clear All" action | Requires New Implementation |
| **P** | **Pagination Requirements** | Page number buttons, previous/next, ellipsis, page size selector, accessible `aria-current="page"` | Already Supported |
| **Q** | **Date / Time Components** | Native accessible `DateInput`, `DateTimeDisplay` with locale and timezone formatting | Requires New Implementation |
| **R** | **File-Upload Components** | `FileUploadDropzone` with drag/drop, browser file select, size preview, client validation, security disclaimers | Requires New Implementation |
| **S** | **Tabs / Accordion** | `Tabs` (W3C tablist pattern, left/right keyboard nav), `Accordion` (`aria-expanded`, smooth collapse) | Requires New Implementation |
| **T** | **Menus / Dropdowns** | `DropdownMenu` with trigger, items, dividers, keyboard arrow navigation, ESC dismissal | Requires New Implementation |
| **U** | **Tooltips / Popovers** | Accessible hover/focus `Tooltip` with arrow and placement; click-triggered `Popover` | Requires New Implementation |
| **V** | **Confirmation Dialogs** | `ConfirmationDialog` for destructive/critical actions with title, consequence text, confirm/cancel buttons | Requires New Implementation |
| **W** | **Status Indicators** | `StatusBadge` and `Badge` with dot indicators, mapping into neutral, info, success, warning, error | Requires Extension |
| **X** | **Dashboard / Bento Primitives** | `BentoCard` supporting `colSpan?: 4 | 6 | 8 | 12` and corporate tints (`pink`, `yellow`, `mint`, `sky`, `white`) | Requires New Implementation |
| **Y** | **Responsive Behavior** | Fluid responsiveness across Mobile (<768px), Tablet (768-1024px), Desktop (≥1024px) | Requires Implementation |
| **Z** | **Accessibility (WCAG 2.2 AA)**| Contrast ≥ 4.5:1, touch target ≥ 44px, visible focus ring (`ring-2 ring-voak-purple`), explicit ARIA attributes | Requires Implementation |
| **AA**| **Keyboard Behavior** | Tab navigation, Space/Enter activation, ESC dismissal, arrow key traversal on tabs/menus/radios | Requires Implementation |
| **AB**| **Focus Management** | Focus trapping in modals and drawers, focus restoration to triggers on dialog/menu close | Requires Implementation |
| **AC**| **Design-Token Usage** | Exclusively consume CSS variables in `index.css` and Tailwind mappings (`--voak-*`, `--admin-*`, `--state-*`) | Requires Implementation |
| **AD**| **Dark / Light Mode** | Color definitions in CSS variables with dark mode class support (`dark:`) | Already Supported |
| **AE**| **Testing Requirements** | Vitest + React Testing Library unit & component tests testing rendering, variants, keyboard, ARIA, and callbacks | Requires Implementation |
| **AF**| **Performance Requirements**| Zero heavyweight external UI frameworks, tree-shakeable atomic modules, zero bundle bloat | Requires Implementation |
| **AG**| **Acceptance Criteria** | 100% green frontend suite, 0 lint errors, 0 typecheck errors, successful production build, 100% green backend regression | Requires Implementation |

---

### 3. Component Architecture & Detailed Specifications

#### Form System Primitives
1. **`Button` & `IconButton`** (`Button.tsx`):
   - Variants: `primary`, `secondary`, `outline`, `ghost`, `danger`, `pill-pink`, `pill-yellow`, `pill-mint`.
   - Sizes: `sm`, `md` (min 44px touch target), `lg`.
   - Loading indicator with `role="status"` and `aria-label="Loading"`.
2. **`Input`** (`Input.tsx`):
   - Support `leftIcon`, `rightIcon`, `size?: 'sm' | 'md' | 'lg'`, `error?: boolean`, `hasError?: boolean`.
   - Ref forwarding via `React.forwardRef`.
3. **`Textarea`** (`Textarea.tsx`):
   - Form-field compatible, character counter optional, ref forwarding.
4. **`Select`** (`Select.tsx`):
   - Native select with custom chevron icon, option groups, error state.
5. **`Checkbox`** (`Checkbox.tsx`):
   - Accessible checkbox with custom focus ring, disabled state, helper label.
6. **`RadioGroup` & `Radio`** (`RadioGroup.tsx`):
   - `role="radiogroup"` with keyboard arrow traversal (`ArrowDown`, `ArrowUp`, `ArrowLeft`, `ArrowRight`).
   - `Radio` child with custom ring, label, and description.
7. **`Switch`** (`Switch.tsx`):
   - Accessible toggle control with `role="switch"`, `aria-checked`, keyboard Space/Enter toggle.
8. **`FormField`, `FieldLabel`, `FieldDescription`, `FieldError`** (`FormField.tsx`):
   - Form control wrapper binding `id`, `htmlFor`, and `aria-describedby` for WCAG 2.2 AA validation compliance.

#### Data Display & Bento Primitives
9. **`BentoCard`** (`BentoCard.tsx`):
   - Implements `docs/phase-03/14_DESIGN_TO_REACT_COMPONENT_MAPPING.md` spec: `colSpan?: 4 | 6 | 8 | 12`, `tint?: 'pink' | 'yellow' | 'mint' | 'sky' | 'white'`, `icon`, `title`, `description`, `chips`.
10. **`Badge` & `StatusBadge`** (`Badge.tsx`):
    - Variants: `purple`, `navy`, `pink`, `yellow`, `mint`, `sky`, `neutral`, `success`, `warning`, `error`, `info`.
    - Support `dot?: boolean` for live status indicators.
11. **`Table` & `DataTable`** (`Table.tsx` & `DataTable.tsx`):
    - Base semantic table elements (`Table`, `TableHeader`, `TableBody`, `TableRow`, `TableHead`, `TableCell`).
    - Sortable headers with column sorting direction indicators.
    - `DataTable<T>`: unified component accepting `columns: ColumnDef<T>[]`, `data: T[]`, sorting state, empty message, loading overlay, pagination integration.

#### Search & Filter Primitives
12. **`SearchInput`** (`SearchInput.tsx`):
    - Search field with magnifying glass icon, clear button (`Escape` or click), accessible label.
13. **`FilterBar` & `FilterChip`** (`FilterBar.tsx`):
    - Toolbar wrapper for search, dropdown filters, active filter chips with remove triggers, and "Clear all" button.

#### Overlays & Dialogs
14. **`Modal`** (`Modal.tsx`):
    - Accessible dialog with backdrop blur, focus trap, ESC key dismissal, accessible title.
15. **`ConfirmationDialog`** (`ConfirmationDialog.tsx`):
    - Destructive or critical confirmation modal: title, description/consequences, confirm button with loading state, cancel button.
16. **`Drawer` / `Sheet`** (`Drawer.tsx`):
    - Slide-out panel (`left` or `right` side) with backdrop, focus trap, ESC dismissal, mobile navigation or preview panel support.
17. **`DropdownMenu`** (`DropdownMenu.tsx`):
    - Accessible menu with trigger button, menu container, items, dividers, keyboard arrow navigation, ESC to close, focus restoration.
18. **`Tooltip` & `Popover`** (`Tooltip.tsx` & `Popover.tsx`):
    - Hover/focus `Tooltip` with `role="tooltip"`, `aria-describedby`, placement (`top`, `bottom`, `left`, `right`).
    - Click-triggered `Popover` for contextual popups.

#### Navigation & Layout
19. **`Tabs`** (`Tabs.tsx`):
    - Accessible W3C Tab pattern: `role="tablist"`, `role="tab"`, `role="tabpanel"`, `aria-selected`, left/right keyboard navigation.
20. **`Accordion`** (`Accordion.tsx`):
    - Collapsible accordion with `aria-expanded`, `aria-controls`, smooth expand animation, single/multiple expand mode.
21. **`Breadcrumb` & `Pagination` & `PageHeader`** (`Breadcrumb.tsx`, `Pagination.tsx`, `PageHeader.tsx`):
    - Maintained and integrated from Phase 18.

#### Feedback, Utilities & Progress
22. **`ProgressBar`** (`ProgressBar.tsx`):
    - Determinate and indeterminate progress bar with `role="progressbar"`, `aria-valuenow`, label, percentage display.
23. **`FileUploadDropzone`** (`FileUploadDropzone.tsx`):
    - Drag-and-drop file upload zone with file picker, size & allowed extension display, client-side validation preview, remove action, security disclaimer.
24. **`DateInput` & `DateTimeDisplay`** (`DateInput.tsx`):
    - Native accessible date picker input and formatted date display with locale/timezone consistency.
25. **`Alert`, `Toast`, `Spinner`, `Skeleton`, `EmptyState`, `ErrorState`, `ErrorBoundary`**:
    - Complete suite of feedback and recovery states.

---

### 4. Implementation Steps

1. **Step 1: Component Extensions**:
   - Extend `Button.tsx` (pill variants, IconButton styling).
   - Extend `Input.tsx` (icons, sizes).
   - Extend `Badge.tsx` (brand accent tints, status dots).
   - Extend `FormField.tsx` (export `FieldLabel`, `FieldDescription`, `FieldError`).
   - Extend `Table.tsx` (sortable column header indicator, row selection).
2. **Step 2: New Component Implementation**:
   - Implement `RadioGroup.tsx` and `Switch.tsx`.
   - Implement `SearchInput.tsx` and `FilterBar.tsx`.
   - Implement `BentoCard.tsx`.
   - Implement `ConfirmationDialog.tsx`.
   - Implement `Drawer.tsx`.
   - Implement `DropdownMenu.tsx`.
   - Implement `Tooltip.tsx` and `Popover.tsx`.
   - Implement `Tabs.tsx` and `Accordion.tsx`.
   - Implement `FileUploadDropzone.tsx`.
   - Implement `DateInput.tsx` / `DateTimeDisplay.tsx`.
   - Implement `ProgressBar.tsx`.
   - Implement `DataTable.tsx`.
   - Create `index.ts` barrel file exporting all components.
3. **Step 3: Automated Testing**:
   - Create comprehensive unit and component tests in `frontend/tests/components.phase19.test.tsx` covering all new and extended primitives.
4. **Step 4: Local Verification**:
   - Run `npm run typecheck`, `npm run lint`, `npm test`, `npm run build`.
   - Run complete backend test suites, secret scanning, PHP syntax lint, composer validate.
5. **Step 5: Documentation & Git Lifecycle**:
   - Create `docs/phase-19/02_PHASE_19_VERIFICATION_REPORT.md`.
   - Commit `feat(phase-19): implement complete ui component system`.
   - Push to `origin main` and verify green GitHub Actions run.
