import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, act } from '@testing-library/react';
import { AuthProvider, useAuth } from '../src/apps/admin/context/AuthContext';
import { apiClient } from '../src/shared/api/apiClient';

const TestAuthConsumer: React.FC = () => {
  const { user, isAuthenticated, isLoading, login, logout, hasPermission, hasRole } = useAuth();

  if (isLoading) return <div>Auth Loading...</div>;

  return (
    <div>
      <div data-testid="auth-status">{isAuthenticated ? 'AUTHENTICATED' : 'ANONYMOUS'}</div>
      {user && <div data-testid="user-name">{user.name}</div>}
      <button
        onClick={() => login('admin@voakhealthcare.com', 'AdminPass123!')}
        data-testid="login-btn"
      >
        Login
      </button>
      <button onClick={() => logout()} data-testid="logout-btn">
        Logout
      </button>
      <div data-testid="perm-crm-read">
        {hasPermission('crm.read') ? 'HAS_CRM_READ' : 'NO_CRM_READ'}
      </div>
      <div data-testid="perm-super-admin">
        {hasRole(['super_admin', 'admin']) ? 'IS_ADMIN_ROLE' : 'NOT_ADMIN_ROLE'}
      </div>
    </div>
  );
};

describe('AuthContext', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('restores session when backend /api/v1/admin/auth/me returns valid user', async () => {
    const mockUser = {
      id: 1,
      email: 'admin@voakhealthcare.com',
      name: 'Dr. Jane Voak',
      role: 'admin',
      permissions: ['crm.read', 'content.read'],
    };

    vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
      success: true,
      data: {
        user: mockUser,
        csrf_token: 'csrf-session-token',
      },
    });

    render(
      <AuthProvider>
        <TestAuthConsumer />
      </AuthProvider>
    );

    expect(screen.getByText('Auth Loading...')).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByTestId('auth-status')).toHaveTextContent('AUTHENTICATED');
    });

    expect(screen.getByTestId('user-name')).toHaveTextContent('Dr. Jane Voak');
    expect(screen.getByTestId('perm-crm-read')).toHaveTextContent('HAS_CRM_READ');
    expect(screen.getByTestId('perm-super-admin')).toHaveTextContent('IS_ADMIN_ROLE');
  });

  it('sets unauthenticated state when /api/v1/admin/auth/me fails or returns 401', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValueOnce(new Error('Unauthenticated'));

    render(
      <AuthProvider>
        <TestAuthConsumer />
      </AuthProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('auth-status')).toHaveTextContent('ANONYMOUS');
    });
    expect(screen.getByTestId('perm-crm-read')).toHaveTextContent('NO_CRM_READ');
  });

  it('logs in successfully and updates authentication state', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValueOnce(new Error('Unauthenticated'));

    const mockLoggedInUser = {
      id: 2,
      email: 'ops@voakhealthcare.com',
      name: 'Operations Manager',
      role: 'editor',
      permissions: ['content.read', 'content.write'],
    };

    vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
      success: true,
      data: {
        user: mockLoggedInUser,
        csrf_token: 'login-csrf-token',
      },
    });

    render(
      <AuthProvider>
        <TestAuthConsumer />
      </AuthProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('auth-status')).toHaveTextContent('ANONYMOUS');
    });

    await act(async () => {
      screen.getByTestId('login-btn').click();
    });

    await waitFor(() => {
      expect(screen.getByTestId('auth-status')).toHaveTextContent('AUTHENTICATED');
    });
    expect(screen.getByTestId('user-name')).toHaveTextContent('Operations Manager');
  });

  it('super_admin role bypasses all individual permission requirements', async () => {
    const superAdminUser = {
      id: 99,
      email: 'root@voakhealthcare.com',
      name: 'Super Admin',
      role: 'super_admin',
      permissions: [], // empty permissions array, but super_admin has implicit access
    };

    vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
      success: true,
      data: {
        user: superAdminUser,
        csrf_token: 'super-csrf',
      },
    });

    render(
      <AuthProvider>
        <TestAuthConsumer />
      </AuthProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('auth-status')).toHaveTextContent('AUTHENTICATED');
    });

    // Despite permissions being empty, super_admin hasPermission returns true
    expect(screen.getByTestId('perm-crm-read')).toHaveTextContent('HAS_CRM_READ');
  });
});
