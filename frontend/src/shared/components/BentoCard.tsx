import React from 'react';
import { cn } from '../utils/cn';

export type BentoTint = 'white' | 'pink' | 'yellow' | 'mint' | 'sky' | 'purple';
export type BentoColSpan = 4 | 6 | 8 | 12;

export interface BentoCardProps extends Omit<React.HTMLAttributes<HTMLDivElement>, 'title'> {
  colSpan?: BentoColSpan;
  tint?: BentoTint;
  title?: React.ReactNode;
  subtitle?: React.ReactNode;
  icon?: React.ReactNode;
  chip?: React.ReactNode;
  action?: React.ReactNode;
  interactive?: boolean;
}

export const BentoCard: React.FC<BentoCardProps> = ({
  colSpan = 6,
  tint = 'white',
  title,
  subtitle,
  icon,
  chip,
  action,
  interactive = false,
  className,
  children,
  ...props
}) => {
  const colSpanClasses: Record<BentoColSpan, string> = {
    4: 'col-span-12 md:col-span-4',
    6: 'col-span-12 md:col-span-6',
    8: 'col-span-12 md:col-span-8',
    12: 'col-span-12',
  };

  const tintClasses: Record<BentoTint, string> = {
    white: 'bg-white border-voak-border text-voak-navy',
    pink: 'bg-voak-pink-light/70 border-voak-pink/30 text-voak-navy',
    yellow: 'bg-voak-yellow-light/70 border-voak-yellow/40 text-voak-navy',
    mint: 'bg-voak-mint-light/70 border-voak-mint/30 text-voak-navy',
    sky: 'bg-voak-sky-light/70 border-voak-sky/30 text-voak-navy',
    purple: 'bg-voak-purple-light/50 border-voak-purple/20 text-voak-navy',
  };

  return (
    <div
      className={cn(
        'relative flex flex-col justify-between overflow-hidden rounded-3xl border p-6 shadow-sm transition-all duration-200',
        colSpanClasses[colSpan],
        tintClasses[tint],
        interactive && 'hover:-translate-y-1 hover:shadow-md cursor-pointer',
        className
      )}
      {...props}
    >
      {/* Top Header Row (Chip / Icon / Action) */}
      {(chip || icon || action) && (
        <div className="flex items-start justify-between gap-3 mb-4">
          <div className="flex items-center gap-2.5">
            {icon && (
              <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-white shadow-sm text-voak-navy">
                {icon}
              </div>
            )}
            {chip && <div>{chip}</div>}
          </div>
          {action && <div className="flex items-center">{action}</div>}
        </div>
      )}

      {/* Main Content Area */}
      <div className="flex-1">
        {title && (
          <h3 className="text-xl font-bold tracking-tight text-voak-navy mb-1">
            {title}
          </h3>
        )}
        {subtitle && (
          <p className="text-sm font-normal text-voak-muted mb-4">
            {subtitle}
          </p>
        )}
        {children}
      </div>
    </div>
  );
};
