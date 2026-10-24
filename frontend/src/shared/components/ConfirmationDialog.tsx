import React from 'react';
import { AlertTriangle, Info } from 'lucide-react';
import { Modal } from './Modal';
import { Button } from './Button';
import { cn } from '../utils/cn';

export interface ConfirmationDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  message: React.ReactNode;
  confirmText?: string;
  cancelText?: string;
  variant?: 'danger' | 'warning' | 'primary';
  isLoading?: boolean;
  icon?: React.ReactNode;
  className?: string;
}

export const ConfirmationDialog: React.FC<ConfirmationDialogProps> = ({
  isOpen,
  onClose,
  onConfirm,
  title,
  message,
  confirmText = 'Confirm',
  cancelText = 'Cancel',
  variant = 'danger',
  isLoading = false,
  icon,
  className,
}) => {
  const defaultIcon =
    variant === 'danger' || variant === 'warning' ? (
      <AlertTriangle
        className={cn(
          'h-6 w-6',
          variant === 'danger' ? 'text-state-error' : 'text-state-warning'
        )}
      />
    ) : (
      <Info className="h-6 w-6 text-voak-purple" />
    );

  const iconBg =
    variant === 'danger'
      ? 'bg-state-error-bg border-state-error/20'
      : variant === 'warning'
      ? 'bg-state-warning-bg border-state-warning/20'
      : 'bg-voak-purple-light border-voak-purple/20';

  const footer = (
    <>
      <Button
        variant="ghost"
        onClick={onClose}
        disabled={isLoading}
      >
        {cancelText}
      </Button>
      <Button
        variant={variant === 'danger' ? 'danger' : 'primary'}
        onClick={onConfirm}
        isLoading={isLoading}
      >
        {confirmText}
      </Button>
    </>
  );

  return (
    <Modal
      isOpen={isOpen}
      onClose={isLoading ? () => {} : onClose}
      size="sm"
      footer={footer}
      className={className}
    >
      <div className="flex items-start gap-4 pt-2">
        <div
          className={cn(
            'flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl border',
            iconBg
          )}
          aria-hidden="true"
        >
          {icon || defaultIcon}
        </div>
        <div className="flex-1">
          <h3 className="text-lg font-bold text-voak-navy">{title}</h3>
          <div className="mt-1.5 text-sm text-voak-muted">{message}</div>
        </div>
      </div>
    </Modal>
  );
};
