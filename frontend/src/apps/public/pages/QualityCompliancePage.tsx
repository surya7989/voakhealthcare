import React, { useState, useEffect } from 'react';
import {
  ShieldCheck,
  AlertTriangle,
  FileCheck2,
  Lock,
  CalendarCheck,
  ArrowRight,
  Stethoscope,
  ClipboardCheck,
  Building,
  CheckCircle,
} from 'lucide-react';
import { SeoHead } from '../seo/SeoHead';
import { PublicHeader } from '../components/PublicHeader';
import { PublicFooter } from '../components/PublicFooter';
import { CorporatePageHero } from '../components/CorporatePageHero';
import { Card } from '../../../shared/components/Card';
import { Button } from '../../../shared/components/Button';
import { Badge } from '../../../shared/components/Badge';
import {
  Accordion,
  AccordionItem,
  AccordionTrigger,
  AccordionContent,
} from '../../../shared/components/Accordion';
import { apiClient } from '../../../shared/api/apiClient';

export const QualityCompliancePage: React.FC = () => {
  const [, setIsLoading] = useState<boolean>(true);
  const [pageTitle, setPageTitle] = useState<string>(
    'Clinical Quality & Regulatory Compliance Standards'
  );
  const [pageDescription, setPageDescription] = useState<string>(
    'Voak Health Care operates under an uncompromising clinical governance framework. Our multi-stage credentialing protocols, independent medical advisory oversight, and automated compliance telemetry ensure zero unverified facility placements.'
  );

  // API Hydration
  useEffect(() => {
    let isMounted = true;
    apiClient
      .get<{ page?: { title?: string; meta_description?: string } }>(
        '/api/v1/public/pages/quality-compliance'
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
        title="Clinical Quality & Regulatory Compliance Standards | Voak Health Care"
        description={pageDescription}
        canonicalUrl="https://voakhealthcare.com.au/quality-compliance"
        structuredData={{
          '@context': 'https://schema.org',
          '@type': 'MedicalWebPage',
          name: 'Clinical Quality & Regulatory Compliance Standards',
          description: "Voak Health Care's clinical governance, AHPRA credentialing, and regulatory compliance protocols.",
          url: 'https://voakhealthcare.com.au/quality-compliance',
        }}
      />

      {/* Header */}
      <PublicHeader />

      <main id="main-content" className="flex-1">
        {/* Page Hero */}
        <CorporatePageHero
          badgeLabel="Clinical Governance Charter"
          badgeVariant="purple"
          title={pageTitle}
          subtitle={pageDescription}
          breadcrumbs={[{ label: 'Quality & Compliance' }]}

          highlights={[
            { label: 'AHPRA Standing Scans', value: 'Daily' },
            { label: 'Supervisor Audit Checks', value: '2 Minimum' },
            { label: 'Unverified Placements', value: '0' },
            { label: 'Accreditation Alignment', value: 'NSQHS' },
          ]}
        />

        {/* Mandatory High-Contrast Non-Clinical Care Legal Boundary Banner */}
        <section
          role="alert"
          aria-label="Mandatory Non-Clinical Care Legal Disclaimer"
          className="py-8 bg-amber-500/10 border-y border-amber-500/30"
        >
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4 bg-white p-6 rounded-3xl border-2 border-amber-500/40 shadow-sm">

              <div className="flex h-12 w-12 rounded-2xl bg-amber-500/20 text-amber-700 items-center justify-center shrink-0">
                <AlertTriangle className="h-6 w-6" aria-hidden="true" />
              </div>
              <div className="flex-1">
                <div className="flex items-center gap-2 mb-1">
                  <Badge variant="warning" className="text-[10px] font-extrabold uppercase tracking-wider">
                    Statutory Boundary Notice
                  </Badge>
                  <span className="text-xs font-bold text-voak-navy uppercase tracking-wide">
                    B2B Operational Scope
                  </span>
                </div>
                <p className="text-xs sm:text-sm text-voak-navy leading-relaxed font-semibold">
                  Voak Health Care is a specialized B2B healthcare operational services provider and does not provide direct patient clinical care, emergency medical services, or patient booking channels. All clinical practitioners supplied operate strictly under the operational governance, clinical supervision, and institutional policies of the client healthcare facility.
                </p>
              </div>
            </div>
          </div>
        </section>

        {/* Section 1: 5-Tier Practitioner Credentialing Protocol (WAI-ARIA Accordions) */}
        <section className="py-16 sm:py-20 bg-voak-canvas/40 border-b border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="text-center max-w-3xl mx-auto mb-14">
              <span className="text-xs font-bold uppercase tracking-widest text-voak-purple">
                Rigorous Verification
              </span>
              <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-voak-navy tracking-tight mt-2">
                5-Tier Clinical Credentialing Protocol
              </h2>
              <p className="text-sm sm:text-base text-voak-muted mt-3">
                Every doctor, registered nurse, allied health specialist, and clinical support practitioner must satisfy all 5 verification tiers before their profile is cleared for roster placement.
              </p>
            </div>

            <div className="max-w-4xl mx-auto">
              <Accordion type="single" defaultValue="tier-1" className="bg-white shadow-sm">
                <AccordionItem value="tier-1">
                  <AccordionTrigger icon={<Stethoscope className="h-5 w-5 text-voak-purple" />}>
                    <div className="flex items-center gap-3 text-left">
                      <span className="flex h-7 w-7 rounded-xl bg-voak-purple text-white text-xs font-extrabold items-center justify-center shrink-0">
                        01
                      </span>
                      <div>
                        <span className="text-sm font-extrabold text-voak-navy block">
                          AHPRA Registration & Conditions Monitored Continuously
                        </span>
                        <span className="text-xs text-voak-muted font-normal">
                          Daily automated registry sync, practice notations, and disciplinary review
                        </span>
                      </div>
                    </div>
                  </AccordionTrigger>
                  <AccordionContent>
                    <div className="space-y-3 pt-2 text-xs sm:text-sm text-voak-muted leading-relaxed">
                      <p>
                        Our automated credentialing engine interfaces directly with Australian Health Practitioner Regulation Agency (AHPRA) registers to verify current unconditional registration, endorsement scope, and practice restrictions.
                      </p>
                      <ul className="list-disc pl-5 space-y-1.5 font-medium text-voak-navy/90">
                        <li>Automated daily API status checks identifying any emerging practice conditions or suspensions.</li>
                        <li>Mandatory verification of specialty registration (e.g. Fellow of RACGP, RACP, ANZCA, CICM).</li>
                        <li>Automated alerts trigger instant roster removal if any regulatory status change occurs.</li>
                      </ul>
                    </div>
                  </AccordionContent>
                </AccordionItem>

                <AccordionItem value="tier-2">
                  <AccordionTrigger icon={<FileCheck2 className="h-5 w-5 text-voak-mint" />}>
                    <div className="flex items-center gap-3 text-left">
                      <span className="flex h-7 w-7 rounded-xl bg-voak-mint text-white text-xs font-extrabold items-center justify-center shrink-0">
                        02
                      </span>
                      <div>
                        <span className="text-sm font-extrabold text-voak-navy block">
                          Identity Verification & Legal Right to Work
                        </span>
                        <span className="text-xs text-voak-muted font-normal">
                          100-point Australian biometric identity validation and Department of Home Affairs VEVO checks
                        </span>
                      </div>
                    </div>
                  </AccordionTrigger>
                  <AccordionContent>
                    <div className="space-y-3 pt-2 text-xs sm:text-sm text-voak-muted leading-relaxed">
                      <p>
                        Rigorous identity authentication preventing impersonation and confirming legitimate Australian work rights across citizen, permanent resident, and sponsored visa statuses.
                      </p>
                      <ul className="list-disc pl-5 space-y-1.5 font-medium text-voak-navy/90">
                        <li>100-point identity check against passport, driver license, Medicare, and birth certificates.</li>
                        <li>Real-time Department of Home Affairs Visa Entitlement Verification Online (VEVO) validation.</li>
                        <li>Automated tracking of visa work limitations and expiry milestones.</li>
                      </ul>
                    </div>
                  </AccordionContent>
                </AccordionItem>

                <AccordionItem value="tier-3">
                  <AccordionTrigger icon={<ShieldCheck className="h-5 w-5 text-amber-600" />}>
                    <div className="flex items-center gap-3 text-left">
                      <span className="flex h-7 w-7 rounded-xl bg-amber-500 text-white text-xs font-extrabold items-center justify-center shrink-0">
                        03
                      </span>
                      <div>
                        <span className="text-sm font-extrabold text-voak-navy block">
                          Criminal History & Vulnerable Persons Checks
                        </span>
                        <span className="text-xs text-voak-muted font-normal">
                          National Police Check, Working with Children Check (WWCC), and NDIS Worker Screening
                        </span>
                      </div>
                    </div>
                  </AccordionTrigger>
                  <AccordionContent>
                    <div className="space-y-3 pt-2 text-xs sm:text-sm text-voak-muted leading-relaxed">
                      <p>
                        Mandatory criminal background screening for all personnel deployed to hospitals, aged care homes, psychiatric units, and community health services.
                      </p>
                      <ul className="list-disc pl-5 space-y-1.5 font-medium text-voak-navy/90">
                        <li>Australian Federal Police / ACIC National Police Certificate renewed annually.</li>
                        <li>State-based Working with Children Check (WWCC) verified and continuously linked.</li>
                        <li>NDIS Worker Screening Database clearance verified for disability and residential deployments.</li>
                      </ul>
                    </div>
                  </AccordionContent>
                </AccordionItem>

                <AccordionItem value="tier-4">
                  <AccordionTrigger icon={<ClipboardCheck className="h-5 w-5 text-pink-600" />}>
                    <div className="flex items-center gap-3 text-left">
                      <span className="flex h-7 w-7 rounded-xl bg-pink-500 text-white text-xs font-extrabold items-center justify-center shrink-0">
                        04
                      </span>
                      <div>
                        <span className="text-sm font-extrabold text-voak-navy block">
                          Immunization, Serology & Infection Prevention
                        </span>
                        <span className="text-xs text-voak-muted font-normal">
                          Australian Immunisation Handbook compliance, blood-borne virus serology, and annual vaccines
                        </span>
                      </div>
                    </div>
                  </AccordionTrigger>
                  <AccordionContent>
                    <div className="space-y-3 pt-2 text-xs sm:text-sm text-voak-muted leading-relaxed">
                      <p>
                        Comprehensive occupational health screening aligning with Australian healthcare facility infection control requirements and state health department guidelines.
                      </p>
                      <ul className="list-disc pl-5 space-y-1.5 font-medium text-voak-navy/90">
                        <li>Documented serology immunity for Hepatitis B, Measles, Mumps, Rubella, and Varicella.</li>
                        <li>Pertussis (dTpa) vaccination verified within the past 10 years.</li>
                        <li>Mandatory annual seasonal influenza vaccine tracking and COVID-19 compliance certification.</li>
                      </ul>
                    </div>
                  </AccordionContent>
                </AccordionItem>

                <AccordionItem value="tier-5">
                  <AccordionTrigger icon={<CheckCircle className="h-5 w-5 text-voak-purple" />}>
                    <div className="flex items-center gap-3 text-left">
                      <span className="flex h-7 w-7 rounded-xl bg-voak-purple text-white text-xs font-extrabold items-center justify-center shrink-0">
                        05
                      </span>
                      <div>
                        <span className="text-sm font-extrabold text-voak-navy block">
                          Multi-Tier Clinical Reference & Competency Audits
                        </span>
                        <span className="text-xs text-voak-muted font-normal">
                          Structured verbal peer audits conducted with recent hospital supervising clinicians
                        </span>
                      </div>
                    </div>
                  </AccordionTrigger>
                  <AccordionContent>
                    <div className="space-y-3 pt-2 text-xs sm:text-sm text-voak-muted leading-relaxed">
                      <p>
                        Direct qualitative evaluation of clinical judgment, technical competency, communication, and patient safety records by Voak clinical advisory leads.
                      </p>
                      <ul className="list-disc pl-5 space-y-1.5 font-medium text-voak-navy/90">
                        <li>Minimum of two primary clinical supervisor references from recent Australian healthcare appointments.</li>
                        <li>Standardized inquiry assessing diagnostic rigor, team collaboration, and critical incident response.</li>
                        <li>Documented verification of Basic Life Support (BLS) and Advanced Life Support (ALS) where applicable.</li>
                      </ul>
                    </div>
                  </AccordionContent>
                </AccordionItem>
              </Accordion>
            </div>
          </div>
        </section>

        {/* Section 2: Standards Alignment */}
        <section className="py-16 sm:py-20 bg-white border-b border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="text-center max-w-3xl mx-auto mb-14">
              <span className="text-xs font-bold uppercase tracking-widest text-voak-purple">
                Regulatory Harmonization
              </span>
              <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-voak-navy tracking-tight mt-2">
                Alignment with Australian Healthcare Quality Standards
              </h2>
              <p className="text-sm sm:text-base text-voak-muted mt-3">
                Our operational procedures integrate directly with client accreditation and auditing frameworks.
              </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              <Card className="p-6 bg-voak-canvas/50 border-voak-border rounded-3xl shadow-sm">
                <div className="h-10 w-10 rounded-xl bg-voak-purple/15 text-voak-purple flex items-center justify-center mb-4">
                  <Building className="h-5 w-5" />
                </div>
                <h3 className="text-base font-extrabold text-voak-navy mb-2">NSQHS Standards</h3>
                <p className="text-xs text-voak-muted leading-relaxed">
                  Direct alignment with the National Safety and Quality Health Service Standards: Clinical Governance (Standard 1) and Partnering with Consumers (Standard 2).
                </p>
              </Card>

              <Card className="p-6 bg-voak-canvas/50 border-voak-border rounded-3xl shadow-sm">
                <div className="h-10 w-10 rounded-xl bg-voak-mint/20 text-voak-mint flex items-center justify-center mb-4">
                  <ShieldCheck className="h-5 w-5" />
                </div>
                <h3 className="text-base font-extrabold text-voak-navy mb-2">Aged Care Quality Standards</h3>
                <p className="text-xs text-voak-muted leading-relaxed">
                  Tailored aged care workforce protocols supporting Dignity and Choice (Standard 1), Ongoing Assessment and Planning (Standard 2), and Personal & Clinical Care (Standard 3).
                </p>
              </Card>

              <Card className="p-6 bg-voak-canvas/50 border-voak-border rounded-3xl shadow-sm">
                <div className="h-10 w-10 rounded-xl bg-amber-500/20 text-amber-700 flex items-center justify-center mb-4">
                  <ClipboardCheck className="h-5 w-5" />
                </div>
                <h3 className="text-base font-extrabold text-voak-navy mb-2">NDIS Practice Standards</h3>
                <p className="text-xs text-voak-muted leading-relaxed">
                  Compliance with the NDIS Quality and Safeguards Commission, ensuring positive behavior support, incident management, and human rights alignment.
                </p>
              </Card>

              <Card className="p-6 bg-voak-canvas/50 border-voak-border rounded-3xl shadow-sm">
                <div className="h-10 w-10 rounded-xl bg-pink-500/20 text-pink-600 flex items-center justify-center mb-4">
                  <Lock className="h-5 w-5" />
                </div>
                <h3 className="text-base font-extrabold text-voak-navy mb-2">Australian Privacy Principles</h3>
                <p className="text-xs text-voak-muted leading-relaxed">
                  Strict adherence to the Privacy Act 1988 (Cth) and APPs. Zero individual patient identifiable health records are collected, stored, or processed by Voak.
                </p>
              </Card>
            </div>
          </div>
        </section>

        {/* Section 3: Clinical Incident Escalation & Audit Schedule */}
        <section className="py-16 sm:py-20 bg-voak-canvas/40 border-b border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
              {/* Escalation Pathway */}
              <div>
                <span className="text-xs font-bold uppercase tracking-widest text-voak-purple">
                  Safety Protocol
                </span>
                <h2 className="text-2xl sm:text-3xl font-extrabold text-voak-navy tracking-tight mt-2 mb-6">
                  Clinical Incident Management & Escalation Framework
                </h2>
                <p className="text-sm text-voak-muted leading-relaxed mb-6">
                  In the event of an operational anomaly, clinical dispute, or safety report, Voak implements a deterministic 4-tier escalation protocol with immediate executive transparency.
                </p>

                <div className="space-y-4">
                  {[
                    {
                      level: 'Severity 1 (Critical Clinical Event)',
                      time: 'Immediate (< 1 Hour)',
                      action: 'Immediate practitioner stand-down, notification to Chief Clinical Officer, and collaborative root-cause review with facility clinical leadership.',
                    },
                    {
                      level: 'Severity 2 (Operational / Protocol Dispute)',
                      time: '< 4 Hours',
                      action: 'Account lead investigation, replacement clinician deployed, and formal response logged on compliance ledger.',
                    },
                    {
                      level: 'Severity 3 (Roster Scheduling Variance)',
                      time: '< 12 Hours',
                      action: 'Roster telemetry re-alignment, automated audit check, and stakeholder status confirmation.',
                    },
                    {
                      level: 'Severity 4 (Administrative / Timesheet Inquiry)',
                      time: '< 24 Hours',
                      action: 'Financial and administrative verification via automated electronic timesheet logs.',
                    },
                  ].map((esc) => (
                    <div
                      key={esc.level}
                      className="rounded-2xl bg-white p-5 border border-voak-border/80 shadow-sm"
                    >
                      <div className="flex items-center justify-between mb-1.5">
                        <span className="text-xs font-extrabold text-voak-navy">{esc.level}</span>
                        <Badge variant="purple" className="text-[10px] font-bold">
                          {esc.time}
                        </Badge>
                      </div>
                      <p className="text-xs text-voak-muted leading-relaxed">{esc.action}</p>
                    </div>
                  ))}
                </div>
              </div>

              {/* Continuous Audit Schedule */}
              <div>
                <span className="text-xs font-bold uppercase tracking-widest text-voak-purple">
                  Ongoing Oversight
                </span>
                <h2 className="text-2xl sm:text-3xl font-extrabold text-voak-navy tracking-tight mt-2 mb-6">
                  Continuous Clinical Audit Schedule
                </h2>
                <p className="text-sm text-voak-muted leading-relaxed mb-6">
                  Compliance is a continuous discipline rather than a one-time onboarding checkpoint. Our scheduled reviews preserve institutional integrity year-round.
                </p>

                <div className="space-y-4">
                  {[
                    {
                      interval: 'Daily Automated Registry Sync',
                      focus: 'AHPRA registration standing, conditions, notations, and VEVO visa work hours.',
                    },
                    {
                      interval: 'Weekly Random Peer Reviews',
                      focus: '10% sample audit of deployed clinicians with facility nursing unit managers.',
                    },
                    {
                      interval: 'Monthly Telemetry & SLA Reports',
                      focus: 'Automated fulfillment percentage, response time tracking, and punctuality records.',
                    },
                    {
                      interval: 'Quarterly Clinical Advisory Review',
                      focus: 'Formal evaluation of incident registers, credential expirations, and continuous training.',
                    },
                    {
                      interval: 'Annual Accreditation Readiness Audit',
                      focus: 'Comprehensive audit trail packaging to support client NSQHS and Aged Care inspections.',
                    },
                  ].map((audit) => (
                    <div
                      key={audit.interval}
                      className="rounded-2xl bg-white p-5 border border-voak-border/80 shadow-sm flex items-start gap-4"
                    >
                      <div className="h-8 w-8 rounded-xl bg-voak-purple/15 text-voak-purple flex items-center justify-center shrink-0 mt-0.5">
                        <CalendarCheck className="h-4 w-4" />
                      </div>
                      <div>
                        <span className="text-xs font-extrabold text-voak-navy block">{audit.interval}</span>
                        <p className="text-xs text-voak-muted leading-relaxed mt-0.5">{audit.focus}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* Section 4: Action Banner */}
        <section className="py-16 sm:py-20 bg-voak-navy text-white relative overflow-hidden">
          <div
            className="pointer-events-none absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-voak-purple/20 blur-3xl"
            aria-hidden="true"
          />
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10 text-center max-w-3xl">
            <span className="text-xs font-bold uppercase tracking-widest text-voak-mint">
              Governance Inquiries
            </span>
            <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight mt-2 mb-4 text-white">
              Request a Clinical Compliance Audit Package
            </h2>
            <p className="text-sm sm:text-base text-white/80 leading-relaxed mb-8">
              Healthcare procurement officers and clinical governance directors can request our formal Credentialing Charter, sample audit registers, and insurance certificates.
            </p>

            <div className="flex flex-wrap items-center justify-center gap-4">
              <a href="/about">
                <Button variant="secondary" size="md">
                  About Our Leadership Team
                </Button>
              </a>
              <a href="/how-we-work">
                <Button
                  variant="primary"
                  size="md"
                  className="shadow-lg font-bold"
                  rightIcon={<ArrowRight className="h-4 w-4" />}
                >
                  Our 4-Stage Methodology
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

export default QualityCompliancePage;
