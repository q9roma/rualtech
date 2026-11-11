import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import addedBrands from './brand-assets.json';
import { ProductPlaceholder } from './product-placeholder';
import { brandIdentity } from './brand-identity';
import { ProductGallery } from './product-gallery';
import { ProductCard } from './product-card';
import { Button } from '@/components/ui/button';
import type { Product } from '@/types/catalog';

const product: Product = {
    id: 26, name: 'Сервер для корпоративной инфраструктуры', sku: 'ALT-SRV-026',
    brand: 'Алтех', category: 'Серверы', categoryTone: 'sky', availability: 'На складе',
    formattedPrice: '185 000 ₽', url: '/product/26',
};
function DemoAction({ request = false }: { request?: boolean }) {
    const [clicked, setClicked] = useState(false);
    return <Button onClick={() => setClicked(true)}>{clicked ? 'Демонстрация — без отправки' : request ? 'Запросить предложение' : 'В корзину'}</Button>;
}
const meta = {
    title: 'Витрина/Карточка товара', component: ProductCard,
    parameters: { docs: { description: { component: 'Реальная карточка HomeCatalog, на основе shadcn Card. Стили импортируются из storefront.css. Данные демонстрационные; действие заменено кнопкой без API-запросов. Заголовки-ссылки не выполняют переходы. Корзина и форма заявки здесь не тестируются. Обложка: фото → логотип бренда на мягком градиенте → иконка категории. Логотип расположен прямо на градиенте, без белого бокса, рамки и тени. Палитра, путь и видимые границы логотипа задаются в brand-assets.json; categoryTone не определяет фон заглушки. Компактный вариант не показывает обложку.' } } },
    decorators: [(Story, context) => <div style={{ maxWidth: context.parameters.wide ? 1280 : 320 }}><Story /></div>],
    args: { product, action: <DemoAction /> },
    argTypes: { action: { control: false }, variant: { control: 'inline-radio', options: ['standard', 'compact'] } },
} satisfies Meta<typeof ProductCard>;
export default meta;
type Story = StoryObj<typeof meta>;
export const Default: Story = { name: 'В наличии', args: { variant: 'standard' } };
export const Compact: Story = { name: 'Компактная — без обложки', args: { variant: 'compact' } };
export const CompactComparison: Story = {
    name: 'Сравнение — с обложкой и без',
    parameters: { wide: true, docs: { description: { story: 'Один ProductCard и один объект product в обоих вариантах. Меняется только наличие обложки: категория, название, бренд, артикул, поставка, цена, наличие и действие сохранены. На главной и в каталоге используется вариант с обложкой; компактный доступен для плотных списков.' } } },
    args: { product: { ...product, name: 'Корпоративная операционная система формат поставки — электронная лицензия', category: 'Операционные системы', categoryTone: 'green', sku: 'DEMO-OS-001', formattedPrice: '12 500 ₽', availability: 'Доступно к заказу' } },
    render: args => <div className="storefront-card-comparison">{([
        ['standard', 'Текущая — с обложкой'],
        ['compact', 'Компактная — без обложки'],
    ] as const).map(([variant, label]) => <section key={variant} aria-label={label}>
        <h2 className="storefront-card-comparison-label">{label}</h2>
        <ProductCard {...args} variant={variant} />
    </section>)}</div>,
};
export const WithCover: Story = { name: 'С обложкой', args: { product: { ...product, name: 'Apple MacBook Pro 14″', brand: 'Apple', sku: 'DEMO-MBP14', category: 'Ноутбуки', categoryTone: 'lavender', image: '/images/demo/macbook-pro-1.webp', imageAlt: 'Apple MacBook Pro 14″ — общий вид' } }, parameters: { docs: { description: { story: 'Каталожное изображение из [DummyJSON](https://dummyjson.com/docs/products), только для демонстрации. Цена и артикул демонстрационные. Фото заполняет обложку целиком; края кадра могут обрезаться.' } } } };
export const WithoutCover: Story = { name: 'Нейтральная заглушка — бренд без логотипа', args: { product: { ...product, image: null } } };
export const Gallery: Story = { name: 'Галерея — наведение мыши', args: { product: { ...product, name: 'Apple MacBook Pro 14″', brand: 'Apple', sku: 'DEMO-MBP14', category: 'Ноутбуки', categoryTone: 'lavender', images: ['/images/demo/macbook-pro-1.webp', '/images/demo/macbook-pro-2.webp', '/images/demo/macbook-pro-3.webp'] } }, parameters: { docs: { description: { story: 'Три каталожных ракурса одного MacBook из [DummyJSON](https://dummyjson.com/docs/products). Цена и артикул демонстрационные. Двигайте мышь по обложке слева направо. Индикаторы доступны по нажатию и с клавиатуры (Tab, Enter, стрелки). При уходе мыши возвращается первый кадр. Реальные фотографии передаются через product.images; одиночная обложка product.image поддерживается.' } } } };
export const BrokenCover: Story = { name: 'Ошибка загрузки обложки', args: { product: { ...product, image: '/images/storybook-missing-product.png' } }, parameters: { docs: { description: { story: 'Намеренно недоступный URL: вместо сломанной картинки появляется та же заглушка. Размер обложки сохраняется.' } } } };
export const SoftwareCover: Story = { name: 'Логотип — Microsoft', args: { product: { ...product, brand: 'Microsoft', name: 'Лицензия корпоративной операционной системы', category: 'Программное обеспечение', categoryTone: 'green', image: null } } };
export const OnRequest: Story = { name: 'Цена по запросу', args: { product: { ...product, formattedPrice: 'По запросу', availability: null }, action: <DemoAction request /> } };
export const LongTitle: Story = { name: 'Длинное название', args: { product: { ...product, name: 'Корпоративная операционная система для защищённой серверной инфраструктуры с расширенной технической поддержкой формат поставки — электронная лицензия', category: 'Программное обеспечение', categoryTone: 'green' } } };
export const Minimal: Story = { name: 'Без бренда и артикула', args: { product: { ...product, brand: null, sku: null, category: null, categoryTone: 'neutral', availability: null } } };
export const Mobile: Story = { name: 'Узкая карточка', decorators: [Story => <div style={{ width: 288, maxWidth: '100%' }}><Story /></div>] };

