import React from 'react';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from './Table';
import { Pagination } from './Pagination';
import { Skeleton } from './Skeleton';
import { EmptyState } from './EmptyState';
import { ErrorState } from './ErrorState';
import { Checkbox } from './Checkbox';
import { cn } from '../utils/cn';

export interface DataTableColumn<T> {
  key: string;
  header: React.ReactNode;
  render?: (row: T, index: number) => React.ReactNode;
  sortable?: boolean;
  align?: 'left' | 'center' | 'right';
  className?: string;
  headerClassName?: string;
}

export interface DataTableProps<T> {
  data: T[];
  columns: DataTableColumn<T>[];
  getRowId?: (row: T, index: number) => string | number;
  sortKey?: string;
  sortDirection?: 'asc' | 'desc' | null;
  onSort?: (key: string) => void;
  isLoading?: boolean;
  loadingRowCount?: number;
  error?: string | null;
  onRetry?: () => void;
  emptyTitle?: string;
  emptyDescription?: string;
  emptyAction?: React.ReactNode;
  selectable?: boolean;
  selectedIds?: (string | number)[];
  onSelectionChange?: (selectedIds: (string | number)[]) => void;
  onRowClick?: (row: T) => void;
  pagination?: {
    currentPage: number;
    totalPages: number;
    onPageChange: (page: number) => void;
    totalItems?: number;
    pageSize?: number;
  };
  className?: string;
  containerClassName?: string;
}

export function DataTable<T extends object>({
  data,
  columns,
  getRowId = (row: T, idx: number) =>
    'id' in row && (row as { id?: unknown }).id !== undefined && (row as { id?: unknown }).id !== null
      ? ((row as { id?: string | number }).id as string | number)
      : idx,
  sortKey,
  sortDirection,
  onSort,
  isLoading = false,
  loadingRowCount = 5,
  error,
  onRetry,
  emptyTitle = 'No data available',
  emptyDescription = 'There are no records to display at this time.',
  emptyAction,
  selectable = false,
  selectedIds = [],
  onSelectionChange,
  onRowClick,
  pagination,
  className,
  containerClassName,
}: DataTableProps<T>) {
  const isAllSelected =
    data.length > 0 &&
    data.every((row, idx) => selectedIds.includes(getRowId(row, idx)));

  const isSomeSelected =
    data.some((row, idx) => selectedIds.includes(getRowId(row, idx))) &&
    !isAllSelected;

  const handleSelectAll = (checked: boolean) => {
    if (!onSelectionChange) return;
    if (checked) {
      const allIds = data.map((row, idx) => getRowId(row, idx));
      onSelectionChange(Array.from(new Set([...selectedIds, ...allIds])));
    } else {
      const currentIds = new Set(data.map((row, idx) => getRowId(row, idx)));
      onSelectionChange(selectedIds.filter((id) => !currentIds.has(id)));
    }
  };

  const handleSelectRow = (rowId: string | number, checked: boolean) => {
    if (!onSelectionChange) return;
    if (checked) {
      onSelectionChange([...selectedIds, rowId]);
    } else {
      onSelectionChange(selectedIds.filter((id) => id !== rowId));
    }
  };

  const alignClasses = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
  };

  const totalCols = columns.length + (selectable ? 1 : 0);

  return (
    <div className={cn('flex flex-col gap-4', containerClassName)}>
      <Table className={className}>
        <TableHeader>
          <TableRow>
            {selectable && (
              <TableHead className="w-12 px-4">
                <Checkbox
                  checked={isAllSelected}
                  indeterminate={isSomeSelected}
                  onChange={(e) => handleSelectAll(e.target.checked)}
                  aria-label="Select all rows"
                />
              </TableHead>
            )}
            {columns.map((col) => (
              <TableHead
                key={col.key}
                sortDirection={sortKey === col.key ? sortDirection : null}
                onSort={col.sortable && onSort ? () => onSort(col.key) : undefined}
                className={cn(col.align ? alignClasses[col.align] : 'text-left', col.headerClassName)}
              >
                {col.header}
              </TableHead>
            ))}
          </TableRow>
        </TableHeader>

        <TableBody>
          {error ? (
            <TableRow>
              <TableCell colSpan={totalCols} className="p-8">
                <ErrorState
                  message={error}
                  onRetry={onRetry}
                />
              </TableCell>
            </TableRow>
          ) : isLoading ? (
            Array.from({ length: loadingRowCount }).map((_, rIdx) => (
              <TableRow key={`loading-row-${rIdx}`}>
                {selectable && (
                  <TableCell className="w-12 px-4">
                    <Skeleton className="h-4 w-4 rounded" />
                  </TableCell>
                )}
                {columns.map((col, cIdx) => (
                  <TableCell key={`loading-col-${cIdx}`} className={col.className}>
                    <Skeleton className="h-5 w-3/4 rounded" />
                  </TableCell>
                ))}
              </TableRow>
            ))
          ) : data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={totalCols} className="p-8">
                <EmptyState
                  title={emptyTitle}
                  description={emptyDescription}
                  action={emptyAction}
                />
              </TableCell>
            </TableRow>
          ) : (
            data.map((row, rIdx) => {
              const rowId = getRowId(row, rIdx);
              const isSelected = selectedIds.includes(rowId);

              return (
                <TableRow
                  key={String(rowId)}
                  data-state={isSelected ? 'selected' : undefined}
                  onClick={() => onRowClick?.(row)}
                  className={cn(onRowClick && 'cursor-pointer hover:bg-voak-canvas/60')}
                >
                  {selectable && (
                    <TableCell
                      className="w-12 px-4"
                      onClick={(e) => e.stopPropagation()}
                    >
                      <Checkbox
                        checked={isSelected}
                        onChange={(e) => handleSelectRow(rowId, e.target.checked)}
                        aria-label={`Select row ${rIdx + 1}`}
                      />
                    </TableCell>
                  )}
                  {columns.map((col) => {
                    const rowRecord = row as Record<string, unknown>;
                    const cellValue = col.render ? col.render(row, rIdx) : (rowRecord[col.key] as React.ReactNode);
                    return (
                      <TableCell
                        key={col.key}
                        className={cn(col.align ? alignClasses[col.align] : 'text-left', col.className)}
                      >
                        {cellValue !== undefined && cellValue !== null ? cellValue : '-'}
                      </TableCell>
                    );
                  })}
                </TableRow>
              );
            })
          )}
        </TableBody>
      </Table>

      {pagination && !error && !isLoading && data.length > 0 && (
        <Pagination {...pagination} />
      )}
    </div>
  );
}
