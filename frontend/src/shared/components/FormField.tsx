import React from 'react';
import { cn } from '../utils/cn';

export interface FieldLabelProps extends React.LabelHTMLAttributes<HTMLLabelElement> {
  required?: boolean;
}

export const FieldLabel: React.FC<FieldLabelProps> = ({
  children,
  required,
  className,
  ...props
}) => {
  return (
    <label
      className={cn(
        'text-xs font-semibold uppercase tracking-wider text-voak-navy select-none',
        className
      )}
      {...props}
    >
      {children}
      {required && (
        <span className="ml-1 text-state-error" aria-hidden="true">
          *
        </span>
      )}
    </label>
  );
};

export type FieldDescriptionProps = React.HTMLAttributes<HTMLParagraphElement>;

export const FieldDescription: React.FC<FieldDescriptionProps> = ({
  children,
  className,
  ...props
}) => {
  return (
    <p className={cn('text-xs text-voak-muted', className)} {...props}>
      {children}
    </p>
  );
};

export interface FieldErrorProps extends React.HTMLAttributes<HTMLParagraphElement> {
  error?: string | null;
}

export const FieldError: React.FC<FieldErrorProps> = ({
  error,
  children,
  className,
  ...props
}) => {
  const content = error || children;
  if (!content) return null;

  return (
    <p
      className={cn('text-xs font-medium text-state-error', className)}
      role="alert"
      {...props}
    >
      {content}
    </p>
  );
};

export interface ValidationSummaryProps extends React.HTMLAttributes<HTMLDivElement> {
  title?: string;
  errors: string[];
}

export const ValidationSummary: React.FC<ValidationSummaryProps> = ({
  title = 'Please correct the following errors:',
  errors,
  className,
  ...props
}) => {
  if (!errors || errors.length === 0) return null;

  return (
    <div
      role="alert"
      aria-live="assertive"
      className={cn(
        'rounded-xl border border-state-error/30 bg-state-error-bg p-4 text-sm text-state-error',
        className
      )}
      {...props}
    >
      <p className="font-semibold mb-1.5">{title}</p>
      <ul className="list-disc list-inside space-y-1 text-xs">
        {errors.map((err, idx) => (
          <li key={idx}>{err}</li>
        ))}
      </ul>
    </div>
  );
};

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
        <FieldLabel htmlFor={id} required={required}>
          {label}
        </FieldLabel>
      )}

      {children}

      {error ? (
        <FieldError id={errorId} error={error} />
      ) : helperText ? (
        <FieldDescription id={helperId}>{helperText}</FieldDescription>
      ) : null}
    </div>
  );
};