export const CatalogGrid: Story = {
    name: 'Карточки в каталоге', parameters: { wide: true },
    render: () => <div className="storefront-home-products">
        <ProductCard variant="standard" product={{ ...product, name: 'Apple MacBook Pro 14″', brand: 'Apple', sku: 'DEMO-MBP14', category: 'Ноутбуки', categoryTone: 'lavender', images: ['/images/demo/macbook-pro-1.webp', '/images/demo/macbook-pro-2.webp', '/images/demo/macbook-pro-3.webp'] }} action={<DemoAction />} />
        <ProductCard variant="standard" product={{ ...product, id: 27, name: 'Сервер для корпоративной инфраструктуры', formattedPrice: 'По запросу', availability: null }} action={<DemoAction request />} />
        <ProductCard variant="standard" product={{ ...product, id: 28, name: 'Лицензия корпоративной операционной системы формат поставки — электронная лицензия', category: 'Операционные системы', categoryTone: 'green', formattedPrice: 'По запросу', availability: null }} action={<DemoAction request />} />
        <ProductCard variant="standard" product={{ ...product, id: 29, name: 'Apple MacBook Pro 14″', brand: 'Apple', sku: 'DEMO-MBP14', category: 'Ноутбуки', categoryTone: 'lavender', image: '/images/demo/macbook-pro-2.webp' }} action={<DemoAction />} />
    </div>,
};

const comparisonProducts: Product[] = [
    { ...product, name: 'Apple MacBook Pro 14″', brand: 'Apple', sku: 'DEMO-MBP14', category: 'Ноутбуки', categoryTone: 'lavender', images: ['/images/demo/macbook-pro-1.webp', '/images/demo/macbook-pro-2.webp', '/images/demo/macbook-pro-3.webp'] },
    { ...product, id: 27, name: 'Сервер', sku: 'DEMO-SRV-027', formattedPrice: 'По запросу', availability: null },
    { ...product, id: 28, name: 'Корпоративная операционная система для защищённой серверной инфраструктуры с расширенной технической поддержкой формат поставки — электронная лицензия', category: 'Операционные системы', categoryTone: 'green', sku: 'DEMO-OS-028', formattedPrice: '12 500 ₽', availability: 'Доступно к заказу' },
    { ...product, id: 29, name: 'Сетевой адаптер', category: 'Комплектующие', categoryTone: 'sand', brand: null, sku: null, formattedPrice: '4 500 ₽', availability: 'Под заказ' },
];

