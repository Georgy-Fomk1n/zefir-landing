// Общие типы API (зеркало Laravel API Resources в backend/app/Http/Resources).

export type Role = 'customer' | 'seller' | 'courier' | 'admin';

export interface Seo {
  title: string | null;
  description: string | null;
  h1: string | null;
  text: string | null;
  noindex: boolean;
}

export interface Media {
  type: 'image' | 'video';
  /** Базовое имя в /media без суффикса размера, например "kofeynye-zerna" */
  name: string;
  alt: string;
}

export interface Variant {
  id: number;
  sku: string;
  name: string;
  /** Цена в копейках */
  price: number;
  /** Цена «от» (итог зависит от состава) */
  priceFrom: boolean;
  inStock: boolean;
  stock: number;
  packSize: number;
}

export interface Product {
  id: number;
  slug: string;
  categorySlug: string;
  name: string;
  description: string;
  isPp: boolean;
  media: Media[];
  variants: Variant[];
  rating: { value: number; count: number } | null;
  related: string[];
  seo: Seo;
  updatedAt: string;
}

export interface Category {
  id: number;
  slug: string;
  name: string;
  intro: string;
  sort: number;
  seo: Seo;
  /** Slug товаров из других категорий, которые показываются здесь со ссылкой на канонический URL */
  crossListed: string[];
  updatedAt: string;
}

export interface Catalog {
  generatedAt: string;
  categories: Category[];
  products: Product[];
}

export interface Paginated<T> {
  data: T[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  roles: Role[];
  marketingConsent: boolean;
  twoFactorEnabled: boolean;
}

export type FulfilmentType = 'pickup' | 'courier' | 'cdek' | 'russian_post';

export type OrderStatus =
  | 'pending_payment' | 'paid' | 'accepted' | 'preparing' | 'ready'
  | 'handed_to_courier' | 'shipped' | 'completed' | 'cancelled';

export type DeliveryStatus =
  | 'assigned' | 'accepted' | 'picked_up' | 'in_transit' | 'delivered' | 'failed' | 'returned';

export interface OrderItem {
  variantId: number;
  productId: number;
  /** Можно оставить отзыв: заказ выдан, отзыв на товар ещё не оставлен */
  reviewable: boolean;
  productName: string;
  variantName: string;
  price: number;
  qty: number;
}

export interface StatusEvent {
  from: string | null;
  to: string;
  at: string;
  comment: string | null;
}

export interface Order {
  id: number;
  number: string;
  status: OrderStatus;
  fulfilment: FulfilmentType;
  items: OrderItem[];
  subtotal: number;
  discount: number;
  pointsSpent: number;
  deliveryCost: number;
  total: number;
  paymentStatus: 'pending' | 'paid' | 'failed' | 'refunded';
  slot: { date: string; from: string; to: string } | null;
  delivery: { status: DeliveryStatus; address: string | null; history: StatusEvent[] } | null;
  history: StatusEvent[];
  trackingUrl: string | null;
  createdAt: string;
}

export interface CartLine {
  variantId: number;
  qty: number;
}

export interface Slot {
  id: number;
  date: string;
  from: string;
  to: string;
  available: number;
}

export interface DeliveryQuote {
  provider: FulfilmentType;
  available: boolean;
  cost: number;
  etaText: string;
  zoneId: number | null;
  message: string | null;
}

export interface LoyaltySummary {
  balance: number;
  maxSpendShare: number;
  qrToken: string;
  qrExpiresAt: string;
  transactions: { id: number; type: string; amount: number; comment: string | null; createdAt: string }[];
}

export type SubscriptionStatus = 'pending' | 'active' | 'past_due' | 'grace' | 'cancelled' | 'expired';

export interface SubscriptionPlan {
  id: number;
  name: string;
  description: string;
  price: number;
  periodDays: number;
  dailyLimit: number;
  totalLimit: number | null;
}

export interface Subscription {
  id: number;
  plan: SubscriptionPlan;
  status: SubscriptionStatus;
  currentPeriodEnd: string | null;
  autoRenew: boolean;
  usedToday: number;
  qrToken: string | null;
  renewalMode: 'recurring' | 'link';
}

export interface Review {
  id: number;
  author: string;
  rating: number;
  text: string;
  createdAt: string;
}
