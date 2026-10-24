import React from 'react';
import { Info, CheckCircle2, AlertTriangle, AlertCircle, X } from 'lucide-react';
import { cn } from '../utils/cn';

export interface AlertProps {
  variant?: 'info' | 'success' | 'warning' | 'error';
  title?: React.ReactNode;
  children: React.ReactNode;
  onClose?: () => void;
  className?: string;
}

const variantStyles = {
  info: {
    container: 'bg-blue-50/80 border-blue-200 text-blue-900 dark:bg-blue-950/40 dark:border-blue-800 dark:text-blue-200',
    icon: 'text-blue-600 dark:text-blue-400',
    IconComponent: Info,
  },
  success: {
    container: 'bg-emerald-50/80 border-emerald-200 text-emerald-900 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-200',
    icon: 'text-emerald-600 dark:text-emerald-400',
    IconComponent: CheckCircle2,
  },
  warning: {
    container: 'bg-amber-50/80 border-amber-200 text-amber-900 dark:bg-amber-950/40 dark:border-amber-800 dark:text-amber-200',
    icon: 'text-amber-600 dark:text-amber-400',
    IconComponent: AlertTriangle,
  },
  error: {
    container: 'bg-rose-50/80 border-rose-200 text-rose-900 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-200',
    icon: 'text-rose-600 dark:text-rose-400',
    IconComponent: AlertCircle,
  },
};

export const Alert: React.FC<AlertProps> = ({
  variant = 'info',
  title,
  children,
  onClose,
  className,
}) => {
  const { container, icon, IconComponent } = variantStyles[variant];

  return (
    <div
      role="alert"
      className={cn(
        'relative flex items-start gap-3 rounded-xl border p-4 text-sm transition-all',
        container,
        className
      )}
    >
      <IconComponent className={cn('h-5 w-5 shrink-0 mt-0.5', icon)} aria-hidden="true" />
      <div className="flex-1 min-w-0">
        {title && <h4 className="font-semibold leading-5 mb-1">{title}</h4>}
        <div className="leading-relaxed opacity-95">{children}</div>
      </div>
      {onClose && (
        <button
          type="button"
          onClick={onClose}
          className="shrink-0 -mr-1 -mt-1 p-1 rounded-md opacity-70 hover:opacity-100 hover:bg-black/5 dark:hover:bg-white/5 transition-opacity"
          aria-label="Dismiss alert"
        >
          <X className="h-4 w-4" />
        </button>
      )}
    </div>
  );
};