export const GridComparison: Story = {
    name: 'Сравнение гридов — с обложками и без',
    parameters: { wide: true, docs: { description: { story: 'Два грида используют одни и те же четыре товара и сетку каталога: 4, 2 или 1 колонка в зависимости от ширины экрана. В каждом ряду карточки равной высоты; цена и действие выровнены снизу. Длинное название показано полностью. Отсутствующие бренд и артикул — свойства демонстрационного товара, одинаковые в обоих гридах.' } } },
    render: () => <div style={{ display: 'grid', gap: 40 }}>{([
        ['standard', 'С обложками — фото и заглушки'],
        ['compact', 'Компактный — без обложек'],
    ] as const).map(([variant, label]) => <section key={variant} aria-label={label}>
        <h2 className="storefront-card-comparison-label">{label}</h2>
        <div className="storefront-home-products">{comparisonProducts.map(item => <ProductCard key={item.id} product={item} variant={variant} action={<DemoAction request={item.formattedPrice === 'По запросу'} />} />)}</div>
    </section>)}</div>,
};

export const BrandPalette: Story = {
    name: 'Логотипы и мягкие градиенты',
    parameters: { wide: true, docs: { description: { story: 'Палитра берётся из проверенного SVG/PNG в brand-assets.json. Радиальное свечение снизу плавно уходит в белый сверху. У многоцветных знаков используются до четырёх цветов. В карточках контейнер логотипа уменьшен до min(44%, 112px), с отступом 12px. На странице товара логотип крупнее. Размер обложки не меняется. Логотип без подложки, рамки и тени. Подпись бренда в обложке не дублируется; бренд остаётся в данных карточки. Пропорции сохранены; выравнивание учитывает видимые границы, а не пустые поля файла. Сам файл логотипа должен иметь прозрачный фон: белые прямоугольники и другие подложки недопустимы. Неизвестный или отсутствующий бренд получает иконку категории. Ошибка загрузки логотипа также приводит к нейтральной заглушке.' } } },
    render: () => <div className="storefront-placeholder-palette">{['Microsoft', 'Hisense', 'Synology', 'Pantum', 'Genius', 'Неизвестный бренд'].map((brand, index) => <ProductCard key={index} variant="standard" product={{ ...product, id: index + 100, brand, name: 'Демонстрационный товар', image: null }} action={<DemoAction />} />)}</div>,
};

export const PhotoPriority: Story = {
    name: 'Фото важнее логотипа',
    args: { variant: 'standard', product: { ...product, brand: 'Apple', image: '/images/demo/macbook-pro-1.webp', imageAlt: 'Демонстрационное фото MacBook' } },
    parameters: { docs: { description: { story: 'У Apple есть логотип, но при наличии доступного фото показывается именно фото. Логотип не накладывается на фотографию.' } } },
};

export const BrokenPhotoWithBrand: Story = {
    name: 'Ошибка фото → логотип',
    args: { variant: 'standard', product: { ...product, brand: 'Microsoft', image: '/images/storybook-missing-product.png' } },
    parameters: { docs: { description: { story: 'Намеренно недоступное фото заменяется логотипом Microsoft на мягком градиенте. Для бренда без логотипа это состояние показано в истории «Ошибка загрузки обложки».' } } },
};

