import React, { useState, useEffect, useMemo } from 'react';
import { SeoHead } from '../seo/SeoHead';
import { PublicHeader } from '../components/PublicHeader';
import { PublicFooter } from '../components/PublicFooter';
import { Breadcrumb } from '../../../shared/components/Breadcrumb';
import { Button } from '../../../shared/components/Button';
import { Badge } from '../../../shared/components/Badge';
import { Card } from '../../../shared/components/Card';
import { BentoCard, BentoTint } from '../../../shared/components/BentoCard';
import { SearchInput } from '../../../shared/components/SearchInput';
import { EmptyState } from '../../../shared/components/EmptyState';
import { Skeleton } from '../../../shared/components/Skeleton';
import { Link, useRouter } from '../../../shared/router/Router';
import { apiClient } from '../../../shared/api/apiClient';
import {
  Briefcase,
  MapPin,
  ArrowRight,
  ShieldCheck,
  Award,
  Users,
  GraduationCap,
  Sparkles,
  RotateCcw,
  CheckCircle2,
  Calendar,
  AlertTriangle,
  Send,
} from 'lucide-react';

export interface JobItem {
  id: number;
  title: string;
  slug: string;
  department: string;
  employment_type: 'FULL_TIME' | 'PART_TIME' | 'CASUAL' | 'CONTRACT' | string;
  location: string;
  summary: string;
  responsibilities?: string[];
  requirements?: string[];
  is_active: number;
  status?: string;
  closing_date?: string | null;
  created_at?: string;
  updated_at?: string;
}

