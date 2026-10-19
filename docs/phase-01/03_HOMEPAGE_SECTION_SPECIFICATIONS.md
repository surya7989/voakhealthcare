# Homepage Section Functional Specifications
## Exhaustive Architectural Specification for All 15 Homepage Sections

**Document Reference:** `docs/phase-01/03_HOMEPAGE_SECTION_SPECIFICATIONS.md`  
**Phase:** Phase 1 of 31 — Complete Functional Specification  
**Visual & Design Authority:** Approved Second Generated Homepage Reference (`docs/phase-00/05_APPROVED_DESIGN_SYSTEM.md`), Palette: `#F2F0F8` canvas, `#EEAFD7` pink, `#FBDC62` yellow, `#54CB96` mint, `#63A5E6` sky blue, `#665CE9` purple CTA, `#252B5E` navy typography, `#50567A` muted typography.

---

### Executive Overview & Section Inventory

The Voak Health Care homepage is the primary executive touchpoint for enterprise B2B healthcare decision-makers. It must project uncompromising operational reliability, clinical governance, and institutional sophistication.

This document details the functional, editorial, responsive, and technical behavior for all 15 homepage sections:

```
01. Global Header and Sticky Navigation
02. Asymmetrical Hero Section
03. Corporate Value Proposition Statement
04. Primary Conversion Call-to-Action (CTA) Cluster
05. Featured Healthcare Services Bento Showcase
06. Supported Healthcare Industry Sectors Directory
07. Core Operational Capabilities Matrix
08. Strategic Differentiation ("Why Choose Voak")
09. 4-Stage Operational Partnership Process
10. Clinical Quality & Governance Framework
11. Approved Institutional Partner & Client Proof (Conditional)
12. Evidence-Based Case Study Spotlight (Conditional)
13. Thought Leadership & Resource Highlights
14. Executive Partnership Conversion Banner
15. Global Corporate Footer & Legal Disclaimers
```

---

### Section Specifications (01 to 15)

---

#### Section 01: Global Header and Sticky Navigation
- **Section Code:** `HP-SEC-01`
- **Purpose:** Provide persistent brand orientation, intuitive multi-level navigation across public pages, and high-visibility access to the primary commercial conversion action ("Request a Proposal").
- **Content Inputs:**
  - Corporate SVG logo (Voak Health Care mark + wordmark).
  - Navigation menu hierarchy:
    - Services (Mega-menu / Dropdown linking to 6 service pillars + `/services` overview).
    - Industries (Dropdown linking to 6 sectors + `/industries` overview).
    - About Us (Dropdown: Corporate Overview, Why Choose Voak, How We Work, Quality & Compliance).
    - Insights & Resources (`/insights`).
    - Careers (`/careers`).
    - Contact Us (`/contact`).
  - Primary Header Action: "Request a Proposal" button.
  - Optional Announcement Ribbon (e.g., "Now partnering with regional healthcare networks across Victoria").
- **CMS Fields:**
  - `announcement_text` (string, optional, max 120 chars).
  - `announcement_link` (string, optional URL).
  - `announcement_active` (boolean toggle).
  - `header_cta_label` (string, default "Request a Proposal").
  - `header_cta_destination` (string, default `/request-proposal`).
- **Rendering Conditions:** Always rendered at the top of the viewport. Becomes sticky on scroll with a subtle backdrop blur (`backdrop-blur-md bg-[#F2F0F8]/90 border-b border-[#252B5E]/10`).
- **Image Requirements:** High-resolution vector SVG logo (`viewBox="0 0 240 48"`), optimized for light canvas; zero raster artifacts.
- **Responsive Ordering:**
  - Desktop (>= 1024px): Horizontal nav links with animated underline hover indicators; right-aligned purple CTA button.
  - Mobile (< 1024px): Logo left, primary quick CTA icon center, hamburger menu icon right. Clicking hamburger triggers full-height slide-over drawer from right.
- **CTA Behavior:** Clicking "Request a Proposal" directly routes to `/request-proposal`. In mobile drawer, primary CTA is pinned to the bottom of the drawer viewport.
- **Link Destinations:** Valid internal React Router links matching defined routes.
- **Data Dependencies:** Navigation structure is static in code; announcement banner fetched from `GET /api/v1/public/settings/header`.
- **Disabled / Unpublished Behavior:** If `announcement_active = 0`, the announcement ribbon collapses completely (`height: 0; opacity: 0`) without leaving margin or layout shift.
- **Accessibility:**
  - `<header role="banner">`, `<nav aria-label="Main Navigation">`.
  - Dropdown triggers implement `aria-haspopup="true"`, `aria-expanded="false|true"`, `aria-controls="menu-dropdown-id"`.
  - Mobile hamburger button has `aria-label="Toggle navigation menu"`.
  - Focus trap engaged when mobile drawer is open; Escape key closes drawer and returns focus to hamburger button.
