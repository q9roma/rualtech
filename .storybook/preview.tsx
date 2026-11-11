import type { Preview } from '@storybook/react-vite';
import '../resources/css/storefront.css';

const preview: Preview = {
    tags: ['autodocs'],
    parameters: {
        layout: 'padded',
        backgrounds: { options: { storefront: { name: 'Фон сайта', value: '#fafafa' }, white: { name: 'Белая поверхность', value: '#ffffff' } } },
        controls: { expanded: true },
    },
    initialGlobals: { backgrounds: { value: 'storefront' } },
    decorators: [Story => <div style={{ fontFamily: 'Geist, Arial, sans-serif' }} onClickCapture={event => {
        // Stories demonstrate appearance; links must not issue Inertia requests.
        if ((event.target as HTMLElement).closest('a')) { event.preventDefault(); event.stopPropagation(); }
    }}><Story /></div>],
};
export default preview;
