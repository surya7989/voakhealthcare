import { createContext, useContext } from 'react';

export interface RouterContextType {
  currentPath: string;
  path: string;
  navigate: (to: string, options?: { replace?: boolean }) => void;
  query: Record<string, string>;
}

export const RouterContext = createContext<RouterContextType | undefined>(undefined);

export function useRouter(): RouterContextType {
  const context = useContext(RouterContext);
  if (!context) {
    throw new Error('useRouter must be used within a Router');
  }
  return context;
}
