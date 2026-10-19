# Master Component Design System & Visual Specification Catalog
## Voak Health Care — Corporate Digital Brand Guidelines

**Document Reference:** `docs/phase-03/04_COMPONENT_DESIGN_SYSTEM.md`  
**Phase:** Phase 3 of 31 — Professional UI/UX Design  
**Implementation Target:** React + TypeScript + Tailwind CSS + Motion for React  
**Component Standard:** Atomic, Reusable, Accessible (WCAG 2.2 AA)

---

### 1. Header & Navigation Components

#### A. Floating Navigation Header (`VoakHeader`)
- **Visual Description:** Floating white pill navigation bar resting on the lavender canvas.
- **Tokens:** Background `rgba(255, 255, 255, 0.94)`, backdrop blur `16px`, border `1px solid rgba(216, 212, 229, 0.7)`, radius `9999px`, shadow `var(--shadow-md)`.
- **Anatomy:**
  - *Brand Lockup:* Original SVG Voak care mark (triple-care figure arc) + "VOAK" in `Fraunces 22px` + "HEALTH CARE" tracking subtitle in `#665CE9`.
  - *Nav Links:* Home, About, Services, Industries, Careers, Resources, Contact. Active indicator: 2px purple underline.
  - *Conversion Trigger:* Primary Royal Purple CTA button (*"Partner With Us"*).
- **Responsive Behavior:** Links hide at `< 992px`, replaced by a 44x44px accessible hamburger button that triggers the mobile drawer.

#### B. Mobile Navigation Drawer (`MobileNavDrawer`)
- **Visual Description:** Slide-out right-anchored drawer covering 80% of mobile viewport (max 360px).
- **Tokens:** Background `#FFFFFF`, shadow `-10px 0 30px rgba(0,0,0,0.15)`.
- **Accessibility:** Traps keyboard focus, closes on `Escape` or backdrop click, sets `aria-expanded="true/false"`.

---

### 2. Hero & Feature Card Components

#### A. Hero Banner (`VoakHero`)
- **Visual Description:** Asymmetric split section with gentle lavender gradient (`#F4F1FA` to `#EBE6F7`) and large rounded corners (`36px`).
- **Anatomy:**
  - *Pill Badge:* "Enterprise Healthcare B2B Solutions" with a pulsing mint dot (`#54CB96`).
  - *Editorial Headline:* "Better Healthcare Together" in `Fraunces 64px` (`#252B5E`).
  - *Action Buttons:* "Explore Our Services" (Primary Purple) + "Partner With Us" (Outline Navy).
  - *Collage Stage:* Doctor portrait frame (`320x380px`, radius `28px`, white border 4px), layered over pastel pink blob (`#EEAFD7`) and warm yellow accent (`#FBDC62`).
  - *Floating Illustrated Badges:* "Clinical Excellence" (purple icon) + "Enterprise Partner" (yellow star icon) with spring floating animation.

#### B. Three Distinctive Feature Cards (`FeatureCard`)
- **Variants:**
  - *Pink Card (`feature-card-pink`):* Bg `#EEAFD7`, color `#252B5E`. "Healthcare Services".
  - *Yellow Card (`feature-card-yellow`):* Bg `#FBDC62`, color `#252B5E`. "Industries We Serve".
  - *Mint Card (`feature-card-mint`):* Bg `#54CB96`, color `#252B5E`. "Partner With Us".
- **Hover Micro-interaction:** Translates `translateY(-6px)` with shadow elevation `0 12px 24px -4px rgba(37, 43, 94, 0.08)`. Transition: `250ms cubic-bezier(0.16, 1, 0.3, 1)`.

---

### 3. Bento Service Grid Components

#### Bento Card (`BentoCard`)
- **Grid Layout:** 12-column layout supporting 8-col (`bento-col-8`), 4-col (`bento-col-4`), and 6-col spans.
- **Tinted Variations:**
  - `bento-tint-pink`: Background `#FDF2F9`, border `rgba(238, 175, 215, 0.4)`.
  - `bento-tint-yellow`: Background `#FEF9E6`, border `rgba(251, 220, 98, 0.4)`.
  - `bento-tint-mint`: Background `#EBF9F2`, border `rgba(84, 203, 150, 0.4)`.
  - `bento-tint-sky`: Background `#EEF6FC`, border `rgba(99, 165, 230, 0.4)`.
