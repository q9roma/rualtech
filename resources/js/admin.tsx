import '../css/admin.css';
import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { createRoot, hydrateRoot } from 'react-dom/client';

const pages = import.meta.glob<{ default: ResolvedComponent }>('./pages/Admin/*.tsx');
createInertiaApp({
    title: title => `${title} — Алтех · Управление`,
    resolve: async name => (await pages[`./pages/${name}.tsx`]()).default,
    setup({ el, App, props }) {
        if (el.hasChildNodes()) hydrateRoot(el, <App {...props} />);
        else createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#737373' },
});
