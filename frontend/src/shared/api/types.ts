/**
 * Standard API Envelope Contracts matching Voak Health Care Backend (Phase 2 Standard)
 */

export interface ApiResponse<T = unknown> {
  success: true;
  data: T;
  meta?: {
    pagination?: {
      total?: number;
      total_records?: number;
      page?: number;
      current_page?: number;
      per_page?: number;
      last_page?: number;
      total_pages?: number;
    };
    [key: string]: unknown;
  };
}

export interface ApiErrorDetail {
  code: string;
  message: string;
  details?: unknown;
  fields?: Record<string, string[]>;
}

export interface ApiErrorResponse {
  success: false;
  error: ApiErrorDetail;
}

export class ApiError extends Error {
  public readonly status: number;
  public readonly code: string;
  public readonly details?: unknown;
  public readonly fields?: Record<string, string[]>;

  constructor(
    status: number,
    code: string,
    message: string,
    details?: unknown,
    fields?: Record<string, string[]>
  ) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.details = details;
    this.fields = fields;
  }

  public isUnauthenticated(): boolean {
    return this.status === 401 || this.code === 'ERR_UNAUTHENTICATED';
  }

  public isForbidden(): boolean {
    return this.status === 403 || this.code === 'ERR_FORBIDDEN';
  }

  public isNotFound(): boolean {
    return this.status === 404 || this.code === 'ERR_NOT_FOUND';
  }

  public isValidation(): boolean {
    return this.status === 422 || this.code === 'ERR_VALIDATION';
  }

  public isRateLimited(): boolean {
    return this.status === 429 || this.code === 'ERR_RATE_LIMIT';
  }
}

export interface RequestOptions extends Omit<RequestInit, 'body'> {
  params?: Record<string, string | number | boolean | undefined | null>;
  body?: unknown;
  skipCsrf?: boolean;
}
