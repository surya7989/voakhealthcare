import React, { useState } from 'react';
import { AdminSidebar } from './AdminSidebar';
import { AdminHeader } from './AdminHeader';
import { ErrorBoundary } from '../../../shared/components/ErrorBoundary';

interface AdminLayoutProps {
  children: React.ReactNode;
  breadcrumbs?: React.ReactNode;
}

export const AdminLayout: React.FC<AdminLayoutProps> = ({ children, breadcrumbs }) => {
  const [isMobileOpen, setIsMobileOpen] = useState(false);

  return (
    <div className="flex min-h-screen bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100">
      {/* Sidebar Navigation */}
      <AdminSidebar
        isMobileOpen={isMobileOpen}
        onMobileClose={() => setIsMobileOpen(false)}
      />

      {/* Main Content Area */}
      <div className="flex flex-1 flex-col min-w-0">
        <AdminHeader
          onMobileMenuToggle={() => setIsMobileOpen((prev) => !prev)}
          breadcrumbs={breadcrumbs}
        />

        <main className="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
          <ErrorBoundary>
            {children}
          </ErrorBoundary>
        </main>
      </div>
    </div>
  );
};
