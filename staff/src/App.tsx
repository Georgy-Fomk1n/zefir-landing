import { ApiError, type Role, type User } from '@zefir/shared';
import { QueryClient, QueryClientProvider, useQuery, useQueryClient } from '@tanstack/react-query';
import QRCode from 'qrcode';
import { lazy, Suspense, useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { BrowserRouter, Navigate, NavLink, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import { api, tokens } from './lib/api';

const Admin = lazy(() => import('./pages/admin'));
const Seller = lazy(() => import('./pages/seller'));
const Courier = lazy(() => import('./pages/courier'));

const queryClient = new QueryClient({ defaultOptions: { queries: { refetchOnWindowFocus: true, retry: 1 } } });

export function useMe() {
  return useQuery({
    queryKey: ['me'],
    queryFn: () => api.get<{ data: User }>('/staff/auth/me').then((r) => r.data),
    enabled: Boolean(tokens.get()),
    retry: false,
    staleTime: 5 * 60_000,
  });
}

const homeFor = (roles: Role[]) => (roles.includes('admin') ? '/admin' : roles.includes('seller') ? '/seller' : '/courier');

function Login() {
  const navigate = useNavigate();
  const location = useLocation();
  const qc = useQueryClient();
  const [step, setStep] = useState<'password' | 'code' | 'setup'>('password');
  const [creds, setCreds] = useState({ email: '', password: '' });
  const [setup, setSetup] = useState<{ secret: string; qr: string } | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const f = new FormData(e.currentTarget);
    const body = step === 'password'
      ? { email: String(f.get('email')), password: String(f.get('password')) }
      : { ...creds, code: String(f.get('code')) };
    setError(null);
    try {
      const res = await api.post<{ token?: string; user?: User; twoFactor?: 'setup' | 'required'; secret?: string; otpauth?: string }>('/staff/auth/login', body);
      if (res.twoFactor) {
        setCreds({ email: body.email, password: body.password });
        if (res.twoFactor === 'setup' && res.otpauth && res.secret) {
          setSetup({ secret: res.secret, qr: await QRCode.toString(res.otpauth, { type: 'svg', width: 200 }) });
          setStep('setup');
        } else setStep('code');
        return;
      }
      const roles = res.user!.roles;
      tokens.setPersistent(res.token!, roles.length === 1 && roles[0] === 'courier');
      qc.setQueryData(['me'], res.user);
      const next = new URLSearchParams(location.search).get('next');
      navigate(next && next.startsWith('/') ? next : homeFor(res.user!.roles), { replace: true });
    } catch (err) {
      setError(err instanceof ApiError ? Object.values(err.errors)[0]?.[0] ?? err.message : 'Нет связи с сервером');
    }
  }

  return (
    <main className="login">
      <h1>Вход для сотрудников</h1>
      <form onSubmit={submit} className="stack">
        {step === 'password' && (
          <>
            <label>Email<input name="email" type="email" required autoComplete="username" /></label>
            <label>Пароль<input name="password" type="password" required autoComplete="current-password" /></label>
          </>
        )}
        {step === 'setup' && setup && (
          <div className="stack">
            <p>Для администратора обязательна двухфакторная аутентификация. Отсканируйте QR в приложении-аутентификаторе (Яндекс Ключ, Google Authenticator) и введите код.</p>
            <div className="qr" dangerouslySetInnerHTML={{ __html: setup.qr }} />
            <p className="muted">Ключ вручную: <code>{setup.secret}</code></p>
          </div>
        )}
        {step !== 'password' && <label>Код из приложения<input name="code" inputMode="numeric" pattern="\d{6}" required autoComplete="one-time-code" autoFocus /></label>}
        {error && <p className="error">{error}</p>}
        <button type="submit" className="btn">Войти</button>
      </form>
    </main>
  );
}

function Shell({ user, children }: { user: User; children: ReactNode }) {
  const qc = useQueryClient();
  const navigate = useNavigate();
  async function logout() {
    await api.post('/staff/auth/logout').catch(() => undefined);
    tokens.set(null);
    qc.clear();
    navigate('/login');
  }
  return (
    <div className="shell">
      <header className="topbar">
        <strong>Зефир и пряник · штат</strong>
        <nav>
          {user.roles.includes('admin') && <NavLink to="/admin">Админ</NavLink>}
          {(user.roles.includes('seller') || user.roles.includes('admin')) && <NavLink to="/seller">Продавец</NavLink>}
          {user.roles.includes('courier') && <NavLink to="/courier">Курьер</NavLink>}
        </nav>
        <span className="muted">{user.name}</span>
        <button type="button" className="link" onClick={logout}>Выйти</button>
      </header>
      <Suspense fallback={<p className="pad">Загрузка…</p>}>{children}</Suspense>
    </div>
  );
}

function Guard({ roles, children }: { roles: Role[]; children: ReactNode }) {
  const me = useMe();
  const location = useLocation();
  if (!tokens.get() || me.isError) return <Navigate to={`/login?next=${encodeURIComponent(location.pathname)}`} replace />;
  if (!me.data) return <p className="pad">Загрузка…</p>;
  if (!roles.some((r) => me.data.roles.includes(r))) return <Navigate to={homeFor(me.data.roles)} replace />;
  return <Shell user={me.data}>{children}</Shell>;
}

function Home() {
  const me = useMe();
  if (!tokens.get()) return <Navigate to="/login" replace />;
  return me.data ? <Navigate to={homeFor(me.data.roles)} replace /> : <p className="pad">Загрузка…</p>;
}

export default function App() {
  useEffect(() => {
    if ('serviceWorker' in navigator && import.meta.env.PROD) navigator.serviceWorker.register('/sw.js').catch(() => undefined);
  }, []);
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/admin/*" element={<Guard roles={['admin']}><Admin /></Guard>} />
          <Route path="/seller/*" element={<Guard roles={['seller', 'admin']}><Seller /></Guard>} />
          <Route path="/courier/*" element={<Guard roles={['courier']}><Courier /></Guard>} />
          <Route path="*" element={<Home />} />
        </Routes>
      </BrowserRouter>
    </QueryClientProvider>
  );
}
