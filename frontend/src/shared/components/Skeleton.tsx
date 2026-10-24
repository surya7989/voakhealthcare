import React from 'react';
import { cn } from '../utils/cn';

export interface SkeletonProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: 'text' | 'circular' | 'rectangular';
  width?: string | number;
  height?: string | number;
}

export const Skeleton: React.FC<SkeletonProps> = ({
  variant = 'text',
  width,
  height,
  className,
  style,
  ...props
}) => {
  const customStyles: React.CSSProperties = {
    ...style,
    ...(width !== undefined ? { width: typeof width === 'number' ? `${width}px` : width } : {}),
    ...(height !== undefined ? { height: typeof height === 'number' ? `${height}px` : height } : {}),
  };

  return (
    <div
      aria-hidden="true"
      className={cn(
        'animate-pulse bg-neutral-200 dark:bg-neutral-800',
        variant === 'circular' && 'rounded-full',
        variant === 'rectangular' && 'rounded-xl',
        variant === 'text' && 'h-4 w-full rounded',
        className
      )}
      style={customStyles}
      {...props}
    />
  );
};
