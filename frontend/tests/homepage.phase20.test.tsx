import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { Router } from '../src/shared/router/Router';
import { HomePage } from '../src/apps/public/pages/HomePage';
import { apiClient } from '../src/shared/api/apiClient';

describe('Phase 20 — Full Homepage Suite (PAGE-PUB-01)', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  const mockHomepageConfig = {
    hero_headline: 'Enterprise Healthcare Workforce & Governance',
    hero_subheadline: 'Connecting acute hospitals with verified clinical practitioners and governance infrastructure.',
    hero_primary_cta_text: 'Explore Clinical Services',
    hero_primary_cta_url: '#services',
    hero_secondary_cta_text: 'Request Operational Proposal',
    hero_secondary_cta_url: '#contact',
    proof_metrics: [
      { label: 'Hospital Networks', value: '140+' },
      { label: 'Credentialed Specialists', value: '500+' },
      { label: 'Clinical Hours Delivered', value: '1.5M+' },
      { label: 'SLA Fulfillment Rate', value: '99.9%' },
    ],
  };

  const mockServices = [
    { id: 1, name: 'Specialist ICU Staffing', slug: 'specialist-icu-staffing' },
    { id: 2, name: 'Emergency Surge Coordination', slug: 'emergency-surge-coordination' },
    { id: 3, name: 'Digital Clinical Governance', slug: 'digital-clinical-governance' },
  ];

  const mockIndustries = [
    {
      id: 1,
      name: 'Public Tertiary Hospitals',
      slug: 'public-tertiary-hospitals',
      summary: 'Tier 1 acute surgical and critical care staffing continuity.',
    },
    {
      id: 2,
      name: 'Regional Health Districts',
      slug: 'regional-health-districts',
      summary: 'Remote and regional healthcare coverage solutions.',
    },
  ];

  const mockArticles = [
    {
      id: 101,
      title: '2026 Healthcare Workforce Trends in Australia',
      slug: '2026-healthcare-workforce-trends',
      excerpt: 'Strategic analysis on clinical vacancy rates and surge planning.',
      category: 'Workforce Planning',
      reading_time_minutes: 6,
      published_at: '2026-03-20',
      author_name: 'Dr. Jane Voak',
    },
  ];

  it('renders all canonical sections with default fallback data when API calls are in flight or empty', async () => {
    vi.spyOn(apiClient, 'get').mockImplementation(() =>
      new Promise(() => {}) // pending promise simulates initial loading
    );

    render(
      <Router>
        <HomePage />
      </Router>
    );

    // 1. Accessibility landmarks
    expect(screen.getByRole('banner')).toBeInTheDocument();
    expect(screen.getByRole('main')).toBeInTheDocument();
    expect(screen.getByRole('contentinfo')).toBeInTheDocument();

    // 2. Skip to content link
    const skipLink = screen.getByText(/Skip to main content/i);
    expect(skipLink).toBeInTheDocument();
    expect(skipLink).toHaveAttribute('href', '#main-content');

    // 3. Hero Section (Image 2 Parity)
    expect(
      screen.getByRole('heading', { level: 1, name: /Better Healthcare Together/i })
    ).toBeInTheDocument();
    expect(screen.getByText(/B2B Healthcare Services/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Explore Our Services/i })).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: /Partner With Us/i }).length).toBeGreaterThanOrEqual(1);

    // 4. Feature Cards Section
    expect(screen.getByText(/Comprehensive Healthcare Solutions/i)).toBeInTheDocument();
    expect(screen.getByText(/We Work Across Healthcare Sectors/i)).toBeInTheDocument();
    expect(screen.getByText(/Grow Together/i)).toBeInTheDocument();

    // 5. Services Bento Section
    expect(screen.getByText(/We Provide End-to-End Support/i)).toBeInTheDocument();

    // 6. Trust Section
    expect(screen.getByText(/Why Voak/i)).toBeInTheDocument();
    expect(screen.getByText(/Trusted by Healthcare Organisations/i)).toBeInTheDocument();

    // 7. Industries Section
    expect(screen.getByText(/Industries We Serve/i)).toBeInTheDocument();

    // 8. Workflow Section
    expect(screen.getByText(/How We Partner With Healthcare Organizations/i)).toBeInTheDocument();
    expect(screen.getByText(/Operational Discovery/i)).toBeInTheDocument();
    expect(screen.getByText(/Solution Architecture/i)).toBeInTheDocument();
    expect(screen.getByText(/Deployment & Integration/i)).toBeInTheDocument();
    expect(screen.getByText(/Continuous Governance/i)).toBeInTheDocument();

    // 9. CTA Banner Section
    expect(screen.getByText(/Transform Your Healthcare Operations Today/i)).toBeInTheDocument();

    // 10. Footer Section
    expect(screen.getByText(/Enterprise B2B healthcare workforce solutions/i)).toBeInTheDocument();
    expect(screen.getByText(/© 2026 Voak Health Care. All rights reserved./i)).toBeInTheDocument();
  });

  it('hydrates dynamically with API data from endpoints', async () => {
    vi.spyOn(apiClient, 'get').mockImplementation(async (endpoint: string) => {
      if (endpoint === '/public/homepage') {
        return { success: true, data: { config: mockHomepageConfig } };
      }
      if (endpoint === '/public/services') {
        return { success: true, data: { services: mockServices } };
      }
      if (endpoint === '/public/industries') {
        return { success: true, data: { industries: mockIndustries } };
      }
      if (endpoint === '/public/articles') {
        return { success: true, data: { articles: mockArticles } };
      }
      return { success: true, data: {} };
    });

    render(
      <Router>
        <HomePage />
      </Router>
    );

    // Await hydration of custom hero headline
    await waitFor(() => {
      expect(
        screen.getByRole('heading', {
          level: 1,
          name: /Enterprise Healthcare Workforce & Governance/i,
        })
      ).toBeInTheDocument();
    });

    // Custom metrics from config
    expect(screen.getByText('140+')).toBeInTheDocument();
    expect(screen.getByText('Hospital Networks')).toBeInTheDocument();
    expect(screen.getByText('99.9%')).toBeInTheDocument();

    // Custom dynamic service
    expect(screen.getByText('Specialist ICU Staffing')).toBeInTheDocument();

    // Custom dynamic industry
    expect(screen.getByText('Public Tertiary Hospitals')).toBeInTheDocument();
    expect(
      screen.getByText(/Tier 1 acute surgical and critical care staffing continuity/i)
    ).toBeInTheDocument();

    // Custom dynamic article
    expect(screen.getByText('2026 Healthcare Workforce Trends in Australia')).toBeInTheDocument();
    expect(screen.getByText('Workforce Planning')).toBeInTheDocument();
  });

  it('gracefully handles API network errors without breaking the page', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValue(new Error('Network error 500'));

    render(
      <Router>
        <HomePage />
      </Router>
    );

    // Page must remain intact with fallback data
    await waitFor(() => {
      expect(
        screen.getByRole('heading', { level: 1, name: /Better Healthcare Together/i })
      ).toBeInTheDocument();
    });

    expect(screen.getByText(/We Provide End-to-End Support/i)).toBeInTheDocument();
    expect(screen.getByText(/Industries We Serve/i)).toBeInTheDocument();
    expect(screen.getByText(/Healthcare Insights & Governance/i)).toBeInTheDocument();
  });

  it('toggles mobile navigation drawer on hamburger button click', async () => {
    render(
      <Router>
        <HomePage />
      </Router>
    );

    const mobileMenuBtn = screen.getByLabelText(/Open navigation menu/i);
    expect(mobileMenuBtn).toBeInTheDocument();

    // Click to open drawer
    fireEvent.click(mobileMenuBtn);

    // Check that drawer opened and shows title
    await waitFor(() => {
      expect(screen.getByText('Enterprise Healthcare Solutions')).toBeInTheDocument();
    });

    // Close button inside drawer
    const closeBtn = screen.getByLabelText(/Close panel/i);
    fireEvent.click(closeBtn);

    // Wait for drawer to close
    await waitFor(() => {
      expect(screen.queryByText('Enterprise Healthcare Solutions')).not.toBeInTheDocument();
    });
  });

  it('injects document title and schema.org MedicalOrganization JSON-LD', async () => {
    vi.spyOn(apiClient, 'get').mockResolvedValue({ success: true, data: {} });

    render(
      <Router>
        <HomePage />
      </Router>
    );

    await waitFor(() => {
      expect(document.title).toContain('Voak Health Care');
    });

    const jsonLdScript = document.getElementById('seo-json-ld');
    expect(jsonLdScript).toBeInTheDocument();

    const parsed = JSON.parse(jsonLdScript?.textContent || '{}');
    expect(parsed['@type']).toBe('MedicalOrganization');
    expect(parsed.name).toBe('VOAK Health Care');
    expect(parsed.contactPoint?.telephone).toBe('+61-1800-VOAK-HC');
  });
});
