import { Component, ErrorInfo, ReactNode } from 'react';
import { AlertCircle, RotateCcw } from 'lucide-react';
import { Button } from './Button';

export interface ErrorBoundaryProps {
  children: ReactNode;
  fallback?: ReactNode | ((error: Error, reset: () => void) => ReactNode);
}

interface ErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
}

export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  public override state: ErrorBoundaryState = {
    hasError: false,
    error: null,
  };

  public static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { hasError: true, error };
  }

  public override componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    // In production or development, could report to telemetry
    console.error('Uncaught error in component tree:', error, errorInfo);
  }

  private handleReset = () => {
    this.setState({ hasError: false, error: null });
  };

  public override render(): ReactNode {
    if (this.state.hasError) {
      if (typeof this.props.fallback === 'function') {
        return this.props.fallback(this.state.error ?? new Error('Unknown error'), this.handleReset);
      }
      if (this.props.fallback) {
        return this.props.fallback;
      }

      return (
        <div className="min-h-[300px] flex items-center justify-center p-6">
          <div className="max-w-md w-full text-center p-8 rounded-2xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/50 dark:bg-rose-950/20 shadow-sm">
            <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 mb-4">
              <AlertCircle className="h-8 w-8" />
            </div>
            <h2 className="text-xl font-bold text-rose-950 dark:text-rose-100 mb-2">
              Application Error
            </h2>
            <p className="text-sm text-rose-700 dark:text-rose-300 mb-6">
              An unexpected client error occurred. You can attempt to refresh this section or reload the application.
            </p>
            {this.state.error && (
              <div className="text-xs font-mono bg-white dark:bg-neutral-900 p-3 rounded-lg border border-rose-200 dark:border-rose-900/50 text-left text-neutral-600 dark:text-neutral-400 mb-6 overflow-x-auto max-h-32">
                {this.state.error.message}
              </div>
            )}
            <div className="flex justify-center gap-3">
              <Button
                variant="primary"
                onClick={this.handleReset}
                leftIcon={<RotateCcw className="h-4 w-4" />}
              >
                Recover View
              </Button>
              <Button
                variant="outline"
                onClick={() => window.location.reload()}
              >
                Reload App
              </Button>
            </div>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}
