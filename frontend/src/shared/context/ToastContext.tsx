import React, { createContext, useContext, useState, useCallback, useMemo } from 'react';
import { Info, CheckCircle2, AlertTriangle, AlertCircle, X } from 'lucide-react';
import { cn } from '../utils/cn';

export type ToastType = 'info' | 'success' | 'warning' | 'error';

export interface ToastItem {
  id: string;
  type: ToastType;
  title?: string;
  message: string;
  duration?: number;
}

interface ToastContextValue {
  toasts: ToastItem[];
  showToast: (toast: Omit<ToastItem, 'id'>) => string;
  dismissToast: (id: string) => void;
}

const ToastContext = createContext<ToastContextValue | null>(null);

const iconMap = {
  info: { Icon: Info, color: 'text-blue-500' },
  success: { Icon: CheckCircle2, color: 'text-emerald-500' },
  warning: { Icon: AlertTriangle, color: 'text-amber-500' },
  error: { Icon: AlertCircle, color: 'text-rose-500' },
};

export const ToastProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [toasts, setToasts] = useState<ToastItem[]>([]);

  const dismissToast = useCallback((id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  const showToast = useCallback(
    ({ type, title, message, duration = 5000 }: Omit<ToastItem, 'id'>) => {
      const id = Math.random().toString(36).substring(2, 9);
      const newToast: ToastItem = { id, type, title, message, duration };

      setToasts((prev) => [...prev, newToast]);

      if (duration > 0) {
        setTimeout(() => {
          dismissToast(id);
        }, duration);
      }

      return id;
    },
    [dismissToast]
  );

  const value = useMemo(
    () => ({ toasts, showToast, dismissToast }),
    [toasts, showToast, dismissToast]
  );

  return (
    <ToastContext.Provider value={value}>
      {children}
      {/* Toast floating container */}
      <div
        aria-live="polite"
        className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none"
      >
        {toasts.map((toast) => {
          const { Icon, color } = iconMap[toast.type];
          return (
            <div
              key={toast.id}
              role="status"
              className={cn(
                'pointer-events-auto flex items-start gap-3 p-4 rounded-xl shadow-lg border transition-all animate-in fade-in slide-in-from-bottom-2',
                'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 text-neutral-800 dark:text-neutral-100'
              )}
            >
              <Icon className={cn('h-5 w-5 shrink-0 mt-0.5', color)} />
              <div className="flex-1 min-w-0">
                {toast.title && <h5 className="font-semibold text-sm leading-5">{toast.title}</h5>}
                <p className="text-xs text-neutral-600 dark:text-neutral-400 mt-0.5">{toast.message}</p>
              </div>
              <button
                type="button"
                onClick={() => dismissToast(toast.id)}
                className="shrink-0 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 p-1"
                aria-label="Close notification"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
          );
        })}
      </div>
    </ToastContext.Provider>
  );
};

export function useToast(): ToastContextValue {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error('useToast must be used within a ToastProvider');
  }
  return context;
}
