import React, { useState, useEffect, useMemo } from 'react';
import { PublicHeader } from '../components/PublicHeader';
import { PublicFooter } from '../components/PublicFooter';
import { SeoHead } from '../seo/SeoHead';
import { Breadcrumb } from '../../../shared/components/Breadcrumb';
import { Button } from '../../../shared/components/Button';
import { BentoCard, BentoTint } from '../../../shared/components/BentoCard';
import { Skeleton } from '../../../shared/components/Skeleton';
import { EmptyState } from '../../../shared/components/EmptyState';
import { SearchInput } from '../../../shared/components/SearchInput';
import { Link } from '../../../shared/router/Router';
import { apiClient } from '../../../shared/api/apiClient';
import {
  ArrowRight,
  Building2,
  HeartPulse,
  Stethoscope,
  Microscope,
  ShieldCheck,
  Briefcase,
  AlertTriangle,
  RotateCcw,
  Sparkles,
  Users2,
  CheckCircle,
} from 'lucide-react';

export interface IndustryItem {
  id: number;
  title: string;
  slug: string;
  summary: string;
  regulatory_burden_specs?: {
    governing_body?: string;
    standards?: string[];
    scope?: string;
  };
  pain_points?: string[];
  is_published?: number;
  display_order?: number;
}

// Canonical fallback data adhering to verified Phase 9 seed contracts
export const CANONICAL_INDUSTRIES: IndustryItem[] = [
  {
    id: 1,
    title: 'Hospitals & Health Services',
    slug: 'hospitals-health-services',
    summary: 'Public and private tertiary hospital networks and clinical day surgeries requiring rapid workforce deployment and strict accreditation assurance.',
    regulatory_burden_specs: {
      governing_body: 'Australian Commission on Safety and Quality in Health Care (ACSQHC)',
      standards: [
        'National Safety and Quality Health Service (NSQHS) Standards 1–8',
        'Clinical Governance Standard and Sentinel Event Reporting (SAC 1 & 2)',
        'Hand Hygiene Australia and National Infection Control Frameworks',
      ],
      scope: 'Acute care, emergency departments, surgical wards, and intensive care units.',
    },
    pain_points: [
      'Severe emergency department surges and inpatient bed-block pressure',
      'Specialist clinical nurse vacancies in ICU, CCU, and perioperative wards',
      'Accreditation vulnerability during unannounced NSQHS compliance surveillance audits',
      'High agency labor costs driven by fragmented, reactive procurement processes',
    ],
    display_order: 1,
    is_published: 1,
  },
  {
    id: 2,
    title: 'Residential Aged Care Facilities',
    slug: 'residential-aged-care',
    summary: 'Residential aged care homes and retirement communities navigating direct-care minute targets, 24/7 RN mandates, and quality audits.',
    regulatory_burden_specs: {
      governing_body: 'Aged Care Quality and Safety Commission (ACQSC)',
      standards: [
        'Aged Care Quality Standards (Standards 1–8)',
        'Mandatory 215 direct care minutes per resident per day (including 44 RN minutes)',
        'Mandatory 24/7 Registered Nurse on-site coverage legislation',
      ],
      scope: 'Residential care facilities, dementia-specific care wings, and multi-facility providers.',
    },
    pain_points: [
      'Meeting the non-negotiable 24/7 on-site Registered Nurse requirement',
      'Consistently achieving the mandatory 215 direct-care minute threshold',
      'High staff churn and administrative fatigue in verifying clinician compliance',
      'Risk of formal sanction and non-compliance notices from unannounced ACQSC site visits',
    ],
    display_order: 2,
    is_published: 1,
  },
  {
    id: 3,
    title: 'Primary Care & General Practice',
    slug: 'primary-care-general-practice',
    summary: 'Medical centres, GP clinics, and urgent care networks requiring dependable medical administrative operations and practice nursing.',
    regulatory_burden_specs: {
      governing_body: 'Royal Australian College of General Practitioners (RACGP)',
      standards: [
        'RACGP Standards for General Practices (5th edition)',
        'Medicare Benefits Schedule (MBS) billing integrity guidelines',
        'National Vaccine Storage Guidelines (Strive for 5) cold-chain compliance',
      ],
      scope: 'General practices, bulk-billing medical centres, and urgent outpatient clinics.',
    },
    pain_points: [
      'Chronic receptionist turnover causing front-desk congestion and patient friction',
      'Complex Medicare billing rejections and practitioner compliance risk',
      'Shortage of certified practice nurses for chronic disease care plans',
      'Administrative strain preparing clinics for RACGP 5th edition triennial accreditation',
    ],
    display_order: 3,
    is_published: 1,
  },
  {
    id: 4,
    title: 'Allied Health Networks',
    slug: 'allied-health-networks',
    summary: 'Multi-disciplinary physiotherapy, occupational therapy, and rehabilitation networks delivering NDIS and private clinical services.',
    regulatory_burden_specs: {
      governing_body: 'National Disability Insurance Agency (NDIA) & AHPRA',
      standards: [
        'NDIS Quality and Safeguards Commission Practice Standards',
        'AHPRA Allied Health National Boards Registration Standards',
        'Mandatory NDIS Worker Screening Checks and incident reporting',
      ],
      scope: 'Multi-site physiotherapy, occupational therapy, speech pathology, and rehab clinics.',
    },
    pain_points: [
      'Time-consuming NDIS reporting and service booking billing reconciliation',
      'High non-billable administrative burden on therapists reducing clinical hours',
      'Difficulty recruiting credentialed allied health clinicians in high-demand areas',
      'Managing clinician schedules and travel across distributed multi-clinic hubs',
    ],
    display_order: 4,
    is_published: 1,
  },
  {
    id: 5,
    title: 'Diagnostic & Pathology Centers',
    slug: 'diagnostic-pathology-centers',
    summary: 'Clinical laboratories and diagnostic imaging networks requiring high-turnaround specimen logistics and certified medical technicians.',
    regulatory_burden_specs: {
      governing_body: 'National Association of Testing Authorities (NATA) & RCPA',
      standards: [
        'ISO 15189 Medical Laboratories Quality and Competence Standards',
        'NATA / RCPA Joint Accreditation Criteria',
        'Diagnostic Imaging Accreditation Scheme (DIAS) standards',
      ],
      scope: 'Pathology laboratories, medical imaging centers, ultrasound and MRI clinics.',
    },
    pain_points: [
      'Maintaining strict turnaround time SLAs for critical and stat pathology specimens',
      'Severe national shortage of qualified pathology technicians and radiographers',
      'Stringent calibration and document audit standards required for NATA compliance',
      'Managing high-volume patient intake concierge and billing documentation',
    ],
    display_order: 5,
    is_published: 1,
  },
  {
    id: 6,
    title: 'Corporate & Occupational Health',
    slug: 'corporate-occupational-health',
    summary: 'Industrial sites, mining operations, and corporate campuses requiring workplace health surveillance, vaccination, and fit-for-work testing.',
    regulatory_burden_specs: {
      governing_body: 'Safe Work Australia & Comcare',
      standards: [
        'Work Health and Safety (WHS) Act 2011 and State Regulations',
        'National Standard for Health Assessment of Rail Safety Workers',
        'Occupational Noise Management Standard AS/NZS 1269',
      ],
      scope: 'Industrial manufacturing, mining camps, corporate enterprises, and transport hubs.',
    },
    pain_points: [
      'Deploying qualified occupational health nurses to remote and regional industrial locations',
      'Coordinating large-scale, multi-state annual workplace flu and health screening programs',
      'Rising workers compensation costs driven by delayed early musculoskeletal triage',
      'Bottlenecks in pre-employment medical clearances delaying workforce onboarding',
    ],
    display_order: 6,
    is_published: 1,
  },
];

