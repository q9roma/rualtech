import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardTitle } from '@/components/ui/card';
import { ProductCard } from '@/components/storefront/product-card';
import { BrandRibbon } from '@/components/storefront/brand-ribbon';
import { ProductGallery } from '@/components/storefront/product-gallery';
import { Tabs } from 'radix-ui';
import { Link } from '@inertiajs/react';
import { Truck, ChevronRight, ArrowUpRight, Check, Eye, Pencil } from 'lucide-react';
import { Page, type PageProps } from '@/components/storefront/content';
import { ContactDialog } from '@/components/storefront/contact-dialog';
import { ProductAction, ProductStock, hasPrice, stockLabel } from '@/components/storefront/product-purchase';
import type { Product } from '@/types/catalog';

function productTitle(product: Product) { return product.name.split(/\s+формат поставки\s*[-—:]?/i)[0]; }
function format(product: Product) { return product.name.match(/формат поставки\s*[-—:]?\s*(.+?)(?:\s*\(вкл\.|$)/i)?.[1]; }
export default function ProductPage(props: PageProps & { product: Product; related: Product[]; variants: Product[]; canAddToCart: boolean; addToCartUrl: string; preview?: boolean; previewIsPublished?: boolean; photoTest?: { sources: string[]; count: number } }) {
    const { product } = props;
    const delivery = format(product);
    const specs = [...(product.specifications ?? []).map(item => [item.name, item.value]), ['Производитель', product.brand], ['Артикул', product.sku], ['Категория', product.category], ['Формат поставки', delivery], ['Наличие', stockLabel(product.availability)]].filter(([, value]) => value);
    const categoryUrl = product.categoryUrl || `${props.navigation.catalog}?category=${encodeURIComponent(product.category || '')}`;
    const variants = props.variants;
    return <Page {...props} hero>
        {props.photoTest && <div className="mb-6 rounded-lg border bg-card p-4" role="status"><strong>Тест фотографий · без публикации</strong><p className="mt-1 text-sm text-muted-foreground">{props.photoTest.count} фото · {props.photoTest.sources.join(' + ') || 'Пригодных фото пока нет'}. Цена, наличие и описание взяты из текущей карточки. Изображения проверены технически; комплектацию и ракурсы оцениваем отдельно.</p><a className="mt-2 inline-block underline" href="/catalog/photo-quality">Все тестовые карточки и отчёт</a></div>}
        {props.preview && <Card role="region" aria-label="Предпросмотр администратора" className="mb-6 rounded-lg py-4 shadow-none">
            <CardContent className="flex flex-col gap-4 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div className="flex items-start gap-3">
                    <Eye className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <div className="space-y-1.5">
                        <div className="flex flex-wrap items-center gap-2">
                            <CardTitle className="text-sm font-medium">Предпросмотр</CardTitle>
                            <Badge variant="secondary">{props.previewIsPublished ? 'Опубликован' : 'Черновик'}</Badge>
                        </div>
                        <CardDescription className="text-xs">{props.previewIsPublished ? 'Товар опубликован в каталоге' : 'Этот товар виден только сотрудникам'}</CardDescription>
                    </div>
                </div>
                <Button asChild variant="outline" size="xs" className="h-7 min-h-7 self-start sm:self-center">
                    <a href={`/admin/products/${product.id}/edit`}><Pencil aria-hidden="true" />Редактировать</a>
                </Button>
            </CardContent>
        </Card>}
        <article className="shop-detail">
            <nav className="shop-breadcrumbs" aria-label="Хлебные крошки"><Link href={props.navigation.home}>Главная</Link><ChevronRight /><Link href={props.navigation.catalog}>Каталог</Link>{product.category && <><ChevronRight /><Link href={categoryUrl}>{product.category}</Link></>}</nav>

            <div className="shop-main-grid">
                <div className="shop-product-info"><div className="shop-product-intro"><div><ProductGallery key={product.id} product={product} /></div><section className="shop-summary" aria-label="Сведения о товаре"><header className="shop-heading"><div className="shop-eyebrow">{product.brand || product.category}</div><h1>{productTitle(product)}</h1><div className="shop-meta">{product.sku && <span>Артикул: {product.sku}</span>}<ProductStock value={product.availability} /></div></header>{(delivery || variants.length > 0) && <div className="shop-variants"><h3>Формат поставки</h3><div>{delivery && <span aria-current="true"><Check size={14} />{delivery}</span>}{variants.map(item => <Link key={item.id} href={item.url}>{format(item)}</Link>)}</div></div>}</section></div>
            <Tabs.Root defaultValue="specifications" className="shop-tabs">
                <Tabs.List className="shop-section-nav" aria-label="Информация о товаре">
                    <Tabs.Trigger value="description">Описание</Tabs.Trigger>
                    <Tabs.Trigger value="specifications">Характеристики</Tabs.Trigger>
                </Tabs.List>
                <div className="shop-tab-panels">
                    <Tabs.Content value="description" forceMount className="shop-tab-panel data-[state=inactive]:hidden">
                        {product.description ? <p className="whitespace-pre-line break-words">{product.description}</p> : product.supplierDescription ? <><h3>Описание поставщика</h3><p className="whitespace-pre-line break-words">{product.supplierDescription}</p></> : <p className="shop-muted">Поставщик не предоставил описание товара.</p>}
                        {delivery && <p>Формат поставки: {delivery}.</p>}
                        <p className="shop-muted">Если вам нужно уточнить комплектацию или совместимость, задайте вопрос нашему менеджеру перед покупкой.</p>
                    </Tabs.Content>
                    <Tabs.Content value="specifications" forceMount className="shop-tab-panel data-[state=inactive]:hidden">
                        
                        <dl className="shop-specs shop-full-specs">{specs.map(([label, value], index) => <div key={index}><dt>{label}</dt><dd>{value}</dd></div>)}</dl>
                    </Tabs.Content>
                </div>
            </Tabs.Root>
<section className="equipment-help" aria-label="Помощь с выбором оборудования"><div className="catalog-help-copy"><h2>Подберём оборудование под вашу задачу</h2><p>Проверим совместимость, уточним комплектацию и поможем выбрать подходящее решение.</p></div><ContactDialog label="Поставить задачу" secondary service={`Подбор оборудования: ${product.name}`} className="catalog-help-action" icon={<ArrowUpRight size={16} />} /></section></div>
                <aside className="shop-purchase" aria-label="Покупка товара"><div className="shop-price-group"><div className="shop-price-label">{hasPrice(product) ? 'Цена за 1 шт.' : 'Стоимость'}</div><p className="shop-price">{product.formattedPrice}</p><p className="shop-tax">{product.name.match(/вкл\.\s*(НДС\s*\d+%)/i)?.[1]?.replace(/\s+/g, ' ')?.concat(' уже в цене') || 'Окончательную стоимость подтвердит менеджер'}</p></div><div className="shop-buy-actions">{props.photoTest ? <p className="text-sm text-muted-foreground">Тестовый просмотр фотографий</p> : <><ProductAction product={product} />{product.canAddToCart && <ContactDialog label="Запросить счёт" secondary service={`Запрос счёта: ${product.sku || ''} — ${product.name}`} />}</>}</div><section className="shop-delivery" aria-label="Условия доставки">
                        <div className="shop-delivery-heading"><span className="shop-delivery-free">За наш счёт при заказе от 150 000 ₽</span></div>
                        <div className="shop-delivery-promise"><h2>Доставим<br />в течение суток</h2><Truck size={30} strokeWidth={1.25} aria-hidden="true" /></div>
                        <p className="shop-delivery-caption">По Москве и Московской области</p>
                        
                        <div className="shop-delivery-carriers"><BrandRibbon items={['СДЭК', 'Деловые Линии', 'ПЭК', 'Почта России', 'DPD', 'Более 5 компаний']} label="Транспортные компании" className="shop-carrier-ribbon" /></div>
                        <p className="shop-delivery-footer">Отправляем своими курьерами или любой транспортной компанией</p>
                    </section></aside>
            </div>
            {props.related.length > 0 && <section id="related" className="shop-related"><div className="shop-related-heading"><h2>Смотрите также</h2><Link href={categoryUrl}>Все товары категории <ArrowUpRight size={16} /></Link></div><div className="shop-related-grid">{props.related.map(item => <ProductCard key={item.id} product={item} />)}</div></section>}
        </article>
    </Page>;
}
