import { useState, useRef, useEffect } from 'react';
import { LogOut, Shield, ChevronDown } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { cn } from '../../../shared/utils/cn';

export const UserDropdown: React.FC = () => {
  const { user, logout } = useAuth();
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

  if (!user) return null;

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        type="button"
        onClick={() => setIsOpen((prev) => !prev)}
        className="flex items-center gap-2.5 rounded-full p-1.5 text-sm font-medium text-neutral-700 hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-800 transition-colors focus:outline-none focus:ring-2 focus:ring-[#6A0DAD]"
        aria-expanded={isOpen}
        aria-haspopup="menu"
        aria-label="User profile menu"
      >
        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[#6A0DAD] text-xs font-bold text-white shadow-sm">
          {user.name.charAt(0).toUpperCase()}
        </div>
        <div className="hidden md:flex flex-col text-left">
          <span className="text-xs font-semibold leading-none">{user.name}</span>
          <span className="text-[10px] text-neutral-500 dark:text-neutral-400 capitalize mt-0.5">{user.role}</span>
        </div>
        <ChevronDown className={cn('h-4 w-4 text-neutral-400 transition-transform duration-200', isOpen && 'rotate-180')} />
      </button>

      {isOpen && (
        <div
          role="menu"
          aria-orientation="vertical"
          className="absolute right-0 mt-2 w-64 origin-top-right rounded-2xl bg-white dark:bg-neutral-900 p-2 shadow-xl border border-neutral-200 dark:border-neutral-800 focus:outline-none z-50 animate-in fade-in slide-in-from-top-1"
        >
          <div className="px-3 py-2.5 border-b border-neutral-100 dark:border-neutral-800">
            <p className="text-sm font-semibold text-neutral-900 dark:text-neutral-100 truncate">{user.name}</p>
            <p className="text-xs text-neutral-500 dark:text-neutral-400 truncate mt-0.5">{user.email}</p>
            <div className="mt-2 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-medium bg-[#6A0DAD]/10 text-[#6A0DAD] dark:text-[#E6E6FA]">
              <Shield className="h-3 w-3" />
              <span className="capitalize">{user.role.replace('_', ' ')}</span>
            </div>
          </div>

          <div className="pt-1">
            <div className="px-3 py-1.5 text-[11px] font-medium text-neutral-400 dark:text-neutral-500">
              Session Active
            </div>
            <button
              type="button"
              role="menuitem"
              onClick={async () => {
                setIsOpen(false);
                await logout();
              }}
              className="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-lg transition-colors"
            >
              <LogOut className="h-4 w-4 shrink-0" />
              <span>Sign Out</span>
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
