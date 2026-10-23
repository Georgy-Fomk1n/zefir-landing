import type { CartLine, User } from '@zefir/shared';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';
import { api, tokens } from './api';
import { useCart } from './cart';
import { reachGoal } from './metrika';

interface AuthResponse {
  token: string;
  user: User;
}

export function useAuth() {
  const qc = useQueryClient();
  const cart = useCart();
  const hasToken = typeof window !== 'undefined' && Boolean(tokens.get());

  const me = useQuery({
    queryKey: ['me'],
    queryFn: () => api.get<{ data: User }>('/auth/me').then((r) => r.data),
    enabled: hasToken,
    retry: false,
    staleTime: 60_000,
  });

  const afterAuth = useCallback(async (res: AuthResponse) => {
    tokens.set(res.token);
    qc.setQueryData(['me'], res.user);
    // Слияние гостевой корзины с серверной
    const merged = await api.post<{ data: CartLine[] }>('/cart/merge', { lines: cart.lines });
    cart.replace(merged.data);
  }, [qc, cart]);

  const login = useCallback(async (email: string, password: string) => {
    await afterAuth(await api.post<AuthResponse>('/auth/login', { email, password }));
  }, [afterAuth]);

  const register = useCallback(async (payload: { name: string; email: string; phone?: string; password: string; consent: boolean; marketingConsent?: boolean }) => {
    await afterAuth(await api.post<AuthResponse>('/auth/register', payload));
    reachGoal('register');
  }, [afterAuth]);

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout');
    } finally {
      tokens.set(null);
      qc.clear();
    }
  }, [qc]);

  return { user: me.data ?? null, loading: hasToken && me.isLoading, login, register, logout };
}
