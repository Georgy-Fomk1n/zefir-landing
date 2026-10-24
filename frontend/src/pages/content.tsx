import { brand, formatPrice, yandex } from '@zefir/shared';
import { Link } from 'react-router-dom';
import { Breadcrumbs, ChannelButtons, crumbs, FaqList, Hours, LazyIframe, ProductCard } from '../components/common';
import { pages, type PageKey } from '../content/pages';
import { corporate, customOrder, faq, founder, gift, location, production, reviews } from '../content/texts';
import { catalog, categoryUrl, productsInCategory, seoOverride } from '../data/catalog';
import { LazyVideo, Picture } from '../media';
import { faqPage, organization } from '../seo/jsonld';
import { Seo } from '../seo/Seo';

function useContentPage(key: PageKey, extraLd: Record<string, unknown>[] = []) {
  const base = pages[key];
  const o = seoOverride(base.path);
  const meta = { ...base, title: o.title ?? base.title, description: o.description ?? base.description, h1: o.h1 ?? base.h1 };
  const bc = crumbs({ name: base.label, path: base.path });
  const seo = (
    <Seo title={meta.title} description={meta.description} path={meta.path} og={'og' in base ? base.og : undefined}
      noindex={o.noindex} jsonLd={[bc.jsonLd, ...extraLd]} />
  );
  const seoText = o.text ? <div className="seo-text">{o.text.split('\n\n').map((t) => <p key={t}>{t}</p>)}</div> : null;
  return { meta, seo, seoText, breadcrumbs: <Breadcrumbs items={bc.items} /> };
}

export function IndividualOrderPage() {
  const { meta, seo, seoText, breadcrumbs } = useContentPage('individual');
  return (
    <>
      {seo}
      <div className="container page">
        {breadcrumbs}
        <h1>{meta.h1}</h1>
        <section>
          <h2>{gift.title}</h2>
          <p className="lead">{gift.lead}</p>
          <div className="grid grid--2">
            {productsInCategory('podarki').map((p) => <ProductCard key={p.slug} product={p} />)}
          </div>
          <p className="more"><Link to={categoryUrl('podarki')}>Все подарочные наборы</Link></p>
        </section>
        <section>
          <h2>{customOrder.title}</h2>
          <p className="lead">{customOrder.lead}</p>
          <div className="grid grid--2">
            {[customOrder.gift, customOrder.company].map((c) => (
              <div key={c.title} className="panel">
                <h3>{c.title}</h3>
                <p>{c.text}</p>
                <ul className="ticks">{c.features.map((f) => <li key={f}>{f}</li>)}</ul>
              </div>
            ))}
          </div>
        </section>
        <section>
          <h2>{customOrder.cta}</h2>
          <p><a href={`tel:${brand.phone}`} className="phone">{brand.phoneDisplay}</a></p>
          <ChannelButtons />
        </section>
        {seoText}
      </div>
    </>
  );
}

export function CorporatePage() {
  const { meta, seo, seoText, breadcrumbs } = useContentPage('corporate');
  return (
    <>
      {seo}
      <div className="container page">
        {breadcrumbs}
        <div className="split">
          <Picture name="fistashka" alt="Корпусные пирожные для корпоративного заказа" sizes="(min-width: 1024px) 45vw, 100vw" priority />
          <div>
            <h1>{meta.h1}</h1>
            <p className="lead">{corporate.text}</p>
            <h2>{customOrder.company.title}</h2>
            <p>{customOrder.company.text}</p>
            <ul className="ticks">{customOrder.company.features.map((f) => <li key={f}>{f}</li>)}</ul>
            <h2>{corporate.cta}</h2>
            <p><a href={`tel:${brand.phone}`} className="phone">{brand.phoneDisplay}</a></p>
            <ChannelButtons compact />
          </div>
        </div>
        <section>
          <h2>Наборы для мероприятий</h2>
          <div className="grid grid--2">{productsInCategory('nabory').map((p) => <ProductCard key={p.slug} product={p} />)}</div>
        </section>
        {seoText}
      </div>
    </>
  );
}

export function FounderPage() {
  const { meta, seo, seoText, breadcrumbs } = useContentPage('founder');
  return (
    <>
      {seo}
      <div className="container page">
        {breadcrumbs}
        <div className="split">
          <Picture name="krasolda" alt={founder.alt} sizes="(min-width: 1024px) 45vw, 100vw" priority />
          <div>
            <h1>{meta.h1}</h1>
            <p className="lead">{founder.role}</p>
            <p>{founder.text}</p>
            {/* [УТОЧНИТЬ] развёрнутая история бренда — уникальный текст от владельца, см. docs/assumptions.md */}
            <p><Link to={pages.production.path}>Как мы делаем пирожные</Link> · <Link to="/catalog/">Каталог</Link></p>
          </div>
        </div>
        {seoText}
      </div>
    </>
  );
}

export function ProductionPage() {
  const { meta, seo, seoText, breadcrumbs } = useContentPage('production');
  return (
    <>
      {seo}
      <div className="container page">
        {breadcrumbs}
        <h1>{meta.h1}</h1>
        <p className="lead">{production.lead}</p>
        <div className="split">
          <LazyVideo name="zastavka" label="Производство корпусных пирожных Зефир и пряник" className="production__video" />
          <ol className="steps">
            {production.steps.map((s) => <li key={s}><h2>{s}</h2></li>)}
          </ol>
        </div>
        <Picture name="banan-razrez" alt="Разрез корпусного пирожного: шоколадный корпус и начинка" sizes="100vw" />
        {seoText}
      </div>
    </>
  );
}

