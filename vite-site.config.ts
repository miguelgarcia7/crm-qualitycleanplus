import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

/*
 * Marketing surface bundle (qualitycleanplus.com "/", ADR-0023) — fully separate
 * from the admin React/Tailwind app: its own entries, its own build directory
 * (public/build/site) + manifest, and its own hot file (public/site.hot). The
 * marketing Blade pages read this bundle via the UseMarketingVite middleware.
 *
 *   npm run build:site   compile just this bundle
 *   npm run dev:site     dev server with HMR for marketing
 */
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/site/app.scss', 'resources/js/site/app.js'],
            buildDirectory: 'build/site',
            hotFile: 'public/site.hot',
            refresh: ['resources/views/site/**', 'resources/css/site/**', 'resources/js/site/**'],
        }),
    ],
});
