import { ProductImagePlaceholder } from '@/components/admin/product-image-placeholder';
import { PriceDatePicker } from '@/components/admin/price-date-picker';
import { Checkbox } from '@/components/ui/checkbox';
import { PhotoCatalogFilter } from '@/components/admin/photo-catalog-filter';
import { Card } from '@/components/ui/card';
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription } from '@/components/ui/empty';

import { Badge } from '@/components/ui/badge';
import { CatalogRelease, type ReleasePreview } from '@/components/admin/catalog-release';
import { Fragment, useEffect, useRef, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { ArrowUp, ArrowDown, TrendingUp, PackageX, FileMinus2, TriangleAlert, CheckCircle2, Download, FileSpreadsheet, Loader2, RefreshCw, Upload } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import { CatalogToolbar } from '@/components/admin/catalog-search-panel';
import { Choice } from '@/components/admin/controls';
import { PriceFileUpload } from '@/components/admin/price-file-upload';
import { CatalogPriceStepper, type PriceSection } from '@/components/admin/catalog-price-stepper';

import { PhotoQueueContent, type PhotoQueueProps } from './PhotoQueue';

type PriceUpload = { price_date?: string; safety?: {blocked: string[]; warnings: string[]; token: string} | null; id: string; name: string; status: string; source_profile: string; summary: Record<string, number> | null; error: string | null; created_at: string };
type Detail = { brand?:string|null; assortmentDecision?:string|null; decisionCategory?:number|null; membership?:'appeared'|'retained'|'missing'; cover?:string|null; productUrl?:string|null; sourceCategory?: string | null; rule?: string | null; row: number; sku: string; name: string; operation: string; price: string | null; stock: (string | null)[]; changes: Record<string, {before: string | null; after: string | null}>; issues: string[] };
const anomalyIcons: Record<string, typeof TrendingUp> = {price: TrendingUp, stock: PackageX, missing: FileMinus2, data: TriangleAlert};
const states: Record<string, string> = { queued: 'Ожидает проверки', preparing: 'Проверяем файл', ready: 'Готов к обновлению', applying: 'Обновляем каталог', completed: 'Обновление завершено', stale: 'Нужна новая проверка', failed: 'Не удалось обработать' };
const operations: Record<string, string> = { updated: 'Обновление', created_drafts: 'Новый черновик', existing_untouched: 'Без изменений', held: 'Отложены', excluded: 'Не входят в каталог', request_only: 'Пропущены: под запрос' };
const explanations: Record<string,string> = {
    updated:'Изменятся цена или остатки существующих товаров. Проверьте значения «было → стало».',
    created_drafts:'Появятся новые товары без публикации. Проверьте, что они нужны в каталоге.',
    existing_untouched:'Цена и остатки совпадают. Ничего делать не нужно.',
    held:'Автоматическое добавление или обновление остановлено для этих строк. Остальному прайсу они не мешают.',
    excluded:'Не импортируются по правилам ассортимента. Существующие товары не удаляются.',
    request_only:'Не добавляются и не обновляются этим импортом. Существующие товары сохраняются.',
};
const classificationReasons: Record<string,string> = {
    'audio-review':'Аудиотовар отложен правилом отбора ассортимента. Его включение в B2B-каталог ещё не согласовано.',
    'unknown-type':'Автоматически не определён тип товара. Подкатегория для импорта не подтверждена.',
    'unknown-category':'Категория поставщика не распознана. Подкатегория для импорта не подтверждена.',
};
const reasons: Record<string, string> = { saved_decision_identity_changed: 'Данные товара изменились после сохранения решения. Проверьте соответствие и примите решение повторно', supplier_identity_changed: 'Под прежним ID изменились бренд, артикул или EAN. Обновление задержано: проверьте идентичность товара у поставщика', ambiguous_source_identity: 'Несколько строк с одной идентичностью', duplicate_row: 'Повтор строки', ambiguous_database_identity: 'Неоднозначное совпадение в каталоге', verify_database_identity: 'Нужно проверить совпадение товара', title_changed_verify_identity: 'Название отличается', invalid_identity_or_price: 'Проверьте бренд, артикул, цену и валюту', missing_or_inactive_category: 'Не назначена активная подкатегория', invalid_stock: 'Не распознаны остатки', missing_name: 'Нет названия', missing_brand: 'Нет бренда', missing_pn: 'Нет артикула', invalid_price: 'Не распознана цена', currency_needs_review: 'Проверьте валюту', stock_text_4: 'Проверьте «Есть»', stock_text_5: 'Проверьте «Свободно»', stock_text_6: 'Проверьте «Транзит»' };
const value = (v: string | null | undefined) => v == null || v === '' ? '—' : v;

const fieldLabels: Record<string,string> = {price:'Цена',currency:'Валюта',availability:'Наличие',stock_free:'Свободно',stock_transit:'Ожидается'};
const formatValue = (field:string, v:string|null) => field==='price' && v && Number.isFinite(Number(v)) ? Number(v).toLocaleString('ru-RU')+' ₽' : value(v);
function difference(field:string,before:string|null,after:string|null) {
    if (before===after || (!before && !after)) return {label:'Без изменений',kind:'same'};
    const numeric=(v:string|null)=>v!==null && /^-?\d+(?:\.\d+)?$/.test(v);
    if (field!=='currency' && numeric(before) && numeric(after)) {
        const delta=Math.round((Number(after)-Number(before))*100)/100;
        if (!delta) return {label:'Без изменений',kind:'same'};
        return {label:`${delta>0 ? '+' : '−'}${Math.abs(delta).toLocaleString('ru-RU')} ${field==='price' ? '₽' : 'шт.'}`,kind:delta>0 ? 'up' : 'down'};
    }
    return {label:'Изменилось',kind:'changed'};
}
function reasonText(row: {operation:string;rule?:string|null;issues:string[]}) {
    if (row.operation==='absent') return 'Был в предыдущем прайсе, в новом отсутствует. Товар сохраняется. При применении прайса устаревшее наличие этого источника будет снято.';
    if (row.operation!=='held') return explanations[row.operation];
    if (row.issues.length) return row.issues.map(issue=>reasons[issue] || `Проверка данных: ${issue}`).join('. ');
    return classificationReasons[row.rule || ''] || explanations[row.operation];
}
type PriceCategory = {id:number;name:string;parentId:number|null};
function AssortmentDecision({row,uploadId,categories}:{row:Detail;uploadId:string;categories:PriceCategory[]}) {
    const [editing,setEditing]=useState(false);
    const form=useForm({row:row.row,decision:'include',category_id:row.decisionCategory ? String(row.decisionCategory) : ''});
    const save=(decision:string)=>{form.transform(data=>({...data,decision}));form.post(`/admin/catalog-prices/${uploadId}/decision`,{preserveScroll:true});};
    return <div className="border-t py-4">
        {row.assortmentDecision && <p className="mb-2 text-sm">Ваше решение: {row.assortmentDecision==='include' ? 'включить в импорт' : 'не включать'}</p>}
        <div className="flex flex-wrap items-center gap-2">
            <Button variant="outline" disabled={form.processing} onClick={()=>setEditing(!editing)}>Включить в импорт</Button>
            <Button variant="ghost" disabled={form.processing || row.assortmentDecision==='exclude'} onClick={()=>save('exclude')}>Не включать</Button>
            {row.assortmentDecision && <Button variant="ghost" disabled={form.processing} onClick={()=>save('reset')}>Отменить решение</Button>}
        </div>
        {editing && <div className="mt-4 space-y-4"><div className="flex flex-wrap items-center gap-2"><div className="w-full sm:w-80"><Choice label="Подкатегория для нового товара" placeholder="Выберите подкатегорию" value={form.data.category_id} options={Object.fromEntries(categories.filter(c=>c.parentId).map(c=>[String(c.id),`${categories.find(p=>p.id===c.parentId)?.name} / ${c.name}`]))} onChange={value=>form.setData('category_id',value)}/></div><Button disabled={!form.data.category_id || form.processing} onClick={()=>save('include')}>Сохранить решение</Button></div><p className="text-xs text-muted-foreground">Для этого товара и следующих прайсов. Новый товар будет черновиком; категория существующего сохранится.</p></div>}
        {Object.values(form.errors).map((error,i)=><p key={i} role="alert" className="mt-2 text-sm text-destructive">{error}</p>)}
    </div>;
}
function ProductChange({row,completed,uploadId,categories,editable}: {row:Detail;completed:boolean;uploadId:string;categories:PriceCategory[];editable:boolean}) {
    const [expanded,setExpanded]=useState(false);
    const [failedCover,setFailedCover]=useState<string|null>(null);
    const existing=['updated','existing_untouched'].includes(row.operation);
    const draft=row.operation==='created_drafts';
    const canDecide=editable && (['held','excluded','request_only'].includes(row.operation) || !!row.assortmentDecision);
    const hasDetails=canDecide || !existing || !!row.changes.currency;
    const label=row.operation==='existing_untouched' ? 'Без изменений' : existing ? 'Обновление' : row.operation==='absent' ? 'Нет в прайсе' : draft ? 'Черновик' : 'Пропущен';
    const metric=(field:string,incoming:string|null)=>{
        const change=row.changes[field];
        const changed=existing && change && change.before!==incoming;
        const diff=changed ? difference(field,change.before,incoming) : null;
        const directional=diff && ['up','down'].includes(diff.kind);
        const Arrow=diff?.kind==='up' ? ArrowUp : ArrowDown;
        return <div className="flex items-center justify-end gap-4 whitespace-nowrap text-sm tabular-nums" title={changed ? 'Было: '+formatValue(field,change.before) : undefined}>
            <span className={changed ? 'font-medium' : ''}><span className="sr-only">{completed ? 'Стало: ' : 'Будет: '}</span>{formatValue(field,incoming)}</span>
            {changed && diff?.kind!=='same' && <span className={'inline-flex items-center gap-1 '+(diff?.kind==='up' ? 'text-emerald-700 dark:text-emerald-400' : diff?.kind==='down' ? 'text-red-700 dark:text-red-400' : 'text-muted-foreground')} aria-label={directional ? (diff.kind==='up' ? 'Увеличение на ' : 'Уменьшение на ')+diff.label.slice(1) : 'Значение изменилось'}>{directional && <Arrow className="size-3.5" aria-hidden="true"/>}{directional ? diff.label.replace(' шт.','') : 'Изменено'}</span>}
        </div>;
    };
    return <Fragment><TableRow>
        <TableCell className="min-w-64 max-w-lg whitespace-normal py-4 pl-4"><div className="flex items-center gap-4"><div className="admin-product-cover" aria-label={row.cover && failedCover!==row.cover ? undefined : 'Фото отсутствует'}>{row.cover && failedCover!==row.cover ? <img src={row.cover} alt="" loading="lazy" onError={()=>setFailedCover(row.cover!)}/> : <ProductImagePlaceholder/>}</div><div className="min-w-0"><div className="line-clamp-2 font-medium" title={row.name}>{row.productUrl ? <a href={row.productUrl} target="_blank" rel="noreferrer" className="hover:underline underline-offset-4">{row.name}</a> : row.name}</div><div className="mt-1 text-sm text-muted-foreground">{row.sku || 'Артикул не указан'}</div></div></div></TableCell>
        <TableCell className="text-right">{metric('price',row.price)}</TableCell><TableCell className="text-right">{metric('availability',row.stock[0])}</TableCell><TableCell className="text-right">{metric('stock_free',row.stock[1])}</TableCell><TableCell className="text-right">{metric('stock_transit',row.stock[2])}</TableCell>
        <TableCell><Badge variant={row.operation==='existing_untouched' ? 'outline' : 'secondary'} className={row.operation==='existing_untouched' ? 'text-muted-foreground' : undefined}>{label}</Badge></TableCell>
        <TableCell className="pr-4">{hasDetails && <Button variant="ghost" aria-expanded={expanded} aria-label={'Подробности: '+row.name} onClick={()=>setExpanded(value=>!value)}>{expanded ? 'Скрыть' : 'Подробнее'}</Button>}</TableCell>
    </TableRow>{expanded && <TableRow className="bg-muted/30"><TableCell colSpan={7} className="whitespace-normal p-0"><div className="py-4 text-sm text-muted-foreground">{reasonText(row)}{row.changes.currency && <p className="mt-2">Валюта: {value(row.changes.currency.before)} → {value(row.changes.currency.after)}</p>}</div>{canDecide && <AssortmentDecision row={row} uploadId={uploadId} categories={categories}/>}</TableCell></TableRow>}</Fragment>;
}

export default function CatalogPrice({ upload, details, history, section='upload', comparison, photoQueue, release, sourceHealth=[], priceToday='' }: { priceToday?:string; sourceHealth?:{source:string;label:string;at:string|null;priceDate:string|null;stale:boolean}[]; upload: PriceUpload | null; details: Detail[]; history: PriceUpload[]; section?:PriceSection; comparison?:{quickCounts?:Record<string,number>;anomaly?:string;anomalies?:{key:string;label:string;count:number}[];total:number;page:number;lastPage:number;search:string;operation:string;membership?:string;baseline?:{id:string;name:string}|null;appearedCount?:number;missingCount?:number;category?:string;subcategory?:string;categories?:{id:number;name:string;parentId:number|null}[];reason?:string;groups?:{key:string;count:number;operation:string;rule?:string|null;issues:string[]}[]}; photoQueue?:PhotoQueueProps|null;release?:ReleasePreview|null }) {
    const [footerActionHost,setFooterActionHost]=useState<HTMLDivElement|null>(null);
    const [refreshingPhotos,setRefreshingPhotos]=useState(false);
    const [search,setSearch]=useState(comparison?.search || '');
    const submittedSearch=useRef(comparison?.search || '');
    const [operation,setOperation]=useState(comparison?.operation || '');
    useEffect(()=>{submittedSearch.current=comparison?.search || '';setSearch(comparison?.search || '');setOperation(comparison?.operation || '');},[comparison?.search,comparison?.operation]);
    const compare = (page=1)=>router.get(`/admin/catalog-prices/${upload?.id}`,{section:'compare',anomaly:comparison?.anomaly,compare_search:search,operation,membership:comparison?.membership,category:comparison?.category,subcategory:comparison?.subcategory,compare_page:page},{preserveState:true});
    const categories=comparison?.categories || [];
    const updateFilters=(changes:Record<string,string>)=>{submittedSearch.current=changes.compare_search ?? search;router.get('/admin/catalog-prices/'+upload?.id,{section:'compare',compare_search:search,operation,membership:comparison?.membership || '',anomaly:comparison?.anomaly || '',category:comparison?.category || '',subcategory:comparison?.subcategory || '',...changes},{preserveState:true,preserveScroll:true,replace:true});};
    const listKey = `${upload?.id}:${section}:${comparison?.operation}:${comparison?.search}:${comparison?.reason || ''}:${comparison?.category}:${comparison?.subcategory}:${comparison?.membership}:${comparison?.anomaly}`;
    const [loaded,setLoaded]=useState<{key:string;rows:Detail[]}>({key:listKey,rows:details});
    const [loadingMore,setLoadingMore]=useState(false);
    const [loadError,setLoadError]=useState(false);
    const pending=useRef(false);
    const sentinel=useRef<HTMLDivElement>(null);
    useEffect(()=>{
        setLoaded(previous=>({key:listKey,rows:previous.key!==listKey || (comparison?.page || 1)===1 ? details : [...previous.rows,...details.filter(row=>!previous.rows.some(old=>old.row===row.row))]}));
        setLoadError(false);
    },[details,listKey,comparison?.page]);
    useEffect(()=>{
        const viewport=sentinel.current?.closest('[data-slot="scroll-area-viewport"]');
        if (viewport) viewport.scrollTop=0;
    },[listKey]);
    const visibleRows=loaded.key===listKey ? loaded.rows : details;
    const hasMore=!!comparison && comparison.page<comparison.lastPage;
    const loadMore=()=>{
        if (!hasMore || pending.current || !upload) return;
        pending.current=true;setLoadingMore(true);setLoadError(false);
        router.get(`/admin/catalog-prices/${upload.id}`,{section:'compare',anomaly:comparison.anomaly,operation:comparison.operation,membership:comparison.membership,compare_search:comparison.search,category:comparison.category,subcategory:comparison.subcategory,compare_page:comparison.page+1},
            {only:['details','comparison'],preserveState:true,preserveScroll:true,replace:true,onError:()=>setLoadError(true),onFinish:()=>{pending.current=false;setLoadingMore(false);}});
    };
    useEffect(()=>{
        const target=sentinel.current;
        if (!target || !hasMore || loadingMore || loadError) return;
        const observer=new IntersectionObserver(entries=>{if(entries.some(entry=>entry.isIntersecting)) loadMore();},{root:target.closest('[data-slot="scroll-area-viewport"]'),rootMargin:'300px'});
        observer.observe(target);
        return ()=>observer.disconnect();
    },[hasMore,loadingMore,loadError,listKey,comparison?.page]);
    useEffect(()=>{
        if (!upload || section!=='compare' || search===(comparison?.search || '') || search===submittedSearch.current) return;
        const timer=setTimeout(()=>router.get(`/admin/catalog-prices/${upload.id}`,{section:'compare',anomaly:comparison?.anomaly,operation,compare_search:search,membership:comparison?.membership,category:comparison?.category,subcategory:comparison?.subcategory},{preserveState:true,replace:true}),400);
        return ()=>clearTimeout(timer);
    },[search,upload?.id,section,operation,comparison?.search,comparison?.membership,comparison?.category,comparison?.subcategory,comparison?.anomaly]);
    const form = useForm<{file: File | null; price_date: string}>({file: null, price_date: ''});
    const action = useForm({safety_ack: ''});
    useEffect(()=>action.setData('safety_ack',''),[upload?.safety?.token]);
    const busy = !!upload && ['queued', 'preparing', 'applying'].includes(upload.status);
    useEffect(() => {
        if (!busy) return;
        const timer = setInterval(() => router.reload({only: ['upload', 'details', 'comparison', 'photoQueue','release']}), 4000);
        return () => clearInterval(timer);
    }, [busy, upload?.id]);
    const summary = upload?.summary;
    const footer=upload ? (
            <nav className="price-workflow-actions" aria-label="Переход между этапами">
                {section!=='upload' ? <Button variant="outline" asChild><Link href={`/admin/catalog-prices/${upload.id}?section=${({compare:'upload',apply:'compare',photos:'apply',result:'photos'} as Record<string,string>)[section]}`}>Назад</Link></Button> : <span/>}
                <div className="flex items-center gap-2">
                {section==='result' && <div ref={setFooterActionHost}/>}
                {section==='photos' && photoQueue && <Button variant="outline" disabled={refreshingPhotos} onClick={()=>{setRefreshingPhotos(true);router.get(`/admin/catalog-prices/${upload.id}`,{section:'photos',...photoQueue.filters},{preserveState:true,preserveScroll:true,replace:true,onFinish:()=>setRefreshingPhotos(false)});}}>{refreshingPhotos ? 'Обновляем список…' : 'Обновить список фото'}</Button>}
                {section==='upload'  && <Button asChild><Link href={`/admin/catalog-prices/${upload.id}?section=compare`}>К сравнению</Link></Button>}
                {section==='compare' && (summary && !busy && ['ready','completed'].includes(upload.status) ? <Button asChild><Link href={`/admin/catalog-prices/${upload.id}?section=apply`}>К применению</Link></Button> : <Button disabled>К применению</Button>)}
                {section==='apply' && (upload.status==='completed' ? <Button asChild><Link href={`/admin/catalog-prices/${upload.id}?section=photos`}>К фотографиям</Link></Button> : <Button disabled={upload.status!=='ready' || action.processing || !!upload.safety?.blocked.length || (!!upload.safety?.warnings.length && action.data.safety_ack!==upload.safety.token)} onClick={()=>action.post(`/admin/catalog-prices/${upload.id}/run`)}>{busy ? 'Обрабатываем…' : action.processing ? 'Запускаем…' : 'Применить прайс'}</Button>)}
                {section==='photos' && (upload.status==='completed' ? <Button asChild><Link href={`/admin/catalog-prices/${upload.id}?section=result`}>К результату</Link></Button> : <Button variant="outline" asChild><Link href={`/admin/catalog-prices/${upload.id}?section=apply`}>Сначала применить прайс</Link></Button>)}
                </div>
            </nav>
    ) : <div className="flex justify-end">
        <Button type="submit" form="price-upload-form" disabled={form.processing || !form.data.file || !form.data.price_date}>{form.processing ? <Loader2 className="animate-spin"/> : <Upload/>}{form.processing ? `Загружаем${form.progress ? ` ${form.progress.percentage}%` : '…'}` : 'Проверить прайс'}</Button>
    </div>;
    return <AdminLayout workspaceScroll footer={footer} navigation={upload ? <CatalogPriceStepper id={upload.id} section={section} status={upload.status} hasSummary={!!summary} uploading={false} uploadError={false}/> : <CatalogPriceStepper hasSummary={false} uploading={form.processing} uploadError={Object.keys(form.errors).length > 0}/>} description={!upload ? 'Обновите цены и остатки из Excel. Новые товары сохранятся черновиками.' : undefined} title={upload ? upload.name : 'Обновить прайс'} back={{ href: upload ? '/admin/catalog-prices' : '/admin/products', label: upload ? 'К загрузкам прайса' : 'К товарам' }}>
        <div className="admin-price-page">
        {!upload ? <>

            
            <section className="admin-price-upload">
                <div className="admin-section-heading"><h2>Файл и дата прайса</h2><p>Один лист с ID, EAN и остатками. Поддерживается и прежний формат из 9 колонок.</p></div>
                <form id="price-upload-form" className="space-y-4" onSubmit={e => {e.preventDefault(); form.post('/admin/catalog-prices');}}>
                    <PriceDatePicker value={form.data.price_date} onChange={date=>form.setData('price_date',date)} today={priceToday} disabled={form.processing} error={form.errors.price_date}/><PriceFileUpload file={form.data.file} onChange={file => form.setData('file', file)} processing={form.processing} progress={form.progress?.percentage} error={form.errors.file} onError={error => error ? form.setError('file', error) : form.clearErrors('file')} onCancel={() => form.cancel()}/>
                    {Object.entries(form.errors).filter(([key]) => key !== 'file' && key !== 'price_date').map(([key, error]) => <p key={key} role="alert" className="text-sm text-destructive">{error}</p>)}
                </form>
            </section>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">{sourceHealth.map(source=><Card key={source.source} className="admin-source-health gap-2"><p className="text-sm font-medium">{source.label}</p><Badge variant={source.stale ? 'destructive' : 'secondary'}>{source.stale ? 'Проверьте актуальность' : 'Недавно обновлён'}</Badge><p className="text-xs text-muted-foreground">Обновлён: {source.at ? new Date(source.at).toLocaleString('ru-RU') : 'ещё не было'} · Дата прайса: {source.priceDate || 'не указана'}.</p></Card>)}</div>
            {history.length > 0 && <section className="admin-surface mt-4" aria-labelledby="price-history-heading"><div className="admin-section-heading"><h2 id="price-history-heading">Последние загрузки</h2></div>
                <div className="admin-table-wrap"><Table aria-label="История загрузок прайса"><TableHeader><TableRow><TableHead>Файл</TableHead><TableHead>Дата</TableHead><TableHead className="text-right">Статус</TableHead></TableRow></TableHeader>
                    <TableBody>{history.map(item => <TableRow key={item.id}>
                        <TableCell className="font-medium"><Link className="underline-offset-4 hover:underline" href={`/admin/catalog-prices/${item.id}`}>{item.name}</Link></TableCell>
                        <TableCell><time dateTime={item.created_at}>{new Date(item.created_at).toLocaleString('ru-RU')}</time></TableCell>
                        <TableCell className="text-right"><Badge variant="outline" className={['ready','completed'].includes(item.status) ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : item.status==='failed' ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300' : item.status==='stale' ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300' : ['preparing','applying'].includes(item.status) ? 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-300' : 'text-muted-foreground'}>{states[item.status] || item.status}</Badge></TableCell>
                    </TableRow>)}</TableBody>
                </Table></div>
            </section>}
        </> : <>
            
            {section==='result' && <section className="price-result-panel">{!release && <h2>Прайс применён</h2>}{release && <CatalogRelease title="Прайс применён" preview={release} uploadId={upload.id} actionHost={footerActionHost}/>}</section>}
            {section==='apply' && <section className={upload.status === 'ready' ? 'price-apply-layout space-y-4' : 'price-stage-panel rounded-xl border bg-card p-4'} aria-label="Подтверждение обновления каталога" data-price-apply="true">
                <h2 className={upload.status==='ready' ? 'sr-only' : undefined}>{upload.status==='completed' ? 'Обновление завершено' : busy ? 'Обрабатываем прайс' : upload.status==='stale' ? 'Нужно обновить сравнение' : upload.status==='failed' ? 'Проверка не завершена' : 'Применить этот прайс?'}</h2>
                {Object.values(action.errors).map((error,index)=><p key={index} role="alert" className="my-4 text-sm text-destructive">{error}</p>)}
                {upload.status==='ready' && upload.safety && (!!upload.safety.blocked.length || !!upload.safety.warnings.length) && <div className="admin-surface space-y-4">{upload.safety.blocked.map(text=><p key={text} role="alert" className="text-sm text-destructive">{text}</p>)}{upload.safety.warnings.map(text=><p key={text} className="text-sm">{text}</p>)}{!!upload.safety.warnings.length && !upload.safety.blocked.length && <label className="flex items-start gap-2 text-sm"><Checkbox checked={action.data.safety_ack===upload.safety.token} onCheckedChange={checked=>action.setData('safety_ack',checked===true ? upload.safety!.token : '')}/>Я проверил предупреждения: файл полный, данные можно применить.</label>}</div>}
                {upload.status==='ready' && summary && <><section className="admin-surface"><h2>Изменения в каталоге</h2><p className="text-sm">Обновятся {summary.storefront_changed ?? '—'} опубликованных товаров. Остатки обнулятся у {summary.stock_cleared_missing ?? '—'} позиций, отсутствующих в новом файле. Товары не удаляются.</p><p className="mt-2 text-xs text-muted-foreground">Названия, категории, фотографии и публикация сохраняются. Новые товары — черновики.</p></section>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {[
                            {key:'absent',tone:'bg-amber-500',count:comparison?.missingCount ?? 0,title:'Нет в новом прайсе',description:'Строки предыдущего прайса, которых нет в новом. Товары не удаляются.'},
                            {key:'updated',tone:'bg-blue-500',count:summary.updated || 0,title:'Обновятся',description:'Цены и остатки существующих товаров.'},
                            {key:'created_drafts',tone:'bg-emerald-500',count:summary.created_drafts || 0,title:'Новые черновики',description:'Появятся на сайте после публикации.'},
                            {key:'existing_untouched',tone:'bg-muted-foreground/50',count:summary.existing_untouched || 0,title:'Без изменений',description:'Цена и остатки совпадают.'},
                            {key:'skipped',tone:'bg-muted-foreground/50',count:(summary.held || 0)+(summary.excluded || 0)+(summary.request_only || 0),title:'Пропущены',description:'По правилам ассортимента или из-за ошибок. Откройте причины.'},
                        ].map(item=><Link key={item.key} href={`/admin/catalog-prices/${upload.id}?section=compare&${item.key==='absent' ? 'membership=missing' : 'operation='+item.key}`} className="price-apply-metric rounded-xl border bg-card p-4 transition-colors hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring"><span className="flex items-start justify-between gap-4"><span className="flex items-center gap-2 text-sm font-medium"><span aria-hidden="true" className={`size-2 shrink-0 rounded-full ${item.tone}`}/>{item.title}</span><strong className="text-xl leading-7 tabular-nums">{item.count.toLocaleString('ru-RU')}</strong></span><span className="mt-2 block text-xs text-muted-foreground">{item.description}</span></Link>)}
                    </div>
                    {comparison?.anomalies?.some(item=>item.count>0) && <aside className="admin-surface" aria-label="Требует внимания"><h3 className="text-base font-semibold">Проверьте перед обновлением</h3><div className="-mx-2 -mb-2 mt-2 space-y-1">{comparison.anomalies.filter(item=>item.count>0).map(item=>{const Icon=anomalyIcons[item.key] || TriangleAlert;return <Link key={item.key} href={`/admin/catalog-prices/${upload.id}?section=compare&anomaly=${item.key}`} className="flex min-h-9 items-center justify-between gap-4 rounded-md px-2 py-2 text-sm transition-colors hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring"><span className="flex items-center gap-2"><Icon className="size-4 shrink-0 text-muted-foreground" aria-hidden="true"/>{item.label}</span><Badge variant="secondary" className="tabular-nums">{item.count.toLocaleString('ru-RU')}</Badge></Link>})}</div></aside>}
                </>}
                {busy && <p role="status" className="mt-2 flex items-center gap-2 text-sm text-muted-foreground"><Loader2 className="size-4 animate-spin"/>{upload.status==='applying' ? 'Сохраняем цены, остатки и новые черновики.' : 'Проверяем данные перед обновлением.'} Страница обновится автоматически.</p>}
                {['stale','failed'].includes(upload.status) ? <div className="mt-2 flex max-w-2xl flex-col items-start gap-4"><p role="status" className="text-sm leading-5 text-muted-foreground">{upload.status==='stale' ? 'После проверки изменились каталог или правила импорта. Пересчитайте изменения перед применением — загружать файл заново не нужно.' : upload.error || 'Не удалось завершить проверку. Попробуйте ещё раз.'}</p><Button disabled={action.processing} onClick={()=>action.post(`/admin/catalog-prices/${upload.id}/refresh`)}><RefreshCw className="size-4"/>{action.processing ? 'Запускаем проверку…' : upload.status==='stale' ? 'Обновить сравнение' : 'Повторить проверку'}</Button></div> : upload.error && <p role="alert" className="mt-2 text-sm text-destructive">{upload.error}</p>}
                {upload.status==='completed' && <p className="text-sm text-muted-foreground">Повторно применять этот файл не нужно. Результаты сохранены.</p>}
            </section>}
            {section==='compare' && (busy || upload.error || ['failed','stale'].includes(upload.status) || !summary) && <section className="price-stage-panel rounded-xl border bg-card p-4" aria-label="Обработка прайса">
                <div role="status" aria-live="polite" className="flex items-center gap-4 font-medium">{busy && <Loader2 className="size-5 animate-spin motion-reduce:animate-none"/>}{states[upload.status] || 'Готовим сравнение'}</div>
                {busy && <p className="mt-2 text-sm text-muted-foreground">{upload.status==='queued' ? 'Файл загружен. Ожидаем запуск проверки.' : upload.status==='preparing' ? 'Читаем строки Excel и сравниваем товары с каталогом.' : 'Сохраняем проверенные изменения.'} Страница обновляется автоматически. Можно вернуться позже — загрузка сохранена.</p>}
                {upload.error && <p role="alert" className="mt-2 text-sm text-destructive">{upload.error}</p>}
                {['failed','stale'].includes(upload.status) && <Button className="mt-4" disabled={action.processing} onClick={()=>action.post(`/admin/catalog-prices/${upload.id}/refresh`)}><RefreshCw/>Повторить проверку</Button>}
                {!busy && !summary && !['failed','stale'].includes(upload.status) && <p className="mt-2 text-sm text-muted-foreground">Результат сравнения пока недоступен. <button className="underline" onClick={()=>router.reload()}>Обновить статус</button></p>}
            </section>}
            {section === 'compare' && summary && !busy && <section className="price-stage-panel space-y-4" aria-label="Подробности изменений">
                <div className="space-y-4">
                    <CatalogToolbar filter={<PhotoCatalogFilter categories={categories} category={comparison?.category || ''} subcategory={comparison?.subcategory || ''} onChange={(category,subcategory)=>updateFilters({category,subcategory})}/>} search={search} onSearch={setSearch} onSubmit={()=>compare()} total={comparison?.total || 0} onReset={()=>{setSearch('');updateFilters({compare_search:'',operation:'',membership:'',anomaly:'',category:'',subcategory:''});}}/>
                    <nav aria-label="Быстрые фильтры прайса" className="admin-quick-filters flex flex-wrap gap-2">
                        {[{value:'',label:'Все'},{value:'updated',label:'С изменениями'},{value:'existing_untouched',label:'Без изменений'},{value:'created_drafts',label:'Новые'},{value:'skipped',label:'Пропущенные'}].map(item=>{
                            const selected=operation===item.value && !comparison?.membership && !comparison?.anomaly && (item.value!=='' || comparison?.category!=='0');
                            return <Button key={item.value} type="button" variant={selected ? 'secondary' : 'ghost'} aria-pressed={selected} onClick={()=>updateFilters({operation:item.value,membership:'',anomaly:'',...(item.value==='' ? {category:'',subcategory:''} : {})})}>{item.label}<span className="text-muted-foreground tabular-nums">{(comparison?.quickCounts?.[item.value || 'all'] ?? 0).toLocaleString('ru-RU')}</span></Button>;
                        })}
                    <Button type="button" variant={comparison?.category==='0' ? 'secondary' : 'ghost'} aria-pressed={comparison?.category==='0'} onClick={()=>updateFilters({category:comparison?.category==='0' ? '' : '0',subcategory:'',operation:'',membership:'',anomaly:''})}>Без категории<span className="text-muted-foreground tabular-nums">{(comparison?.quickCounts?.uncategorized ?? 0).toLocaleString('ru-RU')}</span></Button>
                    </nav>
                    {(comparison?.membership || comparison?.anomaly || ['held','excluded','request_only'].includes(operation)) && <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"><span>{comparison?.anomaly ? comparison.anomalies?.find(item=>item.key===comparison.anomaly)?.label || 'Особое условие' : comparison?.membership ? comparison.membership==='missing' ? 'Нет в новом прайсе' : 'Появились в прайсе' : operations[operation] || ''}</span><Button variant="ghost" onClick={()=>updateFilters({operation:'',membership:'',anomaly:''})}>Сбросить фильтр</Button></div>}              {comparison?.membership==='missing' && <p className="text-sm text-muted-foreground">Эти позиции были в предыдущем прайсе, а в новом отсутствуют. Товары сохраняются. {upload.status==='completed' ? 'Устаревшее наличие этого источника снято при применении прайса.' : 'При применении прайса устаревшее наличие этого источника будет снято.'}</p>}

                    {!visibleRows.length ? <Empty className="border"><EmptyHeader><EmptyTitle>Нет позиций</EmptyTitle><EmptyDescription>{search ? 'По вашему запросу товаров нет.' : operation==='updated' ? 'Нет изменений цены или остатков. Можно перейти к следующему разделу.' : operation==='created_drafts' ? 'Новых товаров в этой загрузке нет.' : 'В этой группе нет строк.'}</EmptyDescription></EmptyHeader></Empty> : <div className="admin-table-wrap"><Table className="[&_th]:px-4 [&_td]:px-4 [&_th:first-child]:w-[45%]" aria-label="Сравнение прайса с каталогом"><TableHeader><TableRow><TableHead className="pl-4">Товар</TableHead><TableHead className="text-right">Цена</TableHead><TableHead className="text-right">Наличие</TableHead><TableHead className="text-right">Свободно</TableHead><TableHead className="text-right">В пути</TableHead><TableHead>Статус</TableHead><TableHead className="pr-4"><span className="sr-only">Действия</span></TableHead></TableRow></TableHeader><TableBody>{visibleRows.map(row=><ProductChange key={row.row} row={row} completed={upload.status==='completed'} uploadId={upload.id} categories={comparison?.categories || []} editable={['ready','stale'].includes(upload.status)}/>)}</TableBody></Table></div>}
                    <div ref={sentinel} className="flex min-h-14 items-center justify-center" aria-live="polite">{loadingMore ? <span className="flex items-center gap-2 text-sm text-muted-foreground"><Loader2 className="size-4 animate-spin"/>Загружаем товары…</span> : hasMore ? <Button variant="ghost" onClick={loadMore}>{loadError ? 'Повторить загрузку' : 'Загрузить ещё'}</Button> : visibleRows.length>0 ? <span className="text-xs text-muted-foreground">Все товары загружены · {visibleRows.length}</span> : null}</div>
                </div>
            </section>}
            {section==='photos' && (photoQueue ? <div className="space-y-4">{upload.status!=='completed' && <p className="mb-4 text-sm text-muted-foreground">Поиск фото для новых товаров начнётся после создания черновиков.</p>}<PhotoQueueContent {...photoQueue} baseUrl={`/admin/catalog-prices/${upload.id}`} embedded/></div> : <p className="price-stage-panel text-sm text-muted-foreground">Для этого файла ещё нет результатов поиска фото. Сначала завершите проверку прайса.</p>)}
            {section==='upload' && <section className="price-stage-panel price-source-panel rounded-xl border bg-card p-4">
                <div className="flex flex-wrap items-center justify-between gap-4"><div className="flex min-w-0 items-center gap-4"><FileSpreadsheet className="size-6 shrink-0 text-muted-foreground"/><div className="price-source-file min-w-0"><h2 className="break-all">{upload.name}</h2><p role="status" className="text-sm leading-5 text-muted-foreground">{upload.status==='completed' ? 'Прайс применён' : states[upload.status]}</p></div></div><Button variant="outline" asChild><Link href="/admin/catalog-prices">Загрузить другой файл</Link></Button></div>
                {upload.source_profile==='supplier-price-32' && <dl className="mt-4 grid gap-4 border-t pt-4 sm:grid-cols-2"><div><dt className="text-sm text-muted-foreground">Цена</dt><dd className="mt-1 text-sm">С клиент, предоплата — с НДС, без наценки</dd></div><div><dt className="text-sm text-muted-foreground">Наличие</dt><dd className="mt-1 text-sm">Четыре обычных склада, без уценки</dd></div><div><dt className="text-sm text-muted-foreground">Ожидаемые поставки</dt><dd className="mt-1 text-sm">Отдельно от наличия</dd></div><div><dt className="text-sm text-muted-foreground">Данные поставщика</dt><dd className="mt-1 text-sm">ID, EAN и описания сохраняются</dd></div></dl>}
                {upload.error && <p role="alert" className="mt-4 text-sm text-destructive">{upload.error}</p>}
                {upload.status!=='completed' && <div className="mt-4 flex flex-wrap items-center gap-4 border-t pt-4">{['stale','failed'].includes(upload.status) && <Button disabled={action.processing} onClick={()=>action.post(`/admin/catalog-prices/${upload.id}/refresh`)}>Повторить проверку</Button>}<span className="text-sm text-muted-foreground">Загрузка и проверка не меняют каталог</span></div>}
            </section>}


        </>}
        </div>
    </AdminLayout>;
}