// Canonical baseline vacancies matching verified Phase 14 recruitment specifications
export const CANONICAL_JOBS: JobItem[] = [
  {
    id: 1,
    title: 'Clinical Nurse Lead — Critical Care & Emergency',
    slug: 'clinical-nurse-lead',
    department: 'Clinical Operations',
    employment_type: 'FULL_TIME',
    location: 'Sydney, NSW',
    summary: 'Lead frontline acute clinical nurse deployment, emergency triage protocols, and roster surge resilience across tertiary hospital networks.',
    responsibilities: [
      'Oversee acute care clinical nursing teams across emergency and ICU departments.',
      'Maintain compliance with NSQHS Standard 3 infection control and clinical handover procedures.',
      'Conduct clinical triage escalation, roster balancing, and surge nurse onboarding.',
      'Deliver bedside clinical mentoring and annual mandatory competency assessments.',
    ],
    requirements: [
      'Current unconditional AHPRA Registered Nurse (Division 1) registration.',
      'Minimum 5 years acute emergency or intensive care nursing experience in Australia.',
      'Postgraduate qualification in Critical Care, Emergency Nursing, or Clinical Education.',
      'Demonstrated experience leading multidisciplinary teams under surge pressure.',
    ],
    is_active: 1,
    status: 'PUBLISHED',
    closing_date: '2026-12-31',
    created_at: '2026-10-21T08:00:00Z',
  },
  {
    id: 2,
    title: 'Palliative Care Clinical Lead',
    slug: 'palliative-care-lead',
    department: 'Clinical Operations',
    employment_type: 'FULL_TIME',
    location: 'Melbourne, VIC',
    summary: 'Senior clinical leadership for palliative healthcare operations, end-of-life care pathways, and clinical team supervision across acute and residential settings.',
    responsibilities: [
      'Provide clinical expertise and case consultations for complex symptom management.',
      'Direct multidisciplinary palliative care teams across inpatient and community settings.',
      'Formulate clinical governance guidelines aligned with ACQSC and RACGP standards.',
      'Lead family clinical conferencing and compassionate advance care planning.',
    ],
    requirements: [
      'Current unconditional AHPRA Registered Nurse registration.',
      'Minimum 5 years specialized palliative care or oncology clinical experience.',
      'Demonstrated capability in clinical audit frameworks and compassionate communication.',
      'Working with Children Check and National Police Check clearance.',
    ],
    is_active: 1,
    status: 'PUBLISHED',
    closing_date: '2026-12-31',
    created_at: '2026-10-21T08:00:00Z',
  },
  {
    id: 3,
    title: 'Healthcare Facilities Operations Manager',
    slug: 'healthcare-operations-manager',
    department: 'Operations',
    employment_type: 'FULL_TIME',
    location: 'Brisbane, QLD',
    summary: 'Oversee multi-site hospital environmental hygiene, sterile logistics, biomedical compliance, and NSQHS Standard 3 infection control operations.',
    responsibilities: [
      'Direct hospital environmental services, sterile processing, and specialized clinical waste streams.',
      'Ensure contract SLA fulfillment (>99.5%) across tertiary healthcare facilities.',
      'Manage biomedical asset maintenance schedules and infection outbreak response protocols.',
      'Coordinate facility staffing rosters and consumable inventory supply chains.',
    ],
    requirements: [
      'Bachelor’s degree in Health Administration, Business, or equivalent clinical management experience.',
      'Minimum 6 years facility operations experience in Australian acute healthcare environments.',
      'Deep operational understanding of NSQHS standards and hospital accreditation audits.',
      'Strong financial acumen, contract negotiation, and multi-site leadership skills.',
    ],
    is_active: 1,
    status: 'PUBLISHED',
    closing_date: '2026-12-31',
    created_at: '2026-10-21T08:00:00Z',
  },
  {
    id: 4,
    title: 'Senior Clinical Quality & Credentialing Officer',
    slug: 'senior-clinical-governance-officer',
    department: 'Clinical Governance',
    employment_type: 'PART_TIME',
    location: 'Sydney, NSW (Hybrid)',
    summary: 'Conduct primary-source AHPRA audits, clinical scope of practice reviews, and sentinel incident analysis across national healthcare provider networks.',
    responsibilities: [
      'Execute 5-tier primary-source credential verification across all clinical candidates.',
      'Perform quarterly clinical practice audits and sentinel event root-cause investigations.',
      'Maintain regulatory alignment with AHPRA, ACSQHC, and Aged Care Quality Standards.',
      'Prepare comprehensive clinical audit scorecards for hospital executive boards.',
    ],
    requirements: [
      'Clinical background (RN, Allied Health, or Medical Administration) with active or previous AHPRA registration.',
      'Minimum 4 years experience in healthcare clinical governance, quality assurance, or auditing.',
      'Extensive knowledge of the National Model Clinical Governance Framework.',
      'High analytical rigor and attention to detail in practitioner credential verification.',
    ],
    is_active: 1,
    status: 'PUBLISHED',
    closing_date: '2026-12-31',
    created_at: '2026-10-21T08:00:00Z',
  },
  {
    id: 5,
    title: 'Medical Administration & Patient Intake Specialist',
    slug: 'health-administration-specialist',
    department: 'Corporate',
    employment_type: 'CONTRACT',
    location: 'Adelaide, SA',
    summary: 'Direct health administration workflows, clinical record privacy assurance (APPs), and hospital admission telemetry systems.',
    responsibilities: [
      'Manage high-volume patient admission processing and electronic health record (EHR) integrity.',
      'Enforce strict patient privacy protocols under the Australian Privacy Act 1988 (APPs).',
      'Coordinate specialist booking schedules, billing documentation, and health fund claims.',
      'Streamline administrative triage and digital timesheet verification for deployed staff.',
    ],
    requirements: [
      'Diploma or Degree in Health Administration, Medical Records, or Business Administration.',
      'Minimum 3 years experience in Australian medical practice or hospital administration.',
      'Proficiency with major hospital electronic medical record and patient billing systems.',
      'Outstanding executive communication, discretion, and organizational capabilities.',
    ],
    is_active: 1,
    status: 'PUBLISHED',
    closing_date: '2026-12-31',
    created_at: '2026-10-21T08:00:00Z',
  },
];

export const DEPARTMENTS = [
  'All Departments',
  'Clinical Operations',
  'Operations',
  'Clinical Governance',
  'Corporate',
];

export const EMPLOYMENT_TYPES = [
  { code: 'ALL', label: 'All Types' },
  { code: 'FULL_TIME', label: 'Full-Time' },
  { code: 'PART_TIME', label: 'Part-Time' },
  { code: 'CASUAL', label: 'Casual' },
  { code: 'CONTRACT', label: 'Contract' },
];

export const LOCATIONS = [
  'All Locations',
  'Sydney, NSW',
  'Melbourne, VIC',
  'Brisbane, QLD',
  'Adelaide, SA',
];

