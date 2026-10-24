import React from 'react';
import {
  Users,
  Activity,
  UserCheck,
  Building2,
  ShieldCheck,
  Cpu,
  Layers,
  GraduationCap,
  ArrowRight,
} from 'lucide-react';
import { Button } from '../../../shared/components/Button';
import { Skeleton } from '../../../shared/components/Skeleton';

export interface ServiceItem {
  id?: number | string;
  name: string;
  slug?: string;
  summary?: string;
  description?: string;
  icon?: string;
}

export interface ServicesBentoSectionProps {
  services?: ServiceItem[];
  isLoading?: boolean;
}

const defaultTiles = [
  {
    title: 'Clinical Support',
    tint: 'bg-voak-purple-light/70 border-voak-purple/20 text-voak-purple',
    iconBg: 'bg-voak-purple text-white',
    icon: <Users className="h-5 w-5" />,
  },
  {
    title: 'Allied Health Services',
    tint: 'bg-voak-pink-light/70 border-voak-pink/30 text-[#D1348C]',
    iconBg: 'bg-[#EEAFD7] text-voak-navy',
    icon: <Activity className="h-5 w-5" />,
  },
  {
    title: 'Healthcare Staffing',
    tint: 'bg-voak-sky-light/70 border-voak-sky/30 text-[#2A74B8]',
    iconBg: 'bg-[#63A5E6] text-white',
    icon: <UserCheck className="h-5 w-5" />,
  },
  {
    title: 'Operational Support',
    tint: 'bg-voak-yellow-light/70 border-voak-yellow/40 text-[#9A7B00]',
    iconBg: 'bg-[#FBDC62] text-voak-navy',
    icon: <Building2 className="h-5 w-5" />,
  },
  {
    title: 'Practice Management',
    tint: 'bg-voak-mint-light/70 border-voak-mint/30 text-[#1F7A4D]',
    iconBg: 'bg-[#54CB96] text-white',
    icon: <ShieldCheck className="h-5 w-5" />,
  },
  {
    title: 'Health Technology Solutions',
    tint: 'bg-voak-purple-light/70 border-voak-purple/20 text-voak-purple',
    iconBg: 'bg-voak-purple text-white',
    icon: <Cpu className="h-5 w-5" />,
  },
  {
    title: 'Consulting & Advisory',
    tint: 'bg-voak-pink-light/70 border-voak-pink/30 text-[#D1348C]',
    iconBg: 'bg-[#EEAFD7] text-voak-navy',
    icon: <Layers className="h-5 w-5" />,
  },
  {
    title: 'Training & Development',
    tint: 'bg-voak-sky-light/70 border-voak-sky/30 text-[#2A74B8]',
    iconBg: 'bg-[#63A5E6] text-white',
    icon: <GraduationCap className="h-5 w-5" />,
  },
];

export const ServicesBentoSection: React.FC<ServicesBentoSectionProps> = ({
  services = [],
  isLoading = false,
}) => {
  const displayTiles =
    services.length > 0
      ? services.slice(0, 8).map((s, idx) => ({
          title: s.name,
          tint: defaultTiles[idx % defaultTiles.length].tint,
          iconBg: defaultTiles[idx % defaultTiles.length].iconBg,
          icon: defaultTiles[idx % defaultTiles.length].icon,
        }))
      : defaultTiles;

  return (
    <section id="services" className="py-16 lg:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 gap-12 lg:grid-cols-12 lg:gap-12 items-start">
          {/* Left Intro Column */}
          <div className="flex flex-col items-start lg:col-span-4">
            <span className="inline-flex items-center rounded-full border border-voak-purple/20 bg-voak-purple-light px-3 py-1 text-xs font-bold uppercase tracking-wider text-voak-purple mb-4">
              Our Services
            </span>
            <h2 className="text-3xl sm:text-4xl font-extrabold tracking-tight text-voak-navy leading-tight mb-4">
              We Provide End-to-End Support
            </h2>
            <p className="text-base text-voak-muted leading-relaxed mb-8">
              Our flexible, scalable and people-focused solutions are designed to meet the evolving operational needs of the healthcare industry.
            </p>
            <a href="#contact">
              <Button
                variant="primary"
                size="md"
                className="font-semibold shadow-sm"
                rightIcon={<ArrowRight className="h-4 w-4" />}
              >
                Inquire About Services
              </Button>
            </a>
          </div>

          {/* Right 8-Tile Bento Grid */}
          <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 lg:col-span-8">
            {isLoading
              ? Array.from({ length: 8 }).map((_, idx) => (
                  <div
                    key={`service-skel-${idx}`}
                    className="flex flex-col justify-between rounded-2xl border border-voak-border bg-white p-5 h-36"
                  >
                    <Skeleton className="h-10 w-10 rounded-full" />
                    <Skeleton className="h-4 w-3/4 rounded" />
                  </div>
                ))
              : displayTiles.map((tile, idx) => (
                  <div
                    key={`tile-${idx}`}
                    className={`group relative flex flex-col justify-between overflow-hidden rounded-2xl border p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-md cursor-pointer ${tile.tint}`}
                  >
                    <div
                      className={`flex h-11 w-11 items-center justify-center rounded-full shadow-sm mb-4 transition-transform group-hover:scale-110 ${tile.iconBg}`}
                    >
                      {tile.icon}
                    </div>
                    <div className="flex items-end justify-between gap-2">
                      <h3 className="text-sm font-bold text-voak-navy leading-snug">
                        {tile.title}
                      </h3>
                      <span className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-white/80 text-voak-navy group-hover:bg-voak-navy group-hover:text-white transition-colors">
                        <ArrowRight className="h-3.5 w-3.5" />
                      </span>
                    </div>
                  </div>
                ))}
          </div>
        </div>
      </div>
    </section>
  );
};
