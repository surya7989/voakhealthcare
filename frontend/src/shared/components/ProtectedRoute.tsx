import React from 'react';
import { useAuth } from '../../apps/admin/context/AuthContext';
import { Navigate } from '../router/Router';
import type { RoleSlug } from '../types/auth';

export interface ProtectedRouteProps {
  children: React.ReactNode;
  requiredPermission?: string | string[];
  requiredRole?: RoleSlug | RoleSlug[];
  redirectTo?: string;
}

export const ProtectedRoute: React.FC<ProtectedRouteProps> = ({
  children,
  requiredPermission,
  requiredRole,
  redirectTo = '/admin/login',
}) => {
  const { isAuthenticated, isLoading, hasPermission, hasRole } = useAuth();

  if (isLoading) {
    return (
      <div
        className="flex min-h-screen items-center justify-center bg-voak-canvas"
        role="status"
        aria-label="Loading verification"
      >
        <div className="flex flex-col items-center gap-3">
          <div className="h-10 w-10 animate-spin rounded-full border-4 border-voak-border border-t-voak-purple" />
          <p className="text-sm font-medium text-voak-muted">Verifying credentials...</p>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to={redirectTo} replace />;
  }

  if (requiredPermission && !hasPermission(requiredPermission)) {
    return (
      <div
        className="flex min-h-[60vh] flex-col items-center justify-center p-8 text-center"
        role="alert"
        aria-live="assertive"
      >
        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-state-error-bg text-state-error">
          <svg
            className="h-8 w-8"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={2}
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
          </svg>
        </div>
        <h2 className="text-2xl font-bold text-voak-navy">Access Denied (403 Forbidden)</h2>
        <p className="mt-2 max-w-md text-sm text-voak-muted">
          Your administrative account does not possess the required permission{' '}
          <span className="font-mono font-semibold">({Array.isArray(requiredPermission) ? requiredPermission.join(', ') : requiredPermission})</span> to access this module.
        </p>
      </div>
    );
  }

  if (requiredRole && !hasRole(requiredRole)) {
    return (
      <div
        className="flex min-h-[60vh] flex-col items-center justify-center p-8 text-center"
        role="alert"
        aria-live="assertive"
      >
        <h2 className="text-2xl font-bold text-voak-navy">Access Restricted</h2>
        <p className="mt-2 max-w-md text-sm text-voak-muted">
          This area is restricted to specific administrative roles.
        </p>
      </div>
    );
  }

  return <>{children}</>;
};
