import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { apiClient } from '../src/shared/api/apiClient';
import { ApiError } from '../src/shared/api/types';

describe('apiClient', () => {
  const originalFetch = global.fetch;

  beforeEach(() => {
    apiClient.setCsrfToken(null);
    vi.restoreAllMocks();
  });

  afterEach(() => {
    global.fetch = originalFetch;
  });

  it('performs GET request and parses JSON response envelope', async () => {
    const mockData = { user: { id: 1, name: 'Admin User' } };
    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ success: true, data: mockData }),
    });

    const res = await apiClient.get('/api/v1/admin/auth/me');

    expect(global.fetch).toHaveBeenCalledTimes(1);
    const [url, options] = (global.fetch as any).mock.calls[0];
    expect(url).toBe('/api/v1/admin/auth/me');
    expect(options.method).toBe('GET');
    expect(options.credentials).toBe('include');
    expect(res.data).toEqual(mockData);
  });

  it('appends query parameters correctly', async () => {
    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ success: true, data: [] }),
    });

    await apiClient.get('/api/v1/admin/users', {
      params: { page: 2, limit: 25, search: 'dr smith' },
    });

    const [url] = (global.fetch as any).mock.calls[0];
    expect(url).toBe('/api/v1/admin/users?page=2&limit=25&search=dr+smith');
  });

  it('fetches CSRF token before executing state-changing POST request if token is not cached', async () => {
    // 1st call: GET /api/v1/admin/auth/csrf
    // 2nd call: POST /api/v1/admin/test
    global.fetch = vi
      .fn()
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({ success: true, data: { csrf_token: 'fresh-csrf-token-123' } }),
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({ success: true, data: { status: 'created' } }),
      });

    const res = await apiClient.post('/api/v1/admin/test', { name: 'Test Item' });

    expect(global.fetch).toHaveBeenCalledTimes(2);

    // CSRF acquisition check
    const [csrfUrl] = (global.fetch as any).mock.calls[0];
    expect(csrfUrl).toBe('/api/v1/admin/auth/csrf');

    // POST request check
    const [postUrl, postOptions] = (global.fetch as any).mock.calls[1];
    expect(postUrl).toBe('/api/v1/admin/test');
    expect(postOptions.method).toBe('POST');
    expect(postOptions.headers['X-CSRF-Token']).toBe('fresh-csrf-token-123');
    expect(postOptions.headers['Content-Type']).toBe('application/json');
    expect(JSON.parse(postOptions.body)).toEqual({ name: 'Test Item' });
    expect(res.data).toEqual({ status: 'created' });
  });

  it('reuses cached CSRF token for subsequent requests without re-fetching', async () => {
    apiClient.setCsrfToken('cached-token-abc');

    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ success: true, data: { updated: true } }),
    });

    await apiClient.put('/api/v1/admin/items/1', { title: 'New Title' });

    expect(global.fetch).toHaveBeenCalledTimes(1);
    const [, options] = (global.fetch as any).mock.calls[0];
    expect(options.headers['X-CSRF-Token']).toBe('cached-token-abc');
  });

  it('handles multipart FormData correctly without overriding Content-Type', async () => {
    apiClient.setCsrfToken('valid-token');

    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ success: true, data: { uploaded: true } }),
    });

    const formData = new FormData();
    formData.append('document', 'sample-content');

    await apiClient.post('/api/v1/admin/upload', formData);

    const [, options] = (global.fetch as any).mock.calls[0];
    expect(options.headers['X-CSRF-Token']).toBe('valid-token');
    // Content-Type should not be set manually for FormData so browser sets boundary
    expect(options.headers['Content-Type']).toBeUndefined();
  });

  it('throws ApiError on 401 unauthenticated response', async () => {
    global.fetch = vi.fn().mockResolvedValue({
      ok: false,
      status: 401,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({
        success: false,
        error: { code: 'UNAUTHENTICATED', message: 'Session expired or invalid' },
      }),
    });

    await expect(apiClient.get('/api/v1/admin/me')).rejects.toThrow(ApiError);
  });

  it('throws ApiError with validation details on 422 Unprocessable Entity', async () => {
    apiClient.setCsrfToken('valid-token');

    global.fetch = vi.fn().mockResolvedValue({
      ok: false,
      status: 422,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({
        success: false,
        error: {
          code: 'VALIDATION_FAILED',
          message: 'The submitted data was invalid.',
          details: { email: ['Invalid email format'] },
        },
      }),
    });

    try {
      await apiClient.post('/api/v1/admin/users', { email: 'bad' });
      expect.fail('Should have thrown ApiError');
    } catch (err: any) {
      expect(err).toBeInstanceOf(ApiError);
      expect(err.status).toBe(422);
      expect(err.code).toBe('VALIDATION_FAILED');
      expect(err.details).toEqual({ email: ['Invalid email format'] });
    }
  });
});
