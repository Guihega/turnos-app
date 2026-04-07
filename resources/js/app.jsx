import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from './Theme/ThemeContext';

const appName = import.meta.env.VITE_APP_NAME || 'Olinora';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <ThemeProvider defaultTheme="refined-dark">
                <App {...props} />
            </ThemeProvider>
        );
    },
    progress: {
        color: '#3D7AFF',
    },
});
