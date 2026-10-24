import React from 'react';
import { cn } from '../utils/cn';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  hasError?: boolean;
  error?: boolean;
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ className, hasError = false, error = false, type = 'text', disabled, ...props }, ref) => {
    const isError = Boolean(hasError || error);
    return (
      <input
        ref={ref}
        type={type}
        disabled={disabled}
        aria-invalid={isError ? 'true' : 'false'}
        className={cn(
          'flex h-11 w-full rounded-xl border bg-white px-3.5 py-2 text-sm text-voak-navy transition-colors placeholder:text-voak-muted/60',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-1',
          hasError
            ? 'border-state-error text-state-error focus-visible:border-state-error focus-visible:ring-state-error'
            : 'border-voak-border focus-visible:border-voak-purple focus-visible:ring-voak-purple',
          'disabled:cursor-not-allowed disabled:bg-voak-canvas disabled:opacity-60',
          className
        )}
        {...props}
      />
    );
  }
);

Input.displayName = 'Input';
