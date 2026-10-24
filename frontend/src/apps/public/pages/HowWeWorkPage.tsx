import React, { useState, useEffect } from 'react';
import {
  CheckCircle2,
  Clock,
  FileCheck,
  ShieldCheck,
  ArrowRight,
  Headphones,
  Check,
} from 'lucide-react';
import { SeoHead } from '../seo/SeoHead';
import { PublicHeader } from '../components/PublicHeader';
import { PublicFooter } from '../components/PublicFooter';
import { CorporatePageHero } from '../components/CorporatePageHero';
import { Card } from '../../../shared/components/Card';
import { Button } from '../../../shared/components/Button';
import { Badge } from '../../../shared/components/Badge';
import { apiClient } from '../../../shared/api/apiClient';

interface RoadmapStage {
  number: number;
  title: string;
  duration: string;
  summary: string;
  objectives: string[];
  deliverables: string[];
  governanceGate: string;
  accentColor: string;
  tint: 'purple' | 'mint' | 'yellow' | 'pink';
}

export const HowWeWorkPage: React.FC = () => {
  const [activeStage, setActiveStage] = useState<number>(1);
  const [, setIsLoading] = useState<boolean>(true);

  const stages: RoadmapStage[] = [
    {
      number: 1,
      title: 'Diagnostic Assessment & Operational Audit',
      duration: 'Weeks 1 – 2',
      summary: 'Comprehensive on-site and remote evaluation of facility workforce gaps, shift turnover, compliance registers, and expenditure bottlenecks.',
      objectives: [
        'Analyze historical shift vacancy rates and emergency surge expenditure patterns.',
        'Review current facility credentialing, orientation protocols, and compliance registers.',
        'Conduct stakeholder interviews with Directors of Nursing, Clinical Leads, and HR Procurement.',
      ],
      deliverables: [
        'Detailed Healthcare Operations Diagnostic Report',
        'Clinical Compliance & Credentialing Gap Register',
        'Tailored Workforce Model & Rate Card Optimization Proposal',
      ],
      governanceGate: 'Executive Diagnostic Review & Joint Scope Approval',
      accentColor: '#665CE9',
      tint: 'purple',
    },
    {
      number: 2,
      title: 'Bespoke Solution Architecture & SLA Design',
      duration: 'Weeks 3 – 4',
      summary: 'Engineering tailored Service Level Agreements (SLAs), escalation pathways, dedicated practitioner cohorts, and digital telemetry integrations.',
      objectives: [
        'Formulate transparent, fixed rate cards without emergency penalty multipliers.',
        'Define mandatory clinical competency checklists and facility-specific orientation modules.',
        'Establish 24/7 communications pathways and escalation protocols with key ward leaders.',
      ],
      deliverables: [
        'Formal Institutional Service Level Agreement (SLA) Charter',
        'Facility-Specific Clinical Credentialing Protocol Agreement',
        'Automated Shift Telemetry & Roster Sync Blueprint',
      ],
      governanceGate: 'Procurement, Clinical Governance & Legal Charter Sign-off',
      accentColor: '#54CB96',
      tint: 'mint',
    },
    {
      number: 3,
      title: 'Phased Integration & Credentialed Deployment',
      duration: 'Weeks 5 – 8',
      summary: 'Commencing staged clinician cohort placement, facility induction, digital electronic timesheet onboarding, and ward familiarization.',
      objectives: [
        'Deploy dedicated primary practitioner cohort with verified AHPRA standing and police checks.',
        'Conduct mandatory clinical orientation with senior charge nurses and unit managers.',
        'Establish direct integration with facility rostering schedules and relief surge buffers.',
      ],
      deliverables: [
        'Cohort Deployment Schedule & Practitioner Roster Ledger',
        'Dedicated 24/7 Facility Account Lead Allocation',
        'Emergency Shift Relief Buffer Activation',
      ],
      governanceGate: 'Clinical Unit Orientation Readiness Sign-Off',
      accentColor: '#FBDC62',
      tint: 'yellow',
    },
    {
      number: 4,
      title: 'Continuous Quality Governance & Performance Telemetry',
      duration: 'Ongoing Operations',
      summary: 'Sustained operational monitoring, real-time shift fulfillment telemetry, quarterly clinical audits, and continuous workforce retention reviews.',
      objectives: [
        'Monitor live fulfillment telemetry maintaining our guaranteed 99.8% SLA rate.',
        'Execute weekly randomized clinical supervisor audits and peer credential evaluations.',
        'Deliver quarterly executive operations and cost-efficiency review meetings with facility leadership.',
      ],
      deliverables: [
        'Monthly Automated Operational Telemetry Dashboards',
        'Quarterly Clinical Governance & Incident Review Reports',
        'Annual Workforce Optimization & Strategic Planning Review',
      ],
      governanceGate: 'Annual Executive Partnership Accreditation Review',
      accentColor: '#EEAFD7',
      tint: 'pink',
    },
  ];

  const [pageTitle, setPageTitle] = useState<string>(
    'A Structured 4-Stage Healthcare Operational Framework'
  );
  const [pageDescription, setPageDescription] = useState<string>(
    'Our engagement methodology eliminates procurement friction and operational guesswork. From initial clinical diagnostic audit to continuous quality telemetry, we structure high-reliability healthcare partnerships.'
  );

  // API Hydration
  useEffect(() => {
    let isMounted = true;
    apiClient
      .get<{ page?: { title?: string; meta_description?: string } }>(
        '/api/v1/public/pages/how-we-work'
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

  const selectedStage = stages.find((s) => s.number === activeStage) || stages[0];

  return (
    <div className="min-h-screen bg-white text-voak-navy flex flex-col font-sans selection:bg-voak-purple-light selection:text-voak-purple">
      {/* SEO Head */}
      <SeoHead
        title="How We Work | 4-Stage Healthcare Operational Framework | Voak Health Care"
        description={pageDescription}
        canonicalUrl="https://voakhealthcare.com.au/how-we-work"
        structuredData={{
          '@context': 'https://schema.org',
          '@type': 'HowTo',
          name: 'Voak Health Care 4-Stage Operational Integration Framework',
          description: 'A structured engagement methodology from diagnostic audit to continuous clinical governance.',
          step: stages.map((s) => ({
            '@type': 'HowToStep',
            position: s.number,
            name: s.title,
            text: s.summary,
          })),
        }}
      />

      {/* Header */}
      <PublicHeader />

      <main id="main-content" className="flex-1">
        {/* Page Hero */}
        <CorporatePageHero
          badgeLabel="Operational Methodology"
          badgeVariant="success"
          title={pageTitle}
          subtitle={pageDescription}
          breadcrumbs={[{ label: 'How We Work' }]}

          highlights={[
            { label: 'Implementation Stages', value: '4' },
            { label: 'Audit Phase', value: '2 Weeks' },
            { label: 'Emergency Response', value: '< 2 hrs' },
            { label: 'Dedicated Account Lead', value: '24/7' },
          ]}
        />

        {/* Section 1: The 4-Stage Interactive Roadmap Stepper */}
        <section className="py-16 sm:py-20 bg-voak-canvas/40 border-b border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="text-center max-w-3xl mx-auto mb-14">
              <span className="text-xs font-bold uppercase tracking-widest text-voak-purple">
                Methodology Roadmap
              </span>
              <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-voak-navy tracking-tight mt-2">
                4-Stage Implementation & Governance Lifecycle
              </h2>
              <p className="text-sm sm:text-base text-voak-muted mt-3">
                Select a stage below to examine specific operational objectives, deliverables, and governance gates.
              </p>
            </div>

            {/* Stepper Navigation (<ol> Ordered List for Accessibility) */}
            <ol
              role="list"
              className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-10"
              aria-label="Operational Framework Stages"
            >
              {stages.map((stage) => {
                const isActive = stage.number === activeStage;
                return (
                  <li key={stage.number} className="relative">
                    <button
                      type="button"
                      onClick={() => setActiveStage(stage.number)}
                      aria-current={isActive ? 'step' : undefined}
                      className={`w-full text-left p-5 rounded-3xl border transition-all duration-200 flex flex-col justify-between h-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple ${
                        isActive
                          ? 'bg-white border-voak-purple shadow-md ring-1 ring-voak-purple/20'
                          : 'bg-white/80 border-voak-border hover:bg-white hover:border-voak-border-strong'
                      }`}
                    >
                      <div className="flex items-center justify-between mb-3 w-full">
                        <span
                          className={`flex h-8 w-8 items-center justify-center rounded-xl text-xs font-black ${
                            isActive
                              ? 'bg-voak-purple text-white'
                              : 'bg-voak-canvas text-voak-navy'
                          }`}
                        >
                          0{stage.number}
                        </span>
                        <Badge variant={isActive ? 'purple' : 'neutral'} className="text-[10px] font-semibold">
                          {stage.duration}
                        </Badge>
                      </div>

                      <div>
                        <h3
                          className={`text-sm font-extrabold leading-snug transition-colors ${
                            isActive ? 'text-voak-purple' : 'text-voak-navy'
                          }`}
                        >
                          {stage.title}
                        </h3>
                        <p className="text-xs text-voak-muted line-clamp-2 mt-1">
                          {stage.summary}
                        </p>
                      </div>

                      <div className="pt-3 mt-3 border-t border-voak-border/60 flex items-center justify-between text-[11px] font-bold">
                        <span className={isActive ? 'text-voak-purple' : 'text-voak-muted'}>
                          {isActive ? 'Active Stage' : 'Click to Inspect'}
                        </span>
                        <ArrowRight
                          className={`h-3.5 w-3.5 transition-transform ${
                            isActive ? 'text-voak-purple translate-x-1' : 'text-voak-muted'
                          }`}
                        />
                      </div>
                    </button>
                  </li>
                );
              })}
            </ol>

            {/* Active Stage Detailed Breakdown Panel */}
            <Card className="p-6 sm:p-10 bg-white border-voak-purple/30 rounded-3xl shadow-md relative overflow-hidden">
              <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-voak-border">
                <div className="flex items-center gap-4">
                  <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-voak-purple/10 text-voak-purple font-black text-2xl shrink-0">
                    0{selectedStage.number}
                  </div>
                  <div>
                    <div className="flex items-center gap-2 mb-1">
                      <Badge variant="purple" className="text-xs font-semibold">
                        Stage 0{selectedStage.number}
                      </Badge>
                      <span className="text-xs font-bold text-voak-muted flex items-center gap-1">
                        <Clock className="h-3.5 w-3.5" />
                        <span>{selectedStage.duration}</span>
                      </span>
                    </div>
                    <h3 className="text-xl sm:text-2xl font-extrabold text-voak-navy tracking-tight">
                      {selectedStage.title}
                    </h3>
                  </div>
                </div>

                <div className="rounded-2xl bg-voak-canvas/80 px-4 py-3 border border-voak-border/80 lg:text-right shrink-0">
                  <span className="text-[10px] font-bold uppercase tracking-wider text-voak-muted block">
                    Governance Gate
                  </span>
                  <span className="text-xs font-bold text-voak-navy flex items-center gap-1.5 lg:justify-end mt-0.5">
                    <ShieldCheck className="h-4 w-4 text-voak-mint shrink-0" />
                    <span>{selectedStage.governanceGate}</span>
                  </span>
                </div>
              </div>

              <p className="text-sm sm:text-base text-voak-muted leading-relaxed my-6 font-normal">
                {selectedStage.summary}
              </p>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-8 pt-4">
                {/* Objectives */}
                <div className="rounded-2xl bg-voak-canvas/40 p-6 border border-voak-border/70">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-voak-navy mb-4 flex items-center gap-2">
                    <CheckCircle2 className="h-4 w-4 text-voak-purple" />
                    <span>Key Stage Objectives</span>
                  </h4>
                  <ul className="space-y-3">
                    {selectedStage.objectives.map((obj) => (
                      <li key={obj} className="flex items-start gap-2.5 text-xs sm:text-sm text-voak-navy/90">
                        <span className="flex h-4 w-4 rounded-full bg-voak-purple/20 text-voak-purple items-center justify-center shrink-0 mt-0.5 text-[10px] font-bold">
                          ✓
                        </span>
                        <span className="leading-relaxed">{obj}</span>
                      </li>
                    ))}
                  </ul>
                </div>

                {/* Deliverables */}
                <div className="rounded-2xl bg-voak-mint/10 p-6 border border-voak-mint/30">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-voak-navy mb-4 flex items-center gap-2">
                    <FileCheck className="h-4 w-4 text-voak-mint" />
                    <span>Formal Documentation & Deliverables</span>
                  </h4>
                  <ul className="space-y-3">
                    {selectedStage.deliverables.map((deliv) => (
                      <li key={deliv} className="flex items-start gap-2.5 text-xs sm:text-sm text-voak-navy/90">
                        <span className="flex h-4 w-4 rounded-full bg-voak-mint text-white items-center justify-center shrink-0 mt-0.5 text-[10px] font-bold">
                          ✓
                        </span>
                        <span className="leading-relaxed font-semibold">{deliv}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            </Card>
          </div>
        </section>

        {/* Section 2: Transition & Account Governance */}
        <section className="py-16 sm:py-20 bg-white border-b border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
              <div className="lg:col-span-6">
                <span className="text-xs font-bold uppercase tracking-widest text-voak-purple">
                  Ongoing Assurance
                </span>
                <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-voak-navy tracking-tight mt-2 mb-6">
                  Dedicated Account Lead & Escalation Architecture
                </h2>
                <p className="text-sm sm:text-base text-voak-muted leading-relaxed mb-6 font-normal">
                  Every health institution partnered with Voak is assigned a designated Senior Account Lead with clinical operations experience, supported by 24/7 communications centers.
                </p>

                <div className="space-y-4">
                  {[
                    'Direct access to our 24/7 Operations Coordination Center',
                    'Less than 1-hour resolution protocol for operational adjustments',
                    'Monthly shift telemetry reports measuring fill rates, feedback, and SLA adherence',
                    'Bi-annual rate card and workforce capacity optimization reviews',
                  ].map((item) => (
                    <div key={item} className="flex items-start gap-3">
                      <div className="flex h-5 w-5 rounded-full bg-voak-mint/20 text-voak-mint items-center justify-center shrink-0 mt-0.5">
                        <Check className="h-3.5 w-3.5" aria-hidden="true" />
                      </div>
                      <span className="text-sm font-semibold text-voak-navy">{item}</span>
                    </div>
                  ))}
                </div>
              </div>

              <div className="lg:col-span-6">
                <div className="rounded-3xl bg-voak-canvas/80 p-8 border border-voak-border/80 shadow-sm">
                  <div className="flex items-center gap-3 mb-6">
                    <div className="h-10 w-10 rounded-xl bg-voak-purple/15 text-voak-purple flex items-center justify-center">
                      <Headphones className="h-5 w-5" />
                    </div>
                    <div>
                      <h3 className="text-base font-bold text-voak-navy">24/7 Operations Support</h3>
                      <p className="text-xs text-voak-muted">Dedicated Client Service Level Framework</p>
                    </div>
                  </div>

                  <div className="space-y-4">
                    <div className="rounded-2xl bg-white p-4 border border-voak-border/60 flex items-center justify-between">
                      <div>
                        <span className="text-xs font-bold text-voak-navy block">Emergency Shift Surge</span>
                        <span className="text-[11px] text-voak-muted">Unpredicted absenteeism response</span>
                      </div>
                      <Badge variant="purple" className="text-xs font-bold">
                        &lt; 2hr Triage
                      </Badge>
                    </div>

                    <div className="rounded-2xl bg-white p-4 border border-voak-border/60 flex items-center justify-between">
                      <div>
                        <span className="text-xs font-bold text-voak-navy block">Planned Roster Fill</span>
                        <span className="text-[11px] text-voak-muted">Monthly block coverage</span>
                      </div>
                      <Badge variant="success" className="text-xs font-bold">
                        99.8% Fill SLA
                      </Badge>
                    </div>

                    <div className="rounded-2xl bg-white p-4 border border-voak-border/60 flex items-center justify-between">
                      <div>
                        <span className="text-xs font-bold text-voak-navy block">Clinical Escalations</span>
                        <span className="text-[11px] text-voak-muted">Direct to Chief Clinical Officer</span>
                      </div>
                      <Badge variant="warning" className="text-xs font-bold">
                        &lt; 1hr Response
                      </Badge>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* Section 3: Action Banner */}
        <section className="py-16 sm:py-20 bg-voak-navy text-white relative overflow-hidden">
          <div
            className="pointer-events-none absolute -bottom-24 -right-24 h-96 w-96 rounded-full bg-voak-purple/20 blur-3xl"
            aria-hidden="true"
          />
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10 text-center max-w-3xl">
            <span className="text-xs font-bold uppercase tracking-widest text-voak-mint">
              Stage 1 Consultation
            </span>
            <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight mt-2 mb-4 text-white">
              Initiate Stage 1 Diagnostic Consultation
            </h2>
            <p className="text-sm sm:text-base text-white/80 leading-relaxed mb-8">
              Take the first step toward high-reliability healthcare workforce operations. Our advisory team will conduct a thorough review of your facility needs without operational interruption.
            </p>

            <div className="flex flex-wrap items-center justify-center gap-4">
              <a href="/quality-compliance">
                <Button variant="secondary" size="md">
                  Review Clinical Compliance
                </Button>
              </a>
              <a href="/about">
                <Button
                  variant="primary"
                  size="md"
                  className="shadow-lg font-bold"
                  rightIcon={<ArrowRight className="h-4 w-4" />}
                >
                  Our Leadership Team
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

export default HowWeWorkPage;
