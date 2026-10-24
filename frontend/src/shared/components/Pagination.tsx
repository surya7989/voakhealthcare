import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from './Button';
import { cn } from '../utils/cn';

export interface PaginationProps {
  currentPage: number;
  totalPages: number;
  onPageChange: (page: number) => void;
  totalItems?: number;
  pageSize?: number;
  className?: string;
}

export const Pagination: React.FC<PaginationProps> = ({
  currentPage,
  totalPages,
  onPageChange,
  totalItems,
  pageSize,
  className,
}) => {
  if (totalPages <= 1 && !totalItems) return null;

  const getPageNumbers = () => {
    const pages: (number | string)[] = [];
    if (totalPages <= 7) {
      for (let i = 1; i <= totalPages; i++) pages.push(i);
    } else {
      pages.push(1);
      if (currentPage > 3) pages.push('...');
      const start = Math.max(2, currentPage - 1);
      const end = Math.min(totalPages - 1, currentPage + 1);
      for (let i = start; i <= end; i++) pages.push(i);
      if (currentPage < totalPages - 2) pages.push('...');
      pages.push(totalPages);
    }
    return pages;
  };

  const startItem = totalItems && pageSize ? (currentPage - 1) * pageSize + 1 : undefined;
  const endItem = totalItems && pageSize ? Math.min(currentPage * pageSize, totalItems) : undefined;

  return (
    <nav
      role="navigation"
      aria-label="Pagination"
      className={cn(
        'flex flex-col sm:flex-row items-center justify-between gap-4 py-3',
        className
      )}
    >
      {/* Summary */}
      <div className="text-sm text-neutral-500 dark:text-neutral-400">
        {totalItems !== undefined && startItem !== undefined && endItem !== undefined ? (
          <span>
            Showing <strong className="font-semibold text-neutral-900 dark:text-neutral-100">{startItem}</strong> to{' '}
            <strong className="font-semibold text-neutral-900 dark:text-neutral-100">{endItem}</strong> of{' '}
            <strong className="font-semibold text-neutral-900 dark:text-neutral-100">{totalItems}</strong> entries
          </span>
        ) : (
          <span>
            Page <strong className="font-semibold text-neutral-900 dark:text-neutral-100">{currentPage}</strong> of{' '}
            <strong className="font-semibold text-neutral-900 dark:text-neutral-100">{totalPages}</strong>
          </span>
        )}
      </div>

      {/* Buttons */}
      <div className="flex items-center gap-1.5">
        <Button
          variant="outline"
          size="sm"
          onClick={() => onPageChange(currentPage - 1)}
          disabled={currentPage <= 1}
          aria-label="Previous page"
        >
          <ChevronLeft className="h-4 w-4" />
          <span className="sr-only sm:not-sr-only sm:ml-1">Previous</span>
        </Button>

        <div className="flex items-center gap-1">
          {getPageNumbers().map((page, idx) => {
            if (page === '...') {
              return (
                <span key={`ellipsis-${idx}`} className="px-2 text-neutral-400">
                  ...
                </span>
              );
            }
            const isCurrent = page === currentPage;
            return (
              <button
                key={`page-${page}`}
                type="button"
                onClick={() => onPageChange(Number(page))}
                aria-current={isCurrent ? 'page' : undefined}
                className={cn(
                  'h-8 min-w-[2rem] px-2 text-xs font-medium rounded-lg transition-colors',
                  isCurrent
                    ? 'bg-[#6A0DAD] text-white shadow-sm'
                    : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'
                )}
              >
                {page}
              </button>
            );
          })}
        </div>

        <Button
          variant="outline"
          size="sm"
          onClick={() => onPageChange(currentPage + 1)}
          disabled={currentPage >= totalPages}
          aria-label="Next page"
        >
          <span className="sr-only sm:not-sr-only sm:mr-1">Next</span>
          <ChevronRight className="h-4 w-4" />
        </Button>
      </div>
    </nav>
  );
};
