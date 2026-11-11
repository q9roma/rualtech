import { Progress } from '@/components/ui/progress';
import { Collapsible, CollapsibleTrigger, CollapsibleContent } from '@/components/ui/collapsible';
import { usePhotoWheel } from '@/hooks/use-photo-wheel';
import { ProductImagePlaceholder } from '@/components/admin/product-image-placeholder';
import { PhotoCatalogFilter } from '@/components/admin/photo-catalog-filter';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table';
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription } from '@/components/ui/empty';
import { Fragment, useEffect, useState, useRef } from 'react';
import { Link, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogTrigger } from '@/components/ui/dialog';
import { CatalogToolbar } from '@/components/admin/catalog-search-panel';


import { ChevronDown, Check, Trash2, LoaderCircle } from 'lucide-react';
import axios from 'axios';
import { toast } from 'sonner';
import { PhotoMatches } from '@/components/admin/photo-matches';
import { PhotoUpload } from '@/components/admin/photo-upload';
import { Card, CardContent } from '@/components/ui/card';

type Photo = { url: string; source: string; width: number; height: number; flags: string[]; matchesCount?:number };
type Task = { assignment?:{label:string;at:string|null}|null; reviewToken:string; id: number; productId: number; name: string; sku: string; brand: string; status: string; attempts: number; updatedAt: string; nextAttempt: string | null; leaseUntil: string | null; error: string | null; reason: string | null; ean: string; eanConflict: boolean; identity: {verdict: string; brand: string; mpn: string; gtins: string[]} | null; sources: {source: string; status: string}[]; errors: string[]; images: Photo[] };
export type PhotoQueueProps = { tasks: {data: Task[]; total: number; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null}; counts: Record<string, number>; total: number; completed: number; categories?: {id:number;name:string;parentId:number|null}[]; filters: {search: string; status: string; category?:string; subcategory?:string}; lastRun: string | null };

export default function PhotoQueue(props: PhotoQueueProps) {
    const [refreshing,setRefreshing]=useState(false);
    return <AdminLayout title="Фотографии каталога" actions={<Button variant="outline" disabled={refreshing} onClick={()=>{setRefreshing(true);router.reload({onFinish:()=>setRefreshing(false)});}}>{refreshing ? 'Обновляем…' : 'Обновить список'}</Button>}><PhotoQueueContent {...props}/></AdminLayout>;
}
const states: Record<string,string> = {queued:'В очереди',processing:'Обрабатывается',preserved:'Назначены',ready:'Найдены',review:'Требуется проверка',not_found:'Не найдено',restricted:'Доступ ограничен',retry:'Повторная попытка',failed:'Ошибка',rejected:'Фото отклонено',superseded:'Заменено новым заданием'};
const statusBadgeClasses: Record<string,string> = {
    ready: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    preserved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    review: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300',
    restricted: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300',
    retry: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300',
    processing: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    failed: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};
const flags: Record<string,string> = {small:'Недостаточное разрешение',blank:'Пустое изображение',shared_across_models:'Этот же файл используется у нескольких моделей — проверьте соответствие товара'};
const sourceStates: Record<string,string> = {...states, matched:'Точное совпадение',missing:'Совпадение не найдено',no_photo:'Нет ссылки на фото',candidate:'Найден товар в источнике',identity_review:'Проверить идентификаторы',ean_conflict_or_missing:'EAN отсутствует или не совпадает',ambiguous:'Несколько совпадений'};
const reason = (task: Task) => {
    if (task.status==='rejected') return 'Фото отклонено. Товар можно публиковать без него, если данные заполнены.';
    if (task.status==='ready') return 'Фото проверено. Назначается автоматически.';
    if (task.status==='review') return null;
    return ({rejected:'Фото отклонено. Товар можно публиковать без него, если данные заполнены.',preserved:'Уже выбранные фотографии сохранены, повторная загрузка не нужна.',queued:'Ожидает фоновой обработки.',processing:'Проверяем источники и изображения.',ready:'Фото найдено и технически проверено. На витрину не назначено.',not_found:'В проверенных источниках подходящее фото не найдено.',restricted:'Источник ограничивает доступ. Это не означает отсутствие товара.',retry:'Временная ошибка. Повтор запланирован автоматически.',failed:'Попытки исчерпаны; требуется разбор ошибки.',review:'Нужно проверить соответствие товара или качество фото.',superseded:'Идентификаторы товара изменились либо он отсутствует в актуальном прайсе.'} as Record<string,string>)[task.status];
};
const canReviewPhotos = (task: Task) => task.status==='review' || (task.status==='failed' && !task.images.length);
const date = (value: string | null) => value ? new Date(value.includes('T') ? value : value.replace(' ','T')+'Z').toLocaleString('ru-RU',{timeZone:'Europe/Moscow'}) : '—';

