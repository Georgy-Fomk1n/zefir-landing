// Готовит картинки и иконки приложения.
//   resources/images-src/*  →  public/images/*  (AVIF + WebP нескольких размеров) + manifest.json
//   logo.png                →  public/icons/*   (иконки PWA, favicon, apple-touch)
// Запуск: npm run images. Результат лежит в git, на хостинге ничего собирать не нужно.
import sharp from 'sharp';
import { mkdir, readdir, writeFile, copyFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';

const SRC = 'resources/images-src';
const OUT = 'public/images';
const ICONS = 'public/icons';
const WIDTHS = [320, 480, 720];
const BG = '#f6f0e8';

await mkdir(OUT, { recursive: true });
await mkdir(ICONS, { recursive: true });

const manifest = {};
const files = (await readdir(SRC)).sort();

for (const f of files) {
    const ext = path.extname(f).toLowerCase();
    const name = path.basename(f, ext);

    if (ext === '.mp4') {
        await copyFile(path.join(SRC, f), path.join(OUT, f));
        continue;
    }
    if (!['.jpg', '.jpeg', '.png', '.webp'].includes(ext)) continue;

    const img = sharp(path.join(SRC, f));
    const meta = await img.metadata();
    const widths = name === 'logo' ? [120, 240] : WIDTHS.filter((w) => w <= meta.width).concat(meta.width > WIDTHS.at(-1) ? [] : [meta.width]);
    const uniq = [...new Set(widths)].sort((a, b) => a - b);
    manifest[name] = { w: meta.width, h: meta.height, widths: uniq };

    for (const w of uniq) {
        const base = sharp(path.join(SRC, f)).resize({ width: w, withoutEnlargement: true });
        await base.clone().webp({ quality: 78, effort: 5 }).toFile(path.join(OUT, `${name}-${w}.webp`));
        await base.clone().avif({ quality: 52, effort: 4 }).toFile(path.join(OUT, `${name}-${w}.avif`));
    }
    console.log(name, uniq.join('/'));
}

await writeFile(path.join(OUT, 'manifest.json'), JSON.stringify(manifest));

// Иконки PWA из логотипа: на молочной подложке, maskable с запасом по краям
const logo = path.join(SRC, 'logo.png');
async function icon(size, file, pad) {
    const inner = Math.round(size * (1 - pad * 2));
    const fg = await sharp(logo).resize({ width: inner, height: inner, fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } }).png().toBuffer();
    await sharp({ create: { width: size, height: size, channels: 4, background: BG } })
        .composite([{ input: fg, gravity: 'center' }]).png({ palette: true, quality: 85, compressionLevel: 9 }).toFile(path.join(ICONS, file));
}
await icon(192, 'icon-192.png', 0.08);
await icon(512, 'icon-512.png', 0.08);
await icon(512, 'icon-maskable-512.png', 0.2);
await icon(180, 'apple-touch-icon.png', 0.08);
await icon(32, 'favicon-32.png', 0.04);
await icon(96, 'badge-96.png', 0.04);

if (!existsSync(path.join(ICONS, 'icon-512.png'))) throw new Error('icons not written');
console.log('ok');
