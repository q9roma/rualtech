import type { Meta, StoryObj } from '@storybook/react-vite';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from './card';
import { Button } from './button';

const meta = {
    title: 'shadcn/Card', component: Card,
    parameters: { docs: { description: { component: 'Базовая карточка проекта из shadcn/ui. Белая поверхность --card, рамка --border, скругление rounded-xl. Состав: CardHeader, CardTitle, CardDescription, CardContent, CardFooter. Карточки витрины расширяют эту основу своими классами; не копируйте их CSS в stories.' } } },
    decorators: [Story => <div style={{ maxWidth: 440 }}><Story /></div>],
    render: args => <Card {...args}><CardHeader><CardTitle>ИТ-решения для бизнеса</CardTitle><CardDescription>Описание карточки</CardDescription></CardHeader><CardContent>Содержимое с отступами и типографикой проекта.</CardContent><CardFooter><Button variant="outline">Подробнее</Button></CardFooter></Card>,
} satisfies Meta<typeof Card>;
export default meta;
type Story = StoryObj<typeof meta>;
export const Default: Story = { name: 'Базовая' };
export const ContentOnly: Story = { name: 'Только содержимое', render: args => <Card {...args}><CardContent>Пока нет опубликованных материалов.</CardContent></Card> };