function PhotoPreview({image,task,index=0,cover=false}:{image:Photo;task:Task;index?:number;cover?:boolean}) {
    const [active, setActive] = useState(index);
    const currentIndex = active < task.images.length ? active : 0;
    const current = task.images[currentIndex] || image;
    const previewRef = usePhotoWheel(task.images.length, setActive);
    return <Dialog onOpenChange={open => { if (open) setActive(index); }}>
        <DialogTrigger asChild>
            <button type="button" className={cover ? 'admin-product-cover photo-preview-link' : 'photo-preview-link block w-full rounded-lg'} style={cover ? {width:96,height:96} : undefined} aria-label={`Открыть фото ${index+1}: ${task.name}`}>
                <img loading="lazy" src={image.url} alt="" className={cover ? 'size-full object-contain' : 'h-60 w-full rounded-lg bg-white object-contain p-3'}/>
            </button>
        </DialogTrigger>
        <DialogContent ref={previewRef} className="max-h-[90dvh] overflow-y-auto sm:max-w-4xl" onKeyDown={event => {
            if (task.images.length > 1 && (event.key === 'ArrowRight' || event.key === 'ArrowLeft')) {
                event.preventDefault();
                setActive(previous => (previous + (event.key === 'ArrowRight' ? 1 : -1) + task.images.length) % task.images.length);
            }
        }}>
            <DialogHeader className="pr-6">
                <DialogTitle className="line-clamp-2 break-words leading-snug" title={task.name}>{task.name}</DialogTitle>
                <DialogDescription>{task.sku || 'Артикул не указан'} · Фото {currentIndex+1} из {task.images.length}{current.width>0 && current.height>0 ? ` · ${current.width} × ${current.height}` : ''}</DialogDescription>
            </DialogHeader>
            <img src={current.url} alt={`${task.name} — фото ${currentIndex+1}`} className="h-[60dvh] w-full rounded-md bg-white object-contain"/>
            {task.images.length > 1 && <div className="flex flex-wrap gap-2" role="group" aria-label="Фотографии товара">
                {task.images.map((photo, photoIndex) => <button key={photoIndex} type="button" aria-label={`Фото ${photoIndex+1} из ${task.images.length}`} aria-pressed={photoIndex===currentIndex} onClick={()=>setActive(photoIndex)} className="h-14 w-14 rounded-md border bg-white p-1 aria-pressed:border-foreground focus-visible:outline-2 focus-visible:outline-offset-2">
                    <img src={photo.url} alt="" className="size-full object-contain"/>
                </button>)}
            </div>}
        </DialogContent>
    </Dialog>;
}

