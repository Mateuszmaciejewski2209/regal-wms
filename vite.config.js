import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/scanner.js'],
            refresh: true,
            fonts: [
                bunny('Inter', { weights: [400, 500, 600, 700] }),
                bunny('JetBrains Mono', { weights: [400, 500] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        // Dostęp z telefonu w sieci LAN podczas `npm run dev`
        host: '0.0.0.0',
        hmr: { host: process.env.VITE_HMR_HOST || 'localhost' },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
