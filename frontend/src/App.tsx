import React from 'react';
import { Router, useRouter } from './shared/router/Router';
import { AuthProvider } from './apps/admin/context/AuthContext';
import { ToastProvider } from './shared/context/ToastContext';
import { ErrorBoundary } from './shared/components/ErrorBoundary';
import { AdminApp } from './apps/admin/AdminApp';
import { PublicApp } from './apps/public/PublicApp';

const AppContent: React.FC = () => {
  const { path } = useRouter();

  if (path.startsWith('/admin')) {
    return <AdminApp />;
  }

  return <PublicApp />;
};

export const App: React.FC = () => {
  return (
    <ErrorBoundary>
      <Router>
        <AuthProvider>
          <ToastProvider>
            <AppContent />
          </ToastProvider>
        </AuthProvider>
      </Router>
    </ErrorBoundary>
  );
};

export default App;
