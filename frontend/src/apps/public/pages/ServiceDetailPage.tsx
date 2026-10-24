import React, { useState, useEffect } from 'react';
import { PublicHeader } from '../components/PublicHeader';
import { PublicFooter } from '../components/PublicFooter';
import { SeoHead } from '../seo/SeoHead';
import { Breadcrumb } from '../../../shared/components/Breadcrumb';
import { Button } from '../../../shared/components/Button';
import { Badge } from '../../../shared/components/Badge';
import { Card } from '../../../shared/components/Card';
import { Skeleton } from '../../../shared/components/Skeleton';
import { NotFoundPage } from './NotFoundPage';
import { CANONICAL_SERVICES, ServiceItem } from './ServicesPage';
import { Link, useRouter } from '../../../shared/router/Router';
import { apiClient } from '../../../shared/api/apiClient';
import {
  ArrowRight,
  CheckCircle2,
  Building2,
  Sparkles,
  PhoneCall,
  Activity,
  Layers,
} from 'lucide-react';

export interface ServiceDetailPayload extends ServiceItem {
  industry_ids?: number[];
}

export interface ServiceDetailPageProps {
  slug?: string;
}

// Canonical industries for cross-linking
const CANONICAL_INDUSTRY_CROSS_LINKS = [
  { id: 1, title: 'Hospitals & Health Services', slug: 'hospitals-health-services' },
  { id: 2, title: 'Residential Aged Care Facilities', slug: 'residential-aged-care' },
  { id: 3, title: 'Primary Care & General Practice', slug: 'primary-care-general-practice' },
  { id: 4, title: 'Allied Health Networks', slug: 'allied-health-networks' },
  { id: 5, title: 'Diagnostic & Pathology Centers', slug: 'diagnostic-pathology-centers' },
  { id: 6, title: 'Corporate & Occupational Health', slug: 'corporate-occupational-health' },
];

