import React, { createContext, useContext, useState, useId } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '../utils/cn';

interface AccordionContextValue {
  expandedItems: string[];
  toggleItem: (value: string) => void;
  type: 'single' | 'multiple';
}

const AccordionContext = createContext<AccordionContextValue | null>(null);

export interface AccordionProps {
  type?: 'single' | 'multiple';
  defaultValue?: string | string[];
  value?: string | string[];
  onChange?: (value: string | string[]) => void;
  children: React.ReactNode;
  className?: string;
}

export const Accordion: React.FC<AccordionProps> = ({
  type = 'single',
  defaultValue,
  value: controlledValue,
  onChange,
  children,
  className,
}) => {
  const getInitial = (): string[] => {
    if (defaultValue) {
      return Array.isArray(defaultValue) ? defaultValue : [defaultValue];
    }
    return [];
  };

  const [uncontrolledItems, setUncontrolledItems] = useState<string[]>(getInitial);
  const isControlled = controlledValue !== undefined;
  const currentItems = isControlled
    ? Array.isArray(controlledValue)
      ? controlledValue
      : [controlledValue]
    : uncontrolledItems;

  const toggleItem = (itemValue: string) => {
    let next: string[];
    if (type === 'single') {
      next = currentItems.includes(itemValue) ? [] : [itemValue];
    } else {
      next = currentItems.includes(itemValue)
        ? currentItems.filter((v) => v !== itemValue)
        : [...currentItems, itemValue];
    }

    if (!isControlled) {
      setUncontrolledItems(next);
    }

    onChange?.(type === 'single' ? next[0] || '' : next);
  };

  return (
    <AccordionContext.Provider
      value={{
        expandedItems: currentItems,
        toggleItem,
        type,
      }}
    >
      <div className={cn('divide-y divide-voak-border rounded-2xl border border-voak-border bg-white', className)}>
        {children}
      </div>
    </AccordionContext.Provider>
  );
};

interface AccordionItemContextValue {
  value: string;
  isExpanded: boolean;
  headerId: string;
  panelId: string;
}

const AccordionItemContext = createContext<AccordionItemContextValue | null>(null);

export interface AccordionItemProps extends React.HTMLAttributes<HTMLDivElement> {
  value: string;
}

export const AccordionItem: React.FC<AccordionItemProps> = ({
  value,
  children,
  className,
  ...props
}) => {
  const context = useContext(AccordionContext);
  if (!context) throw new Error('AccordionItem must be used within Accordion');

  const isExpanded = context.expandedItems.includes(value);
  const baseId = useId();
  const headerId = `acc-header-${baseId}`;
  const panelId = `acc-panel-${baseId}`;

  return (
    <AccordionItemContext.Provider value={{ value, isExpanded, headerId, panelId }}>
      <div className={cn('overflow-hidden first:rounded-t-2xl last:rounded-b-2xl', className)} {...props}>
        {children}
      </div>
    </AccordionItemContext.Provider>
  );
};

export interface AccordionTriggerProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  icon?: React.ReactNode;
}

export const AccordionTrigger: React.FC<AccordionTriggerProps> = ({
  children,
  icon,
  className,
  ...props
}) => {
  const accContext = useContext(AccordionContext);
  const itemContext = useContext(AccordionItemContext);
  if (!accContext || !itemContext) {
    throw new Error('AccordionTrigger must be used within AccordionItem');
  }

  const { isExpanded, value, headerId, panelId } = itemContext;

  return (
    <h3 className="m-0 flex">
      <button
        id={headerId}
        type="button"
        aria-expanded={isExpanded}
        aria-controls={panelId}
        onClick={() => accContext.toggleItem(value)}
        className={cn(
          'flex flex-1 items-center justify-between p-4 text-left text-sm font-semibold text-voak-navy transition-colors',
          'hover:bg-voak-canvas/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple select-none',
          className
        )}
        {...props}
      >
        <div className="flex items-center gap-3">
          {icon && <span className="text-voak-muted">{icon}</span>}
          <span>{children}</span>
        </div>
        <ChevronDown
          className={cn(
            'h-4 w-4 text-voak-muted transition-transform duration-200',
            isExpanded && 'rotate-180 text-voak-purple'
          )}
          aria-hidden="true"
        />
      </button>
    </h3>
  );
};

export type AccordionContentProps = React.HTMLAttributes<HTMLDivElement>;

export const AccordionContent: React.FC<AccordionContentProps> = ({
  children,
  className,
  ...props
}) => {
  const itemContext = useContext(AccordionItemContext);
  if (!itemContext) throw new Error('AccordionContent must be used within AccordionItem');

  const { isExpanded, headerId, panelId } = itemContext;

  if (!isExpanded) return null;

  return (
    <div
      id={panelId}
      role="region"
      aria-labelledby={headerId}
      className={cn('px-4 pb-4 pt-1 text-sm text-voak-muted leading-relaxed', className)}
      {...props}
    >
      {children}
    </div>
  );
};
