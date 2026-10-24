import React from 'react';
import { cn } from '../utils/cn';

export type ButtonVariant =
  | 'primary'
  | 'secondary'
  | 'outline'
  | 'ghost'
  | 'danger'
  | 'pill-pink'
  | 'pill-yellow'
  | 'pill-mint';

export type ButtonSize = 'sm' | 'md' | 'lg';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
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

    const variants: Record<ButtonVariant, string> = {
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
      'pill-pink':
        'bg-voak-pink text-voak-navy hover:bg-[#E89DCF] active:bg-[#D886BC] rounded-full shadow-sm',
      'pill-yellow':
        'bg-voak-yellow text-voak-navy hover:bg-[#F0CF4A] active:bg-[#E5C132] rounded-full shadow-sm',
      'pill-mint':
        'bg-voak-mint text-voak-navy hover:bg-[#44B883] active:bg-[#38A573] rounded-full shadow-sm',
    };

    const isPill = variant.startsWith('pill-');

    const sizes: Record<ButtonSize, string> = {
      sm: cn('h-8 px-3 text-xs gap-1.5 min-w-[32px]', isPill ? 'rounded-full' : 'rounded-lg'),
      md: cn('h-10 px-4 text-sm gap-2 min-h-[44px]', isPill ? 'rounded-full' : 'rounded-xl'), // WCAG 2.2 touch target >= 44px
      lg: cn('h-12 px-6 text-base gap-2.5 min-h-[48px]', isPill ? 'rounded-full' : 'rounded-2xl'),
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

export interface IconButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  isLoading?: boolean;
  'aria-label': string;
  icon: React.ReactNode;
}

export const IconButton = React.forwardRef<HTMLButtonElement, IconButtonProps>(
  (
    {
      className,
      variant = 'ghost',
      size = 'md',
      isLoading = false,
      disabled,
      icon,
      'aria-label': ariaLabel,
      ...props
    },
    ref
  ) => {
    const sizeClasses: Record<ButtonSize, string> = {
      sm: 'h-8 w-8 min-h-[32px] min-w-[32px] p-1.5 rounded-lg',
      md: 'h-10 w-10 min-h-[44px] min-w-[44px] p-2.5 rounded-xl',
      lg: 'h-12 w-12 min-h-[48px] min-w-[48px] p-3 rounded-2xl',
    };

    return (
      <Button
        ref={ref}
        variant={variant}
        size={size}
        isLoading={isLoading}
        disabled={disabled}
        aria-label={ariaLabel}
        className={cn('p-0', sizeClasses[size], className)}
        {...props}
      >
        {!isLoading && icon}
      </Button>
    );
  }
);

IconButton.displayName = 'IconButton';
