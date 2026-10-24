import React, { useState, useEffect } from 'react';
import {
  ShieldCheck,
  Stethoscope,
  Building2,
  HeartHandshake,
  Award,
  CheckCircle2,
  ArrowRight,
  MapPin,
  Calendar,
  AlertTriangle,
  Users,
} from 'lucide-react';
import { SeoHead } from '../seo/SeoHead';
import { PublicHeader } from '../components/PublicHeader';
import { PublicFooter } from '../components/PublicFooter';
import { CorporatePageHero } from '../components/CorporatePageHero';
import { LeadershipModal, LeaderProfile } from '../components/LeadershipModal';
import { BentoCard } from '../../../shared/components/BentoCard';
import { Card } from '../../../shared/components/Card';
import { Button } from '../../../shared/components/Button';
import { Badge } from '../../../shared/components/Badge';
import { apiClient } from '../../../shared/api/apiClient';

export const AboutPage: React.FC = () => {
  const [selectedLeader, setSelectedLeader] = useState<LeaderProfile | null>(null);
  const [activeLeaderTab, setActiveLeaderTab] = useState<'all' | 'Executive' | 'Clinical Advisory'>('all');
  const [, setIsLoading] = useState<boolean>(true);

  // Canonical Leadership Profiles (Approved project baseline)
  const canonicalLeaders: LeaderProfile[] = [
    {
      id: 'leader-1',
      name: 'Dr. Alistair Vance',
      role: 'Chief Clinical Officer & Governance Chair',
      credentials: 'MBBS, FRACGP, FACHSM',
      category: 'Executive',
      bio: `Dr. Vance oversees Voak Health Care's clinical credentialing framework, regulatory compliance standards, and quality advisory board. With over 22 years of hospital medicine and clinical governance leadership across public hospital networks in New South Wales and Victoria, he ensures every deployed specialist and operational framework aligns directly with NSQHS and AHPRA criteria.\n\nHe previously served as Director of Medical Services for tertiary regional hospital networks, leading clinical safety audits, medical workforce reforms, and accreditation readiness programs.`,
      governanceFocus: 'Oversees 5-tier practitioner credentialing, clinical escalation pathways, and hospital network advisory alignment.',
      imageSrc: '/images/hero-doctor.png',
      linkedinUrl: 'https://linkedin.com',
    },
    {
      id: 'leader-2',
      name: 'Eleanor Sterling',
      role: 'Chief Executive Officer & Managing Director',
      credentials: 'BCom, MBA (Health Mgmt), GAICD',
      category: 'Executive',
      bio: `Eleanor leads Voak Health Care's enterprise operational strategy, institutional partnerships, and service delivery across Australia. Bringing 18 years of executive leadership in healthcare facilities management, workforce logistics, and procurement operations, she has structured multi-million dollar health staffing and operational SLA frameworks.\n\nPrior to establishing Voak, Eleanor directed national operations for institutional healthcare logistics groups, modernizing workforce telemetry, roster relief architectures, and hospital BPO delivery.`,
      governanceFocus: 'Enterprise SLA execution, institutional hospital contracts, and executive stakeholder alignment.',
      imageSrc: '/images/hero-doctor.png',
      linkedinUrl: 'https://linkedin.com',
    },
    {
      id: 'leader-3',
      name: 'Dr. Meera Patel',
      role: 'Clinical Director — Aged & Community Care',
      credentials: 'MD, FRACP (Geriatric Med)',
      category: 'Clinical Advisory',
      bio: `Dr. Patel leads clinical advisory and governance protocols tailored to residential aged care, transition care, and complex community health facilities. A practicing geriatrician and health systems consultant, she advises health services on clinical continuity, infection prevention, and multidisciplinary allied health integration.\n\nShe chairs the Voak Clinical Incident Review Committee and oversees compliance with the Aged Care Quality Standards.`,
      governanceFocus: 'Aged Care Quality Standards alignment, clinical audit protocols, and geriatric workforce integration.',
      imageSrc: '/images/hero-doctor.png',
      linkedinUrl: 'https://linkedin.com',
    },
    {
      id: 'leader-4',
      name: 'Marcus Thorne',
      role: 'Head of Workforce Operations & Telemetry',
      credentials: 'BEng (Hons), MHealthInf',
      category: 'Executive',
      bio: `Marcus is responsible for the digital telemetry, roster surge relief systems, and SLA monitoring infrastructure that delivers Voak's 99.8% fulfillment rate. He specializes in health workforce optimization, digital shift integration, and real-time credential verification pipelines.\n\nHis team maintains 24/7 client operations coordination centers serving hospital networks and healthcare providers nationwide.`,
      governanceFocus: 'Real-time credential telemetry, automated shift fill validation, and roster surge response.',
      imageSrc: '/images/hero-doctor.png',
      linkedinUrl: 'https://linkedin.com',
    },
    {
      id: 'leader-5',
      name: 'Sarah Jenkins',
      role: 'Senior Clinical Nursing Advisor',
      credentials: 'RN, MN (Clinical Leadership), MACN',
      category: 'Clinical Advisory',
      bio: `Sarah directs the nursing practice governance and peer review protocols for Voak's credentialed registered nurse workforce. With 16 years of acute care, ICU, and emergency department leadership, she leads clinical skill assessments and onboarding compliance frameworks across acute hospital wards.`,
      governanceFocus: 'Nursing credentialing audits, acute care skill verification, and clinical mentorship protocols.',
      imageSrc: '/images/hero-doctor.png',
      linkedinUrl: 'https://linkedin.com',
    },
  ];

  // Milestone Timeline
  const milestones = [
    {
      year: '2020',
      title: 'Inception & Clinical Governance Foundation',
      description: 'Voak Health Care was established to eliminate operational friction and staffing instability in Australian regional and metropolitan healthcare facilities.',
    },
    {
      year: '2022',
      title: 'Multi-Sector Healthcare Network Expansion',
      description: 'Extended operational capabilities across public and private hospital networks, residential aged care facilities, and community health services.',
    },
    {
      year: '2024',
      title: 'SLA Telemetry & Roster Relief Automation',
      description: 'Launched real-time operational telemetry, guaranteeing 2-hour emergency escalation response and achieving 99.8% SLA fulfillment rates.',
    },
    {
      year: '2026',
      title: 'National Enterprise Healthcare Infrastructure',
      description: 'Serving over 120 hospital networks and healthcare facilities across all Australian states with verified clinical compliance and enterprise BPO solutions.',
    },
  ];

  const [pageTitle, setPageTitle] = useState<string>(
    'Pioneering Operational Reliability in Australian Healthcare'
  );
  const [pageDescription, setPageDescription] = useState<string>(
    'Voak Health Care delivers specialized enterprise operational infrastructure, credentialed clinical workforce solutions, and governance rigor for hospital networks, aged care facilities, and healthcare institutions across Australia.'
  );

  // API Hydration with fallback
  useEffect(() => {
    let isMounted = true;
    apiClient
      .get<{ page?: { title?: string; meta_description?: string; content_json?: unknown } }>(
        '/api/v1/public/pages/about'
      )
      .then((res) => {
        if (!isMounted) return;
        if (res?.data?.page?.title) {
          setPageTitle(res.data.page.title);
        }
        if (res?.data?.page?.meta_description) {
          setPageDescription(res.data.page.meta_description);
        }
        setIsLoading(false);
      })
      .catch(() => {
        if (isMounted) setIsLoading(false);
      });
    return () => {
      isMounted = false;
    };
  }, []);


  const filteredLeaders = canonicalLeaders.filter((leader) => {
    if (activeLeaderTab === 'all') return true;
    return leader.category === activeLeaderTab;
  });

  return (
    <div className="min-h-screen bg-white text-voak-navy flex flex-col font-sans selection:bg-voak-purple-light selection:text-voak-purple">
      {/* SEO Head */}
      <SeoHead
        title="About Voak Health Care | B2B Healthcare Governance & Operational Leadership"
        description="Discover Voak Health Care's commitment to clinical governance, operational reliability, and institutional healthcare partnership across Australia."
        canonicalUrl="https://voakhealthcare.com.au/about"
        structuredData={{
          '@context': 'https://schema.org',
          '@type': 'AboutPage',
          name: 'About Voak Health Care',
          description: "Australia's institutional healthcare operational services partner.",
          mainEntity: {
            '@type': 'MedicalOrganization',
            name: 'Voak Health Care',
            url: 'https://voakhealthcare.com.au',
            description: 'Enterprise B2B healthcare operational and clinical workforce partner.',
            areaServed: 'Australia',
            knowsAbout: [
              'Healthcare Workforce Logistics',
              'Clinical Governance',
              'Hospital Operations',
              'AHPRA Credentialing',
            ],
          },
        }}
      />

      {/* Header */}
      <PublicHeader />

      <main id="main-content" className="flex-1">
        {/* Page Hero */}
        <CorporatePageHero
          badgeLabel="Corporate Governance & Profile"
          badgeVariant="purple"
          title={pageTitle}
          subtitle={pageDescription}
          breadcrumbs={[{ label: 'About Us' }]}
          highlights={[
            { label: 'Hospital Networks Supported', value: '120+' },
            { label: 'Credentialed Specialists', value: '450+' },
            { label: 'Clinical Hours Delivered', value: '1.2M+' },
            { label: 'SLA Fulfillment Rate', value: '99.8%' },
          ]}
        />

        {/* Section 1: Mission, Vision & Core Values Bento */}
        <section className="py-16 sm:py-20 bg-voak-canvas/40 border-b border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="text-center max-w-3xl mx-auto mb-14">
              <span className="text-xs font-bold uppercase tracking-widest text-voak-purple">
                Foundational Principles
              </span>
              <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-voak-navy tracking-tight mt-2">
                Mission, Vision & Core Values
              </h2>
              <p className="text-sm sm:text-base text-voak-muted mt-3">
                Our organizational architecture is anchored in uncompromising clinical rigor, operational transparency, and genuine healthcare partnership.
              </p>
            </div>

            {/* Mission & Vision Row */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
              <Card className="p-8 bg-white border-voak-border/80 shadow-sm rounded-3xl relative overflow-hidden">
                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-voak-purple/10 text-voak-purple mb-5">
                  <ShieldCheck className="h-6 w-6" aria-hidden="true" />
                </div>
                <h3 className="text-xl font-extrabold text-voak-navy mb-3">Our Corporate Mission</h3>
                <p className="text-sm text-voak-muted leading-relaxed">
                  To augment Australian healthcare institutions with resilient workforce architecture and operational stability, ensuring every facility maintains seamless continuity of care through credentialed professionals and verifiable SLA execution.
                </p>
              </Card>

              <Card className="p-8 bg-white border-voak-border/80 shadow-sm rounded-3xl relative overflow-hidden">
                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-voak-mint/15 text-voak-mint mb-5">
                  <Award className="h-6 w-6" aria-hidden="true" />
                </div>
                <h3 className="text-xl font-extrabold text-voak-navy mb-3">Our Institutional Vision</h3>
                <p className="text-sm text-voak-muted leading-relaxed">
                  To establish the national benchmark for integrity, clinical governance, and responsiveness in B2B healthcare operational services, becoming Australia&apos;s most trusted institutional healthcare workforce infrastructure partner.
                </p>
              </Card>
            </div>

            {/* 4 Values Bento Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
              <BentoCard
                colSpan={12}
                tint="mint"
                icon={<Stethoscope className="h-6 w-6 text-voak-mint" />}
                title="Clinical Governance & Rigor"
                subtitle="Independent clinical advisory oversight ensuring every clinician holds verified AHPRA standing, rigorous credentialing, and ongoing competency validation."
              />
              <BentoCard
                colSpan={12}
                tint="sky"
                icon={<Building2 className="h-6 w-6 text-voak-purple" />}
                title="Operational Transparency"
                subtitle="Zero hidden markups, auditable shift telemetry, and transparent SLA metrics shared collaboratively with healthcare procurement leads."
              />
              <BentoCard
                colSpan={12}
                tint="yellow"
                icon={<Users className="h-6 w-6 text-amber-600" />}
                title="Workforce Resilience"
                subtitle="Dedicated continuity pools that mitigate roster burnout, reduce reliance on fragmented emergency agency staff, and preserve institutional knowledge."
              />
              <BentoCard
                colSpan={12}
                tint="pink"
                icon={<HeartHandshake className="h-6 w-6 text-pink-600" />}
                title="Collaborative Partnership"
                subtitle="Aligning directly with client clinical protocols, facility workflows, and accreditation goals rather than operating as an arms-length broker."
              />
            </div>
          </div>
        </section>

        {/* Section 2: Clinical Governance Framework */}
        <section className="py-16 sm:py-20 bg-white border-b border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
              <div className="lg:col-span-6">
                <span className="text-xs font-bold uppercase tracking-widest text-voak-purple">
                  Institutional Oversight
                </span>
                <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-voak-navy tracking-tight mt-2 mb-6">
                  Clinical Governance That Exceeds Accreditation Standards
                </h2>
                <p className="text-sm sm:text-base text-voak-muted leading-relaxed mb-6 font-normal">
                  Unlike conventional agency brokers that treat healthcare staffing as transactional fulfillment, Voak Health Care operates under an active Clinical Governance Framework led by practicing medical specialists.
                </p>

                <div className="space-y-4">
                  {[
                    'AHPRA Registration & Conditions Monitored Continuously',
                    'National Safety and Quality Health Service (NSQHS) Standards Aligned',
                    '24/7 Clinical Incident Triage & Dedicated Escalation Channels',
                    'Multi-Tier Supervisor Reference Audits and Credential Reviews',
                  ].map((item) => (
                    <div key={item} className="flex items-start gap-3">
                      <div className="flex h-5 w-5 rounded-full bg-voak-mint/20 text-voak-mint items-center justify-center shrink-0 mt-0.5">
                        <CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true" />
                      </div>
                      <span className="text-sm font-semibold text-voak-navy">{item}</span>
                    </div>
                  ))}
                </div>

                <div className="mt-8">
                  <a href="/quality-compliance">
                    <Button variant="secondary" size="md" rightIcon={<ArrowRight className="h-4 w-4" />}>
                      Explore Our Compliance Standards
                    </Button>
                  </a>
                </div>
              </div>

              <div className="lg:col-span-6">
                <div className="rounded-3xl bg-voak-canvas/80 p-8 border border-voak-border/80 shadow-sm">
                  <div className="flex items-center gap-3 mb-6">
                    <div className="h-10 w-10 rounded-xl bg-voak-purple/15 text-voak-purple flex items-center justify-center">
                      <ShieldCheck className="h-5 w-5" />
                    </div>
                    <div>
                      <h3 className="text-base font-bold text-voak-navy">Clinical Advisory Board</h3>
                      <p className="text-xs text-voak-muted">Independent Quality & Patient Safety Oversight</p>
                    </div>
                  </div>

                  <p className="text-xs text-voak-muted leading-relaxed mb-6">
                    Our Advisory Board provides independent peer evaluation, policy oversight, and escalation reviews to safeguard facility standards across all acute and aged care deployments.
                  </p>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="rounded-2xl bg-white p-4 border border-voak-border/60">
                      <span className="text-xs font-bold text-voak-navy block">Weekly Peer Audits</span>
                      <span className="text-[11px] text-voak-muted">Randomized compliance reviews</span>
                    </div>
                    <div className="rounded-2xl bg-white p-4 border border-voak-border/60">
                      <span className="text-xs font-bold text-voak-navy block">Daily AHPRA Scans</span>
                      <span className="text-[11px] text-voak-muted">Automated registry synchronization</span>
                    </div>
                    <div className="rounded-2xl bg-white p-4 border border-voak-border/60">
                      <span className="text-xs font-bold text-voak-navy block">&lt; 1-Hour Triage</span>
                      <span className="text-[11px] text-voak-muted">Direct clinical incident response</span>
                    </div>
                    <div className="rounded-2xl bg-white p-4 border border-voak-border/60">
                      <span className="text-xs font-bold text-voak-navy block">100% Verified Vetting</span>
                      <span className="text-[11px] text-voak-muted">Zero unverified placements</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* Section 3: Executive Leadership Team & Clinical Advisory */}
        <section id="leadership" className="py-16 sm:py-20 bg-voak-canvas/40 border-b border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-6">
              <div>
                <span className="text-xs font-bold uppercase tracking-widest text-voak-purple">
                  Executive Governance
                </span>
                <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-voak-navy tracking-tight mt-2">
                  Leadership & Clinical Advisory Board
                </h2>
                <p className="text-sm sm:text-base text-voak-muted mt-2 max-w-2xl">
                  Guided by senior health executives, practicing physicians, and workforce logistics leaders committed to operational excellence.
                </p>
              </div>

              {/* Category Filter Tabs */}
              <div className="inline-flex rounded-xl bg-white p-1 border border-voak-border shadow-sm">
                {(['all', 'Executive', 'Clinical Advisory'] as const).map((tab) => (
                  <button
                    key={tab}
                    type="button"
                    onClick={() => setActiveLeaderTab(tab)}
                    className={`rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-all ${
                      activeLeaderTab === tab
                        ? 'bg-voak-purple text-white shadow-sm'
                        : 'text-voak-muted hover:text-voak-navy'
                    }`}
                  >
                    {tab === 'all' ? 'All Leaders' : tab}
                  </button>
                ))}
              </div>
            </div>

            {/* Leadership Cards Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {filteredLeaders.map((leader) => (
                <Card
                  key={leader.id}
                  className="p-6 bg-white border-voak-border/80 shadow-sm hover:shadow-md hover:border-voak-purple/30 transition-all rounded-3xl flex flex-col justify-between text-left group"
                >
                  <div>
                    {/* Header profile row */}
                    <div className="flex items-start gap-4 mb-5">
                      <div className="h-16 w-16 rounded-2xl bg-voak-canvas border border-voak-border/70 flex items-center justify-center overflow-hidden shrink-0 shadow-inner group-hover:scale-105 transition-transform">
                        {leader.imageSrc ? (
                          <img
                            src={leader.imageSrc}
                            alt={leader.name}
                            className="h-full w-full object-cover"
                            onError={(e) => {
                              (e.currentTarget as HTMLElement).style.display = 'none';
                              e.currentTarget.parentElement?.querySelector('.fallback-icon')?.classList.remove('hidden');
                            }}
                          />
                        ) : null}
                        <div
                          className={`fallback-icon ${leader.imageSrc ? 'hidden' : ''} flex items-center justify-center text-voak-purple`}
                          aria-hidden="true"
                        >
                          <Stethoscope className="h-7 w-7 text-voak-purple" />
                        </div>
                      </div>

                      <div className="flex-1 min-w-0">
                        <Badge variant="purple" className="text-[10px] font-semibold mb-1">
                          {leader.category}
                        </Badge>
                        <h3 className="text-base font-extrabold text-voak-navy tracking-tight truncate">
                          {leader.name}
                        </h3>
                        <p className="text-xs font-semibold text-voak-purple tracking-tight truncate">
                          {leader.credentials}
                        </p>
                      </div>
                    </div>

                    <p className="text-xs font-medium text-voak-muted line-clamp-1 mb-3">
                      {leader.role}
                    </p>

                    <p className="text-xs text-voak-muted leading-relaxed line-clamp-3 mb-6">
                      {leader.bio}
                    </p>
                  </div>

                  <div className="pt-4 border-t border-voak-border/60">
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => setSelectedLeader(leader)}
                      className="w-full text-xs font-semibold text-voak-purple hover:bg-voak-purple/10 justify-between"
                      rightIcon={<ArrowRight className="h-3.5 w-3.5" />}
                    >
                      Read Full Biography
                    </Button>
                  </div>
                </Card>
              ))}
            </div>
          </div>
        </section>

        {/* Section 4: National Operating Footprint */}
        <section className="py-16 sm:py-20 bg-white border-b border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="text-center max-w-3xl mx-auto mb-14">
              <span className="text-xs font-bold uppercase tracking-widest text-voak-purple">
                Geographic Capability
              </span>
              <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-voak-navy tracking-tight mt-2">
                National Healthcare Footprint
              </h2>
              <p className="text-sm sm:text-base text-voak-muted mt-3">
                Coordinated clinical workforce and operational support across metropolitan, regional, and remote health networks.
              </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
              {[
                {
                  state: 'New South Wales',
                  hub: 'Sydney Corporate Headquarters',
                  details: 'Tier-1 public hospital staffing, private clinic logistics, and metropolitan surgical support.',
                },
                {
                  state: 'Victoria',
                  hub: 'Melbourne Operations Center',
                  details: 'Aged care facility support, clinical roster surge relief, and allied health infrastructure.',
                },
                {
                  state: 'Queensland',
                  hub: 'Brisbane Regional Division',
                  details: 'Regional hospital coverage, community health workforce, and remote telehealth coordination.',
                },
                {
                  state: 'Western & South Australia',
                  hub: 'Perth & Adelaide Network',
                  details: 'Mining & occupational health clinics, emergency department relief, and specialist fly-in support.',
                },
              ].map((loc) => (
                <div
                  key={loc.state}
                  className="rounded-3xl bg-voak-canvas/60 p-6 border border-voak-border/80 shadow-sm"
                >
                  <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-voak-purple/15 text-voak-purple mb-4">
                    <MapPin className="h-5 w-5" aria-hidden="true" />
                  </div>
                  <h3 className="text-base font-extrabold text-voak-navy mb-1">{loc.state}</h3>
                  <span className="text-xs font-bold text-voak-purple block mb-3">{loc.hub}</span>
                  <p className="text-xs text-voak-muted leading-relaxed">{loc.details}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Section 5: Milestone Timeline */}
        <section className="py-16 sm:py-20 bg-voak-canvas/40 border-b border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="text-center max-w-3xl mx-auto mb-14">
              <span className="text-xs font-bold uppercase tracking-widest text-voak-purple">
                Organizational Growth
              </span>
              <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-voak-navy tracking-tight mt-2">
                Our Journey & Strategic Milestones
              </h2>
              <p className="text-sm sm:text-base text-voak-muted mt-3">
                From targeted regional clinical support to a nationwide B2B healthcare workforce infrastructure provider.
              </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 relative">
              {milestones.map((item, index) => (
                <div
                  key={item.year}
                  className="rounded-3xl bg-white p-6 border border-voak-border/80 shadow-sm relative flex flex-col justify-between"
                >
                  <div>
                    <div className="flex items-center justify-between mb-4">
                      <span className="text-2xl font-black text-voak-purple tracking-tight">
                        {item.year}
                      </span>
                      <div className="flex h-8 w-8 items-center justify-center rounded-full bg-voak-canvas text-voak-muted text-xs font-bold">
                        <Calendar className="h-4 w-4 text-voak-purple" />
                      </div>
                    </div>
                    <h3 className="text-base font-extrabold text-voak-navy mb-2">{item.title}</h3>
                    <p className="text-xs text-voak-muted leading-relaxed">{item.description}</p>
                  </div>
                  <div className="pt-4 mt-4 border-t border-voak-border/60 text-[11px] font-semibold text-voak-muted/80">
                    Milestone 0{index + 1}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Section 6: Non-Clinical Care Operational Boundary Notice */}
        <section className="py-12 bg-white border-b border-voak-border/60">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="rounded-3xl bg-amber-500/10 border-2 border-amber-500/30 p-6 sm:p-8 flex flex-col sm:flex-row items-start gap-5">
              <div className="flex h-12 w-12 rounded-2xl bg-amber-500/20 text-amber-700 items-center justify-center shrink-0">
                <AlertTriangle className="h-6 w-6" aria-hidden="true" />
              </div>
              <div className="flex-1">
                <h3 className="text-base font-extrabold text-voak-navy mb-1.5 uppercase tracking-wide">
                  Operational Scope & Legal Boundary Notice
                </h3>
                <p className="text-xs sm:text-sm text-voak-navy/80 leading-relaxed font-medium">
                  Voak Health Care is a specialized B2B healthcare operational services provider and does not provide direct patient clinical care, emergency medical triage, or patient booking channels. All clinical professionals deployed operate strictly under the clinical supervision, institutional governance, and accredited clinical policies of the client healthcare facility.
                </p>
              </div>
            </div>
          </div>
        </section>

        {/* Section 7: Contextual CTA Banner */}
        <section className="py-16 sm:py-20 bg-voak-navy text-white relative overflow-hidden">
          <div
            className="pointer-events-none absolute -bottom-24 -right-24 h-96 w-96 rounded-full bg-voak-purple/20 blur-3xl"
            aria-hidden="true"
          />
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10 text-center max-w-3xl">
            <span className="text-xs font-bold uppercase tracking-widest text-voak-pink">
              Institutional Engagement
            </span>
            <h2 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight mt-2 mb-4 text-white">
              Partner With Our Executive Leadership Team
            </h2>
            <p className="text-sm sm:text-base text-white/80 leading-relaxed mb-8">
              Discuss how Voak Health Care can structure a high-reliability clinical workforce model, facility management agreement, or diagnostic operational audit for your health network.
            </p>

            <div className="flex flex-wrap items-center justify-center gap-4">
              <a href="/why-choose-voak">
                <Button variant="secondary" size="md">
                  Why Choose Voak
                </Button>
              </a>
              <a href="/how-we-work">
                <Button
                  variant="primary"
                  size="md"
                  className="shadow-lg font-bold"
                  rightIcon={<ArrowRight className="h-4 w-4" />}
                >
                  Our 4-Stage Roadmap
                </Button>
              </a>
            </div>
          </div>
        </section>
      </main>

      {/* Footer */}
      <PublicFooter />

      {/* Accessible Leadership Modal */}
      <LeadershipModal
        leader={selectedLeader}
        isOpen={selectedLeader !== null}
        onClose={() => setSelectedLeader(null)}
      />
    </div>
  );
};

export default AboutPage;