- **Loading & Fallback Behavior:** Static skeleton rendered instantly by PHP front controller; navigation links work via standard `<a>` tags even if JavaScript is disabled.

---

#### Section 02: Asymmetrical Hero Section
- **Section Code:** `HP-SEC-02`
- **Purpose:** Deliver an immediate, high-impact value proposition establishing Voak Health Care as the premier B2B healthcare operations partner, utilizing the approved editorial aesthetic.
- **Content Inputs:**
  - Category / Trust Kicker: "ENTERPRISE HEALTHCARE OPERATIONS & WORKFORCE SOLUTIONS".
  - Hero Headline: "Operational Resilience for Complex Healthcare Environments." (Rendered in serif display typography `Fraunces`, color `#252B5E`).
  - Hero Narrative: "We partner with Australian hospitals, healthcare networks, and aged care providers to solve critical staffing shortages, optimize clinical facility workflows, and maintain uncompromising compliance standards."
  - Action Cluster: Primary CTA ("Request a Proposal") + Secondary Ghost CTA ("Explore Services").
  - Hero Visual Composition: Layered asymmetrical layout combining soft lavender backdrop (`#F2F0F8`), rounded pastel cards, and a human-centered professional healthcare team photograph.
  - Floating Operational Metric Pill: "100% AHPRA Credentialed Standards" (Configurable governance badge).
- **CMS Fields:**
  - `hero_kicker` (string, max 60 chars).
  - `hero_headline` (string, max 100 chars).
  - `hero_subhead` (string, max 300 chars).
  - `hero_image_asset_id` (foreign key to `media_assets`).
  - `hero_pill_text` (string, max 50 chars).
- **Rendering Conditions:** Always rendered immediately below the global header.
- **Image Requirements:** High-resolution professional photograph depicting clinical professionals in a modern healthcare environment. Must be royalty-free/licensed, WebP format, dimensions: 1200x900px, compressed < 180KB, with explicit `fetchpriority="high"`.
- **Responsive Ordering:**
  - Desktop: 2-column layout (Text left 55%, Asymmetrical Visual Composition right 45%).
  - Tablet/Mobile: Text top, image composition below. Text aligned left; CTAs stack vertically on screens < 480px.
- **CTA Behavior:** Primary button (`bg-[#665CE9] text-white`) navigates to `/request-proposal`; Secondary button (`border border-[#252B5E]/20 text-[#252B5E]`) smoothly scrolls to `#services-bento` or navigates to `/services`.
- **Link Destinations:** `/request-proposal`, `/services`.
- **Data Dependencies:** Fetched via `GET /api/v1/public/homepage` -> `hero` object.
- **Disabled / Unpublished Behavior:** If CMS returns empty strings, hardcoded approved fallback copy displays. Hero section is non-collapsible.
- **Accessibility:**
  - Semantic `<h1>` strictly assigned to the hero headline.
  - Hero image contains descriptive alt text: `alt="Voak Health Care clinical operations team reviewing facility compliance protocols"`.
  - Contrast: Headline `#252B5E` on `#F2F0F8` has contrast ratio of 11.73:1 (passes AAA).
- **Loading & Fallback Behavior:** PHP front controller pre-renders the complete hero text and image in server HTML, preventing Cumulative Layout Shift (CLS score < 0.05).

---

#### Section 03: Corporate Value Proposition Statement
- **Section Code:** `HP-SEC-03`
- **Purpose:** Reinforce Voak's core institutional thesis: transforming fragmented healthcare operational challenges into dependable, governance-backed systems.
- **Content Inputs:**
  - Section Eyebrow: "OUR INSTITUTIONAL COMMITMENT".
  - Large Editorial Statement: "Healthcare leadership cannot afford operational fragility. Voak delivers the clinical talent, administrative systems, and regulatory governance required to sustain continuous clinical care."
  - 3 Core Value Pillars (Icons + Mini Headlines):
    1. Clinical Rigor: AHPRA-aligned credentialing and clinical oversight.
    2. Operational Agility: Rapid, dependable workforce deployment.
    3. Transparent Governance: SLA-backed performance accountability.
