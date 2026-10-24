import React from 'react';
import {
  LayoutDashboard,
  Inbox,
  FileText,
  Users,
  Layers,
  Image,
  Globe,
  ShieldCheck,
  X,
  Sparkles,
} from 'lucide-react';
import { useRouter, Link } from '../../../shared/router/Router';
import { Badge } from '../../../shared/components/Badge';
import { cn } from '../../../shared/utils/cn';

interface AdminSidebarProps {
  isMobileOpen: boolean;
  onMobileClose: () => void;
}

interface NavItem {
  label: string;
  href: string;
  icon: React.ElementType;
  badge?: string;
  isPlaceholder?: boolean;
}

const navSections: { title: string; items: NavItem[] }[] = [
  {
    title: 'Overview',
    items: [
      {
        label: 'Dashboard',
        href: '/admin',
        icon: LayoutDashboard,
      },
    ],
  },
  {
    title: 'Operations (Scheduled)',
    items: [
      {
        label: 'Contact Inquiries',
        href: '/admin/enquiries',
        icon: Inbox,
        badge: 'Phase 26',
        isPlaceholder: true,
      },
      {
        label: 'Proposals & RFP',
        href: '/admin/proposals',
        icon: FileText,
        badge: 'Phase 26',
        isPlaceholder: true,
      },
      {
        label: 'Recruitment',
        href: '/admin/recruitment',
        icon: Users,
        badge: 'Phase 26',
        isPlaceholder: true,
      },
      {
        label: 'Content CMS',
        href: '/admin/content',
        icon: Layers,
        badge: 'Phase 26',
        isPlaceholder: true,
      },
      {
        label: 'Media Library',
        href: '/admin/media',
        icon: Image,
        badge: 'Phase 26',
        isPlaceholder: true,
      },
      {
        label: 'SEO Console',
        href: '/admin/seo',
        icon: Globe,
        badge: 'Phase 26',
        isPlaceholder: true,
      },
      {
        label: 'Audit Ledger',
        href: '/admin/audit',
        icon: ShieldCheck,
        badge: 'Phase 26',
        isPlaceholder: true,
      },
    ],
  },
];

export const AdminSidebar: React.FC<AdminSidebarProps> = ({
  isMobileOpen,
  onMobileClose,
}) => {
  const { path } = useRouter();

  return (
    <>
      {/* Mobile Backdrop */}
      {isMobileOpen && (
        <div
          className="fixed inset-0 z-40 bg-neutral-900/60 backdrop-blur-sm lg:hidden transition-opacity"
          onClick={onMobileClose}
          aria-hidden="true"
        />
      )}

      {/* Sidebar Container */}
      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-[#111827] text-neutral-300 transition-transform duration-300 ease-in-out lg:static lg:translate-x-0 border-r border-neutral-800',
          isMobileOpen ? 'translate-x-0' : '-translate-x-full'
        )}
        aria-label="Admin Navigation"
      >
        {/* Brand / Logo Header */}
        <div className="flex h-16 items-center justify-between px-6 border-b border-neutral-800">
          <Link to="/admin" className="flex items-center gap-3 group" onClick={onMobileClose}>
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tr from-[#6A0DAD] to-[#D8B4E2] text-white shadow-md shadow-[#6A0DAD]/30 group-hover:scale-105 transition-transform">
              <Sparkles className="h-5 w-5" />
            </div>
            <div className="flex flex-col">
              <span className="font-bold text-white tracking-tight text-base leading-none">VOAK Health</span>
              <span className="text-[10px] text-neutral-400 font-medium tracking-wider uppercase mt-1">Admin Console</span>
            </div>
          </Link>

          {/* Close button on mobile */}
          <button
            type="button"
            onClick={onMobileClose}
            className="lg:hidden p-1.5 rounded-lg text-neutral-400 hover:text-white hover:bg-neutral-800"
            aria-label="Close sidebar"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Navigation items */}
        <div className="flex-1 overflow-y-auto px-4 py-5 space-y-6">
          {navSections.map((section) => (
            <div key={section.title}>
              <h3 className="px-3 text-[11px] font-semibold uppercase tracking-wider text-neutral-400 mb-2">
                {section.title}
              </h3>
              <ul className="space-y-1">
                {section.items.map((item) => {
                  const isActive = path === item.href;
                  const Icon = item.icon;
                  return (
                    <li key={item.href}>
                      <Link
                        to={item.href}
                        onClick={onMobileClose}
                        className={cn(
                          'flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all group',
                          isActive
                            ? 'bg-[#6A0DAD] text-white shadow-sm shadow-[#6A0DAD]/40 font-semibold'
                            : 'text-neutral-300 hover:text-white hover:bg-neutral-800/60'
                        )}
                        aria-current={isActive ? 'page' : undefined}
                      >
                        <div className="flex items-center gap-3 min-w-0">
                          <Icon
                            className={cn(
                              'h-4 w-4 shrink-0 transition-colors',
                              isActive ? 'text-white' : 'text-neutral-400 group-hover:text-white'
                            )}
                          />
                          <span className="truncate">{item.label}</span>
                        </div>
                        {item.badge && (
                          <Badge
                            variant={isActive ? 'purple' : 'neutral'}
                            size="sm"
                            className={cn('text-[10px] uppercase font-bold py-0.5 px-1.5 ml-2 shrink-0',
                              isActive ? 'bg-white/20 text-white' : 'bg-neutral-800 text-neutral-400'
                            )}
                          >
                            {item.badge}
                          </Badge>
                        )}
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </div>
          ))}
        </div>

        {/* Phase 18 Foundation footer indicator */}
        <div className="p-4 border-t border-neutral-800 text-xs text-neutral-400 flex flex-col gap-1">
          <div className="flex items-center justify-between">
            <span className="font-semibold text-neutral-300">Phase 18 Foundation</span>
            <span className="text-[10px] px-2 py-0.5 rounded-full bg-emerald-950/80 text-emerald-400 border border-emerald-800/60">
              Active
            </span>
          </div>
          <span className="text-[11px] text-neutral-400">Connected to Backend API</span>
        </div>
      </aside>
    </>
  );
};
