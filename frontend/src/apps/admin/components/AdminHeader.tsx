import React from 'react';
import { Menu, Activity } from 'lucide-react';
import { UserDropdown } from './UserDropdown';

interface AdminHeaderProps {
  onMobileMenuToggle: () => void;
  breadcrumbs?: React.ReactNode;
}

export const AdminHeader: React.FC<AdminHeaderProps> = ({
  onMobileMenuToggle,
  breadcrumbs,
}) => {
  return (
    <header className="sticky top-0 z-30 flex h-16 w-full items-center justify-between border-b border-neutral-200 dark:border-neutral-800 bg-white/95 dark:bg-neutral-900/95 backdrop-blur px-4 sm:px-6">
      {/* Left side: Hamburger button + Breadcrumbs */}
      <div className="flex items-center gap-3 min-w-0">
        <button
          type="button"
          onClick={onMobileMenuToggle}
          className="lg:hidden p-2 rounded-xl text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
          aria-label="Open navigation menu"
        >
          <Menu className="h-5 w-5" />
        </button>

        {breadcrumbs && (
          <div className="hidden sm:block truncate">
            {breadcrumbs}
          </div>
        )}
      </div>

      {/* Right side: Status indicator + User dropdown */}
      <div className="flex items-center gap-3 sm:gap-4 shrink-0">
        <div className="hidden md:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-[11px] font-medium text-emerald-700 dark:text-emerald-300">
          <Activity className="h-3 w-3 text-emerald-500 animate-pulse" />
          <span>API Connected</span>
        </div>

        <UserDropdown />
      </div>
    </header>
  );
};
