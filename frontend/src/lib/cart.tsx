import type { CartLine } from '@zefir/shared';
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { reachGoal } from './metrika';

const KEY = 'zp_cart';

function read(): CartLine[] {
  try {
    const raw = window.localStorage.getItem(KEY);
    const parsed = raw ? (JSON.parse(raw) as CartLine[]) : [];
    return Array.isArray(parsed) ? parsed.filter((l) => l.qty > 0 && Number.isInteger(l.variantId)) : [];
  } catch {
    return [];
  }
}

function write(lines: CartLine[]) {
  try {
    window.localStorage.setItem(KEY, JSON.stringify(lines));
  } catch {
    // корзина остаётся в памяти вкладки
  }
}

/** Объединяет гостевую корзину с серверной: количество берётся максимальное, чтобы не удвоить товар. */
export function mergeLines(a: CartLine[], b: CartLine[]): CartLine[] {
  const map = new Map<number, number>();
  for (const l of [...a, ...b]) map.set(l.variantId, Math.max(map.get(l.variantId) ?? 0, l.qty));
  return [...map.entries()].map(([variantId, qty]) => ({ variantId, qty }));
}

interface CartContextValue {
  lines: CartLine[];
  count: number;
  add(variantId: number, qty?: number): void;
  setQty(variantId: number, qty: number): void;
  remove(variantId: number): void;
  replace(lines: CartLine[]): void;
  clear(): void;
}

const CartContext = createContext<CartContextValue | null>(null);

export function CartProvider({ children }: { children: ReactNode }) {
  // На этапе пререндера корзина пустая; содержимое подтягивается после гидратации.
  const [lines, setLines] = useState<CartLine[]>([]);

  useEffect(() => {
    setLines(read());
    const onStorage = (e: StorageEvent) => e.key === KEY && setLines(read());
    window.addEventListener('storage', onStorage);
    return () => window.removeEventListener('storage', onStorage);
  }, []);

  const update = useCallback((fn: (prev: CartLine[]) => CartLine[]) => {
    setLines((prev) => {
      const next = fn(prev).filter((l) => l.qty > 0);
      write(next);
      return next;
    });
  }, []);

  const value = useMemo<CartContextValue>(() => ({
    lines,
    count: lines.reduce((s, l) => s + l.qty, 0),
    add(variantId, qty = 1) {
      reachGoal('add_to_cart', { variantId });
      update((prev) => {
        const found = prev.find((l) => l.variantId === variantId);
        return found
          ? prev.map((l) => (l.variantId === variantId ? { ...l, qty: l.qty + qty } : l))
          : [...prev, { variantId, qty }];
      });
    },
    setQty: (variantId, qty) => update((prev) => prev.map((l) => (l.variantId === variantId ? { ...l, qty } : l))),
    remove: (variantId) => update((prev) => prev.filter((l) => l.variantId !== variantId)),
    replace: (next) => update(() => next),
    clear: () => update(() => []),
  }), [lines, update]);

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartContextValue {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error('useCart вне CartProvider');
  return ctx;
}
