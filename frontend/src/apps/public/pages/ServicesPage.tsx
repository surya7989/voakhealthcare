import React, { useState, useEffect, useMemo } from 'react';
import { PublicHeader } from '../components/PublicHeader';
import { PublicFooter } from '../components/PublicFooter';
import { SeoHead } from '../seo/SeoHead';
import { Breadcrumb } from '../../../shared/components/Breadcrumb';
import { Button } from '../../../shared/components/Button';
import { Badge } from '../../../shared/components/Badge';
import { Card } from '../../../shared/components/Card';
import { Skeleton } from '../../../shared/components/Skeleton';
import { EmptyState } from '../../../shared/components/EmptyState';
import { SearchInput } from '../../../shared/components/SearchInput';
import { Link, useRouter } from '../../../shared/router/Router';
import { apiClient } from '../../../shared/api/apiClient';
import {
  ArrowRight,
  CheckCircle2,
  FileText,
  ShieldCheck,
  Stethoscope,
  Building,
  ClipboardList,
  Truck,
  LineChart,
  RotateCcw,
  Sparkles,
} from 'lucide-react';

export interface ServiceItem {
  id: number;
  title: string;
  slug: string;
  tagline: string;
  description: string;
  category_code?: string;
  deliverables?: string[];
  sla_metrics?: Record<string, string>;
  is_published?: number;
  display_order?: number;
}

// Canonical fallback data adhering to verified Phase 8 seed contracts
export const CANONICAL_SERVICES: ServiceItem[] = [
  {
    id: 1,
    title: 'Clinical Workforce Solutions',
    slug: 'clinical-workforce-solutions',
    tagline: 'Rapid deployment of credentialed nurses and specialist clinical teams across acute and aged care.',
    description: 'Comprehensive clinical and locum workforce solutions tailored for Australian healthcare institutions. We provide rapid deployment of AHPRA-registered nurses, clinical nurse specialists, and allied health professionals with rigorous clinical governance.',
    category_code: 'workforce',
    deliverables: [
      'Rapid 24/7 surge staffing and locum placement',
      '100% verified AHPRA registration and working with children checks',
      'Dedicated clinical governance and escalation framework',
      'Integrated shift scheduling and digital timesheet verification',
    ],
    sla_metrics: {
      urgent_shift_fill_rate: '>= 98.5%',
      credential_audit_compliance: '100% Verified',
      escalation_response_window: '< 15 Minutes',
    },
    display_order: 1,
    is_published: 1,
  },
  {
    id: 2,
    title: 'Healthcare Facility Operations',
    slug: 'healthcare-facility-operations',
    tagline: 'Integrated infection control, environmental hygiene, and facility management for healthcare environments.',
    description: 'Integrated healthcare environmental services meeting strict NSQHS infection control standards. Comprehensive clinical cleaning, specialized waste management, linen logistics, and biomedical asset management.',
    category_code: 'operations',
    deliverables: [
      'Hospital-grade terminal cleaning and infection control protocols',
      'Clinical and hazardous waste management compliance',
      'Specialized linen and sterile supply logistics',
      'Facility maintenance and biomedical compliance tracking',
    ],
    sla_metrics: {
      infection_control_audit: '>= 99.2%',
      critical_environmental_response: '< 30 Minutes',
      preventative_maintenance: '100% On-Schedule',
    },
    display_order: 2,
    is_published: 1,
  },
  {
    id: 3,
    title: 'Health Administration & Medical Support',
    slug: 'health-administration-support',
    tagline: 'Turnkey medical reception, clinical ward clerks, billing, and health records management.',
    description: 'End-to-end medical administrative support designed to streamline clinic workflows and hospital ward administration. Experienced medical receptionists, ward clerks, MBS/PBS billing specialists, and transcriptionists.',
    category_code: 'admin',
    deliverables: [
      'Specialized medical reception and patient concierge',
      'Clinical ward clerk and departmental coordination',
      'MBS, DVA and private health fund billing optimization',
      'Electronic medical records (EMR) and transcription services',
    ],
    sla_metrics: {
      billing_accuracy_rate: '>= 99.8%',
      call_answer_response: '< 20 Seconds',
      record_processing_turnaround: '< 2 Hours',
    },
    display_order: 3,
    is_published: 1,
  },
  {
    id: 4,
    title: 'Healthcare Compliance & Clinical Governance',
    slug: 'clinical-compliance-governance',
    tagline: 'NSQHS accreditation readiness, clinical audit frameworks, and AHPRA credentialing assurance.',
    description: 'Expert clinical governance advisory and regulatory readiness consulting for healthcare operators. We prepare facilities for NSQHS and Aged Care Quality Standards accreditation audits and conduct independent clinical incident reviews.',
    category_code: 'governance',
    deliverables: [
      'NSQHS and Aged Care Quality Standards audit readiness',
      'Clinical risk assessments and sentinel event investigations',
      'Ongoing clinical scope of practice and credentialing audits',
      'Policy framework drafting and clinical workflow standardization',
    ],
    sla_metrics: {
      audit_readiness_scorecard: '100% Compliant',
      incident_review_turnaround: '< 48 Hours',
      policy_review_cycle: 'Annual Mandatory Verification',
    },
    display_order: 4,
    is_published: 1,
  },
  {
    id: 5,
    title: 'Healthcare Logistics & Supply Chain Support',
    slug: 'healthcare-logistics-supply',
    tagline: 'Optimized medical consumable procurement, point-of-care inventory replenishment, and PPE distribution.',
    description: 'Reliable healthcare supply chain management ensuring clinical teams always have required consumables, pharmaceuticals, and personal protective equipment. Point-of-care stock tracking and cold-chain integrity assurance.',
    category_code: 'logistics',
    deliverables: [
      'Critical clinical supplies inventory management',
      'Cold-chain temperature-controlled logistics',
      'Emergency PPE and consumable stockpile maintenance',
      'Multi-site healthcare distribution and replenishment',
    ],
    sla_metrics: {
      order_fulfillment_accuracy: '>= 99.5%',
      emergency_stock_delivery: '< 4 Hours',
      cold_chain_audit_compliance: '100% Logged',
    },
    display_order: 5,
    is_published: 1,
  },
  {
    id: 6,
    title: 'Healthcare Consulting & Operational Advisory',
    slug: 'healthcare-consulting-advisory',
    tagline: 'Clinical workforce roster optimization, cost-containment reviews, and digital workflow transformation.',
    description: 'Strategic advisory services for executive healthcare leadership. We specialize in nursing roster optimization, agency spend reduction, digital clinical pathway implementation, and bed-management efficiency across complex health networks.',
    category_code: 'consulting',
    deliverables: [
      'Agency labor spend reduction and roster optimization',
      'Clinical workflow and patient journey bottleneck analysis',
      'Digital health and EMR integration advisory',
      'Capacity planning and bed turnaround efficiency reviews',
    ],
    sla_metrics: {
      agency_spend_optimization: '10-25% Reduction',
      advisory_milestone_delivery: '100% On-Time',
      executive_satisfaction_rating: '>= 95%',
    },
    display_order: 6,
    is_published: 1,
  },
];

