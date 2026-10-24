import { ShieldCheck, CheckCircle2, Lock, UserCheck, Terminal } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { PageHeader } from '../../../shared/components/PageHeader';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../../../shared/components/Card';
import { Badge } from '../../../shared/components/Badge';

export const AdminDashboardLanding: React.FC = () => {
  const { user } = useAuth();

  return (
    <div>
      <PageHeader
        title="Admin Operations Console"
        description="Phase 18 React Admin Foundation is active. Shared infrastructure, session management, and design system primitives are operational."
        actions={
          <Badge variant="purple" size="md" className="font-semibold px-3 py-1 text-xs">
            Phase 18 — Foundation Active
          </Badge>
        }
      />

      {/* Overview Metric Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
        <Card className="border-neutral-200 dark:border-neutral-800">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-neutral-500 dark:text-neutral-400">
              Backend Services
            </CardTitle>
            <div className="p-2 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
              <CheckCircle2 className="h-5 w-5" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-neutral-900 dark:text-neutral-100">Verified</div>
            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1 flex items-center gap-1.5">
              <span className="inline-block h-2 w-2 rounded-full bg-emerald-500" />
              Stage 2 Backend 100% Complete
            </p>
          </CardContent>
        </Card>

        <Card className="border-neutral-200 dark:border-neutral-800">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-neutral-500 dark:text-neutral-400">
              Security Architecture
            </CardTitle>
            <div className="p-2 rounded-xl bg-purple-100 dark:bg-purple-950/60 text-[#6A0DAD] dark:text-[#D8B4E2]">
              <Lock className="h-5 w-5" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-neutral-900 dark:text-neutral-100">Session & CSRF</div>
            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1 flex items-center gap-1.5">
              <ShieldCheck className="h-3.5 w-3.5 text-[#6A0DAD]" />
              HttpOnly Cookies & Token Attachment
            </p>
          </CardContent>
        </Card>

        <Card className="border-neutral-200 dark:border-neutral-800">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-neutral-500 dark:text-neutral-400">
              Active Operator
            </CardTitle>
            <div className="p-2 rounded-xl bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">
              <UserCheck className="h-5 w-5" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-neutral-900 dark:text-neutral-100 truncate">
              {user?.name || 'Administrator'}
            </div>
            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1 capitalize">
              Role: <strong className="text-neutral-700 dark:text-neutral-300">{user?.role?.replace('_', ' ')}</strong>
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Foundation Status & Guidance */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <Card className="border-neutral-200 dark:border-neutral-800">
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <Terminal className="h-5 w-5 text-[#6A0DAD]" />
              Phase 18 Foundation Scope
            </CardTitle>
            <CardDescription>
              Established architectural capabilities ready for upcoming application modules
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex items-start gap-3 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50">
              <div className="h-2 w-2 rounded-full bg-[#6A0DAD] mt-1.5 shrink-0" />
              <div className="text-xs text-neutral-600 dark:text-neutral-300">
                <strong className="text-neutral-900 dark:text-neutral-100">Unified API Client:</strong> Automatically syncs CSRF tokens from <code className="px-1 py-0.5 bg-neutral-200 dark:bg-neutral-700 rounded font-mono">/api/v1/admin/auth/csrf</code> and handles session cookie credentials.
              </div>
            </div>
            <div className="flex items-start gap-3 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50">
              <div className="h-2 w-2 rounded-full bg-[#6A0DAD] mt-1.5 shrink-0" />
              <div className="text-xs text-neutral-600 dark:text-neutral-300">
                <strong className="text-neutral-900 dark:text-neutral-100">RBAC Guard Utilities:</strong> Role and permission verification guards against unauthorized navigation and DOM rendering.
              </div>
            </div>
            <div className="flex items-start gap-3 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50">
              <div className="h-2 w-2 rounded-full bg-[#6A0DAD] mt-1.5 shrink-0" />
              <div className="text-xs text-neutral-600 dark:text-neutral-300">
                <strong className="text-neutral-900 dark:text-neutral-100">Atomic Design Primitives:</strong> Buttons, Form Fields, Modals, Badges, Tables, Pagination, and Error Boundaries adhering to VOAK brand tokens.
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="border-neutral-200 dark:border-neutral-800">
          <CardHeader>
            <CardTitle className="text-base">Roadmap Alignment</CardTitle>
            <CardDescription>
              Upcoming frontend milestones per the canonical 32-phase plan
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="p-3 rounded-xl border border-neutral-100 dark:border-neutral-800 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-neutral-900 dark:text-neutral-100">Phase 19: Complete UI Component System</p>
                <p className="text-[11px] text-neutral-500">Comprehensive public and admin component library</p>
              </div>
              <Badge variant="neutral" size="sm">Next</Badge>
            </div>
            <div className="p-3 rounded-xl border border-neutral-100 dark:border-neutral-800 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-neutral-900 dark:text-neutral-100">Phases 20–24: Public Web Experience</p>
                <p className="text-[11px] text-neutral-500">Homepage, Services, RFP Desk, Careers, Resources</p>
              </div>
              <Badge variant="neutral" size="sm">Scheduled</Badge>
            </div>
            <div className="p-3 rounded-xl border border-neutral-100 dark:border-neutral-800 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-neutral-900 dark:text-neutral-100">Phases 25–26: Complete Admin Operations Console</p>
                <p className="text-[11px] text-neutral-500">Full CRM, Proposals, Careers, Media & Audit Desks</p>
              </div>
              <Badge variant="neutral" size="sm">Scheduled</Badge>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
};
