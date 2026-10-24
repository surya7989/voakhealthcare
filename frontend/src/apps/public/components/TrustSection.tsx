import React from 'react';
import { ArrowRight, Heart } from 'lucide-react';

export interface MetricItem {
  label: string;
  value: string;
}

export interface TrustSectionProps {
  metrics?: MetricItem[];
}

export const TrustSection: React.FC<TrustSectionProps> = ({ metrics }) => {
  const defaultMetrics: MetricItem[] = [
    { value: '120+', label: 'Hospital Networks' },
    { value: '450+', label: 'Credentialed Specialists' },
    { value: '1.2M+', label: 'Clinical Hours Delivered' },
    { value: '99.8%', label: 'SLA Fulfillment Rate' },
  ];

  const displayMetrics = metrics && metrics.length > 0 ? metrics : defaultMetrics;

  return (
    <section id="about" className="py-16 bg-white border-y border-voak-border">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
          {/* Col 1: Circular Operations Director Portrait */}
          <div className="flex justify-center lg:col-span-3">
            <div className="relative">
              <div
                className="absolute -top-3 -right-2 z-10 text-2xl text-purple-600 select-none animate-pulse"
                aria-hidden="true"
              >
                ✦
              </div>
              <div className="h-44 w-44 sm:h-48 sm:w-48 overflow-hidden rounded-full border-4 border-voak-canvas shadow-lg">
                <img
                  src="/images/operations_manager.jpg"
                  alt="Clinical Operations Director"
                  className="h-full w-full object-cover"
                  width={192}
                  height={192}
                  loading="lazy"
                />
              </div>
            </div>
          </div>

          {/* Col 2: Content & CTA */}
          <div className="flex flex-col items-start lg:col-span-4">
            <span className="text-xs font-extrabold tracking-wider text-voak-purple uppercase mb-2">
              Why Voak
            </span>
            <h2 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-voak-navy leading-tight mb-4">
              Trusted by Healthcare Organisations
            </h2>
            <p className="text-sm text-voak-muted leading-relaxed mb-6">
              With an uncompromising focus on clinical quality, credentialing rigor, and governance, we provide healthcare organizations with the workforce stability and operational peace of mind they need.
            </p>
            <a
              href="#how-we-work"
              className="inline-flex items-center gap-2 text-sm font-bold text-voak-purple hover:text-voak-purple-hover transition-colors"
            >
              <span>Explore Our Operating Model</span>
              <ArrowRight className="h-4 w-4" />
            </a>
          </div>

          {/* Col 3: Key Metrics Grid */}
          <div className="grid grid-cols-2 gap-4 lg:col-span-3">
            {displayMetrics.map((stat, idx) => (
              <div
                key={`stat-${idx}`}
                className="flex flex-col rounded-2xl border border-voak-border/60 bg-voak-canvas/50 p-4 shadow-sm"
              >
                <div className="text-2xl sm:text-3xl font-extrabold text-voak-purple tracking-tight mb-1">
                  {stat.value}
                </div>
                <div className="text-xs font-semibold text-voak-navy leading-snug">
                  {stat.label}
                </div>
              </div>
            ))}
          </div>

          {/* Col 4: Patient Care Card with Heart Icon */}
          <div className="flex justify-center lg:col-span-2">
            <div className="relative overflow-hidden rounded-2xl border-2 border-white shadow-md max-w-[170px]">
              <img
                src="/images/patient_care_trust.jpg"
                alt="Dedicated Patient Care in Clinical Setting"
                className="h-40 w-full object-cover"
                width={170}
                height={160}
                loading="lazy"
              />
              <div className="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-full bg-rose-500 text-white shadow-sm">
                <Heart className="h-3.5 w-3.5 fill-white" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};
