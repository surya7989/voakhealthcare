/* ==========================================================================
   VOAK HEALTH CARE — PHASE 3 MASTER UI/UX PROTOTYPE (app.js)
   Reconciled & Corrected Baseline:
   - 5 Canonical Admin Roles (Super Admin, Website Admin, Content Editor, BD Manager, HR Manager)
   - 12 Canonical System Forms (FORM-PUB-01..04, FORM-ADM-01..08)
   - Canonical Proposal Route: /request-proposal
   - High-Fidelity Inspectable Screens for ALL 22 Public Pages
   - High-Fidelity Inspectable Screens for ALL 19 Admin Modules
   - Interactive Least-Privilege RBAC Role Simulation & 403 Enforcement
   - DEMO DATA ONLY Watermarking (No unverified business claims)
   - Geographically Configurable Corporate Identity
   ========================================================================== */

// Global State
let currentSimulatedRole = 'ROLE_SUPER_ADMIN';
let currentAdminModule = 'ADM-01';
let currentWizardStep = 1;

const wizardData = {
  orgName: '',
  facilityType: 'HOSPITAL_ACUTE',
  country: 'Configurable (Default AU/US/GB/NZ)',
  regionState: 'Operating Jurisdiction / Region [PENDING CONFIRMATION]',
  leadName: '',
  leadTitle: '',
  leadEmail: '',
  leadPhone: '',
  services: ['Clinical Workforce'],
  scaleMetric: '',
  startDate: '',
  procurementStage: 'ACTIVE_TENDER',
  budgetBand: 'TIER_2_100K_TO_500K',
  projectBrief: '',
  attachmentName: ''
};

// RBAC Permission Map (Phase 1 Canonical Matrix)
const RBAC_MODULE_PERMISSIONS = {
  'ROLE_SUPER_ADMIN': [
    'ADM-01', 'ADM-02', 'ADM-03', 'ADM-04', 'ADM-05', 'ADM-06', 'ADM-07', 'ADM-08',
    'ADM-09', 'ADM-10', 'ADM-11', 'ADM-12', 'ADM-13', 'ADM-14', 'ADM-15', 'ADM-16',
    'ADM-17', 'ADM-18', 'ADM-19'
  ],
  'ROLE_WEBSITE_ADMIN': [
    'ADM-01', 'ADM-02', 'ADM-04', 'ADM-05', 'ADM-06', 'ADM-07', 'ADM-08',
    'ADM-09', 'ADM-12', 'ADM-13', 'ADM-15', 'ADM-16', 'ADM-17'
  ],
  'ROLE_CONTENT_EDITOR': [
    'ADM-01', 'ADM-02', 'ADM-04', 'ADM-05', 'ADM-06', 'ADM-07', 'ADM-08',
    'ADM-15', 'ADM-16'
  ],
  'ROLE_BD_MANAGER': [
    'ADM-01', 'ADM-02', 'ADM-09', 'ADM-10', 'ADM-11'
  ],
  'ROLE_HR_MANAGER': [
    'ADM-01', 'ADM-02', 'ADM-13', 'ADM-14'
  ]
};

const ROLE_PROFILES = {
  'ROLE_SUPER_ADMIN': {
    name: '[SIMULATED IDENTITY — DEMO ONLY] Executive Lead',
    roleLabel: 'Super Admin',
    email: 'superadmin.demo@example.invalid',
    avatar: 'SA',
    scopeDesc: 'Full unconstrained system authority across all 19 administrative modules, user provisioning, and audit telemetry.'
  },
  'ROLE_WEBSITE_ADMIN': {
    name: '[SIMULATED IDENTITY — DEMO ONLY] Systems Lead',
    roleLabel: 'Website Administrator',
    email: 'siteadmin.demo@example.invalid',
    avatar: 'WA',
    scopeDesc: 'CMS operations, SEO, media assets, general contact triage, and site maintenance. Blocked from commercial tenders and candidate resumes.'
  },
  'ROLE_CONTENT_EDITOR': {
    name: '[SIMULATED IDENTITY — DEMO ONLY] Editorial Lead',
    roleLabel: 'Content Editor',
    email: 'editor.demo@example.invalid',
    avatar: 'CE',
    scopeDesc: 'Editorial copy, blog articles, static pages, and media tagging. Zero access to CRM pipelines, user management, or system settings.'
  },
  'ROLE_BD_MANAGER': {
    name: '[SIMULATED IDENTITY — DEMO ONLY] Commercial Lead',
    roleLabel: 'Business Development Manager',
    email: 'partnerships.demo@example.invalid',
    avatar: 'BD',
    scopeDesc: 'Commercial inquiries, partnership pipeline (ADM-10), and proposal tender desk (ADM-11). Zero access to HR candidate resumes or CMS publishing.'
  },
  'ROLE_HR_MANAGER': {
    name: '[SIMULATED IDENTITY — DEMO ONLY] Recruitment Lead',
    roleLabel: 'HR Manager',
    email: 'recruitment.demo@example.invalid',
    avatar: 'HR',
    scopeDesc: 'Clinical recruitment postings (ADM-13) and candidate resume triage (ADM-14). Zero access to commercial proposals, CRM leads, or CMS settings.'
  }
};

document.addEventListener('DOMContentLoaded', () => {
  initTabs();
  initDeviceSwitcher();
  renderPublicPagesGallery();
  populateInspectorDropdown();
  renderColorTokens();
  initProposalWizard();
  showAdminModule('ADM-01');

  // Check initial hash
  const hash = window.location.hash.replace('#', '');
  if (hash === 'mobile') switchTab('homepage-mobile');
  else if (hash === 'pages') switchTab('public-pages');
  else if (hash === 'admin') switchTab('admin-portal');
  else if (hash === 'components') switchTab('components-tokens');
  else if (hash === 'refs') switchTab('reference-comparison');
});

/* --------------------------------------------------------------------------
   1. MASTER TAB SWITCHER
   -------------------------------------------------------------------------- */
function initTabs() {
  const tabs = document.querySelectorAll('.preview-tab-btn');
  const panels = document.querySelectorAll('.view-panel');
  const deviceSwitcher = document.getElementById('device-switcher');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const target = tab.dataset.tab;

      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      panels.forEach(p => {
        p.classList.remove('active');
        if (p.id === `view-${target}`) {
          p.classList.add('active');
        }
      });

      if (target === 'homepage-mobile') {
        if (deviceSwitcher) deviceSwitcher.style.display = 'flex';
      } else {
        if (deviceSwitcher) deviceSwitcher.style.display = 'none';
      }

      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });
}

function switchTab(tabId) {
  const tabs = document.querySelectorAll('.preview-tab-btn');
  const panels = document.querySelectorAll('.view-panel');
  const deviceSwitcher = document.getElementById('device-switcher-bar');

  tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === tabId));
  panels.forEach(p => p.classList.toggle('active', p.id === `view-${tabId}`));

  if (deviceSwitcher) {
    deviceSwitcher.style.display = (tabId === 'homepage-mobile') ? 'flex' : 'none';
  }
  window.scrollTo(0, 0);
}

/* --------------------------------------------------------------------------
   2. DEVICE SIZING CONTROLS
   -------------------------------------------------------------------------- */
function initDeviceSwitcher() {
  const btns = document.querySelectorAll('.device-btn');
  const frame = document.getElementById('mobile-frame-target');

  btns.forEach(b => {
    b.addEventListener('click', () => {
      btns.forEach(btn => btn.classList.remove('active'));
      b.classList.add('active');
      const size = b.dataset.size;
      if (frame) frame.style.maxWidth = `${size}px`;
    });
  });
}

/* --------------------------------------------------------------------------
   3. ALL 22 PUBLIC PAGES CATALOG (Canonical Routes & Requirements)
   -------------------------------------------------------------------------- */
const PUBLIC_PAGES_DATA = [
  {
    id: "PUB-01",
    canonicalId: "PAGE-PUB-01",
    reqId: "FR-PUB-002 (with FR-PUB-001, FR-PUB-003, FR-PUB-004)",
    title: "Home",
    route: "/",
    heroStyle: "Lavender Canvas, Editorial Serif Headlines, Healthcare Professional Collage",
    accentColor: "var(--voak-pink)",
    sections: "Fixed Header Navigation, Hero Collage, 3 Primary Feature Cards, Bento Service Grid, Why Voak Matrix, Industries We Serve, Operational Delivery Timeline, Case Studies, Governance Framework, Insights, RFP CTA, Accessible Footer",
    cta: "Explore Healthcare Services / Partner With Us",
    badge: "Benchmark"
  },
  {
    id: "PUB-02",
    canonicalId: "PAGE-PUB-02",
    reqId: "FR-PUB-009",
    title: "About Us",
    route: "/about",
    heroStyle: "Warm Lavender Surface, Corporate Mission, Leadership Values",
    accentColor: "var(--voak-sky)",
    sections: "Institutional Mission, Executive Governance, Clinical Quality Charter, Corporate Values Grid, Leadership Overview, Multi-Regional Operational Footprint",
    cta: "Review Quality Standards / Contact Leadership",
    badge: "Core"
  },
  {
    id: "PUB-03",
    canonicalId: "PAGE-PUB-03",
    reqId: "FR-PUB-005",
    title: "Services Catalog",
    route: "/services",
    heroStyle: "Bento Healthcare Services Grid with Interactive Sector Filtering",
    accentColor: "var(--voak-mint)",
    sections: "Interactive Category Filters, 6 Pillar Service Cards (Clinical Workforce, Operations BPO, Advisory, Governance, Logistics, Infrastructure), Deliverable Lists, Procurement Action Cards",
    cta: "Select Specialized Service / Request Proposal",
    badge: "Catalog"
  },
  {
    id: "PUB-04",
    canonicalId: "PAGE-PUB-04",
    reqId: "FR-PUB-006",
    title: "Service Detail",
    route: "/services/clinical-workforce",
    heroStyle: "Deep Editorial Header, Specific Healthcare Discipline Specifications",
    accentColor: "var(--voak-yellow)",
    sections: "Detailed Service Scope, Clinical Shift Tiers, Credentialing Protocols, Verified SLA Framework, Client Testimonial Case Box, Direct Proposal Initiation",
    cta: "Request Proposal for Service / Schedule Scoping Call",
    badge: "Detail"
  },
  {
    id: "PUB-05",
    canonicalId: "PAGE-PUB-05",
    reqId: "FR-PUB-007",
    title: "Industries",
    route: "/industries",
    heroStyle: "Healthcare Vertical Sector Showcase & Alignment Matrix",
    accentColor: "var(--voak-purple-cta)",
    sections: "Sector Bento Grid (Public Hospitals, Private Health Networks, Aged Care, Community & Specialized Care [PENDING APPROVAL], Day Surgery Clinics), Tailored Value Propositions, Regulatory Alignment",
    cta: "Explore Specific Sector Solutions",
    badge: "Sectors"
  },
  {
    id: "PUB-06",
    canonicalId: "PAGE-PUB-06",
    reqId: "FR-PUB-008",
    title: "Industry Detail",
    route: "/industries/hospitals-health-services",
    heroStyle: "Acute Healthcare Facility Operational Focus",
    accentColor: "var(--voak-pink)",
    sections: "Acute Care Roster Optimization, Tier-1 Hospital Deployment Specs, Incident Reduction Protocols, Multi-Disciplinary Ward Coverage, Engagement Models",
    cta: "Initiate Hospital Workforce Discussion",
    badge: "Sector Detail"
  },
  {
    id: "PUB-07",
    canonicalId: "PAGE-PUB-07",
    reqId: "FR-PUB-010",
    title: "Why Choose Voak",
    route: "/why-choose-voak",
    heroStyle: "Comparative Procurement Matrix & Operational Governance",
    accentColor: "var(--voak-yellow)",
    sections: "4 Strategic Differentiators, Direct Comparison Table (Voak Dedicated Model vs. Ad-Hoc Agencies), Operational Cost Transparency, Clinical Quality Assurance",
    cta: "Compare Procurement Models / Request RFP",
    badge: "Value Prop"
  },
  {
    id: "PUB-08",
    canonicalId: "PAGE-PUB-08",
    reqId: "FR-PUB-011",
    title: "How We Work",
    route: "/how-we-work",
    heroStyle: "Structured 4-Stage Operational Delivery Roadmap",
    accentColor: "var(--voak-mint)",
    sections: "Stage 1 Diagnostic Scoping, Stage 2 Custom Deployment Plan, Stage 3 Embedded Delivery, Stage 4 Ongoing Clinical Governance, Transition Risk Mitigation",
    cta: "Review Deployment Timeline",
    badge: "Methodology"
  },
  {
    id: "PUB-09",
    canonicalId: "PAGE-PUB-09",
    reqId: "FR-PUB-012",
    title: "Quality & Compliance",
    route: "/quality-compliance",
    heroStyle: "Clinical Governance Framework & Regulatory Safeguards",
    accentColor: "var(--voak-sky)",
    sections: "Credentialing Audit Pipeline, National & Regional Standards Alignment, Clinical Supervision Protocols, Non-Clinical Corporate Boundaries Disclaimer",
    cta: "Download Governance Framework / Review Protocols",
    badge: "Compliance"
  },
  {
    id: "PUB-10",
    canonicalId: "PAGE-PUB-10",
    reqId: "FR-PUB-004 (Verified Partner & Social Proof Directory, Phase 24)",
    title: "Partners & Clients",
    route: "/partners-clients",
    heroStyle: "Institutional Health Network Alliances & Collaboration Framework",
    accentColor: "var(--voak-purple-cta)",
    sections: "Partnership Category Framework (Health Systems, Academic Affiliations, Technology Alliances), Verified Partner Directory (Strictly Verified Assets Only), Safe Unverified Empty States, Partnership Inquiry Gateway",
    cta: "Explore Institutional Collaboration / Partner Inquiry",
    badge: "Alliances"
  },
  {
    id: "PUB-11",
    canonicalId: "PAGE-PUB-11",
    reqId: "FR-PUB-013",
    title: "Case Studies",
    route: "/case-studies",
    heroStyle: "Demonstrated Evidence of Healthcare Operational Impact",
    accentColor: "var(--voak-pink)",
    sections: "Filter by Healthcare Sector, Problem / Intervention / Outcome Framework, Verified Institutional Release Tagging, Quantitative Shift Fulfillment Metrics",
    cta: "Review Operational Evidence / Request Consultation",
    badge: "Evidence"
  },
  {
    id: "PUB-12",
    canonicalId: "PAGE-PUB-12",
    reqId: "FR-PUB-014",
    title: "Careers Hub",
    route: "/careers",
    heroStyle: "Professional Healthcare Career Growth & Clinical Placement",
    accentColor: "var(--voak-yellow)",
    sections: "Clinical Culture, Continuing Professional Development Support, Placement Flexibility, Searchable Vacancy Roster with Sector & Role Filters",
    cta: "Explore Open Clinical Vacancies",
    badge: "Recruitment"
  },
  {
    id: "PUB-13",
    canonicalId: "PAGE-PUB-13",
    reqId: "FR-PUB-015",
    title: "Job Detail & Application",
    route: "/careers/clinical-nurse-specialist",
    heroStyle: "Structured Healthcare Role Description & FORM-PUB-04 Engine",
    accentColor: "var(--voak-mint)",
    sections: "Clinical Responsibilities, Mandatory Qualifications, Shift Roster Overview, Embedded Application Form (FORM-PUB-04) with Secure Quarantined CV Upload",
    cta: "Submit Clinical Application (FORM-PUB-04)",
    badge: "Application"
  },
  {
    id: "PUB-14",
    canonicalId: "PAGE-PUB-14",
    reqId: "FR-PUB-016",
    title: "Insights & Articles",
    route: "/insights",
    heroStyle: "Healthcare Operational Thought Leadership & Policy Analysis",
    accentColor: "var(--voak-sky)",
    sections: "Editorial Articles Grid, Category Filters (Workforce, Policy, Compliance, Technology), Reading Time Counters, Proposed Newsletter Notification Box [PROPOSED]",
    cta: "Read Operational Analysis",
    badge: "Editorial"
  },
  {
    id: "PUB-15",
    canonicalId: "PAGE-PUB-15",
    reqId: "FR-PUB-017",
    title: "Article Detail",
    route: "/insights/optimizing-healthcare-workforce-models-2026",
    heroStyle: "Editorial Serif Headline, Sanitized Long-form Healthcare Commentary",
    accentColor: "var(--voak-purple-cta)",
    sections: "Executive Author Card, Reading Time, Rich Formatted Typography, Regulatory Analysis Callouts, Citation References, Social Sharing Controls",
    cta: "Share Analysis / Inquire About Implementation",
    badge: "Editorial"
  },
  {
    id: "PUB-16",
    canonicalId: "PAGE-PUB-16",
    reqId: "FR-PUB-018",
    title: "Frequently Asked Questions",
    route: "/faqs",
    heroStyle: "Structured Accessible FAQ Accordion Categories",
    accentColor: "var(--voak-pink)",
    sections: "Categorized Accordions (Workforce Deployment, Compliance & Credentialing, Commercial Contracting, Invoicing), Accessible Keyboard Expand/Collapse, Search",
    cta: "Search FAQs / Contact Operations Desk",
    badge: "Support"
  },
  {
    id: "PUB-17",
    canonicalId: "PAGE-PUB-17",
    reqId: "FR-PUB-019",
    title: "Contact Us",
    route: "/contact",
    heroStyle: "Inbound Corporate Triage Portal (FORM-PUB-01 Engine)",
    accentColor: "var(--voak-yellow)",
    sections: "Corporate Contact Form (FORM-PUB-01) with International Phone Support, Configurable Operating Head Office Details, Triage Response Overview",
    cta: "Submit Inbound Message (FORM-PUB-01)",
    badge: "Contact"
  },
  {
    id: "PUB-18",
    canonicalId: "PAGE-PUB-18",
    reqId: "FR-PUB-020",
    title: "Partner With Us",
    route: "/partner-with-us",
    heroStyle: "Dedicated Strategic Healthcare Alliances (FORM-PUB-02 Engine)",
    accentColor: "var(--voak-mint)",
    sections: "Enterprise Collaboration Tiers, Dedicated Strategic Partnership Intake Form (FORM-PUB-02), Business Development Scoping Criteria",
    cta: "Initiate Partnership Discussion (FORM-PUB-02)",
    badge: "Commercial"
  },
  {
    id: "PUB-19",
    canonicalId: "PAGE-PUB-19",
    reqId: "FR-PUB-021",
    title: "Request a Proposal",
    route: "/request-proposal",
    heroStyle: "Multi-Step Enterprise RFP Engine (FORM-PUB-03 Interaction Design)",
    accentColor: "var(--voak-sky)",
    sections: "4-Step Proposal Scoping Architecture, Facility & Executive Profile, Service Scope, Procurement Parameters, Secure Tender Document Upload (15MB Ceiling)",
    cta: "Launch Multi-Step RFP Wizard (FORM-PUB-03)",
    badge: "Core RFP Desk"
  },
  {
    id: "PUB-20",
    canonicalId: "PAGE-PUB-20",
    reqId: "FR-PUB-022 (Privacy Policy)",
    title: "Privacy Policy",
    route: "/privacy-policy",
    heroStyle: "Strict Corporate Healthcare Privacy & Data Protection Disclosures",
    accentColor: "var(--voak-purple-cta)",
    sections: "Privacy Principles, Commercial Entity Data Handling, Clinical PII Quarantine Safeguards, Cookie & Telemetry Notice, Configurable Legal Controller Variables",
    cta: "Contact Privacy Officer",
    badge: "Legal"
  },
  {
    id: "PUB-21",
    canonicalId: "PAGE-PUB-21",
    reqId: "FR-PUB-022 (Terms & Conditions)",
    title: "Terms & Conditions",
    route: "/terms",
    heroStyle: "Structured B2B Commercial Terms of Service",
    accentColor: "var(--voak-navy)",
    sections: "Master Service Terms, Website Conditions of Use, Intellectual Property Rights, Limitation of Liability, Configurable Governing Law and Jurisdiction Notice",
    cta: "Review Commercial Terms",
    badge: "Legal"
  },
  {
    id: "PUB-22",
    canonicalId: "PAGE-PUB-22",
    reqId: "FR-PUB-023",
    title: "System Error States (404 / 500)",
    route: "/error",
    heroStyle: "Human-Centred Accessible Error Recovery Canvas",
    accentColor: "var(--voak-pink)",
    sections: "Friendly Recovery Guidance, Diagnostic Correlation ID Display (Zero Stack Trace Leak), Quick Return Navigation Links, Operations Support Desk Contact",
    cta: "Return to Safe Navigation / Contact Desk",
    badge: "System State"
  }
];