export function ReviewsPage() {
  const { meta, seo, seoText, breadcrumbs } = useContentPage('reviews');
  const siteReviews = catalog.reviews;
  return (
    <>
      {seo}
      <div className="container page">
        {breadcrumbs}
        <h1>{meta.h1}</h1>
        <p className="lead">{reviews.lead}</p>
        <section>
          <h2>Отзывы покупателей на сайте</h2>
          {siteReviews.length === 0 ? (
            <p className="muted">Отзывы появятся после первых заказов: оставить отзыв можно в личном кабинете после получения заказа.</p>
          ) : (
            <ul className="reviews">
              {siteReviews.map((r) => (
                <li key={r.id}><p><strong>{r.author}</strong> · {'★'.repeat(r.rating)}</p><p>{r.text}</p></li>
              ))}
            </ul>
          )}
        </section>
        <section>
          <h2>Отзывы в Яндекс Картах</h2>
          {/* Виджет Яндекса только как iframe: в JSON-LD эти отзывы не копируются */}
          <LazyIframe src={yandex.reviewsWidget} title="Отзывы о Зефир и пряник в Яндекс Картах" height={600} />
          <p><a href={yandex.orgUrl} target="_blank" rel="noopener">{reviews.mapLink}</a></p>
          <p><a href={yandex.reviewUrl} target="_blank" rel="noopener" className="btn btn-secondary">Оставить отзыв в Яндекс Картах</a></p>
        </section>
        {seoText}
      </div>
    </>
  );
}

export function DeliveryPage() {
  const { meta, seo, seoText, breadcrumbs } = useContentPage('delivery');
  const zones = catalog.deliveryZones;
  return (
    <>
      {seo}
      <div className="container page">
        {breadcrumbs}
        <h1>{meta.h1}</h1>
        <section>
          <h2>{location.pickup.title}</h2>
          <p>{brand.address.display}. {location.pickup.note}.</p>
          <Hours />
        </section>
        <section>
          <h2>Курьерская доставка по Москве</h2>
          {zones.length > 0 ? (
            <table className="table">
              <thead><tr><th>Зона</th><th>Стоимость</th><th>Минимальный заказ</th><th>Бесплатно от</th><th>Срок</th></tr></thead>
              <tbody>
                {zones.map((z) => (
                  <tr key={z.id}>
                    <td>{z.name}</td>
                    <td>{formatPrice(z.price)}</td>
                    <td>{z.minOrder ? formatPrice(z.minOrder) : '—'}</td>
                    <td>{z.freeFrom ? formatPrice(z.freeFrom) : '—'}</td>
                    <td>{z.etaText}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <p>Зоны и стоимость курьерской доставки рассчитываются при оформлении заказа по адресу.</p>
          )}
          <p>Интервал доставки выбирается при оформлении. Адрес вне зоны доставки оформить нельзя — подойдут самовывоз или Flowwow.</p>
        </section>
        <section>
          <h2>СДЭК и Почта России</h2>
          <p>Стоимость и сроки рассчитываются при оформлении заказа по адресу или пункту выдачи.</p>
        </section>
        <section>
          <h2>Через партнёров</h2>
          <ChannelButtons compact />
        </section>
        <section>
          <h2>Оплата</h2>
          <p>Онлайн-оплата картой при оформлении заказа. Оплата баллами карты лояльности — частично.</p>
        </section>
        <section>
          <h2>Сроки заказа</h2>
          <p>{faq[6].a}</p>
        </section>
        {seoText}
      </div>
    </>
  );
}

export function ContactsPage() {
  const { meta, seo, seoText, breadcrumbs } = useContentPage('contacts', [organization()]);
  return (
    <>
      {seo}
      <div className="container page">
        {breadcrumbs}
        <h1>{meta.h1}</h1>
        <p className="lead">{location.lead}</p>
        <div className="bento">
          <div className="bento__item bento__item--wide">
            <h2>{location.pickup.title}</h2>
            <address>{brand.address.display}</address>
            <p>{location.pickup.note}</p>
            <LazyIframe src={yandex.mapWidget} title="Зефир и пряник на карте" height={340} />
          </div>
          <div className="bento__item">
            <h2>{location.contact.title}</h2>
            <p><a href={`tel:${brand.phone}`} className="phone">{brand.phoneDisplay}</a></p>
            <ChannelButtons compact />
          </div>
          <div className="bento__item">
            <h2>{location.hours.title}</h2>
            <Hours />
          </div>
        </div>
        {seoText}
      </div>
    </>
  );
}

export function FaqPage() {
  const { meta, seo, seoText, breadcrumbs } = useContentPage('faq', [faqPage(faq)]);
  return (
    <>
      {seo}
      <div className="container page">
        {breadcrumbs}
        <h1>{meta.h1}</h1>
        <FaqList items={faq} />
        <p className="more"><Link to={pages.delivery.path}>Доставка и оплата</Link> · <Link to={pages.contacts.path}>Контакты</Link></p>
        {seoText}
      </div>
    </>
  );
}

export function LegalPage({ kind }: { kind: 'privacy' | 'offer' }) {
  const { meta, seo, seoText, breadcrumbs } = useContentPage(kind);
  return (
    <>
      {seo}
      <div className="container page legal">
        {breadcrumbs}
        <h1>{meta.h1}</h1>
        <p>{brand.legal.entity}, ОГРНИП {brand.legal.ogrnip}, ИНН {brand.legal.inn}.</p>
        {/* [УТОЧНИТЬ] юридический текст готовит владелец/юрист; шаблон не публикуется как оферта */}
        <p>Текст документа готовится. По вопросам обработки персональных данных: <a href={`tel:${brand.phone}`}>{brand.phoneDisplay}</a>.</p>
        {seoText}
      </div>
    </>
  );
}