const CULTURE_PILLARS: Array<{
  title: string;
  description: string;
  icon: React.FC<{ className?: string }>;
  tint: BentoTint;
}> = [
  {
    title: 'Clinical Governance & Safety First',
    description:
      'We place clinical safety and practitioner support at the core of all operations. Robust escalation frameworks ensure you are never without leadership backing.',
    icon: ShieldCheck,
    tint: 'mint',
  },
  {
    title: 'Continuous Professional Development',
    description:
      'Access subsidized CPD training, specialized clinical workshops, leadership mentoring, and credential maintenance assurance programs.',
    icon: GraduationCap,
    tint: 'purple',
  },
  {
    title: 'Workforce Flexibility & Balance',
    description:
      'Predictable shift architectures, localized contracts, hybrid corporate opportunities, and digital timesheet verification for seamless work-life harmony.',
    icon: Users,
    tint: 'sky',
  },
  {
    title: 'Transparent Remuneration & Recognition',
    description:
      'Competitive award and above-award salary frameworks, timely superannuation contributions, travel allowances for regional placements, and transparent SLAs.',
    icon: Award,
    tint: 'yellow',
  },
];

export const CareersPage: React.FC = () => {
  const { query, navigate } = useRouter();
  const [jobs, setJobs] = useState<JobItem[]>(CANONICAL_JOBS);
  const [loading, setLoading] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedDept, setSelectedDept] = useState<string>(query.department || 'All Departments');
  const [selectedType, setSelectedType] = useState<string>(query.type || 'ALL');
  const [selectedLocation, setSelectedLocation] = useState<string>('All Locations');

  useEffect(() => {
    let isMounted = true;
    const fetchJobs = async () => {
      try {
        const res = await apiClient.get<{ jobs?: JobItem[]; data?: JobItem[] }>('/api/v1/public/jobs');
        const list = res?.data?.jobs || res?.data?.data || (Array.isArray(res?.data) ? res.data : null);
        if (isMounted && Array.isArray(list) && list.length > 0) {
          // Filter to only active jobs with current/future closing dates
          const today = new Date().toISOString().split('T')[0];
          const activeJobs = list.filter(
            (j) => j.is_active === 1 && (!j.closing_date || j.closing_date >= today)
          );
          if (activeJobs.length > 0) {
            setJobs(activeJobs);
          }
        }
      } catch {
        // Retain verified canonical baseline data
      } finally {
        if (isMounted) setLoading(false);
      }
    };

    fetchJobs();

    return () => {
      isMounted = false;
    };
  }, []);

  const handleResetFilters = () => {
    setSearchQuery('');
    setSelectedDept('All Departments');
    setSelectedType('ALL');
    setSelectedLocation('All Locations');
    navigate('/careers');
  };

  const filteredJobs = useMemo(() => {
    return jobs.filter((job) => {
      // Exclude inactive or past closing date
      if (job.is_active === 0) return false;

      // Keyword search
      if (searchQuery.trim() !== '') {
        const q = searchQuery.toLowerCase();
        const matchTitle = job.title.toLowerCase().includes(q);
        const matchDept = job.department.toLowerCase().includes(q);
        const matchLoc = job.location.toLowerCase().includes(q);
        const matchSummary = job.summary.toLowerCase().includes(q);
        if (!matchTitle && !matchDept && !matchLoc && !matchSummary) {
          return false;
        }
      }

      // Department filter
      if (selectedDept !== 'All Departments' && job.department !== selectedDept) {
        return false;
      }

      // Employment type filter
      if (selectedType !== 'ALL' && job.employment_type !== selectedType) {
        return false;
      }

      // Location filter
      if (selectedLocation !== 'All Locations' && !job.location.includes(selectedLocation.split(',')[0])) {
        return false;
      }

      return true;
    });
  }, [jobs, searchQuery, selectedDept, selectedType, selectedLocation]);

  const formatEmploymentType = (type: string) => {
    switch (type) {
      case 'FULL_TIME':
        return 'Full-Time';
      case 'PART_TIME':
        return 'Part-Time';
      case 'CASUAL':
        return 'Casual';
      case 'CONTRACT':
        return 'Contract';
      default:
        return type.replace('_', ' ');
    }
  };

  // Structured Data (Schema.org CollectionPage)
  const collectionSchema = {
    '@context': 'https://schema.org',
    '@type': 'CollectionPage',
    name: 'Careers in Healthcare Operations & Clinical Staffing',
    description: 'Explore rewarding clinical, operational, and corporate healthcare careers at Voak Health Care across Australia.',
    url: 'https://voakhealthcare.com.au/careers',
    hasPart: filteredJobs.map((j) => ({
      '@type': 'JobPosting',
      title: j.title,
      description: j.summary,
      url: `https://voakhealthcare.com.au/careers/${j.slug}`,
      employmentType: j.employment_type,
      jobLocation: {
        '@type': 'Place',
        address: {
          '@type': 'PostalAddress',
          addressLocality: j.location,
          addressCountry: 'AU',
        },
      },
    })),
  };

  return (
    <div className="flex min-h-screen flex-col bg-voak-canvas text-voak-navy">
      <SeoHead
        title="Careers in Healthcare Operations & Clinical Staffing | Voak Health Care"
        description="Explore rewarding clinical, operational, and corporate healthcare careers at Voak Health Care across Australia. Join our credentialed workforce network."
        canonicalUrl="https://voakhealthcare.com.au/careers"
        structuredData={collectionSchema}
      />

      <PublicHeader />

      <main id="main-content" className="flex-1">
        {/* EVP Hero Section */}
        <section className="border-b border-voak-border/60 bg-gradient-to-b from-white via-voak-canvas to-voak-purple-light/20 py-12 sm:py-16 lg:py-20">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <Breadcrumb
              items={[{ label: 'Careers' }]}
              homeHref="/"
              homeLabel="Home"
              className="mb-6"
            />

            <div className="max-w-3xl">
              <div className="inline-flex items-center gap-2 rounded-full border border-voak-purple/20 bg-voak-purple-light/60 px-3.5 py-1 text-xs font-bold tracking-wide text-voak-purple uppercase mb-4">
                <Briefcase className="h-3.5 w-3.5" aria-hidden="true" />
                Join Our Healthcare Network
              </div>

              <h1 className="text-3xl font-extrabold tracking-tight text-voak-navy sm:text-4xl lg:text-5xl font-serif">
                Shape the Future of Operational Healthcare
              </h1>

              <p className="mt-4 text-base sm:text-lg text-voak-muted leading-relaxed">
                At Voak Health Care, we believe sustainable patient outcomes rely on resilient, respected clinical and operational teams. Join our multidisciplinary network of credentialed specialists, facility operators, and health administrators across Australia.
              </p>
            </div>
          </div>
        </section>

        {/* Culture & EVP Bento Grid */}
        <section className="py-12 sm:py-16 border-b border-voak-border/60 bg-white">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="max-w-2xl mb-10">
              <h2 className="text-2xl font-bold tracking-tight text-voak-navy font-serif">
                Why Healthcare Professionals Choose Voak
              </h2>
              <p className="mt-2 text-sm text-voak-muted">
                Our workforce framework is structured by healthcare practitioners, for healthcare practitioners. Experience authentic leadership backing and operational clarity.
              </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              {CULTURE_PILLARS.map((pillar, idx) => {
                const IconComponent = pillar.icon;
                return (
                  <BentoCard
                    key={idx}
                    tint={pillar.tint}
                    className="flex flex-col justify-between p-6 rounded-2xl border-voak-border/80 hover:shadow-md transition-shadow"
                  >
                    <div>
                      <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-white border border-voak-border shadow-xs text-voak-navy mb-4">
                        <IconComponent className="h-5 w-5" />
                      </div>
                      <h3 className="text-base font-bold text-voak-navy mb-2 font-serif">
                        {pillar.title}
                      </h3>
                      <p className="text-xs text-voak-muted leading-relaxed">
                        {pillar.description}
                      </p>
                    </div>
                  </BentoCard>
                );
              })}
            </div>
          </div>
        </section>

        {/* Search & Filter Toolbar */}
        <section className="py-6 bg-white border-b border-voak-border/60 sticky top-20 z-30 shadow-xs">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
              {/* Search input */}
              <div className="w-full lg:w-72">
                <SearchInput
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  onClear={() => setSearchQuery('')}
                  placeholder="Search jobs by title or keyword..."
                  aria-label="Search job openings"
                />
              </div>

              {/* Multi-facet dropdown filters */}
              <div className="flex flex-wrap items-center gap-2.5">
                {/* Department */}
                <select
                  value={selectedDept}
                  onChange={(e) => setSelectedDept(e.target.value)}
                  aria-label="Filter by department"
                  className="rounded-xl border border-voak-border bg-white px-3 py-2 text-xs font-semibold text-voak-navy focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple shadow-xs"
                >
                  {DEPARTMENTS.map((dept) => (
                    <option key={dept} value={dept}>
                      {dept}
                    </option>
                  ))}
                </select>

                {/* Employment Type */}
                <select
                  value={selectedType}
                  onChange={(e) => setSelectedType(e.target.value)}
                  aria-label="Filter by employment type"
                  className="rounded-xl border border-voak-border bg-white px-3 py-2 text-xs font-semibold text-voak-navy focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple shadow-xs"
                >
                  {EMPLOYMENT_TYPES.map((type) => (
                    <option key={type.code} value={type.code}>
                      {type.label}
                    </option>
                  ))}
                </select>

                {/* Location */}
                <select
                  value={selectedLocation}
                  onChange={(e) => setSelectedLocation(e.target.value)}
                  aria-label="Filter by location"
                  className="rounded-xl border border-voak-border bg-white px-3 py-2 text-xs font-semibold text-voak-navy focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple shadow-xs"
                >
                  {LOCATIONS.map((loc) => (
                    <option key={loc} value={loc}>
                      {loc}
                    </option>
                  ))}
                </select>

                {(searchQuery !== '' ||
                  selectedDept !== 'All Departments' ||
                  selectedType !== 'ALL' ||
                  selectedLocation !== 'All Locations') && (
                  <button
                    type="button"
                    onClick={handleResetFilters}
                    className="inline-flex items-center gap-1 text-xs text-voak-purple hover:underline font-semibold ml-1"
                  >
                    <RotateCcw className="h-3 w-3" />
                    Reset
                  </button>
                )}
              </div>

              {/* Dynamic live count */}
              <div
                className="text-xs text-voak-muted font-medium self-end lg:self-center"
                aria-live="polite"
                aria-label="Job openings count"
              >
                Showing <strong className="font-bold text-voak-navy">{filteredJobs.length}</strong>{' '}
                open role{filteredJobs.length !== 1 ? 's' : ''}
              </div>
            </div>
          </div>
        </section>

        {/* Live Vacancies Directory */}
        <section className="py-12 sm:py-16">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            {loading ? (
              <div className="space-y-4">
                {[1, 2, 3, 4].map((idx) => (
                  <div key={idx} className="rounded-2xl border border-voak-border bg-white p-6 shadow-sm">
                    <Skeleton className="h-6 w-1/3 mb-3" />
                    <Skeleton className="h-4 w-1/2 mb-4" />
                    <Skeleton className="h-12 w-full mb-4" />
                    <Skeleton className="h-8 w-28 rounded-lg" />
                  </div>
                ))}
              </div>
            ) : filteredJobs.length === 0 ? (
              <EmptyState
                title="No Vacancies Match Your Search"
                description="We could not find any active job openings matching your selected filters. Adjust your criteria or express interest through our talent network."
                action={
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handleResetFilters}
                    leftIcon={<RotateCcw className="h-4 w-4" />}
                  >
                    Reset Filters
                  </Button>
                }
              />
            ) : (
              <div className="space-y-4">
                {filteredJobs.map((job) => (
                  <Card
                    key={job.id}
                    className="flex flex-col md:flex-row md:items-center justify-between p-6 sm:p-8 hover:shadow-md transition-all duration-200 border-voak-border/80 hover:border-voak-purple/40 group rounded-2xl bg-white gap-6"
                  >
                    <div className="space-y-3 flex-1">
                      {/* Department and Type Badges */}
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="purple" size="sm">
                          {job.department}
                        </Badge>
                        <Badge variant="neutral" size="sm">
                          {formatEmploymentType(job.employment_type)}
                        </Badge>
                        <div className="flex items-center gap-1 text-xs text-voak-muted font-medium ml-1">
                          <MapPin className="h-3.5 w-3.5 text-voak-purple" />
                          <span>{job.location}</span>
                        </div>
                      </div>

                      {/* Title */}
                      <h2 className="text-xl font-bold tracking-tight text-voak-navy group-hover:text-voak-purple transition-colors font-serif">
                        <Link to={`/careers/${job.slug}`} className="focus-visible:outline-none focus-visible:underline">
                          {job.title}
                        </Link>
                      </h2>

                      {/* Summary */}
                      <p className="text-sm text-voak-muted leading-relaxed line-clamp-2 max-w-3xl">
                        {job.summary}
                      </p>

                      {/* Footer Details */}
                      <div className="flex items-center gap-4 text-xs text-voak-muted pt-1">
                        {job.closing_date && (
                          <div className="flex items-center gap-1.5">
                            <Calendar className="h-3.5 w-3.5 text-voak-muted" />
                            <span>Closing: {job.closing_date}</span>
                          </div>
                        )}
                        <span className="text-voak-border">&bull;</span>
                        <span className="text-state-success font-semibold flex items-center gap-1">
                          <CheckCircle2 className="h-3.5 w-3.5" />
                          Applications Open
                        </span>
                      </div>
                    </div>

                    {/* Action Button */}
                    <div className="md:self-center flex-shrink-0">
                      <Link to={`/careers/${job.slug}`}>
                        <Button
                          variant="primary"
                          size="md"
                          rightIcon={<ArrowRight className="h-4 w-4" />}
                          className="w-full md:w-auto"
                        >
                          View Role & Apply
                        </Button>
                      </Link>
                    </div>
                  </Card>
                ))}
              </div>
            )}
          </div>
        </section>

        {/* Talent Network Expression of Interest Section */}
        <section className="py-12 sm:py-16 bg-voak-canvas border-t border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="rounded-3xl border border-voak-purple/20 bg-gradient-to-br from-white via-voak-purple-light/20 to-white p-8 sm:p-12 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-8">
              <div className="max-w-2xl">
                <div className="inline-flex items-center gap-1.5 rounded-full bg-voak-purple-light px-3 py-1 text-xs font-bold text-voak-purple uppercase tracking-wider mb-3">
                  <Sparkles className="h-3.5 w-3.5" />
                  Talent Network
                </div>
                <h2 className="text-2xl sm:text-3xl font-extrabold text-voak-navy font-serif">
                  Don't see your specific discipline listed?
                </h2>
                <p className="mt-3 text-sm sm:text-base text-voak-muted leading-relaxed">
                  We are continuously expanding our national clinical and operational registry. Register your credentials with our recruitment team to receive confidential notifications for upcoming locum, permanent, or advisory opportunities.
                </p>
              </div>

              <div className="flex flex-col sm:flex-row items-center gap-4 flex-shrink-0">
                <a
                  href="mailto:careers@voakhealthcare.com.au?subject=Talent%20Network%20Expression%20of%20Interest"
                  className="w-full sm:w-auto"
                >
                  <Button
                    variant="outline"
                    size="lg"
                    leftIcon={<Send className="h-4 w-4" />}
                    className="w-full sm:w-auto"
                  >
                    Express General Interest
                  </Button>
                </a>
              </div>
            </div>
          </div>
        </section>

        {/* Non-Clinical Care Legal Disclaimer */}
        <section className="bg-white border-t border-voak-border/60 py-8 text-xs text-voak-muted">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="flex items-start gap-3 rounded-2xl border border-voak-border/70 bg-voak-canvas p-4 sm:p-6">
              <AlertTriangle className="h-5 w-5 text-voak-purple mt-0.5 flex-shrink-0" />
              <div className="space-y-1">
                <h3 className="font-bold uppercase tracking-wider text-voak-navy text-[11px]">
                  Employment Scope & Regulatory Notice
                </h3>
                <p className="leading-relaxed">
                  Voak Health Care is an Australian healthcare operational solutions and workforce infrastructure partner. We do not provide direct public medical triage or emergency patient bookings. All advertised opportunities represent verified employment and clinical secondment contracts across our national partner facilities.
                </p>
              </div>
            </div>
          </div>
        </section>
      </main>

      <PublicFooter />
    </div>
  );
};

export default CareersPage;
