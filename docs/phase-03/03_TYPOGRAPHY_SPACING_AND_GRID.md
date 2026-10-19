# Typography, Spacing Scale and Bento Grid Architecture
## Voak Health Care — Corporate Digital Brand Guidelines

**Document Reference:** `docs/phase-03/03_TYPOGRAPHY_SPACING_AND_GRID.md`  
**Phase:** Phase 3 of 31 — Professional UI/UX Design  
**Typography Authorities:** `Fraunces` (Display Serif) & `Plus Jakarta Sans` (UI Sans)  
**Grid Standards:** 12-Column Responsive Fluid Grid & Asymmetric Bento Architecture

---

### 1. Typography Hierarchy & Font Stacks

To faithfully reproduce the editorial modernity of the approved second reference image, Voak Health Care pairs an expressive optical serif with a crisp, geometric sans-serif:

```css
:root {
  --font-serif: 'Fraunces', Georgia, Cambria, 'Times New Roman', serif;
  --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
```

#### Detailed Typography Hierarchy Table:

| Level | Semantic Tag | Font Family | Size (Desktop) | Size (Mobile) | Line Height | Weight | Letter Spacing | Color Token |
| :--- | :---: | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| **Hero Display** | `h1` | `var(--font-serif)` | `clamp(2.5rem, 5vw + 1rem, 4.25rem)` (68px max) | `2.15rem` (34px) | 1.08 | 700 Bold | -0.03em | `--voak-navy` (`#252B5E`) |
| **Section Title**| `h2` | `var(--font-serif)` | `clamp(1.75rem, 3vw + 0.5rem, 2.5rem)` (40px max) | `1.75rem` (28px) | 1.15 | 600 SemiBold | -0.02em | `--voak-navy` (`#252B5E`) |
| **Card Heading** | `h3` | `var(--font-serif)` | `clamp(1.35rem, 2vw + 0.25rem, 1.85rem)` (30px max)| `1.35rem` (22px) | 1.25 | 600 SemiBold | -0.015em | `--voak-navy` (`#252B5E`) |
| **Subsection**   | `h4` | `var(--font-serif)` | `1.25rem` (20px) | `1.15rem` (18px) | 1.30 | 600 SemiBold | -0.01em | `--voak-navy` (`#252B5E`) |
| **Lead Paragraph**| `p.lead` | `var(--font-sans)` | `1.125rem` (18px) | `1.0rem` (16px) | 1.60 | 400 Regular | normal | `--voak-muted` (`#50567A`) |
| **Body Text**    | `p` | `var(--font-sans)` | `1.0rem` (16px) | `0.938rem` (15px) | 1.65 | 400 Regular | normal | `--voak-muted` (`#50567A`) |
| **Table & UI**   | `td, th` | `var(--font-sans)` | `0.875rem` (14px) | `0.813rem` (13px) | 1.45 | 500 Medium | normal | `--voak-navy` / `--voak-muted` |
| **Button Label** | `button` | `var(--font-sans)` | `0.938rem` (15px) | `0.875rem` (14px) | 1.00 | 600 SemiBold | +0.01em | `#FFFFFF` / `--voak-navy` |
| **Pill / Badge** | `span.chip` | `var(--font-sans)` | `0.75rem` (12px) | `0.70rem` (11px) | 1.20 | 700 Bold | +0.08em | Color-coded chips |

---

### 2. Spacing Scale & Baseline Vertical Rhythm

Voak Health Care uses an 8px baseline spatial grid. All padding, margins, and component gaps derive from this system:

```
[4px]  - Micro-gap, badge icon margin
[8px]  - Inline tag gap, tight button padding
[12px] - Inner bento sub-element gap
[16px] - Standard input padding, mobile gutter
[24px] - Bento card gap, table cell vertical padding
[32px] - Card inner padding, section sub-block gap
[48px] - Section header margin, hero button row gap
[64px] - Section top/bottom padding (tablet/mobile)
[96px] - Section top/bottom padding (desktop)
```

---

### 3. Asymmetric Bento Grid Architecture

The homepage, service directory, and industry overview utilize a **12-column responsive bento grid**:

```
+-------------------------------------------------------------------------------+
| BENTO GRID: 12 COLUMNS                                                        |
|-------------------------------------------------------------------------------|
| [ CARD 1: 8 COLUMNS - PINK TINT ]            | [ CARD 2: 4 COLUMNS - YELLOW ] |
| Healthcare Workforce & Specialized Staffing  | Healthcare Operations BPO      |
|-------------------------------------------------------------------------------|
| [ CARD 3: 4 COLUMNS - MINT TINT ]            | [ CARD 4: 8 COLUMNS - SKY BLUE]|
| Governance & Quality Compliance              | Allied Health Infrastructure   |
+-------------------------------------------------------------------------------+
```

#### Responsive Column Reflow Rules:
- **Desktop (≥ 1024px):** 12-column grid. Cards span `8 + 4`, `4 + 8`, or `4 + 4 + 4`.
- **Tablet (768px – 1023px):** 2-column uniform grid (`6 + 6`).
- **Mobile (≤ 767px):** Single-column stacked cards (`12 cols` each). Zero horizontal scroll or margin truncation.

#### Max Container Widths:
- Primary Layout Shell: `max-w-[1360px]`
- Content Grids: `max-w-7xl` (`1280px`)
- Reading Articles & Policy Texts: `max-w-4xl` (`896px`)
- Form & Modal Cards: `max-w-2xl` (`680px`)
