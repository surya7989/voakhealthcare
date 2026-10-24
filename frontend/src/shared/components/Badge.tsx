import React from 'react';
import { cn } from '../utils/cn';

export interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  variant?: 'purple' | 'success' | 'warning' | 'error' | 'info' | 'neutral' | 'pink' | 'yellow' | 'mint';
  size?: 'sm' | 'md';
}

export const Badge: React.FC<BadgeProps> = ({
  className,
  variant = 'neutral',
  size = 'md',
  children,
  ...props
}) => {
  const variants = {
    purple: 'bg-voak-purple-light text-voak-purple border-voak-purple/20',
    success: 'bg-state-success-bg text-state-success border-state-success/20',
    warning: 'bg-state-warning-bg text-state-warning border-state-warning/20',
    error: 'bg-state-error-bg text-state-error border-state-error/20',
    info: 'bg-state-info-bg text-state-info border-state-info/20',
    neutral: 'bg-voak-canvas text-voak-muted border-voak-border',
    pink: 'bg-voak-pink-light text-[#D1348C] border-voak-pink/30',
    yellow: 'bg-voak-yellow-light text-[#9A7B00] border-voak-yellow/40',
    mint: 'bg-voak-mint-light text-[#1F7A4D] border-voak-mint/30',
  };

  const sizes = {
    sm: 'px-2 py-0.5 text-[11px]',
    md: 'px-2.5 py-1 text-xs',
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
      {children}
    </span>
  );
};
