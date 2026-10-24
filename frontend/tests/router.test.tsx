import React from 'react';
import { describe, it, expect } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import { Router, useRouter, Link, Route } from '../src/shared/router/Router';

const RouterConsumer: React.FC = () => {
  const { path, query, navigate } = useRouter();
  return (
    <div>
      <div data-testid="current-path">{path}</div>
      <div data-testid="search-param">{query.search || 'none'}</div>
      <Link to="/admin/enquiries" data-testid="nav-link">
        Go to Enquiries
      </Link>
      <button
        onClick={() => navigate('/admin/audit?search=login')}
        data-testid="programmatic-nav-btn"
      >
        Programmatic Nav
      </button>
      <Route path="/admin">
        <div data-testid="admin-home-view">Admin Home View</div>
      </Route>
      <Route path="/admin/enquiries">
        <div data-testid="enquiries-view">Enquiries View</div>
      </Route>
    </div>
  );
};

describe('Router', () => {
  it('provides current path and responds to Link navigation', () => {
    window.history.pushState({}, '', '/admin');

    render(
      <Router>
        <RouterConsumer />
      </Router>
    );

    expect(screen.getByTestId('current-path')).toHaveTextContent('/admin');
    expect(screen.getByTestId('admin-home-view')).toBeInTheDocument();
    expect(screen.queryByTestId('enquiries-view')).not.toBeInTheDocument();

    act(() => {
      screen.getByTestId('nav-link').click();
    });

    expect(screen.getByTestId('current-path')).toHaveTextContent('/admin/enquiries');
    expect(screen.getByTestId('enquiries-view')).toBeInTheDocument();
  });

  it('handles programmatic navigation and parses query parameters', () => {
    window.history.pushState({}, '', '/admin');

    render(
      <Router>
        <RouterConsumer />
      </Router>
    );

    act(() => {
      screen.getByTestId('programmatic-nav-btn').click();
    });

    expect(screen.getByTestId('current-path')).toHaveTextContent('/admin/audit');
    expect(screen.getByTestId('search-param')).toHaveTextContent('login');
  });
});
