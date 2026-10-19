# Mobile, Tablet & Multi-Breakpoint Responsive Design Specification
## Voak Health Care — Corporate Digital Brand Guidelines

**Document Reference:** `docs/phase-03/10_MOBILE_TABLET_RESPONSIVENESS.md`  
**Phase:** Phase 3 of 31 — Professional UI/UX Design  
**Target Viewports Audited:** 320px, 360px, 390px, 428px, 768px, 1024px, 1280px, 1440px, 1920px  
**Standards:** Fluid Responsive Layouts, Fluid Typography (`clamp()`), Zero Horizontal Overflow

---

### 1. Viewport Adaptation Architecture

Voak Health Care implements an adaptive fluid responsive architecture ensuring visual elegance from the smallest 320px mobile viewport up to 1920px high-resolution enterprise displays:

```
+------------------------------------------------------------------------------------------------------+
| 1920px (Widescreen): Outer canvas #F2F0F8, Centered Shell max-w-[1360px], 24px Gaps, 96px Padding   |
| 1440px (Desktop):   Standard Baseline Shell max-w-[1280px], 12-Col Bento Grid, Sticky Pill Nav       |
| 1024px (Small Desk): 12-Col Bento Grid, Hero 1fr + 1fr, Touch-friendly Menus                        |
| 768px (Tablet):     2-Col Uniform Grid (6+6), Hero Stacks (Content Top, Image Bottom), 64px Padding |
| 428px (Large Mobile): Single Column Stack, Relaxed 20px Gutters, 100% Width CTA Buttons              |
| 390px (Mobile Base): Standard Smartphone Baseline, 34px Headline, 240px Portrait, 16px Gutters      |
| 360px (Android):     32px Headline, 14px Gutters, Stacked Feature Cards                              |
| 320px (Min Mobile):  30px Headline, 12px Gutters, Zero Text Overflow, 44x44px Min Touch Targets    |
+------------------------------------------------------------------------------------------------------+
```

---

### 2. Comprehensive 9-Breakpoint Responsive Matrix

| Viewport Width | Screen Category | Layout Container | Grid Behavior | Hero Composition | Navigation Mode | Admin Portal Behavior |
| :---: | :--- | :--- | :--- | :--- | :--- | :--- |
| **320px** | Min Mobile (iPhone SE) | Shell margin `0.5rem`, padding `1.25rem 0.75rem` | 1 Column (12 cols each) | Stacked: Badge → Title (30px) → Buttons (100% width) → Portrait (220px) | Hamburger Menu → Fullscreen Slide Drawer | Sidebar hidden; Fullscreen Off-Canvas Drawer |
| **360px** | Compact Android | Shell margin `0.75rem`, padding `1.5rem 1rem` | 1 Column (12 cols each) | Stacked: Badge → Title (32px) → Buttons (100% width) → Portrait (240px) | Hamburger Menu → Fullscreen Slide Drawer | Sidebar hidden; Fullscreen Off-Canvas Drawer |
| **390px** | Baseline Smartphone | Shell margin `0.75rem`, padding `1.75rem 1.25rem` | 1 Column (12 cols each) | Stacked: Badge → Title (34px) → Buttons (100% width) → Portrait (240px) | Hamburger Menu → 80% Width Slide Drawer | Sidebar hidden; 80% Width Off-Canvas Drawer |
| **428px** | Large Smartphone (Pro Max)| Shell margin `1rem`, padding `2rem 1.5rem` | 1 Column (12 cols each) | Stacked: Badge → Title (36px) → Buttons (100% width) → Portrait (260px) | Hamburger Menu → 80% Width Slide Drawer | Sidebar hidden; Off-Canvas Drawer |
| **768px** | Portrait Tablet (iPad) | Shell margin `1.25rem`, padding `2.5rem 2rem` | 2 Columns (`6 + 6`) | Split: Content Left (60%), Portrait Right (40%) | Hamburger Menu → Slide Drawer | Collapsible Icon Sidebar (72px width) |
| **1024px** | Landscape Tablet / Laptop | Shell margin `1.5rem`, padding `3rem 2.5rem` | 12 Columns (`8 + 4`, `4 + 8`) | Split Grid (1.1fr + 0.9fr), Full Collage Stage | Horizontal Pill Bar (Visible Nav Links) | Fixed Sidebar (240px width) |
| **1280px** | Standard Laptop | Centered Shell `max-w-7xl` | 12 Columns (`8 + 4`, `4 + 8`) | Full Hero Collage with Floating Badges | Horizontal Pill Bar (Full Navigation) | Fixed Sidebar (260px width) |
| **1440px** | Desktop Baseline | Centered Shell `max-w-[1360px]`| 12 Columns (`8 + 4`, `4 + 8`) | Full Hero Collage with Floating Badges | Horizontal Pill Bar (Full Navigation) | Fixed Sidebar (260px width) |
| **1920px** | Full HD Widescreen | Centered Shell `max-w-[1360px]`| 12 Columns (`8 + 4`, `4 + 8`) | Full Hero Collage with Floating Badges | Horizontal Pill Bar (Full Navigation) | Fixed Sidebar (260px width) |

---

### 3. Touch Ergonomics & Form Interaction on Mobile

- **Touch Target Dimensions:** All interactive triggers (buttons, navigation links, accordion headers, form checkboxes) maintain a minimum hit area of **44px × 44px**.
- **Keyboard Optimization:**
  - Phone inputs specify `type="tel"` and `inputmode="tel"`.
  - Numeric verification codes specify `inputmode="numeric"` and `pattern="[0-9]*"`.
  - Email fields specify `type="email"` and `autocomplete="email"`.
- **Data Tables on Mobile:** Admin data tables and comparison matrices implement smooth horizontal touch scrolling (`overflow-x: auto; -webkit-overflow-scrolling: touch`) with sticky identifier columns so context is never lost.
