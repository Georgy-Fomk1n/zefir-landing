#!/usr/bin/env node
// Локальный предпросмотр dist/ с поведением как на хостинге: /path/ → index.html, /account/* → оболочка,
// несуществующее → 404.html с HTTP 404, путь без слэша → 301. Нужен для Lighthouse CI и smoke-тестов.
import { createReadStream, existsSync, statSync } from 'node:fs';
import { createServer } from 'node:http';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';

const dist = fileURLToPath(new URL('../dist', import.meta.url));
const port = Number(process.env.PORT ?? 4173);
const types = {
  '.html': 'text/html; charset=utf-8', '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json',
  '.webp': 'image/webp', '.avif': 'image/avif', '.jpg': 'image/jpeg', '.png': 'image/png', '.ico': 'image/x-icon',
  '.woff2': 'font/woff2', '.mp4': 'video/mp4', '.webm': 'video/webm', '.xml': 'application/xml', '.txt': 'text/plain; charset=utf-8',
  '.webmanifest': 'application/manifest+json', '.yml': 'application/xml',
};

function send(res, file, status = 200) {
  res.writeHead(status, { 'Content-Type': types[extname(file)] ?? 'application/octet-stream' });
  createReadStream(file).pipe(res);
}

createServer((req, res) => {
  const url = new URL(req.url, 'http://localhost');
  const path = normalize(decodeURIComponent(url.pathname)).replace(/^(\.\.[/\\])+/, '');
  const file = join(dist, path);
  if (/^\/(admin|seller|courier|staff)(\/|$)/.test(path)) return send(res, join(dist, '404.html'), 404);
  if (existsSync(file) && statSync(file).isFile()) return send(res, file);
  if (existsSync(file) && statSync(file).isDirectory()) {
    if (!path.endsWith('/')) {
      res.writeHead(301, { Location: `${path}/${url.search}` });
      return res.end();
    }
    if (existsSync(join(file, 'index.html'))) return send(res, join(file, 'index.html'));
  }
  if (/^\/account\/.+/.test(path)) return send(res, join(dist, 'account', 'index.html'));
  return send(res, join(dist, '404.html'), 404);
}).listen(port, () => console.log(`dist: http://localhost:${port}`));
