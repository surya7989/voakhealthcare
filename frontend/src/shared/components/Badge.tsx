import React from 'react';
import { cn } from '../utils/cn';

export type BadgeVariant =
  | 'purple'
  | 'success'
  | 'warning'
  | 'error'
  | 'info'
  | 'neutral'
  | 'pink'
  | 'yellow'
  | 'mint'
  | 'sky'
  | 'navy';

export interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  variant?: BadgeVariant;
  size?: 'sm' | 'md';
  dot?: boolean;
}

export const Badge: React.FC<BadgeProps> = ({
  className,
  variant = 'neutral',
  size = 'md',
  dot = false,
  children,
  ...props
}) => {
  const variants: Record<BadgeVariant, string> = {
    purple: 'bg-voak-purple-light text-voak-purple border-voak-purple/20',
    success: 'bg-state-success-bg text-state-success border-state-success/20',
    warning: 'bg-state-warning-bg text-state-warning border-state-warning/20',
    error: 'bg-state-error-bg text-state-error border-state-error/20',
    info: 'bg-state-info-bg text-state-info border-state-info/20',
    neutral: 'bg-voak-canvas text-voak-muted border-voak-border',
    pink: 'bg-voak-pink-light text-[#D1348C] border-voak-pink/30',
    yellow: 'bg-voak-yellow-light text-[#9A7B00] border-voak-yellow/40',
    mint: 'bg-voak-mint-light text-[#1F7A4D] border-voak-mint/30',
    sky: 'bg-voak-sky-light text-[#2A74B8] border-voak-sky/30',
    navy: 'bg-[#EAEBF0] text-voak-navy border-voak-navy/20',
  };

  const dotColors: Record<BadgeVariant, string> = {
    purple: 'bg-voak-purple',
    success: 'bg-state-success',
    warning: 'bg-state-warning',
    error: 'bg-state-error',
    info: 'bg-state-info',
    neutral: 'bg-voak-muted',
    pink: 'bg-voak-pink',
    yellow: 'bg-voak-yellow',
    mint: 'bg-voak-mint',
    sky: 'bg-voak-sky',
    navy: 'bg-voak-navy',
  };

  const sizes = {
    sm: 'px-2 py-0.5 text-[11px] gap-1.5',
    md: 'px-2.5 py-1 text-xs gap-1.5',
  };

  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full border font-semibold tracking-wide select-none',
        variants[variant],
        sizes[size],
        className
      )}
      {...props}
    >
      {dot && (
        <span
          className={cn('inline-block h-1.5 w-1.5 rounded-full flex-shrink-0', dotColors[variant])}
          aria-hidden="true"
        />
      )}
      {children}
    </span>
  );
};

export type SemanticStatus =
  | 'active'
  | 'inactive'
  | 'pending'
  | 'published'
  | 'draft'
  | 'archived'
  | 'verified'
  | 'failed'
  | 'success'
  | 'warning'
  | 'danger'
  | 'neutral'
  | string;

export interface StatusBadgeProps extends Omit<BadgeProps, 'variant'> {
  status: SemanticStatus;
  variantMap?: Record<string, BadgeVariant>;
}

export const StatusBadge: React.FC<StatusBadgeProps> = ({
  status,
  variantMap,
  className,
  dot = true,
  children,
  ...props
}) => {
  const defaultStatusMap: Record<string, BadgeVariant> = {
    active: 'success',
    published: 'success',
    verified: 'success',
    success: 'success',
    pending: 'warning',
    warning: 'warning',
    draft: 'yellow',
    inactive: 'neutral',
    archived: 'neutral',
    neutral: 'neutral',
    failed: 'error',
    danger: 'error',
    error: 'error',
    info: 'info',
  };

  const normalized = status.toLowerCase();
  const variant: BadgeVariant =
    variantMap?.[status] ||
    variantMap?.[normalized] ||
    defaultStatusMap[normalized] ||
    'neutral';

  return (
    <Badge variant={variant} dot={dot} className={className} {...props}>
      {children || status.charAt(0).toUpperCase() + status.slice(1)}
    </Badge>
  );
};

