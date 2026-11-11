import type { Meta, StoryObj } from '@storybook/react-vite';
import { useId, useState } from 'react';
import { SearchSuggestions, type SearchSuggestionsProps } from './live-search';
import type { Product } from '@/types/catalog';

const items: Product[] = [
    { id: 1, name: 'Microsoft Windows 11 Pro формат поставки — конверт с диском', brand: 'Microsoft', category: 'Программное обеспечение', sku: 'FQC-10547', formattedPrice: '2 690,00 ₽', availability: '>50', url: '/product/1' },
    { id: 2, name: 'Microsoft Windows 11 Pro формат поставки — наклейка', brand: 'Microsoft', category: 'Программное обеспечение', sku: 'FQC-10547', formattedPrice: '2 190,00 ₽', availability: 'Под заказ', url: '/product/2' },
    { id: 3, name: 'Apple MacBook Pro 14″', brand: 'Apple', category: 'Ноутбуки', sku: 'DEMO-MBP14', formattedPrice: 'По запросу', availability: null, image: '/images/demo/macbook-pro-1.webp', url: '/product/3' },
];
function Preview(args: SearchSuggestionsProps) {
    const id = useId();
    const [active, setActive] = useState(args.active);
    return <SearchSuggestions {...args} id={id} active={active} onActive={setActive} />;
}
const meta = {
    title: 'Витрина/Поисковая выдача', component: SearchSuggestions,
    parameters: { docs: { description: { component: 'Реальное представление LiveSearch с демонстрационными данными, без сетевых запросов и переходов. Серый hover совпадает с выделением при выборе стрелками. Нижняя строка «Показать все результаты» со счётчиком прилегает к боковым и нижнему краям панели: без внешних отступов, отдельной рамки и синей заливки. Прокручивается только список; нижняя строка остаётся видимой и не перекрывает товары. При загрузке и пустой выдаче строка скрыта. Реальный поиск дополнительно обрабатывает debounce, отмену запросов, Escape и Enter.' } } },
    decorators: [(Story, context) => <div style={{ width: '100%', maxWidth: context.parameters.mobile ? 360 : 820, height: 640, position: 'relative' }}><Story /></div>],
    args: { id: 'search-demo', items, total: 24, status: 'ready', active: -1, term: 'Pro', resultsUrl: '/catalog?search=Pro', onActive: () => {}, onSelect: () => {} },
    argTypes: { onActive: { control: false }, onSelect: { control: false }, id: { control: false }, status: { control: 'inline-radio', options: ['ready', 'loading', 'error'] } },
    render: args => <Preview key={`${args.status}-${args.active}`} {...args} />,
} satisfies Meta<typeof SearchSuggestions>;
export default meta;
type Story = StoryObj<typeof meta>;
export const Results: Story = { name: 'Результаты и переход ко всем товарам' };
export const Highlighted: Story = { name: 'Серый hover и выбранная строка', args: { active: 0 }, parameters: { docs: { description: { story: 'Первая строка выделена через aria-selected — тот же серый стиль, что у :hover. Наведите курсор на другую строку для сравнения. У нижней строки также есть серый hover и видимый фокус с клавиатуры.' } } } };
export const Scrollable: Story = { name: 'Прокрутка — нижняя строка вровень с панелью', args: { items: Array.from({ length: 9 }, (_, i) => ({ ...items[i % items.length], id: i + 10 })), total: 128 } };
export const Empty: Story = { name: 'Ничего не найдено', args: { items: [], total: 0 } };
export const Loading: Story = { name: 'Загрузка', args: { items: [], total: 0, status: 'loading' } };
export const Error: Story = { name: 'Ошибка подсказок', args: { items: [], total: 0, status: 'error' } };
export const Mobile: Story = { name: 'Узкая выдача', parameters: { mobile: true } };