- **CMS Fields:**
  - `value_prop_headline` (string, max 150 chars).
  - `pillar_1_title`, `pillar_1_desc`, `pillar_1_icon`.
  - `pillar_2_title`, `pillar_2_desc`, `pillar_2_icon`.
  - `pillar_3_title`, `pillar_3_desc`, `pillar_3_icon`.
- **Rendering Conditions:** Always rendered.
- **Image Requirements:** Minimalist SVG line-art icons in royal purple (`#665CE9`) inside rounded pastel circles (`#EEAFD7`, `#FBDC62`, `#54CB96`).
- **Responsive Ordering:** Desktop: 3-column horizontal cards; Mobile: stacked vertically.
- **CTA Behavior:** Informational only; cards subtly highlight on hover.
- **Link Destinations:** None directly in cards; contextual link to `/why-choose-voak`.
- **Data Dependencies:** `GET /api/v1/public/homepage` -> `value_props`.
- **Disabled / Unpublished Behavior:** Core pillars default to system baseline if unedited.
- **Accessibility:** Text marked with `<h2>` for section header and `<h3>` for pillars; contrast ratios meet WCAG 2.2 AA.
- **Loading & Fallback Behavior:** Static pre-rendered text in HTML.

---

#### Section 04: Primary Conversion Call-to-Action (CTA) Cluster
- **Section Code:** `HP-SEC-04`
- **Purpose:** Provide an immediate, friction-free engagement bridge directly below the hero and value statement for procurement leads who arrived with clear intent.
- **Content Inputs:**
  - Dual Engagement Box:
    - Track A: "Immediate Clinical Staffing or Facility Need" -> Direct RFP submission.
    - Track B: "Strategic Partnership or Network Co-Sourcing" -> Consultation booking.
  - Direct Phone Contact Badge: Official business inquiry number (e.g., `1300 VOAK HC`).
  - Response Guarantee Pill: "Response Guaranteed Within 1 Business Day".
- **CMS Fields:**
  - `cluster_track_a_title` (string).
  - `cluster_track_b_title` (string).
  - `cluster_phone_display` (string).
  - `cluster_phone_tel` (string).
- **Rendering Conditions:** Always rendered.
- **Image Requirements:** None (clean card styling with SVG action arrows).
- **Responsive Ordering:** 2-column split on desktop; stacked 1-column on mobile.
- **CTA Behavior:** Track A navigates to `/request-proposal`; Track B navigates to `/partner-with-us`; phone link triggers `tel:` protocol.
- **Link Destinations:** `/request-proposal`, `/partner-with-us`, `tel:XXXXXXXXXX`.
- **Data Dependencies:** Static configuration.
- **Disabled / Unpublished Behavior:** If phone number is unconfirmed, the phone button is hidden and replaced with "Inquire Online" linking to `/contact`.
- **Accessibility:** All phone and button links have distinct `aria-label` text explaining action.
- **Loading & Fallback Behavior:** Pre-rendered HTML.

---

#### Section 05: Featured Healthcare Services Bento Showcase
- **Section Code:** `HP-SEC-05`
- **Purpose:** Showcase Voak Health Care's 6 configurable service pillars in an engaging, interactive bento-grid composition that encourages exploration.
- **Content Inputs:**
  - Section Header: Eyebrow: "OUR OPERATIONAL PILLARS", Headline: "Comprehensive Healthcare Operational Solutions".
  - 6 Asymmetrical Bento Grid Cards (Representing the 6 unconfirmed configurable proposals):
    1. Clinical Workforce Solutions (Large card, Mint accent `#54CB96`)
    2. Healthcare Facility Operations (Standard card, Sky Blue accent `#63A5E6`)
    3. Health Administration & Medical Support (Standard card, Yellow accent `#FBDC62`)
    4. Healthcare Compliance & Clinical Governance (Standard card, Pink accent `#EEAFD7`)
    5. Healthcare Logistics & Supply Chain Support (Standard card, Mint accent `#54CB96`)
    6. Healthcare Consulting & Operational Advisory (Wide card, Royal Purple accent `#665CE9`)
  - Each card features: Icon, Service Title, Concise Scope Narrative, Deliverables Badge List, "View Details" arrow button.
  - Section Footer CTA: "View All 6 Service Pillars & SLA Specifications" -> `/services`.
- **CMS Fields:**
  - Linked to `services` table records where `is_featured = 1` and `is_published = 1` (ordered by `display_order`).
  - Editable via Admin Module 07 (Services Management).
