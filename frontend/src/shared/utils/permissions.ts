import type { RoleSlug, UserProfile } from '../types/auth';

/**
 * Checks if a user has a specific permission or set of permissions.
 * Super Admin ('super_admin') has unrestricted authority over all permissions.
 */
export function checkPermission(
  user: UserProfile | null,
  permission: string | string[],
  requireAll = false
): boolean {
  if (!user) {
    return false;
  }

  // Super Admin bypass: unrestricted access
  if (user.role === 'super_admin' || user.roles?.includes('super_admin')) {
    return true;
  }

  const userPerms = new Set(user.permissions || []);
  const permsToCheck = Array.isArray(permission) ? permission : [permission];

  if (requireAll) {
    return permsToCheck.every((p) => userPerms.has(p));
  }

  return permsToCheck.some((p) => userPerms.has(p));
}

/**
 * Checks if a user has one of the specified roles.
 */
export function checkRole(user: UserProfile | null, role: RoleSlug | RoleSlug[]): boolean {
  if (!user) {
    return false;
  }

  const rolesToCheck = Array.isArray(role) ? role : [role];
  const userRoles = new Set([user.role, ...(user.roles || [])]);

  return rolesToCheck.some((r) => userRoles.has(r));
}
