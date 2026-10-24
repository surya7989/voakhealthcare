import React from 'react';
import { Breadcrumb, BreadcrumbItem } from '../../../shared/components/Breadcrumb';
import { Badge } from '../../../shared/components/Badge';

export interface CorporatePageHeroProps {
  badgeLabel: string;
  badgeVariant?: 'purple' | 'info' | 'success' | 'warning';
  title: string;
  subtitle: string;
  breadcrumbs: BreadcrumbItem[];
  actions?: React.ReactNode;
  highlights?: Array<{ label: string; value: string }>;
}

export const CorporatePageHero: React.FC<CorporatePageHeroProps> = ({
  badgeLabel,
  badgeVariant = 'purple',
  title,
  subtitle,
  breadcrumbs,
  actions,
  highlights,
}) => {
  return (
    <section className="relative overflow-hidden bg-gradient-to-b from-voak-canvas/80 via-voak-canvas/30 to-white pt-8 pb-14 border-b border-voak-border/60">
      {/* Subtle decorative background shapes */}
      <div
        className="pointer-events-none absolute -top-24 right-0 h-96 w-96 rounded-full bg-voak-purple/5 blur-3xl"
        aria-hidden="true"
      />
      <div
        className="pointer-events-none absolute top-1/2 -left-20 h-72 w-72 rounded-full bg-voak-pink/10 blur-3xl"
        aria-hidden="true"
      />

      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
        {/* Breadcrumb Navigation */}
        <div className="mb-6">
          <Breadcrumb
            items={breadcrumbs}
            homeHref="/"
            homeLabel="Voak Health Care Homepage"
            className="text-xs text-voak-muted"
          />
        </div>

        <div className="max-w-4xl">
          {/* Badge */}
          <div className="inline-flex mb-4">
            <Badge variant={badgeVariant} className="px-3.5 py-1 text-xs font-semibold tracking-wide uppercase">
              {badgeLabel}
            </Badge>
          </div>

          {/* Heading */}
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-voak-navy tracking-tight leading-[1.15] mb-6">
            {title}
          </h1>

          {/* Subtitle */}
          <p className="text-base sm:text-lg lg:text-xl text-voak-muted leading-relaxed max-w-3xl mb-8 font-normal">
            {subtitle}
          </p>

          {/* Actions & Highlights */}
          {actions && <div className="flex flex-wrap items-center gap-4 mb-8">{actions}</div>}

          {highlights && highlights.length > 0 && (
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6 border-t border-voak-border/70">
              {highlights.map((item) => (
                <div key={item.label} className="flex flex-col">
                  <span className="text-xl sm:text-2xl font-extrabold text-voak-navy">{item.value}</span>
                  <span className="text-xs font-medium text-voak-muted mt-0.5">{item.label}</span>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </section>
  );
};