- **Anatomy:** 48x48px icon box, card headline (`24px` serif), paragraph copy, and status/SLA tags.

---

### 4. Interactive Action & State Components

#### Button Component (`Button`)
| Variant | Background | Text Color | Border | Primary Use |
| :--- | :---: | :---: | :---: | :--- |
| `btn-primary` | `#665CE9` | `#FFFFFF` | None | Primary conversion, RFP submit |
| `btn-secondary` | `#F0EFFD` | `#665CE9` | None | Secondary exploration |
| `btn-outline` | Transparent | `#252B5E` | `1.5px solid #252B5E`| Tertiary action, discovery calls |
| `btn-pill-pink` | `#EEAFD7` | `#252B5E` | None | Pastel feature trigger |
| `btn-pill-yellow`| `#FBDC62` | `#252B5E` | None | Pastel feature trigger |
| `btn-pill-mint` | `#54CB96` | `#252B5E` | None | Pastel feature trigger |
| `btn-disabled` | `#E2E8F0` | `#94A3B8` | None | Inactive form states |

*Interactive States:*
- Hover: `transform: translateY(-2px)`, shadow elevation.
- Active: `transform: translateY(0)`, background darken 5%.
- Focus Visible: `outline: 3px solid #665CE9; outline-offset: 3px;`.
- Loading: Replaces text with a 18px SVG spinning circle.

#### Status Chips & Badges (`Chip`)
- `chip-success`: Bg `#DCFCE7`, text `#15803D` (Completed, Pass, 100% Verified). Contrast: 4.85:1.
- `chip-warning`: Bg `#FEF3C7`, text `#B45309` (In Review, Action Needed). Contrast: 5.12:1.
- `chip-error`: Bg `#FEE2E2`, text `#B91C1C` (Urgent Triage, Non-conformance). Contrast: 5.74:1.
- `chip-info`: Bg `#DBEAFE`, text `#1D4ED8` (Dispatched, Information). Contrast: 4.90:1.
- `chip-purple`: Bg `#F0EFFD`, text `#665CE9` (Super Admin, SLA Tier). Contrast: 4.92:1.

---

### 5. Form & Multi-Step Wizard Components

#### A. Form Input & Controls (`FormField`)
- **States:** Default (`border: 1.5px solid #D8D4E5`), Focus (`border: #665CE9; box-shadow: 0 0 0 3px rgba(102, 92, 233, 0.15)`), Error (`border: #B91C1C; bg: #FEE2E2`).
- **Anatomy:** Label with required indicator `*` in red, optional helper text below label, input control, and accessible inline error message with warning SVG icon.

#### B. File Upload Dropzone (`DropzoneBox`)
- **Visual:** Dashed border (`2px dashed #D8D4E5`), background `#FAF9FE`, hover border `#665CE9`, cloud upload icon, drag-over highlight.
- **Supported:** PDF, DOCX, XLSX up to 10MB per file with immediate client-side validation.

#### C. Multi-Step Proposal Wizard (`ProposalWizard`)
- **Step Progress Bar:** 5 sequential circular nodes (1 Organization, 2 Requirements, 3 Compliance, 4 Documents, 5 Review).
- **Node States:** Pending (gray circle), Active (purple circle with ring), Completed (mint green circle with checkmark).
- **Keyboard & Validation:** Can only proceed to the next step when required fields pass validation. Preserves draft state in memory.

---

### 6. Admin Portal Components

#### A. Admin Shell & Topbar (`AdminLayout`)
- **Sidebar:** Width 260px, background `#0F172A`, text `#94A3B8`. Groups: Core Operations, Publishing & CMS, Governance & Security.
- **Topbar:** Height 64px, background `#FFFFFF`, search bar with `Ctrl+K` hint, operational status pill, and user profile avatar.

#### B. Admin KPI Metric Card (`AdminKpiCard`)
- **Anatomy:** Subtitle label in uppercase 13px, large metric number in `Fraunces 32px`, trend direction indicator with green/amber arrow.

#### C. Admin Data Table (`AdminTable`)
- **Tokens:** Header background `#F1F5F9`, header text `#475569`, row border `#E2E8F0`, row hover background `#F8FAFC`. Responsive horizontal scroll on mobile with frozen action column.
