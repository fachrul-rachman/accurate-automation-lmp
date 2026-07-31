import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

import '../css/app.css';

const appName = import.meta.env.VITE_APP_NAME || 'Accurate Check';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob<{ default: ComponentType }>(
            './pages/**/*.tsx',
            { eager: true },
        );
        const page = pages[`./pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        return page;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#0f766e',
    },
});
