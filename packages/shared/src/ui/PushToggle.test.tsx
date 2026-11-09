import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1';

afterEach(() => {
  vi.doUnmock('../push');
  vi.resetModules();
});

async function load(support: string, enableResult: 'enabled' | 'denied' | 'no-key' | Error = 'enabled', current: string | null = null) {
  vi.resetModules();
  const actual = await vi.importActual<typeof import('../push')>('../push');
  const enablePush = vi.fn(async () => {
    if (enableResult instanceof Error) throw enableResult;
    return enableResult;
  });
  const disablePush = vi.fn().mockResolvedValue(undefined);
  vi.doMock('../push', () => ({ ...actual, pushSupport: () => support, enablePush, disablePush, currentPushEndpoint: vi.fn().mockResolvedValue(current) }));
  const { PushToggle } = await import('./PushToggle');
  const api = { publicKey: vi.fn(), save: vi.fn(), remove: vi.fn() };
  return { PushToggle, api, enablePush, disablePush };
}

describe('PushToggle', () => {
  it('на iPhone вне веб-приложения объясняет, как добавить на экран «Домой»', async () => {
    Object.defineProperty(navigator, 'userAgent', { configurable: true, value: IPHONE });
    const { PushToggle, api } = await load('ios-install');
    render(<PushToggle api={api} description="Статусы заказов" />);
    expect(await screen.findByRole('note')).toHaveTextContent('На экран „Домой“');
    expect(screen.queryByRole('button')).toBeNull();
  });

  it('включает и выключает уведомления', async () => {
    const { PushToggle, api, enablePush, disablePush } = await load('supported');
    render(<PushToggle api={api} description="Статусы заказов" />);
    const btn = await screen.findByRole('button', { name: 'Включить уведомления' });
    fireEvent.click(btn);
    await waitFor(() => expect(screen.getByRole('button', { name: 'Выключить уведомления' })).toHaveAttribute('aria-pressed', 'true'));
    expect(enablePush).toHaveBeenCalledWith(api);
    fireEvent.click(screen.getByRole('button', { name: 'Выключить уведомления' }));
    await waitFor(() => expect(disablePush).toHaveBeenCalled());
    expect(await screen.findByRole('button', { name: 'Включить уведомления' })).toBeInTheDocument();
  });

  it('отказ в разрешении и ошибки показываются пользователю', async () => {
    const denied = await load('supported', 'denied');
    const { unmount } = render(<denied.PushToggle api={denied.api} description="x" />);
    fireEvent.click(await screen.findByRole('button', { name: 'Включить уведомления' }));
    expect(await screen.findByRole('note')).toHaveTextContent('запрещены');
    unmount();

    const noKey = await load('supported', 'no-key');
    const r2 = render(<noKey.PushToggle api={noKey.api} description="x" />);
    fireEvent.click(await screen.findByRole('button', { name: 'Включить уведомления' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('временно недоступны');
    r2.unmount();

    const failing = await load('supported', new Error('boom'));
    render(<failing.PushToggle api={failing.api} description="x" />);
    fireEvent.click(await screen.findByRole('button', { name: 'Включить уведомления' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Не удалось');
  });

  it('уже подписанное устройство показывает «Выключить»', async () => {
    const { PushToggle, api } = await load('supported', 'enabled', 'https://push.test/1');
    render(<PushToggle api={api} description="x" />);
    expect(await screen.findByRole('button', { name: 'Выключить уведомления' })).toBeInTheDocument();
  });
});
