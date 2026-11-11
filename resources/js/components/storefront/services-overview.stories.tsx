import type { Meta, StoryObj } from '@storybook/react-vite';
import { ServicesOverview } from './services-overview';

const directions = ['Оснащение рабочих мест', 'Серверы и хранение данных', 'Сети и Wi-Fi', 'Переговорные и видеосвязь', 'Печать и расходные материалы', 'Бесперебойное питание', 'Сборка и модернизация ПК', 'Программное обеспечение'].map((title, id) => ({ slug: String(id), title, description: 'Подберём оборудование под задачи вашей компании. Посмотрите подходящие товары или отправьте запрос на подбор.', icon: 'server', url: '/services' }));
const meta = {
    title: 'Витрина/Карточки услуг', component: ServicesOverview,
    args: { directions },
    parameters: { docs: { description: { component: 'Секция ServicesOverview с главной страницы: реальные карточки shadcn Card, SVG-иконки и адаптивная сетка проекта. Для примера у каждой услуги есть ссылка; переходы отключены в preview.' } } },
} satisfies Meta<typeof ServicesOverview>;
export default meta;
type Story = StoryObj<typeof meta>;
export const Default: Story = { name: 'Сетка услуг' };
