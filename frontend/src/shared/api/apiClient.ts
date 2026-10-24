import { ApiError, type ApiResponse, type ApiErrorResponse, type RequestOptions } from './types';

class ApiClient {
  private baseUrl: string;
  private csrfToken: string | null = null;
  private csrfPromise: Promise<string> | null = null;

  constructor() {
    this.baseUrl = (import.meta.env.VITE_API_BASE_URL as string | undefined) || '/api/v1';
  }

  public setBaseUrl(url: string): void {
    this.baseUrl = url;
  }

  public getBaseUrl(): string {
    return this.baseUrl;
  }

  public setCsrfToken(token: string | null): void {
    this.csrfToken = token;
  }

  public getCsrfToken(): string | null {
    return this.csrfToken;
  }

  /**
   * Fetches bootstrap CSRF token from canonical backend endpoint:
   * GET /api/v1/admin/auth/csrf
   */
  public async fetchCsrfToken(): Promise<string> {
    if (this.csrfPromise) {
      return this.csrfPromise;
    }

    this.csrfPromise = (async () => {
      try {
        const url = `${this.baseUrl}/admin/auth/csrf`;
        const res = await fetch(url, {
          method: 'GET',
          credentials: 'include',
          headers: {
            Accept: 'application/json',
          },
        });

        if (!res.ok) {
          throw new Error(`Failed to fetch CSRF token (HTTP ${res.status})`);
        }

        const data = (await res.json()) as ApiResponse<{ csrf_token: string }>;
        if (data.success && data.data?.csrf_token) {
          this.csrfToken = data.data.csrf_token;
          return this.csrfToken;
        }

        throw new Error('CSRF response did not contain token');
      } finally {
        this.csrfPromise = null;
      }
    })();

    return this.csrfPromise;
  }

  /**
   * Executes an HTTP request with session credentials and automatic CSRF handling.
   */
  public async request<T = unknown>(
    endpoint: string,
    options: RequestOptions = {}
  ): Promise<ApiResponse<T>> {
    const { params, body, skipCsrf = false, headers: customHeaders, ...restOptions } = options;
    const method = (restOptions.method || 'GET').toUpperCase();
    const isMutating = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);

    // Build URL with query params
    let url = endpoint.startsWith('http')
      ? endpoint
      : endpoint.startsWith(this.baseUrl)
      ? endpoint
      : `${this.baseUrl}${endpoint.startsWith('/') ? '' : '/'}${endpoint}`;
    if (params) {
      const searchParams = new URLSearchParams();
      for (const [key, val] of Object.entries(params)) {
        if (val !== undefined && val !== null) {
          searchParams.append(key, String(val));
        }
      }
      const qs = searchParams.toString();
      if (qs) {
        url += (url.includes('?') ? '&' : '?') + qs;
      }
    }

    // Acquire CSRF token if mutating and not skipped
    if (isMutating && !skipCsrf && !this.csrfToken) {
      try {
        await this.fetchCsrfToken();
      } catch {
        // Fail open to let request proceed; backend will validate and emit 403 if missing
      }
    }

    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...((customHeaders as Record<string, string>) || {}),
    };

    if (isMutating && !skipCsrf && this.csrfToken) {
      headers['X-CSRF-Token'] = this.csrfToken;
    }

    let requestBody: BodyInit | undefined;
    if (body !== undefined && body !== null) {
      if (body instanceof FormData) {
        requestBody = body;
        // Let browser set multipart/form-data boundary automatically
        delete headers['Content-Type'];
      } else if (typeof body === 'string') {
        requestBody = body;
        if (!headers['Content-Type']) {
          headers['Content-Type'] = 'application/json';
        }
      } else {
        requestBody = JSON.stringify(body);
        if (!headers['Content-Type']) {
          headers['Content-Type'] = 'application/json';
        }
      }
    }

    const response = await fetch(url, {
      ...restOptions,
      method,
      headers,
      body: requestBody,
      credentials: 'include',
    });

    // Check for CSRF retry condition: 403 with ERR_CSRF_INVALID or ERR_CSRF_MISSING
    if (response.status === 403 && isMutating && !skipCsrf) {
      const clone = response.clone();
      try {
        const errorData = (await clone.json()) as ApiErrorResponse;
        if (
          errorData?.error?.code === 'ERR_CSRF_INVALID' ||
          errorData?.error?.code === 'ERR_CSRF_MISSING'
        ) {
          // Re-fetch token and retry once
          this.csrfToken = null;
          await this.fetchCsrfToken();
          return this.request<T>(endpoint, { ...options, skipCsrf: false });
        }
      } catch {
        // Continue to regular error handling
      }
    }

    // Process non-OK response
    if (!response.ok) {
      let errorResponse: ApiErrorResponse | null = null;
      try {
        errorResponse = (await response.json()) as ApiErrorResponse;
      } catch {
        errorResponse = null;
      }

      if (errorResponse && errorResponse.error) {
        throw new ApiError(
          response.status,
          errorResponse.error.code || 'ERR_UNKNOWN',
          errorResponse.error.message || `Request failed with status ${response.status}`,
          errorResponse.error.details,
          errorResponse.error.fields
        );
      }

      throw new ApiError(
        response.status,
        `ERR_HTTP_${response.status}`,
        response.statusText || `HTTP error ${response.status}`
      );
    }

    // Success response
    const json = (await response.json()) as ApiResponse<T>;
    return json;
  }

  public get<T = unknown>(endpoint: string, options?: RequestOptions): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, { ...options, method: 'GET' });
  }

  public post<T = unknown>(
    endpoint: string,
    body?: unknown,
    options?: RequestOptions
  ): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, { ...options, method: 'POST', body });
  }

  public put<T = unknown>(
    endpoint: string,
    body?: unknown,
    options?: RequestOptions
  ): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, { ...options, method: 'PUT', body });
  }

  public patch<T = unknown>(
    endpoint: string,
    body?: unknown,
    options?: RequestOptions
  ): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, { ...options, method: 'PATCH', body });
  }

  public delete<T = unknown>(endpoint: string, options?: RequestOptions): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, { ...options, method: 'DELETE' });
  }
}

export const apiClient = new ApiClient();
export { ApiClient };
