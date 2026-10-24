import { Calendar, ArrowLeft, Shield } from 'lucide-react';
import { PageHeader } from '../../../shared/components/PageHeader';
import { Breadcrumb } from '../../../shared/components/Breadcrumb';
import { EmptyState } from '../../../shared/components/EmptyState';
import { Button } from '../../../shared/components/Button';
import { Badge } from '../../../shared/components/Badge';
import { useRouter } from '../../../shared/router/Router';

export interface AdminPlaceholderPageProps {
  title: string;
  moduleKey: string;
  scheduledPhase?: string;
  description?: string;
  requiredPermission?: string;
}

export const AdminPlaceholderPage: React.FC<AdminPlaceholderPageProps> = ({
  title,
  moduleKey,
  scheduledPhase = 'Phase 26',
  description = 'This administrative operations desk is scheduled in the canonical 32-phase roadmap. Full CRUD workflows, filters, and management tools will be introduced in the designated phase.',
  requiredPermission,
}) => {
  const { navigate } = useRouter();

  return (
    <div data-testid={`admin-placeholder-${moduleKey}`} data-module={moduleKey}>
      <PageHeader
        title={title}
        description={`Scheduled for ${scheduledPhase}`}
        breadcrumbs={
          <Breadcrumb
            items={[
              { label: 'Admin', href: '/admin' },
              { label: title },
            ]}
          />
        }
        actions={
          <div className="flex items-center gap-2">
            <Badge variant="purple" size="md">
              {scheduledPhase} Scheduled
            </Badge>
            {requiredPermission && (
              <Badge variant="neutral" size="md" className="flex items-center gap-1 font-mono text-[10px]">
                <Shield className="h-3 w-3" />
                {requiredPermission}
              </Badge>
            )}
          </div>
        }
      />

      <EmptyState
        title={`${title} Module`}
        description={description}
        icon={<Calendar className="h-7 w-7 text-[#6A0DAD]" />}
        action={
          <div className="flex flex-col sm:flex-row items-center gap-3">
            <Button
              variant="outline"
              onClick={() => navigate('/admin')}
              leftIcon={<ArrowLeft className="h-4 w-4" />}
            >
              Return to Admin Overview
            </Button>
          </div>
        }
      />
    </div>
  );
};
