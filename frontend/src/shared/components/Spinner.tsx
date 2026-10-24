import React from 'react';
import { cn } from '../utils/cn';

export interface SpinnerProps extends React.SVGAttributes<SVGSVGElement> {
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
  variant?: 'primary' | 'white' | 'neutral';
  label?: string;
}

const sizeClasses = {
  xs: 'h-3.5 w-3.5',
  sm: 'h-4 w-4',
  md: 'h-6 w-6',
  lg: 'h-8 w-8',
  xl: 'h-12 w-12',
};

const variantClasses = {
  primary: 'text-[#6A0DAD]',
  white: 'text-white',
  neutral: 'text-neutral-500',
};

export const Spinner: React.FC<SpinnerProps> = ({
  size = 'md',
  variant = 'primary',
  label = 'Loading...',
  className,
  ...props
}) => {
  return (
    <svg
      role="status"
      aria-label={label}
      className={cn('animate-spin', sizeClasses[size], variantClasses[variant], className)}
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      {...props}
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
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      />
      <span className="sr-only">{label}</span>
    </svg>
  );
};
