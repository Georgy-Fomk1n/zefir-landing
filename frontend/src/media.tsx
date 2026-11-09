import { useEffect, useRef, useState, type CSSProperties } from 'react';
import manifest from './data/media.json';

interface ImageInfo {
  width: number;
  height: number;
  widths: number[];
}

const images = manifest.images as Record<string, ImageInfo>;
const videos = manifest.videos as Record<string, { width: number; height: number }>;

export function mediaUrl(name: string, width: number, ext: 'webp' | 'avif' = 'webp'): string {
  const info = images[name];
  if (!info) return `/media/${name}-poster.webp`;
  const w = info.widths.filter((x) => x <= width).pop() ?? info.widths[0];
  return `/media/${name}-${w}.${ext}`;
}

export function ogImage(name: string | undefined): string {
  return `/media/${name && images[name] ? name : 'banan-razrez'}-og.jpg`;
}

interface PictureProps {
  name: string;
  alt: string;
  sizes: string;
  /** LCP-картинка: без lazy, fetchpriority=high (preload добавляет страница через <Head>) */
  priority?: boolean;
  className?: string;
  style?: CSSProperties;
}

export function Picture({ name, alt, sizes, priority, className, style }: PictureProps) {
  const info = images[name];
  if (!info) {
    // Нет фото, только видео: используем постер
    const v = videos[name];
    return (
      <img src={`/media/${name}-poster.webp`} alt={alt} width={v?.width ?? 720} height={v?.height ?? 1280}
        loading={priority ? 'eager' : 'lazy'} decoding="async" className={className} style={style} />
    );
  }
  const height = (w: number) => Math.round((w * info.height) / info.width);
  const srcset = (ext: string) => info.widths.map((w) => `/media/${name}-${w}.${ext} ${w}w`).join(', ');
  const fallback = info.widths[Math.min(1, info.widths.length - 1)];
  return (
    <picture>
      <source type="image/avif" srcSet={srcset('avif')} sizes={sizes} />
      <source type="image/webp" srcSet={srcset('webp')} sizes={sizes} />
      <img
        src={`/media/${name}-${fallback}.webp`}
        alt={alt}
        width={fallback}
        height={height(fallback)}
        loading={priority ? 'eager' : 'lazy'}
        fetchPriority={priority ? 'high' : 'auto'}
        decoding="async"
        className={className}
        style={style}
      />
    </picture>
  );
}

/** srcset для <link rel="preload" imagesrcset> LCP-картинки */
export function preloadSrcSet(name: string): string {
  const info = images[name];
  return info ? info.widths.map((w) => `/media/${name}-${w}.avif ${w}w`).join(', ') : '';
}

interface VideoProps {
  name: string;
  label: string;
  className?: string;
}

/**
 * Видео WebM + MP4 (H.264), poster, preload="none". Источники подставляются, только когда ролик
 * близко к экрану, чтобы видео вне экрана не грузилось вообще.
 */
export function LazyVideo({ name, label, className }: VideoProps) {
  const ref = useRef<HTMLVideoElement>(null);
  const [visible, setVisible] = useState(false);
  const info = videos[name] ?? { width: 720, height: 1280 };

  useEffect(() => {
    const el = ref.current;
    if (!el || !('IntersectionObserver' in window)) return;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) return;
    const io = new IntersectionObserver(([entry]) => {
      if (entry.isIntersecting) {
        setVisible(true);
        io.disconnect();
      }
    }, { rootMargin: '200px' });
    io.observe(el);
    return () => io.disconnect();
  }, []);

  useEffect(() => {
    if (visible) {
      ref.current?.load();
      ref.current?.play().catch(() => undefined);
    }
  }, [visible]);

  return (
    <video
      ref={ref}
      className={className}
      muted
      loop
      playsInline
      preload="none"
      poster={`/media/${name}-poster.webp`}
      width={info.width}
      height={info.height}
      aria-label={label}
    >
      {visible && <source src={`/media/${name}.webm`} type="video/webm" />}
      {visible && <source src={`/media/${name}.mp4`} type="video/mp4" />}
    </video>
  );
}