function renderPublicPagesGallery() {
  const container = document.getElementById('public-pages-container');
  if (!container) return;

  container.innerHTML = PUBLIC_PAGES_DATA.map(p => `
    <div class="gallery-item-card" style="border-top: 4px solid ${p.accentColor}; display: flex; flex-direction: column;">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
        <div style="display: flex; align-items: center; gap: 0.4rem;">
          <span class="preview-badge" style="background: var(--voak-navy);">${p.id}</span>
          <span class="preview-badge" style="background: #475569; font-size: 0.688rem;">${p.reqId}</span>
        </div>
        <span style="font-family: monospace; font-size: 0.75rem; color: #64748B;">${p.route}</span>
      </div>
      <h3 style="font-size: 1.35rem; margin-bottom: 0.5rem; color: var(--voak-navy);">${p.title}</h3>
      <p style="font-size: 0.813rem; color: var(--voak-muted); margin-bottom: 0.75rem;">
        <strong>Hero Style:</strong> ${p.heroStyle}
      </p>
      <div style="background: #F8FAFC; border-radius: var(--radius-sm); padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #E2E8F0; flex-grow: 1;">
        <div style="font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.25rem;">KEY SECTIONS:</div>
        <div style="font-size: 0.75rem; color: #64748B; line-height: 1.4;">${p.sections}</div>
      </div>
      <div style="padding-top: 0.75rem; border-top: 1px solid var(--voak-border-subtle); display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
        <span style="font-size: 0.75rem; font-weight: 600; color: var(--voak-purple-cta); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${p.cta}</span>
        <button class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; min-height: 32px; white-space: nowrap;" onclick="inspectPage('${p.id}')">Inspect Screen &rarr;</button>
      </div>
    </div>
  `).join('');
}

function populateInspectorDropdown() {
  const select = document.getElementById('inspector-page-select');
  if (!select) return;

  select.innerHTML = PUBLIC_PAGES_DATA.map(p => `
    <option value="${p.id}">${p.id}: ${p.title} (${p.route})</option>
  `).join('');
}

/* --------------------------------------------------------------------------
   4. FULL-PAGE SCREEN INSPECTOR (High-Fidelity Visual Previews)
   -------------------------------------------------------------------------- */
