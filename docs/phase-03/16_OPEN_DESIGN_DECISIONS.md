# Open Design Decisions & Business Governance Register
## Voak Health Care — Corporate Digital Brand Guidelines

**Document Reference:** `docs/phase-03/16_OPEN_DESIGN_DECISIONS.md`  
**Phase:** Phase 3 of 31 — Professional UI/UX Design (Reconciled Baseline)  
**Governance Standard:** Transparent Risk Management & Factual Integrity Governance

---

### 1. Register of Open Design Decisions

The following items are documented for client and business stakeholder alignment prior to the frontend and backend implementation phases:

```
+---------------------------------------------------------------------------------------------------+
| DECISION ID | TOPIC                      | CURRENT RECONCILED STATUS      | REQUIRED CLIENT ACTION|
|-------------|----------------------------|--------------------------------|-----------------------|
| DEC-01      | Production Vector Logo     | Concept SVG Mark Implemented   | Deliver Brand Vector  |
| DEC-02      | Verified Business Claims   | Neutral Non-Statistical Copy   | Supply Verified Stats |
| DEC-03      | Executive Photography      | High-Res Demo Synthesized Img  | Approve or Commission |
| DEC-04      | Client Logos & Testimonials| Strict Anonymized Placeholders | Supply Signed Releases|
| DEC-05      | Multi-Lingual Scope (i18n) | English Baseline with Switcher | Scope Decision for MVP|
| DEC-06      | Operating Jurisdiction     | Configurable Entity Parameter  | Confirm Entity & State|
| DEC-07      | Terms & Privacy Approvals  | Draft Legal Notices (Pending)  | Corporate Counsel Sign|
+---------------------------------------------------------------------------------------------------+
```

---

### 2. Detailed Decision Specifications

#### DEC-01: Final Production Vector Logo Master
- **Context:** The approved reference image contained a conceptual logo. Phase 3 engineered a custom SVG brand mark (triple-caring figure arc in `#665CE9`, `#EEAFD7`, and `#FBDC62`) that pairs cleanly with the corporate typography.
- **Current Position:** The current SVG logo is scalable, accessible, and responsive.
- **Client Decision Required:** If Voak Health Care possesses a registered trademark vector `.ai` or `.svg` asset, provide it prior to Phase 18 (React Foundation) for drop-in replacement.

#### DEC-02: Verified Business Claims & Commercial Commitments
- **Context:** Independent review identified that claims such as *"100% Credentialing Guarantee"*, *"Zero Roster Downtime SLA"*, *"48 active business contracts"*, and *"$2.4M commercial pipeline"* were unsupported commercial promises rather than verified facts.
- **Current Position:** All unsupported claims have been strictly excised from public screens and replaced with neutral, descriptive operational copy. Admin prototype screens are prominently watermarked `[DEMO DATA ONLY — SIMULATED RECORDS]`.
- **Client Decision Required:** Provide verified historical operational data and legally approved service level metrics prior to Phase 20 homepage content population.

#### DEC-03: Clinician & Executive Photography
- **Context:** Phase 3 synthesized two high-resolution healthcare portraits (`hero_doctor.jpg` and `operations_manager.jpg`) depicting authentic healthcare practitioners.
- **Current Position:** The photography establishes the exact lighting, framing, and color balance required by the approved Image 2 aesthetic.
- **Client Decision Required:** Confirm whether Voak Health Care will retain these demonstration images or commission custom commercial photography of actual corporate leadership.

#### DEC-04: Client Logos & Institutional Endorsements
- **Context:** To avoid false claims of association with specific hospital networks or care facilities, Phase 3 utilized category-based partnership representations (e.g. "Public & Private Hospital Networks", "Aged Care Providers").
- **Current Position:** Zero unverified hospital logos, third-party trademarks, or unconfirmed quotes appear on public screens.
- **Client Decision Required:** Supply written brand release agreements and approved high-resolution logos for any specific healthcare partner to be featured on PUB-10 or PUB-11.

#### DEC-05: Multi-Lingual Interface Scope
- **Context:** The approved reference displayed an `EN ▾` language switcher.
- **Current Position:** The language switcher UI trigger is preserved in the navigation header, but all initial 22 public pages are authored in English.
- **Client Decision Required:** Confirm whether multi-lingual localization (e.g. i18n for additional languages) is in scope for MVP launch or scheduled for a post-launch phase.

#### DEC-06: Legal Operating Jurisdiction & Business Details
- **Context:** Previous documents assumed Sydney headquarters, New South Wales governing law, Australian-only phone formatting, and registered ABN details. The definitive legal business identity and operating jurisdiction remain subject to client verification.
- **Current Position:** All interfaces and forms have been re-architected to be geographically configurable. International phone validation (E.164 standard) is supported globally. Corporate details are parameterized via CMS.
- **Client Decision Required:** Confirm the definitive corporate entity registration name, headquarters address, tax registration number, and primary governing law jurisdiction.

#### DEC-07: Public Legal Notices & Terms Approval
- **Context:** Public legal documents (Privacy Policy `PUB-20` and Terms & Conditions `PUB-21`) cannot be finalized as binding corporate contracts without legal counsel review.
- **Current Position:** The pages are designed with clean, accessible, sticky table-of-contents layouts and labeled as draft notices pending final legal review.
- **Client Decision Required:** Corporate legal counsel must review and provide authoritative statutory policy text prior to Phase 24 publishing.
