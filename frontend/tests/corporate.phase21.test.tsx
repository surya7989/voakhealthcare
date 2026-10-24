import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent, within } from '@testing-library/react';
import { Router } from '../src/shared/router/Router';
import { PublicApp } from '../src/apps/public/PublicApp';
import { AboutPage } from '../src/apps/public/pages/AboutPage';
import { WhyChooseVoakPage } from '../src/apps/public/pages/WhyChooseVoakPage';
import { HowWeWorkPage } from '../src/apps/public/pages/HowWeWorkPage';
import { QualityCompliancePage } from '../src/apps/public/pages/QualityCompliancePage';
import { apiClient } from '../src/shared/api/apiClient';

describe('Phase 21 — Corporate Information Pages Suite', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  describe('PAGE-PUB-02: About Us (/about)', () => {
    it('renders all canonical sections and accessibility landmarks with fallback data', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/about">
          <AboutPage />
        </Router>
      );

      // Landmarks
      expect(screen.getByRole('banner')).toBeInTheDocument();
      expect(screen.getByRole('main')).toBeInTheDocument();
      expect(screen.getByRole('contentinfo')).toBeInTheDocument();

      // Hero H1 and Breadcrumbs
      expect(
        screen.getByRole('heading', {
          level: 1,
          name: /Pioneering Operational Reliability in Australian Healthcare/i,
        })
      ).toBeInTheDocument();
      expect(screen.getByText('About Us')).toBeInTheDocument();

      // Mission & Core Values
      expect(screen.getByText(/Mission, Vision & Core Values/i)).toBeInTheDocument();
      expect(screen.getByText(/Clinical Governance & Rigor/i)).toBeInTheDocument();
      expect(screen.getByText(/Workforce Resilience/i)).toBeInTheDocument();

      // Leadership Team
      expect(screen.getByText(/Leadership & Clinical Advisory Board/i)).toBeInTheDocument();
      expect(screen.getAllByText('Dr. Alistair Vance').length).toBeGreaterThanOrEqual(1);
      expect(screen.getByText(/Chief Clinical Officer & Governance Chair/i)).toBeInTheDocument();

      // National Footprint & Milestones
      expect(screen.getByText(/National Healthcare Footprint/i)).toBeInTheDocument();
      expect(screen.getByText(/Our Journey & Strategic Milestones/i)).toBeInTheDocument();

      // Non-clinical disclaimer banner
      expect(screen.getByText(/Operational Scope & Legal Boundary Notice/i)).toBeInTheDocument();
      expect(
        screen.getByText(/does not provide direct patient clinical care, emergency medical triage, or patient booking channels/i)
      ).toBeInTheDocument();
    });

    it('opens and closes the LeadershipModal with accessible dialog behavior and Escape dismissal', async () => {
      render(
        <Router initialPath="/about">
          <AboutPage />
        </Router>
      );

      // Verify modal is initially closed
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

      // Click biography button for first leader
      const bioButtons = screen.getAllByRole('button', { name: /Read Full Biography/i });
      expect(bioButtons.length).toBeGreaterThan(0);
      fireEvent.click(bioButtons[0]);

      // Modal is open
      const dialog = screen.getByRole('dialog');
      expect(dialog).toBeInTheDocument();
      expect(dialog).toHaveAttribute('aria-modal', 'true');
      expect(within(dialog).getByText('Dr. Alistair Vance')).toBeInTheDocument();
      expect(within(dialog).getByText(/Executive & Clinical Background/i)).toBeInTheDocument();

      // Dismiss via Escape key on document
      fireEvent.keyDown(document, { key: 'Escape', code: 'Escape' });
      await waitFor(() => {
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      });

      // Re-open and dismiss via Close button
      fireEvent.click(bioButtons[0]);
      expect(screen.getByRole('dialog')).toBeInTheDocument();
      const closeBtn = screen.getByRole('button', { name: /Close executive biography modal/i });
      fireEvent.click(closeBtn);
      await waitFor(() => {
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      });
    });

    it('injects document title and schema.org AboutPage structured data', async () => {
      render(
        <Router initialPath="/about">
          <AboutPage />
        </Router>
      );

      await waitFor(() => {
        expect(document.title).toContain('About Voak Health Care');
      });

      const jsonLd = document.getElementById('seo-json-ld');
      expect(jsonLd).toBeInTheDocument();
      const parsed = JSON.parse(jsonLd?.textContent || '{}');
      expect(parsed['@type']).toBe('AboutPage');
      expect(parsed.name).toContain('About Voak Health Care');
    });

    it('hydrates with CMS API data when available', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValue({
        success: true,
        data: {
          page: {
            title: 'Custom About Title From CMS',
            meta_description: 'Custom CMS description for About Us.',
          },
        },
      });

      render(
        <Router initialPath="/about">
          <AboutPage />
        </Router>
      );

      await waitFor(() => {
        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
          'Custom About Title From CMS'
        );
      });
    });
  });

  describe('PAGE-PUB-07: Why Choose Voak (/why-choose-voak)', () => {
    it('renders comparative matrix with table headers and responsive cards', async () => {
      render(
        <Router initialPath="/why-choose-voak">
          <WhyChooseVoakPage />
        </Router>
      );

      // Hero H1
      expect(
        screen.getByRole('heading', {
          level: 1,
          name: /Objective Institutional Justification for Selecting Voak/i,
        })
      ).toBeInTheDocument();

      // 4 Pillars
      expect(screen.getByText(/1\. Clinical Governance Rigor/i)).toBeInTheDocument();
      expect(screen.getByText(/2\. Operational Speed & Certainty/i)).toBeInTheDocument();
      expect(screen.getByText(/3\. Fixed SLA Pricing Transparency/i)).toBeInTheDocument();
      expect(screen.getByText(/4\. Cohort Continuity & Retention/i)).toBeInTheDocument();

      // Comparative Table headers
      expect(screen.getByRole('table')).toBeInTheDocument();
      const columnHeaders = screen.getAllByRole('columnheader');
      expect(columnHeaders.length).toBe(4);
      expect(columnHeaders[0]).toHaveTextContent('Operational Dimension');
      expect(columnHeaders[1]).toHaveTextContent('Voak Managed Services');
      expect(columnHeaders[2]).toHaveTextContent('Traditional Agency');
      expect(columnHeaders[3]).toHaveTextContent('In-House Coordination');

      // Row headers
      const rowHeaders = screen.getAllByRole('rowheader');
      expect(rowHeaders.length).toBeGreaterThanOrEqual(5);

      // Responsive mobile card view (rendered for smaller breakpoints)
      expect(screen.getAllByText(/Voak Approach/i).length).toBeGreaterThanOrEqual(5);
      expect(screen.getAllByText(/Traditional Agency/i).length).toBeGreaterThanOrEqual(5);

      // Enterprise Risk Management & QA
      expect(screen.getByText(/Operational Continuity & Risk Mitigation/i)).toBeInTheDocument();
      expect(screen.getByText(/Continuous Compliance Audits/i)).toBeInTheDocument();
    });


    it('injects document title and schema.org WebPage structured data', async () => {
      render(
        <Router initialPath="/why-choose-voak">
          <WhyChooseVoakPage />
        </Router>
      );

      await waitFor(() => {
        expect(document.title).toContain('Why Choose Voak');
      });

      const jsonLd = document.getElementById('seo-json-ld');
      expect(jsonLd).toBeInTheDocument();
      const parsed = JSON.parse(jsonLd?.textContent || '{}');
      expect(parsed['@type']).toBe('WebPage');
    });
  });

  describe('PAGE-PUB-08: How We Work (/how-we-work)', () => {
    it('renders 4-stage sequential implementation stepper and updates active stage details', async () => {
      render(
        <Router initialPath="/how-we-work">
          <HowWeWorkPage />
        </Router>
      );

      // Hero H1
      expect(
        screen.getByRole('heading', {
          level: 1,
          name: /A Structured 4-Stage Healthcare Operational Framework/i,
        })
      ).toBeInTheDocument();

      // Stepper ordered list
      const stepperList = screen.getByRole('list', { name: /Operational Framework Stages/i });
      expect(stepperList).toBeInTheDocument();

      // Initial active stage: Stage 1
      const stage1Button = screen.getByRole('button', { name: /Diagnostic Assessment & Operational Audit/i });
      expect(stage1Button).toHaveAttribute('aria-current', 'step');
      expect(screen.getAllByText('Diagnostic Assessment & Operational Audit').length).toBeGreaterThanOrEqual(1);
      expect(screen.getByText(/Analyze historical shift vacancy rates/i)).toBeInTheDocument();

      // Click Stage 2 button
      const stage2Button = screen.getByRole('button', { name: /Bespoke Solution Architecture & SLA Design/i });
      fireEvent.click(stage2Button);

      // Active state shifts to Stage 2
      expect(stage2Button).toHaveAttribute('aria-current', 'step');
      expect(stage1Button).not.toHaveAttribute('aria-current');
      expect(screen.getAllByText('Bespoke Solution Architecture & SLA Design').length).toBeGreaterThanOrEqual(1);
      expect(screen.getByText(/Formulate transparent, fixed rate cards/i)).toBeInTheDocument();

      // Click Stage 3 button
      const stage3Button = screen.getByRole('button', { name: /Phased Integration & Credentialed Deployment/i });
      fireEvent.click(stage3Button);

      expect(stage3Button).toHaveAttribute('aria-current', 'step');
      expect(screen.getAllByText('Phased Integration & Credentialed Deployment').length).toBeGreaterThanOrEqual(1);

      // Click Stage 4 button
      const stage4Button = screen.getByRole('button', { name: /Continuous Quality Governance & Performance Telemetry/i });
      fireEvent.click(stage4Button);
      expect(stage4Button).toHaveAttribute('aria-current', 'step');
      expect(screen.getAllByText('Continuous Quality Governance & Performance Telemetry').length).toBeGreaterThanOrEqual(1);
    });

    it('injects document title and schema.org HowTo structured data', async () => {
      render(
        <Router initialPath="/how-we-work">
          <HowWeWorkPage />
        </Router>
      );

      await waitFor(() => {
        expect(document.title).toContain('How We Work');
      });

      const jsonLd = document.getElementById('seo-json-ld');
      expect(jsonLd).toBeInTheDocument();
      const parsed = JSON.parse(jsonLd?.textContent || '{}');
      expect(parsed['@type']).toBe('HowTo');
      expect(parsed.step?.length).toBe(4);
    });
  });

  describe('PAGE-PUB-09: Quality & Compliance (/quality-compliance)', () => {
    it('renders high-contrast non-clinical care legal notice banner and AHPRA credentialing accordions', async () => {
      render(
        <Router initialPath="/quality-compliance">
          <QualityCompliancePage />
        </Router>
      );

      // Hero H1
      expect(
        screen.getByRole('heading', {
          level: 1,
          name: /Clinical Quality & Regulatory Compliance Standards/i,
        })
      ).toBeInTheDocument();

      // Mandatory Legal Notice Banner
      const legalAlert = screen.getByRole('alert', { name: /Mandatory Non-Clinical Care Legal Disclaimer/i });
      expect(legalAlert).toBeInTheDocument();
      expect(screen.getByText(/Statutory Boundary Notice/i)).toBeInTheDocument();
      expect(
        screen.getByText(/Voak Health Care is a specialized B2B healthcare operational services provider and does not provide direct patient clinical care/i)
      ).toBeInTheDocument();

      // 5-Tier Credentialing Accordion
      expect(screen.getByText(/5-Tier Clinical Credentialing Protocol/i)).toBeInTheDocument();
      expect(screen.getByText(/AHPRA Registration & Conditions Monitored Continuously/i)).toBeInTheDocument();
      expect(screen.getByText(/Identity Verification & Legal Right to Work/i)).toBeInTheDocument();
      expect(screen.getByText(/Criminal History & Vulnerable Persons Checks/i)).toBeInTheDocument();
      expect(screen.getByText(/Immunization, Serology & Infection Prevention/i)).toBeInTheDocument();
      expect(screen.getByText(/Multi-Tier Clinical Reference & Competency Audits/i)).toBeInTheDocument();

      // Regulatory Standards Alignment
      expect(screen.getByText(/Alignment with Australian Healthcare Quality Standards/i)).toBeInTheDocument();
      expect(screen.getByText('NSQHS Standards')).toBeInTheDocument();
      expect(screen.getByText('Australian Privacy Principles')).toBeInTheDocument();

      // Clinical Incident Escalation Matrix
      expect(screen.getByText(/Clinical Incident Management & Escalation Framework/i)).toBeInTheDocument();
    });

    it('injects document title and schema.org MedicalWebPage structured data', async () => {
      render(
        <Router initialPath="/quality-compliance">
          <QualityCompliancePage />
        </Router>
      );

      await waitFor(() => {
        expect(document.title).toContain('Clinical Quality & Regulatory Compliance Standards');
      });

      const jsonLd = document.getElementById('seo-json-ld');
      expect(jsonLd).toBeInTheDocument();
      const parsed = JSON.parse(jsonLd?.textContent || '{}');
      expect(parsed['@type']).toBe('MedicalWebPage');
    });
  });

  describe('PublicApp Integration & Header Active Navigation', () => {
    it('routes correctly to each corporate page through PublicApp router', () => {
      const { unmount: u1 } = render(
        <Router initialPath="/about">
          <PublicApp />
        </Router>
      );
      expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(/Pioneering Operational Reliability/i);
      u1();

      const { unmount: u2 } = render(
        <Router initialPath="/why-choose-voak">
          <PublicApp />
        </Router>
      );
      expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(/Objective Institutional Justification/i);
      u2();

      const { unmount: u3 } = render(
        <Router initialPath="/how-we-work">
          <PublicApp />
        </Router>
      );
      expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(/A Structured 4-Stage Healthcare Operational Framework/i);
      u3();

      const { unmount: u4 } = render(
        <Router initialPath="/quality-compliance">
          <PublicApp />
        </Router>
      );
      expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(/Clinical Quality & Regulatory Compliance Standards/i);
      u4();
    });

    it('gracefully handles API network errors across pages without breaking UI', async () => {
      vi.spyOn(apiClient, 'get').mockRejectedValue(new Error('500 Service Unavailable'));

      render(
        <Router initialPath="/quality-compliance">
          <PublicApp />
        </Router>
      );

      await waitFor(() => {
        expect(
          screen.getByRole('heading', {
            level: 1,
            name: /Clinical Quality & Regulatory Compliance Standards/i,
          })
        ).toBeInTheDocument();
      });

      expect(
        screen.getByRole('alert', { name: /Mandatory Non-Clinical Care Legal Disclaimer/i })
      ).toBeInTheDocument();
    });
  });
});
