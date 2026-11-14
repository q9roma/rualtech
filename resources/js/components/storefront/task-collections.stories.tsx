import type { Meta, StoryObj } from '@storybook/react-vite';
import { TaskCollections } from './task-collections';

const meta = {
    title: 'Витрина/Подборки',
    component: TaskCollections,
    args: { catalogUrl: '/catalog', appearance: 'light' },
    argTypes: {
        appearance: { control: 'inline-radio', options: ['light', 'dark'], name: 'Оформление' },
        children: { control: false },
        catalogUrl: { control: false },
    },
} satisfies Meta<typeof TaskCollections>;
export default meta;
type Story = StoryObj<typeof meta>;
export const Light: Story = { name: 'Светлые карточки', args: { appearance: 'light' } };
export const Dark: Story = { name: 'Тёмные карточки', args: { appearance: 'dark' } };
