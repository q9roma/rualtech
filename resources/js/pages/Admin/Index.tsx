import { CatalogFilter, CatalogSearchPanel } from '@/components/admin/catalog-search-panel';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table';
import { useEffect, useState, type FormEvent } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { ArrowUpDown, Check, Columns2, Ellipsis, Eye, FileUp, Pencil, Plus, SlidersHorizontal, Search, Trash2, X } from 'lucide-react';
import { DropdownMenu, DropdownMenuTrigger, DropdownMenuPortal, DropdownMenuContent, DropdownMenuItem, DropdownMenuCheckboxItem } from '@/components/ui/dropdown-menu';
import { ProductImagePlaceholder } from '@/components/admin/product-image-placeholder';
import AdminLayout from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { CheckBox, Choice, Confirm, CopyValue, Pager, date } from '@/components/admin/controls';
import type { Column, Options, Pagination, RecordData } from '@/components/admin/types';

type Props = { resource: string; title: string; columns: Column[]; records: Pagination<RecordData>; filters: { key: string; label: string; options: Options }[]; query: Record<string, string | number>; canCreate: boolean; canDelete: boolean; presenceInfo?: {source: string; file: string | null} | null };
function ProductCover({ src, brand, category }: { src: string | null; brand: string | null; category: string | null }) {
    const [failed, setFailed] = useState<string | null>(null);
    return src && failed !== src ? <img src={src} alt="" loading="lazy" onError={() => setFailed(src)}/> : <ProductImagePlaceholder/>;
}

