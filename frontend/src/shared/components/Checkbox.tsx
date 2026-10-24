import React from 'react';
import { cn } from '../utils/cn';

export interface CheckboxProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label?: string;
  hasError?: boolean;
}

export const Checkbox = React.forwardRef<HTMLInputElement, CheckboxProps>(
  ({ className, label, id, hasError = false, disabled, ...props }, ref) => {
    const inputId = id || (label ? `cb-${label.toLowerCase().replace(/\s+/g, '-')}` : undefined);

    return (
      <label
        htmlFor={inputId}
        className={cn(
          'inline-flex items-center gap-2.5 text-sm font-medium text-voak-navy select-none',
          disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
          className
        )}
      >
        <input
          ref={ref}
          type="checkbox"
          id={inputId}
          disabled={disabled}
          aria-invalid={hasError ? 'true' : 'false'}
          className={cn(
            'h-4 w-4 rounded border text-voak-purple transition-colors',
            'focus-visible:ring-2 focus-visible:ring-voak-purple focus-visible:ring-offset-1',
            hasError ? 'border-state-error' : 'border-voak-border'
          )}
          {...props}
        />
        {label && <span>{label}</span>}
      </label>
    );
  }
);

Checkbox.displayName = 'Checkbox';
