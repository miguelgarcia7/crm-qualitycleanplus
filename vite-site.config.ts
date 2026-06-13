import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

/*
 * Marketing surface bundle (qualitycleanplus.com "/", ADR-0023) — fully separate
 * from the admin React/Tailwind app: its own entries, its own build directory
 * (public/site-build — a SIBLING of public/build, never nested inside it, since
 * each vite build empties its own outDir) + manifest, and its own hot file
 * (public/site.hot). The marketing Blade pages read this bundle via the
 * UseMarketingVite middleware.
 *
 *   npm run build:site   compile just this bundle
 *   npm run dev:site     dev server with HMR for marketing
 */
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/site/app.scss', 'resources/js/site/app.js'],
            buildDirectory: 'site-build',
            hotFile: 'public/site.hot',
            refresh: ['resources/views/site/**', 'resources/css/site/**', 'resources/js/site/**'],
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.x's SCSS predates the modern Dart Sass module system,
                // so the compiler emits ~hundreds of deprecation warnings from inside
                // node_modules (color-functions, global-builtin, if-function, @import).
                // We're on the latest Bootstrap (no 5.x fix until 6.0), so silence the
                // *dependency* deprecations only — warnings from our own SCSS still show.
                quietDeps: true,
            },
        },
    },
});
