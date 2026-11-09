import { channels } from '@zefir/shared';
import { act, fireEvent, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { productBySlug } from '../data/catalog';
import { LazyVideo, mediaUrl, ogImage, Picture, preloadSrcSet } from '../media';
import { renderApp } from '../test/render';
import { Breadcrumbs, ChannelButtons, crumbs, FaqList, Hours, LazyIframe, OpeningStatus, ProductCard, Section } from './common';
import { Layout } from './Layout';

describe('карточка товара', () => {
  it('ведёт на каноническую карточку, показывает цену «от» для наборов', () => {
    const { container } = renderApp(<ProductCard product={productBySlug('nabor-10')!} priority />);
    const links = screen.getAllByRole('link');
    expect(links.every((a) => a.getAttribute('href') === '/catalog/nabory/nabor-10/')).toBe(true);
    expect(container.querySelector('.price')?.textContent).toBe('от 2\u00a0200\u00a0₽');
    const img = screen.getByRole('img');
    expect(img).toHaveAttribute('loading', 'eager');
    expect(img).toHaveAttribute('fetchpriority', 'high');
    expect(img).toHaveAttribute('width');
    expect(img).toHaveAttribute('height');
  });

  it('по умолчанию картинка ленивая, заголовок h3', () => {
    renderApp(<ProductCard product={productBySlug('kofe')!} />);
    expect(screen.getByRole('img')).toHaveAttribute('loading', 'lazy');
    expect(screen.getByRole('heading', { level: 3, name: 'Кофе' })).toBeInTheDocument();
  });
});

describe('медиа', () => {
  it('строит srcset AVIF/WebP и подбирает ширину', () => {
    const { container } = renderApp(<Picture name="kofeynye-zerna" alt="Кофе" sizes="100vw" />);
    const sources = container.querySelectorAll('source');
    expect(sources[0].getAttribute('type')).toBe('image/avif');
    expect(sources[0].getAttribute('srcset')).toContain('/media/kofeynye-zerna-400.avif 400w');
    expect(mediaUrl('kofeynye-zerna', 700)).toBe('/media/kofeynye-zerna-600.webp');
    expect(mediaUrl('kofeynye-zerna', 100)).toBe('/media/kofeynye-zerna-400.webp');
    expect(mediaUrl('pp-malina', 800)).toBe('/media/pp-malina-poster.webp');
    expect(preloadSrcSet('banan')).toContain('banan-400.avif');
    expect(preloadSrcSet('nope')).toBe('');
    expect(ogImage('chernika')).toBe('/media/chernika-og.jpg');
    expect(ogImage('nope')).toBe('/media/banan-razrez-og.jpg');
  });

  it('для товара без фото показывает постер видео', () => {
    renderApp(<Picture name="pp-malina" alt="ПП-малина" sizes="100vw" priority />);
    expect(screen.getByRole('img')).toHaveAttribute('src', '/media/pp-malina-poster.webp');
  });

  it('видео подгружает источники только у экрана, WebM и MP4', () => {
    const { container } = renderApp(<LazyVideo name="pp-banan" label="ПП-банан" />);
    const video = container.querySelector('video')!;
    expect(video).toHaveAttribute('preload', 'none');
    expect(video).toHaveAttribute('poster', '/media/pp-banan-poster.webp');
    expect(video.hasAttribute('playsinline')).toBe(true); // обязательно для iPhone
    expect([...video.querySelectorAll('source')].map((s) => s.getAttribute('type'))).toEqual(['video/webm', 'video/mp4']);
  });

  it('видео не грузится при «уменьшении движения»', () => {
    window.matchMedia = ((q: string) => ({ matches: q.includes('reduce'), media: q }) as MediaQueryList);
    const { container } = renderApp(<LazyVideo name="bento" label="Бенто" />);
    expect(container.querySelectorAll('source')).toHaveLength(0);
  });

  it('iframe виджета грузится лениво', () => {
    renderApp(<LazyIframe src="https://yandex.ru/map" title="Карта" height={300} />);
    expect(screen.getByTitle('Карта')).toHaveAttribute('loading', 'lazy');
  });
});

describe('навигация и контент', () => {
  it('хлебные крошки: последняя — текущая страница без ссылки', () => {
    const bc = crumbs({ name: 'Каталог', path: '/catalog/' }, { name: 'Вкусы', path: '/catalog/vkusy/' });
    renderApp(<Breadcrumbs items={bc.items} />);
    const nav = screen.getByRole('navigation', { name: 'Хлебные крошки' });
    expect(within(nav).getAllByRole('link').map((a) => a.textContent)).toEqual(['Главная', 'Каталог']);
    expect(within(nav).getByText('Вкусы')).toHaveAttribute('aria-current', 'page');
    expect(bc.jsonLd).toMatchObject({ '@type': 'BreadcrumbList' });
  });

  it('FAQ работает без JS (details/summary)', () => {
    const { container } = renderApp(<FaqList items={[{ q: 'Вопрос?', a: 'Ответ.' }]} />);
    expect(container.querySelector('details summary')?.textContent).toBe('Вопрос?');
    expect(screen.getByText('Ответ.')).toBeInTheDocument();
  });

  it('кнопки каналов ведут наружу и отправляют цель Метрики', () => {
    const ym = vi.fn();
    window.ym = ym as unknown as Window['ym'];
    renderApp(<ChannelButtons />);
    const tg = screen.getByRole('link', { name: /Telegram/ });
    expect(tg).toHaveAttribute('href', 'https://t.me/Krasolda_Tihonovna');
    expect(screen.getAllByRole('link')).toHaveLength(channels.length);
    fireEvent.click(tg);
    delete window.ym;
  });

  it('статус «открыто/закрыто» по московскому времени', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-10-27T12:00:00+03:00'));
    renderApp(<OpeningStatus />);
    expect(screen.getByText('Сейчас открыто')).toBeInTheDocument();
    act(() => {
      vi.setSystemTime(new Date('2026-10-27T22:00:00+03:00'));
      vi.advanceTimersByTime(60_000);
    });
    expect(screen.getByText('Сейчас закрыто')).toBeInTheDocument();
    vi.useRealTimers();
  });

  it('часы работы и секция', () => {
    renderApp(<Section title="Заголовок" lead="Подзаголовок" as="h1"><Hours /></Section>);
    expect(screen.getByRole('heading', { level: 1, name: 'Заголовок' })).toBeInTheDocument();
    expect(screen.getByText('Пн: выходной')).toBeInTheDocument();
    expect(screen.getByText('Вт-Вс: 10:00 – 21:00')).toBeInTheDocument();
  });
});

