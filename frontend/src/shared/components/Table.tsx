import React from 'react';
import { ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-react';
import { cn } from '../utils/cn';

export interface TableProps extends React.TableHTMLAttributes<HTMLTableElement> {
  containerClassName?: string;
}

export const Table = React.forwardRef<HTMLTableElement, TableProps>(
  ({ className, containerClassName, ...props }, ref) => (
    <div
      className={cn(
        'relative w-full overflow-auto rounded-xl border border-voak-border bg-white',
        containerClassName
      )}
    >
      <table ref={ref} className={cn('w-full caption-bottom text-sm text-left', className)} {...props} />
    </div>
  )
);
Table.displayName = 'Table';

export const TableHeader = React.forwardRef<
  HTMLTableSectionElement,
  React.HTMLAttributes<HTMLTableSectionElement>
>(({ className, ...props }, ref) => (
  <thead
    ref={ref}
    className={cn(
      'bg-voak-canvas/60 border-b border-voak-border text-xs uppercase tracking-wider text-voak-muted font-semibold',
      className
    )}
    {...props}
  />
));
TableHeader.displayName = 'TableHeader';

export const TableBody = React.forwardRef<
  HTMLTableSectionElement,
  React.HTMLAttributes<HTMLTableSectionElement>
>(({ className, ...props }, ref) => (
  <tbody
    ref={ref}
    className={cn('divide-y divide-voak-border bg-white', className)}
    {...props}
  />
));
TableBody.displayName = 'TableBody';

export const TableRow = React.forwardRef<
  HTMLTableRowElement,
  React.HTMLAttributes<HTMLTableRowElement>
>(({ className, ...props }, ref) => (
  <tr
    ref={ref}
    className={cn(
      'transition-colors hover:bg-voak-canvas/40 data-[state=selected]:bg-voak-purple-light/40',
      className
    )}
    {...props}
  />
));
TableRow.displayName = 'TableRow';

export interface TableHeadProps extends React.ThHTMLAttributes<HTMLTableCellElement> {
  sortDirection?: 'asc' | 'desc' | null;
  onSort?: () => void;
}

export const TableHead = React.forwardRef<HTMLTableCellElement, TableHeadProps>(
  ({ className, children, sortDirection, onSort, ...props }, ref) => {
    const isSortable = typeof onSort === 'function';
    const ariaSort =
      sortDirection === 'asc'
        ? 'ascending'
        : sortDirection === 'desc'
        ? 'descending'
        : isSortable
        ? 'none'
        : undefined;

    return (
      <th
        ref={ref}
        aria-sort={ariaSort}
        className={cn(
          'h-11 px-4 text-left align-middle font-semibold text-voak-navy select-none',
          isSortable && 'cursor-pointer hover:text-voak-purple transition-colors',
          className
        )}
        onClick={onSort}
        onKeyDown={(e) => {
          if (isSortable && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            onSort();
          }
        }}
        tabIndex={isSortable ? 0 : undefined}
        {...props}
      >
        <div className="inline-flex items-center gap-1.5">
          <span>{children}</span>
          {isSortable && (
            <span className="text-voak-muted flex-shrink-0" aria-hidden="true">
              {sortDirection === 'asc' ? (
                <ChevronUp className="h-4 w-4 text-voak-purple" />
              ) : sortDirection === 'desc' ? (
                <ChevronDown className="h-4 w-4 text-voak-purple" />
              ) : (
                <ChevronsUpDown className="h-3.5 w-3.5 opacity-60" />
              )}
            </span>
          )}
        </div>
      </th>
    );
  }
);
TableHead.displayName = 'TableHead';

export const TableCell = React.forwardRef<
  HTMLTableCellElement,
  React.TdHTMLAttributes<HTMLTableCellElement>
>(({ className, ...props }, ref) => (
  <td
    ref={ref}
    className={cn('p-4 align-middle text-voak-navy font-normal', className)}
    {...props}
  />
));
TableCell.displayName = 'TableCell';

