import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { Router } from '../src/shared/router/Router';
import { PublicApp } from '../src/apps/public/PublicApp';
import { ServicesPage } from '../src/apps/public/pages/ServicesPage';
import { ServiceDetailPage } from '../src/apps/public/pages/ServiceDetailPage';
import { IndustriesPage } from '../src/apps/public/pages/IndustriesPage';
import { IndustryDetailPage } from '../src/apps/public/pages/IndustryDetailPage';
import { apiClient } from '../src/shared/api/apiClient';

describe('Phase 22 — Services & Industries Catalog Suite', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  /* ====================================================================
   * PAGE-PUB-03: Services Overview (/services)
   * ==================================================================== */
  describe('PAGE-PUB-03: Services Overview (/services)', () => {
    it('renders all canonical sections, accessibility landmarks, and breadcrumbs', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/services">
          <ServicesPage />
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
          name: /Enterprise Healthcare Services & Clinical Solutions/i,
        })
      ).toBeInTheDocument();
      expect(screen.getAllByText('Services').length).toBeGreaterThanOrEqual(1);

      // Category filter buttons
      expect(screen.getByRole('button', { name: /All Services/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Clinical Workforce/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Facility Operations/i })).toBeInTheDocument();

      // 6 canonical service cards
      expect(screen.getByRole('heading', { name: /Clinical Workforce Solutions/i })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: /Healthcare Facility Operations/i })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: /Health Administration & Medical Support/i })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: /Healthcare Compliance & Clinical Governance/i })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: /Healthcare Logistics & Supply Chain Support/i })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: /Healthcare Consulting & Operational Advisory/i })).toBeInTheDocument();

      // Governance Pledge
      expect(screen.getByText(/100% Credential Audit/i)).toBeInTheDocument();
      expect(screen.getByText(/Clinical Governance Rigor/i)).toBeInTheDocument();
      expect(screen.getByText(/Transparent SLA Metrics/i)).toBeInTheDocument();
    });

    it('filters services by category pill, updates aria-pressed, and updates telemetry announcement', async () => {
      render(
        <Router initialPath="/services">
          <ServicesPage />
        </Router>
      );

      const workforceButton = screen.getByRole('button', { name: /Clinical Workforce/i });
      expect(workforceButton).toHaveAttribute('aria-pressed', 'false');

      // Click category filter
      fireEvent.click(workforceButton);

      // Verify active filter state
      expect(workforceButton).toHaveAttribute('aria-pressed', 'true');

      // Telemetry announcement
      expect(screen.getByText(/Showing/i)).toBeInTheDocument();

      // Workforce card should be visible
      expect(screen.getByRole('heading', { name: /Clinical Workforce Solutions/i })).toBeInTheDocument();

      // Reset to all services
      const allButton = screen.getByRole('button', { name: /All Services/i });
      fireEvent.click(allButton);
      expect(allButton).toHaveAttribute('aria-pressed', 'true');
      expect(screen.getByRole('heading', { name: /Healthcare Facility Operations/i })).toBeInTheDocument();
    });

    it('filters services by keyword search and displays empty state on zero matches', async () => {
      render(
        <Router initialPath="/services">
          <ServicesPage />
        </Router>
      );

      const searchInput = screen.getByPlaceholderText(/Search services/i);
      fireEvent.change(searchInput, { target: { value: 'locum' } });

      // Only Clinical Workforce matches 'locum' in description/tagline
      expect(screen.getByRole('heading', { name: /Clinical Workforce Solutions/i })).toBeInTheDocument();
      expect(screen.queryByRole('heading', { name: /Healthcare Facility Operations/i })).not.toBeInTheDocument();

      // Type non-matching search query
      fireEvent.change(searchInput, { target: { value: 'xyznonexistent123' } });

      // Empty state renders
      expect(screen.getByText(/No Services Found/i)).toBeInTheDocument();
      expect(
        screen.getByText(/No services currently match your selected category or search query/i)
      ).toBeInTheDocument();

      // Reset filters button
      const resetBtns = screen.getAllByRole('button', { name: /Reset Filters/i });
      fireEvent.click(resetBtns[0]);

      // Restores all cards
      expect(screen.getByRole('heading', { name: /Clinical Workforce Solutions/i })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: /Healthcare Facility Operations/i })).toBeInTheDocument();
    });

    it('injects document title and schema.org ItemList structured data', async () => {
      render(
        <Router initialPath="/services">
          <ServicesPage />
        </Router>
      );

      await waitFor(() => {
        expect(document.title).toContain('Healthcare Services & Operational Solutions');
      });

      const script = document.querySelector('script[type="application/ld+json"]');
      expect(script).not.toBeNull();
      const json = JSON.parse(script?.textContent || '{}');
      expect(json['@type']).toBe('ItemList');
      expect(json.itemListElement.length).toBeGreaterThanOrEqual(6);
    });
  });

  /* ====================================================================
   * PAGE-PUB-04: Individual Service Detail (/services/:slug)
   * ==================================================================== */
  describe('PAGE-PUB-04: Individual Service Detail (/services/:slug)', () => {
    it('renders service detail page for a valid slug with deliverables, methodology, and SLAs', async () => {
      render(
        <Router initialPath="/services/clinical-workforce-solutions">
          <ServiceDetailPage slug="clinical-workforce-solutions" />
        </Router>
      );

      // Hero H1
      expect(
        screen.getByRole('heading', {
          level: 1,
          name: /Clinical Workforce Solutions/i,
        })
      ).toBeInTheDocument();

      // Tagline
      expect(screen.getByText(/Rapid deployment of credentialed nurses/i)).toBeInTheDocument();

      // Deliverables checklist
      expect(screen.getByText(/Core Service Deliverables/i)).toBeInTheDocument();
      expect(screen.getByText(/Rapid 24\/7 surge staffing and locum placement/i)).toBeInTheDocument();
      expect(screen.getByText(/100% verified AHPRA registration/i)).toBeInTheDocument();

      // Operational Methodology
      expect(screen.getByText(/Operational Integration Framework/i)).toBeInTheDocument();
      expect(screen.getByText(/Clinical & Operational Diagnostic/i)).toBeInTheDocument();
      expect(screen.getByText(/Credentialed Workforce Deployment/i)).toBeInTheDocument();

      // SLA Benchmarks
      expect(screen.getByText(/Service Level Agreements & Benchmarks/i)).toBeInTheDocument();
      expect(screen.getByText(/>= 98.5%/i)).toBeInTheDocument();
      expect(screen.getAllByText(/100% Verified/i).length).toBeGreaterThanOrEqual(1);

      // Contextual proposal buttons
      const proposalBtns = screen.getAllByRole('button', { name: /Request Scope Proposal/i });
      expect(proposalBtns.length).toBeGreaterThanOrEqual(1);

      // Sibling services
      expect(screen.getByText(/Related Healthcare Services/i)).toBeInTheDocument();
    });

    it('renders authentic semantic 404 view for an invalid or unpublished service slug', async () => {
      render(
        <Router initialPath="/services/invalid-fake-service-slug">
          <ServiceDetailPage slug="invalid-fake-service-slug" />
        </Router>
      );

      await waitFor(() => {
        expect(screen.getByRole('heading', { name: /Healthcare Service Not Found/i })).toBeInTheDocument();
      });
      expect(screen.getByText(/We could not locate a published healthcare service/i)).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Browse All Services/i })).toBeInTheDocument();
    });

    it('injects document title and schema.org Service structured data', async () => {
      render(
        <Router initialPath="/services/clinical-workforce-solutions">
          <ServiceDetailPage slug="clinical-workforce-solutions" />
        </Router>
      );

      await waitFor(() => {
        expect(document.title).toContain('Clinical Workforce Solutions');
      });

      const script = document.querySelector('script[type="application/ld+json"]');
      expect(script).not.toBeNull();
      const json = JSON.parse(script?.textContent || '{}');
      expect(json['@type']).toBe('Service');
      expect(json.name).toBe('Clinical Workforce Solutions');
    });
  });

  /* ====================================================================
   * PAGE-PUB-05: Industries Overview (/industries)
   * ==================================================================== */
  describe('PAGE-PUB-05: Industries Overview (/industries)', () => {
    it('renders all 6 approved industry sectors with governing bodies and pain points', async () => {
      render(
        <Router initialPath="/industries">
          <IndustriesPage />
        </Router>
      );

      // Landmarks
      expect(screen.getByRole('banner')).toBeInTheDocument();
      expect(screen.getByRole('main')).toBeInTheDocument();
      expect(screen.getByRole('contentinfo')).toBeInTheDocument();

      // Hero H1
      expect(
        screen.getByRole('heading', {
          level: 1,
          name: /Healthcare Industries & Institutional Sectors/i,
        })
      ).toBeInTheDocument();

      // All 6 canonical sectors
      expect(screen.getByRole('heading', { name: /Hospitals & Health Services/i })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: /Residential Aged Care Facilities/i })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: /Primary Care & General Practice/i })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: /Allied Health Networks/i })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: /Diagnostic & Pathology Centers/i })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: /Corporate & Occupational Health/i })).toBeInTheDocument();

      // Governing bodies
      expect(screen.getByText(/Australian Commission on Safety and Quality in Health Care/i)).toBeInTheDocument();
      expect(screen.getByText(/Aged Care Quality and Safety Commission/i)).toBeInTheDocument();

      // Cross-sector capabilities
      expect(screen.getByText(/Universal Governance Standards Across All Sectors/i)).toBeInTheDocument();
      expect(screen.getByText(/Primary-Source Credentialing/i)).toBeInTheDocument();
    });

    it('filters sectors by search query and recovers on reset', async () => {
      render(
        <Router initialPath="/industries">
          <IndustriesPage />
        </Router>
      );

      const searchInput = screen.getByPlaceholderText(/Filter sectors by challenge or keyword/i);
      fireEvent.change(searchInput, { target: { value: 'Aged Care' } });

      // Aged Care matches
      expect(screen.getByRole('heading', { name: /Residential Aged Care Facilities/i })).toBeInTheDocument();
      expect(screen.queryByRole('heading', { name: /Diagnostic & Pathology Centers/i })).not.toBeInTheDocument();

      // Reset
      const resetBtn = screen.getByRole('button', { name: /Reset/i });
      fireEvent.click(resetBtn);

      expect(screen.getByRole('heading', { name: /Diagnostic & Pathology Centers/i })).toBeInTheDocument();
    });

    it('injects document title and schema.org CollectionPage structured data', async () => {
      render(
        <Router initialPath="/industries">
          <IndustriesPage />
        </Router>
      );

      await waitFor(() => {
        expect(document.title).toContain('Healthcare Industries & Sectors Served');
      });

      const script = document.querySelector('script[type="application/ld+json"]');
      expect(script).not.toBeNull();
      const json = JSON.parse(script?.textContent || '{}');
      expect(json['@type']).toBe('CollectionPage');
      expect(json.hasPart.length).toBe(6);
    });
  });

  /* ====================================================================
   * PAGE-PUB-06: Individual Industry Detail (/industries/:slug)
   * ==================================================================== */
  describe('PAGE-PUB-06: Individual Industry Detail (/industries/:slug)', () => {
    it('renders industry detail page with regulatory landscape, pain points, and recommended services', async () => {
      render(
        <Router initialPath="/industries/residential-aged-care">
          <IndustryDetailPage slug="residential-aged-care" />
        </Router>
      );

      // Hero H1
      expect(
        screen.getByRole('heading', {
          level: 1,
          name: /Residential Aged Care Facilities/i,
        })
      ).toBeInTheDocument();

      // Regulatory section
      expect(screen.getByText(/Regulatory Landscape & Governance/i)).toBeInTheDocument();
      expect(screen.getAllByText(/Aged Care Quality and Safety Commission \(ACQSC\)/i).length).toBeGreaterThanOrEqual(1);
      expect(screen.getByText(/Mandatory 215 direct care minutes per resident per day/i)).toBeInTheDocument();
      expect(screen.getByText(/Mandatory 24\/7 Registered Nurse on-site coverage legislation/i)).toBeInTheDocument();

      // Challenges and interventions
      expect(screen.getByText(/Sector Challenges & Voak Solutions/i)).toBeInTheDocument();
      expect(screen.getByText(/Meeting the non-negotiable 24\/7 on-site Registered Nurse requirement/i)).toBeInTheDocument();

      // Contextual proposal CTA
      expect(screen.getByRole('button', { name: /Request Sector Consultation/i })).toBeInTheDocument();

      // Recommended services
      expect(screen.getByText(/Recommended Services/i)).toBeInTheDocument();
    });

    it('renders authentic semantic 404 view for an invalid industry slug', async () => {
      render(
        <Router initialPath="/industries/non-existent-sector-slug">
          <IndustryDetailPage slug="non-existent-sector-slug" />
        </Router>
      );

      await waitFor(() => {
        expect(screen.getByRole('heading', { name: /Healthcare Sector Not Found/i })).toBeInTheDocument();
      });
      expect(screen.getByText(/We could not locate a published healthcare industry vertical/i)).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Explore All Sectors/i })).toBeInTheDocument();
    });

    it('injects document title and schema.org MedicalWebPage structured data', async () => {
      render(
        <Router initialPath="/industries/hospitals-health-services">
          <IndustryDetailPage slug="hospitals-health-services" />
        </Router>
      );

      await waitFor(() => {
        expect(document.title).toContain('Hospitals & Health Services Operations');
      });

      const script = document.querySelector('script[type="application/ld+json"]');
      expect(script).not.toBeNull();
      const json = JSON.parse(script?.textContent || '{}');
      expect(json['@type']).toBe('MedicalWebPage');
    });
  });

  /* ====================================================================
   * PublicApp Router Integration Tests
   * ==================================================================== */
  describe('PublicApp Routing Integration', () => {
    it('routes /services to ServicesPage', async () => {
      render(
        <Router initialPath="/services">
          <PublicApp />
        </Router>
      );

      expect(
        screen.getByRole('heading', {
          level: 1,
          name: /Enterprise Healthcare Services & Clinical Solutions/i,
        })
      ).toBeInTheDocument();
    });

    it('routes /services/:slug to ServiceDetailPage', async () => {
      render(
        <Router initialPath="/services/clinical-workforce-solutions">
          <PublicApp />
        </Router>
      );

      await waitFor(() => {
        expect(
          screen.getByRole('heading', {
            level: 1,
            name: /Clinical Workforce Solutions/i,
          })
        ).toBeInTheDocument();
      });
    });

    it('routes /industries to IndustriesPage', async () => {
      render(
        <Router initialPath="/industries">
          <PublicApp />
        </Router>
      );

      await waitFor(() => {
        expect(
          screen.getByRole('heading', {
            level: 1,
            name: /Healthcare Industries & Institutional Sectors/i,
          })
        ).toBeInTheDocument();
      });
    });

    it('routes /industries/:slug to IndustryDetailPage', async () => {
      render(
        <Router initialPath="/industries/hospitals-health-services">
          <PublicApp />
        </Router>
      );

      await waitFor(() => {
        expect(
          screen.getByRole('heading', {
            level: 1,
            name: /Hospitals & Health Services/i,
          })
        ).toBeInTheDocument();
      });
    });

    it('renders NotFoundPage for unknown arbitrary routes', async () => {
      render(
        <Router initialPath="/some-arbitrary-unregistered-route">
          <PublicApp />
        </Router>
      );

      expect(screen.getByRole('heading', { name: /Page Not Found/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Return to Homepage/i })).toBeInTheDocument();
    });
  });
});