const SECTOR_ACCENTS: Record<string, { bg: 'mint' | 'purple' | 'yellow' | 'pink' | 'white'; icon: React.FC<{ className?: string }> }> = {
  'hospitals-health-services': { bg: 'purple', icon: Building2 },
  'residential-aged-care': { bg: 'mint', icon: HeartPulse },
  'primary-care-general-practice': { bg: 'yellow', icon: Stethoscope },
  'allied-health-networks': { bg: 'pink', icon: Users2 },
  'diagnostic-pathology-centers': { bg: 'white', icon: Microscope },
  'corporate-occupational-health': { bg: 'white', icon: Briefcase },
};

export const IndustriesPage: React.FC = () => {
  const [industries, setIndustries] = useState<IndustryItem[]>(CANONICAL_INDUSTRIES);
  const [loading, setLoading] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');

  useEffect(() => {
    let isMounted = true;
    const fetchIndustries = async () => {
      try {
        const res = await apiClient.get<{ industries: IndustryItem[] }>('/api/v1/public/industries');
        if (isMounted && res?.data?.industries && Array.isArray(res.data.industries) && res.data.industries.length > 0) {
          setIndustries(res.data.industries);
        }
      } catch {
        // Fallback to canonical industries
      } finally {
        if (isMounted) setLoading(false);
      }
    };
    fetchIndustries();
    return () => {
      isMounted = false;
    };
  }, []);

  const handleResetFilters = () => {
    setSearchQuery('');
  };

  const filteredIndustries = useMemo(() => {
    if (searchQuery.trim() === '') return industries;
    const q = searchQuery.toLowerCase();
    return industries.filter((ind) => {
      const matchTitle = ind.title.toLowerCase().includes(q);
      const matchSummary = (ind.summary || '').toLowerCase().includes(q);
      const matchGov = (ind.regulatory_burden_specs?.governing_body || '').toLowerCase().includes(q);
      const matchPains = (ind.pain_points || []).some((p) => p.toLowerCase().includes(q));
      return matchTitle || matchSummary || matchGov || matchPains;
    });
  }, [industries, searchQuery]);

  // Schema.org CollectionPage
  const collectionSchema = {
    '@context': 'https://schema.org',
    '@type': 'CollectionPage',
    name: 'Healthcare Industries & Sectors Served',
    description: 'Voak Health Care delivers tailored operational and workforce solutions across Australian hospitals, aged care, primary clinics, and medical centers.',
    url: 'https://voakhealthcare.com.au/industries',
    hasPart: industries.map((ind) => ({
      '@type': 'WebPage',
      name: ind.title,
      description: ind.summary,
      url: `https://voakhealthcare.com.au/industries/${ind.slug}`,
    })),
  };

  return (
    <div className="flex min-h-screen flex-col bg-voak-canvas text-voak-navy">
      <SeoHead
        title="Healthcare Industries & Sectors Served | Voak Health Care"
        description="Voak Health Care delivers tailored operational and workforce solutions across Australian hospitals, aged care, primary clinics, and medical centers."
        canonicalUrl="https://voakhealthcare.com.au/industries"
        structuredData={collectionSchema}
      />

      <PublicHeader />

      <main id="main-content" className="flex-1">
        {/* Hero Section */}
        <section className="border-b border-voak-border/60 bg-gradient-to-b from-white via-voak-canvas to-voak-purple-light/20 py-12 sm:py-16 lg:py-20">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <Breadcrumb
              items={[{ label: 'Industries' }]}
              homeHref="/"
              homeLabel="Home"
              className="mb-6"
            />

            <div className="max-w-3xl">
              <div className="inline-flex items-center gap-2 rounded-full border border-voak-purple/20 bg-voak-purple-light/60 px-3.5 py-1 text-xs font-bold tracking-wide text-voak-purple uppercase mb-4">
                <Building2 className="h-3.5 w-3.5" aria-hidden="true" />
                Specialized Sector Expertise
              </div>

              <h1 className="text-3xl font-extrabold tracking-tight text-voak-navy sm:text-4xl lg:text-5xl font-serif">
                Healthcare Industries & Institutional Sectors
              </h1>

              <p className="mt-4 text-base sm:text-lg text-voak-muted leading-relaxed">
                Australian healthcare delivery demands deep, sector-specific operational alignment. From acute hospital surgical wings to residential aged care direct-minute mandates, explore how Voak tailors clinical governance, workforce continuity, and compliance frameworks to your exact institutional environment.
              </p>
            </div>
          </div>
        </section>

        {/* Search Bar & Telemetry */}
        <section className="py-6 bg-white border-b border-voak-border/60 sticky top-20 z-30 shadow-xs">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div className="w-full sm:w-80">
                <SearchInput
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  onClear={() => setSearchQuery('')}
                  placeholder="Filter sectors by challenge or keyword..."
                  aria-label="Search healthcare sectors"
                />
              </div>

              <div className="flex items-center justify-between sm:justify-end gap-4 text-xs text-voak-muted" aria-live="polite">
                <span>
                  Showing <strong className="font-bold text-voak-navy">{filteredIndustries.length}</strong> of{' '}
                  {industries.length} sectors
                </span>
                {searchQuery.trim() !== '' && (
                  <button
                    type="button"
                    onClick={handleResetFilters}
                    className="inline-flex items-center gap-1 text-voak-purple hover:underline font-medium"
                  >
                    <RotateCcw className="h-3 w-3" />
                    Reset
                  </button>
                )}
              </div>
            </div>
          </div>
        </section>

        {/* 6-Sector Bento Grid */}
        <section className="py-12 sm:py-16">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            {loading ? (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                {[1, 2, 3, 4, 5, 6].map((idx) => (
                  <div key={idx} className="rounded-3xl border border-voak-border bg-white p-6 shadow-sm">
                    <Skeleton className="h-10 w-10 rounded-2xl mb-4" />
                    <Skeleton className="h-6 w-3/4 mb-3" />
                    <Skeleton className="h-16 w-full mb-6" />
                    <Skeleton className="h-10 w-full rounded-xl" />
                  </div>
                ))}
              </div>
            ) : filteredIndustries.length === 0 ? (
              <EmptyState
                title="No Sectors Found"
                description="No healthcare sectors matched your search query. Reset your search to view all 6 institutional verticals."
                action={
                  <Button variant="outline" size="sm" onClick={handleResetFilters} leftIcon={<RotateCcw className="h-4 w-4" />}>
                    Reset Search
                  </Button>
                }
              />
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                {filteredIndustries.map((ind) => {
                  const accent = SECTOR_ACCENTS[ind.slug] || { bg: 'white', icon: Building2 };
                  const SectorIcon = accent.icon;
                  const govBody = ind.regulatory_burden_specs?.governing_body || 'National Healthcare Framework';

                  return (
                    <BentoCard
                      key={ind.id}
                      tint={accent.bg as BentoTint}
                      className="flex flex-col justify-between hover:shadow-lg transition-all duration-200 border-voak-border/80 hover:border-voak-purple/40 group rounded-3xl p-6 sm:p-8"
                    >
                      <div>
                        {/* Top Icon & Tag */}
                        <div className="flex items-center justify-between mb-4">
                          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white border border-voak-border/80 shadow-xs text-voak-navy group-hover:text-voak-purple transition-colors">
                            <SectorIcon className="h-6 w-6" />
                          </div>
                          <span className="text-[11px] font-bold text-voak-muted tracking-wider uppercase">
                            Vertical 0{ind.display_order || ind.id}
                          </span>
                        </div>

                        {/* Title */}
                        <h2 className="text-xl font-bold tracking-tight text-voak-navy group-hover:text-voak-purple transition-colors mb-2 font-serif">
                          {ind.title}
                        </h2>

                        {/* Summary */}
                        <p className="text-sm text-voak-muted leading-relaxed mb-6">
                          {ind.summary}
                        </p>

                        {/* Regulatory Body Callout */}
                        <div className="rounded-xl border border-voak-border/60 bg-white/80 p-3 mb-6">
                          <span className="text-[10px] font-bold uppercase tracking-wider text-voak-muted block mb-0.5">
                            Governing Body
                          </span>
                          <span className="text-xs font-semibold text-voak-navy leading-snug">
                            {govBody}
                          </span>
                        </div>

                        {/* Key Pain Points Snippet */}
                        {ind.pain_points && ind.pain_points.length > 0 && (
                          <div className="space-y-2 mb-6">
                            <span className="text-xs font-bold uppercase tracking-wider text-voak-navy block">
                              Sector Pressure Points:
                            </span>
                            <ul className="space-y-1.5">
                              {ind.pain_points.slice(0, 2).map((point, pIdx) => (
                                <li key={pIdx} className="flex items-start gap-2 text-xs text-voak-navy/80">
                                  <AlertTriangle className="h-3 w-3 text-voak-yellow shrink-0 mt-0.5" aria-hidden="true" />
                                  <span>{point}</span>
                                </li>
                              ))}
                            </ul>
                          </div>
                        )}
                      </div>

                      {/* Card Action Link */}
                      <div className="pt-4 border-t border-voak-border/60">
                        <Link to={`/industries/${ind.slug}`} className="w-full">
                          <Button
                            variant="primary"
                            size="sm"
                            className="w-full justify-between group-hover:bg-voak-navy transition-colors"
                            rightIcon={<ArrowRight className="h-3.5 w-3.5" />}
                          >
                            Explore Sector Solutions
                          </Button>
                        </Link>
                      </div>
                    </BentoCard>
                  );
                })}
              </div>
            )}
          </div>
        </section>

        {/* Cross-Sector Capabilities Matrix */}
        <section className="bg-white border-y border-voak-border/60 py-16">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="text-center max-w-3xl mx-auto mb-12">
              <span className="text-xs font-bold uppercase tracking-widest text-voak-purple block mb-2">
                Operational Foundation
              </span>
              <h2 className="text-2xl sm:text-3xl font-extrabold text-voak-navy font-serif">
                Universal Governance Standards Across All Sectors
              </h2>
              <p className="mt-2 text-sm sm:text-base text-voak-muted">
                Regardless of facility type, every Voak healthcare deployment benefits from our foundational governance architecture.
              </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
              <div className="rounded-2xl border border-voak-border/70 bg-voak-canvas/40 p-5">
                <ShieldCheck className="h-6 w-6 text-voak-purple mb-3" />
                <h3 className="text-sm font-bold text-voak-navy mb-1">Primary-Source Credentialing</h3>
                <p className="text-xs text-voak-muted leading-relaxed">
                  Daily automated AHPRA registry synchronization ensuring zero uncredentialed clinical practice.
                </p>
              </div>

              <div className="rounded-2xl border border-voak-border/70 bg-voak-canvas/40 p-5">
                <CheckCircle className="h-6 w-6 text-voak-mint mb-3" />
                <h3 className="text-sm font-bold text-voak-navy mb-1">Incident Escalation Protocols</h3>
                <p className="text-xs text-voak-muted leading-relaxed">
                  Structured 4-severity escalation framework with mandatory 1-hour executive notification windows.
                </p>
              </div>

              <div className="rounded-2xl border border-voak-border/70 bg-voak-canvas/40 p-5">
                <HeartPulse className="h-6 w-6 text-voak-pink mb-3" />
                <h3 className="text-sm font-bold text-voak-navy mb-1">24/7 Clinical Coordination</h3>
                <p className="text-xs text-voak-muted leading-relaxed">
                  Dedicated round-the-clock clinical coordinators resolving emergency shift vacancies in real time.
                </p>
              </div>

              <div className="rounded-2xl border border-voak-border/70 bg-voak-canvas/40 p-5">
                <Sparkles className="h-6 w-6 text-voak-yellow mb-3" />
                <h3 className="text-sm font-bold text-voak-navy mb-1">Accreditation Readiness</h3>
                <p className="text-xs text-voak-muted leading-relaxed">
                  Full compliance dossiers pre-compiled for unannounced NSQHS and ACQSC surveillance audits.
                </p>
              </div>
            </div>
          </div>
        </section>

        {/* Strategic Consultation CTA */}
        <section className="py-16 sm:py-20 bg-voak-canvas">
          <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div className="rounded-3xl border border-voak-purple/30 bg-gradient-to-r from-voak-purple-light/40 via-white to-voak-mint-light/40 p-8 sm:p-12 shadow-sm text-center">
              <span className="inline-block rounded-full border border-voak-purple/20 bg-white px-3.5 py-1 text-xs font-bold uppercase tracking-wider text-voak-purple mb-4">
                Sector Consultation
              </span>
              <h2 className="text-2xl sm:text-3xl font-extrabold text-voak-navy font-serif">
                Schedule an Institutional Sector Briefing
              </h2>
              <p className="mt-3 text-sm sm:text-base text-voak-muted max-w-2xl mx-auto">
                Connect with our clinical governance leads and workforce specialists to review sector-specific rate cards, staffing continuity models, and SLA frameworks.
              </p>
              <div className="mt-8 flex flex-wrap items-center justify-center gap-4">
                <Link to="/request-proposal">
                  <Button variant="primary" size="lg" rightIcon={<ArrowRight className="h-4 w-4" />}>
                    Request Sector Proposal
                  </Button>
                </Link>
                <Link to="/contact">
                  <Button variant="outline" size="lg">
                    Speak with an Industry Lead
                  </Button>
                </Link>
              </div>
            </div>
          </div>
        </section>
      </main>

      <PublicFooter />
    </div>
  );
};

export default IndustriesPage;
