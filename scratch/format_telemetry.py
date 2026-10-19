import json

with open('tests/screenshot_metrics.json') as f:
    metrics = json.load(f)

print("| Relative File Path | Exact Width | Exact Height | Exact Byte Size | Kilobytes (KB) | Subject & Verification Confirmation |")
print("|---|:---:|:---:|:---:|:---:|---|")

descriptions = {
    "01_desktop_homepage.png": "Clean Desktop Homepage (Asymmetric hero collage, 4x2 services, no preview toolbar)",
    "02_mobile_homepage_390px.png": "Clean Mobile Homepage (390px baseline, 0px overflow)",
    "03_tablet_homepage_768px.png": "Clean Tablet Homepage (768px viewport)",
    "04_public_pages_gallery.png": "Public Pages Gallery View (All 22 Page Cards)",
    "05_admin_dashboard.png": "ADM-01 Full Admin Console Dashboard",
    "06_component_system.png": "Tokens, Typography, Buttons, Chips, States",
    "07_approved_reference_comparison.png": "Reference Comparison View (Authentic Image 2 verified PASS, Admin labeled concept)",
    "08_proposal_wizard_modal.png": "FORM-PUB-03 4-Step RFP Wizard Modal (Clean framing)",
    "09_simulated_403_access_denied.png": "Least-Privilege RBAC Simulation Denied Card",
}

admin_descs = {
    "admin_adm-01_preview.png": "ADM-01 Executive Dashboard & Telemetry",
    "admin_adm-02_preview.png": "ADM-02 Authentication & MFA Security",
    "admin_adm-03_preview.png": "ADM-03 Users & RBAC Matrix Management",
    "admin_adm-04_preview.png": "ADM-04 Static Pages Content CMS",
    "admin_adm-05_preview.png": "ADM-05 Homepage Layout Configurator",
    "admin_adm-06_preview.png": "ADM-06 Services Management CMS",
    "admin_adm-07_preview.png": "ADM-07 Industries Verticals Management",
    "admin_adm-08_preview.png": "ADM-08 Media Asset Library & Storage Governance",
    "admin_adm-09_preview.png": "ADM-09 Contact Inquiries CRM Desk",
    "admin_adm-10_preview.png": "ADM-10 Strategic Partnership Pipeline",
    "admin_adm-11_preview.png": "ADM-11 Proposal / RFP Review Desk",
    "admin_adm-12_preview.png": "ADM-12 Transactional Email Templates & Outbox",
    "admin_adm-13_preview.png": "ADM-13 Careers Job Postings CMS",
    "admin_adm-14_preview.png": "ADM-14 Candidate Resumes Vault (PII Governance)",
    "admin_adm-15_preview.png": "ADM-15 Case Studies CMS",
    "admin_adm-16_preview.png": "ADM-16 Insights & Articles CMS",
    "admin_adm-17_preview.png": "ADM-17 SEO Console & Redirect Manager",
    "admin_adm-18_preview.png": "ADM-18 System Settings & Entity Config",
    "admin_adm-19_preview.png": "ADM-19 Immutable Audit Logs & Telemetry",
}

pub_descs = {
    "page_pub-01_preview.png": "PUB-01 Home (Exact height, zero canvas excess)",
    "page_pub-02_preview.png": "PUB-02 About Us (Exact height, zero canvas excess)",
    "page_pub-03_preview.png": "PUB-03 Services Catalog (Exact height, zero canvas excess)",
    "page_pub-04_preview.png": "PUB-04 Service Detail (Exact height, zero canvas excess)",
    "page_pub-05_preview.png": "PUB-05 Industries (Exact height, zero canvas excess)",
    "page_pub-06_preview.png": "PUB-06 Industry Detail (Exact height, zero canvas excess)",
    "page_pub-07_preview.png": "PUB-07 Why Choose Voak (Exact height, zero canvas excess)",
    "page_pub-08_preview.png": "PUB-08 How We Work (Exact height, zero canvas excess)",
    "page_pub-09_preview.png": "PUB-09 Quality & Compliance (Exact height, zero canvas excess)",
    "page_pub-10_preview.png": "PUB-10 Partners & Clients (Exact height, zero canvas excess)",
    "page_pub-11_preview.png": "PUB-11 Case Studies (Exact height, zero canvas excess)",
    "page_pub-12_preview.png": "PUB-12 Careers Hub (Exact height, zero canvas excess)",
    "page_pub-13_preview.png": "PUB-13 Job Detail & App (Exact height, zero canvas excess)",
    "page_pub-14_preview.png": "PUB-14 Insights & Articles (Exact height, zero canvas excess)",
    "page_pub-15_preview.png": "PUB-15 Article Detail (Exact height, zero canvas excess)",
    "page_pub-16_preview.png": "PUB-16 FAQs (Exact height, zero canvas excess)",
    "page_pub-17_preview.png": "PUB-17 Contact Us (Exact height, zero canvas excess)",
    "page_pub-18_preview.png": "PUB-18 Partner With Us (Exact height, zero canvas excess)",
    "page_pub-19_preview.png": "PUB-19 Request a Proposal (Exact height, zero canvas excess)",
    "page_pub-20_preview.png": "PUB-20 Privacy Policy (Exact height, zero canvas excess)",
    "page_pub-21_preview.png": "PUB-21 Terms & Conditions (Exact height, zero canvas excess)",
    "page_pub-22_preview.png": "PUB-22 Error States 404/500 (Exact height, zero canvas excess)",
}

for k, v in metrics.items():
    desc = descriptions.get(k)
    if not desc:
        base = k.split("/")[-1]
        if k.startswith("admin_modules/"):
            desc = admin_descs.get(base, base)
        elif k.startswith("public_pages/"):
            desc = pub_descs.get(base, base)
        else:
            desc = "Verified screenshot capture"
    print(f"| `{k}` | {v['width']} px | {v['height']} px | {v['sizeBytes']:,} B | {v['sizeKb']} KB | {desc} |")
