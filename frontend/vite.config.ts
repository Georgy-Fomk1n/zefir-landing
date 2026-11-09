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
  test: {
    environment: 'jsdom',
    setupFiles: ['./vitest.setup.ts'],
    include: ['src/**/*.test.{ts,tsx}', 'scripts/**/*.test.mjs', '../packages/shared/src/**/*.test.{ts,tsx}'],
    coverage: {
      provider: 'v8',
      include: ['src/**/*.{ts,tsx}', '../packages/shared/src/**/*.{ts,tsx}'],
      exclude: ['**/*.test.*', 'src/main.tsx', 'src/routes.tsx', 'src/data/*.json'],
      reporter: ['text-summary', 'text', 'lcov'],
      // Пороги проверяются в CI (npm run test:coverage); значения — docs/testing.md
      thresholds: { lines: 90, statements: 90, functions: 85, branches: 80 },
    },
  },
} as Parameters<typeof defineConfig>[0]);
