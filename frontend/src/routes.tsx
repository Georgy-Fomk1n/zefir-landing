import type { RouteRecord } from 'vite-react-ssg';
import { Layout } from './components/Layout';
import { pages } from './content/pages';
import { catalog, categories, productsInCategory, productUrl } from './data/catalog';
import Cart from './pages/Cart';
import { CatalogIndex, CategoryPage, PAGE_SIZE, ProductPage } from './pages/catalog';
import { ContactsPage, CorporatePage, DeliveryPage, FaqPage, FounderPage, IndividualOrderPage, LegalPage, ProductionPage, ReviewsPage } from './pages/content';
import Home from './pages/Home';
import NotFound from './pages/NotFound';

const strip = (p: string) => p.replace(/^\/|\/$/g, '');

/**
 * Маршруты публичного сайта. Пререндерятся все, кроме "*".
 * /account, /cart, /checkout, /track, /poisk — оболочки с noindex, их содержимое строится в браузере.
 * Служебных маршрутов (/admin, /seller, /courier) здесь нет: они живут на штат.зефир-и-пряник.рф.
 */
export const routes: RouteRecord[] = [
  {
    path: '/',
    element: <Layout />,
    entry: 'src/components/Layout.tsx',
    children: [
      { index: true, element: <Home />, entry: 'src/pages/Home.tsx' },
      { path: 'catalog', element: <CatalogIndex /> },
      {
        path: 'catalog/:category',
        element: <CategoryPage />,
        getStaticPaths: () => categories.map((c) => `catalog/${c.slug}`),
      },
      {
        path: 'catalog/:category/page/:page',
        element: <CategoryPage />,
        getStaticPaths: () => categories.flatMap((c) => {
          const n = Math.ceil(productsInCategory(c.slug).length / PAGE_SIZE);
          return Array.from({ length: Math.max(0, n - 1) }, (_, i) => `catalog/${c.slug}/page/${i + 2}`);
        }),
      },
      {
        path: 'catalog/:category/:product',
        element: <ProductPage />,
        getStaticPaths: () => catalog.products.map((p) => strip(productUrl(p))),
      },
      { path: strip(pages.individual.path), element: <IndividualOrderPage /> },
      { path: strip(pages.corporate.path), element: <CorporatePage /> },
      { path: strip(pages.founder.path), element: <FounderPage /> },
      { path: strip(pages.production.path), element: <ProductionPage /> },
      { path: strip(pages.reviews.path), element: <ReviewsPage /> },
      { path: strip(pages.delivery.path), element: <DeliveryPage /> },
      { path: strip(pages.contacts.path), element: <ContactsPage /> },
      { path: strip(pages.faq.path), element: <FaqPage /> },
      { path: strip(pages.privacy.path), element: <LegalPage kind="privacy" /> },
      { path: strip(pages.offer.path), element: <LegalPage kind="offer" /> },
      { path: 'cart', element: <Cart /> },
      { path: 'checkout', lazy: () => import('./pages/Checkout').then((m) => ({ Component: m.default })) },
      { path: 'track', lazy: () => import('./pages/Track').then((m) => ({ Component: m.default })) },
      { path: 'poisk', lazy: () => import('./pages/Search').then((m) => ({ Component: m.default })) },
      {
        path: 'account/*',
        lazy: () => import('./pages/account/Account').then((m) => ({ Component: m.default })),
        getStaticPaths: () => ['account'],
      },
      { path: '404', element: <NotFound /> },
      { path: '500', element: <NotFound code={500} /> },
      { path: '*', element: <NotFound /> },
    ],
  },
];
