import { brand, channels } from '@zefir/shared';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, ScrollRestoration } from 'react-router-dom';
import { footer } from '../content/texts';
import { mainNav, pages } from '../content/pages';
import { categories, categoryUrl } from '../data/catalog';
import { CartProvider, useCart } from '../lib/cart';
import { hasConsent, scheduleMetrika, setConsent } from '../lib/metrika';
import { ChannelIcon, PhoneIcon } from './icons';

const queryClient = new QueryClient({ defaultOptions: { queries: { refetchOnWindowFocus: false } } });

function CartLink() {
  const { count } = useCart();
  return (
    <Link to="/cart/" className="cart-link" aria-label={`Корзина, товаров: ${count}`}>
      <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="2"><path d="M6 6h15l-1.5 9h-12z" /><path d="M6 6 5 3H2" /><circle cx="9" cy="20" r="1.5" /><circle cx="18" cy="20" r="1.5" /></svg>
      {count > 0 && <span className="cart-link__count">{count}</span>}
    </Link>
  );
}

function Header() {
  const [open, setOpen] = useState(false);
  return (
    <header className="site-header">
      <div className="site-header__bar">
        <Link to="/" className="logo" aria-label={`${brand.name} — на главную`}>
          <img src="/media/logo-160.webp" alt="Зефир и пряник - логотип кондитерской" width="80" height="65" />
        </Link>
        <nav className={open ? 'main-nav main-nav--open' : 'main-nav'} aria-label="Основное меню">
          {mainNav.map((k) => (
            <NavLink key={k} to={pages[k].path} onClick={() => setOpen(false)}>{pages[k].label}</NavLink>
          ))}
        </nav>
        <div className="site-header__actions">
          <Link to="/account/" className="account-link">Кабинет</Link>
          <CartLink />
          <button type="button" className="burger" aria-expanded={open} aria-label="Меню" onClick={() => setOpen(!open)}>
            <span /><span /><span />
          </button>
        </div>
      </div>
    </header>
  );
}

function CookieBanner() {
  const [show, setShow] = useState(false);
  useEffect(() => {
    let answered = false;
    try {
      answered = window.localStorage.getItem('zp_cookie_consent') !== null;
    } catch {
      answered = false;
    }
    setShow(!answered);
    if (hasConsent()) scheduleMetrika();
  }, []);
  if (!show) return null;
  return (
    <div className="cookie" role="dialog" aria-label="Согласие на cookie">
      <p>
        Мы используем cookie и Яндекс Метрику, чтобы сайт работал удобнее.{' '}
        <Link to={pages.privacy.path}>Подробнее</Link>
      </p>
      <div className="cookie__actions">
        <button type="button" className="btn btn-primary btn-sm" onClick={() => { setConsent(true); setShow(false); }}>Согласен</button>
        <button type="button" className="btn btn-secondary btn-sm" onClick={() => { setConsent(false); setShow(false); }}>Только необходимые</button>
      </div>
    </div>
  );
}

function Footer() {
  return (
    <footer className="site-footer">
      <div className="container footer-grid">
        <div>
          <img src="/media/logo-160.webp" alt="Зефир и пряник - логотип кондитерской" width="80" height="65" loading="lazy" />
          <p className="muted">{footer.tagline}</p>
        </div>
        <div>
          <p className="footer-title">Каталог</p>
          <ul className="footer-links">
            {categories.map((c) => <li key={c.slug}><Link to={categoryUrl(c.slug)}>{c.name}</Link></li>)}
          </ul>
        </div>
        <div>
          <p className="footer-title">Покупателям</p>
          <ul className="footer-links">
            {(['individual', 'corporate', 'founder', 'production', 'reviews', 'delivery', 'faq', 'contacts'] as const).map((k) => (
              <li key={k}><Link to={pages[k].path}>{pages[k].label}</Link></li>
            ))}
          </ul>
        </div>
        <div>
          <p className="footer-title">Контакты</p>
          <ul className="footer-links">
            <li><a href={`tel:${brand.phone}`} className="footer-phone"><PhoneIcon size={16} /> {brand.phoneDisplay}</a></li>
            <li>{brand.address.display}</li>
            {channels.map((c) => (
              <li key={c.id}><a href={c.url} target="_blank" rel="noopener"><ChannelIcon id={c.id} size={16} /> {c.title}</a></li>
            ))}
          </ul>
        </div>
      </div>
      <div className="container footer-legal">
        <p><strong>{brand.legal.entity}</strong></p>
        <p>ОГРНИП {brand.legal.ogrnip}</p>
        <p>от <a href={brand.legal.ogrnipDateUrl} target="_blank" rel="noopener nofollow">{brand.legal.ogrnipDate}</a></p>
        <p>ИНН {brand.legal.inn}</p>
        <p>{footer.disclaimer}</p>
        <p>
          <Link to={pages.privacy.path}>{pages.privacy.label}</Link> · <Link to={pages.offer.path}>{pages.offer.label}</Link>
        </p>
        <p>© {new Date().getFullYear()} {footer.copyright}</p>
      </div>
    </footer>
  );
}

export function Layout() {
  return (
    <QueryClientProvider client={queryClient}>
      <CartProvider>
        <a href="#main" className="skip-link">К содержимому</a>
        <Header />
        <main id="main">
          <Outlet />
        </main>
        <Footer />
        <CookieBanner />
        <ScrollRestoration />
      </CartProvider>
    </QueryClientProvider>
  );
}
