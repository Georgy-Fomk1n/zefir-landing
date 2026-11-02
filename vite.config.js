import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }),
        react(),
    ],
    build: {
        target: 'es2020',
        cssCodeSplit: true,
        chunkSizeWarningLimit: 200,
        rollupOptions: {
            output: {
                // React и Motion — отдельные кэшируемые чанки, общие для всех островов
                manualChunks(id) {
                    if (id.includes('node_modules/react-dom') || id.includes('node_modules/react/') || id.includes('node_modules/scheduler')) return 'react';
                    if (id.includes('node_modules/motion') || id.includes('node_modules/framer-motion') || id.includes('node_modules/motion-')) return 'motion';
                },
            },
        },
    },
});
