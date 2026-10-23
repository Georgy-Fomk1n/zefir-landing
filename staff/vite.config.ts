import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// Служебное SPA для штат.зефир-и-пряник.рф: без пререндера, закрыто от индексации.
export default defineConfig({
  plugins: [react()],
  build: { target: 'es2022' },
  test: { environment: 'jsdom', include: ['src/**/*.test.{ts,tsx}'] },
} as Parameters<typeof defineConfig>[0]);
