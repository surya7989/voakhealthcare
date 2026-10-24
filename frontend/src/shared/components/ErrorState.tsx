import React from 'react';
import { AlertCircle, RotateCcw } from 'lucide-react';
import { Button } from './Button';
import { cn } from '../utils/cn';

export interface ErrorStateProps {
  title?: string;
  message?: string;
  onRetry?: () => void;
  retryLabel?: string;
  action?: React.ReactNode;
  className?: string;
}

export const ErrorState: React.FC<ErrorStateProps> = ({
  title = 'Something went wrong',
  message = 'An unexpected error occurred while loading this section. Please try again.',
  onRetry,
  retryLabel = 'Try Again',
  action,
  className,
}) => {
  return (
    <div
      role="alert"
      className={cn(
        'flex flex-col items-center justify-center p-8 sm:p-12 text-center rounded-2xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/50 dark:bg-rose-950/20',
        className
      )}
    >
      <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 mb-4">
        <AlertCircle className="h-7 w-7" />
      </div>
      <h3 className="text-lg font-semibold text-rose-950 dark:text-rose-100">
        {title}
      </h3>
      {message && (
        <p className="mt-1.5 max-w-sm text-sm text-rose-700 dark:text-rose-300">
          {message}
        </p>
      )}
      <div className="mt-6 flex items-center gap-3">
        {onRetry && (
          <Button
            variant="secondary"
            onClick={onRetry}
            leftIcon={<RotateCcw className="h-4 w-4" />}
          >
            {retryLabel}
          </Button>
        )}
        {action}
      </div>
    </div>
  );
};
