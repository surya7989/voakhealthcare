import React from 'react';
import { cn } from '../utils/cn';

export interface PageHeaderProps {
  title: string;
  description?: string;
  breadcrumbs?: React.ReactNode;
  actions?: React.ReactNode;
  className?: string;
}

export const PageHeader: React.FC<PageHeaderProps> = ({
  title,
  description,
  breadcrumbs,
  actions,
  className,
}) => {
  return (
    <div className={cn('mb-6 sm:mb-8', className)}>
      {breadcrumbs && <div className="mb-3">{breadcrumbs}</div>}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100 sm:text-3xl">
            {title}
          </h1>
          {description && (
            <p className="mt-1.5 text-sm text-neutral-500 dark:text-neutral-400 max-w-3xl">
              {description}
            </p>
          )}
        </div>
        {actions && <div className="flex flex-wrap items-center gap-3 shrink-0">{actions}</div>}
      </div>
    </div>
  );
};