- **Rendering Conditions:** Requires at least 3 published services to render. If fewer than 3 services exist, section falls back to a 3-column linear layout.
- **Image Requirements:** High-quality custom SVG service icons (stroke 2px, filled with secondary pastels). Optional subtle background ambient illustration.
- **Responsive Ordering:**
  - Desktop: 3-column asymmetrical grid with cards spanning 1 or 2 columns/rows.
  - Tablet: 2-column uniform grid.
  - Mobile: 1-column vertically stacked cards.
- **CTA Behavior:** Clicking any bento card routes directly to the specific service detail page (`/services/:slug`).
- **Link Destinations:** `/services/:slug`, `/services`.
- **Data Dependencies:** `GET /api/v1/public/homepage` -> `featured_services` array.
- **Disabled / Unpublished Behavior:** If a service has `is_published = 0`, it is omitted from the grid. The remaining cards reflow automatically using CSS Grid `grid-auto-flow: dense`.
- **Accessibility:** Cards are semantic `<article>` tags wrapped in accessible links with descriptive titles; hover transform respects `prefers-reduced-motion`.
- **Loading & Fallback Behavior:** 6-card shimmer skeleton in soft lavender shimmers during initial client-side data fetch; server-rendered HTML fallback present.

---

#### Section 06: Supported Healthcare Industry Sectors Directory
- **Section Code:** `HP-SEC-06`
- **Purpose:** Demonstrate domain expertise across distinct healthcare institutional environments.
- **Content Inputs:**
  - Section Header: "Tailored to Your Healthcare Environment".
  - 6 Sector Cards:
    1. Hospitals & Health Services (Acute care, surgical staffing, emergency overflow).
    2. Residential Aged Care Facilities (Quality standards compliance, 24/7 clinical staffing).
    3. Primary Care & General Practice (Practice administration, nurse practitioners).
    4. Allied Health Networks (Multi-disciplinary scheduling, credentialing).
    5. Diagnostic & Pathology Centers (Laboratory technicians, specimen logistics).
    6. Corporate & Occupational Health (Workplace wellness, health surveillance).
  - Direct Action: "Explore Sector Solutions" -> `/industries/:slug`.
- **CMS Fields:**
  - Linked to `industries` table records where `is_published = 1` (ordered by `display_order`).
- **Rendering Conditions:** Always rendered.
- **Image Requirements:** Curated SVG category icons or clean, high-contrast monochrome healthcare illustrations.
- **Responsive Ordering:** Desktop: 3x2 grid; Tablet: 2x3 grid; Mobile: 1x6 stacked.
- **CTA Behavior:** Clicking card navigates to `/industries/:slug`.
- **Link Destinations:** `/industries/:slug`, `/industries`.
- **Data Dependencies:** `GET /api/v1/public/homepage` -> `industries` array.
- **Disabled / Unpublished Behavior:** Unpublished sectors are omitted; layout adapts dynamically.
- **Accessibility:** Each card has `role="group"` with explicit `aria-labelledby`.
- **Loading & Fallback Behavior:** Pre-rendered HTML.

---

#### Section 07: Core Operational Capabilities Matrix
- **Section Code:** `HP-SEC-07`
- **Purpose:** Provide concrete, procurement-grade proof of operational rigor without fabricating unverified statistics.
- **Content Inputs:**
  - Section Header: "Engineered for Clinical Continuity & Compliance".
  - 4 Capability Quadrants:
    - Quadrant A: 100% Pre-Placement Credentialing (AHPRA check, police certificates, clinical reference verification).
    - Quadrant B: Rapid Incident Escalation (Defined clinical escalation hierarchy with 24/7 on-call governance).
    - Quadrant C: Transparent SLA Telemetry (Clear reporting on shift fulfillment, arrival times, and compliance audits).
    - Quadrant D: Turnkey Operational Integration (Dedicated account managers and custom rostering API compatibility).
- **CMS Fields:**
  - Quadrant titles, descriptions, and iconography managed via CMS Homepage settings.
- **Rendering Conditions:** Always rendered.
- **Image Requirements:** Custom vector line diagrams explaining workflow pathways.
- **Responsive Ordering:** 4-column layout on desktop; 2x2 on tablet; 1-column on mobile.
- **CTA Behavior:** Informational with link to `/quality-compliance`.
- **Link Destinations:** `/quality-compliance`.
- **Data Dependencies:** Static CMS configuration.
- **Disabled / Unpublished Behavior:** Defaults to baseline text.
- **Accessibility:** High-contrast text; headings structured as `<h3>`.
- **Loading & Fallback Behavior:** Pre-rendered in server markup.

