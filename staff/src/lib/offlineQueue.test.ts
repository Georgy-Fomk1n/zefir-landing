import { beforeEach, describe, expect, it } from 'vitest';
import { enqueue, flushQueue, readQueue, toFormData, type QueuedAction } from './offlineQueue';

const action = (id: string, status = 'accepted'): QueuedAction => ({ clientActionId: id, deliveryId: 1, status, happenedAt: '2026-10-27T10:00:00Z' });

describe('офлайн-очередь курьера', () => {
  beforeEach(() => window.localStorage.clear());

  it('отправляет действия по порядку и очищает очередь', async () => {
    enqueue(action('a'));
    enqueue(action('b', 'picked_up'));
    const sent: string[] = [];
    const res = await flushQueue(async (a) => { sent.push(a.clientActionId); });
    expect(sent).toEqual(['a', 'b']);
    expect(res.sent).toBe(2);
    expect(readQueue()).toEqual([]);
  });

  it('при сетевой ошибке оставляет действие в очереди', async () => {
    enqueue(action('a'));
    enqueue(action('b'));
    const res = await flushQueue(async () => { throw new TypeError('Failed to fetch'); });
    expect(res.sent).toBe(0);
    expect(readQueue().map((a) => a.clientActionId)).toEqual(['a', 'b']);
  });

  it('ответ 422 убирает действие и сообщает ошибку, следующие отправляются', async () => {
    enqueue(action('a'));
    enqueue(action('b'));
    const res = await flushQueue(async (a) => {
      if (a.clientActionId === 'a') throw Object.assign(new Error('Переход недопустим'), { status: 422 });
    });
    expect(res).toEqual({ sent: 1, errors: ['Переход недопустим'] });
    expect(readQueue()).toEqual([]);
  });

  it('собирает multipart с временем и геометкой', () => {
    const f = toFormData({ ...action('x', 'delivered'), lat: 55.88, lng: 37.51, codMethod: 'cash', codAmount: 90000 });
    expect(f.get('happenedAt')).toBe('2026-10-27T10:00:00Z');
    expect(f.get('lat')).toBe('55.88');
    expect(f.get('codAmount')).toBe('90000');
  });
});
