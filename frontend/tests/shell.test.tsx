import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { Router } from '../src/shared/router/Router';
import { AuthProvider } from '../src/apps/admin/context/AuthContext';
import { AdminLayout } from '../src/apps/admin/components/AdminLayout';
import { ProtectedRoute } from '../src/shared/components/ProtectedRoute';
import { PermissionGuard } from '../src/shared/components/PermissionGuard';
import { apiClient } from '../src/shared/api/apiClient';

describe('Admin Shell & Protection Suite', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('renders AdminLayout with sidebar, header, and content children', async () => {
    const mockUser = {
      id: 1,
      email: 'admin@voakhealthcare.com',
      name: 'Dr. Jane Voak',
      role: 'admin',
      permissions: ['crm.read'],
    };

    vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
      success: true,
      data: {
        user: mockUser,
        csrf_token: 'valid-csrf',
      },
    });

    render(
      <Router>
        <AuthProvider>
          <AdminLayout>
            <div data-testid="dashboard-content">Dashboard Content Section</div>
          </AdminLayout>
        </AuthProvider>
      </Router>
    );

    await waitFor(() => {
      expect(screen.getByTestId('dashboard-content')).toBeInTheDocument();
    });

    expect(screen.getByText('VOAK Health')).toBeInTheDocument();
    expect(screen.getByText('Admin Console')).toBeInTheDocument();
    expect(screen.getByText('API Connected')).toBeInTheDocument();
  });

  it('ProtectedRoute redirects unauthenticated visitors to login route', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValueOnce(new Error('Unauthenticated'));

    render(
      <Router>
        <AuthProvider>
          <ProtectedRoute redirectTo="/admin/login">
            <div data-testid="secret-admin-area">Top Secret Admin View</div>
          </ProtectedRoute>
        </AuthProvider>
      </Router>
    );

    // Protected content should NOT be rendered
    await waitFor(() => {
      expect(screen.queryByTestId('secret-admin-area')).not.toBeInTheDocument();
    });
  });

  it('ProtectedRoute checks required permissions and denies access if lacking', async () => {
    const editorUser = {
      id: 3,
      email: 'editor@voakhealthcare.com',
      name: 'Content Editor',
      role: 'editor',
      permissions: ['content.read'], // Missing 'audit.read'
    };

    vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
      success: true,
      data: {
        user: editorUser,
        csrf_token: 'csrf-123',
      },
    });

    render(
      <Router>
        <AuthProvider>
          <ProtectedRoute requiredPermission="audit.read">
            <div data-testid="audit-content">Audit Ledger View</div>
          </ProtectedRoute>
        </AuthProvider>
      </Router>
    );

    await waitFor(() => {
      expect(screen.getByText(/Access Denied/i)).toBeInTheDocument();
    });

    expect(screen.queryByTestId('audit-content')).not.toBeInTheDocument();
    expect(screen.getByText(/audit\.read/i)).toBeInTheDocument();
  });

  it('PermissionGuard conditionally renders UI element when permission is satisfied', async () => {
    const staffUser = {
      id: 5,
      email: 'staff@voakhealthcare.com',
      name: 'Staff Member',
      role: 'editor',
      permissions: ['content.write'],
    };

    vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
      success: true,
      data: {
        user: staffUser,
        csrf_token: 'csrf-456',
      },
    });

    render(
      <Router>
        <AuthProvider>
          <PermissionGuard permission="content.write">
            <button data-testid="publish-btn">Publish Article</button>
          </PermissionGuard>
          <PermissionGuard permission="billing.manage">
            <button data-testid="billing-btn">Manage Billing</button>
          </PermissionGuard>
        </AuthProvider>
      </Router>
    );

    await waitFor(() => {
      expect(screen.getByTestId('publish-btn')).toBeInTheDocument();
    });

    expect(screen.queryByTestId('billing-btn')).not.toBeInTheDocument();
  });
});
