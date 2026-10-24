import React from 'react';
import { cn } from '../utils/cn';

export interface InputProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'size'> {
  hasError?: boolean;
  error?: boolean;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
  size?: 'sm' | 'md' | 'lg';
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
  (
    {
      className,
      hasError = false,
      error = false,
      type = 'text',
      disabled,
      leftIcon,
      rightIcon,
      size = 'md',
      ...props
    },
    ref
  ) => {
    const isError = Boolean(hasError || error);

    const sizeStyles = {
      sm: 'h-9 px-3 text-xs rounded-lg',
      md: 'h-11 px-3.5 text-sm rounded-xl',
      lg: 'h-13 px-4 text-base rounded-2xl',
    };

    const inputElement = (
      <input
        ref={ref}
        type={type}
        disabled={disabled}
        aria-invalid={isError ? 'true' : 'false'}
        className={cn(
          'w-full border bg-white text-voak-navy transition-colors placeholder:text-voak-muted/60',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-1',
          sizeStyles[size],
          leftIcon && 'pl-10',
          rightIcon && 'pr-10',
          isError
            ? 'border-state-error text-state-error focus-visible:border-state-error focus-visible:ring-state-error'
            : 'border-voak-border focus-visible:border-voak-purple focus-visible:ring-voak-purple',
          'disabled:cursor-not-allowed disabled:bg-voak-canvas disabled:opacity-60',
          className
        )}
        {...props}
      />
    );

    if (leftIcon || rightIcon) {
      return (
        <div className="relative w-full flex items-center">
          {leftIcon && (
            <div className="pointer-events-none absolute left-3 flex items-center justify-center text-voak-muted">
              {leftIcon}
            </div>
          )}
          {inputElement}
          {rightIcon && (
            <div className="absolute right-3 flex items-center justify-center text-voak-muted">
              {rightIcon}
            </div>
          )}
        </div>
      );
    }

    return inputElement;
  }
);

Input.displayName = 'Input';
