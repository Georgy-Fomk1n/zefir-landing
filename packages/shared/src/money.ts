/** Форматирует копейки в рубли: 220000 → «2 200 ₽». Неразрывные пробелы, как в типографике сайта. */
export function formatPrice(kopecks: number, from = false): string {
  const rub = Math.round(kopecks / 100);
  const digits = String(rub).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');
  return `${from ? 'от ' : ''}${digits}\u00a0₽`;
}

/** Цена для schema.org: «2200.00» */
export function schemaPrice(kopecks: number): string {
  return (kopecks / 100).toFixed(2);
}