export const DetailCovers: Story = {
    name: 'Обложки на странице товара',
    parameters: { wide: true, docs: { description: { story: 'Реальный ProductGallery использует тот же ProductPlaceholder, что и карточка. Слева фото, в центре логотип без фото, справа иконка категории. Размер области сохраняется. Для добавления бренда положите прозрачный SVG или PNG в public/images/partners и укажите файл, цвет и источник в brand-assets.json; ключ — название бренда в нижнем регистре. Проверьте исходник на шахматном фоне.' } } },
    render: () => <div className="storefront-placeholder-palette">{[
        { ...product, brand: 'Apple', image: '/images/demo/macbook-pro-1.webp' },
        { ...product, brand: 'Microsoft', image: null },
        { ...product, brand: null, image: null },
    ].map((item, index) => <div className="shop-product-intro" style={{ display: 'block' }} key={index}><ProductGallery product={item} /></div>)}</div>,
};

export const SmallerLogos: Story = {
    name: 'Уменьшенные логотипы — главная и каталог',
    parameters: { wide: true, docs: { description: { story: 'Текущий вариант карточек главной и каталога. Логотип занимает меньше места, вокруг него больше воздуха; градиент и высота обложки сохранены. Здесь показаны разные пропорции логотипов и иконка категории.' } } },
    render: () => <div className="storefront-home-products">{['Microsoft', 'Dell', 'Cisco', null].map((brand, index) => <ProductCard key={index} variant="standard" product={{ ...product, id: 200 + index, brand, name: 'Демонстрационный товар' }} action={<DemoAction />} />)}</div>,
};

export const ExpandedBrands: Story = {
    name: 'Расширенный справочник брендов',
    parameters: { wide: true },
    render: () => <div className="storefront-placeholder-palette">{Object.keys(addedBrands).map((brand, index) => <ProductCard key={brand} variant="standard" product={{ ...product, id: 300 + index, brand, name: 'Демонстрационный товар', image: null }} action={<DemoAction />} />)}</div>,
};
export const TransparentAssets: Story = {
    name: 'Прозрачность исходников логотипов',
    parameters: { wide: true, docs: { description: { story: 'Шахматный фон должен просвечивать вокруг логотипа. Здесь проверяется прозрачность исходного файла, без маскировки белого фона средствами CSS.' } } },
    render: () => <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: 16 }}>{Object.entries({ ...addedBrands, hp: { file: 'hp.svg' }, supermicro: { file: 'supermicro.svg' } }).map(([brand, asset]) => <figure key={brand} style={{ margin: 0 }}><div style={{ height: 100, display: 'grid', placeItems: 'center', background: 'repeating-conic-gradient(#ddd 0% 25%, #f4f4f4 0% 50%) 0 / 16px 16px' }}><img src={'/images/partners/' + asset.file} alt={brand} style={{ maxWidth: 112, maxHeight: 64, filter: brandIdentity(brand)?.monochrome ? 'brightness(0)' : undefined }} /></div><figcaption>{brand}</figcaption></figure>)}</div>,
};

export const BrandAlignment: Story = {
    name: 'Выравнивание, палитры и контраст', parameters: { wide: true },
    render: () => <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: 16 }}>{['Microsoft', 'Hisense', 'Synology', 'Pantum', 'Genius', 'Cactus', 'Apple', 'Dell', 'Cisco', 'Formula V line', 'Marvo', 'ACD', 'Netac', 'Accord PC', 'WD', 'G&G', 'Apacer', 'Rikor'].map(brand => <div key={brand} className="storefront-product-visual" style={{ height: 180, borderRadius: 12, overflow: 'hidden' }}><ProductPlaceholder brand={brand} category="Комплектующие" /></div>)}</div>,
};
export const CategoryPlaceholders: Story = {
    name: 'Нет фото и логотипа — иконки категорий', parameters: { wide: true },
    render: () => <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: 16 }}>{['Ноутбуки', 'Серверы', 'Мониторы', 'Клавиатуры', 'Мыши и трекпады', 'Гарнитуры', 'Принтеры', 'Маршрутизаторы', 'SSD', 'Операционные системы', 'Аккумуляторы для ИБП', null].map((category, index) => <figure key={index} style={{ margin: 0 }}><div className="storefront-product-visual" style={{ height: 160, borderRadius: 12, overflow: 'hidden' }}><ProductPlaceholder brand={null} category={category} /></div><figcaption style={{ marginTop: 8, fontSize: 12 }}>{category ?? 'Категория не указана'}</figcaption></figure>)}</div>,
};