---

#### Section 08: Strategic Differentiation ("Why Choose Voak")
- **Section Code:** `HP-SEC-08`
- **Purpose:** Directly address the pain points of healthcare executives frustrated by transactional staffing agencies and rigid overhead models.
- **Content Inputs:**
  - Section Header: "A Fundamental Shift in Healthcare Operations".
  - 3-Way Comparative Feature Matrix Preview:
    - Criteria 1: Clinical Oversight (Voak: Active Governance; Traditional Agency: Zero Oversight; In-House: High Admin Burden).
    - Criteria 2: Credentialing Guarantee (Voak: Verified Before Shift; Agency: Unvetted Pool; In-House: Manual Bottlenecks).
    - Criteria 3: SLA Accountability (Voak: Guaranteed Fill Rates; Agency: Best Effort; In-House: Staff Burnout).
  - Link to Full Matrix: "View Complete Comparative Analysis" -> `/why-choose-voak`.
- **CMS Fields:**
  - Configurable comparative criteria rows in Admin Module 05.
- **Rendering Conditions:** Always rendered.
- **Image Requirements:** None.
- **Responsive Ordering:** Table layout on desktop (>= 768px); stacked comparison cards on mobile (< 768px).
- **CTA Behavior:** Navigates to `/why-choose-voak`.
- **Link Destinations:** `/why-choose-voak`.
- **Data Dependencies:** `GET /api/v1/public/homepage` -> `comparison_preview`.
- **Disabled / Unpublished Behavior:** Falls back to hardcoded 3-row baseline.
- **Accessibility:** Data table has proper `<th>` headers with `scope` attributes; mobile cards maintain clear association between criteria and values.
- **Loading & Fallback Behavior:** Pre-rendered HTML.

---

#### Section 09: 4-Stage Operational Partnership Process
- **Section Code:** `HP-SEC-09`
- **Purpose:** Demystify how Voak Health Care integrates with a new healthcare facility, setting clear expectations for procurement evaluation.
- **Content Inputs:**
  - Section Header: "Our 4-Stage Implementation Model".
  - Stage 1: Diagnostic Assessment (Reviewing facility rostering, gaps, and compliance posture).
  - Stage 2: Bespoke SLA Architecture (Designing custom shift structures, escalation paths, and billing models).
  - Stage 3: Phased Integration (Deploying verified personnel and integrating administrative workflows).
  - Stage 4: Continuous Governance (Regular audit reviews, KPI reporting, and stakeholder check-ins).
  - CTA Link: "Read Full Operational Methodology" -> `/how-we-work`.
- **CMS Fields:**
  - Stage titles and descriptions editable in CMS.
- **Rendering Conditions:** Always rendered.
- **Image Requirements:** Connected timeline tracker graphics with animated SVG pulse on active stage.
- **Responsive Ordering:** Horizontal stepper track on desktop (>= 1024px); vertical connecting timeline on tablet/mobile (< 1024px).
- **CTA Behavior:** Navigates to `/how-we-work`.
- **Link Destinations:** `/how-we-work`.
- **Data Dependencies:** Static CMS content.
- **Disabled / Unpublished Behavior:** Standard defaults apply.
- **Accessibility:** Ordered list `<ol>` containing `<li>` steps; clear numeric ordering for screen readers.
- **Loading & Fallback Behavior:** Pre-rendered HTML.

---

#### Section 10: Clinical Quality & Governance Framework
- **Section Code:** `HP-SEC-10`
- **Purpose:** Reassure hospital boards, clinical leaders, and compliance directors of Voak's rigorous clinical standards and non-clinical B2B boundaries.
- **Content Inputs:**
  - Governance Badge: "CLINICAL AUDIT READINESS".
  - Headline: "Upholding Australia's Most Rigorous Healthcare Standards".
  - Standards Checklist:
    - Alignment with NSQHS (National Safety and Quality Health Service) Standards.
    - Full compliance with Aged Care Quality Standards.
    - Continuous AHPRA registration tracking and renewal verification.
    - Formal infection control and mandatory training compliance.
  - Compliance Callout: "Non-Clinical Operational Boundary: Voak Health Care provides enterprise workforce and operational systems. Individual patient clinical care is managed under the direct clinical supervision of our partner healthcare facilities."
- **CMS Fields:**
  - Governance headline, checklist items, and boundary text editable in Admin Module 05.
