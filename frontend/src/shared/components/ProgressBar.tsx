import React from 'react';
import { cn } from '../utils/cn';

export type ProgressVariant = 'purple' | 'mint' | 'pink' | 'yellow' | 'navy';
export type ProgressSize = 'sm' | 'md' | 'lg';

export interface ProgressBarProps extends React.HTMLAttributes<HTMLDivElement> {
  value: number; // 0 to 100
  max?: number;
  variant?: ProgressVariant;
  size?: ProgressSize;
  showLabel?: boolean;
  label?: string;
  indeterminate?: boolean;
}

export const ProgressBar: React.FC<ProgressBarProps> = ({
  value,
  max = 100,
  variant = 'purple',
  size = 'md',
  showLabel = false,
  label,
  indeterminate = false,
  className,
  ...props
}) => {
  const percentage = Math.min(100, Math.max(0, Math.round((value / max) * 100)));

  const sizeClasses: Record<ProgressSize, string> = {
    sm: 'h-1.5',
    md: 'h-2.5',
    lg: 'h-4',
  };

  const fillColors: Record<ProgressVariant, string> = {
    purple: 'bg-voak-purple',
    mint: 'bg-voak-mint',
    pink: 'bg-voak-pink',
    yellow: 'bg-voak-yellow',
    navy: 'bg-voak-navy',
  };

  return (
    <div className={cn('w-full flex flex-col gap-1.5', className)} {...props}>
      {(label || showLabel) && (
        <div className="flex items-center justify-between text-xs font-medium text-voak-navy">
          <span>{label}</span>
          {showLabel && <span>{indeterminate ? 'Processing...' : `${percentage}%`}</span>}
        </div>
      )}

      <div
        role="progressbar"
        aria-valuenow={indeterminate ? undefined : percentage}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label={label || 'Progress'}
        className={cn(
          'w-full overflow-hidden rounded-full bg-voak-canvas border border-voak-border/60',
          sizeClasses[size]
        )}
      >
        <div
          className={cn(
            'h-full rounded-full transition-all duration-300 ease-in-out',
            fillColors[variant],
            indeterminate && 'w-1/3 animate-pulse'
          )}
          style={{ width: indeterminate ? '40%' : `${percentage}%` }}
        />
      </div>
    </div>
  );
};
