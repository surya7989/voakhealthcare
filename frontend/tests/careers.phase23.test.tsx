import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { Router } from '../src/shared/router/Router';
import { PublicApp } from '../src/apps/public/PublicApp';
import { CareersPage, CANONICAL_JOBS } from '../src/apps/public/pages/CareersPage';
import { JobDetailPage } from '../src/apps/public/pages/JobDetailPage';
import { apiClient } from '../src/shared/api/apiClient';

describe('Phase 23 — Careers Portal Suite', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  /* ====================================================================
   * PAGE-PUB-12: Careers Overview Directory (/careers)
   * ==================================================================== */
  describe('PAGE-PUB-12: Careers Overview Directory (/careers)', () => {
    it('renders all canonical sections, EVP hero, culture pillars, and job cards', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/careers">
          <CareersPage />
        </Router>
      );

      // Accessibility Landmarks
      expect(screen.getByRole('banner')).toBeInTheDocument();
      expect(screen.getByRole('main')).toBeInTheDocument();
      expect(screen.getByRole('contentinfo')).toBeInTheDocument();

      // Hero H1 and EVP Copy
      expect(
        screen.getByRole('heading', {
          level: 1,
          name: /Shape the Future of Operational Healthcare/i,
        })
      ).toBeInTheDocument();

      expect(screen.getByText(/Join Our Healthcare Network/i)).toBeInTheDocument();

      // 4 Culture Pillars
      expect(screen.getByText('Clinical Governance & Safety First')).toBeInTheDocument();
      expect(screen.getByText('Continuous Professional Development')).toBeInTheDocument();
      expect(screen.getByText('Workforce Flexibility & Balance')).toBeInTheDocument();
      expect(screen.getByText('Transparent Remuneration & Recognition')).toBeInTheDocument();

      // Filter Controls
      expect(screen.getByRole('searchbox')).toBeInTheDocument();
      expect(screen.getByLabelText(/Filter by department/i)).toBeInTheDocument();
      expect(screen.getByLabelText(/Filter by employment type/i)).toBeInTheDocument();
      expect(screen.getByLabelText(/Filter by location/i)).toBeInTheDocument();

      // Live results counter
      const counter = screen.getByLabelText(/Job openings count/i);
      expect(counter).toHaveTextContent(/Showing 5 open roles/i);

      // Verify canonical jobs render
      expect(screen.getByText('Clinical Nurse Lead — Critical Care & Emergency')).toBeInTheDocument();
      expect(screen.getByText('Palliative Care Clinical Lead')).toBeInTheDocument();
      expect(screen.getByText('Healthcare Facilities Operations Manager')).toBeInTheDocument();
      expect(screen.getByText('Senior Clinical Quality & Credentialing Officer')).toBeInTheDocument();
      expect(screen.getByText('Medical Administration & Patient Intake Specialist')).toBeInTheDocument();

      // Talent network & governance note
      expect(screen.getByText(/specific discipline listed/i)).toBeInTheDocument();
      expect(screen.getByText(/Employment Scope & Regulatory Notice/i)).toBeInTheDocument();
    });

    it('filters jobs by department dropdown', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/careers">
          <CareersPage />
        </Router>
      );

      const deptSelect = screen.getByLabelText(/Filter by department/i);
      fireEvent.change(deptSelect, { target: { value: 'Clinical Governance' } });

      // Live counter updates
      expect(screen.getByLabelText(/Job openings count/i)).toHaveTextContent(/Showing 1 open role/i);

      // Only Clinical Governance job shown
      expect(screen.getByText('Senior Clinical Quality & Credentialing Officer')).toBeInTheDocument();
      expect(screen.queryByText('Clinical Nurse Lead — Critical Care & Emergency')).not.toBeInTheDocument();
    });

    it('filters jobs by employment type dropdown', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/careers">
          <CareersPage />
        </Router>
      );

      const typeSelect = screen.getByLabelText(/Filter by employment type/i);
      fireEvent.change(typeSelect, { target: { value: 'CONTRACT' } });

      // Live counter updates
      expect(screen.getByLabelText(/Job openings count/i)).toHaveTextContent(/Showing 1 open role/i);
      expect(screen.getByText('Medical Administration & Patient Intake Specialist')).toBeInTheDocument();
    });

    it('filters jobs by location dropdown', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/careers">
          <CareersPage />
        </Router>
      );

      const locSelect = screen.getByLabelText(/Filter by location/i);
      fireEvent.change(locSelect, { target: { value: 'Melbourne, VIC' } });

      expect(screen.getByText('Palliative Care Clinical Lead')).toBeInTheDocument();
      expect(screen.queryByText('Healthcare Facilities Operations Manager')).not.toBeInTheDocument();
    });

    it('searches jobs by keyword and shows empty state on zero matches with reset recovery', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/careers">
          <CareersPage />
        </Router>
      );

      const searchInput = screen.getByRole('searchbox');
      fireEvent.change(searchInput, { target: { value: 'Nonexistent Specialization 12345' } });

      // Empty State
      expect(screen.getByText(/No Vacancies Match Your Search/i)).toBeInTheDocument();
      const resetBtn = screen.getByRole('button', { name: /Reset Filters/i });
      expect(resetBtn).toBeInTheDocument();

      // Click reset restores full list
      fireEvent.click(resetBtn);
      expect(screen.getByText('Clinical Nurse Lead — Critical Care & Emergency')).toBeInTheDocument();
      expect(screen.getByLabelText(/Job openings count/i)).toHaveTextContent(/Showing 5 open roles/i);
    });

    it('injects Schema.org CollectionPage structured data script in head', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/careers">
          <CareersPage />
        </Router>
      );

      await waitFor(() => {
        const script = document.querySelector('script[type="application/ld+json"]');
        expect(script).not.toBeNull();
        const json = JSON.parse(script?.textContent || '{}');
        expect(json['@type']).toBe('CollectionPage');
        expect(json.name).toContain('Careers in Healthcare Operations & Clinical Staffing');
      });
    });
  });

  /* ====================================================================
   * PAGE-PUB-13: Job Detail & Application Flow (/careers/:slug)
   * ==================================================================== */
  describe('PAGE-PUB-13: Job Detail & Application Flow (/careers/:slug)', () => {
    it('renders job detail content, responsibilities, requirements, and AHPRA check', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/careers/clinical-nurse-lead">
          <JobDetailPage slug="clinical-nurse-lead" />
        </Router>
      );

      // Hero H1
      expect(
        screen.getByRole('heading', {
          level: 1,
          name: /Clinical Nurse Lead — Critical Care & Emergency/i,
        })
      ).toBeInTheDocument();

      // Key details
      expect(screen.getByText('Clinical Operations')).toBeInTheDocument();
      expect(screen.getByText('Sydney, NSW')).toBeInTheDocument();
      expect(screen.getByText(/Closing: 2026-12-31/i)).toBeInTheDocument();

      // Sections
      expect(screen.getByRole('heading', { name: /Role Overview & Strategic Scope/i })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: /Key Responsibilities/i })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: /Qualifications & Credentialing Criteria/i })).toBeInTheDocument();

      // AHPRA Mandate banner
      expect(screen.getByText(/Mandatory AHPRA Credential Verification/i)).toBeInTheDocument();

      // Compensation Card
      expect(screen.getByRole('heading', { name: /Compensation & Practitioner Support/i })).toBeInTheDocument();

      // Application Form
      expect(screen.getByRole('heading', { name: /Candidate Application/i })).toBeInTheDocument();
      expect(screen.getByLabelText(/Full Name/i)).toBeInTheDocument();
      expect(screen.getByLabelText(/Email Address/i)).toBeInTheDocument();
      expect(screen.getByLabelText(/Contact Phone Number/i)).toBeInTheDocument();
      expect(screen.getByLabelText(/AHPRA Registration Number/i)).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Submit Application/i })).toBeInTheDocument();
    });

    it('injects Schema.org JobPosting structured data', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/careers/clinical-nurse-lead">
          <JobDetailPage slug="clinical-nurse-lead" />
        </Router>
      );

      await waitFor(() => {
        const script = document.querySelector('script[type="application/ld+json"]');
        expect(script).not.toBeNull();
        const json = JSON.parse(script?.textContent || '{}');
        expect(json['@type']).toBe('JobPosting');
        expect(json.title).toBe('Clinical Nurse Lead — Critical Care & Emergency');
        expect(json.hiringOrganization.name).toBe('VOAK Health Care Australia');
        expect(json.jobLocation.address.addressLocality).toBe('Sydney, NSW');
      });
    });

    it('renders closed vacancy banner and prevents application when job is closed', async () => {
      const closedJob = {
        ...CANONICAL_JOBS[0],
        status: 'CLOSED',
        is_closed: true,
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: closedJob,
        status: 200,
        headers: {},
      } as any);

      render(
        <Router initialPath="/careers/clinical-nurse-lead">
          <JobDetailPage slug="clinical-nurse-lead" />
        </Router>
      );

      await waitFor(() => {
        expect(screen.getByText(/Applications Closed/i)).toBeInTheDocument();
      });

      expect(screen.getByText(/Applications are no longer accepted for this vacancy/i)).toBeInTheDocument();
      expect(screen.queryByRole('button', { name: /Submit Application/i })).not.toBeInTheDocument();
    });

    it('renders semantic 404 recovery page for unknown vacancy slug', async () => {
      vi.spyOn(apiClient, 'get').mockRejectedValue({
        status: 404,
        message: 'Job not found',
      });

      render(
        <Router initialPath="/careers/unknown-slug-xyz">
          <JobDetailPage slug="unknown-slug-xyz" />
        </Router>
      );

      await waitFor(() => {
        expect(screen.getByText(/Job Opening Not Found/i)).toBeInTheDocument();
      });

      expect(screen.getByRole('button', { name: /Explore All Vacancies/i })).toBeInTheDocument();
    });
  });

  /* ====================================================================
   * FORM-PUB-04: Candidate Application & Resume Upload Interactions
   * ==================================================================== */
  describe('FORM-PUB-04: Candidate Application & Resume Upload Interactions', () => {
    it('validates client-side required fields and shows validation summary', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/careers/clinical-nurse-lead">
          <JobDetailPage slug="clinical-nurse-lead" />
        </Router>
      );

      const submitBtn = screen.getByRole('button', { name: /Submit Application/i });
      fireEvent.click(submitBtn);

      await waitFor(() => {
        expect(screen.getByText(/Please correct the following errors:/i)).toBeInTheDocument();
      });

      expect(screen.getAllByText(/Full name is required/i).length).toBeGreaterThanOrEqual(1);
      expect(screen.getAllByText(/A valid email address is required/i).length).toBeGreaterThanOrEqual(1);
      expect(screen.getAllByText(/A valid contact phone number is required/i).length).toBeGreaterThanOrEqual(1);
      expect(screen.getAllByText(/resume document attachment/i).length).toBeGreaterThanOrEqual(1);
      expect(screen.getAllByText(/Australian Privacy Principles consent agreement/i).length).toBeGreaterThanOrEqual(1);
    });

    it('validates email and phone formats', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/careers/clinical-nurse-lead">
          <JobDetailPage slug="clinical-nurse-lead" />
        </Router>
      );

      fireEvent.change(screen.getByLabelText(/Full Name/i), { target: { value: 'Dr. Jane Smith' } });
      fireEvent.change(screen.getByLabelText(/Email Address/i), { target: { value: 'invalid-email-address' } });
      fireEvent.change(screen.getByLabelText(/Contact Phone Number/i), { target: { value: '123' } });

      const submitBtn = screen.getByRole('button', { name: /Submit Application/i });
      fireEvent.click(submitBtn);

      await waitFor(() => {
        expect(screen.getAllByText(/A valid email address is required/i).length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByText(/A valid contact phone number is required/i).length).toBeGreaterThanOrEqual(1);
      });
    });

    it('handles file dropzone upload and file removal', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/careers/clinical-nurse-lead">
          <JobDetailPage slug="clinical-nurse-lead" />
        </Router>
      );

      const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
      expect(fileInput).not.toBeNull();

      const testFile = new File(['%PDF-1.4 test resume content'], 'nurse-jane-smith-cv.pdf', {
        type: 'application/pdf',
      });

      fireEvent.change(fileInput, { target: { files: [testFile] } });

      await waitFor(() => {
        expect(screen.getByText('nurse-jane-smith-cv.pdf')).toBeInTheDocument();
      });

      // Remove file
      const removeBtn = screen.getByRole('button', { name: /Remove file nurse-jane-smith-cv.pdf/i });
      fireEvent.click(removeBtn);

      await waitFor(() => {
        expect(screen.queryByText('nurse-jane-smith-cv.pdf')).not.toBeInTheDocument();
      });
    });

    it('rejects oversized files exceeding 10MB', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/careers/clinical-nurse-lead">
          <JobDetailPage slug="clinical-nurse-lead" />
        </Router>
      );

      const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
      const oversizedFile = new File(['x'.repeat(100)], 'huge-portfolio.pdf', {
        type: 'application/pdf',
      });
      // Override size to 11MB
      Object.defineProperty(oversizedFile, 'size', { value: 11 * 1024 * 1024 });

      fireEvent.change(fileInput, { target: { files: [oversizedFile] } });

      await waitFor(() => {
        expect(screen.getByText(/"huge-portfolio.pdf" exceeds maximum allowed size of 10MB/i)).toBeInTheDocument();
      });
    });

    it('successfully submits valid candidate application and displays reference code', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));
      const postSpy = vi.spyOn(apiClient, 'post').mockResolvedValue({
        data: {
          reference_code: 'APP-2026-NUR001',
          message: 'Candidate application received and securely queued.',
          retention_expiry_date: '2027-04-24',
        },
        status: 201,
        headers: {},
      } as any);

      render(
        <Router initialPath="/careers/clinical-nurse-lead">
          <JobDetailPage slug="clinical-nurse-lead" />
        </Router>
      );

      // Fill valid inputs
      fireEvent.change(screen.getByLabelText(/Full Name/i), { target: { value: 'Dr. Jane Smith, MBBS' } });
      fireEvent.change(screen.getByLabelText(/Email Address/i), { target: { value: 'jane.smith@hospital.org.au' } });
      fireEvent.change(screen.getByLabelText(/Contact Phone Number/i), { target: { value: '+61 412 345 678' } });
      fireEvent.change(screen.getByLabelText(/AHPRA Registration Number/i), { target: { value: 'MED0001234567' } });

      // Add resume
      const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
      const validResume = new File(['%PDF-1.4 Jane Smith CV content'], 'jane_smith_cv.pdf', {
        type: 'application/pdf',
      });
      fireEvent.change(fileInput, { target: { files: [validResume] } });

      // Check privacy consent
      const consentCb = screen.getByLabelText(/I consent to Voak Health Care/i);
      fireEvent.click(consentCb);

      // Submit
      const submitBtn = screen.getByRole('button', { name: /Submit Application/i });
      fireEvent.click(submitBtn);

      await waitFor(() => {
        expect(screen.getByText(/Application Received/i)).toBeInTheDocument();
      });

      // Verify reference code and retention notice
      expect(screen.getByText('APP-2026-NUR001')).toBeInTheDocument();

      // Check API payload
      expect(postSpy).toHaveBeenCalledTimes(1);
      expect(postSpy).toHaveBeenCalledWith(
        '/api/v1/public/careers/apply',
        expect.any(FormData)
      );
    });

    it('handles backend 422 Unprocessable Entity and maps field errors', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));
      vi.spyOn(apiClient, 'post').mockRejectedValue({
        status: 422,
        errors: {
          email: 'Email domain has been flagged as temporary or unroutable.',
          phone: 'Phone number must follow E.164 Australian mobile or landline format.',
        },
        message: 'Validation failed.',
      });

      render(
        <Router initialPath="/careers/clinical-nurse-lead">
          <JobDetailPage slug="clinical-nurse-lead" />
        </Router>
      );

      // Fill inputs
      fireEvent.change(screen.getByLabelText(/Full Name/i), { target: { value: 'Jane Smith' } });
      fireEvent.change(screen.getByLabelText(/Email Address/i), { target: { value: 'jane@tempmail.com' } });
      fireEvent.change(screen.getByLabelText(/Contact Phone Number/i), { target: { value: '0400000000' } });

      const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
      const validResume = new File(['dummy'], 'cv.pdf', { type: 'application/pdf' });
      fireEvent.change(fileInput, { target: { files: [validResume] } });

      const consentCb = screen.getByLabelText(/I consent to Voak Health Care/i);
      fireEvent.click(consentCb);

      const submitBtn = screen.getByRole('button', { name: /Submit Application/i });
      fireEvent.click(submitBtn);

      await waitFor(() => {
        expect(screen.getAllByText(/Email domain has been flagged as temporary or unroutable/i).length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByText(/Phone number must follow E.164 Australian mobile or landline format/i).length).toBeGreaterThanOrEqual(1);
      });
    });

    it('handles 429 Rate Limit error response', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));
      vi.spyOn(apiClient, 'post').mockRejectedValue({
        status: 429,
        code: 'ERR_RATE_LIMITED',
        message: 'Too many applications submitted from your IP address. Please wait an hour before submitting again.',
      });

      render(
        <Router initialPath="/careers/clinical-nurse-lead">
          <JobDetailPage slug="clinical-nurse-lead" />
        </Router>
      );

      fireEvent.change(screen.getByLabelText(/Full Name/i), { target: { value: 'Jane Smith' } });
      fireEvent.change(screen.getByLabelText(/Email Address/i), { target: { value: 'jane@example.com' } });
      fireEvent.change(screen.getByLabelText(/Contact Phone Number/i), { target: { value: '0400000000' } });

      const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
      const validResume = new File(['dummy'], 'cv.pdf', { type: 'application/pdf' });
      fireEvent.change(fileInput, { target: { files: [validResume] } });

      const consentCb = screen.getByLabelText(/I consent to Voak Health Care/i);
      fireEvent.click(consentCb);

      const submitBtn = screen.getByRole('button', { name: /Submit Application/i });
      fireEvent.click(submitBtn);

      await waitFor(() => {
        expect(screen.getByText(/Application submission limit exceeded/i)).toBeInTheDocument();
      });
    });
  });

  /* ====================================================================
   * PublicApp Router Integration
   * ==================================================================== */
  describe('PublicApp Router Integration', () => {
    it('routes /careers to CareersPage via PublicApp', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/careers">
          <PublicApp />
        </Router>
      );

      expect(
        screen.getByRole('heading', {
          level: 1,
          name: /Shape the Future of Operational Healthcare/i,
        })
      ).toBeInTheDocument();
    });

    it('routes /careers/:slug to JobDetailPage via PublicApp', async () => {
      vi.spyOn(apiClient, 'get').mockImplementation(() => new Promise(() => {}));

      render(
        <Router initialPath="/careers/palliative-care-lead">
          <PublicApp />
        </Router>
      );

      expect(
        screen.getByRole('heading', {
          level: 1,
          name: /Palliative Care Clinical Lead/i,
        })
      ).toBeInTheDocument();
    });
  });
});
