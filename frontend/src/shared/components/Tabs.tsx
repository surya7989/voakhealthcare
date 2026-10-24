import React, { createContext, useContext, useId } from 'react';
import { cn } from '../utils/cn';

interface TabsContextValue {
  activeTab: string;
  setActiveTab: (tab: string) => void;
  baseId: string;
}

const TabsContext = createContext<TabsContextValue | null>(null);

export interface TabsProps {
  value?: string;
  defaultValue?: string;
  onChange?: (value: string) => void;
  children: React.ReactNode;
  className?: string;
}

export const Tabs: React.FC<TabsProps> = ({
  value: controlledValue,
  defaultValue,
  onChange,
  children,
  className,
}) => {
  const [uncontrolledValue, setUncontrolledValue] = React.useState<string>(defaultValue || '');
  const isControlled = controlledValue !== undefined;
  const activeTab = isControlled ? controlledValue : uncontrolledValue;
  const baseId = useId();

  const handleSetActiveTab = (tab: string) => {
    if (!isControlled) {
      setUncontrolledValue(tab);
    }
    onChange?.(tab);
  };

  return (
    <TabsContext.Provider
      value={{
        activeTab,
        setActiveTab: handleSetActiveTab,
        baseId,
      }}
    >
      <div className={cn('flex flex-col gap-4', className)}>{children}</div>
    </TabsContext.Provider>
  );
};

export interface TabListProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: 'underline' | 'pills';
}

export const TabList: React.FC<TabListProps> = ({
  variant = 'underline',
  className,
  children,
  ...props
}) => {
  const handleKeyDown = (e: React.KeyboardEvent<HTMLDivElement>) => {
    const tabs = Array.from(
      e.currentTarget.querySelectorAll<HTMLButtonElement>('button[role="tab"]:not(:disabled)')
    );
    if (!tabs.length) return;

    const currentIndex = tabs.indexOf(document.activeElement as HTMLButtonElement);
    if (currentIndex === -1) return;

    let nextIndex = -1;

    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
      e.preventDefault();
      nextIndex = currentIndex < tabs.length - 1 ? currentIndex + 1 : 0;
    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
      e.preventDefault();
      nextIndex = currentIndex > 0 ? currentIndex - 1 : tabs.length - 1;
    } else if (e.key === 'Home') {
      e.preventDefault();
      nextIndex = 0;
    } else if (e.key === 'End') {
      e.preventDefault();
      nextIndex = tabs.length - 1;
    }

    if (nextIndex !== -1 && tabs[nextIndex]) {
      tabs[nextIndex].focus();
      tabs[nextIndex].click();
    }
  };

  return (
    <div
      role="tablist"
      aria-orientation="horizontal"
      onKeyDown={handleKeyDown}
      className={cn(
        'flex items-center gap-2 overflow-x-auto select-none',
        variant === 'underline' && 'border-b border-voak-border',
        variant === 'pills' && 'rounded-2xl bg-voak-canvas p-1 border border-voak-border/60',
        className
      )}
      {...props}
    >
      {children}
    </div>
  );
};

export interface TabProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  value: string;
  variant?: 'underline' | 'pills';
  badge?: React.ReactNode;
}

export const Tab: React.FC<TabProps> = ({
  value,
  variant = 'underline',
  badge,
  className,
  children,
  ...props
}) => {
  const context = useContext(TabsContext);
  if (!context) throw new Error('Tab must be used within Tabs');

  const { activeTab, setActiveTab, baseId } = context;
  const isSelected = activeTab === value;
  const tabId = `${baseId}-tab-${value}`;
  const panelId = `${baseId}-panel-${value}`;

  const underlineStyles = cn(
    'border-b-2 px-4 py-2.5 text-sm font-medium transition-colors -mb-px',
    isSelected
      ? 'border-voak-purple text-voak-purple font-semibold'
      : 'border-transparent text-voak-muted hover:border-voak-border hover:text-voak-navy'
  );

  const pillStyles = cn(
    'rounded-xl px-4 py-2 text-sm font-medium transition-all',
    isSelected
      ? 'bg-white text-voak-purple font-semibold shadow-sm'
      : 'text-voak-muted hover:text-voak-navy'
  );

  return (
    <button
      id={tabId}
      type="button"
      role="tab"
      aria-selected={isSelected}
      aria-controls={panelId}
      tabIndex={isSelected ? 0 : -1}
      onClick={() => setActiveTab(value)}
      className={cn(
        'inline-flex items-center gap-2 whitespace-nowrap focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple focus-visible:ring-offset-2',
        variant === 'underline' ? underlineStyles : pillStyles,
        className
      )}
      {...props}
    >
      <span>{children}</span>
      {badge && <span className="flex-shrink-0">{badge}</span>}
    </button>
  );
};

export type TabPanelsProps = React.HTMLAttributes<HTMLDivElement>;

export const TabPanels: React.FC<TabPanelsProps> = ({ className, children, ...props }) => (
  <div className={cn('w-full', className)} {...props}>
    {children}
  </div>
);

export interface TabPanelProps extends React.HTMLAttributes<HTMLDivElement> {
  value: string;
}

export const TabPanel: React.FC<TabPanelProps> = ({
  value,
  className,
  children,
  ...props
}) => {
  const context = useContext(TabsContext);
  if (!context) throw new Error('TabPanel must be used within Tabs');

  const { activeTab, baseId } = context;
  const isSelected = activeTab === value;
  const tabId = `${baseId}-tab-${value}`;
  const panelId = `${baseId}-panel-${value}`;

  if (!isSelected) return null;

  return (
    <div
      id={panelId}
      role="tabpanel"
      aria-labelledby={tabId}
      tabIndex={0}
      className={cn('focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-voak-purple rounded-xl', className)}
      {...props}
    >
      {children}
    </div>
  );
};