- **Rendering Conditions:** Always rendered.
- **Image Requirements:** High-contrast shield and verified checkmark vector icons.
- **Responsive Ordering:** 2-column layout (Headline + Narrative left, Checklist cards right). Stacks on mobile.
- **CTA Behavior:** Link to `/quality-compliance`.
- **Link Destinations:** `/quality-compliance`.
- **Data Dependencies:** Static CMS content.
- **Disabled / Unpublished Behavior:** Baseline copy displayed.
- **Accessibility:** List items marked semantically; disclaimer wrapped in `<aside>` or callout card with distinct border.
- **Loading & Fallback Behavior:** Pre-rendered HTML.

---

#### Section 11: Approved Institutional Partner & Client Proof (Conditional)
- **Section Code:** `HP-SEC-11`
- **Purpose:** Display verified partner healthcare networks, hospital groups, and clinical associations to establish social proof.
- **Content Inputs:**
  - Section Header: "Trusted by Healthcare Leaders Across Australia".
  - Partner Logo Grid / Ribbon: Monochrome SVG logos of confirmed, contractually verified partner institutions.
  - Testimonial Quote (Optional): Verified quote from a healthcare operations director with verified release consent.
- **CMS Fields:**
  - Managed via Admin Module 15 (Case Studies & Testimonials) and Module 10 (Media).
  - Toggled via `is_published` flag.
- **Rendering Conditions (CRITICAL):**
  - **CONDITIONAL RENDERING RULE:** This section renders **IF AND ONLY IF** at least 1 verified partner entity or testimonial has `is_published = 1` in the database.
  - **UNVERIFIED STATE:** When zero verified partners exist, this entire section is **COMPLETELY REMOVED FROM THE DOM** (`display: none` / omitted during SSR).
  - **NO PLACEHOLDERS:** Under no circumstances shall placeholder logos (e.g., "Company Logo", dummy hospital names) or unconfirmed client marks be displayed.
- **Image Requirements:** Approved SVG monochrome logos (max height 40px, uniform opacity 0.7, hover opacity 1.0).
- **Responsive Ordering:** 4 to 6 logos per row on desktop; 2 to 3 per row on mobile.
- **CTA Behavior:** Link to `/partners-clients` (if published).
- **Link Destinations:** `/partners-clients`.
- **Data Dependencies:** `GET /api/v1/public/homepage` -> `partners` array.
- **Disabled / Unpublished Behavior:** Clean collapse; zero margins, zero layout gaps.
- **Accessibility:** Every logo must have an authentic `alt` attribute (e.g., `alt="St. Jude Health Network - Strategic Partner"`).
- **Loading & Fallback Behavior:** If API returns empty array, component unmounts instantly.

---

#### Section 12: Evidence-Based Case Study Spotlight (Conditional)
- **Section Code:** `HP-SEC-12`
- **Purpose:** Present a single, high-impact case study demonstrating measurable operational improvement (e.g., "Eliminating 85% of Emergency Shift Gaps in Regional Victoria").
- **Content Inputs:**
  - Eyebrow: "CASE STUDY SPOTLIGHT".
  - Facility Profile: "Regional Public Hospital Network (Victoria)".
  - The Challenge: Severe nursing vacancy rates leading to bed closures.
  - The Intervention: Turnkey deployment of a 24-nurse specialized roster team within 14 days.
  - The Outcome: 100% shift coverage, zero bed closures, full compliance audit pass.
  - Action Button: "Read Full Case Study" -> `/case-studies/:slug`.
- **CMS Fields:**
  - Linked to `case_studies` table where `is_featured = 1` and `is_published = 1`.
- **Rendering Conditions (CRITICAL):**
  - Renders **ONLY** if an approved case study is marked `is_featured = 1` and `is_published = 1`.
  - If no case study meets this criterion, the section collapses cleanly without leaving blank space.
- **Image Requirements:** High-resolution professional photograph of hospital exterior or operational workspace (licensed/verified, WebP, max 150KB).
- **Responsive Ordering:** 50/50 split on desktop; image top, text bottom on mobile.
- **CTA Behavior:** Navigates to `/case-studies/:slug`.
- **Link Destinations:** `/case-studies/:slug`, `/case-studies`.
- **Data Dependencies:** `GET /api/v1/public/homepage` -> `featured_case_study`.
- **Disabled / Unpublished Behavior:** Total section collapse when unpublished.
- **Accessibility:** Metric numbers paired with descriptive text for screen readers; high-contrast card styling.
- **Loading & Fallback Behavior:** Pre-rendered HTML if active; omitted if inactive.

