// Клиент API для обоих фронтендов. Токен Sanctum передаётся заголовком Authorization: Bearer.
// Хранилище токена передаётся снаружи: покупатель и сотрудник хранят токены раздельно, на разных доменах.

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: Record<string, string[]> = {},
  ) {
    super(message);
  }
}

export interface TokenStore {
  get(): string | null;
  set(token: string | null): void;
}

export function memoryTokenStore(): TokenStore {
  let token: string | null = null;
  return { get: () => token, set: (t) => { token = t; } };
}

export function storageTokenStore(key: string, storage: () => Storage | undefined): TokenStore {
  return {
    get() {
      try {
        return storage()?.getItem(key) ?? null;
      } catch {
        return null;
      }
    },
    set(token) {
      try {
        if (token) storage()?.setItem(key, token);
        else storage()?.removeItem(key);
      } catch {
        // приватный режим или запрет хранилища: токен живёт только до перезагрузки
      }
    },
  };
}

export interface ApiClientOptions {
  baseUrl: string;
  tokens: TokenStore;
  onUnauthorized?: () => void;
  fetchImpl?: typeof fetch;
}

export type ApiClient = ReturnType<typeof createApiClient>;

export function createApiClient({ baseUrl, tokens, onUnauthorized, fetchImpl }: ApiClientOptions) {
  const base = baseUrl.replace(/\/$/, '');

  async function request<T>(method: string, path: string, body?: unknown, init: RequestInit = {}): Promise<T> {
    const headers: Record<string, string> = { Accept: 'application/json' };
    const token = tokens.get();
    if (token) headers.Authorization = `Bearer ${token}`;
    let payload: BodyInit | undefined;
    if (body instanceof FormData) {
      payload = body;
    } else if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
      payload = JSON.stringify(body);
    }
    const res = await (fetchImpl ?? fetch)(`${base}/api${path}`, { ...init, method, headers: { ...headers, ...(init.headers as Record<string, string>) }, body: payload });
    if (res.status === 204) return undefined as T;
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      if (res.status === 401) {
        tokens.set(null);
        onUnauthorized?.();
      }
      throw new ApiError(res.status, data.message ?? `HTTP ${res.status}`, data.errors ?? {});
    }
    return data as T;
  }

  return {
    tokens,
    get: <T>(path: string, init?: RequestInit) => request<T>('GET', path, undefined, init),
    post: <T>(path: string, body?: unknown, init?: RequestInit) => request<T>('POST', path, body, init),
    put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
    patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body),
    delete: <T>(path: string) => request<T>('DELETE', path),
  };
}
