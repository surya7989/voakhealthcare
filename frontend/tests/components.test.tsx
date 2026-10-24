import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { Button } from '../src/shared/components/Button';
import { Input } from '../src/shared/components/Input';
import { FormField } from '../src/shared/components/FormField';
import { Badge } from '../src/shared/components/Badge';
import { Card, CardHeader, CardTitle, CardContent } from '../src/shared/components/Card';
import { Modal } from '../src/shared/components/Modal';
import { Alert } from '../src/shared/components/Alert';
import { EmptyState } from '../src/shared/components/EmptyState';
import { ErrorState } from '../src/shared/components/ErrorState';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '../src/shared/components/Table';
import { Pagination } from '../src/shared/components/Pagination';
import { Breadcrumb } from '../src/shared/components/Breadcrumb';
import { ErrorBoundary } from '../src/shared/components/ErrorBoundary';
import { Router } from '../src/shared/router/Router';

describe('UI Primitives Suite', () => {
  it('Button renders correctly, handles clicks, and shows loading spinner', () => {
    const handleClick = vi.fn();
    const { rerender } = render(
      <Button variant="primary" onClick={handleClick}>
        Submit Action
      </Button>
    );

    const btn = screen.getByRole('button', { name: /submit action/i });
    expect(btn).toBeInTheDocument();
    fireEvent.click(btn);
    expect(handleClick).toHaveBeenCalledTimes(1);

    // Test loading state
    rerender(
      <Button variant="primary" isLoading onClick={handleClick}>
        Submit Action
      </Button>
    );
    expect(btn).toBeDisabled();
    expect(screen.getByRole('status')).toBeInTheDocument();
  });

  it('Input updates value and respects error styling', () => {
    const handleChange = vi.fn();
    render(
      <Input
        placeholder="Enter user email"
        error={true}
        onChange={handleChange}
        data-testid="test-input"
      />
    );

    const input = screen.getByTestId('test-input');
    expect(input).toHaveAttribute('aria-invalid', 'true');
    fireEvent.change(input, { target: { value: 'test@voak.com' } });
    expect(handleChange).toHaveBeenCalled();
  });

  it('FormField renders label, required indicator, and validation error message', () => {
    render(
      <FormField label="Full Name" required error="Name is required">
        <Input placeholder="John Doe" />
      </FormField>
    );

    expect(screen.getByText('Full Name')).toBeInTheDocument();
    expect(screen.getByText('*')).toBeInTheDocument();
    expect(screen.getByText('Name is required')).toBeInTheDocument();
  });

  it('Badge renders different variants cleanly', () => {
    render(
      <div>
        <Badge variant="purple">Purple Badge</Badge>
        <Badge variant="success">Active Status</Badge>
        <Badge variant="error">Critical Error</Badge>
      </div>
    );

    expect(screen.getByText('Purple Badge')).toBeInTheDocument();
    expect(screen.getByText('Active Status')).toBeInTheDocument();
    expect(screen.getByText('Critical Error')).toBeInTheDocument();
  });

  it('Card renders header, title, and content slots', () => {
    render(
      <Card>
        <CardHeader>
          <CardTitle>Card Title Test</CardTitle>
        </CardHeader>
        <CardContent>Card Body Details</CardContent>
      </Card>
    );

    expect(screen.getByText('Card Title Test')).toBeInTheDocument();
    expect(screen.getByText('Card Body Details')).toBeInTheDocument();
  });

  it('Modal opens, displays content, and triggers onClose on close button or Escape', () => {
    const handleClose = vi.fn();
    const { rerender } = render(
      <Modal isOpen={false} onClose={handleClose} title="Test Modal">
        Modal Inner Content
      </Modal>
    );

    expect(screen.queryByText('Modal Inner Content')).not.toBeInTheDocument();

    rerender(
      <Modal isOpen={true} onClose={handleClose} title="Test Modal">
        Modal Inner Content
      </Modal>
    );

    expect(screen.getByText('Modal Inner Content')).toBeInTheDocument();
    expect(screen.getByRole('dialog')).toBeInTheDocument();

    // Click close button
    const closeBtn = screen.getByLabelText(/close dialog/i);
    fireEvent.click(closeBtn);
    expect(handleClose).toHaveBeenCalledTimes(1);

    // Escape key
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(handleClose).toHaveBeenCalledTimes(2);
  });

  it('Alert renders variant and triggers dismiss callback', () => {
    const handleDismiss = vi.fn();
    render(
      <Alert variant="warning" title="Warning Header" onClose={handleDismiss}>
        Check your configuration
      </Alert>
    );

    expect(screen.getByRole('alert')).toBeInTheDocument();
    expect(screen.getByText('Warning Header')).toBeInTheDocument();
    expect(screen.getByText('Check your configuration')).toBeInTheDocument();

    const dismissBtn = screen.getByLabelText(/dismiss alert/i);
    fireEvent.click(dismissBtn);
    expect(handleDismiss).toHaveBeenCalledTimes(1);
  });

  it('EmptyState renders title and action element', () => {
    render(
      <EmptyState
        title="No Records Found"
        description="Try adjusting your search criteria"
        action={<button>Create Record</button>}
      />
    );

    expect(screen.getByText('No Records Found')).toBeInTheDocument();
    expect(screen.getByText('Try adjusting your search criteria')).toBeInTheDocument();
    expect(screen.getByText('Create Record')).toBeInTheDocument();
  });

  it('ErrorState renders error message and triggers onRetry', () => {
    const handleRetry = vi.fn();
    render(
      <ErrorState
        title="Failed to fetch data"
        message="Network connection timed out"
        onRetry={handleRetry}
      />
    );

    expect(screen.getByText('Failed to fetch data')).toBeInTheDocument();
    expect(screen.getByText('Network connection timed out')).toBeInTheDocument();

    const retryBtn = screen.getByRole('button', { name: /try again/i });
    fireEvent.click(retryBtn);
    expect(handleRetry).toHaveBeenCalledTimes(1);
  });

  it('Table renders headers, rows, and cells', () => {
    render(
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>User</TableHead>
            <TableHead>Role</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow>
            <TableCell>Dr. Smith</TableCell>
            <TableCell>Administrator</TableCell>
          </TableRow>
        </TableBody>
      </Table>
    );

    expect(screen.getByText('User')).toBeInTheDocument();
    expect(screen.getByText('Role')).toBeInTheDocument();
    expect(screen.getByText('Dr. Smith')).toBeInTheDocument();
    expect(screen.getByText('Administrator')).toBeInTheDocument();
  });

  it('Pagination renders pages and invokes onPageChange', () => {
    const handlePageChange = vi.fn();
    render(
      <Pagination
        currentPage={2}
        totalPages={5}
        totalItems={50}
        pageSize={10}
        onPageChange={handlePageChange}
      />
    );

    expect(screen.getByText(/showing/i)).toBeInTheDocument();

    // Click next page
    const nextBtn = screen.getByLabelText(/next page/i);
    fireEvent.click(nextBtn);
    expect(handlePageChange).toHaveBeenCalledWith(3);
  });

  it('Breadcrumb renders links and active page item', () => {
    render(
      <Router>
        <Breadcrumb
          items={[
            { label: 'Admin', href: '/admin' },
            { label: 'Security' },
          ]}
        />
      </Router>
    );

    expect(screen.getByText('Admin')).toBeInTheDocument();
    const activeItem = screen.getByText('Security');
    expect(activeItem).toBeInTheDocument();
    expect(activeItem).toHaveAttribute('aria-current', 'page');
  });

  it('ErrorBoundary catches render error and displays recovery UI', () => {
    const ProblemChild = () => {
      throw new Error('Simulated crash in component');
    };

    // Suppress expected console.error during error boundary test
    const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

    render(
      <ErrorBoundary>
        <ProblemChild />
      </ErrorBoundary>
    );

    expect(screen.getByText('Application Error')).toBeInTheDocument();
    expect(screen.getByText(/Simulated crash in component/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /recover view/i })).toBeInTheDocument();

    consoleSpy.mockRestore();
  });
});