describe('макет', () => {
  it('меню открывается кнопкой, корзина показывает количество, cookie-баннер запоминает выбор', () => {
    window.localStorage.setItem('zp_cart', JSON.stringify([{ variantId: 1, qty: 3 }]));
    renderApp(<Layout />);
    expect(screen.getByRole('link', { name: 'Корзина, товаров: 3' })).toBeInTheDocument();
    const burger = screen.getByRole('button', { name: 'Меню' });
    expect(burger).toHaveAttribute('aria-expanded', 'false');
    fireEvent.click(burger);
    expect(burger).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getByRole('navigation', { name: 'Основное меню' })).toHaveClass('main-nav--open');

    fireEvent.click(screen.getByRole('button', { name: 'Только необходимые' }));
    expect(window.localStorage.getItem('zp_cookie_consent')).toBe('no');
    expect(screen.queryByRole('dialog', { name: 'Согласие на cookie' })).toBeNull();
    // Реквизиты ИП в подвале
    expect(screen.getByText(/ОГРНИП 323774600785438/)).toBeInTheDocument();
  });

  it('согласие на cookie сохраняется', () => {
    renderApp(<Layout />);
    fireEvent.click(screen.getByRole('button', { name: 'Согласен' }));
    expect(window.localStorage.getItem('zp_cookie_consent')).toBe('yes');
  });
});
