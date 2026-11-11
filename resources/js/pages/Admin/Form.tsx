import { formSections } from '@/components/admin/form-sections';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ProductHistoryContent, type ProductHistoryProps } from './ProductHistory';
import { ProductPriceSources, sourceLabel, type PriceSource } from '@/components/admin/product-price-sources';
import { useEffect, useRef, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { Eye, Pencil, Trash2, X } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { CheckBox, Choice, Confirm, date } from '@/components/admin/controls';
import RichEditor from '@/components/admin/rich-editor';
import ImageField from '@/components/admin/image-field';
import type { Field, RecordData } from '@/components/admin/types';

import { Specifications, ProductPhotos, type Specification, type Photo } from '@/components/admin/product-content-fields';
type Value = Specification[] | Photo[] | string | number | boolean | string[] | File | null;
type Props = { resource: string; title: string; fields: Field[]; record: RecordData | null; readOnly: boolean; productTab?: string; productHistory?: ProductHistoryProps | null };
export default function Form(props: Props) { return <RecordForm key={`${props.resource}-${props.record?.id ?? 'create'}-${props.record?.updated_at ?? ''}-${props.readOnly}`} {...props}/>; }
function RecordForm({ resource, title, fields, record, readOnly, productTab='card', productHistory }: Props) {
    const [activeTab,setActiveTab]=useState(productTab);
    useEffect(()=>setActiveTab(productTab),[productTab]);
    const initial = Object.fromEntries(fields.map(f => [f.key, record?.[f.key] ?? f.default ?? (f.type === 'boolean' ? false : ['tags','specifications'].includes(f.type) ? [] : f.type === 'gallery' ? null : '')])) as Record<string, Value>;
    const form = useForm<Record<string, Value>>(initial);
    const [backUrl, setBackUrl] = useState('/admin/'+resource);
    useEffect(() => { try { const saved = sessionStorage.getItem('admin-list-'+resource); if (saved && (saved === '/admin/'+resource || saved.startsWith('/admin/'+resource+'?'))) setBackUrl(saved); } catch {} }, [resource]);
    const [leaveUrl, setLeaveUrl] = useState<string | null>(null);
    const allowLeave = useRef(false);
    useEffect(() => router.on('before', event => { if (form.isDirty && !allowLeave.current && event.detail.visit.method === 'get') { event.preventDefault(); setLeaveUrl(event.detail.visit.url.href); } }), [form.isDirty]);
    const [deleting, setDeleting] = useState(false);
    const [busy, setBusy] = useState(false);
    useEffect(() => { if (!form.isDirty) return; const handler = (e: BeforeUnloadEvent) => {e.preventDefault();}; window.addEventListener('beforeunload',handler); return () => window.removeEventListener('beforeunload',handler); }, [form.isDirty]);
    const isProduct = resource === 'products';
    const Section = 'section';
    const productGroups = [
        { title: 'Товар', description: '', keys: ['name', 'sku', 'brand'] },
        { title: 'Фотографии', description: '', keys: ['photos'] },
        { title: 'Описание и характеристики', description: '', keys: ['description', 'specifications'] },
        { title: 'Цена и наличие', description: '', keys: ['price', 'currency', 'availability', 'stock_free', 'stock_transit'] },
        { title: 'Размещение в каталоге', description: '', keys: ['catalog_category_id', 'slug', 'is_active'] },
        { title: 'Данные поставщика', description: '', keys: ['category', 'source_category'] },
    ];
    const sections = isProduct ? productGroups.map(group => ({ ...group, fields: group.keys.flatMap(key => fields.filter(f => f.key === key)) })) : formSections(resource, fields);
    const resourceTitles: Record<string, [string, string]> = { services: ['Услуга', 'Новая услуга'], 'service-categories': ['Категория услуг', 'Новая категория услуг'], 'product-categories': ['Категория товаров', 'Новая категория товаров'], 'catalog-mappings': ['Правило каталога', 'Новое правило каталога'], 'contact-requests': ['Заявка', 'Новая заявка'], 'blog-posts': ['Статья', 'Новая статья'], pages: ['Страница', 'Новая страница'] };
    const pageTitle = isProduct ? (record ? 'Карточка товара' : 'Новый товар') : record ? (resourceTitles[resource]?.[0] ?? title)+(resource==='contact-requests' ? ' № '+record.id : '') : resourceTitles[resource]?.[1] ?? title;
    function control(field: Field) {
        const value = form.data[field.key];
        if (field.type === 'specifications') return <Specifications value={(value as Specification[]) || []} onChange={v=>form.setData(field.key,v)} readOnly={readOnly}/>;
        if (field.type === 'gallery') return <ProductPhotos value={value as Photo[] | null} originals={(record?.photos as string[]) || []} urls={(record?.photoUrls as string[]) || []} cover={record?.cover as string | undefined} onChange={v=>form.setData(field.key,v)} readOnly={readOnly}/>;
        if (readOnly || field.readOnly) {
            if (resource==='catalog-mappings' && field.key==='source') return <p className="admin-field-value">{sourceLabel(String(value))}</p>;
            if (field.type === 'richtext') return <div className="admin-rich-content" dangerouslySetInnerHTML={{__html:String(value || '')}}/>;
            if (field.type === 'boolean') return <Badge variant="outline">{value ? 'Да' : 'Нет'}</Badge>;
            if (field.type === 'tags') return <div className="flex flex-wrap gap-1">{(Array.isArray(value) ? value as string[] : []).map(tag => <Badge variant="secondary" key={tag}>{tag}</Badge>)}</div>;
            if (field.type === 'image') return record?.[field.key+'_url'] ? <img src={String(record[field.key+'_url'])} alt={field.label} className="max-h-60 rounded-lg"/> : '—';
            if (field.type === 'datetime-local') return <p className="admin-field-value">{date(value)}</p>;
            return <p className="admin-field-value whitespace-pre-wrap break-words">{field.options?.[String(value)] ?? String(value === '' || value === null ? '—' : value)}</p>;
        }
        const set = (value: Value) => form.setData(field.key, value);
        if (field.type === 'boolean') return <label className="admin-toggle-field"><CheckBox label={field.label} checked={!!value} onChange={set}/><span>{field.label}</span></label>;
        if (field.type === 'select') return <Choice id={field.key} label={field.label} value={String(value)} onChange={set} options={field.options ?? {}} searchable={field.searchable}/>;
        if (field.type === 'richtext') return <RichEditor value={String(value)} onChange={set} resource={resource} recordId={record?.id} label={field.label}/>;
        if (field.type === 'image') return <ImageField label={field.label} value={value as File|string|null} currentUrl={record?.[field.key+'_url'] as string|undefined} onChange={set} crop={field.crop}/>;
        if (field.type === 'tags') return <Tags id={field.key} value={Array.isArray(value) ? value as string[] : []} onChange={set}/>;
        if (field.type === 'textarea') return <Textarea id={field.key} rows={4} value={String(value ?? '')} onChange={e => set(e.target.value)} aria-invalid={!!form.errors[field.key]} aria-describedby={form.errors[field.key] ? field.key+'-error' : undefined}/>;
        return <Input id={field.key} type={field.type} step={field.key === 'sort_order' ? '1' : 'any'} value={String(value ?? '')} onChange={e => set(e.target.value)} aria-invalid={!!form.errors[field.key]} aria-describedby={form.errors[field.key] ? field.key+'-error' : undefined}/>;
    }
    return <AdminLayout workspaceScroll footer={!readOnly && (!isProduct || !record || activeTab!=='history') ? <div className="admin-record-actions"><p className="text-sm text-muted-foreground">{form.isDirty ? 'Есть несохранённые изменения' : record ? 'Обновлено '+date(record.updated_at) : null}</p><div className="flex items-center gap-2"><Button variant="outline" asChild><Link href={backUrl}>Отмена</Link></Button><Button type="submit" form="admin-record-form" disabled={form.processing || (!!record && !form.isDirty)}>{form.processing ? 'Сохраняем…' : 'Сохранить'}</Button></div></div> : undefined} navigation={isProduct && record ? (<Tabs value={activeTab} onValueChange={setActiveTab} className="admin-underline-tabs">
                    <TabsList aria-label="Разделы товара">
                        <TabsTrigger id="product-tab-card" aria-controls="product-panel-card" value="card">Карточка</TabsTrigger>
                        <TabsTrigger id="product-tab-source" aria-controls="product-panel-source" value="source">Источник</TabsTrigger>
                        <TabsTrigger id="product-tab-history" aria-controls="product-panel-history" value="history">История</TabsTrigger>
                    </TabsList>
                </Tabs>) : undefined} title={pageTitle} headingTitle={record && resource!=='contact-requests' ? String(record.name ?? record.title ?? pageTitle) : undefined} description={record ? (isProduct ? <div className="admin-product-identity"><span>Товар № {record.id}</span><span className={form.data.is_active ? 'admin-product-published' : 'admin-product-draft'}>{form.data.is_active ? 'Опубликован' : 'Черновик'}</span></div> : resource==='contact-requests' ? String(record.name ?? '') : undefined) : undefined} back={{ href: backUrl, label: 'К списку' }} actions={record ? <>{record && (!readOnly || record.canEdit) && <Button variant="outline" asChild><Link href={`/admin/${resource}/${record.id}${readOnly ? '/edit' : ''}`}><>{readOnly ? <Pencil/> : <Eye/>}{readOnly ? 'Редактировать' : 'Просмотр'}</></Link></Button>}{record?.canDelete && <Button variant="outline" size="icon" onClick={() => setDeleting(true)} aria-label="Удалить запись"><Trash2/></Button>}</> : undefined}>
        <form id="admin-record-form" className={isProduct ? 'admin-record-form admin-product-form' : 'admin-record-form'} onSubmit={e => { e.preventDefault(); const options = {preserveScroll:true, forceFormData:true, onError: () => {setActiveTab('card'); requestAnimationFrame(() => document.querySelector<HTMLElement>('.admin-error-summary')?.scrollIntoView({ block: 'center' }));}, onSuccess: () => form.setDefaults()}; form.transform(data => { const payload = {...data}; if (isProduct) { if (payload.photos === null) delete payload.photos; else if (Array.isArray(payload.photos) && !payload.photos.length) payload.photos = ''; if (Array.isArray(payload.specifications) && !payload.specifications.length) payload.specifications = ''; } return record ? {...payload, _method:'PUT'} : payload; }); if(record) { form.post(`/admin/${resource}/${record.id}`,options); } else form.post(`/admin/${resource}`,options); }}>
            {Object.keys(form.errors).length > 0 && <div role="alert" className="admin-error-summary"><strong>Проверьте заполнение формы</strong><ul>{Object.entries(form.errors).map(([key,error]) => <li key={key}><a href={'#'+key}>{error}</a></li>)}</ul></div>}
            
            {isProduct && record && <>

                <div hidden={activeTab!=='source'} id="product-panel-source" role="tabpanel" aria-labelledby="product-tab-source">
                    {(record.priceSources as PriceSource[])?.length ? <ProductPriceSources sources={record.priceSources as PriceSource[]}/> : <p className="mb-4 text-sm text-muted-foreground">Товар пока не связан с прайсом поставщика.</p>}
                    <section className="admin-form-section"><div className="admin-section-heading"><h2>Исходная классификация</h2></div><div className="admin-form-grid">{fields.filter(field=>['category','source_category'].includes(field.key)).map(field=><div key={field.key} className="admin-field"><Label htmlFor={field.key}>{field.label}</Label>{control(field)}{form.errors[field.key] && <p role="alert" className="admin-error">{form.errors[field.key]}</p>}</div>)}</div></section>
                </div>
                {activeTab==='history' && productHistory && <section id="product-panel-history" role="tabpanel" aria-labelledby="product-tab-history"><ProductHistoryContent {...productHistory} embedded baseUrl={`/admin/products/${record.id}${readOnly ? '' : '/edit'}`}/></section>}
            </>}
            {isProduct && (!record || activeTab==='source') && !!record?.supplierDescription && <details className="mb-4 text-sm"><summary className="cursor-pointer">Описание поставщика</summary><p className="mt-4 whitespace-pre-line text-muted-foreground">{String(record.supplierDescription)}</p>{!readOnly && <Button className="mt-4" type="button" variant="outline" onClick={()=>{form.setData('description',String(record.supplierDescription));setActiveTab('card');}}>Использовать как своё описание</Button>}</details>}
            {resource==='contact-requests' && !!record?.supplyRequest && <section className="admin-form-section"><h2>Запрос поставки</h2><p>Количество: {String((record.supplyRequest as {quantity:number}).quantity)} шт.</p><Link className="underline" href={`/admin/products/${(record.supplyRequest as {product_id:number}).product_id}/edit`}>Открыть карточку товара</Link></section>}
            <div id={isProduct && record ? "product-panel-card" : undefined} role={isProduct && record ? "tabpanel" : undefined} aria-labelledby={isProduct && record ? "product-tab-card" : undefined} hidden={isProduct && !!record && activeTab!=='card'}>{sections.filter(section=>!isProduct || !record || section.title!=='Данные поставщика').map(section => <Section key={section.title} className="admin-form-section"><div className="admin-section-heading"><h2>{section.title}</h2>{section.description ? <p>{section.description}</p> : null}</div><div className="admin-form-grid">{section.fields.map(field => <div key={field.key} className={`admin-field ${(['textarea','richtext','image','tags','gallery','specifications'].includes(field.type) || (isProduct && ['name', 'catalog_category_id', 'slug', 'source_category'].includes(field.key))) ? 'admin-field-wide' : ''}`}>{!(field.type==='boolean' && !readOnly && !field.readOnly) && <Label htmlFor={field.key} className={section.fields.length===1 && field.type==='richtext' ? 'sr-only' : undefined}>{field.label}{field.required && field.key!=='slug' && <span className="text-muted-foreground"> *</span>}</Label>}{control(field)}{field.hint && !readOnly && <p className="text-xs text-muted-foreground">{field.hint}</p>}{form.errors[field.key] && <p id={field.key+'-error'} role="alert" className="admin-error">{form.errors[field.key]}</p>}</div>)}</div></Section>)}
            </div>
            {readOnly && record && (!isProduct || activeTab==='card') && <p className="mt-4 text-xs text-muted-foreground">Создано {date(record.created_at)} · Обновлено {date(record.updated_at)}</p>}
        </form><Dialog open={leaveUrl !== null} onOpenChange={open => !open && setLeaveUrl(null)}><DialogContent><DialogHeader><DialogTitle>Есть несохранённые изменения</DialogTitle><DialogDescription>Если уйти сейчас, изменения в этой форме не сохранятся.</DialogDescription></DialogHeader><DialogFooter><Button variant="outline" onClick={() => setLeaveUrl(null)}>Продолжить редактирование</Button><Button onClick={() => { if (!leaveUrl) return; allowLeave.current = true; router.visit(leaveUrl, { onFinish: () => { allowLeave.current = false; setLeaveUrl(null); } }); }}>Уйти без сохранения</Button></DialogFooter></DialogContent></Dialog><Confirm open={deleting} onOpenChange={setDeleting} busy={busy} onConfirm={() => {setBusy(true);router.delete(`/admin/${resource}/${record?.id}`,{onFinish:()=>setBusy(false)});}}/>
    </AdminLayout>;
}
function Tags({ id, value, onChange }: {id: string; value: string[]; onChange: (value:string[])=>void}) {
    const [draft,setDraft] = useState('');
    function add() {const next=draft.trim();if(next && !value.includes(next)) onChange([...value,next]);setDraft('');}
    return <div className="admin-tags">{value.map(tag=><Badge key={tag} variant="secondary">{tag}<button type="button" aria-label={`Удалить ${tag}`} onClick={()=>onChange(value.filter(t=>t!==tag))}><X size={12}/></button></Badge>)}<Input id={id} placeholder="Добавить и нажать Enter" value={draft} onChange={e=>setDraft(e.target.value)} onBlur={add} onKeyDown={e=>{if(e.key==='Enter'){e.preventDefault();add();}}}/><Button type="button" variant="outline" onClick={add}>Добавить</Button></div>;
}
