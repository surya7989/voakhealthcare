import React from 'react';
import { cn } from '../utils/cn';

export interface FormFieldProps {
  id?: string;
  label?: string;
  required?: boolean;
  error?: string;
  helperText?: string;
  className?: string;
  children: React.ReactNode;
}

export const FormField: React.FC<FormFieldProps> = ({
  id,
  label,
  required = false,
  error,
  helperText,
  className,
  children,
}) => {
  const errorId = id && error ? `${id}-error` : undefined;
  const helperId = id && helperText ? `${id}-helper` : undefined;

  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      {label && (
        <label
          htmlFor={id}
          className="text-xs font-semibold uppercase tracking-wider text-voak-navy"
        >
          {label}
          {required && <span className="ml-1 text-state-error" aria-hidden="true">*</span>}
        </label>
      )}

      {children}

      {error ? (
        <p
          id={errorId}
          className="text-xs font-medium text-state-error"
          role="alert"
        >
          {error}
        </p>
      ) : helperText ? (
        <p id={helperId} className="text-xs text-voak-muted">
          {helperText}
        </p>
      ) : null}
    </div>
  );
};
