# Full Homepage High-Fidelity Desktop Design Specification
## Voak Health Care — Corporate Digital Brand Guidelines

**Document Reference:** `docs/phase-03/05_HOMEPAGE_DESKTOP_DESIGN.md`  
**Phase:** Phase 3 of 31 — Professional UI/UX Design  
**Visual Benchmark:** Approved Second Homepage Reference Image  
**Viewport Resolution:** 1440px Desktop Baseline (Responsive from 1024px to 1920px)  
**Functional Alignment:** All 15 Homepage Functional Sections (Phase 1 Alignment)

---

### 1. Master Desktop Page Layout Architecture

The desktop homepage renders inside a high-contrast white rounded shell situated on a continuous soft lavender canvas:

```
+-----------------------------------------------------------------------------------+
|  Soft Lavender Canvas (#F2F0F8)                                                   |
|  +-----------------------------------------------------------------------------+  |
|  |  White Rounded Main Page Shell (max-w-[1360px], radius: 36px, bg: #FFFFFF)  |  |
|  |                                                                             |  |
|  |  SECTION 01: FLOATING NAVIGATION (White Pill Card, max-w-7xl, radius: full) |  |
|  |  SECTION 02: HERO BANNER (Lavender Gradient, Split Collage, Radius: 36px)   |  |
|  |  SECTION 03: THREE FEATURE CARDS (Pastel Pink, Warm Yellow, Mint Green)     |  |
|  |  SECTION 04: SERVICES BENTO GRID (8-Col & 4-Col Asymmetrical Bento Cards)   |  |
|  |  SECTION 05: WHY CHOOSE VOAK (Split Layout, Operations Manager Portrait)    |  |
|  |  SECTION 06: INDUSTRIES WE SERVE (4 Sector Bento Cards)                     |  |
|  |  SECTION 07: HOW WE WORK (4-Stage B2B Journey Cards with Connectors)        |  |
|  |  SECTION 08: QUALITY & COMPLIANCE GOVERNANCE (Institutional Frameworks)     |  |
|  |  SECTION 09: CASE STUDIES & ENTERPRISE IMPACT (Problem-Solution-Outcomes)   |  |
|  |  SECTION 10: INSIGHTS & RESOURCES HUB (Editorial Article Cards)             |  |
|  |  SECTION 11: PARTNER CTA BANNER (Purple Gradient Card, Dual Conversion)     |  |
|  |  SECTION 12: ENTERPRISE FOOTER (5-Column Layout, ABN, Governance Links)     |  |
|  +-----------------------------------------------------------------------------+  |
+-----------------------------------------------------------------------------------+
```

---

### 2. Section-by-Section Design Specifications

#### SECTION 01 — FLOATING HEADER
- **Container:** `max-width: 1280px`, sticky `top: 1rem`, centered, margin `0 auto`.
- **Card Tokens:** Background `rgba(255, 255, 255, 0.94)`, backdrop blur `16px`, `border-radius: 9999px`, padding `0.75rem 1.75rem`, border `1px solid rgba(216, 212, 229, 0.7)`, shadow `var(--shadow-md)`.
- **Components:**
  - *Logo:* Triple-care silhouette mark (38x38px) + "VOAK" in `Fraunces 22px` (`#252B5E`) + "HEALTH CARE" subtitle (`#665CE9`).
  - *Navigation Menu:* 7 links (`Home`, `About`, `Services`, `Industries`, `Careers`, `Resources`, `Contact`). Font: `Plus Jakarta Sans 15px`, weight 600, color `#252B5E`. Active link has 2px solid `#665CE9` underline.
  - *Primary Action:* Button `btn-primary` ("Partner With Us") in `#665CE9`.

#### SECTION 02 — HERO BANNER
- **Container:** Margin `1rem 1.5rem 2rem 1.5rem`, padding `4rem 3.5rem`, `border-radius: 36px`, background `linear-gradient(135deg, #F4F1FA 0%, #EBE6F7 100%)`.
- **Grid Layout:** 2 columns: Left content (1.15fr), Right visual stage (0.85fr).
- **Left Content:**
  - *Pill Badge:* "Enterprise Healthcare B2B Solutions" with a mint green dot (`#54CB96`).
  - *Headline:* "Better Healthcare Together" in `Fraunces 64px`, line-height 1.08, letter-spacing -0.03em, `#252B5E`.
  - *Lead Copy:* "Empowering healthcare systems, hospitals, and residential care providers with specialized clinical workforce solutions, operational governance, and end-to-end B2B healthcare infrastructure." (18px, `#50567A`).
  - *Buttons:* "Explore Our Services" (Primary Purple with SVG right arrow) + "Partner With Us" (Outline Navy).
- **Right Visual Stage (Approved Reference Replication):**
  - *Pink Collage Backdrop:* `#EEAFD7`, width 320px, height 380px, organic radius (`40% 60% 70% 30% / 40% 50% 60% 50%`).
  - *Yellow Accent Blob:* `#FBDC62`, width 140px, height 140px, top-left organic shape.
  - *Doctor Portrait Frame:* Width 320px, height 380px, `border-radius: 28px`, 4px solid white border, elevated shadow. Displays authentic high-res healthcare female doctor photography (`hero_doctor.jpg`).
  - *Floating Badge Top:* "Clinical Excellence" with purple heart icon box, elevation shadow.
  - *Floating Badge Bottom:* "Enterprise Partner" with yellow star icon box, elevation shadow.

