# Full Homepage High-Fidelity Mobile Design Specification
## Voak Health Care — Corporate Digital Brand Guidelines

**Document Reference:** `docs/phase-03/06_HOMEPAGE_MOBILE_DESIGN.md`  
**Phase:** Phase 3 of 31 — Professional UI/UX Design  
**Visual Benchmark:** Approved Second Homepage Reference Image (Mobile Reflow)  
**Target Viewports:** 320px (min), 360px (Android compact), 390px (iPhone baseline), 428px (Pro Max)  
**Quality Invariant:** Zero horizontal scrolling, zero text clipping, touch targets ≥ 44x44px

---

### 1. Mobile Architectural Principles & Reflow Logic

Mobile design for Voak Health Care is not a crude scaled-down desktop rendering. It is a **purpose-engineered vertical composition** maintaining the full brand dignity of the approved reference while optimizing for single-thumb navigation, rapid comprehension, and mobile touch ergonomics.

```
+------------------------------------------+
|  Soft Lavender Canvas (#F2F0F8)          |
|  +------------------------------------+  |
|  | [VOAK Logo]         [Partner Pill] |  |
|  |------------------------------------|  |
|  | HERO SECTION (bg: #F4F1FA, r: 28px)|  |
|  | - [B2B Healthcare Badge]           |  |
|  | - "Better Healthcare Together"     |  |
|  |   (Fraunces 34px / line-height 1.1)|  |
|  | - Subtitle Copy (15px, #50567A)    |  |
|  | - [Explore Our Services Btn (100%)]|  |
|  | - [Partner With Us Btn (100%)]     |  |
|  |                                    |  |
|  |   +----------------------------+   |  |
|  |   | Doctor Portrait Frame      |   |  |
|  |   | (240x260px, rounded-2xl)   |   |  |
|  |   +----------------------------+   |  |
|  +------------------------------------+  |
|                                          |
|  +------------------------------------+  |
|  | Feature Card 1: Pink (#EEAFD7)     |  |
|  | Healthcare Services -> Discover    |  |
|  +------------------------------------+  |
|                                          |
|  +------------------------------------+  |
|  | Feature Card 2: Yellow (#FBDC62)   |  |
|  | Industries We Serve -> View        |  |
|  +------------------------------------+  |
|                                          |
|  +------------------------------------+  |
|  | Feature Card 3: Mint (#54CB96)     |  |
|  | Partner With Us -> Request         |  |
|  +------------------------------------+  |
|                                          |
|  [ Stacked Bento Services (12 cols) ]    |
|  [ Why Choose Voak Stacked ]             |
|  [ How We Work: 01 to 04 Stacked ]       |
|  [ Mobile Enterprise Footer ]            |
+------------------------------------------+
```

---

### 2. Detailed Mobile Section Reflow Specifications

#### A. Mobile Navigation Header
- **Container:** Sticky top, padding `0.75rem 1rem`, background `#FFFFFF`, border-bottom `1px solid var(--voak-border-subtle)`.
- **Branding:** Compact Voak logo (30x30px icon) + "VOAK" in `Fraunces 18px` (`#252B5E`).
- **Right Action:** Compact pill button (*"Partner"*) in `#665CE9`, font size `13px`, padding `0.4rem 0.85rem`, minimum height `36px`.
- **Hamburger Trigger:** 44x44px accessible touch target triggering the slide-out navigation drawer.

#### B. Mobile Hero Section
- **Container:** Margin `0.75rem 0`, padding `2rem 1.25rem`, `border-radius: 28px`, background `linear-gradient(135deg, #F4F1FA 0%, #EBE6F7 100%)`.
- **Pill Badge:** Centered "B2B Healthcare" badge with mint indicator dot.
- **Headline:** "Better Healthcare Together" in `Fraunces 34px`, line-height `1.12`, letter-spacing `-0.025em`, `#252B5E`. Fits comfortably on 2 to 3 lines at 320px without word break hyphenation.
- **Lead Text:** 15px, line-height 1.55, text color `#50567A`.
- **Action Buttons:** Both buttons span 100% container width with vertical gap `0.75rem`. Minimum tap target height `48px`.
- **Portrait Presentation:** Re-anchored beneath the CTA buttons. Portrait frame: `width: 240px; height: 260px; border-radius: 20px; border: 3px solid #FFF; box-shadow: var(--shadow-md)`. Background pink and yellow organic shapes scale proportionally to avoid overflow.

#### C. Mobile Three Feature Cards Stack
- The 3 feature cards stack vertically with `1rem` gap:
  - **Pink Card:** Height auto, padding `1.5rem 1.25rem`. Title `22px`. Button `card-pill-btn` left-aligned.
  - **Yellow Card:** Height auto, padding `1.5rem 1.25rem`. Title `22px`. Button `card-pill-btn` left-aligned.
  - **Mint Card:** Height auto, padding `1.5rem 1.25rem`. Title `22px`. Button `card-pill-btn` triggers RFP Wizard.

#### D. Mobile Services Bento Stack
- All 12-column bento cards reflow to `span 12` (100% width).
- Inner card padding adjusts to `1.5rem 1.25rem`.
- Icon box: 40x40px.
- Tag chips wrap gracefully with `0.4rem` gap.

#### E. Mobile "Why Choose Voak"
- Operations manager portrait stacks above the value points.
- Value items display with 36x36px circular checkmark icons and `1rem` inline gap.

#### F. Mobile "How We Work"
- Journey steps 01, 02, 03, and 04 stack vertically. Each step card has `border-radius: 16px`, padding `1.25rem`, and prominent purple step number chip.

#### G. Mobile Enterprise Footer
- Collapses from 5 columns to a clean vertical accordion / stacked list.
- Corporate entity details, CMS-controlled regional office notice, and copyright information are centered with generous line height and tap targets.

---

### 3. Viewport Verification Matrix

| Viewport Width | Screen Category | Headline Size | Layout Adjustments | Status |
| :---: | :---: | :---: | :--- | :---: |
| **320px** | Minimum iPhone SE / Compact | 30px | 12px outer padding, portrait scales to 220px | **PASS (Zero Overflow)** |
| **360px** | Standard Android | 32px | 16px outer padding, portrait scales to 240px | **PASS (Zero Overflow)** |
| **390px** | Baseline iPhone 14/15/16 | 34px | 20px outer padding, standard mobile baseline | **PASS (Zero Overflow)** |
| **428px** | iPhone Pro Max / Plus | 36px | 24px outer padding, relaxed card whitespace | **PASS (Zero Overflow)** |
