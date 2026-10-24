import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// Служебное SPA для штат.зефир-и-пряник.рф: без пререндера, закрыто от индексации.
export default defineConfig({
  plugins: [react()],
  build: { target: 'es2022' },
  test: {
    environment: 'jsdom',
    setupFiles: ['./vitest.setup.ts'],
    include: ['src/**/*.test.{ts,tsx}'],
    coverage: {
      provider: 'v8',
      include: ['src/**/*.{ts,tsx}'],
      exclude: ['**/*.test.*', 'src/main.tsx'],
      reporter: ['text-summary', 'text', 'lcov'],
      thresholds: { lines: 85, statements: 85, functions: 80, branches: 75 },
    },
  },
} as Parameters<typeof defineConfig>[0]);
