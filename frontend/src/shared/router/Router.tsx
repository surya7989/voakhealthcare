import React, { useState, useEffect, useMemo, useCallback } from 'react';
import { RouterContext, useRouter } from './RouterContext';

export interface RouterProps {
  children: React.ReactNode;
  initialPath?: string;
}

export const Router: React.FC<RouterProps> = ({ children, initialPath }) => {
  const [currentPath, setCurrentPath] = useState<string>(
    initialPath ||
      (typeof window !== 'undefined'
        ? window.location.pathname + window.location.search
        : '/')
  );

  useEffect(() => {
    if (typeof window === 'undefined') return;

    const onPopState = () => {
      setCurrentPath(window.location.pathname + window.location.search);
    };

    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, []);

  const navigate = useCallback((to: string, options?: { replace?: boolean }) => {
    if (typeof window !== 'undefined') {
      if (options?.replace) {
        window.history.replaceState({}, '', to);
      } else {
        window.history.pushState({}, '', to);
      }
    }
    setCurrentPath(to);
  }, []);

  const query = useMemo(() => {
    const qIndex = currentPath.indexOf('?');
    if (qIndex === -1) return {};
    const qs = currentPath.substring(qIndex + 1);
    const searchParams = new URLSearchParams(qs);
    const result: Record<string, string> = {};
    searchParams.forEach((val, key) => {
      result[key] = val;
    });
    return result;
  }, [currentPath]);

  return (
    <RouterContext.Provider value={{ currentPath, path: currentPath, navigate, query }}>
      {children}
    </RouterContext.Provider>
  );
};

export interface LinkProps extends React.AnchorHTMLAttributes<HTMLAnchorElement> {
  to: string;
  replace?: boolean;
}

export const Link: React.FC<LinkProps> = ({
  to,
  replace = false,
  onClick,
  children,
  className,
  ...rest
}) => {
  const { navigate } = useRouter();

  const handleClick = (e: React.MouseEvent<HTMLAnchorElement>) => {
    if (onClick) {
      onClick(e);
    }
    if (
      !e.defaultPrevented &&
      e.button === 0 &&
      !e.metaKey &&
      !e.altKey &&
      !e.ctrlKey &&
      !e.shiftKey
    ) {
      e.preventDefault();
      navigate(to, { replace });
    }
  };

  return (
    <a href={to} onClick={handleClick} className={className} {...rest}>
      {children}
    </a>
  );
};

export interface RouteProps {
  path: string;
  element?: React.ReactNode;
  children?: React.ReactNode;
  exact?: boolean;
}

export const Route: React.FC<RouteProps> = ({ path, element, children, exact = true }) => {
  const { currentPath } = useRouter();
  const pathname = currentPath.split('?')[0];

  let matches = false;
  if (exact) {
    matches = pathname === path || (path === '/' && pathname === '');
  } else {
    matches = pathname.startsWith(path);
  }

  if (!matches) return null;
  return <>{element ?? children ?? null}</>;
};

export interface NavigateProps {
  to: string;
  replace?: boolean;
}

export const Navigate: React.FC<NavigateProps> = ({ to, replace = true }) => {
  const { navigate } = useRouter();

  useEffect(() => {
    navigate(to, { replace });
  }, [navigate, to, replace]);

  return null;
};

export { useRouter };