function inspectPage(pageId) {
  const page = PUBLIC_PAGES_DATA.find(p => p.id === pageId) || PUBLIC_PAGES_DATA[0];
  const modal = document.getElementById('screen-inspector-modal');
  const badge = document.getElementById('inspector-badge');
  const title = document.getElementById('inspector-title');
  const route = document.getElementById('inspector-route');
  const select = document.getElementById('inspector-page-select');
  const body = document.getElementById('screen-mockup-body');

  if (badge) badge.textContent = `${page.id} • ${page.reqId}`;
  if (title) title.textContent = page.title;
  if (route) route.textContent = page.route;
  if (select) select.value = page.id;

  if (body) {
    body.innerHTML = renderFullPageScreen(page);
  }

  if (modal) {
    modal.style.display = 'block';
    modal.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

function closeScreenInspector() {
  const modal = document.getElementById('screen-inspector-modal');
  if (modal) modal.style.display = 'none';
}

function renderFullPageScreen(page) {
  return `
    <div class="page-shell" style="box-shadow: none; margin: 0; border-radius: 0;">
      <!-- Common Accessible Header -->
      <header class="voak-header" style="position: relative; top: 0;">
        <nav class="voak-nav-card" aria-label="Mockup Navigation">
          <div class="voak-logo-container">
            <svg class="voak-logo-icon" viewBox="0 0 40 40" fill="none">
              <circle cx="20" cy="20" r="18" fill="#F2F0F8"/>
              <path d="M12 24 C14 14 26 14 28 24 C24 30 16 30 12 24 Z" fill="#665CE9"/>
              <circle cx="20" cy="14" r="5" fill="#EEAFD7"/>
            </svg>
            <div class="voak-logo-text">
              <span class="voak-logo-title">VOAK</span>
              <span class="voak-logo-sub">HEALTH CARE</span>
            </div>
          </div>
          <ul class="voak-nav-menu">
            <li><a href="#home" class="voak-nav-link ${page.id === 'PUB-01' ? 'active' : ''}">Home</a></li>
            <li><a href="#about" class="voak-nav-link ${page.id === 'PUB-02' ? 'active' : ''}">About</a></li>
            <li><a href="#services" class="voak-nav-link ${page.id.startsWith('PUB-03') || page.id.startsWith('PUB-04') ? 'active' : ''}">Services</a></li>
            <li><a href="#industries" class="voak-nav-link ${page.id.startsWith('PUB-05') || page.id.startsWith('PUB-06') ? 'active' : ''}">Industries</a></li>
            <li><a href="#careers" class="voak-nav-link ${page.id.startsWith('PUB-12') || page.id.startsWith('PUB-13') ? 'active' : ''}">Careers</a></li>
            <li><a href="#contact" class="voak-nav-link ${page.id === 'PUB-17' ? 'active' : ''}">Contact</a></li>
          </ul>
          <div class="nav-actions">
            <button class="btn btn-primary" onclick="showProposalWizard()">Request Proposal</button>
          </div>
        </nav>
      </header>

      <!-- Page-Specific High Fidelity Hero & Body -->
      ${renderPageSpecificBody(page)}

      <!-- Standard Accessible Corporate Footer -->
      <footer class="voak-footer" style="margin-top: 3rem;">
        <div class="footer-grid">
          <div>
            <div class="voak-logo-container" style="margin-bottom: 1rem;">
              <svg class="voak-logo-icon" viewBox="0 0 40 40" fill="none">
                <circle cx="20" cy="20" r="18" fill="#F2F0F8"/>
                <path d="M12 24 C14 14 26 14 28 24 C24 30 16 30 12 24 Z" fill="#665CE9"/>
                <circle cx="20" cy="14" r="5" fill="#EEAFD7"/>
              </svg>
              <div class="voak-logo-text">
                <span class="voak-logo-title">VOAK</span>
                <span class="voak-logo-sub">HEALTH CARE</span>
              </div>
            </div>
            <p style="font-size: 0.875rem; max-width: 300px; margin-bottom: 1rem; color: var(--voak-muted);">
              Enterprise B2B healthcare workforce solutions and operational infrastructure partner.
            </p>
            <div style="font-size: 0.813rem; color: var(--voak-muted);">
              Corporate Operations &bull; [Jurisdiction & Address Configurable via CMS]<br>
              demo@example.invalid (Contact details pending formal business confirmation)
            </div>
          </div>
          <div>
            <h4 class="footer-col-title">Services</h4>
            <ul class="footer-links-list">
              <li><a href="#">Clinical Workforce Solutions</a></li>
              <li><a href="#">Healthcare Operations BPO</a></li>
              <li><a href="#">Clinical Governance Framework</a></li>
              <li><a href="#">Allied Health Infrastructure</a></li>
            </ul>
          </div>
          <div>
            <h4 class="footer-col-title">Industries</h4>
            <ul class="footer-links-list">
              <li><a href="#">Hospitals & Health Services</a></li>
              <li><a href="#">Residential Aged Care</a></li>
              <li><a href="#">Community & Specialized Care [PENDING APPROVAL]</a></li>
              <li><a href="#">Day Surgery & Specialist Clinics</a></li>
            </ul>
          </div>
          <div>
            <h4 class="footer-col-title">Governance & Legal</h4>
            <ul class="footer-links-list">
              <li><a href="#" onclick="inspectPage('PUB-20')">Privacy Policy</a></li>
              <li><a href="#" onclick="inspectPage('PUB-21')">Terms of Service</a></li>
              <li><a href="#" onclick="inspectPage('PUB-09')">Quality & Credentialing</a></li>
              <li><a href="#" onclick="inspectPage('PUB-19')">Request a Proposal</a></li>
            </ul>
          </div>
        </div>
        <div class="footer-bottom">
          <p>&copy; 2026 Voak Health Care. All rights reserved. [Entity & Jurisdiction Subject to Confirmation]</p>
          <div style="display: flex; gap: 1.5rem;">
            <span>A11y Target: WCAG 2.2 AA Standard</span>
            <span>Canonical Route: ${page.route}</span>
          </div>
        </div>
      </footer>
    </div>
  `;
}

function renderPageSpecificBody(page) {
  switch (page.id) {
    case 'PUB-01':
      return `
        <!-- SECTION 02: HERO SECTION (Approved Image 2 Benchmark) -->
        <section class="hero-section" id="hero">
          <div class="hero-content">
            <div class="hero-pill-badge">
              <span class="hero-pill-dot"></span>
              B2B HEALTHCARE SERVICES
            </div>
            <h1 class="hero-title">Better Healthcare Together</h1>
            <p class="hero-lead">
              Empowering healthcare systems, hospitals, and residential care providers with specialized clinical workforce solutions, operational governance, and end-to-end B2B healthcare infrastructure.
            </p>
            <div class="hero-actions">
              <button class="btn btn-primary" onclick="inspectPage('PUB-03')">
                Explore Our Services
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </button>
              <button class="hero-video-trigger" onclick="alert('Simulated Video Modal [FEATURE PENDING APPROVAL]')">
                <span class="video-play-btn">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                </span>
                <span>Watch Video</span>
              </button>
            </div>
          </div>

          <!-- Hero Right Collage Composition (Approved Reference Benchmark) -->
          <div class="hero-visual-stage">
            <div class="hero-pink-backdrop"></div>
            <div class="hero-yellow-accent"></div>

            <div class="hero-portrait-frame">
              <img src="assets/images/hero_doctor.jpg" alt="Senior Healthcare Director in modern clinic" width="320" height="380">
            </div>

            <div class="hero-floating-heart-badge">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
            </div>

            <div class="hero-floating-facility-card">
              <img src="assets/images/hospital_building.jpg" alt="Healthcare Facility" class="facility-card-img" width="56" height="56">
              <div class="facility-card-body">
                <span class="facility-card-label">Trusted Healthcare Partner</span>
                <button class="facility-card-btn" aria-label="Learn more about partner facilities" onclick="inspectPage('PUB-10')">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- SECTION 03: THREE FEATURE CARDS (Pink, Yellow, Mint) -->
        <section class="feature-cards-grid">
          <!-- Card 1: Pastel Pink -->
          <article class="feature-card feature-card-pink">
            <div class="feature-card-content">
              <span class="card-tag">OUR SERVICES</span>
              <h2 class="card-title-lg">Comprehensive Healthcare Solutions</h2>
              <p class="card-desc">
                Rapid clinical staffing, allied health coordination, and specialized operational management tailored to hospital networks and clinical facilities.
              </p>
              <div class="card-action-row">
                <button class="card-pill-white-btn" onclick="inspectPage('PUB-03')">
                  Discover Services
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
              </div>
            </div>
            <div class="feature-card-visual">
              <img src="assets/images/male_clinician.jpg" alt="Healthcare Professional" class="feature-card-img" width="130" height="160">
            </div>
          </article>

          <!-- Card 2: Warm Yellow -->
          <article class="feature-card feature-card-yellow">
            <div class="feature-card-content">
              <span class="card-tag">INDUSTRIES</span>
              <h2 class="card-title-lg">We Work Across Healthcare Sectors</h2>
              <p class="card-desc">
                From major public hospitals to residential aged care, community healthcare organizations, and private surgical centers [CONTENT PENDING APPROVAL].
              </p>
              <div class="card-action-row">
                <button class="card-pill-white-btn" onclick="inspectPage('PUB-05')">
                  View Sectors
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
              </div>
            </div>
            <div class="feature-card-visual">
              <img src="assets/images/hospital_building.jpg" alt="Hospital Building" class="feature-card-img" width="130" height="160">
            </div>
          </article>

          <!-- Card 3: Mint Green -->
          <article class="feature-card feature-card-mint">
            <div class="feature-card-content">
              <span class="card-tag">PARTNER WITH US</span>
              <h2 class="card-title-lg">Grow Together</h2>
              <p class="card-desc">
                Establish institutional service agreements, customized staffing frameworks, and accredited healthcare delivery partnerships.
              </p>
              <div class="card-action-row">
                <button class="card-pill-white-btn" onclick="showProposalWizard()">
                  Request Proposal
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
              </div>
            </div>
            <div class="feature-card-visual">
              <img src="assets/images/partnership_consultation.jpg" alt="Healthcare Consultation" class="feature-card-img" width="130" height="160">
            </div>
          </article>
        </section>

        <!-- SECTION 04: SERVICES BENTO GRID (4x2 Pastel Tiles Layout) -->
        <section class="section-wrapper" id="services">
          <div class="services-asymmetric-layout">
            <div class="services-intro-col">
              <span class="hero-pill-badge" style="margin-bottom: 0.75rem;">OUR SERVICES</span>
              <h2 class="section-title">We Provide End-to-End Support</h2>
              <p class="section-subtitle" style="margin-bottom: 1.5rem; font-size: 0.938rem; line-height: 1.6;">
                Institutional capabilities engineered to eliminate clinical staffing deficits and ensure operational continuity.
              </p>
              <button class="btn btn-secondary" onclick="inspectPage('PUB-03')">
                See All Services
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </button>
            </div>

            <div class="services-tiles-grid">
              <div class="service-tile-card bento-tint-pink" onclick="inspectPage('PUB-03')">
                <div class="service-icon-box" style="background: rgba(238, 175, 215, 0.6); color: var(--voak-navy);">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <h3 style="font-size: 1.15rem; margin-bottom: 0.4rem;">Clinical Workforce & Staffing</h3>
                <p style="margin-bottom: 1rem; font-size: 0.813rem; line-height: 1.45;">
                  Vetted registered nurses and healthcare professionals for surge relief and facility placements.
                </p>
                <span class="chip chip-purple" style="align-self: flex-start; margin-top: auto; font-size: 0.7rem;">ICU / ED / Acute</span>
              </div>

              <div class="service-tile-card bento-tint-yellow" onclick="inspectPage('PUB-03')">
                <div class="service-icon-box" style="background: rgba(251, 220, 98, 0.6); color: var(--voak-navy);">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                </div>
                <h3 style="font-size: 1.15rem; margin-bottom: 0.4rem;">Operations & Facility BPO</h3>
                <p style="margin-bottom: 1rem; font-size: 0.813rem; line-height: 1.45;">
                  Turnkey administrative management, patient intake coordination, and clinical back-office optimization.
                </p>
                <span class="chip chip-warning" style="align-self: flex-start; margin-top: auto; font-size: 0.7rem;">Operations BPO</span>
              </div>

              <div class="service-tile-card bento-tint-mint" onclick="inspectPage('PUB-09')">
                <div class="service-icon-box" style="background: rgba(84, 203, 150, 0.6); color: var(--voak-navy);">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <h3 style="font-size: 1.15rem; margin-bottom: 0.4rem;">Governance & Compliance</h3>
                <p style="margin-bottom: 1rem; font-size: 0.813rem; line-height: 1.45;">
                  Clinical governance advisory and accreditation readiness auditing aligned with regulatory standards.
                </p>
                <span class="chip chip-success" style="align-self: flex-start; margin-top: auto; font-size: 0.7rem;">Audit Ready</span>
              </div>

              <div class="service-tile-card bento-tint-sky" onclick="inspectPage('PUB-03')">
                <div class="service-icon-box" style="background: rgba(99, 165, 230, 0.6); color: var(--voak-navy);">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </div>
                <h3 style="font-size: 1.15rem; margin-bottom: 0.4rem;">Allied Health Infrastructure</h3>
                <p style="margin-bottom: 1rem; font-size: 0.813rem; line-height: 1.45;">
                  Multidisciplinary healthcare coordination including physiotherapy, OT, and specialized rehabilitation.
                </p>
                <span class="chip chip-info" style="align-self: flex-start; margin-top: auto; font-size: 0.7rem;">Multidisciplinary</span>
              </div>

              <div class="service-tile-card bento-tint-purple" onclick="inspectPage('PUB-03')">
                <div class="service-icon-box" style="background: rgba(102, 92, 233, 0.2); color: var(--voak-purple-cta);">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <h3 style="font-size: 1.15rem; margin-bottom: 0.4rem;">Roster Surge Support</h3>
                <p style="margin-bottom: 1rem; font-size: 0.813rem; line-height: 1.45;">
                  Rapid clinical mobilization for peak hospital demand, seasonal surges, and ward staffing relief.
                </p>
                <span class="chip chip-purple" style="align-self: flex-start; margin-top: auto; font-size: 0.7rem;">Surge Response</span>
              </div>

              <div class="service-tile-card bento-tint-amber" onclick="inspectPage('PUB-09')">
                <div class="service-icon-box" style="background: rgba(245, 158, 11, 0.2); color: #B45309;">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <h3 style="font-size: 1.15rem; margin-bottom: 0.4rem;">Credential Verification</h3>
                <p style="margin-bottom: 1rem; font-size: 0.813rem; line-height: 1.45;">
                  Comprehensive primary-source verification of professional registrations and clinical clearances.
                </p>
                <span class="chip chip-warning" style="align-self: flex-start; margin-top: auto; font-size: 0.7rem;">Verified Pool</span>
              </div>

              <div class="service-tile-card bento-tint-teal" onclick="inspectPage('PUB-03')">
                <div class="service-icon-box" style="background: rgba(20, 184, 166, 0.2); color: #0F766E;">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                </div>
                <h3 style="font-size: 1.15rem; margin-bottom: 0.4rem;">Facility Transit Logistics</h3>
                <p style="margin-bottom: 1rem; font-size: 0.813rem; line-height: 1.45;">
                  Internal facility coordination, clinical equipment logistics, and assistive mobility support.
                </p>
                <span class="chip chip-info" style="align-self: flex-start; margin-top: auto; font-size: 0.7rem;">Logistics Support</span>
              </div>

              <div class="service-tile-card bento-tint-lavender" onclick="inspectPage('PUB-03')">
                <div class="service-icon-box" style="background: rgba(139, 92, 246, 0.2); color: #6D28D9;">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </div>
                <h3 style="font-size: 1.15rem; margin-bottom: 0.4rem;">Health System Strategy</h3>
                <p style="margin-bottom: 1rem; font-size: 0.813rem; line-height: 1.45;">
                  Enterprise workforce frameworks designed for multi-hospital networks and regional health districts.
                </p>
                <span class="chip chip-purple" style="align-self: flex-start; margin-top: auto; font-size: 0.7rem;">Enterprise Tier</span>
              </div>
            </div>
          </div>
        </section>

        <!-- SECTION 05: HORIZONTAL TRUST SECTION (Image 2 Full-Width Benchmark) -->
        <section class="horizontal-trust-section" id="about">
          <div class="trust-container">
            <div class="trust-portrait-col">
              <div class="trust-portrait-circle">
                <img src="assets/images/operations_manager.jpg" alt="Clinical Operations Director" width="160" height="160">
              </div>
            </div>

            <div class="trust-content-col">
              <span class="trust-tag">WHY VOAK</span>
              <h2 class="trust-heading">Trusted by Healthcare Organisations</h2>
              <p class="trust-desc">
                We combine clinical governance with operational reliability, supporting healthcare networks with verified practitioner placement, workforce coordination, and institutional accountability.
              </p>
              <button class="trust-learn-btn" onclick="inspectPage('PUB-07')">
                Learn More
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </button>
            </div>

            <div class="trust-stats-col">
              <div class="trust-stat-item">
                <div class="trust-stat-number">&mdash;</div>
                <div class="trust-stat-label">Active Placements <span class="badge-pending" style="display:block; font-size: 0.65rem; background: rgba(0,0,0,0.06); padding: 2px 6px; border-radius: 4px; margin-top: 4px;">[METRIC TBD &bull; PENDING APPROVAL]</span></div>
              </div>
              <div class="trust-stat-item">
                <div class="trust-stat-number">&mdash;</div>
                <div class="trust-stat-label">Partner Facilities <span class="badge-pending" style="display:block; font-size: 0.65rem; background: rgba(0,0,0,0.06); padding: 2px 6px; border-radius: 4px; margin-top: 4px;">[METRIC TBD &bull; PENDING APPROVAL]</span></div>
              </div>
              <div class="trust-stat-item">
                <div class="trust-stat-number">&mdash;</div>
                <div class="trust-stat-label">Target Fill Rate <span class="badge-pending" style="display:block; font-size: 0.65rem; background: rgba(0,0,0,0.06); padding: 2px 6px; border-radius: 4px; margin-top: 4px;">[METRIC TBD &bull; PENDING APPROVAL]</span></div>
              </div>
            </div>

            <div class="trust-photo-card-col">
              <div class="trust-photo-card">
                <img src="assets/images/patient_care_trust.jpg" alt="Dedicated Patient Care" width="180" height="220">
              </div>
            </div>
          </div>
        </section>

        <!-- SECTION 06: INDUSTRIES WE SERVE -->
        <section class="section-wrapper" id="industries">
          <div class="section-header-row">
            <div>
              <h2 class="section-title">Industries We Serve</h2>
              <p class="section-subtitle">Dedicated operational frameworks tailored for each healthcare sector.</p>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;">
            <div class="bento-card" style="border-top: 4px solid var(--voak-purple-cta);">
              <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Public & Private Hospitals</h3>
              <p style="font-size: 0.875rem;">Emergency department staffing, ICU nurse allocation, and acute surgical team support.</p>
            </div>
            <div class="bento-card" style="border-top: 4px solid var(--voak-pink);">
              <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Aged Care Facilities</h3>
              <p style="font-size: 0.875rem;">Specialized geriatric nursing, medication management, and continuous care operations.</p>
            </div>
            <div class="bento-card" style="border-top: 4px solid var(--voak-yellow);">
              <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Community & Specialized Care <span class="chip chip-warning" style="font-size: 0.65rem; padding: 2px 6px;">PENDING APPROVAL</span></h3>
              <p style="font-size: 0.875rem;">Community nursing, clinical support coordination, and specialized healthcare assistance [CONTENT PENDING APPROVAL].</p>
            </div>
            <div class="bento-card" style="border-top: 4px solid var(--voak-mint);">
              <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Corporate & Occupational Health</h3>
              <p style="font-size: 0.875rem;">Onsite health assessments, workplace wellness programs, and industrial health governance.</p>
            </div>
          </div>
        </section>

        <!-- SECTION 07: HOW WE WORK (4 Steps) -->
        <section class="section-wrapper" id="how-we-work">
          <div class="section-header-row">
            <div>
              <h2 class="section-title">How We Partner With Healthcare Organizations</h2>
              <p class="section-subtitle">A clear, disciplined 4-stage journey from operational audit to sustained delivery.</p>
            </div>
          </div>

          <div class="workflow-steps-grid">
            <div class="step-card">
              <div class="step-number-chip">01</div>
              <h3 style="font-size: 1.2rem; margin-bottom: 0.5rem;">Operational Discovery</h3>
              <p style="font-size: 0.875rem;">We evaluate your organization's staffing patterns, clinical protocols, and peak demand cycles.</p>
            </div>
            <div class="step-card">
              <div class="step-number-chip">02</div>
              <h3 style="font-size: 1.2rem; margin-bottom: 0.5rem;">Solution Architecture</h3>
              <p style="font-size: 0.875rem;">Designing customized workforce SLA tiers, credentialing baselines, and communication workflows.</p>
            </div>
            <div class="step-card">
              <div class="step-number-chip">03</div>
              <h3 style="font-size: 1.2rem; margin-bottom: 0.5rem;">Deployment & Integration</h3>
              <p style="font-size: 0.875rem;">Seamless onboarding into your roster systems with zero downtime and full compliance handoff.</p>
            </div>
            <div class="step-card">
              <div class="step-number-chip">04</div>
              <h3 style="font-size: 1.2rem; margin-bottom: 0.5rem;">Continuous Governance</h3>
              <p style="font-size: 0.875rem;">Scheduled clinical governance reviews, KPI reporting, and quality monitoring [Proposed capability — pending formal business confirmation].</p>
            </div>
          </div>
        </section>

        <!-- SECTION 11: PARTNER CTA BANNER -->
        <section class="partner-cta-card">
          <h2>Transform Your Healthcare Operations Today</h2>
          <p>Partner with Voak Health Care to secure accredited workforce reliability, operational excellence, and peace of mind.</p>
          <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <button class="btn btn-pill-yellow" onclick="showProposalWizard()">Request Formal Proposal</button>
            <button class="btn btn-outline" style="border-color: #FFF; color: #FFF;" onclick="inspectPage('PUB-17')">Schedule Discovery Call</button>
          </div>
        </section>
      `;

    case 'PUB-02':
      return `
        <div style="background: var(--voak-canvas); padding: 4rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-sky); color: #FFF;">About Voak Health Care</span>
          <h1 style="font-family: var(--font-serif); font-size: 3rem; color: var(--voak-navy); margin: 1rem 0;">Institutional Mission & Executive Governance</h1>
          <p style="max-width: 680px; margin: 0 auto 2rem auto; color: var(--voak-muted); font-size: 1.125rem;">
            Voak Health Care is dedicated to solving healthcare staffing and operational challenges through ethical governance, rigorous credentialing verification, and resilient workforce models.
          </p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 1100px; margin: 0 auto;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; margin-bottom: 3rem;">
            <div style="background: #FFF; padding: 2rem; border-radius: var(--radius-lg); border: 1px solid var(--voak-border-subtle);">
              <h3 style="font-size: 1.4rem; color: var(--voak-navy); margin-bottom: 1rem;">Our Institutional Purpose</h3>
              <p style="color: var(--voak-muted); line-height: 1.6;">
                Healthcare systems require dependable operational infrastructure. We build collaborative partnerships with hospitals, health networks, and residential care providers to maintain continuous clinical capability and support positive patient outcomes.
              </p>
            </div>
            <div style="background: #FFF; padding: 2rem; border-radius: var(--radius-lg); border: 1px solid var(--voak-border-subtle);">
              <h3 style="font-size: 1.4rem; color: var(--voak-navy); margin-bottom: 1rem;">Clinical Quality Commitment</h3>
              <p style="color: var(--voak-muted); line-height: 1.6;">
                Every healthcare professional placed through Voak undergoes comprehensive identity verification, primary-source credential verification, clean disciplinary standing checks, and clinical orientation.
              </p>
            </div>
          </div>

          <!-- Executive Leadership Section (Integrated into About per Task 4) -->
          <div style="margin-bottom: 3rem;">
            <div style="text-align: center; margin-bottom: 2rem;">
              <span class="preview-badge" style="background: var(--voak-purple-cta); color: #FFF;">Governance Structure &bull; [REPRESENTATIVE TEMPLATE]</span>
              <h2 style="font-family: var(--font-serif); font-size: 2.2rem; color: var(--voak-navy); margin-top: 0.5rem;">Executive Leadership Team</h2>
              <p style="color: var(--voak-muted);">Proposed clinical and operational executive leadership profiles [FORMAL BIOGRAPHIES PENDING CLIENT APPROVAL].</p>
            </div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
              <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 1.75rem; text-align: center;">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--voak-canvas); margin: 0 auto 1rem auto; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; color: var(--voak-purple-cta);">CEO</div>
                <div style="font-size: 0.75rem; color: #B45309; font-weight: 600; margin-bottom: 0.25rem;">[ROLE TEMPLATE]</div>
                <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin-bottom: 0.25rem;">Chief Executive Officer</h4>
                <div style="font-size: 0.813rem; color: var(--voak-purple-cta); font-weight: 600; margin-bottom: 0.75rem;">Executive Administration</div>
                <p style="font-size: 0.813rem; color: var(--voak-muted); line-height: 1.5;">Hospital administration and clinical governance leadership. [Executive appointment pending formal corporate confirmation]</p>
              </div>
              <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 1.75rem; text-align: center;">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--voak-canvas); margin: 0 auto 1rem auto; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; color: var(--voak-mint);">COD</div>
                <div style="font-size: 0.75rem; color: #B45309; font-weight: 600; margin-bottom: 0.25rem;">[ROLE TEMPLATE]</div>
                <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin-bottom: 0.25rem;">Clinical Operations Director</h4>
                <div style="font-size: 0.813rem; color: var(--voak-mint); font-weight: 600; margin-bottom: 0.75rem;">Clinical Quality & Standards</div>
                <p style="font-size: 0.813rem; color: var(--voak-muted); line-height: 1.5;">Acute roster continuity, clinical mobilization, and national standards compliance [REGULATORY DETAILS PENDING APPROVAL].</p>
              </div>
              <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 1.75rem; text-align: center;">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--voak-canvas); margin: 0 auto 1rem auto; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; color: #B45309;">DPC</div>
                <div style="font-size: 0.75rem; color: #B45309; font-weight: 600; margin-bottom: 0.25rem;">[ROLE TEMPLATE]</div>
                <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin-bottom: 0.25rem;">Director of People & Culture</h4>
                <div style="font-size: 0.813rem; color: #B45309; font-weight: 600; margin-bottom: 0.75rem;">Credentialing & Workforce Ethics</div>
                <p style="font-size: 0.813rem; color: var(--voak-muted); line-height: 1.5;">Healthcare recruitment ethics, primary-source credentialing, and clinician engagement [APPOINTMENT PENDING CONFIRMATION].</p>
              </div>
            </div>
          </div>

          <!-- Core Values -->
          <div style="background: #FAF9FE; padding: 2.5rem; border-radius: var(--radius-xl); border: 1px solid var(--voak-border-subtle);">
            <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin-bottom: 1.5rem; text-align: center;">Institutional Values Grid</h3>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem;">
              <div style="background: #FFF; padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
                <strong style="color: var(--voak-purple-cta); display: block; margin-bottom: 0.25rem;">Clinical Rigor</strong>
                <p style="font-size: 0.75rem; color: var(--voak-muted); margin: 0;">Uncompromising primary-source credential verification.</p>
              </div>
              <div style="background: #FFF; padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
                <strong style="color: var(--voak-mint); display: block; margin-bottom: 0.25rem;">Operational Resilience</strong>
                <p style="font-size: 0.75rem; color: var(--voak-muted); margin: 0;">Dependable coverage for unplanned hospital roster surges.</p>
              </div>
              <div style="background: #FFF; padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
                <strong style="color: #B45309; display: block; margin-bottom: 0.25rem;">Ethical Governance</strong>
                <p style="font-size: 0.75rem; color: var(--voak-muted); margin: 0;">Transparent B2B contracts with zero hidden agency costs.</p>
              </div>
              <div style="background: #FFF; padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
                <strong style="color: var(--voak-sky); display: block; margin-bottom: 0.25rem;">Human Partnership</strong>
                <p style="font-size: 0.75rem; color: var(--voak-muted); margin: 0;">Designated account coordination workflows for institutional partners [SERVICE MODEL PENDING APPROVAL].</p>
              </div>
            </div>
          </div>
        </div>
      `;

    case 'PUB-03':
      return `
        <div style="background: var(--voak-canvas); padding: 3.5rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-mint); color: #FFF;">Comprehensive Services</span>
          <h1 style="font-family: var(--font-serif); font-size: 2.75rem; color: var(--voak-navy); margin: 0.75rem 0;">Healthcare Solutions Portfolio</h1>
          <p style="max-width: 640px; margin: 0 auto; color: var(--voak-muted);">Tailored clinical workforce deployment, facility operations BPO, and governance advisory.</p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 1200px; margin: 0 auto;">
          <!-- Filter Tabs -->
          <div style="display: flex; gap: 0.5rem; margin-bottom: 2rem; flex-wrap: wrap; justify-content: center;">
            <button class="chip chip-purple" style="cursor: pointer; padding: 0.5rem 1rem;">All Services</button>
            <button class="chip" style="cursor: pointer; padding: 0.5rem 1rem; background: #F1F5F9;">Clinical Workforce</button>
            <button class="chip" style="cursor: pointer; padding: 0.5rem 1rem; background: #F1F5F9;">Operations BPO</button>
            <button class="chip" style="cursor: pointer; padding: 0.5rem 1rem; background: #F1F5F9;">Quality & Governance</button>
            <button class="chip" style="cursor: pointer; padding: 0.5rem 1rem; background: #F1F5F9;">Allied Health</button>
          </div>
          <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem;">
            <div class="bento-card bento-pink" style="min-height: 280px; display: flex; flex-direction: column;">
              <span class="card-tag">Workforce</span>
              <h3 style="font-size: 1.4rem; margin: 0.5rem 0;">Clinical Staffing</h3>
              <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1rem;">ICU, Emergency, Acute Care nurses, clinical managers, and medical specialists.</p>
              <button class="btn btn-outline" style="margin-top: auto; align-self: flex-start;" onclick="inspectPage('PUB-04')">View Details &rarr;</button>
            </div>
            <div class="bento-card bento-yellow" style="min-height: 280px; display: flex; flex-direction: column;">
              <span class="card-tag">Operations</span>
              <h3 style="font-size: 1.4rem; margin: 0.5rem 0;">Operations BPO</h3>
              <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1rem;">Ward intake coordination, patient flow support, and rostering administration.</p>
              <button class="btn btn-outline" style="margin-top: auto; align-self: flex-start;" onclick="showProposalWizard()">Request Scope &rarr;</button>
            </div>
            <div class="bento-card bento-mint" style="min-height: 280px; display: flex; flex-direction: column;">
              <span class="card-tag">Governance</span>
              <h3 style="font-size: 1.4rem; margin: 0.5rem 0;">Quality Advisory</h3>
              <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1rem;">Healthcare compliance auditing, clinical review, and credentialing governance.</p>
              <button class="btn btn-outline" style="margin-top: auto; align-self: flex-start;" onclick="inspectPage('PUB-09')">View Framework &rarr;</button>
            </div>
            <div class="bento-card bento-sky" style="min-height: 280px; display: flex; flex-direction: column;">
              <span class="card-tag">Therapy</span>
              <h3 style="font-size: 1.4rem; margin: 0.5rem 0;">Allied Health Infrastructure</h3>
              <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1rem;">Physiotherapy, occupational therapy, and speech pathology multidisciplinary rosters.</p>
              <button class="btn btn-outline" style="margin-top: auto; align-self: flex-start;" onclick="showProposalWizard()">Scope Allied Health &rarr;</button>
            </div>
            <div class="bento-card bento-pink" style="min-height: 280px; display: flex; flex-direction: column;">
              <span class="card-tag">Rapid Cover</span>
              <h3 style="font-size: 1.4rem; margin: 0.5rem 0;">Roster Surge Management</h3>
              <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1rem;">Rapid mobilization for unplanned peak hospital demand and emergency ward shortfalls.</p>
              <button class="btn btn-outline" style="margin-top: auto; align-self: flex-start;" onclick="showProposalWizard()">Deploy Surge &rarr;</button>
            </div>
            <div class="bento-card bento-yellow" style="min-height: 280px; display: flex; flex-direction: column;">
              <span class="card-tag">Logistics</span>
              <h3 style="font-size: 1.4rem; margin: 0.5rem 0;">Healthcare Facility Logistics</h3>
              <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1rem;">Clinical equipment coordination and patient transit logistics across facility campuses.</p>
              <button class="btn btn-outline" style="margin-top: auto; align-self: flex-start;" onclick="showProposalWizard()">Inquire Logistics &rarr;</button>
            </div>
          </div>
        </div>
      `;

    case 'PUB-04':
      return `
        <div style="background: #FFF; padding: 3rem 2.5rem; border-bottom: 1px solid var(--voak-border-subtle);">
          <div style="max-width: 1000px; margin: 0 auto;">
            <span class="chip chip-info" style="margin-bottom: 0.75rem;">Service Pillar 01 &bull; FR-PUB-006</span>
            <h1 style="font-family: var(--font-serif); font-size: 2.75rem; color: var(--voak-navy); margin-bottom: 1rem;">Clinical Workforce Solutions</h1>
            <p style="font-size: 1.15rem; color: var(--voak-muted); max-width: 720px; line-height: 1.6;">
              Dependable staffing for specialized clinical environments. Providing vetted registered nurses, clinical coordinators, and emergency healthcare professionals for short-term surge relief and planned facility placements.
            </p>
          </div>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 1000px; margin: 0 auto;">
          <h3 style="font-size: 1.5rem; margin-bottom: 1.5rem; color: var(--voak-navy);">Clinical Deployment Scope</h3>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 3rem;">
            <div style="background: #F8FAFC; padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Core Clinical Disciplines</h4>
              <ul style="padding-left: 1.25rem; font-size: 0.9rem; color: var(--voak-muted); line-height: 1.8;">
                <li>Critical Care & Intensive Care Unit (ICU) Specialists</li>
                <li>Emergency Department (ED) Registered Nurses</li>
                <li>Surgical Suite & Perioperative Practitioners</li>
                <li>Sub-Acute & Rehabilitation Nursing Leads</li>
              </ul>
            </div>
            <div style="background: #F8FAFC; padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Credentialing & Governance</h4>
              <ul style="padding-left: 1.25rem; font-size: 0.9rem; color: var(--voak-muted); line-height: 1.8;">
                <li>Primary source verification of professional registrations</li>
                <li>Identity verification and statutory background clearance protocols [PENDING APPROVAL]</li>
                <li>Clinical competency assessments & BLS/ALS certification</li>
                <li>Continuous professional development (CPD) auditing</li>
              </ul>
            </div>
          </div>
          <div style="background: var(--voak-canvas); border-radius: var(--radius-lg); padding: 2rem; display: flex; align-items: center; justify-content: space-between;">
            <div>
              <h4 style="font-size: 1.25rem; color: var(--voak-navy); margin-bottom: 0.25rem;">Require Clinical Workforce Support?</h4>
              <p style="color: var(--voak-muted); font-size: 0.875rem;">Our operations desk assists facilities in evaluating roster requirements.</p>
            </div>
            <button class="btn btn-primary" onclick="showProposalWizard()">Initiate Proposal (FORM-PUB-03)</button>
          </div>
        </div>
      `;

    case 'PUB-05':
      return `
        <div style="background: var(--voak-canvas); padding: 4rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-purple-cta); color: #FFF;">Healthcare Verticals</span>
          <h1 style="font-family: var(--font-serif); font-size: 3rem; color: var(--voak-navy); margin: 1rem 0;">Healthcare Sectors We Support</h1>
          <p style="max-width: 680px; margin: 0 auto; color: var(--voak-muted); font-size: 1.125rem;">
            Specialized operational frameworks and tailored workforce models configured for distinct healthcare environments.
          </p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 1200px; margin: 0 auto;">
          <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-bottom: 3rem;">
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; border-top: 4px solid var(--voak-purple-cta);">
              <span class="card-tag">Public Healthcare</span>
              <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin: 0.75rem 0 0.5rem 0;">Public Hospital Networks</h3>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin-bottom: 1.25rem;">Emergency department staffing, acute ward coverage, and multi-disciplinary shift surge relief.</p>
              <button class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;" onclick="inspectPage('PUB-06')">Sector Detail &rarr;</button>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; border-top: 4px solid var(--voak-pink);">
              <span class="card-tag">Private Health</span>
              <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin: 0.75rem 0 0.5rem 0;">Private Hospital Groups</h3>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin-bottom: 1.25rem;">Elective surgical suites, perioperative practitioners, and premium ward clinical coordinators.</p>
              <button class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;" onclick="showProposalWizard()">Scope Sector &rarr;</button>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; border-top: 4px solid var(--voak-yellow);">
              <span class="card-tag">Aged Care</span>
              <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin: 0.75rem 0 0.5rem 0;">Residential Aged Care</h3>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin-bottom: 1.25rem;">Geriatric care nurses, medication administration leads, and planned multi-shift clinical rosters [SERVICE MODEL PENDING APPROVAL].</p>
              <button class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;" onclick="showProposalWizard()">Scope Sector &rarr;</button>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; border-top: 4px solid var(--voak-mint);">
              <span class="card-tag">Community</span>
              <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin: 0.75rem 0 0.5rem 0;">Community & Specialized Care <span class="chip chip-warning" style="font-size: 0.65rem; padding: 2px 6px;">PENDING APPROVAL</span></h3>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin-bottom: 1.25rem;">Specialized healthcare support, clinical care coordination, and mobile allied health teams [CONTENT PENDING APPROVAL].</p>
              <button class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;" onclick="showProposalWizard()">Scope Sector &rarr;</button>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; border-top: 4px solid var(--voak-sky);">
              <span class="card-tag">Ambulatory</span>
              <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin: 0.75rem 0 0.5rem 0;">Day Surgery & Specialist Clinics</h3>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin-bottom: 1.25rem;">Endoscopy, ophthalmology, and outpatient surgical support staffing.</p>
              <button class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;" onclick="showProposalWizard()">Scope Sector &rarr;</button>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; border-top: 4px solid #64748B;">
              <span class="card-tag">Regional</span>
              <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin: 0.75rem 0 0.5rem 0;">Regional & Rural Facilities</h3>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin-bottom: 1.25rem;">Locum clinical coverage, rotational multi-disciplinary teams, and remote clinic support.</p>
              <button class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;" onclick="showProposalWizard()">Scope Sector &rarr;</button>
            </div>
          </div>
          <!-- Regulatory Alignment Bar -->
          <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: var(--radius-lg); padding: 1.5rem; text-align: center;">
            <strong style="color: var(--voak-navy); display: block; margin-bottom: 0.5rem;">Multi-Jurisdictional Regulatory Alignment</strong>
            <p style="font-size: 0.813rem; color: var(--voak-muted); margin: 0;">Our clinical placement criteria adhere strictly to healthcare accreditation guidelines, national registration frameworks, and statutory safety mandates.</p>
          </div>
        </div>
      `;

    case 'PUB-06':
      return `
        <div style="background: #FFF; padding: 3rem 2.5rem; border-bottom: 1px solid var(--voak-border-subtle);">
          <div style="max-width: 1000px; margin: 0 auto;">
            <span class="chip chip-purple" style="margin-bottom: 0.75rem;">Healthcare Vertical 01 &bull; FR-PUB-008</span>
            <h1 style="font-family: var(--font-serif); font-size: 2.75rem; color: var(--voak-navy); margin-bottom: 1rem;">Hospitals & Health Services</h1>
            <p style="font-size: 1.15rem; color: var(--voak-muted); max-width: 720px; line-height: 1.6;">
              Strategic workforce stability and operational coordination for acute, sub-acute, and specialized hospital departments facing roster pressures.
            </p>
          </div>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 1000px; margin: 0 auto;">
          <h3 style="font-size: 1.5rem; margin-bottom: 1.5rem; color: var(--voak-navy);">Acute Sector Operational Capabilities</h3>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 3rem;">
            <div style="background: #F8FAFC; padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Hospital Pain Points Addressed</h4>
              <ul style="padding-left: 1.25rem; font-size: 0.9rem; color: var(--voak-muted); line-height: 1.8;">
                <li>Unplanned emergency department shift vacancies</li>
                <li>Nurse fatigue and overtime burnout across intensive care</li>
                <li>Lengthy agency onboarding and compliance delays</li>
                <li>Sub-optimal patient intake and discharge flow</li>
              </ul>
            </div>
            <div style="background: #F8FAFC; padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Voak Hospital Solutions</h4>
              <ul style="padding-left: 1.25rem; font-size: 0.9rem; color: var(--voak-muted); line-height: 1.8;">
                <li>Pre-credentialed, verified clinical talent pools</li>
                <li>Rapid escalation workflows for unexpected ward surges</li>
                <li>Clinical account coordination workflows [TARGET WORKFLOW &bull; PENDING CLIENT CONTRACT]</li>
                <li>Transparent, audited billing with zero hidden markup</li>
              </ul>
            </div>
          </div>
          <div style="background: var(--voak-canvas); border-radius: var(--radius-lg); padding: 2rem; display: flex; align-items: center; justify-content: space-between;">
            <div>
              <h4 style="font-size: 1.25rem; color: var(--voak-navy); margin-bottom: 0.25rem;">Consult on Hospital Roster Architecture</h4>
              <p style="color: var(--voak-muted); font-size: 0.875rem;">Schedule an operational review with our healthcare advisory team.</p>
            </div>
            <button class="btn btn-primary" onclick="showProposalWizard()">Request Scoping Discussion</button>
          </div>
        </div>
      `;

    case 'PUB-07':
      return `
        <div style="background: var(--voak-canvas); padding: 4rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-yellow); color: var(--voak-navy);">Strategic Differentiation</span>
          <h1 style="font-family: var(--font-serif); font-size: 3rem; color: var(--voak-navy); margin: 1rem 0;">Why Healthcare Leaders Choose Voak</h1>
          <p style="max-width: 680px; margin: 0 auto; color: var(--voak-muted); font-size: 1.125rem;">
            A structured comparison between Voak's proposed dedicated healthcare operational model and conventional industry staffing approaches.
          </p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 1100px; margin: 0 auto;">
          <!-- Comparison Table -->
          <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); overflow: hidden; margin-bottom: 3rem; box-shadow: var(--shadow-sm);">
            <table class="admin-table" style="margin: 0;">
              <thead>
                <tr style="background: #F8FAFC;">
                  <th style="padding: 1.25rem 1.5rem; width: 30%;">Operational Dimension</th>
                  <th style="padding: 1.25rem 1.5rem; width: 35%; color: var(--voak-purple-cta); font-weight: 700;">Proposed Voak Operational Framework</th>
                  <th style="padding: 1.25rem 1.5rem; width: 35%;">Conventional Industry Approaches</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td style="padding: 1.25rem 1.5rem;"><strong>Clinician Credentialing</strong></td>
                  <td style="padding: 1.25rem 1.5rem; background: #FAF9FE;"><span class="chip chip-success">Primary-Source Verification</span><br><small style="color: var(--voak-muted);">Primary-source validation framework against health practitioner registries [Proposed capability — pending formal business confirmation].</small></td>
                  <td style="padding: 1.25rem 1.5rem;"><span class="chip chip-warning">Standard Registry Review</span><br><small style="color: var(--voak-muted);">Variable validation depth depending on broker protocol [Industry Context &bull; General Market Survey].</small></td>
                </tr>
                <tr>
                  <td style="padding: 1.25rem 1.5rem;"><strong>Roster Continuity & SLAs</strong></td>
                  <td style="padding: 1.25rem 1.5rem; background: #FAF9FE;"><span class="chip chip-purple">Structured Commitments</span><br><small style="color: var(--voak-muted);">Proposed roster continuity framework and transparent response escalation [Proposed capability — pending formal business confirmation].</small></td>
                  <td style="padding: 1.25rem 1.5rem;"><span class="chip chip-info">Discretionary Placement</span><br><small style="color: var(--voak-muted);">Shift-by-shift fulfillment without committed continuity tiers [Industry Context &bull; General Market Survey].</small></td>
                </tr>
                <tr>
                  <td style="padding: 1.25rem 1.5rem;"><strong>Account Governance</strong></td>
                  <td style="padding: 1.25rem 1.5rem; background: #FAF9FE;"><span class="chip chip-info">Proposed Dedicated Lead</span><br><small style="color: var(--voak-muted);">Proposed named B2B coordinator and clinical advisor per facility [Proposed capability — pending formal business confirmation].</small></td>
                  <td style="padding: 1.25rem 1.5rem;"><span class="chip chip-warning">Centralized Brokerage</span><br><small style="color: var(--voak-muted);">Centralized booking desks with rotating operational contacts [Industry Context &bull; General Market Survey].</small></td>
                </tr>
                <tr>
                  <td style="padding: 1.25rem 1.5rem;"><strong>Commercial Transparency</strong></td>
                  <td style="padding: 1.25rem 1.5rem; background: #FAF9FE;"><span class="chip chip-success">Structured Fee Framework</span><br><small style="color: var(--voak-muted);">Pre-agreed institutional fee schedules [Proposed capability — pending formal business confirmation].</small></td>
                  <td style="padding: 1.25rem 1.5rem;"><span class="chip chip-warning">Variable Spot Pricing</span><br><small style="color: var(--voak-muted);">Spot-market rate adjustments during high demand periods [Industry Context &bull; General Market Survey].</small></td>
                </tr>
              </tbody>
            </table>
          </div>
          <!-- CTA -->
          <div style="text-align: center;">
            <button class="btn btn-primary" style="padding: 0.85rem 2rem; font-size: 1.05rem;" onclick="showProposalWizard()">Request Formal Comparison & RFP &rarr;</button>
          </div>
        </div>
      `;

    case 'PUB-08':
      return `
        <div style="background: var(--voak-canvas); padding: 4rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-mint); color: #FFF;">Operational Roadmap</span>
          <h1 style="font-family: var(--font-serif); font-size: 3rem; color: var(--voak-navy); margin: 1rem 0;">How We Work</h1>
          <p style="max-width: 680px; margin: 0 auto; color: var(--voak-muted); font-size: 1.125rem;">
            Our structured 4-stage operational delivery framework ensures transparent, reliable, and risk-mitigated healthcare collaboration.
          </p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 1000px; margin: 0 auto;">
          <div style="display: flex; flex-direction: column; gap: 2rem;">
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; display: flex; gap: 2rem; align-items: flex-start;">
              <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--voak-purple-cta); color: #FFF; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.25rem; flex-shrink: 0;">01</div>
              <div>
                <span class="chip chip-purple" style="margin-bottom: 0.5rem;">Diagnostic Scoping</span>
                <h3 style="font-size: 1.4rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Operational Audit & Roster Diagnosis</h3>
                <p style="color: var(--voak-muted); line-height: 1.6; margin: 0;">We analyze your facility's historical shift patterns, peak vacancy windows, clinical specialty needs, and regulatory compliance constraints to build an accurate workforce profile.</p>
              </div>
            </div>

            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; display: flex; gap: 2rem; align-items: flex-start;">
              <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--voak-pink); color: var(--voak-navy); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.25rem; flex-shrink: 0;">02</div>
              <div>
                <span class="chip chip-warning" style="margin-bottom: 0.5rem;">Architecture</span>
                <h3 style="font-size: 1.4rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Solution Architecture & Agreement</h3>
                <p style="color: var(--voak-muted); line-height: 1.6; margin: 0;">Co-designing a customized service level framework with pre-credentialed clinician rosters, escalation protocols, and transparent fee schedules.</p>
              </div>
            </div>

            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; display: flex; gap: 2rem; align-items: flex-start;">
              <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--voak-yellow); color: var(--voak-navy); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.25rem; flex-shrink: 0;">03</div>
              <div>
                <span class="chip chip-info" style="margin-bottom: 0.5rem;">Deployment</span>
                <h3 style="font-size: 1.4rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Active Roster Deployment & Onboarding</h3>
                <p style="color: var(--voak-muted); line-height: 1.6; margin: 0;">Verified clinical personnel are mobilized with facility-specific orientation, electronic timesheet integration, and shift coordinator oversight [Proposed capability — pending formal business confirmation].</p>
              </div>
            </div>

            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; display: flex; gap: 2rem; align-items: flex-start;">
              <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--voak-mint); color: var(--voak-navy); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.25rem; flex-shrink: 0;">04</div>
              <div>
                <span class="chip chip-success" style="margin-bottom: 0.5rem;">Continuous Governance</span>
                <h3 style="font-size: 1.4rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Ongoing Clinical Review & SLA Auditing</h3>
                <p style="color: var(--voak-muted); line-height: 1.6; margin: 0;">Scheduled governance meetings, shift fulfillment reviews, and clinical assessments [Proposed capability — pending formal business confirmation].</p>
              </div>
            </div>
          </div>
        </div>
      `;

    case 'PUB-09':
      return `
        <div style="background: var(--voak-canvas); padding: 4rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-sky); color: #FFF;">Clinical Governance</span>
          <h1 style="font-family: var(--font-serif); font-size: 3rem; color: var(--voak-navy); margin: 1rem 0;">Quality & Compliance Framework</h1>
          <p style="max-width: 680px; margin: 0 auto; color: var(--voak-muted); font-size: 1.125rem;">
            Rigorous clinical quality assurance, multi-tier verification protocols, and healthcare statutory compliance.
          </p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 1000px; margin: 0 auto;">
          <!-- 5-Point Credentialing Pipeline -->
          <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2.5rem; margin-bottom: 2.5rem;">
            <h3 style="font-size: 1.4rem; color: var(--voak-navy); margin-bottom: 0.5rem;">5-Point Primary-Source Credentialing Framework <span class="chip chip-warning" style="font-size: 0.65rem; padding: 2px 6px;">PROPOSED FRAMEWORK &bull; PENDING CONFIRMATION</span></h3>
            <p style="font-size: 0.813rem; color: var(--voak-muted); margin-bottom: 1.5rem;">[FRAMEWORK PARAMETERS & STATUTORY BOARD DETAILS PENDING OPERATIONAL CONFIRMATION]</p>
            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; text-align: center;">
              <div style="background: #F8FAFC; padding: 1rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
                <div style="font-weight: 800; color: var(--voak-purple-cta); font-size: 1.2rem;">01</div>
                <strong style="display: block; font-size: 0.813rem; margin: 0.25rem 0;">Identity</strong>
                <span style="font-size: 0.7rem; color: var(--voak-muted);">Identity verification framework [Proposed capability — pending formal business confirmation]</span>
              </div>
              <div style="background: #F8FAFC; padding: 1rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
                <div style="font-weight: 800; color: var(--voak-purple-cta); font-size: 1.2rem;">02</div>
                <strong style="display: block; font-size: 0.813rem; margin: 0.25rem 0;">Registration</strong>
                <span style="font-size: 0.7rem; color: var(--voak-muted);">Primary-source registration validation [Proposed capability — pending formal business confirmation]</span>
              </div>
              <div style="background: #F8FAFC; padding: 1rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
                <div style="font-weight: 800; color: var(--voak-purple-cta); font-size: 1.2rem;">03</div>
                <strong style="display: block; font-size: 0.813rem; margin: 0.25rem 0;">Clearances</strong>
                <span style="font-size: 0.7rem; color: var(--voak-muted);">Statutory background clearances [Proposed capability — pending formal business confirmation]</span>
              </div>
              <div style="background: #F8FAFC; padding: 1rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
                <div style="font-weight: 800; color: var(--voak-purple-cta); font-size: 1.2rem;">04</div>
                <strong style="display: block; font-size: 0.813rem; margin: 0.25rem 0;">Health</strong>
                <span style="font-size: 0.7rem; color: var(--voak-muted);">Immunization & clinical health clearances [Proposed capability — pending formal business confirmation]</span>
              </div>
              <div style="background: #F8FAFC; padding: 1rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
                <div style="font-weight: 800; color: var(--voak-purple-cta); font-size: 1.2rem;">05</div>
                <strong style="display: block; font-size: 0.813rem; margin: 0.25rem 0;">References</strong>
                <span style="font-size: 0.7rem; color: var(--voak-muted);">Clinical peer reference review [Proposed capability — pending formal business confirmation]</span>
              </div>
            </div>
          </div>
          <!-- Disclaimer -->
          <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: var(--radius-lg); padding: 1.5rem; font-size: 0.875rem; color: #92400E; line-height: 1.6;">
            <strong>Mandatory Governance Notice:</strong> Voak Health Care operates as an enterprise healthcare workforce and operational infrastructure partner. Patient clinical treatment decisions remain under the primary medical authority and oversight of the host healthcare facility.
          </div>
        </div>
      `;

    case 'PUB-10':
      return `
        <div style="background: var(--voak-canvas); padding: 4rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-purple-cta); color: #FFF;">Institutional Alliances</span>
          <h1 style="font-family: var(--font-serif); font-size: 3rem; color: var(--voak-navy); margin: 1rem 0;">Partners & Clients</h1>
          <p style="max-width: 680px; margin: 0 auto; color: var(--voak-muted); font-size: 1.125rem;">
            Collaborating with healthcare systems, clinical education providers, and institutional networks.
          </p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 1000px; margin: 0 auto;">
          <!-- Partnership Categories -->
          <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 3rem;">
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-lg); padding: 1.75rem;">
              <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Health System Alliances</h4>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5;">Structured workforce agreements with metropolitan and regional healthcare networks.</p>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-lg); padding: 1.75rem;">
              <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Clinical Education</h4>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5;">Affiliations supporting continuous professional development and specialized clinical training.</p>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-lg); padding: 1.75rem;">
              <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Technology Alliances</h4>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5;">Integrations with healthcare roster systems, compliance registries, and intake platforms.</p>
            </div>
          </div>

          <!-- Safe Empty State / Pending Verification Directory -->
          <div style="background: #F8FAFC; border: 2px dashed #CBD5E1; border-radius: var(--radius-xl); padding: 3rem 2rem; text-align: center; margin-bottom: 3rem;">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: #E2E8F0; margin: 0 auto 1rem auto; display: flex; align-items: center; justify-content: center; color: #64748B;">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <h4 style="font-size: 1.25rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Institutional Directory &mdash; Compliance Quarantine</h4>
            <p style="font-size: 0.875rem; color: var(--voak-muted); max-width: 540px; margin: 0 auto 1.5rem auto; line-height: 1.6;">
              Under Voak Health Care's strict corporate governance standards, institutional partner logos and client endorsements are published only after formal written release execution. Anonymized partner capability briefs are available to credentialed procurement teams upon request.
            </p>
            <button class="btn btn-primary" onclick="inspectPage('PUB-18')">Inquire About Institutional Partnership (FORM-PUB-02)</button>
          </div>
        </div>
      `;

    case 'PUB-11':
      return `
        <div style="background: var(--voak-canvas); padding: 4rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-pink); color: var(--voak-navy);">Demonstrated Impact</span>
          <h1 style="font-family: var(--font-serif); font-size: 3rem; color: var(--voak-navy); margin: 1rem 0;">Operational Case Studies</h1>
          <p style="max-width: 680px; margin: 0 auto; color: var(--voak-muted); font-size: 1.125rem;">
            Real-world evidence of how Voak Health Care stabilizes hospital rosters, maintains compliance, and mitigates staffing risk.
          </p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 1100px; margin: 0 auto;">
          <!-- Compliance Quarantine Notice for Case Studies -->
          <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 2.5rem; text-align: left;">
            <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
              <span class="chip chip-warning" style="flex-shrink: 0;">CONTENT PENDING APPROVAL</span>
              <p style="margin: 0; font-size: 0.875rem; color: #92400E; line-height: 1.5;">
                <strong>Governance & Privacy Notice:</strong> In accordance with corporate compliance rules and client confidentiality covenants, formal case studies, institutional identifiers, and quantitative outcome statistics are published only following executed bilateral release deeds. The cards below illustrate the canonical Problem-Intervention-Outcome format required by Phase 1 (FR-PUB-013).
              </p>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-bottom: 3rem;">
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; display: flex; flex-direction: column;">
              <span class="chip chip-warning" style="align-self: flex-start; margin-bottom: 0.75rem;">ILLUSTRATIVE TEMPLATE &bull; PENDING RELEASE</span>
              <h3 style="font-size: 1.3rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Critical Care Shift Stabilization</h3>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5; margin-bottom: 1rem;"><strong>Challenge:</strong> Roster vacancy across intensive care units during seasonal admissions surge.</p>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5; margin-bottom: 1rem;"><strong>Intervention:</strong> Rapid deployment of pre-credentialed critical care nurse specialists within agreed transition window.</p>
              <div style="background: #F8FAFC; border: 1px dashed #CBD5E1; padding: 0.75rem; border-radius: var(--radius-sm); font-size: 0.813rem; color: #475569; margin-top: auto;">
                <strong>Outcome Framework:</strong> Shift fulfillment metric verified [PENDING FORMAL CLIENT DATA RELEASE].
              </div>
            </div>

            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; display: flex; flex-direction: column;">
              <span class="chip chip-warning" style="align-self: flex-start; margin-bottom: 0.75rem;">ILLUSTRATIVE TEMPLATE &bull; PENDING RELEASE</span>
              <h3 style="font-size: 1.3rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Emergency Department Continuity</h3>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5; margin-bottom: 1rem;"><strong>Challenge:</strong> Healthcare facility facing emergency triage continuity challenges due to staffing transitions.</p>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5; margin-bottom: 1rem;"><strong>Intervention:</strong> Structured rotational clinical roster deployed with dedicated emergency coordinators.</p>
              <div style="background: #F8FAFC; border: 1px dashed #CBD5E1; padding: 0.75rem; border-radius: var(--radius-sm); font-size: 0.813rem; color: #475569; margin-top: auto;">
                <strong>Outcome Framework:</strong> Continuous emergency support capability maintained [SIMULATED CASE STUDY &bull; CLIENT DATA PENDING APPROVAL].
              </div>
            </div>

            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; display: flex; flex-direction: column;">
              <span class="chip chip-warning" style="align-self: flex-start; margin-bottom: 0.75rem;">ILLUSTRATIVE TEMPLATE &bull; PENDING RELEASE</span>
              <h3 style="font-size: 1.3rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Compliance Audit Turnaround</h3>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5; margin-bottom: 1rem;"><strong>Challenge:</strong> Facility requiring clinical documentation and credentialing governance alignment.</p>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5; margin-bottom: 1rem;"><strong>Intervention:</strong> Clinical audit team deployed to conduct comprehensive file reviews and roster corrections.</p>
              <div style="background: #F8FAFC; border: 1px dashed #CBD5E1; padding: 0.75rem; border-radius: var(--radius-sm); font-size: 0.813rem; color: #475569; margin-top: auto;">
                <strong>Outcome Framework:</strong> Quality compliance verification verified [PENDING FORMAL CLIENT DATA RELEASE].
              </div>
            </div>
          </div>
        </div>
      `;

    case 'PUB-12':
      return `
        <div style="background: var(--voak-canvas); padding: 4rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-yellow); color: var(--voak-navy);">Clinical Careers</span>
          <h1 style="font-family: var(--font-serif); font-size: 3rem; color: var(--voak-navy); margin: 1rem 0;">Join Our Healthcare Network</h1>
          <p style="max-width: 680px; margin: 0 auto; color: var(--voak-muted); font-size: 1.125rem;">
            Practice with clinical autonomy, premium professional remuneration, and dedicated operational support.
          </p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 1000px; margin: 0 auto;">
          <!-- Value Cards -->
          <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 3rem;">
            <div style="background: #FFF; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--voak-border-subtle);">
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Flexible Rostering</h4>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5;">Choose shift schedules that align with your lifestyle across acute, aged care, and community facilities.</p>
            </div>
            <div style="background: #FFF; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--voak-border-subtle);">
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">CPD & Career Growth</h4>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5;">Subsidized clinical education, advanced life support certifications, and peer mentoring.</p>
            </div>
            <div style="background: #FFF; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--voak-border-subtle);">
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Clinical Liaison Support</h4>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5;">Structured clinical coordination pathways to support your facility placements during operational hours [24/7 SUPPORT PENDING APPROVAL].</p>
            </div>
          </div>

          <!-- Open Vacancies List -->
          <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: var(--radius-sm); padding: 0.75rem 1rem; margin-bottom: 1.5rem; font-size: 0.813rem; color: #1E40AF; line-height: 1.5;">
            <strong>[DEMO VACANCY — NOT LIVE]:</strong> Clinical positions shown below are simulated role templates for recruitment UI/UX inspection. Active clinical requisitions are pending formal client onboarding.
          </div>
          <h3 style="font-size: 1.4rem; color: var(--voak-navy); margin-bottom: 1.5rem;">Clinical Role Templates (Illustrative)</h3>
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-md); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
              <div>
                <span class="chip chip-purple" style="font-size: 0.7rem; margin-bottom: 0.25rem;">Acute Hospital Network</span>
                <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin: 0.25rem 0;">Senior Clinical Nurse Specialist (ICU / Emergency)</h4>
                <div style="font-size: 0.813rem; color: var(--voak-muted);">Full-time / Rotational Shifts &bull; <strong style="color: var(--voak-purple-cta);">[DEMO VACANCY — NOT LIVE]</strong> Reference: DEMO-REF-01</div>
              </div>
              <button class="btn btn-primary" onclick="inspectPage('PUB-13')">View Role & Apply &rarr;</button>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-md); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
              <div>
                <span class="chip chip-warning" style="font-size: 0.7rem; margin-bottom: 0.25rem;">Aged Care Services</span>
                <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin: 0.25rem 0;">Clinical Care Coordinator (Geriatric Nursing)</h4>
                <div style="font-size: 0.813rem; color: var(--voak-muted);">Permanent Placement &bull; <strong style="color: var(--voak-purple-cta);">[DEMO VACANCY — NOT LIVE]</strong> Reference: DEMO-REF-02</div>
              </div>
              <button class="btn btn-outline" onclick="inspectPage('PUB-13')">View Role & Apply &rarr;</button>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-md); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
              <div>
                <span class="chip chip-info" style="font-size: 0.7rem; margin-bottom: 0.25rem;">Allied Health</span>
                <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin: 0.25rem 0;">Senior Occupational Therapist / Rehabilitation Lead</h4>
                <div style="font-size: 0.813rem; color: var(--voak-muted);">Flexible Days &bull; <strong style="color: var(--voak-purple-cta);">[DEMO VACANCY — NOT LIVE]</strong> Reference: DEMO-REF-03</div>
              </div>
              <button class="btn btn-outline" onclick="inspectPage('PUB-13')">View Role & Apply &rarr;</button>
            </div>
          </div>
        </div>
      `;

    case 'PUB-13':
      return `
        <div style="background: #FFF; padding: 3rem 2.5rem; border-bottom: 1px solid var(--voak-border-subtle);">
          <div style="max-width: 900px; margin: 0 auto;">
            <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: var(--radius-sm); padding: 0.5rem 0.75rem; margin-bottom: 0.75rem; font-size: 0.75rem; color: #1E40AF;">
              <strong>[DEMO VACANCY — NOT LIVE]:</strong> This vacancy profile is an illustrative role specification for FORM-PUB-04 candidate application testing.
            </div>
            <span class="preview-badge" style="background: var(--voak-mint); color: #FFF;">Clinical Nurse Specialist (ICU / Acute)</span>
            <h1 style="font-family: var(--font-serif); font-size: 2.75rem; color: var(--voak-navy); margin: 0.75rem 0;">Senior Clinical Nurse Specialist</h1>
            <p style="font-size: 1rem; color: var(--voak-muted);">Sector: Acute Hospital Networks &bull; Type: Full-time / Shift Roster &bull; Reference: DEMO-REF-01</p>
          </div>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 900px; margin: 0 auto;">
          <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); padding: 2rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-sm);">
            <span class="preview-badge" style="background: var(--voak-navy); color: #FFF; margin-bottom: 0.5rem;">FORM-PUB-04</span>
            <h3 style="font-size: 1.4rem; color: var(--voak-navy); margin-bottom: 1.5rem;">Candidate Application Form</h3>
            <form onsubmit="event.preventDefault(); alert('Demo submission received. Application logged to HR Vault.');">
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                  <label class="form-label form-label-required">First Name</label>
                  <input type="text" class="form-control" placeholder="Alex" required>
                </div>
                <div class="form-group">
                  <label class="form-label form-label-required">Last Name</label>
                  <input type="text" class="form-control" placeholder="Taylor" required>
                </div>
              </div>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                  <label class="form-label form-label-required">Personal Email</label>
                  <input type="email" class="form-control" placeholder="applicant.demo@example.invalid" required>
                </div>
                <div class="form-group">
                  <label class="form-label form-label-required">Phone (International Format)</label>
                  <input type="tel" class="form-control" placeholder="+61 412 345 678" required>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Professional Registration Number (Optional / Jurisdiction Specific)</label>
                <input type="text" class="form-control" placeholder="e.g. Health Practitioner / Medical Registration ID [Subject to Confirmed Jurisdiction]">
              </div>
              <div class="form-group">
                <label class="form-label form-label-required">Upload Resume / CV (PDF, DOCX &bull; Max 10MB)</label>
                <input type="file" class="form-control" required>
              </div>
              <div class="form-group">
                <label style="font-size: 0.813rem; display: flex; align-items: center; gap: 0.5rem; color: var(--voak-muted);">
                  <input type="checkbox" required> I certify the credentials provided are accurate and consent to recruitment background verification.
                </label>
              </div>
              <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Clinical Application (FORM-PUB-04) &rarr;</button>
            </form>
          </div>
        </div>
      `;

    case 'PUB-14':
      return `
        <div style="background: var(--voak-canvas); padding: 4rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-sky); color: #FFF;">Thought Leadership</span>
          <h1 style="font-family: var(--font-serif); font-size: 3rem; color: var(--voak-navy); margin: 1rem 0;">Healthcare Insights & Analysis</h1>
          <p style="max-width: 680px; margin: 0 auto; color: var(--voak-muted); font-size: 1.125rem;">
            Evidence-based perspectives on healthcare operational resilience, clinical workforce policy, and governance.
          </p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 1100px; margin: 0 auto;">
          <!-- Category Filters -->
          <div style="display: flex; gap: 0.5rem; margin-bottom: 2.5rem; justify-content: center; flex-wrap: wrap;">
            <button class="chip chip-purple" style="padding: 0.5rem 1rem; cursor: pointer;">All Insights</button>
            <button class="chip" style="padding: 0.5rem 1rem; background: #F1F5F9; cursor: pointer;">Workforce Strategy</button>
            <button class="chip" style="padding: 0.5rem 1rem; background: #F1F5F9; cursor: pointer;">Clinical Governance</button>
            <button class="chip" style="padding: 0.5rem 1rem; background: #F1F5F9; cursor: pointer;">Health Policy</button>
          </div>

          <!-- Featured Article -->
          <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-2xl); overflow: hidden; margin-bottom: 3rem; box-shadow: var(--shadow-sm); display: grid; grid-template-columns: 1fr 1fr;">
            <div style="padding: 3rem; display: flex; flex-direction: column; justify-content: center;">
              <span class="chip chip-purple" style="align-self: flex-start; margin-bottom: 0.75rem;">Featured Editorial</span>
              <h2 style="font-family: var(--font-serif); font-size: 1.85rem; color: var(--voak-navy); margin-bottom: 1rem; line-height: 1.25;">Optimizing Healthcare Workforce Models in 2026</h2>
              <p style="color: var(--voak-muted); line-height: 1.6; font-size: 0.938rem; margin-bottom: 1.5rem;">
                An in-depth analysis of roster surge dynamics, clinician retention strategies, and the transition from ad-hoc agency spend to embedded partner governance.
              </p>
              <div style="display: flex; align-items: center; justify-content: space-between; margin-top: auto;">
                <span style="font-size: 0.813rem; color: var(--voak-muted);">Voak Editorial Desk [SIMULATED AUTHOR — DEMO ONLY] &bull; 6 min read</span>
                <button class="btn btn-primary" onclick="inspectPage('PUB-15')">Read Analysis &rarr;</button>
              </div>
            </div>
            <div style="background: var(--voak-canvas); display: flex; align-items: center; justify-content: center; padding: 2rem;">
              <div style="width: 80%; height: 240px; background: #FFF; border-radius: var(--radius-lg); border: 1px solid var(--voak-border-subtle); padding: 1.5rem; display: flex; flex-direction: column; justify-content: center;">
                <strong style="color: var(--voak-navy); font-size: 1.1rem; margin-bottom: 0.5rem;">Key Takeaway Preview:</strong>
                <p style="font-size: 0.813rem; color: var(--voak-muted); margin: 0; line-height: 1.5;">"Facilities adopting dedicated clinical partner frameworks experience lower vacancy volatility [ILLUSTRATIVE COMMENTARY &bull; RESEARCH HYPOTHESIS]."</p>
              </div>
            </div>
          </div>

          <!-- Newsletter Proposed Box -->
          <div style="background: #FAF5FF; border: 1px solid #E9D5FF; border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
            <div>
              <span class="chip chip-warning" style="font-size: 0.7rem; margin-bottom: 0.25rem;">[PROPOSED &bull; PENDING AUTHORIZATION]</span>
              <h4 style="font-size: 1.1rem; color: #581C87; margin: 0.25rem 0;">Subscribe to Quarterly Healthcare Executive Briefings</h4>
              <p style="font-size: 0.813rem; color: #7E22CE; margin: 0;">Curated clinical workforce analysis delivered directly to healthcare leadership desks.</p>
            </div>
            <button class="btn btn-outline" style="border-color: #7E22CE; color: #7E22CE; white-space: nowrap;" onclick="alert('Newsletter subscription feature is currently PROPOSED and awaiting client authorization.')">Join Proposed Dispatch</button>
          </div>
        </div>
      `;

    case 'PUB-15':
      return `
        <div style="background: #FFF; padding: 3.5rem 2.5rem; border-bottom: 1px solid var(--voak-border-subtle);">
          <div style="max-width: 800px; margin: 0 auto;">
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
              <span class="chip chip-purple">Workforce Strategy</span>
              <span class="chip" style="background: #F1F5F9;">6 Min Read</span>
            </div>
            <h1 style="font-family: var(--font-serif); font-size: 2.75rem; color: var(--voak-navy); margin-bottom: 1rem; line-height: 1.2;">
              Optimizing Healthcare Workforce Models in 2026: From Ad-Hoc Spend to Institutional Partnership
            </h1>
            <div style="display: flex; align-items: center; gap: 1rem; padding-top: 1rem; border-top: 1px solid var(--voak-border-subtle);">
              <div style="width: 44px; height: 44px; border-radius: 50%; background: var(--voak-purple-cta); color: #FFF; display: flex; align-items: center; justify-content: center; font-weight: 700;">ED</div>
              <div>
                <strong style="color: var(--voak-navy); font-size: 0.938rem;">Voak Editorial Desk [SIMULATED AUTHOR — DEMO ONLY]</strong>
                <div style="font-size: 0.813rem; color: var(--voak-muted);">Clinical Operations Analysis &bull; Published October 2026</div>
              </div>
            </div>
          </div>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 800px; margin: 0 auto;">
          <div style="font-size: 1.05rem; line-height: 1.8; color: var(--voak-navy); margin-bottom: 2rem;">
            <p style="margin-bottom: 1.5rem;">
              Modern hospital networks and healthcare providers face unprecedented volatility in clinical rostering. Traditional approaches&mdash;relying heavily on fragmented, transactional locum agencies during surge periods&mdash;have introduced severe budgetary inflation and heightened clinical governance risks.
            </p>
            <div style="background: var(--voak-canvas); border-left: 4px solid var(--voak-purple-cta); padding: 1.5rem; border-radius: 0 var(--radius-md) var(--radius-md) 0; margin: 2rem 0; font-family: var(--font-serif); font-size: 1.25rem; font-style: italic;">
              "Workforce resilience is not achieved by buying more ad-hoc agency shifts; it is created by building embedded, audited clinical pipelines with transparent operational SLAs."
            </div>
            <h3 style="font-size: 1.5rem; color: var(--voak-navy); margin: 2rem 0 1rem 0;">1. The True Cost of Roster Instability</h3>
            <p style="margin-bottom: 1.5rem;">
              When acute hospital wards operate with persistent shift gaps, patient intake slows, nurse-to-patient ratios become strained, and clinical overtime accelerates staff turnover. Facilities require structured partners that provide pre-credentialed talent pools rather than spot brokerage.
            </p>
            <h3 style="font-size: 1.5rem; color: var(--voak-navy); margin: 2rem 0 1rem 0;">2. Primary-Source Credentialing as a Clinical Safeguard</h3>
            <p style="margin-bottom: 1.5rem;">
              Ensuring that every deployed nurse, specialist, and allied health professional has undergone comprehensive primary-source registration validation is essential for maintaining hospital accreditation and patient safety.
            </p>
          </div>
          <div style="padding-top: 2rem; border-top: 1px solid var(--voak-border-subtle); display: flex; align-items: center; justify-content: space-between;">
            <button class="btn btn-outline" onclick="inspectPage('PUB-14')">&larr; Back to All Insights</button>
            <button class="btn btn-primary" onclick="showProposalWizard()">Consult on Workforce Strategy &rarr;</button>
          </div>
        </div>
      `;

    case 'PUB-16':
      return `
        <div style="background: var(--voak-canvas); padding: 4rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-yellow); color: var(--voak-navy);">Support & Advisory</span>
          <h1 style="font-family: var(--font-serif); font-size: 3rem; color: var(--voak-navy); margin: 1rem 0;">Frequently Asked Questions</h1>
          <p style="max-width: 680px; margin: 0 auto; color: var(--voak-muted); font-size: 1.125rem;">
            Answers to common questions regarding clinical workforce deployment, compliance, and B2B contracting.
          </p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 900px; margin: 0 auto;">
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-lg); padding: 1.5rem;">
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">How quickly can clinical staff be deployed to a healthcare facility?</h4>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin: 0;">
                For institutional partners with established service agreements, surge deployment response parameters are defined in formal Master Service Agreements [DEPLOYMENT TIMELINES PENDING CLIENT CONTRACT CONFIRMATION].
              </p>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-lg); padding: 1.5rem;">
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">How are clinician credentials and registrations verified?</h4>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin: 0;">
                Clinicians are evaluated through professional registration verification, statutory clearances, health screening compliance, and clinical reference reviews [AUDIT PROTOCOLS PENDING APPROVAL].
              </p>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-lg); padding: 1.5rem;">
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">What commercial contract structures do you offer?</h4>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin: 0;">
                Proposed B2B engagement models include Master Service Agreements (MSA), rotational surge cover frameworks, and facility managed services [Proposed capability — pending formal business confirmation].
              </p>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-lg); padding: 1.5rem;">
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">How are clinical timesheets and billing managed?</h4>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin: 0;">
                Proposed billing framework: Timesheet verification against facility shift logs and itemized invoicing with audit records [Proposed capability — pending formal business confirmation].
              </p>
            </div>
          </div>
          <!-- Contact fallback -->
          <div style="margin-top: 3rem; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: var(--radius-lg); padding: 2rem; text-align: center;">
            <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Have a Question Not Answered Here?</h4>
            <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1.25rem;">Our corporate triage desk assists healthcare executives with specific operational inquiries.</p>
            <button class="btn btn-primary" onclick="inspectPage('PUB-17')">Contact Operations Desk (FORM-PUB-01)</button>
          </div>
        </div>
      `;

    case 'PUB-17':
      return `
        <div style="background: var(--voak-canvas); padding: 3.5rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-yellow); color: var(--voak-navy);">Contact & Triage</span>
          <h1 style="font-family: var(--font-serif); font-size: 2.75rem; color: var(--voak-navy); margin: 0.75rem 0;">Inbound Corporate Triage Desk</h1>
          <p style="max-width: 600px; margin: 0 auto; color: var(--voak-muted);">Direct communication channel for healthcare organizations, providers, and inquiries.</p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 800px; margin: 0 auto;">
          <div style="background: #FFF; padding: 2.5rem; border-radius: var(--radius-xl); border: 1px solid var(--voak-border-subtle); box-shadow: var(--shadow-sm);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--voak-border-subtle);">
              <div>
                <span class="preview-badge" style="background: var(--voak-purple-cta); color: #FFF;">FORM-PUB-01</span>
                <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin-top: 0.25rem;">General Corporate Contact Form</h3>
              </div>
              <span style="font-size: 0.75rem; color: var(--voak-muted);">International Formats Supported</span>
            </div>
            <form onsubmit="event.preventDefault(); alert('Demo submission received. Reference: INQ-2026-DEMO');">
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                  <label class="form-label form-label-required">Full Name</label>
                  <input type="text" class="form-control" placeholder="Dr. Jane Smith" required>
                </div>
                <div class="form-group">
                  <label class="form-label form-label-required">Organization Name</label>
                  <input type="text" class="form-control" placeholder="Healthcare System / Hospital" required>
                </div>
              </div>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                  <label class="form-label form-label-required">Corporate Work Email</label>
                  <input type="email" class="form-control" placeholder="inquiry.demo@example.invalid" required>
                </div>
                <div class="form-group">
                  <label class="form-label form-label-required">Phone (International Format)</label>
                  <input type="tel" class="form-control" placeholder="+61 400 123 456 or +1 555 123 4567" required>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label form-label-required">Inquiry Category</label>
                <select class="form-control">
                  <option>Clinical Workforce & Staffing</option>
                  <option>Strategic Partnership Discussion</option>
                  <option>Healthcare Operations BPO</option>
                  <option>Clinical Governance & Compliance</option>
                  <option>Careers & Recruitment</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label form-label-required">Message</label>
                <textarea class="form-control" rows="4" placeholder="Please describe your facility's operational requirements..." required></textarea>
              </div>
              <div class="form-group">
                <label style="font-size: 0.813rem; display: flex; align-items: center; gap: 0.5rem; color: var(--voak-muted);">
                  <input type="checkbox" required> I consent to Voak Health Care handling this inquiry according to the Privacy Policy.
                </label>
              </div>
              <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Submit Corporate Inquiry &rarr;</button>
            </form>
          </div>
        </div>
      `;

    case 'PUB-18':
      return `
        <div style="background: var(--voak-canvas); padding: 3.5rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-purple-cta); color: #FFF;">Strategic Alliances</span>
          <h1 style="font-family: var(--font-serif); font-size: 2.75rem; color: var(--voak-navy); margin: 0.75rem 0;">Partner With Voak Health Care</h1>
          <p style="max-width: 600px; margin: 0 auto; color: var(--voak-muted);">Dedicated portal for healthcare networks, hospital groups, and institutional providers.</p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 800px; margin: 0 auto;">
          <div style="background: #FFF; padding: 2.5rem; border-radius: var(--radius-xl); border: 1px solid var(--voak-border-subtle);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--voak-border-subtle);">
              <div>
                <span class="preview-badge" style="background: var(--voak-purple-cta); color: #FFF;">FORM-PUB-02</span>
                <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin-top: 0.25rem;">Strategic Partnership Intake Form</h3>
              </div>
              <span style="font-size: 0.75rem; color: var(--voak-muted);">Executive Triage Gate</span>
            </div>
            <form onsubmit="event.preventDefault(); alert('Partnership inquiry logged. Ref: PTN-2026-DEMO');">
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                  <label class="form-label form-label-required">Executive Name</label>
                  <input type="text" class="form-control" placeholder="Sarah Mitchell" required>
                </div>
                <div class="form-group">
                  <label class="form-label form-label-required">Executive Title</label>
                  <input type="text" class="form-control" placeholder="Director of Clinical Services" required>
                </div>
              </div>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                  <label class="form-label form-label-required">Health Network / Enterprise</label>
                  <input type="text" class="form-control" placeholder="Regional Health District" required>
                </div>
                <div class="form-group">
                  <label class="form-label form-label-required">Estimated Facility Scale</label>
                  <select class="form-control">
                    <option>Single Regional Facility</option>
                    <option>2 to 5 Facilities</option>
                    <option>6 to 15 Facilities</option>
                    <option>Statewide / Multi-Region Enterprise</option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label form-label-required">Corporate Work Email</label>
                <input type="email" class="form-control" placeholder="partner.demo@example.invalid" required>
              </div>
              <div class="form-group">
                <label class="form-label form-label-required">Collaboration Overview</label>
                <textarea class="form-control" rows="4" placeholder="Detail proposed strategic scope, clinical workforce integration, or facility operational goals..." required></textarea>
              </div>
              <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Strategic Partnership Proposal &rarr;</button>
            </form>
          </div>
        </div>
      `;

    case 'PUB-19':
      return `
        <div style="background: var(--voak-canvas); padding: 4rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-sky); color: #FFF;">FORM-PUB-03 &bull; Enterprise RFP Desk</span>
          <h1 style="font-family: var(--font-serif); font-size: 3rem; color: var(--voak-navy); margin: 1rem 0;">Request a Structured Proposal</h1>
          <p style="max-width: 680px; margin: 0 auto 2.5rem auto; color: var(--voak-muted); font-size: 1.125rem;">
            Scope workforce needs, specify clinical requirements, and securely upload tender specifications via our multi-step RFP engine.
          </p>
          <button class="btn btn-primary" style="padding: 0.85rem 2rem; font-size: 1.05rem;" onclick="showProposalWizard()">
            Launch Multi-Step RFP Wizard (FORM-PUB-03) &rarr;
          </button>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 960px; margin: 0 auto;">
          <h3 style="font-size: 1.4rem; color: var(--voak-navy); margin-bottom: 1.5rem; text-align: center;">4-Stage RFP Scoping Methodology</h3>
          <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
            <div style="background: #FFF; border: 1px solid #E2E8F0; padding: 1.25rem; border-radius: var(--radius-md); text-align: center;">
              <div style="font-weight: 800; color: var(--voak-purple-cta); font-size: 1.25rem; margin-bottom: 0.25rem;">01</div>
              <strong style="display:block; font-size: 0.9rem; margin-bottom: 0.25rem;">Facility Profile</strong>
              <span style="font-size: 0.75rem; color: var(--voak-muted);">Entity type, jurisdiction, and lead contacts.</span>
            </div>
            <div style="background: #FFF; border: 1px solid #E2E8F0; padding: 1.25rem; border-radius: var(--radius-md); text-align: center;">
              <div style="font-weight: 800; color: var(--voak-purple-cta); font-size: 1.25rem; margin-bottom: 0.25rem;">02</div>
              <strong style="display:block; font-size: 0.9rem; margin-bottom: 0.25rem;">Service Scope</strong>
              <span style="font-size: 0.75rem; color: var(--voak-muted);">Specialized clinical skills & shift scale.</span>
            </div>
            <div style="background: #FFF; border: 1px solid #E2E8F0; padding: 1.25rem; border-radius: var(--radius-md); text-align: center;">
              <div style="font-weight: 800; color: var(--voak-purple-cta); font-size: 1.25rem; margin-bottom: 0.25rem;">03</div>
              <strong style="display:block; font-size: 0.9rem; margin-bottom: 0.25rem;">Procurement</strong>
              <span style="font-size: 0.75rem; color: var(--voak-muted);">Target start dates & budget parameters.</span>
            </div>
            <div style="background: #FFF; border: 1px solid #E2E8F0; padding: 1.25rem; border-radius: var(--radius-md); text-align: center;">
              <div style="font-weight: 800; color: var(--voak-purple-cta); font-size: 1.25rem; margin-bottom: 0.25rem;">04</div>
              <strong style="display:block; font-size: 0.9rem; margin-bottom: 0.25rem;">Tender Upload</strong>
              <span style="font-size: 0.75rem; color: var(--voak-muted);">Quarantined PDF/DOCX tender specs.</span>
            </div>
          </div>
        </div>
      `;

    case 'PUB-20':
      return `
        <div style="background: #FFF; padding: 3.5rem 2.5rem; border-bottom: 1px solid var(--voak-border-subtle);">
          <div style="max-width: 800px; margin: 0 auto;">
            <span class="chip chip-purple" style="margin-bottom: 0.75rem;">Legal & Compliance &bull; FR-PUB-022</span>
            <h1 style="font-family: var(--font-serif); font-size: 2.75rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Privacy Policy</h1>
            <p style="color: var(--voak-muted); font-size: 0.875rem;">Last Updated: October 2026 &bull; <span class="chip chip-warning" style="font-size: 0.7rem;">STATUTORY PRIVACY POLICIES PENDING JURISDICTION CONFIRMATION</span></p>
          </div>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 800px; margin: 0 auto; line-height: 1.7; color: var(--voak-navy);">
          <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: var(--radius-sm); padding: 0.75rem 1rem; margin-bottom: 1.5rem; font-size: 0.8rem; color: #92400E; line-height: 1.5;">
            <strong>DRAFT POLICY NOTICE [PENDING FORMAL LEGAL REVIEW]:</strong> This draft privacy policy outlines proposed operational guidelines for review prior to commercial launch. Statutory compliance commitments, data controller identity, and infrastructure access controls will be confirmed and legally verified during application build (Phases 4–31).
          </div>
          <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin-bottom: 0.75rem;">1. Proposed Healthcare Privacy Charter</h3>
          <p style="color: var(--voak-muted); margin-bottom: 1.5rem;">
            Voak Health Care is committed to protecting the privacy of healthcare professionals, institutional partner representatives, and visitors. Detailed data handling policies are under development and subject to jurisdiction-specific legal review.
          </p>
          <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin-bottom: 0.75rem;">2. Proposed Clinical Candidate Data Handling [ARCHITECTURE SPECIFICATION]</h3>
          <p style="color: var(--voak-muted); margin-bottom: 1.5rem;">
            Under the proposed Phase 14 architecture, candidate recruitment information (resumes, registrations) submitted via FORM-PUB-04 is designed to be stored in an isolated, access-restricted repository upon implementation. Physical storage infrastructure and access controls will be audited during backend development.
          </p>
          <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin-bottom: 0.75rem;">3. Data Boundary Scope [PROPOSED SPECIFICATION]</h3>
          <p style="color: var(--voak-muted); margin-bottom: 1.5rem;">
            The planned scope of the public website infrastructure is limited to corporate information, enterprise procurement intake, and recruitment inquiries. The platform design does not contemplate patient health record storage. Production technical boundaries remain subject to architecture verification.
          </p>
          <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: var(--radius-md); padding: 1.25rem; font-size: 0.813rem; color: #64748B;">
            <strong>Data Controller Variable:</strong> Entity name and physical registered office are CMS-configurable variables subject to formal corporate confirmation.
          </div>
        </div>
      `;

    case 'PUB-21':
      return `
        <div style="background: #FFF; padding: 3.5rem 2.5rem; border-bottom: 1px solid var(--voak-border-subtle);">
          <div style="max-width: 800px; margin: 0 auto;">
            <span class="chip chip-purple" style="margin-bottom: 0.75rem;">Commercial Terms &bull; FR-PUB-022</span>
            <h1 style="font-family: var(--font-serif); font-size: 2.75rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Terms & Conditions</h1>
            <p style="color: var(--voak-muted); font-size: 0.875rem;">Commercial B2B Terms of Use &bull; Effective October 2026</p>
          </div>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 800px; margin: 0 auto; line-height: 1.7; color: var(--voak-navy);">
          <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin-bottom: 0.75rem;">1. Master Website Conditions</h3>
          <p style="color: var(--voak-muted); margin-bottom: 1.5rem;">
            Access to and use of this corporate portal is governed by these Terms & Conditions. By navigating this website or submitting proposals, users agree to comply with all applicable commercial laws.
          </p>
          <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin-bottom: 0.75rem;">2. Non-Clinical Advisory Disclaimer</h3>
          <p style="color: var(--voak-muted); margin-bottom: 1.5rem;">
            Content provided across this platform represents general B2B healthcare operational information and workforce capability descriptions. It does not constitute direct medical advice or formal clinical diagnostic direction.
          </p>
          <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin-bottom: 0.75rem;">3. Governing Law & Jurisdiction</h3>
          <p style="color: var(--voak-muted); margin-bottom: 1.5rem;">
            Governing law jurisdiction is parameterized through CMS variables and will be finalized upon corporate registry confirmation prior to commercial execution.
          </p>
        </div>
      `;

    case 'PUB-22':
      return `
        <div style="background: var(--voak-canvas); padding: 5rem 2.5rem; text-align: center;">
          <div style="width: 72px; height: 72px; border-radius: 50%; background: #FEE2E2; color: #DC2626; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; margin: 0 auto 1.5rem auto;">404</div>
          <span class="preview-badge" style="background: #DC2626; color: #FFF;">System Error Boundary &bull; FR-PUB-023</span>
          <h1 style="font-family: var(--font-serif); font-size: 3rem; color: var(--voak-navy); margin: 1rem 0;">Page Not Found</h1>
          <p style="max-width: 580px; margin: 0 auto 2rem auto; color: var(--voak-muted); font-size: 1.125rem;">
            The clinical operational resource or page requested could not be located. It may have been relocated or updated.
          </p>
          <div style="display: flex; gap: 1rem; justify-content: center; margin-bottom: 2.5rem;">
            <button class="btn btn-primary" onclick="inspectPage('PUB-01')">Return to Homepage &rarr;</button>
            <button class="btn btn-outline" onclick="inspectPage('PUB-03')">Browse Services Catalog</button>
          </div>
          <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-md); padding: 1rem; max-width: 480px; margin: 0 auto; font-size: 0.813rem; color: #64748B;">
            Diagnostic Reference ID: <code>VOAK-ERR-404-SAMPLE</code> &bull; Zero stack trace exposed
          </div>
        </div>
      `;

    default:
      return `<div style="padding: 3rem; text-align: center;">Page loaded</div>`;
  }
}

/* --------------------------------------------------------------------------
   5. ALL 19 ADMIN MODULES DISPLAY & WORKFLOWS
   -------------------------------------------------------------------------- */
const ADMIN_MODULES = {
  'ADM-01': {
    title: 'ADM-01: Executive Dashboard & Operational Telemetry',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_WEBSITE_ADMIN', 'ROLE_CONTENT_EDITOR', 'ROLE_BD_MANAGER', 'ROLE_HR_MANAGER'],
    render: () => `
      <div class="admin-kpi-grid">
        <div class="admin-kpi-card">
          <div class="kpi-label">Active Clinical Deployments (Demo)</div>
          <div class="kpi-value">24</div>
          <div class="kpi-trend up">&bull; Simulated operational active roster</div>
        </div>
        <div class="admin-kpi-card">
          <div class="kpi-label">Inbound Contact Inquiries (Demo)</div>
          <div class="kpi-value">12</div>
          <div class="kpi-trend neutral">&bull; 4 awaiting triage review</div>
        </div>
        <div class="admin-kpi-card">
          <div class="kpi-label">Clinical Audit Status (Demo)</div>
          <div class="kpi-value" style="color: var(--state-success);">Satisfactory</div>
          <div class="kpi-trend up">&bull; Routine compliance verification</div>
        </div>
        <div class="admin-kpi-card">
          <div class="kpi-label">Open Proposals / RFPs (Demo)</div>
          <div class="kpi-value">7</div>
          <div class="kpi-trend up">&bull; Active commercial reviews</div>
        </div>
      </div>

      <!-- Live Queue -->
      <div class="admin-card">
        <div class="admin-card-header">
          <div>
            <span class="preview-badge" style="background: var(--voak-purple-cta); margin-bottom: 0.25rem;">Demo Triage Queue</span>
            <h3 class="admin-card-title">Recent Inquiries & RFP Submissions</h3>
          </div>
          <button class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.813rem;" onclick="showAdminModule('ADM-09')">Manage Full Queue</button>
        </div>

        <table class="admin-table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Healthcare Organization</th>
              <th>Category</th>
              <th>Submission Date</th>
              <th>Status</th>
              <th>Assigned Owner</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>INQ-2026-041</code></td>
              <td><strong>Regional Medical Center (Demo)</strong></td>
              <td>Clinical Workforce</td>
              <td>Today, 08:30</td>
              <td><span class="chip chip-warning">Triage Pending</span></td>
              <td>[SIMULATED BD LEAD]</td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Review</button></td>
            </tr>
            <tr>
              <td><code>RFP-2026-089</code></td>
              <td><strong>Metropolitan Health Network (Demo)</strong></td>
              <td>Multi-Facility RFP</td>
              <td>Yesterday, 16:15</td>
              <td><span class="chip chip-info">Under Evaluation</span></td>
              <td>[SIMULATED BD LEAD]</td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onclick="showAdminModule('ADM-11')">Open RFP</button></td>
            </tr>
            <tr>
              <td><code>APP-2026-104</code></td>
              <td><strong>Clinical Specialist (Candidate PII)</strong></td>
              <td>ICU Specialist</td>
              <td>2 days ago</td>
              <td><span class="chip chip-success">Credentials Verified</span></td>
              <td>[SIMULATED HR LEAD]</td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onclick="showAdminModule('ADM-14')">HR Review</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    `
  },

  'ADM-02': {
    title: 'ADM-02: Authentication & Multi-Factor Security (MFA Portal)',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_WEBSITE_ADMIN', 'ROLE_CONTENT_EDITOR', 'ROLE_BD_MANAGER', 'ROLE_HR_MANAGER'],
    render: () => `
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <div class="admin-card">
          <div class="admin-card-header">
            <span class="preview-badge" style="background: var(--voak-navy);">FORM-ADM-01 & FORM-ADM-03</span>
            <h3 class="admin-card-title">Administrative Login & TOTP Challenge</h3>
          </div>
          <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1.5rem;">
            Enforces Bcrypt (cost 12), rate-limiting (5 failures / 15m), and mandatory RFC 6238 TOTP authenticator validation for privileged actions.
          </p>
          <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1.5rem; border-radius: var(--radius-md);">
            <div class="form-group">
              <label class="form-label form-label-required">Administrative Email</label>
              <input type="email" class="form-control" value="superadmin.demo@example.invalid" readonly>
            </div>
            <div class="form-group">
              <label class="form-label form-label-required">TOTP Authenticator Code (6-Digits)</label>
              <input type="text" class="form-control" value="549 201" style="font-family: monospace; font-size: 1.15rem; letter-spacing: 0.2em; text-align: center;">
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
              <span class="chip chip-success">MFA Verified</span>
              <button class="btn btn-primary" style="padding: 0.4rem 1rem;">Verify & Enter Portal</button>
            </div>
          </div>
        </div>

        <div class="admin-card">
          <div class="admin-card-header">
            <span class="preview-badge" style="background: var(--voak-navy);">FORM-ADM-02</span>
            <h3 class="admin-card-title">Password Recovery Security Gate</h3>
          </div>
          <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1.5rem;">
            Prevents account enumeration by returning standardized success envelopes regardless of whether an email exists in the user database.
          </p>
          <div style="background: #FAF5FF; border: 1px solid #E9D5FF; padding: 1.25rem; border-radius: var(--radius-md); font-size: 0.875rem; color: #6B21A8;">
            <strong>Generic Response Policy:</strong> "If an active account exists for this address, password reset instructions have been dispatched." Zero account status leaked.
          </div>
        </div>
      </div>
    `
  },

  'ADM-03': {
    title: 'ADM-03: Users & Role-Based Access Control (RBAC Matrix)',
    allowedRoles: ['ROLE_SUPER_ADMIN'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <div>
            <span class="preview-badge" style="background: var(--voak-purple-cta); margin-bottom: 0.25rem;">FORM-ADM-08</span>
            <h3 class="admin-card-title">Canonical 5 Administrative Roles Provisioning</h3>
          </div>
          <button class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.813rem;">+ Provision Admin User</button>
        </div>
        <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1.5rem;">
          Strict least-privilege RBAC architecture. Only Super Admin has provisioning authority. Recruitment PII is quarantined to HR Manager; commercial proposals are quarantined to BD Manager.
        </p>
        <table class="admin-table">
          <thead>
            <tr>
              <th>User Name</th>
              <th>Corporate Email</th>
              <th>Canonical Role Key</th>
              <th>MFA Status</th>
              <th>Accessible Modules</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>[SIMULATED IDENTITY — DEMO ONLY] Executive Lead</strong></td>
              <td>superadmin.demo@example.invalid</td>
              <td><span class="chip chip-purple">Super Admin</span></td>
              <td><span class="chip chip-success">TOTP Active</span></td>
              <td>All 19 Modules (Unrestricted)</td>
              <td><span class="chip chip-success">Active</span></td>
            </tr>
            <tr>
              <td><strong>[SIMULATED IDENTITY — DEMO ONLY] Systems Lead</strong></td>
              <td>siteadmin.demo@example.invalid</td>
              <td><span class="chip chip-info">Website Administrator</span></td>
              <td><span class="chip chip-success">TOTP Active</span></td>
              <td>CMS, Media, SEO, General Inquiries</td>
              <td><span class="chip chip-success">Active</span></td>
            </tr>
            <tr>
              <td><strong>[SIMULATED IDENTITY — DEMO ONLY] Editorial Lead</strong></td>
              <td>editor.demo@example.invalid</td>
              <td><span class="chip chip-warning">Content Editor</span></td>
              <td><span class="chip chip-success">TOTP Active</span></td>
              <td>Pages, Articles, Case Studies (No CRM)</td>
              <td><span class="chip chip-success">Active</span></td>
            </tr>
            <tr>
              <td><strong>[SIMULATED IDENTITY — DEMO ONLY] Commercial Lead</strong></td>
              <td>partnerships.demo@example.invalid</td>
              <td><span class="chip chip-info">BD Manager</span></td>
              <td><span class="chip chip-success">TOTP Active</span></td>
              <td>Inbound CRM, Partnerships, RFP Desk</td>
              <td><span class="chip chip-success">Active</span></td>
            </tr>
            <tr>
              <td><strong>[SIMULATED IDENTITY — DEMO ONLY] Recruitment Lead</strong></td>
              <td>recruitment.demo@example.invalid</td>
              <td><span class="chip chip-info">HR Manager</span></td>
              <td><span class="chip chip-success">TOTP Active</span></td>
              <td>Job Postings, Candidate Resumes (PII)</td>
              <td><span class="chip chip-success">Active</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    `
  },

  'ADM-04': {
    title: 'ADM-04: Static Pages Content CMS',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_WEBSITE_ADMIN', 'ROLE_CONTENT_EDITOR'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <div>
            <span class="preview-badge" style="background: var(--voak-navy);">FORM-ADM-04</span>
            <h3 class="admin-card-title">Page Content Editor with Concurrency Guards</h3>
          </div>
          <button class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.813rem;">Publish Page</button>
        </div>
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 1.5rem;">
          <div style="border-right: 1px solid var(--admin-border); padding-right: 1rem;">
            <div style="font-size: 0.75rem; font-weight: 700; color: #64748B; margin-bottom: 0.5rem;">SELECT PAGE:</div>
            <div style="display: flex; flex-direction: column; gap: 0.4rem;">
              <button class="btn btn-outline" style="text-align: left; justify-content: flex-start; background: #F1F5F9;">About Us (/about)</button>
              <button class="btn btn-outline" style="text-align: left; justify-content: flex-start;">Why Choose Voak (/why-choose-voak)</button>
              <button class="btn btn-outline" style="text-align: left; justify-content: flex-start;">How We Work (/how-we-work)</button>
              <button class="btn btn-outline" style="text-align: left; justify-content: flex-start;">Quality & Compliance (/quality-compliance)</button>
            </div>
          </div>
          <div>
            <div class="form-group">
              <label class="form-label">Page Headline (H1)</label>
              <input type="text" class="form-control" value="Institutional Mission & Executive Governance">
            </div>
            <div class="form-group">
              <label class="form-label">Markdown Body Content (Sanitized via DOMPurify)</label>
              <textarea class="form-control" rows="8" style="font-family: monospace; font-size: 0.875rem;">
## Our Clinical Philosophy
Voak Health Care partners with healthcare institutions to provide reliable clinical workforce solutions...
              </textarea>
            </div>
            <div style="font-size: 0.75rem; color: #64748B;">
              Concurrency Lock: <code>last_updated_at: 2026-10-16 04:12:00 UTC</code> (Auto-rejects stale overwrite collisions).
            </div>
          </div>
        </div>
      </div>
    `
  },

  'ADM-05': {
    title: 'ADM-05: Homepage Layout Configurator',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_WEBSITE_ADMIN', 'ROLE_CONTENT_EDITOR'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <h3 class="admin-card-title">Homepage Section Ordering & Visual Hierarchy</h3>
          <button class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.813rem;">Save Homepage Layout</button>
        </div>
        <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1.5rem;">
          Configure hero title, floating healthcare badges, and bento card arrangement adhering to the approved Image 2 aesthetic benchmark.
        </p>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
          <div style="background: #F8FAFC; padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
            <div class="form-group">
              <label class="form-label">Hero Headline</label>
              <input type="text" class="form-control" value="Better Healthcare Together">
            </div>
            <div class="form-group">
              <label class="form-label">Hero Lavender Canvas Color</label>
              <input type="text" class="form-control" value="#F2F0F8" readonly>
            </div>
          </div>
          <div style="background: #F8FAFC; padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
            <div class="form-group">
              <label class="form-label">Bento Card 1 Tag & Accent</label>
              <input type="text" class="form-control" value="Pink: #EEAFD7 &bull; Clinical Staffing">
            </div>
            <div class="form-group">
              <label class="form-label">Bento Card 2 Tag & Accent</label>
              <input type="text" class="form-control" value="Yellow: #FBDC62 &bull; Healthcare Verticals">
            </div>
          </div>
        </div>
      </div>
    `
  },

  'ADM-06': {
    title: 'ADM-06: Services Management CMS',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_WEBSITE_ADMIN', 'ROLE_CONTENT_EDITOR'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <div>
            <span class="preview-badge" style="background: var(--voak-navy);">FORM-ADM-05</span>
            <h3 class="admin-card-title">Healthcare Service Offerings (CRUD & Slug Engine)</h3>
          </div>
          <button class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.813rem;">+ Create Service Offering</button>
        </div>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Service Title</th>
              <th>Category</th>
              <th>Slug Route</th>
              <th>SLA Metrics</th>
              <th>Published State</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Clinical Workforce Solutions</strong></td>
              <td><span class="chip chip-info">WORKFORCE</span></td>
              <td><code>/services/clinical-workforce</code></td>
              <td>Verified Credentials Standard</td>
              <td><span class="chip chip-success">Live (Published)</span></td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Edit</button></td>
            </tr>
            <tr>
              <td><strong>Healthcare Operations BPO</strong></td>
              <td><span class="chip chip-info">OPERATIONS</span></td>
              <td><code>/services/healthcare-bpo</code></td>
              <td>Roster Management Standard</td>
              <td><span class="chip chip-success">Live (Published)</span></td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Edit</button></td>
            </tr>
            <tr>
              <td><strong>Quality & Compliance Advisory</strong></td>
              <td><span class="chip chip-info">GOVERNANCE</span></td>
              <td><code>/services/quality-advisory</code></td>
              <td>Clinical Review Standard</td>
              <td><span class="chip chip-warning">Draft (Review)</span></td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Edit</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    `
  },

  'ADM-07': {
    title: 'ADM-07: Industries Verticals Management',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_WEBSITE_ADMIN', 'ROLE_CONTENT_EDITOR'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <h3 class="admin-card-title">Healthcare Industry Verticals</h3>
          <button class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.813rem;">+ Add Sector</button>
        </div>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
          <div style="border: 1px solid var(--admin-border); border-radius: var(--radius-md); padding: 1.25rem;">
            <span class="chip chip-info" style="margin-bottom: 0.5rem;">HOSPITAL</span>
            <h4 style="font-size: 1.15rem; color: var(--voak-navy);">Hospitals & Health Services</h4>
            <p style="font-size: 0.813rem; color: var(--voak-muted); margin: 0.5rem 0 1rem 0;">Public and private acute surgical networks.</p>
            <span style="font-size: 0.75rem; color: var(--voak-purple-cta);">Mapped to: Clinical Workforce, Operations BPO</span>
          </div>
          <div style="border: 1px solid var(--admin-border); border-radius: var(--radius-md); padding: 1.25rem;">
            <span class="chip chip-warning" style="margin-bottom: 0.5rem;">AGED_CARE</span>
            <h4 style="font-size: 1.15rem; color: var(--voak-navy);">Residential Aged Care</h4>
            <p style="font-size: 0.813rem; color: var(--voak-muted); margin: 0.5rem 0 1rem 0;">Elder care and residential clinical staffing.</p>
            <span style="font-size: 0.75rem; color: var(--voak-purple-cta);">Mapped to: Clinical Staffing, Advisory</span>
          </div>
          <div style="border: 1px solid var(--admin-border); border-radius: var(--radius-md); padding: 1.25rem;">
            <span class="chip chip-success" style="margin-bottom: 0.5rem;">COMMUNITY</span>
            <h4 style="font-size: 1.15rem; color: var(--voak-navy);">Community & Specialized Care Providers</h4>
            <p style="font-size: 0.813rem; color: var(--voak-muted); margin: 0.5rem 0 1rem 0;">Community and specialized participant support [CONTENT PENDING APPROVAL].</p>
            <span style="font-size: 0.75rem; color: var(--voak-purple-cta);">Mapped to: Allied Health, Care Staff</span>
          </div>
        </div>
      </div>
    `
  },

  'ADM-08': {
    title: 'ADM-08: Media Asset Library & Storage Governance',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_WEBSITE_ADMIN', 'ROLE_CONTENT_EDITOR'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <div>
            <span class="preview-badge" style="background: var(--voak-navy);">FORM-ADM-06</span>
            <h3 class="admin-card-title">Media Upload Dropzone & Verified Assets</h3>
          </div>
          <span class="chip chip-info">Max 15MB &bull; WebP, PNG, JPG, PDF (WCAG Alt-Text Mandatory)</span>
        </div>
        <div class="dropzone-box" style="margin-bottom: 2rem;">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--voak-purple-cta)" stroke-width="2" style="margin: 0 auto 0.75rem auto;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <strong style="display:block; color: var(--voak-navy); font-size: 1rem;">Drag & drop verified healthcare imagery or institutional documents</strong>
          <span style="font-size: 0.813rem; color: var(--voak-muted);">Auto-converts to optimized WebP format with required WCAG alt-text metadata</span>
        </div>

        <h4 style="font-size: 1.15rem; margin-bottom: 1rem;">Verified Media Library</h4>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem;">
          <div style="border: 1px solid var(--admin-border); border-radius: var(--radius-sm); overflow: hidden;">
            <img src="assets/images/hero_doctor.jpg" alt="Doctor thumbnail" style="height: 140px; width: 100%; object-fit: cover;">
            <div style="padding: 0.75rem; font-size: 0.75rem;">
              <strong>hero_doctor.jpg</strong> (652 KB &bull; 1024x1024)
              <div style="color: #64748B; margin-top: 0.25rem;">Alt: Healthcare professional with stethoscope</div>
            </div>
          </div>
          <div style="border: 1px solid var(--admin-border); border-radius: var(--radius-sm); overflow: hidden;">
            <img src="assets/images/operations_manager.jpg" alt="Manager thumbnail" style="height: 140px; width: 100%; object-fit: cover;">
            <div style="padding: 0.75rem; font-size: 0.75rem;">
              <strong>operations_manager.jpg</strong> (669 KB &bull; 1024x1024)
              <div style="color: #64748B; margin-top: 0.25rem;">Alt: Healthcare operations coordinator</div>
            </div>
          </div>
          <div style="border: 1px solid var(--admin-border); border-radius: var(--radius-sm); overflow: hidden; background: #FAF9FE; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 180px;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--voak-purple-cta)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <span style="font-size: 0.75rem; font-weight: 600; margin-top: 0.5rem;">Governance_SLA_Brief.pdf</span>
            <span style="font-size: 0.688rem; color: #64748B;">Quarantined Document</span>
          </div>
        </div>
      </div>
    `
  },

  'ADM-09': {
    title: 'ADM-09: Contact Inquiries CRM Desk',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_WEBSITE_ADMIN', 'ROLE_BD_MANAGER'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <h3 class="admin-card-title">Inbound Inquiries Triage (FORM-PUB-01 Submissions)</h3>
          <button class="btn btn-outline" style="padding: 0.45rem 1rem; font-size: 0.813rem;">Export CSV</button>
        </div>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Ref No</th>
              <th>Contact Name</th>
              <th>Organization</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>INQ-DEMO-001</code></td>
              <td>[SIMULATED ENQUIRER A]</td>
              <td>[DEMO FACILITY] Regional Health Network</td>
              <td>inquiry-a.demo@example.invalid</td>
              <td>+61 2 9000 0001 (Simulated)</td>
              <td><span class="chip chip-warning">NEW</span></td>
              <td><button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Triage</button></td>
            </tr>
            <tr>
              <td><code>INQ-DEMO-002</code></td>
              <td>[SIMULATED ENQUIRER B]</td>
              <td>[DEMO FACILITY] Aged Care Facility</td>
              <td>inquiry-b.demo@example.invalid</td>
              <td>+61 2 9000 0002 (Simulated)</td>
              <td><span class="chip chip-info">TRIAGED</span></td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">View Notes</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    `
  },

  'ADM-10': {
    title: 'ADM-10: Strategic Partnership Pipeline CRM',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_BD_MANAGER'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <div>
            <span class="chip chip-purple" style="margin-bottom: 0.25rem;">BD Manager Protected</span>
            <h3 class="admin-card-title">Strategic Partnership Pipeline (FORM-PUB-02 Inquiries)</h3>
          </div>
          <button class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.813rem;">+ Add Lead</button>
        </div>
        <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1.5rem;">
          Restricted CRM gate: Only Super Admin and BD Manager can view organizational alliance proposals.
        </p>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
          <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1rem; border-radius: var(--radius-md);">
            <div style="font-weight: 700; color: #475569; font-size: 0.813rem; margin-bottom: 0.5rem;">STAGE 1: INITIAL (2)</div>
            <div style="background: #FFF; padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid #CBD5E1; margin-bottom: 0.5rem; font-size: 0.813rem;">
              <strong>National Care Alliance [SIMULATED RECORD]</strong>
              <div style="color: #64748B;">Multi-site aged care co-sourcing</div>
            </div>
          </div>
          <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1rem; border-radius: var(--radius-md);">
            <div style="font-weight: 700; color: #475569; font-size: 0.813rem; margin-bottom: 0.5rem;">STAGE 2: SCOPING (1)</div>
            <div style="background: #FFF; padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid #CBD5E1; margin-bottom: 0.5rem; font-size: 0.813rem;">
              <strong>Simulated Surgical Center [SIMULATED RECORD]</strong>
              <div style="color: #64748B;">Specialized perioperative staffing</div>
            </div>
          </div>
          <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1rem; border-radius: var(--radius-md);">
            <div style="font-weight: 700; color: #475569; font-size: 0.813rem; margin-bottom: 0.5rem;">STAGE 3: TERMS (1)</div>
            <div style="background: #FFF; padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid #CBD5E1; margin-bottom: 0.5rem; font-size: 0.813rem;">
              <strong>Simulated Health District [SIMULATED RECORD]</strong>
              <div style="color: #64748B;">Master Services SLA Draft</div>
            </div>
          </div>
          <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1rem; border-radius: var(--radius-md);">
            <div style="font-weight: 700; color: #475569; font-size: 0.813rem; margin-bottom: 0.5rem;">STAGE 4: ACTIVE (3)</div>
            <div style="background: #FFF; padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid #CBD5E1; font-size: 0.813rem;">
              <strong>Simulated Clinical Group [SIMULATED RECORD]</strong>
              <div style="color: #15803D;">Active Deployment Contract</div>
            </div>
          </div>
        </div>
      </div>
    `
  },

  'ADM-11': {
    title: 'ADM-11: Proposal / RFP Review Desk',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_BD_MANAGER'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <div>
            <span class="chip chip-purple" style="margin-bottom: 0.25rem;">Commercial Gate</span>
            <h3 class="admin-card-title">Commercial RFP Desk (FORM-PUB-03 Submissions)</h3>
          </div>
          <button class="btn btn-outline" style="padding: 0.45rem 1rem; font-size: 0.813rem;">Export RFP Index</button>
        </div>
        <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1.5rem;">
          Protected tender documents stored in private quarantine storage. Authenticated streaming enforced with audit logging.
        </p>
        <table class="admin-table">
          <thead>
            <tr>
              <th>RFP Ref</th>
              <th>Organization</th>
              <th>Facility Type</th>
              <th>Services Scoped</th>
              <th>Tender Attachment</th>
              <th>Review Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>RFP-2026-089</code></td>
              <td><strong>Metropolitan Health Network [SIMULATED RECORD]</strong></td>
              <td>HOSPITAL_ACUTE</td>
              <td>Clinical Workforce</td>
              <td><span class="chip chip-info">RFP_Spec_2026.pdf (12.4 MB)</span></td>
              <td><span class="chip chip-warning">EVALUATION</span></td>
              <td><button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Download Tender</button></td>
            </tr>
            <tr>
              <td><code>RFP-2026-088</code></td>
              <td><strong>Coastal Health Group [SIMULATED RECORD]</strong></td>
              <td>AGED_CARE</td>
              <td>Operations BPO</td>
              <td><span class="chip chip-info">Scope_Brief.docx (2.1 MB)</span></td>
              <td><span class="chip chip-success">COMPLETED</span></td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Archive</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    `
  },

  'ADM-12': {
    title: 'ADM-12: Transactional Email Templates & Outbox',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_WEBSITE_ADMIN'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <div>
            <span class="preview-badge" style="background: var(--voak-navy);">FORM-ADM-07</span>
            <h3 class="admin-card-title">Email Notification Templates & Outbox Dispatcher</h3>
          </div>
          <button class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.813rem;">+ New Template</button>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
          <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1.25rem; border-radius: var(--radius-md);">
            <div class="form-group">
              <label class="form-label">Subject Line Template</label>
              <input type="text" class="form-control" value="Voak Health Care — Inquiry Confirmation [{{ref_no}}]">
            </div>
            <div class="form-group">
              <label class="form-label">Mandatory Placeholders Verification</label>
              <div style="font-size: 0.75rem; color: #64748B;">
                Tokens: <code>{{contact_name}}</code>, <code>{{ref_no}}</code>, <code>{{legal_entity_name}}</code>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Template Body (HTML)</label>
              <textarea class="form-control" rows="5" style="font-family: monospace; font-size: 0.813rem;"><p>Dear {{contact_name}},</p><p>Your request has been received under reference {{ref_no}}.</p></textarea>
            </div>
          </div>
          <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1.25rem; border-radius: var(--radius-md);">
            <h4 style="font-size: 1rem; color: var(--voak-navy); margin-bottom: 0.75rem;">Outbox Queue Status</h4>
            <div style="font-size: 0.813rem; color: var(--voak-muted);">
              <div>Pending Dispatch: <strong>0</strong></div>
              <div>Sent (24h): <strong>18</strong></div>
              <div>Failed Retries: <strong>0</strong></div>
              <div style="margin-top: 1rem;"><span class="chip chip-success">SMTP Transport Connected</span></div>
            </div>
          </div>
        </div>
      </div>
    `
  },

  'ADM-13': {
    title: 'ADM-13: Careers Job Postings CMS',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_WEBSITE_ADMIN', 'ROLE_HR_MANAGER'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <h3 class="admin-card-title">Clinical Vacancy Postings</h3>
          <button class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.813rem;">+ Create Job Opening</button>
        </div>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Job Title</th>
              <th>Department</th>
              <th>Type</th>
              <th>Active Applicants</th>
              <th>Posting Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Clinical Nurse Specialist (ICU)</strong></td>
              <td>Critical Care</td>
              <td>Full-Time</td>
              <td>8 Applicants</td>
              <td><span class="chip chip-success">ACTIVE</span></td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Edit</button></td>
            </tr>
            <tr>
              <td><strong>Emergency Room Registered Nurse</strong></td>
              <td>Emergency Medicine</td>
              <td>Shift Roster</td>
              <td>14 Applicants</td>
              <td><span class="chip chip-success">ACTIVE</span></td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Edit</button></td>
            </tr>
            <tr>
              <td><strong>Allied Health Lead (Physiotherapy)</strong></td>
              <td>Rehabilitation</td>
              <td>Contract</td>
              <td>3 Applicants</td>
              <td><span class="chip chip-warning">DRAFT</span></td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Publish</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    `
  },

  'ADM-14': {
    title: 'ADM-14: Candidate Applications & Resume Vault (PII)',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_HR_MANAGER'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <div>
            <span class="chip chip-purple" style="margin-bottom: 0.25rem;">HR Manager Gate &bull; PII Safeguard</span>
            <h3 class="admin-card-title">Candidate Applications & Resume Review (FORM-PUB-04)</h3>
          </div>
          <button class="btn btn-outline" style="padding: 0.45rem 1rem; font-size: 0.813rem;">Export Applicant Roster</button>
        </div>
        <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1.5rem;">
          Strict privacy boundary: Resumes and candidate personal contact details are accessible solely to Super Admin and HR Manager.
        </p>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Applicant Name</th>
              <th>Applied Position</th>
              <th>Email / Phone</th>
              <th>Registration ID</th>
              <th>Resume Vault</th>
              <th>Stage</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>[SIMULATED CANDIDATE A] Clinical Nurse Specialist</strong></td>
              <td>Clinical Nurse Specialist</td>
              <td>candidate-a.demo@example.invalid<br>+61 400 000 001 (Simulated)</td>
              <td>REG-DEMO-001 [MOCK]</td>
              <td><span class="chip chip-info">Resume_Simulated_A.pdf (Quarantined)</span></td>
              <td><span class="chip chip-success">SCREENING</span></td>
              <td><button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Download CV</button></td>
            </tr>
            <tr>
              <td><strong>[SIMULATED CANDIDATE B] Emergency Room Nurse</strong></td>
              <td>Emergency Room Nurse</td>
              <td>candidate-b.demo@example.invalid<br>+61 400 000 002 (Simulated)</td>
              <td>REG-DEMO-002 [MOCK]</td>
              <td><span class="chip chip-info">Resume_Simulated_B.docx (Quarantined)</span></td>
              <td><span class="chip chip-warning">APPLIED</span></td>
              <td><button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Download CV</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    `
  },

  'ADM-15': {
    title: 'ADM-15: Case Studies & Verified Testimonials CMS',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_WEBSITE_ADMIN', 'ROLE_CONTENT_EDITOR'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <h3 class="admin-card-title">Case Studies & Verified Consent Releases</h3>
          <button class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.813rem;">+ Add Case Study</button>
        </div>
        <div style="background: #FFFBEB; border: 1px solid #FCD34D; padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; font-size: 0.813rem; color: #92400E;">
          <strong>Consent Guardrail:</strong> Case studies cannot be published without an attached verified institutional consent release certificate.
        </div>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Case Title</th>
              <th>Sector Vertical</th>
              <th>Consent Release File</th>
              <th>Publish State</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Acute Surge Roster Stabilization</strong></td>
              <td>Public Hospital Network</td>
              <td><span class="chip chip-success">Consent_Signed_Metro.pdf</span></td>
              <td><span class="chip chip-success">Live (Published)</span></td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Edit</button></td>
            </tr>
            <tr>
              <td><strong>Residential Care Shift Optimization</strong></td>
              <td>Aged Care Group</td>
              <td><span class="chip chip-error">Missing Consent Form</span></td>
              <td><span class="chip chip-warning">Draft (Blocked)</span></td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Upload Release</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    `
  },

  'ADM-16': {
    title: 'ADM-16: Insights & Articles CMS',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_WEBSITE_ADMIN', 'ROLE_CONTENT_EDITOR'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <h3 class="admin-card-title">Editorial Insights & Thought Leadership</h3>
          <button class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.813rem;">+ Compose Article</button>
        </div>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Article Title</th>
              <th>Category</th>
              <th>Reading Time</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Optimizing Healthcare Workforce Models in 2026</strong></td>
              <td>Workforce Strategy</td>
              <td>6 mins</td>
              <td><span class="chip chip-success">Published</span></td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Edit</button></td>
            </tr>
            <tr>
              <td><strong>Regulatory Standards for Clinical Credentialing</strong></td>
              <td>Compliance & Governance</td>
              <td>8 mins</td>
              <td><span class="chip chip-success">Published</span></td>
              <td><button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Edit</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    `
  },

  'ADM-17': {
    title: 'ADM-17: SEO Console & Redirect Manager',
    allowedRoles: ['ROLE_SUPER_ADMIN', 'ROLE_WEBSITE_ADMIN'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <h3 class="admin-card-title">SEO Meta Controls, 301 Redirects & XML Sitemap</h3>
          <button class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.813rem;">Rebuild XML Sitemap</button>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
          <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1.25rem; border-radius: var(--radius-md);">
            <h4 style="font-size: 1rem; margin-bottom: 0.75rem;">Automated 301 Redirect Manager</h4>
            <div style="font-size: 0.813rem; color: var(--voak-muted); margin-bottom: 1rem;">
              When services or articles are renamed, automated permanent 301 redirects are generated to prevent broken backlinks.
            </div>
            <div style="font-family: monospace; font-size: 0.75rem; background: #FFF; padding: 0.75rem; border-radius: 4px; border: 1px solid #CBD5E1;">
              /services/nursing-staff &rarr; /services/clinical-workforce (301 Permanent)
            </div>
          </div>
          <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1.25rem; border-radius: var(--radius-md);">
            <h4 style="font-size: 1rem; margin-bottom: 0.75rem;">Structured Data (JSON-LD) Status</h4>
            <div style="font-size: 0.813rem; color: var(--voak-muted);">
              <div>Schema Type: <strong>MedicalBusiness / Organization</strong></div>
              <div>Validation: <span class="chip chip-success">Valid Schema.org</span></div>
              <div style="margin-top: 0.5rem;">Canonical Proposal URL: <code>/request-proposal</code></div>
            </div>
          </div>
        </div>
      </div>
    `
  },

  'ADM-18': {
    title: 'ADM-18: System Settings & Configurable Entity',
    allowedRoles: ['ROLE_SUPER_ADMIN'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <div>
            <span class="chip chip-purple" style="margin-bottom: 0.25rem;">Super Admin Protected</span>
            <h3 class="admin-card-title">Enterprise System Settings & Configurable Entity</h3>
          </div>
          <button class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.813rem;">Save System Settings</button>
        </div>
        <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1.5rem;">
          Geographic neutrality settings: Corporate legal name, headquarters address, and default country phone code are configurable parameters rather than hardcoded assumptions.
        </p>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
          <div class="form-group">
            <label class="form-label">Legal Entity Name</label>
            <input type="text" class="form-control" value="Voak Health Care [Confirmation Pending]">
          </div>
          <div class="form-group">
            <label class="form-label">Default International Calling Code</label>
            <input type="text" class="form-control" value="+61 (Configurable to International Standard)">
          </div>
          <div class="form-group">
            <label class="form-label">Regional Headquarters Address</label>
            <input type="text" class="form-control" value="Corporate Operations [Jurisdiction Configurable via CMS]">
          </div>
          <div class="form-group">
            <label class="form-label">Security Incident Notification Webhook</label>
            <input type="text" class="form-control" value="https://internal.ops.voakhealthcare.com/sec-alerts">
          </div>
        </div>
      </div>
    `
  },

  'ADM-19': {
    title: 'ADM-19: Immutable Audit Logs & Telemetry Monitoring',
    allowedRoles: ['ROLE_SUPER_ADMIN'],
    render: () => `
      <div class="admin-card">
        <div class="admin-card-header">
          <div>
            <span class="chip chip-purple" style="margin-bottom: 0.25rem;">Super Admin Protected</span>
            <h3 class="admin-card-title">Security & System Audit Log (Immutable Retention: 90 Days)</h3>
          </div>
          <button class="btn btn-outline" style="padding: 0.45rem 1rem; font-size: 0.813rem;">Export Cryptographic CSV</button>
        </div>
        <p style="font-size: 0.875rem; color: var(--voak-muted); margin-bottom: 1.5rem;">
          Append-only cryptographic audit chain. Logs actor, action, resource, IP address, and SHA-256 state signature.
        </p>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Timestamp (UTC)</th>
              <th>Actor (Email)</th>
              <th>Action / Event</th>
              <th>Target Resource</th>
              <th>IP Address</th>
              <th>Outcome</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>2026-10-16 05:22:14</td>
              <td>superadmin.demo@example.invalid</td>
              <td><code>AUTH_LOGIN_SUCCESS</code></td>
              <td><code>Session:s_9f82</code></td>
              <td>103.24.112.5</td>
              <td><span class="chip chip-success">Success</span></td>
            </tr>
            <tr>
              <td>2026-10-16 05:14:02</td>
              <td>partnerships.demo@example.invalid</td>
              <td><code>RFP_DOWNLOAD_ATTACHMENT</code></td>
              <td><code>RFP-2026-089</code></td>
              <td>203.18.90.12</td>
              <td><span class="chip chip-success">Success</span></td>
            </tr>
            <tr>
              <td>2026-10-16 04:58:33</td>
              <td>system_worker</td>
              <td><code>BACKUP_SNAPSHOT_COMPLETE</code></td>
              <td><code>DB:voak_core</code></td>
              <td>127.0.0.1</td>
              <td><span class="chip chip-success">Completed</span></td>
            </tr>
            <tr>
              <td>2026-10-16 03:12:19</td>
              <td>anonymous</td>
              <td><code>AUTH_FAILED_RATE_LIMIT</code></td>
              <td><code>Endpoint:/auth/login</code></td>
              <td>45.12.89.201</td>
              <td><span class="chip chip-error">Blocked</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    `
  }
};

/* --------------------------------------------------------------------------
   6. ADMIN NAVIGATION & LEAST-PRIVILEGE ROLE SIMULATION
   -------------------------------------------------------------------------- */
function showAdminModule(moduleId) {
  currentAdminModule = moduleId;

  // Update active sidebar item
  document.querySelectorAll('.admin-nav-item').forEach(item => {
    item.classList.remove('active');
  });
  const activeNav = document.getElementById(`admin-nav-${moduleId}`);
  if (activeNav) activeNav.classList.add('active');

  const container = document.getElementById('admin-module-display');
  if (!container) return;

  const allowedModules = RBAC_MODULE_PERMISSIONS[currentSimulatedRole] || [];
  const isPermitted = allowedModules.includes(moduleId);

  const mod = ADMIN_MODULES[moduleId];

  if (!isPermitted) {
    // Render simulated 403 Forbidden Access Denied Card (Design Preview)
    const profile = ROLE_PROFILES[currentSimulatedRole];
    container.innerHTML = `
      <div class="rbac-403-container">
        <div class="rbac-403-icon">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        </div>
        <span class="chip chip-error" style="margin-bottom: 0.75rem;">SIMULATED 403 ACCESS DENIED (Design Preview — Production RBAC in Phase 6)</span>
        <h3 style="font-size: 1.5rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Least-Privilege RBAC Boundary Simulation</h3>
        <p style="color: var(--voak-muted); font-size: 0.875rem; margin-bottom: 1.5rem; line-height: 1.6;">
          Your simulated role <strong>${profile.roleLabel} (${currentSimulatedRole})</strong> is not authorized to access module <strong>${moduleId}</strong>.
          Under Voak Health Care's strict separation of duties, commercial tenders and candidate resumes are isolated from non-owning roles.
        </p>
        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: var(--radius-sm); padding: 1rem; text-align: left; font-size: 0.813rem; margin-bottom: 1.5rem;">
          <div><strong>Authorized Roles for ${moduleId}:</strong> ${(mod ? mod.allowedRoles : []).join(', ')}</div>
          <div style="margin-top: 0.25rem;"><strong>Simulation Incident Code:</strong> <code>RBAC-SIMULATED-DENIED-${moduleId}-${currentSimulatedRole}</code></div>
          <div style="margin-top: 0.5rem; font-style: italic; color: #64748B;">Notice: This is a front-end design demonstration of role boundary UX. Authentic HTTP 403 response headers and server-side authorization will be implemented in Phase 6 PHP backend.</div>
        </div>
        <button class="btn btn-primary" onclick="showAdminModule('ADM-01')">Return to Accessible Dashboard</button>
      </div>
    `;
    return;
  }

  if (mod && mod.render) {
    container.innerHTML = mod.render();
  } else {
    container.innerHTML = `
      <div class="admin-card">
        <h3 class="admin-card-title">${moduleId} View</h3>
        <p style="font-size: 0.875rem; color: var(--voak-muted); margin-top: 0.5rem;">
          This module is fully specified according to Phase 1 and Phase 2 RBAC policies and database schema.
        </p>
      </div>
    `;
  }
}

function simulateRole(roleKey) {
  currentSimulatedRole = roleKey;
  const profile = ROLE_PROFILES[roleKey] || ROLE_PROFILES['ROLE_SUPER_ADMIN'];

  // Update role buttons
  const pills = document.querySelectorAll('.admin-role-pill');
  pills.forEach(p => {
    if (p.getAttribute('onclick').includes(roleKey)) {
      p.classList.add('active');
    } else {
      p.classList.remove('active');
    }
  });

  // Update user chip in topbar
  const initials = document.getElementById('admin-user-avatar-initials');
  const roleLabel = document.getElementById('admin-user-role-label');
  const emailLabel = document.getElementById('admin-user-email-label');

  if (initials) initials.textContent = profile.avatar;
  if (roleLabel) roleLabel.textContent = profile.roleLabel;
  if (emailLabel) emailLabel.textContent = profile.email;

  // Update sidebar visual indicator for locked modules
  const allowed = RBAC_MODULE_PERMISSIONS[roleKey] || [];
  Object.keys(ADMIN_MODULES).forEach(mId => {
    const nav = document.getElementById(`admin-nav-${mId}`);
    if (nav) {
      if (allowed.includes(mId)) {
        nav.style.opacity = '1';
        nav.style.cursor = 'pointer';
        nav.title = 'Authorized';
      } else {
        nav.style.opacity = '0.4';
        nav.title = `Access Denied for ${profile.roleLabel}`;
      }
    }
  });

  // Re-render current module under new role permissions
  showAdminModule(currentAdminModule);
}

/* --------------------------------------------------------------------------
   7. DESIGN TOKEN PALETTE VIEWER (Approved Image 2 Tokens)
   -------------------------------------------------------------------------- */
const COLOR_TOKENS = [
  { name: "Lavender Canvas", hex: "#F2F0F8", token: "--voak-canvas", bg: "#F2F0F8", text: "#252B5E", usage: "Main Page Canvas", contrast: "11.2:1 (Pass)" },
  { name: "Rose Pink", hex: "#EEAFD7", token: "--voak-pink", bg: "#EEAFD7", text: "#252B5E", usage: "Card 1 & Hero Collage Accent", contrast: "7.8:1 (Pass)" },
  { name: "Warm Yellow", hex: "#FBDC62", token: "--voak-yellow", bg: "#FBDC62", text: "#252B5E", usage: "Card 2 & Organic Vector Accent", contrast: "12.4:1 (Pass)" },
  { name: "Fresh Mint", hex: "#54CB96", token: "--voak-mint", bg: "#54CB96", text: "#252B5E", usage: "Card 3 & Success States", contrast: "6.5:1 (Pass)" },
  { name: "Sky Blue", hex: "#63A5E6", token: "--voak-sky", bg: "#63A5E6", text: "#FFFFFF", usage: "Info Chips & Service Badges", contrast: "4.8:1 (Pass)" },
  { name: "Primary CTA Purple", hex: "#665CE9", token: "--voak-purple-cta", bg: "#665CE9", text: "#FFFFFF", usage: "Interactive Buttons & Focus Rings", contrast: "5.1:1 (Pass)" },
  { name: "Deep Navy Editorial", hex: "#252B5E", token: "--voak-navy", bg: "#252B5E", text: "#FFFFFF", usage: "Serif Headlines & Heavy Typography", contrast: "14.8:1 (Pass)" },
  { name: "Muted Body Text", hex: "#50567A", token: "--voak-muted", bg: "#FFFFFF", text: "#50567A", usage: "High-Contrast Paragraph Copy", contrast: "6.3:1 (Pass)" }
];

function renderColorTokens() {
  const container = document.getElementById('color-token-grid');
  if (!container) return;

  container.innerHTML = COLOR_TOKENS.map(c => `
    <div class="gallery-item-card" style="border-left: 6px solid ${c.hex};">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
        <span class="preview-badge" style="background: ${c.bg}; color: ${c.text}; border: 1px solid rgba(0,0,0,0.1);">${c.hex}</span>
        <span class="chip chip-success" style="font-size: 0.688rem;">${c.contrast}</span>
      </div>
      <h4 style="font-size: 1.15rem; margin-bottom: 0.25rem;">${c.name}</h4>
      <code style="font-size: 0.75rem; color: #64748B; display: block; margin-bottom: 0.5rem;">${c.token}</code>
      <p style="font-size: 0.813rem; color: var(--voak-muted);">${c.usage}</p>
    </div>
  `).join('');
}

/* --------------------------------------------------------------------------
   8. MULTI-STEP PROPOSAL WIZARD (FORM-PUB-03 Interaction Design)
   -------------------------------------------------------------------------- */
function showProposalWizard() {
  const modal = document.getElementById('proposal-modal');
  if (modal) {
    modal.style.display = 'flex';
    currentWizardStep = 1;
    renderWizardStep();
  }
}

function closeProposalWizard() {
  const modal = document.getElementById('proposal-modal');
  if (modal) modal.style.display = 'none';
}

function nextWizardStep() {
  if (currentWizardStep < 4) {
    currentWizardStep++;
    renderWizardStep();
  }
}

function prevWizardStep() {
  if (currentWizardStep > 1) {
    currentWizardStep--;
    renderWizardStep();
  }
}

function renderWizardStep() {
  for (let i = 1; i <= 4; i++) {
    const node = document.getElementById(`step-node-${i}`);
    if (node) {
      node.classList.remove('active', 'completed');
      if (i === currentWizardStep) node.classList.add('active');
      else if (i < currentWizardStep) node.classList.add('completed');
    }
  }

  const container = document.getElementById('wizard-step-content');
  if (!container) return;

  switch (currentWizardStep) {
    case 1:
      container.innerHTML = `
        <h4 style="font-size: 1.25rem; margin-bottom: 1rem;">Step 1: Facility & Executive Profile</h4>
        <div class="form-group">
          <label class="form-label form-label-required">Organization Legal Entity Name</label>
          <input type="text" class="form-control" id="wiz-org" placeholder="e.g. Regional Health District" value="${wizardData.orgName}">
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label form-label-required">Facility Type</label>
            <select class="form-control" id="wiz-type">
              <option value="HOSPITAL_ACUTE">Hospital / Acute Care</option>
              <option value="AGED_CARE">Residential Aged Care</option>
              <option value="PRIMARY_CLINIC">Specialist / Primary Clinic</option>
              <option value="DIAGNOSTIC_LAB">Diagnostic / Allied Facility</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label form-label-required">Lead Contact Phone (International)</label>
            <input type="tel" class="form-control" id="wiz-phone" placeholder="+61 400 123 456 or international format">
          </div>
        </div>
        <div style="display: flex; justify-content: flex-end; margin-top: 2rem;">
          <button class="btn btn-primary" onclick="saveStep1()">Continue to Service Scope &rarr;</button>
        </div>
      `;
      break;

    case 2:
      container.innerHTML = `
        <h4 style="font-size: 1.25rem; margin-bottom: 1rem;">Step 2: Service Scope & Volume</h4>
        <div class="form-group">
          <label class="form-label form-label-required">Select Required Services</label>
          <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">
            <label><input type="checkbox" checked> Specialized Registered Nurses (ICU / Emergency / Acute)</label>
            <label><input type="checkbox" checked> Clinical Supervision & Quality Governance</label>
            <label><input type="checkbox"> Allied Health Specialists</label>
            <label><input type="checkbox"> Healthcare Operations BPO & Ward Intake</label>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Estimated Staffing Volume / Shifts per Week</label>
          <input type="text" class="form-control" placeholder="e.g. 35 shifts/week, ward coverage">
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 2rem;">
          <button class="btn btn-outline" onclick="prevWizardStep()">&larr; Back</button>
          <button class="btn btn-primary" onclick="nextWizardStep()">Continue to Procurement &rarr;</button>
        </div>
      `;
      break;

    case 3:
      container.innerHTML = `
        <h4 style="font-size: 1.25rem; margin-bottom: 1rem;">Step 3: Procurement Parameters</h4>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label form-label-required">Target Deployment Start Date</label>
            <input type="date" class="form-control" value="2026-11-01">
          </div>
          <div class="form-group">
            <label class="form-label form-label-required">Procurement Stage</label>
            <select class="form-control">
              <option>Active Commercial Tender</option>
              <option>Budgetary Planning</option>
              <option>Immediate Surge Replacement</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Estimated Budget Band</label>
          <select class="form-control">
            <option>Tier 1: Under $100K</option>
            <option selected>Tier 2: $100K - $500K</option>
            <option>Tier 3: $500K - $2M</option>
            <option>Tier 4: Above $2M</option>
            <option>Undisclosed / Subject to Proposal</option>
          </select>
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 2rem;">
          <button class="btn btn-outline" onclick="prevWizardStep()">&larr; Back</button>
          <button class="btn btn-primary" onclick="nextWizardStep()">Attach Documents &rarr;</button>
        </div>
      `;
      break;

    case 4:
      container.innerHTML = `
        <h4 style="font-size: 1.25rem; margin-bottom: 1rem;">Step 4: Project Brief & Tender Attachment</h4>
        <div class="form-group">
          <label class="form-label form-label-required">Project Summary / Clinical Scope</label>
          <textarea class="form-control" rows="3" placeholder="Outline specific facility ward requirements, scheduling constraints, or clinical objectives..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Upload Tender Specification (Optional &bull; Max 15MB PDF, DOCX, XLSX)</label>
          <input type="file" class="form-control">
        </div>
        <div class="form-group">
          <label style="font-size: 0.813rem; display: flex; align-items: center; gap: 0.5rem; color: var(--voak-muted);">
            <input type="checkbox" required checked> I consent to Voak Health Care processing this RFP according to the B2B Privacy Policy.
          </label>
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 2rem;">
          <button class="btn btn-outline" onclick="prevWizardStep()">&larr; Back</button>
          <button class="btn btn-primary" onclick="submitProposal()">Submit RFP to Operations Desk</button>
        </div>
      `;
      break;
  }
}

function saveStep1() {
  const org = document.getElementById('wiz-org');
  if (org && org.value) wizardData.orgName = org.value;
  nextWizardStep();
}

function submitProposal() {
  const container = document.getElementById('wizard-step-content');
  if (container) {
    container.innerHTML = `
      <div style="text-align: center; padding: 2.5rem 1rem;">
        <div style="width: 56px; height: 56px; background: var(--state-success-bg); color: var(--state-success); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto;">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h3 style="font-size: 1.5rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Proposal Submitted to Triage Desk</h3>
        <p style="font-size: 0.938rem; color: var(--voak-muted); max-width: 480px; margin: 0 auto 1.5rem auto; line-height: 1.5;">
          Your request has been logged. Reference ID: <strong>RFP-2026-DEMO</strong>.<br>
          Our operations team will review your requirements according to standard procurement triage procedures.
        </p>
        <button class="btn btn-primary" onclick="closeProposalWizard()">Done</button>
      </div>
    `;
  }
}

function initProposalWizard() {
  window.showProposalWizard = showProposalWizard;
  window.closeProposalWizard = closeProposalWizard;
  window.nextWizardStep = nextWizardStep;
  window.prevWizardStep = prevWizardStep;
  window.saveStep1 = saveStep1;
  window.submitProposal = submitProposal;
  window.switchTab = switchTab;
  window.showAdminModule = showAdminModule;
  window.simulateRole = simulateRole;
  window.inspectPage = inspectPage;
  window.closeScreenInspector = closeScreenInspector;
}
