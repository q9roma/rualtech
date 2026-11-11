import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import type { Page } from '@inertiajs/core';
import { renderToString } from 'react-dom/server';

const pages = import.meta.glob<{ default: ResolvedComponent }>('./pages/**/*.tsx', { eager: true });

export const render = (page: Page) => createInertiaApp({
    page,
    render: renderToString,
    title: (title) => `${title} — Алтех`,
    resolve: (name) => pages[`./pages/${name}.tsx`].default,
    setup: ({ App, props }) => <App {...props} />,
});
