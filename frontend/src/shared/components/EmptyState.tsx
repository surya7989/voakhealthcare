import React from 'react';
import { Inbox } from 'lucide-react';
import { cn } from '../utils/cn';

export interface EmptyStateProps {
  title: string;
  description?: string;
  icon?: React.ReactNode;
  action?: React.ReactNode;
  className?: string;
}

export const EmptyState: React.FC<EmptyStateProps> = ({
  title,
  description,
  icon,
  action,
  className,
}) => {
  return (
    <div
      className={cn(
        'flex flex-col items-center justify-center p-8 sm:p-12 text-center rounded-2xl border-2 border-dashed border-neutral-200 dark:border-neutral-800 bg-white/50 dark:bg-neutral-900/50',
        className
      )}
    >
      <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#6A0DAD]/10 text-[#6A0DAD] mb-4">
        {icon || <Inbox className="h-7 w-7" />}
      </div>
      <h3 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
        {title}
      </h3>
      {description && (
        <p className="mt-1.5 max-w-sm text-sm text-neutral-500 dark:text-neutral-400">
          {description}
        </p>
      )}
      {action && <div className="mt-6">{action}</div>}
    </div>
  );
};
