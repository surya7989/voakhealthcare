import React from 'react';
import { cn } from '../utils/cn';

export interface SelectOption {
  value: string;
  label: string;
  disabled?: boolean;
}

export interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
  options: SelectOption[];
  hasError?: boolean;
}

export const Select = React.forwardRef<HTMLSelectElement, SelectProps>(
  ({ className, options, hasError = false, disabled, ...props }, ref) => {
    return (
      <div className="relative w-full">
        <select
          ref={ref}
          disabled={disabled}
          aria-invalid={hasError ? 'true' : 'false'}
          className={cn(
            'flex h-11 w-full appearance-none rounded-xl border bg-white px-3.5 py-2 pr-10 text-sm text-voak-navy transition-colors',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-1',
            hasError
              ? 'border-state-error text-state-error focus-visible:border-state-error focus-visible:ring-state-error'
              : 'border-voak-border focus-visible:border-voak-purple focus-visible:ring-voak-purple',
            'disabled:cursor-not-allowed disabled:bg-voak-canvas disabled:opacity-60',
            className
          )}
          {...props}
        >
          {options.map((opt) => (
            <option key={opt.value} value={opt.value} disabled={opt.disabled}>
              {opt.label}
            </option>
          ))}
        </select>
        <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-voak-muted">
          <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
          </svg>
        </div>
      </div>
    );
  }
);

Select.displayName = 'Select';
