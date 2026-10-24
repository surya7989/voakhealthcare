import React from 'react';
import { Search, Compass, ShieldCheck, TrendingUp } from 'lucide-react';

export const WorkflowSection: React.FC = () => {
  const steps = [
    {
      num: '01',
      title: 'Operational Discovery',
      desc: "We evaluate your organization's staffing patterns, clinical protocols, and peak demand cycles to establish precise baseline requirements.",
      icon: <Search className="h-5 w-5 text-voak-purple" />,
      accent: 'border-voak-purple/30 bg-voak-purple-light/40',
    },
    {
      num: '02',
      title: 'Solution Architecture',
      desc: 'Designing customized workforce SLA tiers, credentialing baselines, and automated communication workflows aligned with facility governance.',
      icon: <Compass className="h-5 w-5 text-[#D1348C]" />,
      accent: 'border-voak-pink/30 bg-voak-pink-light/40',
    },
    {
      num: '03',
      title: 'Deployment & Integration',
      desc: 'Targeted transition continuity into your existing roster systems with structured compliance verification and clinical orientation handoffs.',
      icon: <ShieldCheck className="h-5 w-5 text-[#9A7B00]" />,
      accent: 'border-voak-yellow/40 bg-voak-yellow-light/40',
    },
    {
      num: '04',
      title: 'Continuous Governance',
      desc: 'Scheduled clinical governance audits, transparent SLA KPI reporting, and continuous quality monitoring to ensure exceptional care outcomes.',
      icon: <TrendingUp className="h-5 w-5 text-[#1F7A4D]" />,
      accent: 'border-voak-mint/30 bg-voak-mint-light/40',
    },
  ];

  return (
    <section id="how-we-work" className="py-16 lg:py-24 bg-white border-y border-voak-border">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-3xl mx-auto mb-16">
          <span className="text-xs font-extrabold tracking-wider text-voak-purple uppercase mb-2 block">
            Operating Model
          </span>
          <h2 className="text-3xl sm:text-4xl font-extrabold tracking-tight text-voak-navy mb-4">
            How We Partner With Healthcare Organizations
          </h2>
          <p className="text-base text-voak-muted leading-relaxed">
            A disciplined, four-stage journey designed to take clinical facilities from staffing vulnerability to structured operational resilience.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {steps.map((step) => (
            <div
              key={step.num}
              className={`relative flex flex-col justify-between rounded-3xl border p-6 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md ${step.accent}`}
            >
              <div>
                <div className="flex items-center justify-between mb-4">
                  <span className="flex h-10 w-10 items-center justify-center rounded-2xl bg-white shadow-sm font-extrabold text-sm text-voak-navy">
                    {step.num}
                  </span>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-white/80 shadow-xs">
                    {step.icon}
                  </div>
                </div>

                <h3 className="text-lg font-bold text-voak-navy mb-2">
                  {step.title}
                </h3>

                <p className="text-xs text-voak-navy/80 leading-relaxed">
                  {step.desc}
                </p>
              </div>

              <div className="mt-6 pt-4 border-t border-black/5 flex items-center text-[11px] font-semibold text-voak-muted">
                <span>Stage {step.num} Governance</span>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
};
