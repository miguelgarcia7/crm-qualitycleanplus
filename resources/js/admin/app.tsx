import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import '@/css/admin/app.css'


import MainLayout from './layouts/MainLayout';
import AppProvidersWrapper from './components/wrappers/AppProvidersWrapper'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const page: any = await resolvePageComponent(
            `./views/${name}.tsx`,
            import.meta.glob('./views/**/*.tsx')
        )

        page.default.layout ??= (page: React.ReactNode) => (
            <AppProvidersWrapper>
                <MainLayout>
                    {page}
                </MainLayout>
            </AppProvidersWrapper>
        )

        return page
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />)
    },
});
