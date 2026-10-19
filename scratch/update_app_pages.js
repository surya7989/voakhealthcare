const fs = require('fs');
const path = require('path');

const appJsPath = path.join('c:\\Users\\voakh\\Downloads\\voak health\\design\\phase-03\\app.js');
let content = fs.readFileSync(appJsPath, 'utf8');

// The new complete renderPageSpecificBody function with ALL 22 PAGES
const newFunction = `function renderPageSpecificBody(page) {
  switch (page.id) {
    case 'PUB-01':
      return \`
        <div class="hero-section" style="padding: 3rem 2rem;">
          <div class="hero-content">
            <span class="preview-badge" style="background: var(--voak-purple-cta); color: #FFF; margin-bottom: 1rem;">
              Healthcare Operations & Workforce Infrastructure
            </span>
            <h1 class="hero-headline" style="font-size: 3rem; line-height: 1.15; margin-bottom: 1.25rem;">
              Better Healthcare Together
            </h1>
            <p class="hero-subheading" style="font-size: 1.15rem; max-width: 520px; margin-bottom: 2rem;">
              Empowering healthcare facilities, hospitals, and aged care organizations with dependable clinical workforce solutions, operational governance, and end-to-end B2B healthcare infrastructure.
            </p>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
              <button class="btn btn-primary" onclick="showProposalWizard()">Request a Proposal</button>
              <button class="btn btn-outline" onclick="inspectPage('PUB-03')">Explore Services</button>
            </div>
          </div>
          <div class="hero-image-wrap">
            <div class="doctor-photo-frame">
              <img src="assets/images/hero_doctor.jpg" alt="Healthcare Professional" class="doctor-img" style="max-height: 380px; object-fit: cover; border-radius: var(--radius-xl);">
            </div>
          </div>
        </div>

        <!-- 3 Feature Cards (Pink, Yellow, Mint) -->
        <div style="padding: 2rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
          <div class="feature-card feature-card-pink">
            <span class="card-tag">Workforce & Operations</span>
            <h3 style="font-size: 1.4rem; margin: 0.5rem 0;">Healthcare Services</h3>
            <p style="font-size: 0.9rem; color: var(--voak-muted);">Rapid clinical staffing, allied health coordination, and specialized operational management.</p>
            <div style="margin-top: auto; padding-top: 1rem;">
              <button class="btn btn-outline" style="padding: 0.4rem 0.85rem; font-size: 0.8rem;" onclick="inspectPage('PUB-03')">Discover Services &rarr;</button>
            </div>
          </div>
          <div class="feature-card feature-card-yellow">
            <span class="card-tag">Specialized Sectors</span>
            <h3 style="font-size: 1.4rem; margin: 0.5rem 0;">Industries We Serve</h3>
            <p style="font-size: 0.9rem; color: var(--voak-muted);">Custom solutions tailored for hospitals, residential aged care, and community providers.</p>
            <div style="margin-top: auto; padding-top: 1rem;">
              <button class="btn btn-outline" style="padding: 0.4rem 0.85rem; font-size: 0.8rem;" onclick="inspectPage('PUB-05')">View Sectors &rarr;</button>
            </div>
          </div>
          <div class="feature-card feature-card-mint">
            <span class="card-tag">Strategic Co-operation</span>
            <h3 style="font-size: 1.4rem; margin: 0.5rem 0;">Partner With Us</h3>
            <p style="font-size: 0.9rem; color: var(--voak-muted);">Establish institutional service agreements and accredited healthcare delivery partnerships.</p>
            <div style="margin-top: auto; padding-top: 1rem;">
              <button class="btn btn-outline" style="padding: 0.4rem 0.85rem; font-size: 0.8rem;" onclick="showProposalWizard()">Request Proposal &rarr;</button>
            </div>
          </div>
        </div>

        <!-- 8 Bento Service Tiles -->
        <div style="padding: 2rem;">
          <h2 style="font-family: var(--font-serif); font-size: 2.2rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Comprehensive Healthcare Services</h2>
          <p style="color: var(--voak-muted); margin-bottom: 2rem;">Institutional capabilities engineered to eliminate clinical staffing deficits and ensure operational continuity.</p>
          <div class="bento-grid">
            <div class="bento-card bento-col-6 bento-tint-pink">
              <span class="chip chip-purple" style="align-self: flex-start; margin-bottom: 0.5rem;">Clinical Workforce</span>
              <h4 style="font-size: 1.3rem; margin-bottom: 0.5rem;">Specialized Clinical Nursing</h4>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.5;">ICU, Emergency, and surgical nursing practitioners for surge relief and planned facility placements.</p>
            </div>
            <div class="bento-card bento-col-6 bento-tint-yellow">
              <span class="chip chip-warning" style="align-self: flex-start; margin-bottom: 0.5rem;">Operations BPO</span>
              <h4 style="font-size: 1.3rem; margin-bottom: 0.5rem;">Healthcare Operational Support</h4>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.5;">Turnkey ward intake coordination, patient flow management, and clinical back-office administration.</p>
            </div>
            <div class="bento-card bento-col-4 bento-tint-mint">
              <span class="chip chip-success" style="align-self: flex-start; margin-bottom: 0.5rem;">Governance</span>
              <h4 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Quality & Compliance</h4>
              <p style="font-size: 0.813rem; color: var(--voak-muted);">Clinical governance advisory and regulatory readiness auditing.</p>
            </div>
            <div class="bento-card bento-col-4 bento-tint-sky">
              <span class="chip chip-info" style="align-self: flex-start; margin-bottom: 0.5rem;">Allied Health</span>
              <h4 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Allied Health Infrastructure</h4>
              <p style="font-size: 0.813rem; color: var(--voak-muted);">Physiotherapy, OT, and multidisciplinary healthcare team coordination.</p>
            </div>
            <div class="bento-card bento-col-4 bento-tint-purple">
              <span class="chip chip-purple" style="align-self: flex-start; margin-bottom: 0.5rem;">Surge Roster</span>
              <h4 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Clinical Roster Surge Support</h4>
              <p style="font-size: 0.813rem; color: var(--voak-muted);">Rapid mobilization for peak hospital demand and emergency cover.</p>
            </div>
            <div class="bento-card bento-col-4 bento-tint-amber">
              <span class="chip chip-warning" style="align-self: flex-start; margin-bottom: 0.5rem;">Credentialing</span>
              <h4 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Primary-Source Verification</h4>
              <p style="font-size: 0.813rem; color: var(--voak-muted);">Multi-tier credentialing, background checks, and license verification.</p>
            </div>
            <div class="bento-card bento-col-4 bento-tint-teal">
              <span class="chip chip-info" style="align-self: flex-start; margin-bottom: 0.5rem;">Logistics</span>
              <h4 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Facility Logistics Support</h4>
              <p style="font-size: 0.813rem; color: var(--voak-muted);">Clinical equipment transit and assistive mobility coordination.</p>
            </div>
            <div class="bento-card bento-col-4 bento-tint-lavender">
              <span class="chip chip-purple" style="align-self: flex-start; margin-bottom: 0.5rem;">Enterprise</span>
              <h4 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Health System Strategy</h4>
              <p style="font-size: 0.813rem; color: var(--voak-muted);">Enterprise workforce models for multi-hospital networks.</p>
            </div>
          </div>
        </div>

        <!-- Why Voak Split Section -->
        <div style="padding: 2rem 2rem 4rem 2rem;">
          <div class="why-voak-section">
            <div class="why-portrait-container">
              <div class="why-portrait-frame">
                <img src="assets/images/operations_manager.jpg" alt="Clinical Operations Manager" width="380" height="420">
              </div>
            </div>
            <div class="why-content">
              <span class="hero-pill-badge">Our Operational Distinction</span>
              <h2 style="font-size: 2.2rem; margin-bottom: 1rem; font-family: var(--font-serif); color: var(--voak-navy);">Why Healthcare Leaders Trust Voak</h2>
              <p style="color: var(--voak-muted); margin-bottom: 1.5rem; line-height: 1.6;">
                We combine deep clinical expertise with enterprise service reliability, providing healthcare organizations with dependable clinical workforce coverage and rigorous compliance oversight.
              </p>
              <div class="why-value-item">
                <div class="why-value-icon">&#10003;</div>
                <div>
                  <h4 style="font-size: 1.1rem; margin-bottom: 0.2rem;">Rigorous Primary-Source Verification</h4>
                  <p style="font-size: 0.875rem; color: var(--voak-muted);">Comprehensive verification of clinical registrations, police checks, and specialized competency credentials.</p>
                </div>
              </div>
              <div class="why-value-item">
                <div class="why-value-icon">&#10003;</div>
                <div>
                  <h4 style="font-size: 1.1rem; margin-bottom: 0.2rem;">Structured Service Level Agreements</h4>
                  <p style="font-size: 0.875rem; color: var(--voak-muted);">Transparent operational response commitments and structured clinical replacement protocols.</p>
                </div>
              </div>
              <div class="why-value-item">
                <div class="why-value-icon">&#10003;</div>
                <div>
                  <h4 style="font-size: 1.1rem; margin-bottom: 0.2rem;">Dedicated Institutional Account Management</h4>
                  <p style="font-size: 0.875rem; color: var(--voak-muted);">Experienced B2B healthcare account coordinators and clinical advisors assigned to each facility.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      \`;

    case 'PUB-02':
      return \`
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
              <span class="preview-badge" style="background: var(--voak-purple-cta); color: #FFF;">Governance & Direction</span>
              <h2 style="font-family: var(--font-serif); font-size: 2.2rem; color: var(--voak-navy); margin-top: 0.5rem;">Executive Leadership Team</h2>
              <p style="color: var(--voak-muted);">Dedicated clinical and operational executives guiding institutional healthcare partnerships.</p>
            </div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
              <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 1.75rem; text-align: center;">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--voak-canvas); margin: 0 auto 1rem auto; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; color: var(--voak-purple-cta);">EV</div>
                <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin-bottom: 0.25rem;">Dr. Eleanor Vance</h4>
                <div style="font-size: 0.813rem; color: var(--voak-purple-cta); font-weight: 600; margin-bottom: 0.75rem;">Chief Executive Officer</div>
                <p style="font-size: 0.813rem; color: var(--voak-muted); line-height: 1.5;">20+ years executive hospital administration and clinical governance leadership.</p>
              </div>
              <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 1.75rem; text-align: center;">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--voak-canvas); margin: 0 auto 1rem auto; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; color: var(--voak-mint);">MV</div>
                <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin-bottom: 0.25rem;">Dr. Marcus Bradley</h4>
                <div style="font-size: 0.813rem; color: var(--voak-mint); font-weight: 600; margin-bottom: 0.75rem;">Clinical Operations Director</div>
                <p style="font-size: 0.813rem; color: var(--voak-muted); line-height: 1.5;">Specialist in acute roster continuity, workforce mobilization, and NSQHS compliance.</p>
              </div>
              <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 1.75rem; text-align: center;">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--voak-canvas); margin: 0 auto 1rem auto; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; color: #B45309;">SJ</div>
                <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin-bottom: 0.25rem;">Sarah Jenkins</h4>
                <div style="font-size: 0.813rem; color: #B45309; font-weight: 600; margin-bottom: 0.75rem;">Director of People & Culture</div>
                <p style="font-size: 0.813rem; color: var(--voak-muted); line-height: 1.5;">Leads healthcare recruitment ethics, CPD programs, and clinician well-being initiatives.</p>
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
                <p style="font-size: 0.75rem; color: var(--voak-muted); margin: 0;">Dedicated account coordinators for every institutional partner.</p>
              </div>
            </div>
          </div>
        </div>
      \`;

    case 'PUB-03':
      return \`
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
      \`;

    case 'PUB-04':
      return \`
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
                <li>Mandatory national police and background checks</li>
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
      \`;

    case 'PUB-05':
      return \`
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
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin-bottom: 1.25rem;">Geriatric care nurses, medication administration leads, and 24/7 continuous clinical care rosters.</p>
              <button class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;" onclick="showProposalWizard()">Scope Sector &rarr;</button>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; border-top: 4px solid var(--voak-mint);">
              <span class="card-tag">Community</span>
              <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin: 0.75rem 0 0.5rem 0;">Community & Disability (NDIS)</h3>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin-bottom: 1.25rem;">Specialized clinical supports, complex behavioral care, and mobile allied health teams.</p>
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
      \`;

    case 'PUB-06':
      return \`
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
                <li>Dedicated clinical account manager assigned to facility</li>
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
      \`;

    case 'PUB-07':
      return \`
        <div style="background: var(--voak-canvas); padding: 4rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-yellow); color: var(--voak-navy);">Strategic Differentiation</span>
          <h1 style="font-family: var(--font-serif); font-size: 3rem; color: var(--voak-navy); margin: 1rem 0;">Why Healthcare Leaders Choose Voak</h1>
          <p style="max-width: 680px; margin: 0 auto; color: var(--voak-muted); font-size: 1.125rem;">
            A structured comparison between Voak's dedicated healthcare operational model and traditional ad-hoc staffing agencies.
          </p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 1100px; margin: 0 auto;">
          <!-- Comparison Table -->
          <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); overflow: hidden; margin-bottom: 3rem; box-shadow: var(--shadow-sm);">
            <table class="admin-table" style="margin: 0;">
              <thead>
                <tr style="background: #F8FAFC;">
                  <th style="padding: 1.25rem 1.5rem; width: 30%;">Operational Dimension</th>
                  <th style="padding: 1.25rem 1.5rem; width: 35%; color: var(--voak-purple-cta); font-weight: 700;">Voak Health Care Dedicated Model</th>
                  <th style="padding: 1.25rem 1.5rem; width: 35%;">Traditional Ad-Hoc Agencies</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td style="padding: 1.25rem 1.5rem;"><strong>Clinician Credentialing</strong></td>
                  <td style="padding: 1.25rem 1.5rem; background: #FAF9FE;"><span class="chip chip-success">Primary-Source Verified</span><br><small style="color: var(--voak-muted);">100% verified against primary source boards before facility placement.</small></td>
                  <td style="padding: 1.25rem 1.5rem;"><span class="chip chip-warning">Self-Reported / Sampled</span><br><small style="color: var(--voak-muted);">Frequent reliance on candidate-submitted copies without primary validation.</small></td>
                </tr>
                <tr>
                  <td style="padding: 1.25rem 1.5rem;"><strong>Roster Continuity & SLAs</strong></td>
                  <td style="padding: 1.25rem 1.5rem; background: #FAF9FE;"><span class="chip chip-purple">Structured Commitments</span><br><small style="color: var(--voak-muted);">Dedicated backup pools and transparent response escalation.</small></td>
                  <td style="padding: 1.25rem 1.5rem;"><span class="chip chip-error">Best-Efforts Only</span><br><small style="color: var(--voak-muted);">High last-minute cancellation rates with zero formal replacement SLA.</small></td>
                </tr>
                <tr>
                  <td style="padding: 1.25rem 1.5rem;"><strong>Account Governance</strong></td>
                  <td style="padding: 1.25rem 1.5rem; background: #FAF9FE;"><span class="chip chip-info">Dedicated Clinical Lead</span><br><small style="color: var(--voak-muted);">Named B2B coordinator and clinical advisor assigned to each facility.</small></td>
                  <td style="padding: 1.25rem 1.5rem;"><span class="chip chip-warning">Call-Center Brokerage</span><br><small style="color: var(--voak-muted);">Transactional bookings with rotating, unfamiliar sales representatives.</small></td>
                </tr>
                <tr>
                  <td style="padding: 1.25rem 1.5rem;"><strong>Commercial Transparency</strong></td>
                  <td style="padding: 1.25rem 1.5rem; background: #FAF9FE;"><span class="chip chip-success">Fixed B2B Fee Schedule</span><br><small style="color: var(--voak-muted);">Pre-agreed transparent rates with no surge pricing gouging.</small></td>
                  <td style="padding: 1.25rem 1.5rem;"><span class="chip chip-error">Unpredictable Surge Fees</span><br><small style="color: var(--voak-muted);">Aggressive multiplier markups during peak hospital crises.</small></td>
                </tr>
              </tbody>
            </table>
          </div>
          <!-- CTA -->
          <div style="text-align: center;">
            <button class="btn btn-primary" style="padding: 0.85rem 2rem; font-size: 1.05rem;" onclick="showProposalWizard()">Request Formal Comparison & RFP &rarr;</button>
          </div>
        </div>
      \`;

    case 'PUB-08':
      return \`
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
                <p style="color: var(--voak-muted); line-height: 1.6; margin: 0;">Verified clinical personnel are mobilized with facility-specific orientation, electronic timesheet integration, and dedicated shift coordinator oversight.</p>
              </div>
            </div>

            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; display: flex; gap: 2rem; align-items: flex-start;">
              <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--voak-mint); color: var(--voak-navy); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.25rem; flex-shrink: 0;">04</div>
              <div>
                <span class="chip chip-success" style="margin-bottom: 0.5rem;">Continuous Governance</span>
                <h3 style="font-size: 1.4rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Ongoing Clinical Review & SLA Auditing</h3>
                <p style="color: var(--voak-muted); line-height: 1.6; margin: 0;">Quarterly governance meetings, shift fulfillment metric reviews, peer clinical assessments, and continuous roster optimization.</p>
              </div>
            </div>
          </div>
        </div>
      \`;

    case 'PUB-09':
      return \`
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
            <h3 style="font-size: 1.4rem; color: var(--voak-navy); margin-bottom: 1.5rem;">5-Point Primary-Source Credentialing Protocol</h3>
            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; text-align: center;">
              <div style="background: #F8FAFC; padding: 1rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
                <div style="font-weight: 800; color: var(--voak-purple-cta); font-size: 1.2rem;">01</div>
                <strong style="display: block; font-size: 0.813rem; margin: 0.25rem 0;">Identity</strong>
                <span style="font-size: 0.7rem; color: var(--voak-muted);">100-point biometric & photo ID checks</span>
              </div>
              <div style="background: #F8FAFC; padding: 1rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
                <div style="font-weight: 800; color: var(--voak-purple-cta); font-size: 1.2rem;">02</div>
                <strong style="display: block; font-size: 0.813rem; margin: 0.25rem 0;">Registration</strong>
                <span style="font-size: 0.7rem; color: var(--voak-muted);">Direct primary-source board validation</span>
              </div>
              <div style="background: #F8FAFC; padding: 1rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
                <div style="font-weight: 800; color: var(--voak-purple-cta); font-size: 1.2rem;">03</div>
                <strong style="display: block; font-size: 0.813rem; margin: 0.25rem 0;">Clearances</strong>
                <span style="font-size: 0.7rem; color: var(--voak-muted);">National police & child protection</span>
              </div>
              <div style="background: #F8FAFC; padding: 1rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
                <div style="font-weight: 800; color: var(--voak-purple-cta); font-size: 1.2rem;">04</div>
                <strong style="display: block; font-size: 0.813rem; margin: 0.25rem 0;">Health</strong>
                <span style="font-size: 0.7rem; color: var(--voak-muted);">Immunization & fit-to-practice</span>
              </div>
              <div style="background: #F8FAFC; padding: 1rem; border-radius: var(--radius-md); border: 1px solid #E2E8F0;">
                <div style="font-weight: 800; color: var(--voak-purple-cta); font-size: 1.2rem;">05</div>
                <strong style="display: block; font-size: 0.813rem; margin: 0.25rem 0;">References</strong>
                <span style="font-size: 0.7rem; color: var(--voak-muted);">Verified clinical peer references</span>
              </div>
            </div>
          </div>
          <!-- Disclaimer -->
          <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: var(--radius-lg); padding: 1.5rem; font-size: 0.875rem; color: #92400E; line-height: 1.6;">
            <strong>Mandatory Governance Notice:</strong> Voak Health Care operates as an enterprise healthcare workforce and operational infrastructure partner. Patient clinical treatment decisions remain under the primary medical authority and oversight of the host healthcare facility.
          </div>
        </div>
      \`;

    case 'PUB-10':
      return \`
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
      \`;

    case 'PUB-11':
      return \`
        <div style="background: var(--voak-canvas); padding: 4rem 2.5rem; text-align: center;">
          <span class="preview-badge" style="background: var(--voak-pink); color: var(--voak-navy);">Demonstrated Impact</span>
          <h1 style="font-family: var(--font-serif); font-size: 3rem; color: var(--voak-navy); margin: 1rem 0;">Operational Case Studies</h1>
          <p style="max-width: 680px; margin: 0 auto; color: var(--voak-muted); font-size: 1.125rem;">
            Real-world evidence of how Voak Health Care stabilizes hospital rosters, maintains compliance, and mitigates staffing risk.
          </p>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 1100px; margin: 0 auto;">
          <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-bottom: 3rem;">
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; display: flex; flex-direction: column;">
              <span class="chip chip-purple" style="align-self: flex-start; margin-bottom: 0.75rem;">Acute Hospital Network</span>
              <h3 style="font-size: 1.3rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Critical Care Shift Stabilization</h3>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5; margin-bottom: 1rem;"><strong>Challenge:</strong> 35% roster vacancy across intensive care units during seasonal admissions surge.</p>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5; margin-bottom: 1rem;"><strong>Intervention:</strong> Rapid deployment of 18 pre-credentialed critical care nurse specialists within 72 hours.</p>
              <div style="background: #F0FDF4; border: 1px solid #BBF7D0; padding: 0.75rem; border-radius: var(--radius-sm); font-size: 0.813rem; color: #166534; margin-top: auto;">
                <strong>Outcome:</strong> 100% shift fulfillment achieved with zero ward bed closures.
              </div>
            </div>

            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; display: flex; flex-direction: column;">
              <span class="chip chip-warning" style="align-self: flex-start; margin-bottom: 0.75rem;">Regional Health Service</span>
              <h3 style="font-size: 1.3rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Emergency Department Continuity</h3>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5; margin-bottom: 1rem;"><strong>Challenge:</strong> Isolated regional facility facing closure of night emergency triage due to acute staff departure.</p>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5; margin-bottom: 1rem;"><strong>Intervention:</strong> Structured rotational clinical roster deployed with dedicated emergency coordinators.</p>
              <div style="background: #F0FDF4; border: 1px solid #BBF7D0; padding: 0.75rem; border-radius: var(--radius-sm); font-size: 0.813rem; color: #166534; margin-top: auto;">
                <strong>Outcome:</strong> Continuous 24/7 emergency capability maintained across 12-week transition.
              </div>
            </div>

            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-xl); padding: 2rem; display: flex; flex-direction: column;">
              <span class="chip chip-info" style="align-self: flex-start; margin-bottom: 0.75rem;">Residential Care Group</span>
              <h3 style="font-size: 1.3rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Compliance Audit Turnaround</h3>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5; margin-bottom: 1rem;"><strong>Challenge:</strong> Facility requiring urgent clinical documentation and medication governance alignment.</p>
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5; margin-bottom: 1rem;"><strong>Intervention:</strong> Dedicated clinical audit team deployed to conduct comprehensive file reviews and roster corrections.</p>
              <div style="background: #F0FDF4; border: 1px solid #BBF7D0; padding: 0.75rem; border-radius: var(--radius-sm); font-size: 0.813rem; color: #166534; margin-top: auto;">
                <strong>Outcome:</strong> Full accreditation compliance verified during unannounced quality review.
              </div>
            </div>
          </div>
        </div>
      \`;

    case 'PUB-12':
      return \`
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
              <p style="font-size: 0.85rem; color: var(--voak-muted); line-height: 1.5;">A dedicated clinical coordinator on-call 24/7 to support your facility placements.</p>
            </div>
          </div>

          <!-- Open Vacancies List -->
          <h3 style="font-size: 1.4rem; color: var(--voak-navy); margin-bottom: 1.5rem;">Current Open Clinical Positions</h3>
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-md); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
              <div>
                <span class="chip chip-purple" style="font-size: 0.7rem; margin-bottom: 0.25rem;">Acute Hospital Network</span>
                <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin: 0.25rem 0;">Senior Clinical Nurse Specialist (ICU / Emergency)</h4>
                <div style="font-size: 0.813rem; color: var(--voak-muted);">Full-time / Rotational Shifts &bull; Reference: JOB-2026-CNS</div>
              </div>
              <button class="btn btn-primary" onclick="inspectPage('PUB-13')">View Role & Apply &rarr;</button>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-md); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
              <div>
                <span class="chip chip-warning" style="font-size: 0.7rem; margin-bottom: 0.25rem;">Aged Care Services</span>
                <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin: 0.25rem 0;">Clinical Care Coordinator (Geriatric Nursing)</h4>
                <div style="font-size: 0.813rem; color: var(--voak-muted);">Permanent Placement &bull; Reference: JOB-2026-CCC</div>
              </div>
              <button class="btn btn-outline" onclick="inspectPage('PUB-13')">View Role & Apply &rarr;</button>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-md); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
              <div>
                <span class="chip chip-info" style="font-size: 0.7rem; margin-bottom: 0.25rem;">Allied Health</span>
                <h4 style="font-size: 1.2rem; color: var(--voak-navy); margin: 0.25rem 0;">Senior Occupational Therapist / Rehabilitation Lead</h4>
                <div style="font-size: 0.813rem; color: var(--voak-muted);">Flexible Days &bull; Reference: JOB-2026-OT</div>
              </div>
              <button class="btn btn-outline" onclick="inspectPage('PUB-13')">View Role & Apply &rarr;</button>
            </div>
          </div>
        </div>
      \`;

    case 'PUB-13':
      return \`
        <div style="background: #FFF; padding: 3rem 2.5rem; border-bottom: 1px solid var(--voak-border-subtle);">
          <div style="max-width: 900px; margin: 0 auto;">
            <span class="preview-badge" style="background: var(--voak-mint); color: #FFF;">Clinical Nurse Specialist (ICU / Acute)</span>
            <h1 style="font-family: var(--font-serif); font-size: 2.75rem; color: var(--voak-navy); margin: 0.75rem 0;">Senior Clinical Nurse Specialist</h1>
            <p style="font-size: 1rem; color: var(--voak-muted);">Sector: Acute Hospital Networks &bull; Type: Full-time / Shift Roster &bull; Reference: JOB-2026-CNS</p>
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
                  <input type="email" class="form-control" placeholder="alex.taylor@email.com" required>
                </div>
                <div class="form-group">
                  <label class="form-label form-label-required">Phone (International Format)</label>
                  <input type="tel" class="form-control" placeholder="+61 412 345 678" required>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Professional Registration Number (Optional / Jurisdiction Specific)</label>
                <input type="text" class="form-control" placeholder="e.g. AHPRA / Medical Board ID if applicable to confirmed jurisdiction">
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
      \`;

    case 'PUB-14':
      return \`
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
                <span style="font-size: 0.813rem; color: var(--voak-muted);">Dr. Eleanor Vance &bull; 6 min read</span>
                <button class="btn btn-primary" onclick="inspectPage('PUB-15')">Read Analysis &rarr;</button>
              </div>
            </div>
            <div style="background: var(--voak-canvas); display: flex; align-items: center; justify-content: center; padding: 2rem;">
              <div style="width: 80%; height: 240px; background: #FFF; border-radius: var(--radius-lg); border: 1px solid var(--voak-border-subtle); padding: 1.5rem; display: flex; flex-direction: column; justify-content: center;">
                <strong style="color: var(--voak-navy); font-size: 1.1rem; margin-bottom: 0.5rem;">Key Takeaway Preview:</strong>
                <p style="font-size: 0.813rem; color: var(--voak-muted); margin: 0; line-height: 1.5;">"Facilities adopting dedicated clinical partner frameworks experience significantly lower vacancy volatility than those relying on spot brokerage."</p>
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
      \`;

    case 'PUB-15':
      return \`
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
              <div style="width: 44px; height: 44px; border-radius: 50%; background: var(--voak-purple-cta); color: #FFF; display: flex; align-items: center; justify-content: center; font-weight: 700;">EV</div>
              <div>
                <strong style="color: var(--voak-navy); font-size: 0.938rem;">Dr. Eleanor Vance</strong>
                <div style="font-size: 0.813rem; color: var(--voak-muted);">Chief Executive &bull; Published October 2026</div>
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
      \`;

    case 'PUB-16':
      return \`
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
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">How quickly can Voak deploy clinical staff to a hospital facility?</h4>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin: 0;">
                For institutional partners with established service agreements, urgent surge deployments can typically be mobilized within 24 to 48 hours from our pre-credentialed clinician pool.
              </p>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-lg); padding: 1.5rem;">
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">How are clinician credentials and registrations verified?</h4>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin: 0;">
                Every clinician undergoes a 5-point primary-source check directly with relevant medical boards, national police history checks, working with children verifications, immunization compliance, and peer clinical references.
              </p>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-lg); padding: 1.5rem;">
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">What commercial contract structures do you offer?</h4>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin: 0;">
                We offer flexible B2B engagement models including Master Service Agreements (MSA), rotational surge cover frameworks, and dedicated facility managed services with transparent fixed fee schedules.
              </p>
            </div>
            <div style="background: #FFF; border: 1px solid var(--voak-border-subtle); border-radius: var(--radius-lg); padding: 1.5rem;">
              <h4 style="font-size: 1.15rem; color: var(--voak-navy); margin-bottom: 0.5rem;">How are clinical timesheets and billing managed?</h4>
              <p style="font-size: 0.875rem; color: var(--voak-muted); line-height: 1.6; margin: 0;">
                Timesheets are verified electronically against hospital ward shift logs and submitted via weekly itemized invoicing with full audit trails.
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
      \`;

    case 'PUB-17':
      return \`
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
                  <input type="email" class="form-control" placeholder="j.smith@hospital.org" required>
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
      \`;

    case 'PUB-18':
      return \`
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
                <input type="email" class="form-control" placeholder="s.mitchell@healthdistrict.gov" required>
              </div>
              <div class="form-group">
                <label class="form-label form-label-required">Collaboration Overview</label>
                <textarea class="form-control" rows="4" placeholder="Detail proposed strategic scope, clinical workforce integration, or facility operational goals..." required></textarea>
              </div>
              <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Strategic Partnership Proposal &rarr;</button>
            </form>
          </div>
        </div>
      \`;

    case 'PUB-19':
      return \`
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
      \`;

    case 'PUB-20':
      return \`
        <div style="background: #FFF; padding: 3.5rem 2.5rem; border-bottom: 1px solid var(--voak-border-subtle);">
          <div style="max-width: 800px; margin: 0 auto;">
            <span class="chip chip-purple" style="margin-bottom: 0.75rem;">Legal & Compliance &bull; FR-PUB-022</span>
            <h1 style="font-family: var(--font-serif); font-size: 2.75rem; color: var(--voak-navy); margin-bottom: 0.5rem;">Privacy Policy</h1>
            <p style="color: var(--voak-muted); font-size: 0.875rem;">Last Updated: October 2026 &bull; Compliant with Australian Privacy Principles (APPs) and applicable data protection regulations.</p>
          </div>
        </div>
        <div style="padding: 3rem 2.5rem; max-width: 800px; margin: 0 auto; line-height: 1.7; color: var(--voak-navy);">
          <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin-bottom: 0.75rem;">1. Commitment to Healthcare Privacy</h3>
          <p style="color: var(--voak-muted); margin-bottom: 1.5rem;">
            Voak Health Care is committed to protecting the privacy of healthcare professionals, institutional partner representatives, and visitors. This policy outlines how we collect, store, and safeguard personal and commercial information.
          </p>
          <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin-bottom: 0.75rem;">2. Clinical Candidate Data Quarantine</h3>
          <p style="color: var(--voak-muted); margin-bottom: 1.5rem;">
            Candidate recruitment data (resumes, clinical registrations, and referee contact details) submitted via FORM-PUB-04 are stored in a dedicated, access-restricted HR repository accessible exclusively by authorized HR personnel.
          </p>
          <h3 style="font-size: 1.35rem; color: var(--voak-navy); margin-bottom: 0.75rem;">3. Zero Patient Health Records on Web Infrastructure</h3>
          <p style="color: var(--voak-muted); margin-bottom: 1.5rem;">
            The public web platform does not ingest, process, or store individual patient electronic health records (EHR) or identifiable clinical histories.
          </p>
          <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: var(--radius-md); padding: 1.25rem; font-size: 0.813rem; color: #64748B;">
            <strong>Data Controller Variable:</strong> Entity name and physical registered office are CMS-configurable variables subject to formal corporate confirmation.
          </div>
        </div>
      \`;

    case 'PUB-21':
      return \`
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
      \`;

    case 'PUB-22':
      return \`
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
      \`;

    default:
      return \`<div style="padding: 3rem; text-align: center;">Page loaded</div>\`;
  }
}`;

// Find start and end of renderPageSpecificBody in content
const startIdx = content.indexOf('function renderPageSpecificBody(page) {');
const endMarker = '/* --------------------------------------------------------------------------\n   5. ALL 19 ADMIN MODULES DISPLAY & WORKFLOWS';
const endIdx = content.indexOf(endMarker);

if (startIdx === -1 || endIdx === -1) {
  console.error('Markers not found!');
  process.exit(1);
}

const updatedContent = content.substring(0, startIdx) + newFunction + '\n\n' + content.substring(endIdx);
fs.writeFileSync(appJsPath, updatedContent, 'utf8');
console.log('Successfully updated app.js with ALL 22 individually-designed public page layouts!');