#### SECTION 03 — THREE FEATURE CARDS
- **Grid:** 3 equal columns, gap `1.5rem`, padding `1.5rem 1.5rem 3rem 1.5rem`.
- **Card 1 (Pastel Pink `#EEAFD7`):**
  - Tag: "Workforce & Operations" in pill chip.
  - Title: "Healthcare Services" (`Fraunces 28px`).
  - Copy: "Rapid clinical staffing, allied health coordination, and specialized operational management tailored to hospital networks."
  - Action: Pill button `card-pill-btn` in `#252B5E` ("Discover Services →").
- **Card 2 (Warm Buttercup Yellow `#FBDC62`):**
  - Tag: "Specialized Sectors" in pill chip.
  - Title: "Industries We Serve" (`Fraunces 28px`).
  - Copy: "From major public hospitals to residential aged care, NDIS community care, and private surgical centers across Australia."
  - Action: Pill button `card-pill-btn` in `#252B5E` ("View Sectors →").
- **Card 3 (Fresh Mint Green `#54CB96`):**
  - Tag: "Strategic Co-operation" in pill chip.
  - Title: "Partner With Us" (`Fraunces 28px`).
  - Copy: "Establish institutional service agreements, customized staffing frameworks, and accredited healthcare delivery partnerships."
  - Action: Pill button `card-pill-btn` in `#252B5E` ("Request Proposal →").

#### SECTION 04 — SERVICES BENTO GRID
- **Grid:** 12-column layout with 4 bento cards.
- **Card 1 (Span 8 cols, Pink Tint `#FDF2F9`):** Clinical Workforce & Specialized Staffing. Includes tags: `Verified Credentials`, `Specialized Staffing`, `Flexible Deployment`.
- **Card 2 (Span 4 cols, Yellow Tint `#FEF9E6`):** Healthcare Operational Support & Back-Office BPO. Tag: `High Efficiency`.
- **Card 3 (Span 4 cols, Mint Tint `#EBF9F2`):** Governance & Quality Compliance Auditing. Tag: `Audit-Ready`.
- **Card 4 (Span 8 cols, Sky Blue Tint `#EEF6FC`):** Allied Health Infrastructure & Facility Support. Includes tags: `Multidisciplinary`, `Scalable Teams`.

#### SECTION 05 — WHY CHOOSE VOAK
- **Layout:** Split grid (0.9fr image, 1.1fr content), background `#F8F6FC` to `#EDE9F7`, padding `4rem 3rem`.
- **Image:** Healthcare operations coordinator portrait (`operations_manager.jpg`), framed in a rounded white card with depth shadow.
- **Content:** Heading "Why Healthcare Leaders Trust Voak", lead paragraph, and 3 operational value items with circular checkmark icons:
  1. *Rigorous Credentialing & Verification:* Primary source credential verification protocols, background checks, and certifications.
  2. *Structured Service Level Framework:* Defined operational response procedures and replacement protocols.
  3. *Human-Centred Partnership:* Dedicated B2B account coordinators and clinical governance support.
- *Factual Integrity Note:* Zero fabricated statistics or false client logos are displayed.

#### SECTION 06 — INDUSTRIES WE SERVE
- **Layout:** 4-column card grid with colored top accent borders:
  1. *Public & Private Hospitals* (Purple top border `#665CE9`)
  2. *Aged Care Facilities* (Pink top border `#EEAFD7`)
  3. *NDIS & Community Care* (Yellow top border `#FBDC62`)
  4. *Corporate & Occupational Health* (Mint top border `#54CB96`)

#### SECTION 07 — HOW WE WORK
- **Layout:** 4-column horizontal journey steps with numbered chips (`01`, `02`, `03`, `04`):
  - Step 1: Operational Discovery
  - Step 2: Solution Architecture
  - Step 3: Deployment & Integration
  - Step 4: Continuous Governance

#### SECTION 08 — QUALITY & COMPLIANCE GOVERNANCE
- **Layout:** Authoritative white cards detailing healthcare standards alignment: professional registration verification protocols, quality frameworks, and clinical safety guidelines.

#### SECTION 09 — CASE STUDIES & ENTERPRISE IMPACT
- **Layout:** Bento case study cards structured as Problem → Solution → Measurable Outcome (with verified institutional consent releases).

#### SECTION 10 — INSIGHTS & RESOURCES HUB
- **Layout:** 3-column article cards with category tags, publication dates, reading time estimates, and author clinician attribution.

#### SECTION 11 — PARTNER CTA BANNER
- **Layout:** Royal purple gradient card (`linear-gradient(135deg, #665CE9 0%, #4738C4 100%)`), text in pure white, dual actions: "Request Formal Proposal" (Yellow Pill) + "Schedule Discovery Call" (Outline White).

#### SECTION 12 — ENTERPRISE FOOTER
- **Layout:** 5-column grid (Brand info + Services + Industries + Governance + Administration) + bottom legal bar with configurable corporate entity variables, CMS-controlled regional office notice, and copyright.
