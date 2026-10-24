#!/usr/bin/env node
// Готовит медиа для публичного сайта из frontend/media-src в frontend/public/media:
// адаптивные WebP/AVIF, OG-картинки 1200×630, MP4 (H.264) + постеры к WebM, набор иконок.
// Запуск: npm run media -w frontend (нужен ffmpeg). Результат коммитится, в CI не пересобирается.
import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join, parse } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const src = join(root, 'media-src');
const out = join(root, 'public', 'media');
const icons = join(root, 'public');
const manifestPath = join(root, 'src', 'data', 'media.json');
const force = process.argv.includes('--force');

const WIDTHS = [400, 600, 800, 1200];
const SKIP_IMAGES = new Set(['logo', 'glori-logo']);

mkdirSync(out, { recursive: true });

const ff = (args) => execFileSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', ...args], { stdio: 'inherit' });

function probe(file) {
  const res = execFileSync('ffprobe', [
    '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=width,height', '-of', 'csv=p=0', file,
  ]).toString().trim().split('\n')[0];
  const [width, height] = res.split(',').map(Number);
  return { width, height };
}

function once(target, fn) {
  if (!force && existsSync(target)) return;
  fn();
}

const manifest = { images: {}, videos: {} };

for (const file of readdirSync(src).sort()) {
  const { name, ext } = parse(file);
  const input = join(src, file);

  if (ext === '.webp' && !SKIP_IMAGES.has(name)) {
    const { width, height } = probe(input);
    const widths = WIDTHS.filter((w) => w <= width);
    if (widths.length === 0) widths.push(width);
    for (const w of widths) {
      const scale = `scale=${w}:-2:flags=lanczos`;
      once(join(out, `${name}-${w}.webp`), () =>
        ff(['-i', input, '-vf', scale, '-c:v', 'libwebp', '-quality', '72', '-compression_level', '6', join(out, `${name}-${w}.webp`)]));
      once(join(out, `${name}-${w}.avif`), () =>
        ff(['-i', input, '-vf', `${scale},format=yuv420p`, '-c:v', 'libaom-av1', '-still-picture', '1', '-crf', '38', '-cpu-used', '6', join(out, `${name}-${w}.avif`)]));
    }
    once(join(out, `${name}-og.jpg`), () =>
      ff(['-i', input, '-vf', 'scale=1200:630:force_original_aspect_ratio=increase,crop=1200:630', '-q:v', '4', join(out, `${name}-og.jpg`)]));
    manifest.images[name] = { width, height, widths };
  }

  if (ext === '.webm') {
    const { width, height } = probe(input);
    once(join(out, `${name}.webm`), () => copyFileSync(input, join(out, `${name}.webm`)));
    once(join(out, `${name}.mp4`), () =>
      ff(['-i', input, '-an', '-c:v', 'libx264', '-profile:v', 'main', '-pix_fmt', 'yuv420p', '-crf', '27', '-preset', 'slow', '-movflags', '+faststart', join(out, `${name}.mp4`)]));
    once(join(out, `${name}-poster.webp`), () =>
      ff(['-ss', '0.5', '-i', input, '-frames:v', '1', '-vf', 'scale=720:-2', '-c:v', 'libwebp', '-quality', '75', join(out, `${name}-poster.webp`)]));
    manifest.videos[name] = { width, height };
  }
}

// Логотип и иконки
const logo = join(src, 'logo.png');
once(join(out, 'logo-160.webp'), () => ff(['-i', logo, '-vf', 'scale=160:-2', '-c:v', 'libwebp', '-quality', '90', join(out, 'logo-160.webp')]));
once(join(out, 'logo-160.png'), () => ff(['-i', logo, '-vf', 'scale=160:-2', join(out, 'logo-160.png')]));
once(join(out, 'logo-512.png'), () => ff(['-i', logo, '-vf', 'scale=512:-2', join(out, 'logo-512.png')]));
const square = (size) => `scale=${size}:${size}:force_original_aspect_ratio=decrease,pad=${size}:${size}:(ow-iw)/2:(oh-ih)/2:color=0xFFF5FB`;
for (const [file, size] of [['favicon-32.png', 32], ['apple-touch-icon.png', 180], ['icon-192.png', 192], ['icon-512.png', 512]]) {
  once(join(icons, file), () => ff(['-i', logo, '-vf', square(size), join(icons, file)]));
}
once(join(icons, 'favicon.ico'), () => ff(['-i', logo, '-vf', square(48), join(icons, 'favicon.ico')]));

manifest.logo = probe(logo);
mkdirSync(dirname(manifestPath), { recursive: true });
writeFileSync(manifestPath, JSON.stringify(manifest, null, 2) + '\n');
console.log(`media: ${Object.keys(manifest.images).length} images, ${Object.keys(manifest.videos).length} videos`);
