import React, { useState, useEffect } from 'react';
import {
  Check,
  X,
  AlertCircle,
  ShieldCheck,
  Zap,
  TrendingDown,
  Layers,
  ArrowRight,
  ShieldAlert,
  FileText,
  Lock,
} from 'lucide-react';
import { SeoHead } from '../seo/SeoHead';
import { PublicHeader } from '../components/PublicHeader';
import { PublicFooter } from '../components/PublicFooter';
import { CorporatePageHero } from '../components/CorporatePageHero';
import { BentoCard } from '../../../shared/components/BentoCard';
import { Card } from '../../../shared/components/Card';
import { Button } from '../../../shared/components/Button';
import { Badge } from '../../../shared/components/Badge';
import { apiClient } from '../../../shared/api/apiClient';

interface ComparisonDimension {
  dimension: string;
  description: string;
  voakApproach: string;
  voakAdvantage: boolean;
  agencyApproach: string;
  inHouseApproach: string;
}

export const WhyChooseVoakPage: React.FC = () => {
  const [, setIsLoading] = useState<boolean>(true);

  // Comparison matrix data
  const comparisonMatrix: ComparisonDimension[] = [
    {
      dimension: 'Practitioner Vetting & Credentialing',
      description: 'Depth of clinical qualification and compliance verification prior to facility deployment.',
      voakApproach: '5-Tier AHPRA audit, live registry synchronization, multi-supervisor clinical peer audits, police & WWCC checks.',
      voakAdvantage: true,
      agencyApproach: 'Basic CV review and self-reported certification with infrequent status re-verification.',
      inHouseApproach: 'Exhaustive internal HR checks creating extensive administrative bottlenecks and recruitment delays.',
    },
    {
      dimension: 'SLA Response & Fill Speed',
      description: 'Timeframe for emergency roster surge relief and planned shift allocations.',
      voakApproach: '< 2-hour emergency escalation response with 99.8% verified SLA fulfillment rate across networks.',
      voakAdvantage: true,
      agencyApproach: 'Variable uncommitted timelines; high frequency of last-minute shift cancellations.',
      inHouseApproach: 'Subject entirely to existing staff overtime capacity, causing severe roster burnout and fatigue.',
    },
    {
      dimension: 'Clinical Governance Oversight',
      description: 'Active medical direction and patient safety framework governing deployed workforce.',
      voakApproach: 'Independent Clinical Advisory Board chaired by practicing physicians, with <1hr clinical incident triage.',
      voakAdvantage: true,
      agencyApproach: 'Transactional labor broker model with zero clinical oversight or medical director support.',
      inHouseApproach: 'High executive governance overhead requiring facility clinical leaders to manage every dispute.',
    },
    {
      dimension: 'Pricing & Margin Transparency',
      description: 'Clarity of fee structure and predictability of workforce expenditures.',
      voakApproach: 'Fixed, auditable rate cards with transparent fee structures and zero hidden emergency surge premiums.',
      voakAdvantage: true,
      agencyApproach: 'Opaque markups with punitive penalty rates, emergency multipliers, and variable agency surcharges.',
      inHouseApproach: 'High indirect overheads including mandatory superannuation, recruitment advertising, and payroll tax.',
    },
    {
      dimension: 'Technology & Telemetry Integration',
      description: 'Digital tools supporting roster synchronization, credential storage, and shift sign-off.',
      voakApproach: 'Modern digital shift telemetry, automated electronic timesheets, and real-time compliance dashboards.',
      voakAdvantage: true,
      agencyApproach: 'Manual paper timesheets, phone-tag booking, and disconnected administrative coordination.',
      inHouseApproach: 'Legacy rostering software silos requiring repetitive manual data re-entry and verification.',
    },
    {
      dimension: 'Care Continuity & Retention',
      description: 'Preservation of institutional facility knowledge and patient familiarity.',
      voakApproach: 'Dedicated facility cohort pools achieving 92% retention rate, ensuring practitioner familiarity with wards.',
      voakAdvantage: true,
      agencyApproach: 'Transient, one-off placements with zero institutional familiarity or ward orientation continuity.',
      inHouseApproach: 'High staff turnover in acute environments due to chronic staffing shortfalls and overtime demands.',
    },
  ];

  const [pageTitle, setPageTitle] = useState<string>(
    'Objective Institutional Justification for Selecting Voak'
  );
  const [pageDescription, setPageDescription] = useState<string>(
    "Discover how Voak Health Care's high-reliability governance model, transparent SLA pricing, and dedicated practitioner pools deliver superior operational stability compared to standard agency brokers."
  );

  // API Hydration
  useEffect(() => {
    let isMounted = true;
    apiClient
      .get<{ page?: { title?: string; meta_description?: string } }>(
        '/api/v1/public/pages/why-choose-voak'
      )
      .then((res) => {
        if (!isMounted) return;
        if (res?.data?.page?.title) setPageTitle(res.data.page.title);
        if (res?.data?.page?.meta_description) setPageDescription(res.data.page.meta_description);
        setIsLoading(false);
      })
      .catch(() => {
        if (isMounted) setIsLoading(false);
      });
    return () => {
      isMounted = false;
    };
  }, []);

  return (
    <div className="min-h-screen bg-white text-voak-navy flex flex-col font-sans selection:bg-voak-purple-light selection:text-voak-purple">
      {/* SEO Head */}
      <SeoHead
        title="Why Choose Voak | Enterprise Healthcare Operational Excellence"
        description={pageDescription}
        canonicalUrl="https://voakhealthcare.com.au/why-choose-voak"
        structuredData={{
          '@context': 'https://schema.org',
          '@type': 'WebPage',
          name: 'Why Choose Voak Health Care',
          description: 'Comparative differentiation matrix and institutional healthcare operational advantage.',
          url: 'https://voakhealthcare.com.au/why-choose-voak',
        }}
      />

      {/* Header */}
      <PublicHeader />

      <main id="main-content" className="flex-1">
        {/* Page Hero */}
        <CorporatePageHero
          badgeLabel="Procurement & Operational Comparison"
          badgeVariant="info"
          title={pageTitle}
          subtitle={pageDescription}
          breadcrumbs={[{ label: 'Why Choose Voak' }]}

          highlights={[
            { label: 'SLA Fulfillment Rate', value: '99.8%' },
            { label: 'Practitioner Retention', value: '92%' },
            { label: 'Emergency Response', value: '< 2 hrs' },
            { label: 'Unverified Placements', value: '0' },
          ]}
        />

        {/* Section 1: The Voak Advantage Bento (4 Pillars) */}
        <section className="py-16 sm:py-20 bg-voak-canvas/40 border-b border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="text-center max-w-3xl mx-auto mb-14">
              <span className="text-xs font-bold uppercase tracking-widest text-voak-purple">
                Strategic Differentiation
              </span>
              <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-voak-navy tracking-tight mt-2">
                The Four Pillars of the Voak Advantage
              </h2>
              <p className="text-sm sm:text-base text-voak-muted mt-3">
                Built specifically to solve the structural vulnerabilities of traditional healthcare recruitment and temporary staffing.
              </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
              <BentoCard
                colSpan={12}
                tint="sky"
                icon={<ShieldCheck className="h-6 w-6 text-voak-purple" />}
                title="1. Clinical Governance Rigor"
                subtitle="Independent medical advisory board oversight ensures 100% verified AHPRA standing, active supervisor references, and zero unvetted shifts."
              />
              <BentoCard
                colSpan={12}
                tint="mint"
                icon={<Zap className="h-6 w-6 text-voak-mint" />}
                title="2. Operational Speed & Certainty"
                subtitle="Real-time shift telemetry and guaranteed 2-hour emergency escalation triage, achieving a consistent 99.8% SLA fulfillment rate."
              />
              <BentoCard
                colSpan={12}
                tint="yellow"
                icon={<TrendingDown className="h-6 w-6 text-amber-600" />}
                title="3. Fixed SLA Pricing Transparency"
                subtitle="Auditable rate cards with transparent margins. No emergency multiplier penalties, no hidden fees, and zero budget volatility."
              />
              <BentoCard
                colSpan={12}
                tint="pink"
                icon={<Layers className="h-6 w-6 text-pink-600" />}
                title="4. Cohort Continuity & Retention"
                subtitle="Dedicated practitioner pools aligned to specific hospital wards, delivering care continuity and preserving institutional knowledge."
              />
            </div>
          </div>
        </section>

        {/* Section 2: Comparative Procurement Matrix Table */}
        <section className="py-16 sm:py-20 bg-white border-b border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="text-center max-w-3xl mx-auto mb-14">
              <Badge variant="purple" className="mb-2">
                Procurement Evaluation Matrix
              </Badge>
              <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-voak-navy tracking-tight">
                Voak Managed Model vs. Conventional Alternatives
              </h2>
              <p className="text-sm sm:text-base text-voak-muted mt-3">
                A rigorous comparison of operational capabilities across clinical vetting, response speed, governance oversight, and expenditure predictability.
              </p>
            </div>

            {/* Desktop Table View (>= 768px) */}
            <div className="hidden md:block overflow-x-auto rounded-3xl border border-voak-border shadow-sm">
              <table className="w-full text-left border-collapse">
                <caption className="sr-only">
                  Comparative analysis of Voak Health Care vs Traditional Staffing Agencies vs In-House Coordination
                </caption>
                <thead>
                  <tr className="border-b border-voak-border bg-voak-canvas/80 text-xs font-extrabold uppercase tracking-wider text-voak-navy">
                    <th scope="col" className="py-5 px-6 w-1/4">
                      Operational Dimension
                    </th>
                    <th scope="col" className="py-5 px-6 w-1/3 bg-voak-purple-light/50 text-voak-purple border-x border-voak-border/80">
                      <div className="flex items-center gap-1.5">
                        <Check className="h-4 w-4" aria-hidden="true" />
                        <span>Voak Managed Services</span>
                      </div>
                    </th>
                    <th scope="col" className="py-5 px-6 w-1/4">
                      Traditional Agency
                    </th>
                    <th scope="col" className="py-5 px-6 w-1/4">
                      In-House Coordination
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-voak-border text-xs sm:text-sm">
                  {comparisonMatrix.map((row, idx) => (
                    <tr
                      key={row.dimension}
                      className={idx % 2 === 0 ? 'bg-white' : 'bg-voak-canvas/20'}
                    >
                      <th scope="row" className="py-5 px-6 font-bold text-voak-navy align-top">
                        <div className="text-sm font-extrabold text-voak-navy mb-1">{row.dimension}</div>
                        <div className="text-xs font-normal text-voak-muted leading-relaxed">{row.description}</div>
                      </th>

                      {/* Voak Column (Highlighted) */}
                      <td className="py-5 px-6 bg-voak-purple-light/20 text-voak-navy font-semibold align-top border-x border-voak-border/60">
                        <div className="flex items-start gap-2">
                          <div className="flex h-5 w-5 rounded-full bg-voak-purple text-white items-center justify-center shrink-0 mt-0.5 shadow-sm">
                            <Check className="h-3 w-3 stroke-[3]" aria-hidden="true" />
                          </div>
                          <span className="leading-relaxed">{row.voakApproach}</span>
                        </div>
                      </td>

                      {/* Traditional Agency */}
                      <td className="py-5 px-6 text-voak-muted align-top">
                        <div className="flex items-start gap-2">
                          <div className="flex h-5 w-5 rounded-full bg-red-100 text-red-600 items-center justify-center shrink-0 mt-0.5">
                            <X className="h-3 w-3 stroke-[2.5]" aria-hidden="true" />
                          </div>
                          <span className="leading-relaxed text-xs">{row.agencyApproach}</span>
                        </div>
                      </td>

                      {/* In-House Overhead */}
                      <td className="py-5 px-6 text-voak-muted align-top">
                        <div className="flex items-start gap-2">
                          <div className="flex h-5 w-5 rounded-full bg-amber-100 text-amber-600 items-center justify-center shrink-0 mt-0.5">
                            <AlertCircle className="h-3 w-3" aria-hidden="true" />
                          </div>
                          <span className="leading-relaxed text-xs">{row.inHouseApproach}</span>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Mobile Stacked Card View (< 768px) to guarantee zero clipping */}
            <div className="md:hidden space-y-6">
              {comparisonMatrix.map((row) => (
                <div
                  key={row.dimension}
                  className="rounded-3xl border border-voak-border bg-white p-5 shadow-sm space-y-4"
                >
                  <div className="border-b border-voak-border pb-3">
                    <h3 className="text-base font-extrabold text-voak-navy">{row.dimension}</h3>
                    <p className="text-xs text-voak-muted mt-1">{row.description}</p>
                  </div>

                  {/* Voak Advantage Card */}
                  <div className="rounded-2xl bg-voak-purple-light/40 p-4 border border-voak-purple/20">
                    <div className="flex items-center gap-2 mb-2 text-voak-purple font-bold text-xs uppercase tracking-wide">
                      <Check className="h-4 w-4" />
                      <span>Voak Approach</span>
                    </div>
                    <p className="text-xs font-semibold text-voak-navy leading-relaxed">
                      {row.voakApproach}
                    </p>
                  </div>

                  {/* Traditional Agency */}
                  <div className="rounded-2xl bg-red-50/50 p-4 border border-red-100">
                    <div className="flex items-center gap-2 mb-2 text-red-600 font-bold text-xs uppercase tracking-wide">
                      <X className="h-4 w-4" />
                      <span>Traditional Agency</span>
                    </div>
                    <p className="text-xs text-voak-muted leading-relaxed">
                      {row.agencyApproach}
                    </p>
                  </div>

                  {/* In-House Overhead */}
                  <div className="rounded-2xl bg-amber-50/50 p-4 border border-amber-100">
                    <div className="flex items-center gap-2 mb-2 text-amber-700 font-bold text-xs uppercase tracking-wide">
                      <AlertCircle className="h-4 w-4" />
                      <span>In-House Coordination</span>
                    </div>
                    <p className="text-xs text-voak-muted leading-relaxed">
                      {row.inHouseApproach}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Section 3: Risk Mitigation & Continuity Architecture */}
        <section className="py-16 sm:py-20 bg-voak-canvas/40 border-b border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="text-center max-w-3xl mx-auto mb-14">
              <span className="text-xs font-bold uppercase tracking-widest text-voak-purple">
                Institutional Safety Net
              </span>
              <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-voak-navy tracking-tight mt-2">
                Operational Continuity & Risk Mitigation
              </h2>
              <p className="text-sm sm:text-base text-voak-muted mt-3">
                How our multi-layered operational contingency safeguards patient care and facility accreditation.
              </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <Card className="p-7 bg-white border-voak-border/80 rounded-3xl shadow-sm">
                <div className="h-12 w-12 rounded-2xl bg-voak-purple/10 text-voak-purple flex items-center justify-center mb-5">
                  <ShieldAlert className="h-6 w-6" />
                </div>
                <h3 className="text-base font-extrabold text-voak-navy mb-2">Automated Incident Triage</h3>
                <p className="text-xs text-voak-muted leading-relaxed">
                  Direct escalation protocols route any clinical or operational dispute immediately to our Chief Clinical Officer and dedicated facility account lead within 60 minutes.
                </p>
              </Card>

              <Card className="p-7 bg-white border-voak-border/80 rounded-3xl shadow-sm">
                <div className="h-12 w-12 rounded-2xl bg-voak-mint/15 text-voak-mint flex items-center justify-center mb-5">
                  <Lock className="h-6 w-6" />
                </div>
                <h3 className="text-base font-extrabold text-voak-navy mb-2">Roster Surge Relief Buffer</h3>
                <p className="text-xs text-voak-muted leading-relaxed">
                  Pre-credentialed emergency standby clinician cohorts are maintained in every metropolitan area to absorb unpredicted ward spikes and seasonal absenteeism.
                </p>
              </Card>

              <Card className="p-7 bg-white border-voak-border/80 rounded-3xl shadow-sm">
                <div className="h-12 w-12 rounded-2xl bg-voak-pink/20 text-pink-600 flex items-center justify-center mb-5">
                  <FileText className="h-6 w-6" />
                </div>
                <h3 className="text-base font-extrabold text-voak-navy mb-2">Continuous Compliance Audits</h3>
                <p className="text-xs text-voak-muted leading-relaxed">
                  Automated background scans continuously verify AHPRA registration, conditions, and police check renewals, preventing expired credentials from reaching the floor.
                </p>
              </Card>
            </div>
          </div>
        </section>

        {/* Section 4: Action Banner */}
        <section className="py-16 sm:py-20 bg-voak-navy text-white relative overflow-hidden">
          <div
            className="pointer-events-none absolute -top-24 -left-24 h-96 w-96 rounded-full bg-voak-purple/20 blur-3xl"
            aria-hidden="true"
          />
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10 text-center max-w-3xl">
            <span className="text-xs font-bold uppercase tracking-widest text-voak-yellow">
              Executive Consultation
            </span>
            <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight mt-2 mb-4 text-white">
              Request an Operational Feasibility Review
            </h2>
            <p className="text-sm sm:text-base text-white/80 leading-relaxed mb-8">
              Let our healthcare procurement advisors analyze your current agency expenditures, vacancy rates, and roster stability to demonstrate projected operational improvements.
            </p>

            <div className="flex flex-wrap items-center justify-center gap-4">
              <a href="/how-we-work">
                <Button variant="secondary" size="md">
                  Review Our 4-Stage Methodology
                </Button>
              </a>
              <a href="/quality-compliance">
                <Button
                  variant="primary"
                  size="md"
                  className="shadow-lg font-bold"
                  rightIcon={<ArrowRight className="h-4 w-4" />}
                >
                  Clinical Compliance Framework
                </Button>
              </a>
            </div>
          </div>
        </section>
      </main>

      {/* Footer */}
      <PublicFooter />
    </div>
  );
};

export default WhyChooseVoakPage;
