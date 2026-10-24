/**
 * Authentication and RBAC Types matching Voak Health Care Backend (Phase 6 & Phase 17)
 */

export type RoleSlug =
  | 'super_admin'
  | 'admin'
  | 'website_admin'
  | 'content_editor'
  | 'bd_manager'
  | 'hr_recruiter'
  | 'compliance_auditor';

export interface UserProfile {
  id: number;
  name: string;
  email: string;
  role: RoleSlug | string;
  roles?: string[];
  permissions: string[];
  is_active?: boolean;
}

export interface LoginPayload {
  email: string;
  password: string;
  totp_code?: string;
}

export interface LoginResponseData {
  user: UserProfile;
  csrf_token: string;
  mfa_required?: boolean;
  pre_auth_token?: string;
}

export interface AuthContextType {
  user: UserProfile | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (credentialsOrEmail: LoginPayload | string, password?: string, totp_code?: string) => Promise<UserProfile>;
  logout: () => Promise<void>;
  refreshSession: () => Promise<UserProfile | null>;
  hasPermission: (permission: string | string[]) => boolean;
  hasRole: (role: RoleSlug | RoleSlug[]) => boolean;
}
