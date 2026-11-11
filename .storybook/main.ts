import type { StorybookConfig } from '@storybook/react-vite';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath } from 'node:url';

const config: StorybookConfig = {
    stories: ['../resources/js/**/*.stories.@(ts|tsx)'],
    addons: ['@storybook/addon-docs'],
    framework: { name: '@storybook/react-vite', options: { builder: { viteConfigPath: fileURLToPath(new URL('./vite.config.ts', import.meta.url)) } } },
    staticDirs: [{ from: '../public/fonts', to: '/fonts' }, { from: '../public/images', to: '/images' }],
    core: { disableTelemetry: true },
    // Laravel's Vite plugin belongs to the application, not the isolated preview.
    viteFinal: async config => ({
        ...config,
        resolve: { ...config.resolve, alias: { '@': fileURLToPath(new URL('../resources/js', import.meta.url)) } },
        plugins: [...(config.plugins ?? []), tailwindcss()],
    }),
};
export default config;
