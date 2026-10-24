import React from 'react';
import { ChevronRight, Home } from 'lucide-react';
import { Link } from '../router/Router';
import { cn } from '../utils/cn';

export interface BreadcrumbItem {
  label: string;
  href?: string;
}

export interface BreadcrumbProps {
  items: BreadcrumbItem[];
  className?: string;
  showHomeIcon?: boolean;
}

export const Breadcrumb: React.FC<BreadcrumbProps> = ({
  items,
  className,
  showHomeIcon = true,
}) => {
  return (
    <nav aria-label="Breadcrumb" className={cn('flex items-center text-sm', className)}>
      <ol className="flex items-center gap-1.5 flex-wrap">
        {showHomeIcon && (
          <li className="inline-flex items-center">
            <Link
              to="/admin"
              className="text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100 flex items-center transition-colors"
              aria-label="Admin Home"
            >
              <Home className="h-4 w-4" />
            </Link>
          </li>
        )}

        {items.map((item, index) => {
          const isLast = index === items.length - 1;
          return (
            <li key={`${item.label}-${index}`} className="inline-flex items-center gap-1.5">
              {(showHomeIcon || index > 0) && (
                <ChevronRight className="h-3.5 w-3.5 text-neutral-400 shrink-0" aria-hidden="true" />
              )}
              {isLast || !item.href ? (
                <span
                  className="font-medium text-neutral-900 dark:text-neutral-100"
                  aria-current={isLast ? 'page' : undefined}
                >
                  {item.label}
                </span>
              ) : (
                <Link
                  to={item.href}
                  className="text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100 transition-colors"
                >
                  {item.label}
                </Link>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
};
