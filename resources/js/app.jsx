import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

let appName = import.meta.env.VITE_APP_NAME || '';

createInertiaApp({
    title: (title) => {
        const name = appName || 'Panel Admin';
        return title ? `${title} · ${name}` : name;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const company =
            props.initialPage?.props?.app?.company_name ||
            import.meta.env.VITE_APP_NAME ||
            '';
        if (company) {
            appName = company;
        }

        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#0d9488',
    },
});
