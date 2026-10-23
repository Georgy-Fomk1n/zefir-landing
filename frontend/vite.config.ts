import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';
import type { ViteReactSSGOptions } from 'vite-react-ssg';

const ssgOptions: ViteReactSSGOptions = {
  entry: 'src/main.tsx',
  // /catalog/vkusy/ → /catalog/vkusy/index.html: единая политика «слэш в конце», Apache сам делает 301 без слэша
  dirStyle: 'nested',
  script: 'async',
  // Критический CSS инлайном, остальное — асинхронно
  beastiesOptions: { preload: 'body', pruneSource: false, reduceInlineStyles: false },
  // "*" не пререндерим: настоящий 404 отдаёт сервер (ErrorDocument → /404.html)
  includedRoutes: (paths) => paths.filter((p) => !p.includes('*') && !p.includes(':')),
};

export default defineConfig({
  plugins: [react()],
  build: { target: 'es2022', cssCodeSplit: true, assetsInlineLimit: 2048 },
  ssr: { noExternal: ['@zefir/shared'] },
  ssgOptions,
  test: { environment: 'jsdom', include: ['src/**/*.test.{ts,tsx}', 'scripts/**/*.test.mjs'] },
} as Parameters<typeof defineConfig>[0]);
