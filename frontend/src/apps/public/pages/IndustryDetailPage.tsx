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
import { CANONICAL_INDUSTRIES, IndustryItem } from './IndustriesPage';
import { CANONICAL_SERVICES } from './ServicesPage';
import { Link, useRouter } from '../../../shared/router/Router';
import { apiClient } from '../../../shared/api/apiClient';
import {
  ArrowRight,
  Building2,
  ShieldCheck,
  AlertTriangle,
  CheckCircle2,
  FileText,
  PhoneCall,
  Layers,
  Check,
} from 'lucide-react';

export interface MappedService {
  id: number;
  title: string;
  slug: string;
  tagline?: string;
  display_order?: number;
}

export interface IndustryDetailPayload extends IndustryItem {
  services?: MappedService[];
}

export interface IndustryDetailPageProps {
  slug?: string;
}

export const IndustryDetailPage: React.FC<IndustryDetailPageProps> = ({ slug: propSlug }) => {
  const { path } = useRouter();

  // Extract slug from route path: /industries/:slug
  const currentSlug = propSlug || (() => {
    const cleanPath = path.split('?')[0].split('#')[0];
    const match = cleanPath.match(/^\/industries\/([a-zA-Z0-9_-]+)/);
    return match ? match[1] : '';
  })();

  const initialFallback = CANONICAL_INDUSTRIES.find(
    (i) => i.slug === currentSlug && i.is_published !== 0
  ) || null;

  const [industry, setIndustry] = useState<IndustryDetailPayload | null>(initialFallback);
  const [loading, setLoading] = useState(!initialFallback);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    let isMounted = true;

    if (!currentSlug) {
      setNotFound(true);
      setLoading(false);
      return;
    }

    const fallback = CANONICAL_INDUSTRIES.find(
      (i) => i.slug === currentSlug && i.is_published !== 0
    ) || null;

    if (fallback) {
      setIndustry(fallback);
      setLoading(false);
    } else {
      setLoading(true);
    }

    const fetchIndustry = async () => {
      try {
        const res = await apiClient.get<{ industry: IndustryDetailPayload }>(
          `/api/v1/public/industries/${encodeURIComponent(currentSlug)}`
        );
        if (isMounted) {
          if (res?.data?.industry && res.data.industry.is_published !== 0) {
            setIndustry(res.data.industry);
          } else {
            // Check fallback
            const match = CANONICAL_INDUSTRIES.find(
              (i) => i.slug === currentSlug && i.is_published !== 0
            );
            if (match) {
              setIndustry(match);
            } else {
              setNotFound(true);
            }
          }
        }
      } catch {
        if (isMounted) {
          const match = CANONICAL_INDUSTRIES.find(
            (i) => i.slug === currentSlug && i.is_published !== 0
          );
          if (match) {
            setIndustry(match);
          } else {
            setNotFound(true);
          }
        }
      } finally {
        if (isMounted) setLoading(false);
      }
    };

    fetchIndustry();

    return () => {
      isMounted = false;
    };
  }, [currentSlug]);

  if (notFound) {
    return (
      <NotFoundPage
        resourceType="industry"
        title="Healthcare Sector Not Found"
        message={`We could not locate a published healthcare industry vertical matching "${currentSlug}". It may have been renamed or archived.`}
      />
    );
  }

  if (loading || !industry) {
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

  // Recommended mapped services (either from API response or canonical fallback)
  const mappedServices: MappedService[] =
    industry.services && industry.services.length > 0
      ? industry.services
      : CANONICAL_SERVICES.slice(0, 4).map((s) => ({
          id: s.id,
          title: s.title,
          slug: s.slug,
          tagline: s.tagline,
          display_order: s.display_order,
        }));

  // JSON-LD MedicalWebPage structured data
  const industrySchema = {
    '@context': 'https://schema.org',
    '@type': 'MedicalWebPage',
    name: `${industry.title} Operations & Workforce Solutions`,
    description: industry.summary,
    url: `https://voakhealthcare.com.au/industries/${industry.slug}`,
    medicalAudience: {
      '@type': 'MedicalAudience',
      audienceType: 'Healthcare Procurement & Operations Leadership',
    },
    publisher: {
      '@type': 'MedicalOrganization',
      name: 'Voak Health Care',
      url: 'https://voakhealthcare.com.au',
    },
  };

  const regSpecs = industry.regulatory_burden_specs || {};
  const govBody = regSpecs.governing_body || 'Australian Health Standards';
  const standardsList = regSpecs.standards || ['National Healthcare Accreditation Standards'];
  const scopeDesc = regSpecs.scope || 'Institutional clinical facilities and healthcare networks.';

  return (
    <div className="flex min-h-screen flex-col bg-voak-canvas text-voak-navy">
      <SeoHead
        title={`${industry.title} Operations & Workforce Solutions | Voak Health Care`}
        description={industry.summary}
        canonicalUrl={`https://voakhealthcare.com.au/industries/${industry.slug}`}
        structuredData={industrySchema}
      />

      <PublicHeader />

      <main id="main-content" className="flex-1">
        {/* Sector Hero Banner */}
        <section className="border-b border-voak-border/60 bg-gradient-to-b from-white via-voak-canvas to-voak-purple-light/20 py-12 sm:py-16">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <Breadcrumb
              items={[
                { label: 'Industries', href: '/industries' },
                { label: industry.title },
              ]}
              homeHref="/"
              homeLabel="Home"
              className="mb-6"
            />

            <div className="max-w-4xl">
              <div className="inline-flex items-center gap-2 rounded-full border border-voak-purple/20 bg-voak-purple-light/60 px-3.5 py-1 text-xs font-bold tracking-wide text-voak-purple uppercase mb-4">
                <Building2 className="h-3.5 w-3.5" aria-hidden="true" />
                Institutional Sector Vertical
              </div>

              <h1 className="text-3xl font-extrabold tracking-tight text-voak-navy sm:text-4xl lg:text-5xl font-serif">
                {industry.title}
              </h1>

              <p className="mt-4 text-lg sm:text-xl text-voak-muted leading-relaxed font-medium">
                {industry.summary}
              </p>

              <div className="mt-8 flex flex-wrap items-center gap-3">
                <Link to={`/request-proposal?sector=${industry.slug}`}>
                  <Button variant="primary" size="lg" rightIcon={<ArrowRight className="h-4 w-4" />}>
                    Request Sector Consultation
                  </Button>
                </Link>
                <Link to={`/contact?topic=industry&sector=${industry.slug}`}>
                  <Button variant="outline" size="lg" leftIcon={<PhoneCall className="h-4 w-4" />}>
                    Speak with Sector Lead
                  </Button>
                </Link>
              </div>
            </div>
          </div>
        </section>

        {/* Content Breakdown with 2/3 Main and 1/3 Sidebar */}
        <section className="py-12 sm:py-16">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-12 items-start">
              {/* Main Content Column (2/3 width) */}
              <div className="lg:col-span-2 space-y-12">
                {/* 1. Regulatory Landscape & Statutory Standards */}
                <div className="rounded-3xl border border-voak-border bg-white p-8 sm:p-10 shadow-sm">
                  <div className="flex items-center gap-3 mb-6">
                    <div className="h-10 w-10 rounded-xl bg-voak-purple-light flex items-center justify-center text-voak-purple">
                      <ShieldCheck className="h-5 w-5" aria-hidden="true" />
                    </div>
                    <div>
                      <span className="text-[11px] font-bold uppercase tracking-wider text-voak-purple block">
                        Compliance Mandates
                      </span>
                      <h2 className="text-2xl font-bold tracking-tight text-voak-navy font-serif">
                        Regulatory Landscape & Governance
                      </h2>
                    </div>
                  </div>

                  <div className="rounded-2xl border border-voak-border/60 bg-voak-canvas/40 p-4 mb-6">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                      <span className="text-xs font-bold uppercase tracking-wider text-voak-muted">
                        Statutory Governing Authority:
                      </span>
                      <span className="text-sm font-bold text-voak-navy">
                        {govBody}
                      </span>
                    </div>
                  </div>

                  <h3 className="text-sm font-bold uppercase tracking-wider text-voak-navy mb-3">
                    Applicable Statutory & Accreditation Standards:
                  </h3>
                  <ul className="space-y-2.5 mb-6">
                    {standardsList.map((std, sIdx) => (
                      <li key={sIdx} className="flex items-start gap-2.5 text-sm text-voak-navy/85">
                        <Check className="h-4 w-4 text-voak-mint shrink-0 mt-0.5" aria-hidden="true" />
                        <span>{std}</span>
                      </li>
                    ))}
                  </ul>

                  <div className="border-t border-voak-border/60 pt-4 text-xs text-voak-muted">
                    <strong className="text-voak-navy">Clinical Scope:</strong> {scopeDesc}
                  </div>
                </div>

                {/* 2. Sector Pain Points vs. Voak Operational Interventions */}
                {industry.pain_points && industry.pain_points.length > 0 && (
                  <div className="rounded-3xl border border-voak-border bg-white p-8 sm:p-10 shadow-sm">
                    <div className="flex items-center gap-3 mb-6">
                      <div className="h-10 w-10 rounded-xl bg-voak-yellow-light flex items-center justify-center text-voak-navy">
                        <AlertTriangle className="h-5 w-5 text-voak-navy" aria-hidden="true" />
                      </div>
                      <div>
                        <span className="text-[11px] font-bold uppercase tracking-wider text-voak-yellow-dark block">
                          Operational Bottlenecks
                        </span>
                        <h2 className="text-2xl font-bold tracking-tight text-voak-navy font-serif">
                          Sector Challenges & Voak Solutions
                        </h2>
                      </div>
                    </div>

                    <div className="space-y-4">
                      {industry.pain_points.map((pain, pIdx) => (
                        <div
                          key={pIdx}
                          className="rounded-2xl border border-voak-border/70 bg-voak-canvas/40 p-5 transition-all hover:bg-white hover:border-voak-purple/30"
                        >
                          <div className="flex items-start gap-3 mb-3">
                            <span className="flex h-5 w-5 items-center justify-center rounded-full bg-voak-yellow/30 text-[10px] font-bold text-voak-navy shrink-0 mt-0.5">
                              !
                            </span>
                            <h3 className="text-sm font-bold text-voak-navy">
                              {pain}
                            </h3>
                          </div>
                          <div className="ml-8 text-xs text-voak-muted leading-relaxed flex items-start gap-2">
                            <CheckCircle2 className="h-3.5 w-3.5 text-voak-mint shrink-0 mt-0.5" aria-hidden="true" />
                            <span>
                              <strong>Voak Turnkey Intervention:</strong> Pre-credentialed clinical roster deployment with real-time shift coverage and guaranteed SLA fulfillment rates.
                            </span>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* 3. Operational Transformation Workflow */}
                <div className="rounded-3xl border border-voak-border bg-white p-8 sm:p-10 shadow-sm">
                  <div className="flex items-center gap-3 mb-6">
                    <div className="h-10 w-10 rounded-xl bg-voak-mint-light flex items-center justify-center text-voak-navy">
                      <Layers className="h-5 w-5 text-voak-mint" aria-hidden="true" />
                    </div>
                    <div>
                      <span className="text-[11px] font-bold uppercase tracking-wider text-voak-mint-dark block">
                        Facility Integration
                      </span>
                      <h2 className="text-2xl font-bold tracking-tight text-voak-navy font-serif">
                        How We Integrate with {industry.title}
                      </h2>
                    </div>
                  </div>

                  <div className="space-y-6">
                    <div className="flex items-start gap-4">
                      <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-voak-purple text-xs font-bold text-white shrink-0">
                        1
                      </div>
                      <div>
                        <h3 className="text-base font-bold text-voak-navy">
                          Sector Diagnostic & Regulatory Audit
                        </h3>
                        <p className="mt-1 text-sm text-voak-muted leading-relaxed">
                          We assess facility vacancy rates, direct care minute targets, and audit risks against governing standards like {govBody}.
                        </p>
                      </div>
                    </div>

                    <div className="flex items-start gap-4">
                      <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-voak-navy text-xs font-bold text-white shrink-0">
                        2
                      </div>
                      <div>
                        <h3 className="text-base font-bold text-voak-navy">
                          Protocol Calibration & Staff Orientation
                        </h3>
                        <p className="mt-1 text-sm text-voak-muted leading-relaxed">
                          Assigned clinicians and operational teams complete facility-specific EHR training, badge issuance, and shift handover alignment.
                        </p>
                      </div>
                    </div>

                    <div className="flex items-start gap-4">
                      <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-voak-mint text-xs font-bold text-voak-navy shrink-0">
                        3
                      </div>
                      <div>
                        <h3 className="text-base font-bold text-voak-navy">
                          Live Roster Relief & Telemetry Reporting
                        </h3>
                        <p className="mt-1 text-sm text-voak-muted leading-relaxed">
                          Continuous 24/7 coordinator coverage, digital attendance verification, and monthly SLA compliance dashboards.
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Sidebar Column (1/3 width, Sticky on Desktop) */}
              <div className="lg:col-span-1 space-y-8 sticky top-28">
                {/* Contextual Proposal Card */}
                <Card className="rounded-3xl border-voak-purple/30 bg-gradient-to-b from-white to-voak-purple-light/20 p-6 sm:p-8 shadow-md">
                  <Badge variant="purple" size="sm" className="mb-4">
                    Sector Feasibility Scoping
                  </Badge>

                  <h3 className="text-xl font-bold text-voak-navy font-serif mb-2">
                    Scope Solutions for {industry.title}
                  </h3>

                  <p className="text-xs text-voak-muted leading-relaxed mb-6">
                    Receive a tailored workforce and compliance feasibility proposal customized to your facility scale and regulatory targets.
                  </p>

                  <Link to={`/request-proposal?sector=${industry.slug}`} className="w-full">
                    <Button
                      variant="primary"
                      size="md"
                      className="w-full justify-center mb-3"
                      rightIcon={<ArrowRight className="h-4 w-4" />}
                    >
                      Request Sector Proposal
                    </Button>
                  </Link>

                  <Link to={`/contact?topic=industry&sector=${industry.slug}`} className="w-full">
                    <Button variant="outline" size="sm" className="w-full justify-center">
                      Consult with Sector Director
                    </Button>
                  </Link>
                </Card>

                {/* Recommended Service Packages */}
                <div className="rounded-3xl border border-voak-border bg-white p-6 shadow-sm">
                  <div className="flex items-center gap-2 mb-4">
                    <FileText className="h-4 w-4 text-voak-purple" aria-hidden="true" />
                    <h3 className="text-sm font-bold uppercase tracking-wider text-voak-navy">
                      Recommended Services
                    </h3>
                  </div>

                  <p className="text-xs text-voak-muted mb-4">
                    Clinical and operational service pillars pre-configured for this sector:
                  </p>

                  <div className="flex flex-col gap-2.5">
                    {mappedServices.map((svc) => (
                      <Link
                        key={svc.id}
                        to={`/services/${svc.slug}`}
                        className="group flex flex-col rounded-xl border border-voak-border/60 bg-voak-canvas/40 p-3 hover:border-voak-purple/40 hover:bg-voak-purple-light/30 transition-all"
                      >
                        <div className="flex items-center justify-between text-xs font-bold text-voak-navy mb-1">
                          <span>{svc.title}</span>
                          <ArrowRight className="h-3 w-3 text-voak-muted group-hover:text-voak-purple transition-transform group-hover:translate-x-0.5" />
                        </div>
                        {svc.tagline && (
                          <p className="text-[11px] text-voak-muted line-clamp-2 leading-snug">
                            {svc.tagline}
                          </p>
                        )}
                      </Link>
                    ))}
                  </div>
                </div>

                {/* B2B Regulatory Boundary Notice */}
                <div className="rounded-2xl border border-voak-border/80 bg-voak-canvas/40 p-4 text-[11px] text-voak-muted leading-relaxed">
                  <strong className="text-voak-navy block mb-1">Institutional Partnership Notice:</strong>
                  Voak provides B2B healthcare operational support and clinical teams under client institutional clinical governance. Does not provide direct consumer medical care.
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>

      <PublicFooter />
    </div>
  );
};

export default IndustryDetailPage;