function ReviewPhoto({task,onUpdated}:{task:Task;onUpdated:(update:Pick<Task,'images'|'reviewToken'|'updatedAt'|'status'>)=>void}) {

    const [processing,setProcessing]=useState(false);


    const [error,setError]=useState('');
    const submit=async(decision:string,index:number|null=null,photo?:File,onProgress?:(progress:number)=>void,matchId?:string)=>{
        if(processing) return;
        setProcessing(true);setError('');
        const data=new FormData();
        data.append('decision',decision);data.append('token',task.reviewToken);
        if(index!==null) data.append('index',String(index));
        if(matchId) data.append('matchId',matchId);
        if(photo) data.append('photo',photo);
        try {
            const {data:update}=await axios.post<Pick<Task,'images'|'reviewToken'|'updatedAt'|'status'>>(`/admin/catalog-photo-queue/${task.id}/review`,data,{
                headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},
                onUploadProgress:event=>{if(photo && event.total) onProgress?.(Math.min(99,Math.round(event.loaded/event.total*100)));},
            });
            onUpdated(update);
            if(photo) toast.success('Фото загружено', {description:<><span className="block max-w-full truncate" title={photo.name}>{photo.name}</span><span>Подтвердите фото в карточке.</span></>});
            else if(decision==='accept_all') toast.success('Фото назначены');
            else if(decision==='accept' || decision==='accept_match') toast.success('Фото назначено');
            else toast.success(index!==null ? 'Фото удалено из набора' : task.images.length ? 'Все фото удалены из набора' : 'Товар продолжит обработку без фото');
        } catch(error) {
            const errors=axios.isAxiosError(error) ? error.response?.data?.errors : null;
            const message=errors ? Object.values(errors).flat().join(' ') : 'Не удалось сохранить изменения. Попробуйте ещё раз.';
            if(photo) throw new Error(message);
            setError(message);
        } finally {
            setProcessing(false);

        }
    };
    const upload=(photo:File,onProgress:(progress:number)=>void)=>submit('upload',null,photo,onProgress);
    const decide=(decision:string,index:number|null=null)=>void submit(decision,index);
    const blocked = (image:Photo) => task.eanConflict || image.flags.length > 0;
    const awaitingConfirmation = !task.eanConflict
        && !['Identity conflict','Brand/MPN matched but EAN not confirmed'].includes(task.reason || '')
        && !['conflict','review'].includes(task.identity?.verdict || '')
        && !task.images.some(image=>image.flags.length>0 || !!image.matchesCount);
    return <div className="space-y-4 text-sm" aria-busy={processing}>
        <div className="flex flex-wrap items-center justify-between gap-4">
            <p className="font-medium">Фотографии · {task.images.length}</p>
            <div className="flex flex-wrap gap-2">
                {task.images.length>1 && <>
                    <Button variant="outline" disabled={processing || task.images.some(blocked)} onClick={()=>decide('accept_all')}>Подтвердить все</Button>
                    <Button variant="ghost" disabled={processing} onClick={()=>decide('reject')}>Удалить все</Button>
                </>}
                {task.status==='review' && !task.images.length && <Button variant="outline" disabled={processing} onClick={()=>decide('reject')}>Продолжить без фото</Button>}
            </div>
        </div>
        {error && <p role="alert" className="text-destructive">{error}</p>}
        <div className="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-4">
        <PhotoUpload disabled={processing} onUpload={upload} onError={setError}/>
        {task.images.map((image,index)=><Card key={image.url} className="admin-photo-review-item min-w-0 gap-0 py-0 shadow-none">
            <PhotoPreview image={image} task={task} index={index}/>
            <CardContent className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-baseline justify-between gap-2"><span className="font-medium">Фото {index+1}</span><span className="text-xs text-muted-foreground">{image.width>0 && image.height>0 ? `${image.width} × ${image.height}` : ''}</span></div>
                <p className="text-xs text-muted-foreground">{image.source}</p>
                {awaitingConfirmation && <p className="text-xs leading-5 text-muted-foreground">Ожидает ручного подтверждения. Текущих ошибок качества и совпадений с другими товарами нет.</p>}
                {task.eanConflict && <p className="text-xs leading-5 text-amber-700 dark:text-amber-400">В прайсе указаны разные EAN. Подтверждение недоступно до исправления данных товара.</p>}
                {(task.reason==='Identity conflict' || task.identity?.verdict==='conflict') && <p className="text-xs leading-5 text-amber-700 dark:text-amber-400">Идентификаторы найденного товара расходятся с прайсом. Проверьте соответствие модели.</p>}
                {(task.reason==='Brand/MPN matched but EAN not confirmed' || task.identity?.verdict==='review') && <p className="text-xs leading-5 text-amber-700 dark:text-amber-400">EAN найденного товара не подтверждён. Проверьте соответствие модели.</p>}
                {!!image.matchesCount && <p className="text-xs leading-5 text-amber-700 dark:text-amber-400">Есть похожие фото у других товаров. Сравните модели перед подтверждением.</p>}
                {image.flags.length>0 && <p className="text-xs leading-5 text-amber-700 dark:text-amber-400">{image.flags.map(flag=>flags[flag] || flag).join('; ')}. Подтверждение недоступно.</p>}
                <div className="mt-auto grid shrink-0 grid-cols-2 gap-2 pt-1">
                    <Button variant="outline" className="w-full min-w-0" disabled={processing || blocked(image)} onClick={()=>decide('accept',index)} aria-label={`Подтвердить фото ${index+1}`}><Check className="size-4" aria-hidden="true"/>Подтвердить</Button>
                    <Button variant="outline" className="w-full min-w-0" disabled={processing} onClick={()=>decide('reject',index)} aria-label={`Удалить фото ${index+1} из набора`}><Trash2 className="size-4" aria-hidden="true"/>Удалить</Button>
                </div>
                {!!image.matchesCount && <PhotoMatches taskId={task.id} index={index} token={task.reviewToken} name={task.name} image={image} count={image.matchesCount} processing={processing} eanConflict={task.eanConflict} error={error} onConfirm={matchId=>void submit(matchId ? 'accept_match' : 'accept',index,undefined,undefined,matchId)}/>}
            </CardContent>
        </Card>)}</div>
    </div>;
}

