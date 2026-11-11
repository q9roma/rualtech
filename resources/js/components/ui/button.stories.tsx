import type { Meta, StoryObj } from '@storybook/react-vite';
import { Button } from './button';
const meta = { title: 'shadcn/Button', component: Button, args: { children: 'Подробнее', variant: 'default', disabled: false }, argTypes: { variant: { control: 'select', options: ['default', 'secondary', 'outline', 'ghost', 'link', 'destructive'] }, size: { control: 'select', options: ['default', 'xs', 'sm', 'lg'] } } } satisfies Meta<typeof Button>;
export default meta;
type Story = StoryObj<typeof meta>;
export const Default: Story = {};
export const Outline: Story = { args: { variant: 'outline' } };
export const Disabled: Story = { args: { disabled: true } };
