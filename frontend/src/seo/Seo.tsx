import { absoluteUrl, brand } from '@zefir/shared';
import { Head } from 'vite-react-ssg';
import { config, indexable } from '../config';
import { ogImage } from '../media';

interface SeoProps {
  title: string;
  description: string;
  /** Путь канонического URL, со слэшем в конце */
  path: string;
  og?: string;
  noindex?: boolean;
  jsonLd?: Record<string, unknown>[];
  ogType?: 'website' | 'product' | 'article';
  preload?: { srcSet: string; sizes: string };
}

/** Экранирование JSON внутри <script>: «</script>» и HTML-комментарии не должны ломать разметку. */
export function serializeJsonLd(data: unknown): string {
  return JSON.stringify(data).replace(/</g, '\\u003c').replace(/>/g, '\\u003e').replace(/&/g, '\\u0026');
}

export function Seo({ title, description, path, og, noindex, jsonLd = [], ogType = 'website', preload }: SeoProps) {
  const url = absoluteUrl(path);
  const image = absoluteUrl(ogImage(og));
  const robots = noindex || !indexable ? 'noindex,follow' : 'index,follow';
  return (
    <Head>
      <title>{title}</title>
      <meta name="description" content={description} />
      <meta name="robots" content={robots} />
      <link rel="canonical" href={url} />
      <meta property="og:type" content={ogType} />
      <meta property="og:site_name" content={brand.name} />
      <meta property="og:locale" content="ru_RU" />
      <meta property="og:url" content={url} />
      <meta property="og:title" content={title} />
      <meta property="og:description" content={description} />
      <meta property="og:image" content={image} />
      <meta property="og:image:width" content="1200" />
      <meta property="og:image:height" content="630" />
      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" content={title} />
      <meta name="twitter:description" content={description} />
      <meta name="twitter:image" content={image} />
      {config.yandexVerification && <meta name="yandex-verification" content={config.yandexVerification} />}
      {config.googleVerification && <meta name="google-site-verification" content={config.googleVerification} />}
      {preload && (
        <link rel="preload" as="image" type="image/avif" imageSrcSet={preload.srcSet} imageSizes={preload.sizes} fetchPriority="high" />
      )}
      {jsonLd.map((data, i) => (
        <script key={i} type="application/ld+json">{serializeJsonLd(data)}</script>
      ))}
    </Head>
  );
}
