import React from 'react';
import { Building, HeartPulse, Home, Briefcase, ArrowRight } from 'lucide-react';
import { BentoCard } from '../../../shared/components/BentoCard';
import { Skeleton } from '../../../shared/components/Skeleton';

export interface IndustryItem {
  id?: number | string;
  name: string;
  slug?: string;
  summary?: string;
  description?: string;
}

export interface IndustriesSectionProps {
  industries?: IndustryItem[];
  isLoading?: boolean;
}

const defaultIndustries = [
  {
    title: 'Public & Private Hospitals',
    tint: 'purple' as const,
    icon: <Building className="h-5 w-5 text-voak-purple" />,
    description: 'Emergency department staffing, ICU nurse allocation, and acute surgical team support with guaranteed credentialing.',
  },
  {
    title: 'Aged Care Facilities',
    tint: 'pink' as const,
    icon: <Home className="h-5 w-5 text-[#D1348C]" />,
    description: 'Specialized geriatric nursing, medication management, and 24/7 continuous care operations for residential facilities.',
  },
  {
    title: 'Community & Specialized Care',
    tint: 'yellow' as const,
    icon: <HeartPulse className="h-5 w-5 text-[#9A7B00]" />,
    description: 'Community nursing, clinical support coordination, and specialized healthcare assistance delivered with empathy.',
  },
  {
    title: 'Corporate & Occupational Health',
    tint: 'mint' as const,
    icon: <Briefcase className="h-5 w-5 text-[#1F7A4D]" />,
    description: 'Onsite health assessments, workplace wellness programs, and industrial health governance for modern enterprises.',
  },
];

export const IndustriesSection: React.FC<IndustriesSectionProps> = ({
  industries = [],
  isLoading = false,
}) => {
  const displayIndustries =
    industries.length > 0
      ? industries.slice(0, 4).map((ind, idx) => ({
          title: ind.name,
          tint: defaultIndustries[idx % defaultIndustries.length].tint,
          icon: defaultIndustries[idx % defaultIndustries.length].icon,
          description: ind.summary || ind.description || defaultIndustries[idx % defaultIndustries.length].description,
        }))
      : defaultIndustries;

  return (
    <section id="industries" className="py-16 lg:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex flex-col md:flex-row md:items-end justify-between mb-12">
          <div>
            <span className="text-xs font-extrabold tracking-wider text-voak-purple uppercase mb-2 block">
              Sectors Covered
            </span>
            <h2 className="text-3xl sm:text-4xl font-extrabold tracking-tight text-voak-navy">
              Industries We Serve
            </h2>
            <p className="mt-2 text-base text-voak-muted max-w-xl">
              Dedicated operational frameworks tailored for the distinct regulatory and clinical demands of each healthcare sector.
            </p>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8">
          {isLoading
            ? Array.from({ length: 4 }).map((_, idx) => (
                <div
                  key={`ind-skel-${idx}`}
                  className="rounded-3xl border border-voak-border bg-white p-8 h-48 flex flex-col justify-between"
                >
                  <Skeleton className="h-8 w-1/2 rounded" />
                  <Skeleton className="h-4 w-full rounded" />
                  <Skeleton className="h-4 w-3/4 rounded" />
                </div>
              ))
            : displayIndustries.map((ind, idx) => (
                <BentoCard
                  key={`ind-${idx}`}
                  colSpan={12}
                  tint={ind.tint}
                  icon={ind.icon}
                  title={ind.title}
                  interactive={true}
                  action={
                    <a
                      href="#contact"
                      aria-label={`Inquire about ${ind.title}`}
                      className="flex h-8 w-8 items-center justify-center rounded-full bg-white shadow-sm text-voak-navy hover:bg-voak-navy hover:text-white transition-colors"
                    >
                      <ArrowRight className="h-4 w-4" />
                    </a>
                  }
                  className="h-full justify-between"
                >
                  <p className="text-sm text-voak-navy/80 leading-relaxed mt-2">
                    {ind.description}
                  </p>
                </BentoCard>
              ))}
        </div>
      </div>
    </section>
  );
};