export function PhotoQueueContent({tasks,counts:initialCounts,total:initialTotal,completed:initialCompleted,filters,categories=[],lastRun:initialLastRun,baseUrl="/admin/catalog-photo-queue",embedded=false}: PhotoQueueProps & {baseUrl?: string; embedded?: boolean}) {
    const [expanded,setExpanded]=useState<number|null>(null);
    const [search,setSearch]=useState(filters.search);
    const submittedSearch=useRef(filters.search);
    const [status,setStatus]=useState(filters.status);
    const [live,setLive]=useState({counts:initialCounts,total:initialTotal,completed:initialCompleted,lastRun:initialLastRun});
    const {counts,total,completed,lastRun}=live;
    useEffect(()=>{setLive({counts:initialCounts,total:initialTotal,completed:initialCompleted,lastRun:initialLastRun});},[initialCounts,initialTotal,initialCompleted,initialLastRun]);
    useEffect(()=>{
        const controller=new AbortController();
        let inFlight=false;
        const refresh=async()=>{
            if(document.hidden || inFlight) return;
            inFlight=true;
            try {
                const {data}=await axios.get('/admin/catalog-photo-queue/progress',{params:{upload:embedded ? baseUrl.split('/').pop() : undefined,category:filters.category || '',subcategory:filters.subcategory || ''},signal:controller.signal,timeout:10000});
                if(!controller.signal.aborted) setLive(data);
            } catch { /* Keep the last confirmed counts; the next poll retries. */ }
            finally {inFlight=false;}
        };
        const timer=setInterval(refresh,3000);
        document.addEventListener('visibilitychange',refresh);
        return ()=>{clearInterval(timer);controller.abort();document.removeEventListener('visibilitychange',refresh);};
    },[embedded,baseUrl,filters.category,filters.subcategory]);
    useEffect(()=>{submittedSearch.current=filters.search;setSearch(filters.search);setStatus(filters.status);},[filters.search,filters.status]);
    const rowCounts=useRef(JSON.stringify(initialCounts));
    const liveCounts=JSON.stringify(counts);
    useEffect(()=>{
        if(tasks.current_page>1 || expanded!==null || rowCounts.current===liveCounts) return;
        const timer=setTimeout(()=>{
            rowCounts.current=liveCounts;
            router.reload({only:embedded ? ['photoQueue'] : ['tasks']});
        },1000);
        return ()=>clearTimeout(timer);
    },[liveCounts,embedded,tasks.current_page,expanded]);
    const key=`${baseUrl}:${filters.status}:${filters.search}:${filters.category}:${filters.subcategory}`;
    const [loaded,setLoaded]=useState<{key:string;rows:Task[]}>({key,rows:tasks.data});
    const [loading,setLoading]=useState(false);

    const [loadError,setLoadError]=useState(false);
    const sentinel=useRef<HTMLDivElement>(null);
    const pending=useRef(false);
    useEffect(()=>{setLoaded(previous=>({key,rows:previous.key!==key || tasks.current_page===1 ? tasks.data : [...previous.rows.map(old=>tasks.data.find(item=>item.id===old.id) || old),...tasks.data.filter(item=>!previous.rows.some(old=>old.id===item.id))]}));},[tasks.data,tasks.current_page,key]);
    const rows=loaded.key===key ? loaded.rows : tasks.data;
    const filter=(nextStatus=status,nextSearch=search,category=filters.category || '',subcategory=filters.subcategory || '')=>{submittedSearch.current=nextSearch;setStatus(nextStatus);setSearch(nextSearch);router.get(baseUrl,{search:nextSearch,status:nextStatus,category,subcategory,...(embedded ? {section:'photos'} : {})},{preserveState:true,preserveScroll:true,replace:true});};
    useEffect(()=>{if(search===filters.search || search===submittedSearch.current) return;const timer=setTimeout(()=>filter(status,search),400);return ()=>clearTimeout(timer);},[search,status,filters.search,filters.category,filters.subcategory,baseUrl,embedded]);
    const loadMore=()=>{if(!tasks.next_page_url || pending.current) return;pending.current=true;setLoading(true);setLoadError(false);router.get(tasks.next_page_url,{}, {only:embedded ? ['photoQueue'] : ['tasks'],preserveState:true,preserveScroll:true,replace:true,onError:()=>setLoadError(true),onFinish:()=>{pending.current=false;setLoading(false);}});};
    useEffect(()=>{const element=sentinel.current;if(!element || !tasks.next_page_url || loading || loadError) return;const observer=new IntersectionObserver(entries=>{if(entries.some(e=>e.isIntersecting)) loadMore();},{root:element.closest('[data-slot="scroll-area-viewport"]'),rootMargin:'200px'});observer.observe(element);return ()=>observer.disconnect();},[tasks.next_page_url,loading,loadError,key]);
    const waiting=(counts.queued || 0)+(counts.processing || 0)+(counts.retry || 0);
    const recentlyRan=!!lastRun && Date.now()-Date.parse(lastRun)<180000;
    return <section aria-label="Работа с фотографиями" className="space-y-4">
        {total>0 && <div className="admin-surface space-y-2" role="status" aria-live="polite">
            <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span className="flex items-center gap-2 font-medium">{!!counts.processing && <LoaderCircle className="size-4 shrink-0 animate-spin motion-reduce:animate-none" aria-hidden="true"/>}{counts.processing ? 'Ищем фотографии' : waiting ? recentlyRan ? (counts.queued ? 'Поиск запущен' : 'Ожидаем повторной попытки') : 'Поиск ожидает запуска обработчика' : 'Поиск завершён'}</span>
                <span className="text-muted-foreground tabular-nums">Обработано {completed.toLocaleString('ru-RU')} из {total.toLocaleString('ru-RU')}</span>
            </div>
            <Progress data-active={waiting>0 && (!!counts.processing || recentlyRan)} value={total ? completed/total*100 : 0} aria-label="Прогресс поиска фотографий" className="photo-search-progress h-1.5 [&_[data-slot=progress-indicator]]:transition-transform [&_[data-slot=progress-indicator]]:duration-700 [&_[data-slot=progress-indicator]]:ease-out motion-reduce:[&_[data-slot=progress-indicator]]:transition-none"/>
            <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                <button type="button" className="hover:text-foreground hover:underline" onClick={()=>filter('queued')}>В очереди: {counts.queued || 0}</button>
                <button type="button" className="hover:text-foreground hover:underline" onClick={()=>filter('processing')}>В работе: {counts.processing || 0}</button>
                {!!counts.retry && <button type="button" className="hover:text-foreground hover:underline" onClick={()=>filter('retry')}>Ожидают повтора: {counts.retry}</button>}
                {lastRun && <span>Последний проход: {date(lastRun)}</span>}
            </div>
        </div>}
        <div className="admin-surface space-y-4">
        <CatalogToolbar filter={<PhotoCatalogFilter categories={categories} category={filters.category || ''} subcategory={filters.subcategory || ''} onChange={(category,subcategory)=>filter(status,search,category,subcategory)}/>} search={search} onSearch={setSearch} onSubmit={()=>filter()} total={tasks.total} onReset={()=>filter('','','','')}/>
        <div className="space-y-2">
        <nav aria-label="Товары по состоянию фото" className="admin-quick-filters flex flex-wrap gap-2">
            {[
                {value:'',label:'Все',count:total},
                {value:'preserved',label:'Назначены',count:counts.preserved || 0},
                {value:'ready',label:'Найдены',count:counts.ready || 0},
                {value:'review',label:'Проверить',count:counts.review || 0},
                {value:'not_found',label:'Не найдены',count:counts.not_found || 0},
                {value:'failed',label:'Ошибки',count:(counts.failed || 0)+(counts.restricted || 0)},
                {value:'queued',label:'В очереди',count:counts.queued || 0},
                {value:'processing',label:'Ищем',count:counts.processing || 0},
                {value:'retry',label:'Повтор',count:counts.retry || 0},
                {value:'rejected',label:'Отклонены',count:counts.rejected || 0},
            ].filter(item=>!item.value || item.count>0 || status===item.value).map(item=><Button key={item.value} type="button" variant={status===item.value ? 'secondary' : 'ghost'} aria-pressed={status===item.value} onClick={()=>filter(item.value)} className="gap-2">
                {item.label}<span className="tabular-nums text-muted-foreground">{item.count.toLocaleString('ru-RU')}</span>
            </Button>)}
        </nav>
        </div>
        {!rows.length && <Empty className="border"><EmptyHeader><EmptyTitle>Нет товаров</EmptyTitle><EmptyDescription>{total ? 'По этим условиям товаров нет. Измените поиск или сбросьте фильтр.' : 'Заданий на поиск фото пока нет. После применения прайса товары попадут в автоматическую проверку.'}</EmptyDescription></EmptyHeader></Empty>}
        <div className="admin-table-wrap"><Table aria-label="Фотографии товаров" className="min-w-[760px] table-fixed text-sm leading-5 [&_th]:px-4 [&_td]:px-4">
            <TableHeader className="bg-muted/50"><TableRow className="hover:bg-transparent"><TableHead>Товар / артикул</TableHead><TableHead className="w-48">Статус фото</TableHead><TableHead className="w-44">Действия</TableHead></TableRow></TableHeader>
            <TableBody>{rows.map(task=><Fragment key={task.id}>
                <TableRow>
                    <TableCell className="whitespace-normal py-4"><div className="flex items-center gap-4">{task.images[0] ? <PhotoPreview image={task.images[0]} task={task} cover/> : <div className="admin-product-cover photo-queue-cover" style={{width:96,height:96}} aria-label="Фото отсутствует"><ProductImagePlaceholder/></div>}<div className="min-w-0"><Link href={'/admin/products/'+task.productId+'/edit'} className="line-clamp-2 break-words font-medium leading-5 hover:underline" title={task.name}>{task.name}</Link><p className="mt-1 break-words text-xs leading-4 text-muted-foreground">{task.sku || 'Артикул не указан'}</p></div></div></TableCell>
                    <TableCell className="whitespace-normal py-4 text-sm leading-5"><Badge variant="secondary" className={`max-w-full whitespace-normal text-left ${statusBadgeClasses[task.status] || ''}`}>{states[task.status] || task.status}</Badge></TableCell>
                    <TableCell className="py-4"><Button variant="outline" className="min-w-24" aria-expanded={expanded===task.id} aria-controls={'photo-details-'+task.id} onClick={()=>setExpanded(expanded===task.id ? null : task.id)}>Подробнее<ChevronDown aria-hidden="true" className={expanded===task.id ? 'size-4 shrink-0 rotate-180' : 'size-4 shrink-0'}/></Button></TableCell>
                </TableRow>
                {expanded===task.id && <TableRow className="hover:bg-transparent"><TableCell colSpan={3} className="whitespace-normal px-4 pb-5 pt-3"><div id={'photo-details-'+task.id} className="space-y-4">
                {!canReviewPhotos(task) && <div className="flex flex-wrap items-start gap-4">{task.images.length ? <div className="flex shrink-0 flex-wrap gap-4">{task.images.map((image,index)=><figure key={index} className="w-64 overflow-hidden"><PhotoPreview image={image} task={task} index={index}/><figcaption className="mt-1.5 text-xs leading-4 text-muted-foreground">Фото {index+1} · {image.source}{image.width>0 && image.height>0 ? ` · ${image.width} × ${image.height}` : ''}</figcaption></figure>)}</div> : <div className="admin-product-cover photo-queue-cover" style={{width:96,height:96}} aria-label="Фото отсутствует"><ProductImagePlaceholder/></div>}</div>}
                {canReviewPhotos(task) && <ReviewPhoto task={task} onUpdated={update=>setLoaded(previous=>({...previous,rows:previous.rows.map(row=>row.id===task.id ? {...row,...update} : row)}))}/>}
                {task.status!=='review' && reason(task) && <p className="text-sm text-muted-foreground">{reason(task)}</p>}
                <Collapsible className="border-t pt-2"><CollapsibleTrigger className="group flex min-h-8 w-full items-center justify-between gap-2 rounded-md px-2 py-1 text-left text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring focus-visible:outline-offset-2"><span>Источники и данные проверки</span><ChevronDown className="size-4 shrink-0 transition-transform group-data-[state=open]:rotate-180 motion-reduce:transition-none" aria-hidden="true"/></CollapsibleTrigger><CollapsibleContent className="pt-4 space-y-4">
                {task.sources.length>0 && <dl className="mb-4 space-y-2">{task.sources.map((source,index)=><div key={index} className="grid gap-1 sm:grid-cols-[160px_minmax(0,1fr)]"><dt className="font-medium">{source.source}</dt><dd className="text-muted-foreground">{source.status==='candidate' ? (task.images.some(image=>image.source===source.source) ? 'Найден товар · фото показано выше' : 'Найден товар · фото не получено') : sourceStates[source.status] || source.status}</dd></div>)}</dl>}
                <dl className="admin-detail-grid grid gap-4 sm:grid-cols-2">{task.assignment && <div><dt className="text-muted-foreground">Назначение фото</dt><dd className="mt-1">{task.assignment.label}{task.assignment.at && ` · ${date(task.assignment.at)}`}</dd></div>}<div><dt className="text-muted-foreground">EAN в прайсе</dt><dd className="mt-1 break-words">{task.ean || 'Не указан'}</dd></div>{task.identity && <><div><dt className="text-muted-foreground">Найденный товар</dt><dd className="mt-1 break-words">{task.identity.brand} · {task.identity.mpn}</dd></div><div><dt className="text-muted-foreground">EAN источника</dt><dd className="mt-1 break-words">{task.identity.gtins.join(', ') || 'Не указан'}</dd></div></>}<div><dt className="text-muted-foreground">Последняя проверка</dt><dd className="mt-1">{date(task.updatedAt)}</dd></div><div><dt className="text-muted-foreground">Попыток</dt><dd className="mt-1 tabular-nums">{task.attempts}</dd></div>{task.nextAttempt && <div><dt className="text-muted-foreground">Следующая проверка</dt><dd className="mt-1">{date(task.nextAttempt)}</dd></div>}</dl>
                {(task.error || task.errors.length>0) && <div className="mt-4 border-t pt-4"><p className="mb-2 font-medium">Причина ошибки</p>{[...new Set([task.error,...task.errors].filter(Boolean))].map((error,index)=><p key={index} className="whitespace-pre-wrap break-words text-muted-foreground">{error}</p>)}</div>}
                </CollapsibleContent></Collapsible>
            </div></TableCell></TableRow>}</Fragment>)}</TableBody></Table></div>
        <div ref={sentinel} className="flex min-h-12 justify-center">{tasks.next_page_url && <Button variant="ghost" disabled={loading} onClick={loadMore}>{loading ? 'Загружаем…' : loadError ? 'Повторить загрузку' : 'Загрузить ещё'}</Button>}</div>
        </div>
    </section>;
}