export const ServiceDetailPage: React.FC<ServiceDetailPageProps> = ({ slug: propSlug }) => {
  const { path } = useRouter();

  // Extract slug from route path: /services/:slug
  const currentSlug = propSlug || (() => {
    const cleanPath = path.split('?')[0].split('#')[0];
    const match = cleanPath.match(/^\/services\/([a-zA-Z0-9_-]+)/);
    return match ? match[1] : '';
  })();

  const initialFallback = CANONICAL_SERVICES.find(
    (s) => s.slug === currentSlug && s.is_published !== 0
  ) || null;

  const [service, setService] = useState<ServiceDetailPayload | null>(initialFallback);
  const [loading, setLoading] = useState(!initialFallback);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    let isMounted = true;

    if (!currentSlug) {
      setNotFound(true);
      setLoading(false);
      return;
    }

    const fallback = CANONICAL_SERVICES.find(
      (s) => s.slug === currentSlug && s.is_published !== 0
    ) || null;

    if (fallback) {
      setService(fallback);
      setLoading(false);
    } else {
      setLoading(true);
    }

    const fetchService = async () => {
      try {
        const res = await apiClient.get<{ service: ServiceDetailPayload }>(
          `/api/v1/public/services/${encodeURIComponent(currentSlug)}`
        );
        if (isMounted) {
          if (res?.data?.service && res.data.service.is_published !== 0) {
            setService(res.data.service);
          } else {
            // Check canonical fallback
            const match = CANONICAL_SERVICES.find(
              (s) => s.slug === currentSlug && s.is_published !== 0
            );
            if (match) {
              setService(match);
            } else {
              setNotFound(true);
            }
          }
        }
      } catch {
        if (isMounted) {
          // Check canonical fallback before setting 404
          const match = CANONICAL_SERVICES.find(
            (s) => s.slug === currentSlug && s.is_published !== 0
          );
          if (match) {
            setService(match);
          } else {
            setNotFound(true);
          }
        }
      } finally {
        if (isMounted) setLoading(false);
      }
    };

    fetchService();

    return () => {
      isMounted = false;
    };
  }, [currentSlug]);

  if (notFound) {
    return (
      <NotFoundPage
        resourceType="service"
        title="Healthcare Service Not Found"
        message={`We could not locate a published healthcare service matching the identifier "${currentSlug}". It may have been renamed, archived, or does not exist.`}
      />
    );
  }

  if (loading || !service) {
    return (
      <div className="flex min-h-screen flex-col bg-voak-canvas text-voak-navy">
        <PublicHeader />
        <main id="main-content" className="flex-1 py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">
          <Skeleton className="h-4 w-48 mb-6" />
          <Skeleton className="h-6 w-32 rounded-full mb-4" />
          <Skeleton className="h-12 w-3/4 mb-4" />
          <Skeleton className="h-6 w-full max-w-2xl mb-8" />
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div className="lg:col-span-2 space-y-6">
              <Skeleton className="h-48 w-full rounded-2xl" />
              <Skeleton className="h-48 w-full rounded-2xl" />
            </div>
            <div className="space-y-6">
              <Skeleton className="h-64 w-full rounded-2xl" />
            </div>
          </div>
        </main>
        <PublicFooter />
      </div>
    );
  }

  // Sibling services (excluding current)
  const siblingServices = CANONICAL_SERVICES.filter((s) => s.slug !== service.slug).slice(0, 2);

  // Applicable industries
  const applicableIndustries = CANONICAL_INDUSTRY_CROSS_LINKS.slice(0, 3);

  // Schema.org Service structured data
  const serviceSchema = {
    '@context': 'https://schema.org',
    '@type': 'Service',
    name: service.title,
    description: service.tagline || service.description,
    url: `https://voakhealthcare.com.au/services/${service.slug}`,
    serviceType: 'Healthcare Operational & Clinical Workforce Services',
    provider: {
      '@type': 'MedicalOrganization',
      name: 'Voak Health Care',
      url: 'https://voakhealthcare.com.au',
      areaServed: 'Australia',
    },
  };

  return (
    <div className="flex min-h-screen flex-col bg-voak-canvas text-voak-navy">
      <SeoHead
        title={`${service.title} | Healthcare Operations | Voak Health Care`}
        description={service.tagline || service.description.slice(0, 160)}
        canonicalUrl={`https://voakhealthcare.com.au/services/${service.slug}`}
        structuredData={serviceSchema}
      />

      <PublicHeader />

      <main id="main-content" className="flex-1">
        {/* Service Hero Banner */}
        <section className="border-b border-voak-border/60 bg-gradient-to-b from-white via-voak-canvas to-voak-purple-light/20 py-12 sm:py-16">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <Breadcrumb
              items={[
                { label: 'Services', href: '/services' },
                { label: service.title },
              ]}
              homeHref="/"
              homeLabel="Home"
              className="mb-6"
            />

            <div className="max-w-4xl">
              <div className="inline-flex items-center gap-2 rounded-full border border-voak-purple/20 bg-voak-purple-light/60 px-3.5 py-1 text-xs font-bold tracking-wide text-voak-purple uppercase mb-4">
                <Sparkles className="h-3.5 w-3.5" aria-hidden="true" />
                Specialized Service Pillar
              </div>

              <h1 className="text-3xl font-extrabold tracking-tight text-voak-navy sm:text-4xl lg:text-5xl font-serif">
                {service.title}
              </h1>

              <p className="mt-4 text-lg sm:text-xl text-voak-muted leading-relaxed font-medium">
                {service.tagline}
              </p>

              <div className="mt-8 flex flex-wrap items-center gap-3">
                <Link to={`/request-proposal?service_id=${service.id}&service=${service.slug}`}>
                  <Button variant="primary" size="lg" rightIcon={<ArrowRight className="h-4 w-4" />}>
                    Request Scope Proposal
                  </Button>
                </Link>
                <Link to={`/contact?topic=service&slug=${service.slug}`}>
                  <Button variant="outline" size="lg" leftIcon={<PhoneCall className="h-4 w-4" />}>
                    Schedule Discovery Call
                  </Button>
                </Link>
              </div>
            </div>
          </div>
        </section>

        {/* Content Breakdown with 2/3 and 1/3 Sticky Sidebar */}
        <section className="py-12 sm:py-16">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-12 items-start">
              {/* Main Content Column (2/3 width) */}
              <div className="lg:col-span-2 space-y-12">
                {/* 1. Overview Narrative */}
                <div className="rounded-3xl border border-voak-border bg-white p-8 sm:p-10 shadow-sm">
                  <h2 className="text-2xl font-bold tracking-tight text-voak-navy mb-4 font-serif">
                    Operational Scope & Overview
                  </h2>
                  <div className="prose prose-sm sm:prose text-voak-navy/85 leading-relaxed">
                    {service.description.startsWith('<') ? (
                      <div dangerouslySetInnerHTML={{ __html: service.description }} />
                    ) : (
                      <p>{service.description}</p>
                    )}
                  </div>
                </div>

                {/* 2. Core Deliverables Checklist */}
                {service.deliverables && service.deliverables.length > 0 && (
                  <div className="rounded-3xl border border-voak-border bg-white p-8 sm:p-10 shadow-sm">
                    <div className="flex items-center gap-3 mb-6">
                      <div className="h-10 w-10 rounded-xl bg-voak-mint-light flex items-center justify-center text-voak-navy">
                        <CheckCircle2 className="h-5 w-5 text-voak-mint" aria-hidden="true" />
                      </div>
                      <h2 className="text-2xl font-bold tracking-tight text-voak-navy font-serif">
                        Core Service Deliverables
                      </h2>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      {service.deliverables.map((deliv, idx) => (
                        <div
                          key={idx}
                          className="flex items-start gap-3 rounded-2xl border border-voak-border/60 bg-voak-canvas/50 p-4 transition-all hover:border-voak-purple/30 hover:bg-white"
                        >
                          <div className="h-6 w-6 rounded-full bg-voak-mint/20 flex items-center justify-center shrink-0 mt-0.5">
                            <CheckCircle2 className="h-3.5 w-3.5 text-voak-mint" aria-hidden="true" />
                          </div>
                          <span className="text-sm font-medium text-voak-navy leading-snug">
                            {deliv}
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* 3. Operational Integration Methodology */}
                <div className="rounded-3xl border border-voak-border bg-white p-8 sm:p-10 shadow-sm">
                  <div className="flex items-center gap-3 mb-6">
                    <div className="h-10 w-10 rounded-xl bg-voak-purple-light flex items-center justify-center text-voak-purple">
                      <Layers className="h-5 w-5" aria-hidden="true" />
                    </div>
                    <h2 className="text-2xl font-bold tracking-tight text-voak-navy font-serif">
                      Operational Integration Framework
                    </h2>
                  </div>

                  <div className="space-y-6">
                    <div className="flex items-start gap-4">
                      <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-voak-purple text-xs font-bold text-white shrink-0">
                        01
                      </div>
                      <div>
                        <h3 className="text-base font-bold text-voak-navy">
                          Clinical & Operational Diagnostic
                        </h3>
                        <p className="mt-1 text-sm text-voak-muted leading-relaxed">
                          We audit facility workflow volume, roster gap telemetry, and specific compliance mandates to establish baseline SLAs and scope parameters.
                        </p>
                      </div>
                    </div>

                    <div className="flex items-start gap-4">
                      <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-voak-navy text-xs font-bold text-white shrink-0">
                        02
                      </div>
                      <div>
                        <h3 className="text-base font-bold text-voak-navy">
                          Credentialed Workforce Deployment
                        </h3>
                        <p className="mt-1 text-sm text-voak-muted leading-relaxed">
                          Primary-source AHPRA-verified clinicians and support teams are matched to facility protocols, orientation criteria, and shift schedules.
                        </p>
                      </div>
                    </div>

                    <div className="flex items-start gap-4">
                      <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-voak-mint text-xs font-bold text-voak-navy shrink-0">
                        03
                      </div>
                      <div>
                        <h3 className="text-base font-bold text-voak-navy">
                          Continuous Clinical Governance & Audits
                        </h3>
                        <p className="mt-1 text-sm text-voak-muted leading-relaxed">
                          Real-time SLA fulfillment reporting, supervisor reviews, and monthly compliance scorecard validation overseen by our Clinical Advisory Board.
                        </p>
                      </div>
                    </div>
                  </div>
                </div>

                {/* 4. SLA & Performance Benchmarks */}
                {service.sla_metrics && Object.keys(service.sla_metrics).length > 0 && (
                  <div className="rounded-3xl border border-voak-border bg-white p-8 sm:p-10 shadow-sm">
                    <div className="flex items-center gap-3 mb-6">
                      <div className="h-10 w-10 rounded-xl bg-voak-yellow-light flex items-center justify-center text-voak-navy">
                        <Activity className="h-5 w-5 text-voak-navy" aria-hidden="true" />
                      </div>
                      <h2 className="text-2xl font-bold tracking-tight text-voak-navy font-serif">
                        Service Level Agreements & Benchmarks
                      </h2>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                      {Object.entries(service.sla_metrics).map(([key, val], idx) => {
                        const cleanLabel = key
                          .replace(/_/g, ' ')
                          .replace(/\b\w/g, (char) => char.toUpperCase());
                        return (
                          <div
                            key={idx}
                            className="rounded-2xl border border-voak-border/70 bg-voak-canvas/60 p-5 text-center"
                          >
                            <span className="text-2xl sm:text-3xl font-extrabold text-voak-navy font-serif block mb-1">
                              {val}
                            </span>
                            <span className="text-xs font-semibold text-voak-muted uppercase tracking-wider block">
                              {cleanLabel}
                            </span>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>

              {/* Sidebar Column (1/3 width, Sticky on Desktop) */}
              <div className="lg:col-span-1 space-y-8 sticky top-28">
                {/* Contextual Proposal Card */}
                <Card className="rounded-3xl border-voak-purple/30 bg-gradient-to-b from-white to-voak-purple-light/20 p-6 sm:p-8 shadow-md">
                  <Badge variant="purple" size="sm" className="mb-4">
                    Contextual Procurement RFP
                  </Badge>

                  <h3 className="text-xl font-bold text-voak-navy font-serif mb-2">
                    Scope This Service
                  </h3>

                  <p className="text-xs text-voak-muted leading-relaxed mb-6">
                    Initiate an operational feasibility assessment for <strong>{service.title}</strong> with guaranteed rate cards and custom clinical governance.
                  </p>

                  <Link to={`/request-proposal?service_id=${service.id}&service=${service.slug}`} className="w-full">
                    <Button
                      variant="primary"
                      size="md"
                      className="w-full justify-center mb-3"
                      rightIcon={<ArrowRight className="h-4 w-4" />}
                    >
                      Request Scope Proposal
                    </Button>
                  </Link>

                  <Link to="/contact" className="w-full">
                    <Button variant="outline" size="sm" className="w-full justify-center">
                      Contact Clinical Advisory Team
                    </Button>
                  </Link>
                </Card>

                {/* Applicable Healthcare Sectors */}
                <div className="rounded-3xl border border-voak-border bg-white p-6 shadow-sm">
                  <div className="flex items-center gap-2 mb-4">
                    <Building2 className="h-4 w-4 text-voak-purple" aria-hidden="true" />
                    <h3 className="text-sm font-bold uppercase tracking-wider text-voak-navy">
                      Applicable Sectors
                    </h3>
                  </div>

                  <p className="text-xs text-voak-muted mb-4">
                    This service is pre-calibrated to institutional compliance standards across:
                  </p>

                  <div className="flex flex-col gap-2">
                    {applicableIndustries.map((ind) => (
                      <Link
                        key={ind.id}
                        to={`/industries/${ind.slug}`}
                        className="group flex items-center justify-between rounded-xl border border-voak-border/60 bg-voak-canvas/40 px-3.5 py-2.5 text-xs font-medium text-voak-navy hover:border-voak-purple/40 hover:bg-voak-purple-light/30 transition-all"
                      >
                        <span>{ind.title}</span>
                        <ArrowRight className="h-3 w-3 text-voak-muted group-hover:text-voak-purple transition-transform group-hover:translate-x-0.5" />
                      </Link>
                    ))}
                  </div>
                </div>

                {/* Statutory Operational Boundary Disclaimer */}
                <div className="rounded-2xl border border-voak-border/80 bg-voak-canvas/40 p-4 text-[11px] text-voak-muted leading-relaxed">
                  <strong className="text-voak-navy block mb-1">B2B Operational Notice:</strong>
                  Voak Health Care supplies credentialed healthcare operational teams under facility clinical direction. Does not provide direct primary triage or emergency bookings.
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* Sibling / Related Services Carousel */}
        {siblingServices.length > 0 && (
          <section className="border-t border-voak-border/60 bg-white py-16">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
              <div className="flex items-center justify-between mb-8">
                <div>
                  <span className="text-xs font-bold uppercase tracking-widest text-voak-purple">
                    Complementary Solutions
                  </span>
                  <h2 className="text-2xl font-bold text-voak-navy font-serif mt-1">
                    Related Healthcare Services
                  </h2>
                </div>
                <Link to="/services">
                  <Button variant="ghost" size="sm" rightIcon={<ArrowRight className="h-4 w-4" />}>
                    View All Services
                  </Button>
                </Link>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                {siblingServices.map((sibling) => (
                  <Card
                    key={sibling.id}
                    className="flex flex-col justify-between p-6 rounded-2xl border-voak-border/70 hover:border-voak-purple/40 hover:shadow-md transition-all"
                  >
                    <div>
                      <span className="text-[11px] font-bold text-voak-purple uppercase tracking-wider block mb-2">
                        Pillar 0{sibling.display_order || sibling.id}
                      </span>
                      <h3 className="text-lg font-bold text-voak-navy font-serif mb-2">
                        {sibling.title}
                      </h3>
                      <p className="text-xs text-voak-muted leading-relaxed mb-4">
                        {sibling.tagline}
                      </p>
                    </div>

                    <Link to={`/services/${sibling.slug}`} className="mt-4">
                      <Button variant="outline" size="sm" className="w-full justify-between" rightIcon={<ArrowRight className="h-3.5 w-3.5" />}>
                        Explore Service
                      </Button>
                    </Link>
                  </Card>
                ))}
              </div>
            </div>
          </section>
        )}
      </main>

      <PublicFooter />
    </div>
  );
};

export default ServiceDetailPage;
