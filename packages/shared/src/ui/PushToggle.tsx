import { useEffect, useState } from 'react';
import { currentPushEndpoint, disablePush, enablePush, pushSupport, pushSupportText, type PushApi, type PushSupport } from '../push';

interface Props {
  api: PushApi;
  /** Подпись, что именно будет приходить */
  description: string;
  className?: string;
}

/**
 * Включение/выключение push-уведомлений. На iPhone вне веб-приложения показывает инструкцию
 * «На экран „Домой“» вместо кнопки (иначе Safari не даст подписаться).
 */
export function PushToggle({ api, description, className }: Props) {
  const [support, setSupport] = useState<PushSupport | null>(null);
  const [enabled, setEnabled] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    setSupport(pushSupport());
    currentPushEndpoint().then((e) => setEnabled(Boolean(e))).catch(() => setEnabled(false));
  }, []);

  if (support === null) return null;

  async function toggle() {
    setBusy(true);
    setMessage(null);
    try {
      if (enabled) {
        await disablePush(api);
        setEnabled(false);
      } else {
        const res = await enablePush(api);
        if (res === 'enabled') setEnabled(true);
        else if (res === 'denied') {
          setSupport('denied');
        } else setMessage('Уведомления временно недоступны.');
      }
    } catch {
      setMessage('Не удалось изменить настройку уведомлений. Попробуйте ещё раз.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className={className} data-testid="push-toggle">
      <p>{description}</p>
      {support === 'supported' ? (
        <button type="button" className="btn btn-sm" aria-pressed={enabled} disabled={busy} onClick={toggle}>
          {enabled ? 'Выключить уведомления' : 'Включить уведомления'}
        </button>
      ) : (
        <p className="muted" role="note">{pushSupportText[support]}</p>
      )}
      {message && <p className="error" role="alert">{message}</p>}
    </div>
  );
}
