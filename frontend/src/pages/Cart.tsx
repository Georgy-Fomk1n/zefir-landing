import { formatPrice, type Product, type Variant } from '@zefir/shared';
import { Link } from 'react-router-dom';
import { ChannelButtons } from '../components/common';
import { catalog, productUrl } from '../data/catalog';
import { useCart } from '../lib/cart';
import { reachGoal } from '../lib/metrika';
import { Picture } from '../media';
import { Seo } from '../seo/Seo';

export function resolveVariant(variantId: number): { product: Product; variant: Variant } | null {
  for (const product of catalog.products) {
    const variant = product.variants.find((v) => v.id === variantId);
    if (variant) return { product, variant };
  }
  return null;
}

export default function Cart() {
  const cart = useCart();
  const rows = cart.lines
    .map((l) => ({ line: l, item: resolveVariant(l.variantId) }))
    .filter((r): r is { line: typeof r.line; item: NonNullable<typeof r.item> } => r.item !== null);
  const subtotal = rows.reduce((s, r) => s + r.item.variant.price * r.line.qty, 0);

  return (
    <>
      <Seo title="Корзина — Зефир и пряник" description="Корзина интернет-магазина" path="/cart/" noindex />
      <div className="container page">
        <h1>Корзина</h1>
        {rows.length === 0 ? (
          <>
            <p className="lead">Корзина пуста.</p>
            <Link to="/catalog/" className="btn btn-primary">В каталог</Link>
          </>
        ) : (
          <>
            <ul className="cart">
              {rows.map(({ line, item }) => {
                const image = item.product.media[0];
                return (
                  <li key={line.variantId} className="cart__row">
                    {image && <Picture name={image.name} alt={image.alt} sizes="96px" className="cart__thumb" />}
                    <div className="cart__name">
                      <Link to={productUrl(item.product)}>{item.product.name}</Link>
                      <span className="muted">{item.variant.name}</span>
                    </div>
                    <div className="qty">
                      <button type="button" aria-label="Меньше" onClick={() => cart.setQty(line.variantId, line.qty - 1)}>−</button>
                      <span aria-live="polite">{line.qty}</span>
                      <button type="button" aria-label="Больше" onClick={() => cart.setQty(line.variantId, line.qty + 1)}>+</button>
                    </div>
                    <span className="price">{formatPrice(item.variant.price * line.qty, item.variant.priceFrom)}</span>
                    <button type="button" className="link" onClick={() => cart.remove(line.variantId)}>Удалить</button>
                  </li>
                );
              })}
            </ul>
            <p className="cart__total">Итого без доставки: <strong>{formatPrice(subtotal)}</strong></p>
            <p className="muted">Итоговая сумма с учётом промокода, баллов и доставки рассчитывается при оформлении.</p>
            <div className="btn-row">
              <Link to="/checkout/" className="btn btn-primary" onClick={() => reachGoal('checkout_start')}>Оформить заказ</Link>
              <Link to="/catalog/" className="btn btn-secondary">Продолжить покупки</Link>
            </div>
          </>
        )}
        <p className="muted">Также можно заказать через наши каналы:</p>
        <ChannelButtons compact />
      </div>
    </>
  );
}
