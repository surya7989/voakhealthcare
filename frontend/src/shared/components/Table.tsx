import React from 'react';
import { cn } from '../utils/cn';

export interface TableProps extends React.TableHTMLAttributes<HTMLTableElement> {
  containerClassName?: string;
}

export const Table = React.forwardRef<HTMLTableElement, TableProps>(
  ({ className, containerClassName, ...props }, ref) => (
    <div className={cn('relative w-full overflow-auto rounded-xl border border-neutral-200 dark:border-neutral-800', containerClassName)}>
      <table ref={ref} className={cn('w-full caption-bottom text-sm text-left', className)} {...props} />
    </div>
  )
);
Table.displayName = 'Table';

export const TableHeader = React.forwardRef<
  HTMLTableSectionElement,
  React.HTMLAttributes<HTMLTableSectionElement>
>(({ className, ...props }, ref) => (
  <thead ref={ref} className={cn('bg-neutral-50/80 dark:bg-neutral-900/80 border-b border-neutral-200 dark:border-neutral-800 text-xs uppercase tracking-wider text-neutral-500 dark:text-neutral-400', className)} {...props} />
));
TableHeader.displayName = 'TableHeader';

export const TableBody = React.forwardRef<
  HTMLTableSectionElement,
  React.HTMLAttributes<HTMLTableSectionElement>
>(({ className, ...props }, ref) => (
  <tbody ref={ref} className={cn('divide-y divide-neutral-200 dark:divide-neutral-800 bg-white dark:bg-neutral-900', className)} {...props} />
));
TableBody.displayName = 'TableBody';

export const TableRow = React.forwardRef<
  HTMLTableRowElement,
  React.HTMLAttributes<HTMLTableRowElement>
>(({ className, ...props }, ref) => (
  <tr
    ref={ref}
    className={cn(
      'transition-colors hover:bg-neutral-50/70 dark:hover:bg-neutral-800/50 data-[state=selected]:bg-neutral-100',
      className
    )}
    {...props}
  />
));
TableRow.displayName = 'TableRow';

export const TableHead = React.forwardRef<
  HTMLTableCellElement,
  React.ThHTMLAttributes<HTMLTableCellElement>
>(({ className, ...props }, ref) => (
  <th
    ref={ref}
    className={cn('h-11 px-4 text-left align-middle font-semibold text-neutral-600 dark:text-neutral-300', className)}
    {...props}
  />
));
TableHead.displayName = 'TableHead';

export const TableCell = React.forwardRef<
  HTMLTableCellElement,
  React.TdHTMLAttributes<HTMLTableCellElement>
>(({ className, ...props }, ref) => (
  <td ref={ref} className={cn('p-4 align-middle text-neutral-700 dark:text-neutral-200', className)} {...props} />
));
TableCell.displayName = 'TableCell';
