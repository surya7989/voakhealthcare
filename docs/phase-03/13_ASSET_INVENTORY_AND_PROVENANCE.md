# Visual Asset Inventory & Provenance Record
## Voak Health Care — Corporate Digital Brand Guidelines

**Document Reference:** `docs/phase-03/13_ASSET_INVENTORY_AND_PROVENANCE.md`  
**Phase:** Phase 3 of 31 — Professional UI/UX Design (Reconciled Baseline)  
**Provenance Classification Standard:** Strict Categorization of Prototype Assets, Benchmark References & Commercial Clearance Boundaries  
**Storage Locations:** `design/phase-03/assets/`, `design/phase-03/references/`, and Brain Artifacts Directory

---

### 1. Visual Asset Ledger & Provenance Taxonomy

All visual assets utilized in Voak Health Care's Phase 3 design system and prototype are categorized below with explicit legal provenance, generation context, and usage status. Generating an image via an AI tool does not constitute proprietary copyright ownership or commercial legal clearance.

| Asset Identifier | File Location | Dimensions | File Size | Format | Provenance & Legal Classification | Usage Authority / Scope | Primary UI Role |
| :--- | :--- | :---: | :---: | :---: | :--- | :--- | :--- |
| **Hero Doctor Portrait** | `design/phase-03/assets/images/hero_doctor.jpg` | 1024 × 1024 | 652 KB | JPG | **AI-Generated Demonstration Asset** (Synthesized via Antigravity image generation specifically for prototype demonstration). | Prototype UI/UX inspection only. Final commercial use requires client approval or commissioned commercial photography. | Primary hero collage healthcare professional |
| **Operations Manager** | `design/phase-03/assets/images/operations_manager.jpg` | 1024 × 1024 | 669 KB | JPG | **AI-Generated Demonstration Asset** (Synthesized via Antigravity image generation for prototype layout verification). | Prototype UI/UX inspection only. Final commercial deployment requires client clearance. | "Why Choose Voak" section feature portrait |
| **Voak Brand Logo Mark** | Inline SVG in `index.html` & `styles.css` | Vector (Scalable) | Scalable | SVG | **Placeholder Concept Mark** (Custom-coded geometric SVG representing stylized figures in `#665CE9`, `#EEAFD7`, `#FBDC62`). | Digital placeholder for UI development. Subject to replacement by client's official vector brand pack. | Header navigation, footer & favicon branding |
| **Approved Reference 1 (Image 2)** | `design/phase-03/references/approved_homepage_reference_image_2.png` | 1024 × 768 | 1.1 MB | PNG | **Internal Design Benchmark Reference** (User-approved screenshot `Screenshot_2026-10-16_073113.png`). Not owned by Voak. | Internal benchmark authority only. Strictly excluded from production build and public release. | Visual design authority & QA baseline |
| **Approved Admin Reference** | `design/phase-03/references/approved_admin_reference_portal.png` | 1920 × 1080 | 1.4 MB | PNG | **Internal Admin Benchmark Reference** (User-approved screenshot `Screenshot_2026-10-16_073635.png`). Not owned by Voak. | Internal benchmark authority only. Strictly excluded from production build and public release. | Admin portal layout & UX baseline |
| **Vector UI Icons** | Inline SVGs in `index.html` & `app.js` | 16px to 48px | Scalable | SVG | **Open-Source Icon Set** (Feather / Lucide stroke geometry). | MIT License (Permitted for commercial and open use). | Buttons, navigation, and state indicators |

---

### 2. Legal Clearance & Usage Boundaries

1. **No Unwarranted Proprietary Claims:**  
   AI-generated visual assets are recognized as synthetic demonstration imagery created to facilitate realistic interface design. Voak Health Care does not claim exclusive registered trademark or proprietary copyright over synthetic faces or generic background compositions.
2. **Benchmark Reference Integrity:**  
   The user-approved reference files (`approved_homepage_reference_image_2.png` and `approved_admin_reference_portal.png`) are maintained in immutable form under `references/`. They serve as the visual quality benchmark and are never modified, re-compressed, or published to public web directories.
3. **Client Handoff & Production Replacement Path:**  
   Prior to Phase 30 public deployment, the client may provide official commissioned photography and vector trademark assets (`.ai`, `.eps`, `.svg`). The architecture allows drop-in replacement by updating `assets/images/` without altering CSS layout grids or container aspect ratios.

---

### 3. Photography Art Direction Guidelines for Production Assets

When commissioned photography or licensed stock is procured during subsequent phases, it must adhere to the following artistic standards:
- **Subjects:** Authentic healthcare practitioners (registered nurses, clinical directors, surgical staff, allied health specialists) in professional attire.
- **Tone:** Warm, optimistic, respectful, and dignified. Soft studio or natural diffused clinical lighting.
- **Composition:** Asymmetric framing accommodating rounded collage backdrops and pastel cards without awkward head cropping.
- **Factual Honesty:** Never present stock models as specific named Voak executives without verified releases.
