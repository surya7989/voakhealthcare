import React, { useEffect, useRef } from 'react';
import { X } from 'lucide-react';
import { cn } from '../utils/cn';

export interface DrawerProps {
  isOpen: boolean;
  onClose: () => void;
  title?: React.ReactNode;
  description?: React.ReactNode;
  children: React.ReactNode;
  footer?: React.ReactNode;
  position?: 'left' | 'right';
  size?: 'sm' | 'md' | 'lg' | 'xl' | 'full';
  closeOnBackdropClick?: boolean;
  className?: string;
}

const sizeClasses = {
  sm: 'max-w-xs',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-2xl',
  full: 'max-w-full',
};

export const Drawer: React.FC<DrawerProps> = ({
  isOpen,
  onClose,
  title,
  description,
  children,
  footer,
  position = 'right',
  size = 'md',
  closeOnBackdropClick = true,
  className,
}) => {
  const drawerRef = useRef<HTMLDivElement>(null);
  const previousActiveElement = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (isOpen) {
      previousActiveElement.current = document.activeElement as HTMLElement;
      if (drawerRef.current) {
        drawerRef.current.focus();
      }

      const handleKeyDown = (e: KeyboardEvent) => {
        if (e.key === 'Escape') {
          onClose();
        }
      };

      document.addEventListener('keydown', handleKeyDown);
      document.body.style.overflow = 'hidden';

      return () => {
        document.removeEventListener('keydown', handleKeyDown);
        document.body.style.overflow = '';
        if (previousActiveElement.current) {
          previousActiveElement.current.focus();
        }
      };
    }
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  const isRight = position === 'right';

  return (
    <div
      className="fixed inset-0 z-50 flex"
      role="dialog"
      aria-modal="true"
      aria-labelledby={title ? 'drawer-title' : undefined}
      aria-describedby={description ? 'drawer-description' : undefined}
    >
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-neutral-900/60 backdrop-blur-sm transition-opacity"
        onClick={() => {
          if (closeOnBackdropClick) onClose();
        }}
        aria-hidden="true"
        data-testid="drawer-backdrop"
      />

      {/* Drawer Panel */}
      <div
        ref={drawerRef}
        tabIndex={-1}
        className={cn(
          'relative z-10 flex h-full w-full flex-col bg-white shadow-2xl transition-transform duration-300 ease-in-out focus:outline-none border-neutral-200',
          isRight ? 'ml-auto border-l' : 'mr-auto border-r',
          sizeClasses[size],
          className
        )}
      >
        {/* Header */}
        <div className="flex items-start justify-between border-b border-voak-border p-6">
          <div className="pr-4">
            {title && (
              <h2 id="drawer-title" className="text-xl font-bold tracking-tight text-voak-navy">
                {title}
              </h2>
            )}
            {description && (
              <p id="drawer-description" className="mt-1 text-sm text-voak-muted">
                {description}
              </p>
            )}
          </div>
          <button
            type="button"
            onClick={onClose}
            className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-voak-muted hover:bg-voak-canvas hover:text-voak-navy transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple"
            aria-label="Close panel"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Content Body */}
        <div className="flex-1 overflow-y-auto p-6 text-sm text-voak-navy">
          {children}
        </div>

        {/* Footer */}
        {footer && (
          <div className="flex flex-wrap items-center justify-end gap-3 border-t border-voak-border p-4 bg-voak-canvas/30">
            {footer}
          </div>
        )}
      </div>
    </div>
  );
};
