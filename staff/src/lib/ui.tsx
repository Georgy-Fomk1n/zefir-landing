import { ApiError, formatPrice } from '@zefir/shared';
import { useMutation, useQuery, useQueryClient, type QueryKey } from '@tanstack/react-query';
import { useState, type ReactNode } from 'react';
import { api } from './api';

export const rub = (kopecks: number | null | undefined) => (kopecks == null ? '—' : formatPrice(kopecks));

export function errorText(e: unknown): string | null {
  if (!e) return null;
  if (e instanceof ApiError) return Object.values(e.errors)[0]?.[0] ?? e.message;
  return 'Нет связи с сервером';
}

export function useList<T>(key: QueryKey, path: string) {
  return useQuery({ queryKey: key, queryFn: () => api.get<{ data: T[] }>(path).then((r) => r.data) });
}

/** Мутация с инвалидацией списка */
export function useSave<B>(key: QueryKey, fn: (body: B) => Promise<unknown>) {
  const qc = useQueryClient();
  return useMutation({ mutationFn: fn, onSuccess: () => qc.invalidateQueries({ queryKey: key }) });
}

export function Section({ title, actions, children }: { title: string; actions?: ReactNode; children: ReactNode }) {
  return (
    <section className="card">
      <div className="card__head"><h2>{title}</h2>{actions}</div>
      {children}
    </section>
  );
}

/**
 * Простой редактор записи в JSON — для редко меняемых структур (многоугольники зон, тарифы),
 * где отдельная форма не окупается. Сервер валидирует всё содержимое.
 */
export function JsonEditor({ value, onSave, busy, error }: { value: unknown; onSave: (v: unknown) => void; busy?: boolean; error?: unknown }) {
  const [text, setText] = useState(() => JSON.stringify(value, null, 2));
  const [parseError, setParseError] = useState<string | null>(null);
  return (
    <div className="stack">
      <textarea className="mono" rows={Math.min(20, text.split('\n').length + 1)} value={text} onChange={(e) => setText(e.target.value)} />
      {(parseError || error) ? <p className="error">{parseError ?? errorText(error)}</p> : null}
      <button type="button" className="btn btn-sm" disabled={busy} onClick={() => {
        try {
          const parsed = JSON.parse(text);
          setParseError(null);
          onSave(parsed);
        } catch {
          setParseError('Некорректный JSON');
        }
      }}>Сохранить</button>
    </div>
  );
}
