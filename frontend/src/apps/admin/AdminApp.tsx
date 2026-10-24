import React from 'react';
import { useRouter } from '../../shared/router/Router';
import { ProtectedRoute } from '../../shared/components/ProtectedRoute';
import { AdminLayout } from './components/AdminLayout';
import { AdminLoginPage } from './pages/AdminLoginPage';
import { AdminDashboardLanding } from './pages/AdminDashboardLanding';
import { AdminPlaceholderPage } from './pages/AdminPlaceholderPage';

export const AdminApp: React.FC = () => {
  const { path } = useRouter();

  // Public admin route: Login
  if (path === '/admin/login') {
    return <AdminLoginPage />;
  }

  // All other admin routes require authenticated session
  return (
    <ProtectedRoute redirectTo="/admin/login">
      {(() => {
        switch (path) {
          case '/admin':
          case '/admin/':
            return (
              <AdminLayout>
                <AdminDashboardLanding />
              </AdminLayout>
            );

          case '/admin/enquiries':
            return (
              <AdminLayout>
                <AdminPlaceholderPage
                  title="Contact Enquiries CRM"
                  moduleKey="enquiries"
                  scheduledPhase="Phase 26"
                  requiredPermission="crm.read"
                  description="Consolidated CRM inbox for contact inquiries, triage states, and secure follow-ups. Full operations console scheduled for Phase 26."
                />
              </AdminLayout>
            );

          case '/admin/proposals':
            return (
              <AdminLayout>
                <AdminPlaceholderPage
                  title="Proposals & RFP Desk"
                  moduleKey="proposals"
                  scheduledPhase="Phase 26"
                  requiredPermission="proposals.read"
                  description="Client proposal pipeline, institutional RFP tracking, and enterprise contract workflows. Full operations console scheduled for Phase 26."
                />
              </AdminLayout>
            );

          case '/admin/recruitment':
            return (
              <AdminLayout>
                <AdminPlaceholderPage
                  title="Recruitment & Careers Review"
                  moduleKey="recruitment"
                  scheduledPhase="Phase 26"
                  requiredPermission="recruitment.read"
                  description="Candidate application review desk, authenticated CV download, and privacy-retention compliance. Full operations console scheduled for Phase 26."
                />
              </AdminLayout>
            );

          case '/admin/content':
            return (
              <AdminLayout>
                <AdminPlaceholderPage
                  title="Content Management CMS"
                  moduleKey="content"
                  scheduledPhase="Phase 26"
                  requiredPermission="content.read"
                  description="CMS publishing desk for pages, services catalog, industry solutions, and case studies. Full operations console scheduled for Phase 26."
                />
              </AdminLayout>
            );

          case '/admin/media':
            return (
              <AdminLayout>
                <AdminPlaceholderPage
                  title="Media & Document Library"
                  moduleKey="media"
                  scheduledPhase="Phase 26"
                  requiredPermission="media.read"
                  description="Centralized media asset browser, quarantined upload processing, and secure file delivery. Full operations console scheduled for Phase 26."
                />
              </AdminLayout>
            );

          case '/admin/seo':
            return (
              <AdminLayout>
                <AdminPlaceholderPage
                  title="SEO Console & Redirects"
                  moduleKey="seo"
                  scheduledPhase="Phase 26"
                  requiredPermission="seo.read"
                  description="Search engine optimization controls, 301 redirect management with loop-protection, and sitemap cache controls. Full operations console scheduled for Phase 26."
                />
              </AdminLayout>
            );

          case '/admin/audit':
            return (
              <AdminLayout>
                <AdminPlaceholderPage
                  title="Audit Ledger & Security"
                  moduleKey="audit"
                  scheduledPhase="Phase 26"
                  requiredPermission="audit.read"
                  description="Cryptographic tamper-evident audit ledger viewer, hash chain verification, and system telemetry. Full operations console scheduled for Phase 26."
                />
              </AdminLayout>
            );

          default:
            // Fallback for unmatched /admin/* paths
            return (
              <AdminLayout>
                <AdminPlaceholderPage
                  title="Admin Section"
                  moduleKey="unmatched"
                  scheduledPhase="Phase 26"
                  description="This administrative section is not yet initialized or is scheduled for a future milestone."
                />
              </AdminLayout>
            );
        }
      })()}
    </ProtectedRoute>
  );
};
