import { brand, yandex } from '@zefir/shared';
import { Link } from 'react-router-dom';
import { ChannelButtons, FaqList, Hours, LazyIframe, ProductCard, Section } from '../components/common';
import { pages } from '../content/pages';
import { customOrder, faq, finalCta, founder, hero, inside, location } from '../content/texts';
import { categoryUrl, productsInCategory } from '../data/catalog';
import { LazyVideo, Picture, preloadSrcSet } from '../media';
import { organization, website } from '../seo/jsonld';
import { Seo } from '../seo/Seo';

const HERO_SIZES = '(min-width: 1024px) 50vw, 100vw';

export default function Home() {
  const meta = pages.home;
  return (
    <>
      <Seo
        title={meta.title}
        description={meta.description}
        path={meta.path}
        og={meta.og}
        jsonLd={[organization(), website()]}
        preload={{ srcSet: preloadSrcSet('banan'), sizes: HERO_SIZES }}
      />

      <section className="hero">
        <div className="container hero__grid">
          <div className="hero__content">
            <p className="hero__meta">{hero.meta}</p>
            <h1>{hero.title} <br />{hero.titleTail}</h1>
            <p className="hero__subtitle">{hero.subtitle}</p>
            <div className="btn-row">
              <Link to="/catalog/" className="btn btn-primary">{hero.ctaPrimary}</Link>
              <Link to={categoryUrl('vkusy')} className="btn btn-secondary">{hero.ctaSecondary}</Link>
            </div>
          </div>
          <div className="hero__image">
            <Picture name="banan" alt="Корпусные пирожные ручной работы в шоколадном корпусе" sizes={HERO_SIZES} priority />
            {hero.tags.map((t, i) => <span key={t} className={`float-tag float-tag--${i + 1}`}>{t}</span>)}
          </div>
        </div>
      </section>

      <Section title={inside.title} lead={inside.lead}>
        <div className="inside">
          <Picture name="chernika" alt={inside.alt} sizes="(min-width: 1400px) 1300px, 100vw" className="inside__image" />
          {inside.labels.map((l, i) => <span key={l} className={`inside__label inside__label--${i + 1}`}>{l}</span>)}
        </div>
      </Section>

      <Section title="Вкусы" lead="Выберите свой или соберите набор" id="flavors">
        <div className="grid grid--3">
          {productsInCategory('vkusy').map((p) => <ProductCard key={p.slug} product={p} />)}
        </div>
        <p className="more"><Link to={categoryUrl('vkusy')}>Все вкусы</Link></p>
      </Section>

      <section className="section section--tinted">
        <div className="container">
          <header className="section-header">
            <h2>Правильное питание без сахара</h2>
            <p>Любимый десерт — в версии без лишнего</p>
          </header>
          <div className="grid grid--2">
            {productsInCategory('pp-bez-sahara').map((p) => {
              const video = p.media.find((m) => m.type === 'video');
              return (
                <div key={p.slug} className="pp-item">
                  {video && <LazyVideo name={video.name} label={video.alt} className="pp-item__video" />}
                  <ProductCard product={p} />
                </div>
              );
            })}
          </div>
          <p className="more"><Link to={categoryUrl('pp-bez-sahara')}>ПП-пирожные без сахара</Link></p>
        </div>
      </section>

      <Section title="Наборы" lead="Когда одного вкуса недостаточно" id="bundles">
        <div className="grid grid--2">
          {productsInCategory('nabory').map((p) => <ProductCard key={p.slug} product={p} />)}
        </div>
      </Section>

      <Section title={customOrder.title} lead={customOrder.lead}>
        <div className="grid grid--2">
          {[customOrder.gift, customOrder.company].map((c, i) => (
            <div key={c.title} className="panel">
              <h3>{c.title}</h3>
              <p>{c.text}</p>
              <ul className="ticks">{c.features.map((f) => <li key={f}>{f}</li>)}</ul>
              <Link to={i === 0 ? pages.individual.path : pages.corporate.path} className="btn btn-primary">{customOrder.cta}</Link>
            </div>
          ))}
        </div>
      </Section>

      <section className="section">
        <div className="container founder">
          <Picture name="krasolda" alt={founder.alt} sizes="(min-width: 1024px) 40vw, 100vw" className="founder__image" />
          <div>
            <h2>{founder.name}</h2>
            <p className="muted">{founder.role}</p>
            <p>{founder.text}</p>
            <Link to={pages.founder.path} className="btn btn-secondary">История бренда</Link>
          </div>
        </div>
      </section>

      <Section title="Отзывы" lead="Что говорят наши клиенты">
        <Link to={pages.reviews.path} className="btn btn-secondary">Читать отзывы</Link>
      </Section>

      <Section title={location.title} lead={location.lead} id="location">
        <div className="bento">
          <div className="bento__item bento__item--wide">
            <h3>{location.pickup.title}</h3>
            <p>{brand.address.display}</p>
            <p>{location.pickup.note}</p>
            <LazyIframe src={yandex.mapWidget} title="Зефир и пряник на карте" height={300} />
          </div>
          <div className="bento__item">
            <h3>{location.delivery.title}</h3>
            <p><Link to={pages.delivery.path}>Курьером по Москве, СДЭК и Почтой России</Link></p>
            <ChannelButtons compact />
          </div>
          <div className="bento__item">
            <h3>{location.contact.title}</h3>
            <p><a href={`tel:${brand.phone}`} className="phone">{brand.phoneDisplay}</a></p>
          </div>
          <div className="bento__item">
            <h3>{location.hours.title}</h3>
            <Hours />
          </div>
        </div>
      </Section>

      <Section title="Частые вопросы">
        <FaqList items={faq.slice(0, 3)} />
        <p className="more"><Link to={pages.faq.path}>Все вопросы</Link></p>
      </Section>

      <section className="section final-cta">
        <div className="container">
          <h2>{finalCta.title}</h2>
          <p>{finalCta.text}</p>
          <div className="btn-row btn-row--center">
            <Link to="/catalog/" className="btn btn-primary">{finalCta.primary}</Link>
            <a href={`tel:${brand.phone}`} className="btn btn-secondary">{finalCta.call}</a>
          </div>
        </div>
      </section>
    </>
  );
}
