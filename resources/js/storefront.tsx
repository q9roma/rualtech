import { createInertiaApp } from '@inertiajs/react';
import { createRoot, hydrateRoot } from 'react-dom/client';
import type { ResolvedComponent } from '@inertiajs/react';


const pages = import.meta.glob<{ default: ResolvedComponent }>('./pages/**/*.tsx');

createInertiaApp({
    title: (title) => `${title} — Алтех`,
    resolve: async (name) => (await pages[`./pages/${name}.tsx`]()).default,
    setup({ el, App, props }) {
        if (el.hasAttribute('data-server-fallback')) { el.replaceChildren(); createRoot(el).render(<App {...props} />); }
        else if (el.hasChildNodes()) hydrateRoot(el, <App {...props} />);
        else createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#171717' },
});
