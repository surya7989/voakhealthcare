import React, { useEffect, useRef } from 'react';
import { X } from 'lucide-react';
import { cn } from '../utils/cn';

export interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title?: React.ReactNode;
  description?: React.ReactNode;
  children: React.ReactNode;
  footer?: React.ReactNode;
  size?: 'sm' | 'md' | 'lg' | 'xl';
  closeOnBackdropClick?: boolean;
  className?: string;
}

const sizeClasses = {
  sm: 'max-w-md',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl',
};

export const Modal: React.FC<ModalProps> = ({
  isOpen,
  onClose,
  title,
  description,
  children,
  footer,
  size = 'md',
  closeOnBackdropClick = true,
  className,
}) => {
  const dialogRef = useRef<HTMLDivElement>(null);
  const previousActiveElement = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (isOpen) {
      previousActiveElement.current = document.activeElement as HTMLElement;
      // Focus the dialog
      if (dialogRef.current) {
        dialogRef.current.focus();
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

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
      role="dialog"
      aria-modal="true"
      aria-labelledby={title ? 'modal-title' : undefined}
      aria-describedby={description ? 'modal-description' : undefined}
    >
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-neutral-900/60 backdrop-blur-sm transition-opacity"
        onClick={() => {
          if (closeOnBackdropClick) onClose();
        }}
        aria-hidden="true"
        data-testid="modal-backdrop"
      />

      {/* Dialog container */}
      <div
        ref={dialogRef}
        tabIndex={-1}
        className={cn(
          'relative w-full rounded-2xl bg-white p-6 shadow-2xl transition-all focus:outline-none dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800',
          sizeClasses[size],
          className
        )}
      >
        {/* Header */}
        {(title || description) && (
          <div className="mb-4 pr-8">
            {title && (
              <h2 id="modal-title" className="text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-50">
                {title}
              </h2>
            )}
            {description && (
              <p id="modal-description" className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                {description}
              </p>
            )}
          </div>
        )}

        {/* Close Button */}
        <button
          type="button"
          onClick={onClose}
          className="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200 transition-colors"
          aria-label="Close dialog"
        >
          <X className="h-5 w-5" />
        </button>

        {/* Body */}
        <div className="text-sm text-neutral-700 dark:text-neutral-300">
          {children}
        </div>

        {/* Footer */}
        {footer && (
          <div className="mt-6 flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-neutral-100 dark:border-neutral-800">
            {footer}
          </div>
        )}
      </div>
    </div>
  );
};
