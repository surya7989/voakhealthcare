import React from 'react';
import { useAuth } from '../../apps/admin/context/AuthContext';
import type { RoleSlug } from '../types/auth';

export interface PermissionGuardProps {
  children: React.ReactNode;
  permission?: string | string[];
  role?: RoleSlug | RoleSlug[];
  fallback?: React.ReactNode;
}

export const PermissionGuard: React.FC<PermissionGuardProps> = ({
  children,
  permission,
  role,
  fallback = null,
}) => {
  const { hasPermission, hasRole } = useAuth();

  if (permission && !hasPermission(permission)) {
    return <>{fallback}</>;
  }

  if (role && !hasRole(role)) {
    return <>{fallback}</>;
  }

  return <>{children}</>;
};