export default function Index(props: Props) { return <ResourceTable key={props.resource} {...props}/>; }
function ResourceTable({ resource, title, columns, records, filters, query, canCreate, canDelete, presenceInfo }: Props) {
    const { url } = usePage();
    useEffect(() => { try { sessionStorage.setItem('admin-list-'+resource, url); } catch {} }, [resource, url]);
    const createLabels: Record<string, string> = { products: 'Добавить товар', services: 'Добавить услугу', 'product-categories': 'Добавить категорию', 'service-categories': 'Добавить категорию', 'catalog-mappings': 'Добавить правило', 'contact-requests': 'Новая заявка', 'blog-posts': 'Написать статью', pages: 'Добавить страницу' };
    const emptyLabels: Record<string,string> = { products:'Товаров пока нет', orders:'Заказов пока нет', 'contact-requests':'Заявок пока нет', services:'Услуг пока нет', 'product-categories':'Категорий пока нет', 'service-categories':'Категорий пока нет', 'catalog-mappings':'Правил пока нет', 'blog-posts':'Статей пока нет', pages:'Страниц пока нет' };
    const columnLabel = (column: Column) => column.type==='image' ? 'Фото' : column.key==='sort_order' ? 'Порядок' : column.label;
    const hasQuery = Boolean(query.search || filters.some(f => query[f.key] != null && query[f.key] !== ''));
    function resetFilters() { visit(Object.fromEntries(['search', ...filters.map(f => f.key)].map(key => [key, '']))); }
    const [loading, setLoading] = useState(false);
    const isProducts = resource === 'products';
    const [filtersOpen, setFiltersOpen] = useState(false);
    const activeFilters = filters.filter(f => (!isProducts || f.key !== 'is_active') && query[f.key] != null && query[f.key] !== '');
    const [search, setSearch] = useState(String(query.search ?? ''));
    const [selected, setSelected] = useState<number[]>([]);
    const [visible, setVisible] = useState(columns.filter(c => c.visible).map(c => c.key));
    const [deleting, setDeleting] = useState<number[] | null>(null);
    const [busy, setBusy] = useState(false);
    useEffect(() => { setSelected([]); setSearch(String(query.search ?? '')); }, [records.data, query.search]);
    useEffect(() => { try { const stored = JSON.parse(localStorage.getItem('admin-columns-'+resource) ?? 'null'); if (Array.isArray(stored) && stored.some(key => columns.some(c => c.key === key))) setVisible(stored.filter(key => columns.some(c => c.key === key))); } catch {} }, [resource]);
    function visit(changes: Record<string, string | number>) { router.get('/admin/'+resource, { ...query, ...changes, page: 1 }, { preserveState: true, preserveScroll: true, onStart: () => setLoading(true), onFinish: () => setLoading(false) }); }
    function submit(e: FormEvent) { e.preventDefault(); visit({ search }); }
    function toggleColumn(key: string) { const next = visible.includes(key) ? visible.filter(k => k !== key) : [...visible, key]; if (!next.length) return; setVisible(next); try { localStorage.setItem('admin-columns-'+resource, JSON.stringify(next)); } catch {} }
    const displayed = columns.filter(c => visible.includes(c.key) && !(isProducts && visible.includes('name') && c.key === 'catalog_category_id'));
    const showSelection = canDelete && records.data.some(r => r.canDelete);
    const deletable = records.data.filter(r => r.canDelete).map(r => r.id);
    function cell(row: RecordData, column: Column) {
        const value = row[column.key];
        if (resource === 'products' && column.key === 'is_active') return <Badge variant="outline" className={value ? 'admin-status-published' : 'admin-status-draft'}><span className="admin-status-dot" aria-hidden="true"/>{value ? 'Опубликован' : 'Черновик'}</Badge>;
        if (column.type === 'boolean') return <Badge variant="outline" className={value ? '' : 'text-muted-foreground'}>{value ? <><Check size={12}/>Да</> : 'Нет'}</Badge>;
        if (column.type === 'image') return row[column.key+'_url'] ? <img className="admin-table-image" src={String(row[column.key+'_url'])} alt=""/> : <span className="text-muted-foreground">—</span>;
        if (isProducts && column.key === 'availability') {
            const hasStock = [row.stock_free, row.stock_transit].some(stock => stock !== null && stock !== undefined && stock !== '');
            const status = Number(row.stock_free) > 0 ? 'В наличии' : Number(row.stock_transit) > 0 ? 'В пути' : value ? (column.options?.[String(value)] ?? String(value)) : hasStock ? 'Нет в наличии' : '—';
            return <span className="whitespace-nowrap">{status}</span>;
        }
        if (value === null || value === undefined || value === '') return <span className="text-muted-foreground">—</span>;
        if (column.type === 'datetime-local') return date(value);
        const text = column.options?.[String(value)] ?? String(value);
        if (column.copyable) return <CopyValue value={text}/>;
        if (isProducts && column.key === 'brand') return <span className="text-muted-foreground">{text}</span>;
        if (['status', 'brand', 'availability'].includes(column.key)) return <Badge variant="outline">{text}</Badge>;
        if (['price', 'price_from'].includes(column.key)) return Number(value).toLocaleString('ru-RU', { minimumFractionDigits: 2 })+' ₽';
        if (isProducts && column.key === 'name') {
            const category = columns.find(c => c.key === 'catalog_category_id')?.options?.[String(row.catalog_category_id)];
            return <div className="admin-product-cell"><Link className="admin-product-cover" href={`/admin/products/${row.id}${row.canEdit ? '/edit' : ''}`} aria-label={`Открыть ${text}`}><ProductCover src={row.cover ? String(row.cover) : null} brand={row.brand ? String(row.brand) : null} category={category || (row.category ? String(row.category) : null)}/></Link><div className="min-w-0"><Link className="admin-record-link" href={`/admin/products/${row.id}${row.canEdit ? '/edit' : ''}`} title={text}>{text}</Link><div className="admin-product-category">{category || 'Без категории'} · № {row.id}</div><div className="admin-content-status">{(row.contentMissing as string[] || []).length ? `Добавить: ${(row.contentMissing as string[]).join(' · ')}` : ''}</div></div></div>;
        }
        if (['name', 'title', 'reference'].includes(column.key)) return <Link className="admin-record-link" href={`/admin/${resource}/${row.id}${row.canEdit ? '/edit' : ''}`} title={text}>{text}</Link>;
        return text;
    }
    return <AdminLayout title={title} actions={<>{resource === 'products' && canCreate && <Button variant="outline" asChild><Link href="/admin/catalog-prices"><FileUp/>Обновить прайс</Link></Button>}{canCreate && <Button asChild><Link href={`/admin/${resource}/create`}><Plus/>{createLabels[resource] ?? 'Создать'}</Link></Button>}</>}>
        <section className={isProducts ? 'admin-surface admin-products' : 'admin-surface'} aria-label={title}>
        {isProducts ? <Tabs className="admin-product-tabs" value={String(query.is_active ?? '') || 'all'} onValueChange={value => visit({ is_active: value === 'all' ? '' : value })}><TabsList aria-label="Публикация товаров">{[['', 'Все товары'], ['1', 'Опубликованы'], ['0', 'Черновики']].map(([value, label]) => <TabsTrigger key={value} value={value || 'all'}>{label}</TabsTrigger>)}</TabsList></Tabs> : null}
        <div className="mb-4"><CatalogSearchPanel search={search} onSearch={setSearch} onSubmit={()=>visit({search})} total={records.total} loading={loading} placeholder={isProducts ? 'Название, артикул или бренд…' : 'Поиск записей…'} searchLabel={isProducts ? 'Поиск товаров' : 'Поиск записей'} chips={activeFilters.map(f=>({key:f.key,label:f.label+': '+(f.options[String(query[f.key])] ?? query[f.key]),remove:()=>visit({[f.key]:''})}))} onReset={resetFilters} actions={<DropdownMenu><DropdownMenuTrigger asChild><Button variant="outline"><Columns2/>Колонки</Button></DropdownMenuTrigger><DropdownMenuPortal><DropdownMenuContent className="admin-dropdown" align="end">{columns.map(c => <DropdownMenuCheckboxItem className="admin-dropdown-item" key={c.key} checked={visible.includes(c.key)} onCheckedChange={() => toggleColumn(c.key)} onSelect={e => e.preventDefault()}><span className="w-4">{visible.includes(c.key) && <Check size={14}/>}</span>{c.label}</DropdownMenuCheckboxItem>)}</DropdownMenuContent></DropdownMenuPortal></DropdownMenu>}>
            {filters.filter(f=>!isProducts || f.key!=='is_active').map(f=><CatalogFilter key={f.key} label={isProducts && f.key==='category' ? 'Категория поставщика' : f.label} placeholder={f.label} value={String(query[f.key] ?? '')} options={f.options} searchable={Object.keys(f.options).length>10} onChange={value=>visit({[f.key]:value})}/>)}
            {isProducts && presenceInfo && <p className="text-xs leading-5 text-muted-foreground">Актуальный прайс: {presenceInfo.file ? presenceInfo.source+' · '+presenceInfo.file : 'нет применённого файла для этого источника'}. Присутствие в файле не означает наличие на складе. Товар может быть связан с обоими источниками.</p>}
        </CatalogSearchPanel></div>
        {selected.length > 0 && <div className="admin-selection"><span>Выбрано: {selected.length}</span><Button variant="destructive" onClick={() => setDeleting(selected)}><Trash2/>Удалить выбранные</Button><Button variant="ghost" onClick={() => setSelected([])}>Снять выделение</Button></div>}
        <div className="admin-table-wrap" aria-busy={loading} tabIndex={isProducts ? 0 : undefined} aria-label={isProducts ? 'Список товаров' : undefined}><Table className="admin-table"><TableHeader><TableRow>{showSelection && <TableHead className="w-12"><CheckBox label="Выбрать все записи на странице" checked={deletable.length > 0 && deletable.every(id => selected.includes(id))} onChange={checked => setSelected(checked ? deletable : [])}/></TableHead>}{displayed.map(c => <TableHead key={c.key} data-column={c.key} aria-sort={query.sort === c.key ? query.direction === 'asc' ? 'ascending' : 'descending' : undefined}>{c.sortable ? <button onClick={() => visit({ sort: c.key, direction: query.sort === c.key && query.direction === 'asc' ? 'desc' : 'asc' })}>{columnLabel(c)}<ArrowUpDown size={13} className={query.sort === c.key ? 'admin-sort-active' : 'opacity-40'} aria-hidden="true"/>{query.sort === c.key && <span className="sr-only">{query.direction === 'asc' ? 'По возрастанию' : 'По убыванию'}</span>}</button> : columnLabel(c)}</TableHead>)}<TableHead className="w-12"><span className="sr-only">Действия</span></TableHead></TableRow></TableHeader><TableBody>
        {records.data.map(row => <TableRow key={row.id} data-selected={selected.includes(row.id)}>{showSelection && <TableCell><CheckBox label={`Выбрать запись ${row.id}`} disabled={!row.canDelete} checked={selected.includes(row.id)} onChange={checked => setSelected(ids => checked ? [...ids, row.id] : ids.filter(id => id !== row.id))}/></TableCell>}{displayed.map(c => <TableCell key={c.key} data-column={c.key}>{cell(row, c)}</TableCell>)}<TableCell><DropdownMenu><DropdownMenuTrigger asChild><Button variant="ghost" size="icon" aria-label={`Действия для записи ${row.id}`}><Ellipsis/></Button></DropdownMenuTrigger><DropdownMenuPortal><DropdownMenuContent className="admin-dropdown" align="end">{isProducts && <DropdownMenuItem asChild><a className="admin-dropdown-item" href={`/admin/product-preview/${row.id}`} target="_blank" rel="noreferrer"><Eye size={14}/>Предпросмотр на сайте</a></DropdownMenuItem>}<DropdownMenuItem asChild><Link className="admin-dropdown-item" href={`/admin/${resource}/${row.id}`}><Eye size={14}/>Просмотреть</Link></DropdownMenuItem>{row.canEdit && <DropdownMenuItem asChild><Link className="admin-dropdown-item" href={`/admin/${resource}/${row.id}/edit`}><Pencil size={14}/>Редактировать</Link></DropdownMenuItem>}{row.canDelete && <DropdownMenuItem className="admin-dropdown-item text-destructive" onSelect={() => setDeleting([row.id])}><Trash2 size={14}/>Удалить</DropdownMenuItem>}</DropdownMenuContent></DropdownMenuPortal></DropdownMenu></TableCell></TableRow>)}
        {!records.data.length && <TableRow><TableCell colSpan={displayed.length + (showSelection ? 2 : 1)}><div className="admin-empty"><Search/><h2>{hasQuery ? 'Ничего не найдено' : emptyLabels[resource] ?? 'Записей пока нет'}</h2>{hasQuery && <p>Попробуйте другой запрос или сбросьте фильтры.</p>}{hasQuery ? <Button variant="outline" onClick={resetFilters}>Сбросить фильтры</Button> : canCreate && <Button asChild variant="outline"><Link href={`/admin/${resource}/create`}>{createLabels[resource] ?? 'Создать'}</Link></Button>}</div></TableCell></TableRow>}</TableBody></Table></div>
        <Pager data={records} selected={selected.length} onPageSize={size => visit({ per_page: size })}/>
        </section>
        <Confirm open={deleting !== null} onOpenChange={open => !open && setDeleting(null)} busy={busy} title={`Удалить ${deleting?.length ?? 0} записей?`} description="Записи будут удалены из базы сайта. Это действие нельзя отменить." onConfirm={() => { if (!deleting) return; setBusy(true); router.post(`/admin/${resource}/bulk-delete`, { ids: deleting }, { onSuccess: () => { setDeleting(null); setSelected([]); }, onFinish: () => setBusy(false) }); }}/>
    </AdminLayout>;
}