export const SERVICE_CATEGORIES = [
  { code: 'all', label: 'All Services', icon: Sparkles },
  { code: 'workforce', label: 'Clinical Workforce', icon: Stethoscope },
  { code: 'operations', label: 'Facility Operations', icon: Building },
  { code: 'admin', label: 'Health Administration', icon: ClipboardList },
  { code: 'governance', label: 'Clinical Governance', icon: ShieldCheck },
  { code: 'logistics', label: 'Logistics & Supply', icon: Truck },
  { code: 'consulting', label: 'Consulting & Advisory', icon: LineChart },
];

export const ServicesPage: React.FC = () => {
  const { query, navigate } = useRouter();
  const [services, setServices] = useState<ServiceItem[]>(CANONICAL_SERVICES);
  const [loading, setLoading] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');

  // Active category derived from query string (?category=...)
  const activeCategory = query.category || 'all';

  useEffect(() => {
    let isMounted = true;
    const fetchServices = async () => {
      try {
        const res = await apiClient.get<{ services: ServiceItem[] }>('/api/v1/public/services');
        if (isMounted && res?.data?.services && Array.isArray(res.data.services) && res.data.services.length > 0) {
          setServices(res.data.services);
        }
      } catch {
        // Fallback to canonical services
      } finally {
        if (isMounted) setLoading(false);
      }
    };
    fetchServices();
    return () => {
      isMounted = false;
    };
  }, []);

  const handleCategoryChange = (code: string) => {
    if (code === 'all') {
      navigate('/services');
    } else {
      navigate(`/services?category=${encodeURIComponent(code)}`);
    }
  };

  const handleResetFilters = () => {
    setSearchQuery('');
    navigate('/services');
  };

  // Map category code to human-readable label
  const getCategoryLabel = (svc: ServiceItem): string => {
    if (svc.category_code) {
      const match = SERVICE_CATEGORIES.find((c) => c.code === svc.category_code);
      if (match) return match.label;
    }
    // Heuristic mapping for database records based on slug
    if (svc.slug.includes('workforce')) return 'Clinical Workforce';
    if (svc.slug.includes('facility')) return 'Facility Operations';
    if (svc.slug.includes('administration')) return 'Health Administration';
    if (svc.slug.includes('compliance') || svc.slug.includes('governance')) return 'Clinical Governance';
    if (svc.slug.includes('logistics') || svc.slug.includes('supply')) return 'Logistics & Supply';
    if (svc.slug.includes('consulting') || svc.slug.includes('advisory')) return 'Consulting & Advisory';
    return 'Clinical Operations';
  };

  // Filtered services
  const filteredServices = useMemo(() => {
    return services.filter((svc) => {
      // Category filter check
      if (activeCategory !== 'all') {
        const cat = svc.category_code || (
          svc.slug.includes('workforce') ? 'workforce' :
          svc.slug.includes('facility') ? 'operations' :
          svc.slug.includes('administration') ? 'admin' :
          svc.slug.includes('compliance') || svc.slug.includes('governance') ? 'governance' :
          svc.slug.includes('logistics') || svc.slug.includes('supply') ? 'logistics' :
          svc.slug.includes('consulting') || svc.slug.includes('advisory') ? 'consulting' : ''
        );
        if (cat !== activeCategory && activeCategory !== svc.slug) {
          return false;
        }
      }

      // Keyword search check
      if (searchQuery.trim() !== '') {
        const q = searchQuery.toLowerCase();
        const matchesTitle = svc.title.toLowerCase().includes(q);
        const matchesTagline = (svc.tagline || '').toLowerCase().includes(q);
        const matchesDesc = (svc.description || '').toLowerCase().includes(q);
        const matchesDeliverables = (svc.deliverables || []).some((d) => d.toLowerCase().includes(q));
        if (!matchesTitle && !matchesTagline && !matchesDesc && !matchesDeliverables) {
          return false;
        }
      }

      return true;
    });
  }, [services, activeCategory, searchQuery]);

  // JSON-LD ItemList Schema
  const itemListSchema = {
    '@context': 'https://schema.org',
    '@type': 'ItemList',
    itemListElement: services.map((svc, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      item: {
        '@type': 'Service',
        name: svc.title,
        description: svc.tagline || svc.description,
        url: `https://voakhealthcare.com.au/services/${svc.slug}`,
        provider: {
          '@type': 'MedicalOrganization',
          name: 'Voak Health Care',
          url: 'https://voakhealthcare.com.au',
        },
      },
    })),
  };

  return (
    <div className="flex min-h-screen flex-col bg-voak-canvas text-voak-navy">
      <SeoHead
        title="Healthcare Services & Operational Solutions | Voak Health Care"
        description="Explore Voak Health Care's enterprise services: healthcare workforce staffing, clinical facility management, regulatory compliance, and health administration."
        canonicalUrl="https://voakhealthcare.com.au/services"
        structuredData={itemListSchema}
      />

      <PublicHeader />

      <main id="main-content" className="flex-1">
        {/* Hero Section */}
        <section className="border-b border-voak-border/60 bg-gradient-to-b from-white via-voak-canvas to-voak-purple-light/20 py-12 sm:py-16 lg:py-20">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <Breadcrumb
              items={[{ label: 'Services' }]}
              homeHref="/"
              homeLabel="Home"
              className="mb-6"
            />

            <div className="max-w-3xl">
              <div className="inline-flex items-center gap-2 rounded-full border border-voak-purple/20 bg-voak-purple-light/60 px-3.5 py-1 text-xs font-bold tracking-wide text-voak-purple uppercase mb-4">
                <Sparkles className="h-3.5 w-3.5" aria-hidden="true" />
                Comprehensive Healthcare Offerings
              </div>

              <h1 className="text-3xl font-extrabold tracking-tight text-voak-navy sm:text-4xl lg:text-5xl font-serif">
                Enterprise Healthcare Services & Clinical Solutions
              </h1>

              <p className="mt-4 text-base sm:text-lg text-voak-muted leading-relaxed">
                Discover our specialized B2B operational service pillars. Designed to relieve institutional staffing bottlenecks, uphold stringent NSQHS accreditation readiness, and maintain continuous clinical operational excellence across acute and community healthcare settings.
              </p>
            </div>
          </div>
        </section>

        {/* Search & Category Filter Section */}
        <section className="py-8 bg-white border-b border-voak-border/60 sticky top-20 z-30 shadow-xs">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
              {/* Category Pills (Horizontal Scroll on Mobile) */}
              <div
                className="flex items-center gap-1.5 overflow-x-auto pb-2 md:pb-0 scrollbar-none"
                role="toolbar"
                aria-label="Filter services by operational category"
              >
                {SERVICE_CATEGORIES.map((cat) => {
                  const isSelected = activeCategory === cat.code;
                  return (
                    <button
                      key={cat.code}
                      type="button"
                      onClick={() => handleCategoryChange(cat.code)}
                      aria-pressed={isSelected}
                      className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-semibold transition-all select-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple ${
                        isSelected
                          ? 'bg-voak-purple text-white shadow-sm'
                          : 'bg-voak-canvas text-voak-navy/80 hover:bg-voak-purple-light/60 hover:text-voak-purple'
                      }`}
                    >
                      <cat.icon className="h-3.5 w-3.5" aria-hidden="true" />
                      <span>{cat.label}</span>
                    </button>
                  );
                })}
              </div>

              {/* Search Input */}
              <div className="w-full md:w-72 shrink-0">
                <SearchInput
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  onClear={() => setSearchQuery('')}
                  placeholder="Search services..."
                  aria-label="Search healthcare services"
                />
              </div>
            </div>

            {/* Live Filter Telemetry Announcement */}
            <div className="mt-3 flex items-center justify-between text-xs text-voak-muted" aria-live="polite">
              <span>
                Showing <strong className="font-bold text-voak-navy">{filteredServices.length}</strong> of{' '}
                {services.length} services
              </span>
              {(activeCategory !== 'all' || searchQuery.trim() !== '') && (
                <button
                  type="button"
                  onClick={handleResetFilters}
                  className="inline-flex items-center gap-1 text-voak-purple hover:underline font-medium"
                >
                  <RotateCcw className="h-3 w-3" />
                  Reset filters
                </button>
              )}
            </div>
          </div>
        </section>

        {/* Services Grid Section */}
        <section className="py-12 sm:py-16">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            {loading ? (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                {[1, 2, 3, 4, 5, 6].map((idx) => (
                  <div key={idx} className="rounded-2xl border border-voak-border bg-white p-6 shadow-sm">
                    <Skeleton className="h-6 w-24 rounded-full mb-4" />
                    <Skeleton className="h-8 w-3/4 mb-3" />
                    <Skeleton className="h-16 w-full mb-6" />
                    <Skeleton className="h-4 w-full mb-2" />
                    <Skeleton className="h-4 w-5/6 mb-6" />
                    <Skeleton className="h-10 w-full rounded-xl" />
                  </div>
                ))}
              </div>
            ) : filteredServices.length === 0 ? (
              <EmptyState
                title="No Services Found"
                description="No services currently match your selected category or search query. Contact our advisory team for bespoke requirements."
                action={
                  <Button variant="outline" size="sm" onClick={handleResetFilters} leftIcon={<RotateCcw className="h-4 w-4" />}>
                    Reset Filters
                  </Button>
                }
              />
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                {filteredServices.map((svc) => {
                  const categoryLabel = getCategoryLabel(svc);
                  return (
                    <Card
                      key={svc.id}
                      className="flex flex-col justify-between hover:shadow-lg transition-all duration-200 border-voak-border/70 hover:border-voak-purple/40 group rounded-2xl overflow-hidden bg-white"
                    >
                      <div>
                        {/* Header Badge */}
                        <div className="flex items-center justify-between mb-4">
                          <Badge variant="purple" size="sm">
                            {categoryLabel}
                          </Badge>
                          <span className="text-[11px] font-bold text-voak-muted tracking-wider uppercase">
                            Pillar 0{svc.display_order || svc.id}
                          </span>
                        </div>

                        {/* Title */}
                        <h2 className="text-xl font-bold tracking-tight text-voak-navy group-hover:text-voak-purple transition-colors mb-2 font-serif">
                          {svc.title}
                        </h2>

                        {/* Tagline */}
                        <p className="text-sm text-voak-muted leading-relaxed mb-6">
                          {svc.tagline}
                        </p>

                        {/* Deliverables Summary Checklist */}
                        {svc.deliverables && svc.deliverables.length > 0 && (
                          <div className="border-t border-voak-border/60 pt-4 mb-6">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-voak-navy mb-3">
                              Key Deliverables:
                            </h3>
                            <ul className="space-y-2">
                              {svc.deliverables.slice(0, 3).map((item, idx) => (
                                <li key={idx} className="flex items-start gap-2 text-xs text-voak-navy/80">
                                  <CheckCircle2 className="h-3.5 w-3.5 text-voak-mint shrink-0 mt-0.5" aria-hidden="true" />
                                  <span>{item}</span>
                                </li>
                              ))}
                            </ul>
                          </div>
                        )}
                      </div>

                      {/* Card Actions */}
                      <div className="pt-4 border-t border-voak-border/60 flex flex-col gap-2">
                        <Link to={`/services/${svc.slug}`} className="w-full">
                          <Button
                            variant="primary"
                            size="sm"
                            className="w-full justify-between group-hover:bg-voak-navy transition-colors"
                            rightIcon={<ArrowRight className="h-3.5 w-3.5" />}
                          >
                            View Service Details
                          </Button>
                        </Link>
                        <Link to={`/request-proposal?service=${svc.id}`} className="w-full">
                          <Button
                            variant="ghost"
                            size="sm"
                            className="w-full text-xs text-voak-muted hover:text-voak-purple"
                            leftIcon={<FileText className="h-3.5 w-3.5" />}
                          >
                            Request Scope Proposal
                          </Button>
                        </Link>
                      </div>
                    </Card>
                  );
                })}
              </div>
            )}
          </div>
        </section>

        {/* SLA & Governance Pledge Banner */}
        <section className="bg-voak-navy text-white py-12 sm:py-16">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-8 text-center md:text-left">
              <div className="flex flex-col items-center md:items-start">
                <div className="h-12 w-12 rounded-2xl bg-white/10 flex items-center justify-center mb-4 text-voak-mint">
                  <ShieldCheck className="h-6 w-6" aria-hidden="true" />
                </div>
                <h3 className="text-lg font-bold text-white mb-2">100% Credential Audit</h3>
                <p className="text-sm text-white/70 leading-relaxed">
                  Every clinician and support specialist undergoes primary-source AHPRA registration, police check, and WWCC verification.
                </p>
              </div>

              <div className="flex flex-col items-center md:items-start">
                <div className="h-12 w-12 rounded-2xl bg-white/10 flex items-center justify-center mb-4 text-voak-yellow">
                  <Stethoscope className="h-6 w-6" aria-hidden="true" />
                </div>
                <h3 className="text-lg font-bold text-white mb-2">Clinical Governance Rigor</h3>
                <p className="text-sm text-white/70 leading-relaxed">
                  Overseen by an active Clinical Advisory Board ensuring strict alignment with NSQHS and Aged Care Quality Standards.
                </p>
              </div>

              <div className="flex flex-col items-center md:items-start">
                <div className="h-12 w-12 rounded-2xl bg-white/10 flex items-center justify-center mb-4 text-voak-pink">
                  <FileText className="h-6 w-6" aria-hidden="true" />
                </div>
                <h3 className="text-lg font-bold text-white mb-2">Transparent SLA Metrics</h3>
                <p className="text-sm text-white/70 leading-relaxed">
                  Real-time shift tracking, guaranteed fill rates, and dedicated account managers providing measurable operational continuity.
                </p>
              </div>
            </div>
          </div>
        </section>

        {/* Proposal Callout Banner */}
        <section className="py-16 sm:py-20 bg-voak-canvas">
          <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div className="rounded-3xl border border-voak-purple/30 bg-gradient-to-r from-voak-purple-light/40 via-white to-voak-mint-light/40 p-8 sm:p-12 shadow-sm text-center">
              <span className="inline-block rounded-full border border-voak-purple/20 bg-white px-3.5 py-1 text-xs font-bold uppercase tracking-wider text-voak-purple mb-4">
                Bespoke Workforce Architecture
              </span>
              <h2 className="text-2xl sm:text-3xl font-extrabold text-voak-navy font-serif">
                Need a Custom Healthcare Operational Feasibility Review?
              </h2>
              <p className="mt-3 text-sm sm:text-base text-voak-muted max-w-2xl mx-auto">
                Our clinical directors and workforce architects evaluate your roster gaps, bed-occupancy projections, and accreditation timelines to deliver a tailored service level agreement.
              </p>
              <div className="mt-8 flex flex-wrap items-center justify-center gap-4">
                <Link to="/request-proposal">
                  <Button variant="primary" size="lg" rightIcon={<ArrowRight className="h-4 w-4" />}>
                    Request Formal Proposal
                  </Button>
                </Link>
                <Link to="/contact">
                  <Button variant="outline" size="lg">
                    Schedule Discovery Consultation
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

export default ServicesPage;
