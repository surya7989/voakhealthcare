import React from 'react';
import { X, RotateCcw } from 'lucide-react';
import { cn } from '../utils/cn';

export interface FilterChipProps {
  label: string;
  value?: string;
  onRemove: () => void;
  className?: string;
}

export const FilterChip: React.FC<FilterChipProps> = ({
  label,
  value,
  onRemove,
  className,
}) => {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full border border-voak-purple/20 bg-voak-purple-light/50 px-3 py-1 text-xs font-medium text-voak-navy select-none',
        className
      )}
    >
      <span className="font-semibold text-voak-purple">{label}</span>
      {value && <span>: {value}</span>}
      <button
        type="button"
        onClick={onRemove}
        aria-label={`Remove filter for ${label}`}
        className="ml-1 rounded-full p-0.5 text-voak-muted hover:bg-voak-purple/20 hover:text-voak-navy focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-voak-purple"
      >
        <X className="h-3 w-3" aria-hidden="true" />
      </button>
    </span>
  );
};

export interface ClearFiltersButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  onClear: () => void;
  label?: string;
}

export const ClearFiltersButton: React.FC<ClearFiltersButtonProps> = ({
  onClear,
  label = 'Clear all',
  className,
  ...props
}) => {
  return (
    <button
      type="button"
      onClick={onClear}
      className={cn(
        'inline-flex items-center gap-1.5 text-xs font-medium text-voak-muted hover:text-voak-purple transition-colors select-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple rounded-md px-2 py-1',
        className
      )}
      {...props}
    >
      <RotateCcw className="h-3 w-3" aria-hidden="true" />
      <span>{label}</span>
    </button>
  );
};

export interface FilterBarProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
  activeFilters?: React.ReactNode;
  onClearAll?: () => void;
  clearAllLabel?: string;
}

export const FilterBar: React.FC<FilterBarProps> = ({
  children,
  activeFilters,
  onClearAll,
  clearAllLabel = 'Reset filters',
  className,
  ...props
}) => {
  return (
    <div
      className={cn(
        'flex flex-col gap-3 rounded-2xl border border-voak-border bg-white p-4 shadow-sm',
        className
      )}
      {...props}
    >
      {/* Primary search / filter selectors slot */}
      {children && (
        <div className="flex flex-wrap items-center gap-3">
          {children}
        </div>
      )}

      {/* Active filter chips & clear all row */}
      {(activeFilters || onClearAll) && (
        <div className="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-voak-border/60">
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-xs font-medium text-voak-muted mr-1">Active filters:</span>
            {activeFilters}
          </div>
          {onClearAll && (
            <ClearFiltersButton onClear={onClearAll} label={clearAllLabel} />
          )}
        </div>
      )}
    </div>
  );
};
