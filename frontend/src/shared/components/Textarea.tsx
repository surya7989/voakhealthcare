import React from 'react';
import { cn } from '../utils/cn';

export interface TextareaProps
  extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
  hasError?: boolean;
}

export const Textarea = React.forwardRef<HTMLTextAreaElement, TextareaProps>(
  ({ className, hasError = false, disabled, ...props }, ref) => {
    return (
      <textarea
        ref={ref}
        disabled={disabled}
        aria-invalid={hasError ? 'true' : 'false'}
        className={cn(
          'flex min-h-[96px] w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-voak-navy transition-colors placeholder:text-voak-muted/60',
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

Textarea.displayName = 'Textarea';
