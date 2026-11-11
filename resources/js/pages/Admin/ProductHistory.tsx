import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowRight, ChevronDown, History, Image, Package, Pencil, RefreshCw } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle, EmptyDescription } from '@/components/ui/empty';
import { historyDate, sourceLabel } from '@/components/admin/product-price-sources';

type Change = {before:unknown;after:unknown};
type Event = {id:number;kind:string;actor:string|null;source:string|null;created_at:string;changes:Record<string,Change>;context:{selected_match?:{product_id:number}|null};upload:{id:string;name:string;canOpen:boolean}|null;photoPreviews:Partial<Record<'before'|'after',string[]>>};
const fields:Record<string,string>={name:'Название',sku:'Артикул',brand:'Бренд',price:'Цена',currency:'Валюта',availability:'Наличие',stock_free:'Свободный остаток',stock_transit:'Ожидается',photos:'Фотографии',description:'Описание',specifications:'Характеристики',is_active:'Публикация',catalog_category_id:'Категория',category:'Исходная категория',source_category:'Категория поставщика',slug:'Адрес страницы'};
const titles:Record<string,string>={created:'Карточка создана',edited:'Изменены данные карточки',source_linked:'Товар связан с прайсом',price_updated:'Обновлены цена и наличие',missing:'Товар исчез из прайса',returned:'Товар вернулся в прайс',stock_cleared:'Остатки обнулены',photos_assigned:'Назначены фотографии'};
const filters=[['all','Все'],['price','Цена и наличие'],['content','Содержание'],['photos','Фото'],['publication','Публикация']];
function decode(value:unknown):unknown { if (typeof value==='string' && /^[\[{]/.test(value)) {try{return JSON.parse(value);}catch{}} return value; }
function display(field:string,value:unknown,categories:Record<string,string>):string {
    if(value===null || value===undefined || value==='') return 'Не указано';
    if(field==='is_active') return value===true || value===1 || value==='1' ? 'Опубликован' : 'Скрыт';
    if(field==='catalog_category_id') return categories[String(value)] || 'Категория недоступна';
    if(field==='price' && Number.isFinite(Number(value))) return Number(value).toLocaleString('ru-RU',{minimumFractionDigits:2,maximumFractionDigits:2});
    const parsed=decode(value);
    if(field==='photos') return Array.isArray(parsed) ? `${parsed.length} фото` : 'Галерея поставщика';
    if(field==='specifications' && Array.isArray(parsed)) return parsed.map(item=>`${item.name || 'Характеристика'}: ${item.value ?? '—'}`).join('\n') || 'Не указаны';
    if(typeof parsed==='object') return 'Значение изменено';
    return String(value);
}
function EventRow({event,categories}:{event:Event;categories:Record<string,string>}) {
    const [open,setOpen]=useState(false);
    const changes=Object.entries(event.changes).filter(([field])=>field in fields);
    const title=changes.length===1 && event.kind==='edited' ? (changes[0][0]==='is_active' ? (display('is_active',changes[0][1].after,categories)==='Опубликован' ? 'Товар опубликован' : 'Товар скрыт с витрины') : `Изменено: ${fields[changes[0][0]].toLowerCase()}`) : titles[event.kind] || 'Изменение карточки';
    const Icon=event.kind==='photos_assigned' || changes.some(([key])=>key==='photos') ? Image : event.source ? RefreshCw : event.kind==='created' ? Package : Pencil;
    const details=changes.length>0 || !!event.context.selected_match || !!event.photoPreviews.after?.length;
    const highlights=changes.filter(([key])=>['price','availability','is_active'].includes(key));
    return <Collapsible open={open} onOpenChange={setOpen} className="group relative py-5 pl-11 sm:pl-12">
        <span className="absolute left-0 top-5 flex size-8 items-center justify-center rounded-full border bg-background text-muted-foreground"><Icon className="size-4" aria-hidden="true"/></span>
        <div className="flex flex-wrap items-start justify-between gap-2"><div className="min-w-0 flex-1"><h3 className="text-sm font-medium">{title}</h3><p className="mt-1 text-xs text-muted-foreground"><time dateTime={event.created_at}>{historyDate(event.created_at)}</time> · {event.actor || 'Автоматически'}{event.source ? ' · '+sourceLabel(event.source) : ''}</p></div>{details && <CollapsibleTrigger asChild><Button type="button" variant="ghost" className="shrink-0" aria-label={`${open ? 'Скрыть' : 'Подробнее'}: ${title}`}>{open ? 'Скрыть' : 'Подробнее'}<ChevronDown className={`size-3.5 transition-transform ${open ? 'rotate-180' : ''}`}/></Button></CollapsibleTrigger>}</div>
        {!open && highlights.length>0 && <div className="mt-2 flex flex-wrap gap-x-5 gap-y-1">{highlights.map(([key,change])=><p key={key} className="flex items-center gap-2 text-sm"><span className="text-muted-foreground">{fields[key]}:</span><span className="text-muted-foreground">{display(key,change.before,categories)}</span><ArrowRight className="size-3 text-muted-foreground" aria-label="изменено на"/><span className="font-medium">{display(key,change.after,categories)}</span></p>)}</div>}
        {!open && !highlights.length && changes.length>0 && <p className="mt-2 text-sm text-muted-foreground">{changes.map(([key])=>fields[key]).join(' · ')}</p>}
        {event.upload && <p className="mt-2 truncate text-xs text-muted-foreground" title={event.upload.name}>{event.upload.canOpen ? <Link className="underline underline-offset-4 hover:text-foreground" href={`/admin/catalog-prices/${event.upload.id}`}>{event.upload.name}</Link> : event.upload.name}</p>}
        <CollapsibleContent><div className="mt-4 space-y-4 border-t pt-4">
            {changes.map(([field,change])=><section key={field} aria-label={fields[field]}><h4 className="mb-2 text-xs font-medium">{fields[field]}</h4><div className="grid gap-4 sm:grid-cols-2">{(['before','after'] as const).map(side=><div key={side} className="min-w-0"><p className="mb-1 text-xs text-muted-foreground">{side==='before' ? 'Было' : 'Стало'}</p>{field==='photos' && event.photoPreviews[side]?.length ? <div className="flex flex-wrap gap-2">{event.photoPreviews[side]!.map((url,index)=><img key={url} src={url} alt={`${side==='before' ? 'Прежнее' : 'Новое'} фото ${index+1}`} loading="lazy" className="size-20 rounded-md border bg-background object-contain"/>)}</div> : <p className="max-h-64 overflow-y-auto whitespace-pre-wrap break-words text-sm">{display(field,change[side],categories)}</p>}</div>)}</div></section>)}
            {event.kind==='photos_assigned' && !!event.photoPreviews.after?.length && <div className="flex flex-wrap gap-2">{event.photoPreviews.after.map((url,index)=><img key={url} src={url} alt={`Назначенное фото ${index+1}`} loading="lazy" className="size-20 rounded-md border bg-background object-contain"/>)}</div>}
            {event.context.selected_match && <p className="text-sm">Фото выбрано из <Link className="underline underline-offset-4" href={`/admin/products/${event.context.selected_match.product_id}/edit`}>карточки № {event.context.selected_match.product_id}</Link>.</p>}
        </div></CollapsibleContent>
    </Collapsible>;
}
export type ProductHistoryProps = {product:{id:number;name:string};events:{data:Event[];total:number;current_page:number;last_page:number;prev_page_url:string|null;next_page_url:string|null};filter:string;categories:Record<string,string>};
export function ProductHistoryContent({product,events,filter='all',categories,baseUrl=`/admin/products/${product.id}/history`,embedded=false}:ProductHistoryProps & {baseUrl?:string;embedded?:boolean}) {
    return (
        <div className="admin-surface">
            <div className="mb-2 overflow-x-auto"><Tabs value={filter} onValueChange={value=>router.get(baseUrl,{filter:value,...(embedded ? {tab:'history'} : {})},{preserveScroll:true})}><TabsList aria-label="Тип изменений">{filters.map(([key,label])=><TabsTrigger key={key} value={key}>{label}</TabsTrigger>)}</TabsList></Tabs></div>
            {!events.data.length ? <Empty className="mt-4 border-solid"><EmptyHeader><EmptyMedia><History/></EmptyMedia><EmptyTitle>{filter==='all' ? 'История изменений пока не накоплена' : 'Таких изменений пока нет'}</EmptyTitle><EmptyDescription>{filter==='all' ? 'Здесь появятся обновления из прайсов и правки сотрудников.' : 'Выберите другой тип изменений или вернитесь ко всей истории.'}</EmptyDescription></EmptyHeader>{filter!=='all' && <Button variant="outline" asChild><Link href={baseUrl+(embedded ? '?tab=history' : '')}>Все изменения</Link></Button>}</Empty> : <><p className="mt-4 text-xs text-muted-foreground">Событий: {events.total}</p><div className="relative divide-y before:absolute before:bottom-8 before:left-4 before:top-8 before:border-l">{events.data.map(event=><EventRow key={event.id} event={event} categories={categories}/>)}</div></>}
            {events.last_page>1 && <nav aria-label="Страницы истории" className="mt-4 flex items-center justify-between border-t pt-4"><Button variant="outline" disabled={!events.prev_page_url} asChild={!!events.prev_page_url}>{events.prev_page_url ? <Link href={events.prev_page_url}>Назад</Link> : 'Назад'}</Button><span className="text-xs text-muted-foreground">{events.current_page} из {events.last_page}</span><Button variant="outline" disabled={!events.next_page_url} asChild={!!events.next_page_url}>{events.next_page_url ? <Link href={events.next_page_url}>Далее</Link> : 'Далее'}</Button></nav>}
        </div>
    );
}
export default function ProductHistory(props:ProductHistoryProps) {
    return <AdminLayout title="История изменений" description={`${props.product.name} · № ${props.product.id}`} back={{href:`/admin/products/${props.product.id}/edit`,label:'К карточке'}}>
        
        <ProductHistoryContent {...props}/>
    </AdminLayout>;
}