---

#### Section 13: Thought Leadership & Resource Highlights
- **Section Code:** `HP-SEC-13`
- **Purpose:** Demonstrate intellectual leadership and regulatory foresight by highlighting the 3 latest editorial articles or whitepapers.
- **Content Inputs:**
  - Section Header: Eyebrow: "THOUGHT LEADERSHIP", Headline: "Latest Insights & Regulatory Briefings".
  - 3 Article Cards:
    - Thumbnail image with category badge (`#EEAFD7`, `#54CB96`, `#63A5E6`).
    - Publication date and reading time estimate.
    - Article title (Serif font, hover purple).
    - Concise excerpt (max 120 chars).
    - Author byline with professional title.
  - Section Action: "Explore All Articles & Whitepapers" -> `/insights`.
- **CMS Fields:**
  - Linked to `articles` table: top 3 published articles ordered by `published_at` DESC.
- **Rendering Conditions:** Renders if >= 1 published article exists. If zero articles exist, section collapses.
- **Image Requirements:** 16:9 aspect ratio WebP images (600x338px, max 80KB each).
- **Responsive Ordering:** 3-column row on desktop; 1-column stack on mobile.
- **CTA Behavior:** Clicking card navigates to `/insights/:slug`; button navigates to `/insights`.
- **Link Destinations:** `/insights/:slug`, `/insights`.
- **Data Dependencies:** `GET /api/v1/public/homepage` -> `latest_articles` array.
- **Disabled / Unpublished Behavior:** If no articles are published, section is hidden.
- **Accessibility:** Cards are semantic `<article>` tags; interactive links have `aria-label="Read article: [Title]"`.
- **Loading & Fallback Behavior:** Shimmer skeleton for 3 cards during initial client-side data fetch.

---

#### Section 14: Executive Partnership Conversion Banner
- **Section Code:** `HP-SEC-14`
- **Purpose:** Serve as the high-impact final conversion section on the homepage, driving qualified healthcare procurement leads and strategic partnership inquiries.
- **Content Inputs:**
  - Background: Rich deep navy (`#252B5E`) or deep royal purple (`#665CE9`) with subtle organic geometric waveforms.
  - Headline: "Ready to Strengthen Your Healthcare Operations?" (Rendered in white serif display font).
  - Narrative: "Whether you require immediate clinical workforce deployment, comprehensive facility co-sourcing, or strategic governance advisory, our leadership team is ready to scope your solution."
  - Action Cluster:
    - Primary Button: "Request a Formal Proposal" (White button with purple text `#665CE9`, high-contrast).
    - Secondary Button: "Explore Strategic Partnership" (Ghost button with white border and white text).
    - Direct Contact Hotline: "Direct Procurement Inquiries: 1300 VOAK HC".
  - Compliance Assurance Pill: "All inquiries handled under strict institutional confidentiality."
- **CMS Fields:**
  - `banner_headline` (string, max 100 chars).
  - `banner_subhead` (string, max 250 chars).
  - `banner_primary_label` (string, default "Request a Formal Proposal").
  - `banner_primary_link` (string, default `/request-proposal`).
  - `banner_secondary_label` (string, default "Explore Strategic Partnership").
  - `banner_secondary_link` (string, default `/partner-with-us`).
- **Rendering Conditions:** Always rendered above the global footer.
- **Image Requirements:** Subtle vector background SVG pattern; zero heavy raster backgrounds.
- **Responsive Ordering:** Centered layout on desktop; stacked buttons on mobile (`w-full`).
- **CTA Behavior:** Primary routes to `/request-proposal`; Secondary routes to `/partner-with-us`.
- **Link Destinations:** `/request-proposal`, `/partner-with-us`.
- **Data Dependencies:** Static CMS content.
- **Disabled / Unpublished Behavior:** Always active.
- **Accessibility:** Contrast between white text and `#252B5E` background exceeds 14:1 (passes AAA); button states have prominent focus rings (`ring-2 ring-white`).
- **Loading & Fallback Behavior:** Pre-rendered HTML.

---

