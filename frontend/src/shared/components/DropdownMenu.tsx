import React, { useState, useRef, useEffect, useId } from 'react';
import { cn } from '../utils/cn';

export interface DropdownMenuItemProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  icon?: React.ReactNode;
  destructive?: boolean;
  onSelect?: () => void;
}

export const DropdownMenuItem: React.FC<DropdownMenuItemProps> = ({
  icon,
  destructive = false,
  onSelect,
  className,
  children,
  onClick,
  disabled,
  ...props
}) => {
  const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
    if (disabled) return;
    onClick?.(e);
    onSelect?.();
  };

  return (
    <button
      type="button"
      role="menuitem"
      disabled={disabled}
      onClick={handleClick}
      className={cn(
        'group flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-xs font-medium transition-colors select-none',
        'focus-visible:outline-none focus:bg-voak-purple-light focus:text-voak-purple',
        destructive
          ? 'text-state-error hover:bg-state-error-bg hover:text-state-error'
          : 'text-voak-navy hover:bg-voak-canvas hover:text-voak-purple',
        disabled && 'cursor-not-allowed opacity-50 hover:bg-transparent hover:text-voak-navy',
        className
      )}
      {...props}
    >
      {icon && (
        <span
          className={cn(
            'flex-shrink-0 text-voak-muted group-hover:text-current',
            destructive && 'text-state-error'
          )}
          aria-hidden="true"
        >
          {icon}
        </span>
      )}
      <span className="flex-1 truncate">{children}</span>
    </button>
  );
};

export const DropdownMenuSeparator: React.FC<React.HTMLAttributes<HTMLDivElement>> = ({
  className,
  ...props
}) => (
  <div
    role="separator"
    className={cn('my-1 h-px bg-voak-border', className)}
    {...props}
  />
);

export const DropdownMenuLabel: React.FC<React.HTMLAttributes<HTMLDivElement>> = ({
  className,
  children,
  ...props
}) => (
  <div
    className={cn('px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-voak-muted select-none', className)}
    {...props}
  >
    {children}
  </div>
);

export interface DropdownMenuProps {
  trigger: React.ReactElement;
  children: React.ReactNode;
  align?: 'left' | 'right';
  className?: string;
}

export const DropdownMenu: React.FC<DropdownMenuProps> = ({
  trigger,
  children,
  align = 'right',
  className,
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const menuRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLElement | null>(null);
  const menuId = useId();

  const handleToggle = () => {
    setIsOpen((prev) => !prev);
  };

  const handleClose = () => {
    setIsOpen(false);
    triggerRef.current?.focus();
  };

  useEffect(() => {
    if (!isOpen) return;

    const handleClickOutside = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    };

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        handleClose();
      } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        const items = Array.from(
          menuRef.current?.querySelectorAll<HTMLButtonElement>('button[role="menuitem"]:not(:disabled)') || []
        );
        if (!items.length) return;

        const currentIndex = items.indexOf(document.activeElement as HTMLButtonElement);
        let nextIndex = 0;

        if (e.key === 'ArrowDown') {
          nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
        } else {
          nextIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
        }

        items[nextIndex]?.focus();
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('keydown', handleKeyDown);

    // Auto-focus first item on open
    const firstItem = menuRef.current?.querySelector<HTMLButtonElement>('button[role="menuitem"]:not(:disabled)');
    firstItem?.focus();

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen]);

  const clonedTrigger = React.cloneElement(trigger, {
    ref: (node: HTMLElement) => {
      triggerRef.current = node;
      // forward ref if original trigger had ref
      const origRef = (trigger as unknown as { ref?: React.Ref<HTMLElement> }).ref;
      if (typeof origRef === 'function') origRef(node);
      else if (origRef && 'current' in origRef) {
        (origRef as React.MutableRefObject<HTMLElement | null>).current = node;
      }
    },
    'aria-haspopup': 'menu',
    'aria-expanded': isOpen,
    'aria-controls': isOpen ? menuId : undefined,
    onClick: (e: React.MouseEvent) => {
      trigger.props.onClick?.(e);
      handleToggle();
    },
  });

  return (
    <div ref={containerRef} className="relative inline-block text-left">
      {clonedTrigger}

      {isOpen && (
        <div
          ref={menuRef}
          id={menuId}
          role="menu"
          aria-orientation="vertical"
          tabIndex={-1}
          className={cn(
            'absolute z-50 mt-1.5 min-w-[12rem] rounded-xl border border-voak-border bg-white p-1.5 shadow-lg animate-in fade-in-80 zoom-in-95',
            align === 'right' ? 'right-0' : 'left-0',
            className
          )}
          onClick={(e) => {
            // Close when an action item is clicked unless specified
            const target = e.target as HTMLElement;
            if (target.closest('button[role="menuitem"]')) {
              setIsOpen(false);
            }
          }}
        >
          {children}
        </div>
      )}
    </div>
  );
};
