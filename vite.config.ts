import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import path from 'path';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/admin/app.tsx'],
            ssr: 'resources/js/admin/ssr.tsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
        wayfinder({
            formVariants: true,
            // Generate typed routes/actions under the admin SPA (matches @/ alias).
            path: 'resources/js/admin',
        }),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    resolve: {
        // Specific aliases first (they win on match); the general '@' is last.
        // Key is '@' (no trailing slash) so '@/x' resolves via the slash in the
        // import itself — and scoped npm packages like '@inertiajs/react' are
        // left untouched (no slash immediately after '@').
        alias: {
            '@/images': path.resolve(__dirname, 'resources/images'),
            '@/data': path.resolve(__dirname, 'resources/data'),
            '@/css': path.resolve(__dirname, 'resources/css'),
            '@': path.resolve(__dirname, './resources/js/admin'),
        },
    },
});
