import React from 'react';
import { Calendar } from 'lucide-react';
import { cn } from '../utils/cn';

export interface DateInputProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  hasError?: boolean;
  error?: boolean;
}

export const DateInput = React.forwardRef<HTMLInputElement, DateInputProps>(
  ({ className, hasError = false, error = false, disabled, ...props }, ref) => {
    const isError = Boolean(hasError || error);

    return (
      <div className="relative w-full flex items-center">
        <div className="pointer-events-none absolute left-3 flex items-center justify-center text-voak-muted">
          <Calendar className="h-4 w-4" aria-hidden="true" />
        </div>
        <input
          ref={ref}
          type="date"
          disabled={disabled}
          aria-invalid={isError ? 'true' : 'false'}
          className={cn(
            'flex h-11 w-full rounded-xl border bg-white pl-10 pr-3.5 py-2 text-sm text-voak-navy transition-colors',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-1',
            isError
              ? 'border-state-error text-state-error focus-visible:border-state-error focus-visible:ring-state-error'
              : 'border-voak-border focus-visible:border-voak-purple focus-visible:ring-voak-purple',
            'disabled:cursor-not-allowed disabled:bg-voak-canvas disabled:opacity-60',
            className
          )}
          {...props}
        />
      </div>
    );
  }
);

DateInput.displayName = 'DateInput';

export interface DateTimeDisplayProps {
  value: string | number | Date | null | undefined;
  format?: 'date' | 'time' | 'datetime' | 'relative';
  locale?: string;
  className?: string;
}

export const DateTimeDisplay: React.FC<DateTimeDisplayProps> = ({
  value,
  format = 'datetime',
  locale = 'en-US',
  className,
}) => {
  if (!value) {
    return <span className={cn('text-voak-muted', className)}>-</span>;
  }

  const date = new Date(value);
  if (isNaN(date.getTime())) {
    return <span className={cn('text-voak-muted', className)}>-</span>;
  }

  let formatted = '';

  try {
    if (format === 'date') {
      formatted = date.toLocaleDateString(locale, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    } else if (format === 'time') {
      formatted = date.toLocaleTimeString(locale, {
        hour: '2-digit',
        minute: '2-digit',
      });
    } else if (format === 'datetime') {
      formatted = date.toLocaleString(locale, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    } else if (format === 'relative') {
      const now = new Date();
      const diffSecs = Math.floor((now.getTime() - date.getTime()) / 1000);
      if (diffSecs < 60) {
        formatted = 'Just now';
      } else if (diffSecs < 3600) {
        const mins = Math.floor(diffSecs / 60);
        formatted = `${mins}m ago`;
      } else if (diffSecs < 86400) {
        const hours = Math.floor(diffSecs / 3600);
        formatted = `${hours}h ago`;
      } else {
        const days = Math.floor(diffSecs / 86400);
        formatted = `${days}d ago`;
      }
    }
  } catch {
    formatted = date.toISOString().split('T')[0];
  }

  return (
    <time
      dateTime={date.toISOString()}
      className={cn('text-xs text-voak-navy font-normal select-none', className)}
    >
      {formatted}
    </time>
  );
};