#### Section 15: Global Corporate Footer & Legal Disclaimers
- **Section Code:** `HP-SEC-15`
- **Purpose:** Provide complete corporate navigation, regulatory disclosures, copyright notices, and mandatory non-clinical operational boundary disclaimers.
- **Content Inputs:**
  - Column 1: Corporate Identity & Overview (Voak Health Care SVG logo, company summary, business registration reference, corporate headquarters location).
  - Column 2: Operational Solutions (Direct links to 6 service pillars + `/services`).
  - Column 3: Industry Sectors (Direct links to 6 industry sectors + `/industries`).
  - Column 4: Governance & Company (About Us, Why Choose Voak, How We Work, Quality & Compliance, Careers, Insights).
  - Column 5: Direct Procurement & Inquiries (Official phone, official email, operating hours, "Request Proposal" link).
  - Sub-Footer Bar:
    - Mandatory B2B Disclaimer: "{{legal_entity_name}} is a business-to-business healthcare operations and workforce management provider. {{legal_entity_name}} does not provide direct clinical medical care to individual patients, operate emergency medical services, or provide patient health consultations. In the event of a medical emergency, contact your local emergency services immediately (e.g. 000 in Australia)."
    - Copyright Notice: "© [Current Year] {{legal_entity_name}}. All rights reserved."
    - Legal Links: Privacy Policy (`/privacy-policy`), Terms of Service (`/terms`), Clinical Governance Charter (`/quality-compliance`).
- **CMS Fields:**
  - Corporate business registration number (`registration_number`), headquarters address (`registered_address`), telephone, email, and social profile URLs managed via Admin Module 18 (System Settings).
- **Rendering Conditions:** Always rendered on all public pages.
- **Image Requirements:** Monochrome white SVG logo.
- **Responsive Ordering:**
  - Desktop: 5-column grid + 2-row sub-footer.
  - Tablet: 3-column top grid + 2-column bottom grid.
  - Mobile: Accordion-collapsible navigation columns or clean vertical stacking with clear category headings.
- **CTA Behavior:** Direct navigation to all site pages.
- **Link Destinations:** Valid internal and external routes.
- **Data Dependencies:** `GET /api/v1/public/settings/footer` (cached 1 hour).
- **Disabled / Unpublished Behavior:** Social icons hide if URLs are empty.
- **Accessibility:** Semantic `<footer>` landmark; clear contrast on dark background; sub-footer disclaimer readable at 14px font size.
- **Loading & Fallback Behavior:** Pre-rendered server HTML.

---

### Homepage Section State & Dependency Matrix

| Code | Section Name | Primary Visual Role | CMS Controlled | Conditional Rendering | Fallback Strategy |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **HP-SEC-01** | Global Header & Nav | Navigation & Orientation | Yes (Announcement) | No (Always) | Server-rendered HTML |
| **HP-SEC-02** | Asymmetrical Hero | Value Prop & Primary CTA | Yes (Copy & Media) | No (Always) | Server-rendered HTML |
| **HP-SEC-03** | Value Proposition | Core Institutional Thesis | Yes (Copy) | No (Always) | Baseline Copy |
| **HP-SEC-04** | Conversion CTA Cluster | Rapid Inbound Routing | Yes (Phone/Links) | No (Always) | Standard Routes |
| **HP-SEC-05** | Services Bento Grid | Core Offering Discovery | Yes (Services CRUD) | Yes (Min 3 services) | Dense CSS Grid Reflow |
| **HP-SEC-06** | Industry Directory | Sector Relevance Proof | Yes (Industries CRUD) | No (Always) | Standard Sectors |
| **HP-SEC-07** | Operational Capabilities| Procurement Rigor Proof | Yes (Copy) | No (Always) | Baseline Quadrants |
| **HP-SEC-08** | Differentiation Matrix | Comparative Advantage | Yes (Matrix CRUD) | No (Always) | Baseline 3-Row Matrix |
| **HP-SEC-09** | 4-Stage Roadmap | Engagement Transparency | Yes (Copy) | No (Always) | Baseline 4 Stages |
| **HP-SEC-10** | Clinical Governance | Compliance Reassurance | Yes (Checklist) | No (Always) | Baseline Standards |
| **HP-SEC-11** | Partner & Client Proof | Social Proof | Yes (Partners CRUD) | **YES (Strict)** | **Total DOM Collapse** |
| **HP-SEC-12** | Case Study Spotlight | Evidence-Based ROI | Yes (Case Study CRUD)| **YES (Strict)** | **Total DOM Collapse** |
| **HP-SEC-13** | Insights Highlights | Thought Leadership | Yes (Articles CRUD) | Yes (Min 1 article) | Clean Section Collapse|
| **HP-SEC-14** | Conversion Banner | Final Procurement Push | Yes (Copy) | No (Always) | Standard Action Paths |
| **HP-SEC-15** | Global Footer | Legal & Sub-Nav | Yes (Settings) | No (Always) | Server-rendered HTML |
