import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { apiClient } from '../../../shared/api/apiClient';
import type {
  AuthContextType,
  LoginPayload,
  LoginResponseData,
  RoleSlug,
  UserProfile,
} from '../../../shared/types/auth';
import { checkPermission, checkRole } from '../../../shared/utils/permissions';

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<UserProfile | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);

  const refreshSession = useCallback(async (): Promise<UserProfile | null> => {
    try {
      const response = await apiClient.get<LoginResponseData>('/admin/auth/me');
      if (response.success && response.data?.user) {
        setUser(response.data.user);
        if (response.data.csrf_token) {
          apiClient.setCsrfToken(response.data.csrf_token);
        }
        return response.data.user;
      }
      setUser(null);
      return null;
    } catch {
      setUser(null);
      return null;
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    refreshSession();
  }, [refreshSession]);

  const login = async (
    credentialsOrEmail: LoginPayload | string,
    password?: string,
    totp_code?: string
  ): Promise<UserProfile> => {
    setIsLoading(true);
    try {
      const payload: LoginPayload =
        typeof credentialsOrEmail === 'string'
          ? { email: credentialsOrEmail, password: password || '', totp_code }
          : credentialsOrEmail;
      const response = await apiClient.post<LoginResponseData>('/admin/auth/login', payload);
      if (response.success && response.data?.user) {
        setUser(response.data.user);
        if (response.data.csrf_token) {
          apiClient.setCsrfToken(response.data.csrf_token);
        }
        return response.data.user;
      }
      throw new Error('Authentication succeeded but profile payload was incomplete');
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async (): Promise<void> => {
    setIsLoading(true);
    try {
      await apiClient.post('/admin/auth/logout');
    } catch {
      // Continue client cleanup even if network fails
    } finally {
      setUser(null);
      apiClient.setCsrfToken(null);
      setIsLoading(false);
    }
  };

  const hasPermission = useCallback(
    (permission: string | string[]): boolean => {
      return checkPermission(user, permission);
    },
    [user]
  );

  const hasRole = useCallback(
    (role: RoleSlug | RoleSlug[]): boolean => {
      return checkRole(user, role);
    },
    [user]
  );

  const value: AuthContextType = {
    user,
    isAuthenticated: !!user,
    isLoading,
    login,
    logout,
    refreshSession,
    hasPermission,
    hasRole,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export function useAuth(): AuthContextType {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
