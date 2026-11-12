import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath, URL } from 'node:url';
import { inertiaDevSsr } from './scripts/vite-inertia-ssr.mjs';

export default defineConfig({
    server: { host: '127.0.0.1', port: 5173, strictPort: true },
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    plugins: [
        inertiaDevSsr(),
        laravel({
            input: ['resources/css/app.css', 'resources/css/storefront-entry.css', 'resources/js/app.js', 'resources/js/storefront.tsx', 'resources/js/admin.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: ['resources/views/**', 'routes/**', 'app/Http/**', 'app/Support/**'],
        }),
        tailwindcss(),
        react(),
    ],
});
