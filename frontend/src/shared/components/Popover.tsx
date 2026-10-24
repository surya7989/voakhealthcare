import React, { useState, useRef, useEffect, useId } from 'react';
import { cn } from '../utils/cn';

export interface PopoverProps {
  trigger: React.ReactElement;
  children: React.ReactNode;
  align?: 'left' | 'right';
  className?: string;
  isOpen?: boolean;
  onOpenChange?: (open: boolean) => void;
}

export const Popover: React.FC<PopoverProps> = ({
  trigger,
  children,
  align = 'left',
  className,
  isOpen: controlledOpen,
  onOpenChange,
}) => {
  const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
  const isControlled = controlledOpen !== undefined;
  const isOpen = isControlled ? controlledOpen : uncontrolledOpen;

  const containerRef = useRef<HTMLDivElement>(null);
  const popoverId = useId();

  const setOpen = React.useCallback(
    (open: boolean) => {
      if (!isControlled) {
        setUncontrolledOpen(open);
      }
      onOpenChange?.(open);
    },
    [isControlled, onOpenChange]
  );

  useEffect(() => {
    if (!isOpen) return;

    const handleClickOutside = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('keydown', handleKeyDown);

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen, setOpen]);

  const clonedTrigger = React.cloneElement(trigger, {
    'aria-haspopup': 'dialog',
    'aria-expanded': isOpen,
    'aria-controls': isOpen ? popoverId : undefined,
    onClick: (e: React.MouseEvent) => {
      trigger.props.onClick?.(e);
      setOpen(!isOpen);
    },
  });

  return (
    <div ref={containerRef} className="relative inline-block text-left">
      {clonedTrigger}

      {isOpen && (
        <div
          id={popoverId}
          role="dialog"
          className={cn(
            'absolute z-40 mt-2 min-w-[16rem] rounded-2xl border border-voak-border bg-white p-4 shadow-xl animate-in fade-in-50 zoom-in-95',
            align === 'right' ? 'right-0' : 'left-0',
            className
          )}
        >
          {children}
        </div>
      )}
    </div>
  );
};
