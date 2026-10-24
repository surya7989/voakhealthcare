import React from 'react';
import { cn } from '../utils/cn';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
  size?: 'sm' | 'md' | 'lg';
  isLoading?: boolean;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  (
    {
      className,
      variant = 'primary',
      size = 'md',
      isLoading = false,
      disabled,
      leftIcon,
      rightIcon,
      children,
      ...props
    },
    ref
  ) => {
    const baseStyles =
      'inline-flex items-center justify-center font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 select-none';

    const variants = {
      primary:
        'bg-voak-purple text-white hover:bg-voak-purple-hover active:bg-voak-purple-active shadow-sm',
      secondary:
        'bg-voak-navy text-white hover:bg-voak-navy-surface shadow-sm',
      outline:
        'border border-voak-border bg-white text-voak-navy hover:bg-voak-canvas hover:border-voak-purple/50',
      ghost:
        'text-voak-navy hover:bg-voak-purple-light hover:text-voak-purple',
      danger:
        'bg-state-error text-white hover:bg-red-700 active:bg-red-800 shadow-sm',
    };

    const sizes = {
      sm: 'h-8 px-3 text-xs rounded-lg gap-1.5 min-w-[32px]',
      md: 'h-10 px-4 text-sm rounded-xl gap-2 min-h-[44px]', // WCAG 2.2 touch target >= 44px
      lg: 'h-12 px-6 text-base rounded-2xl gap-2.5 min-h-[48px]',
    };

    return (
      <button
        ref={ref}
        className={cn(baseStyles, variants[variant], sizes[size], className)}
        disabled={disabled || isLoading}
        {...props}
      >
        {isLoading ? (
          <span role="status" aria-label="Loading" className="inline-flex">
            <svg
              className="h-4 w-4 animate-spin text-current"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <circle
                className="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                strokeWidth="4"
              />
              <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v8H4z"
              />
            </svg>
          </span>
        ) : (
          leftIcon
        )}
        {children}
        {!isLoading && rightIcon}
      </button>
    );
  }
);

Button.displayName = 'Button';
