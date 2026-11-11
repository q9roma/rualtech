import { useEffect, useState } from 'react';
import axios from 'axios';
import { Images, Loader2, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogTrigger } from '@/components/ui/dialog';

type Image = {url:string;source:string;width:number;height:number;flags:string[]};
type Match = Image & {id:string;productId:number;name:string;sku:string;distance:number;exact:boolean};
export function PhotoMatches({taskId,index,token,name,image,count,processing,eanConflict,error,onConfirm}:{taskId:number;index:number;token:string;name:string;image:Image;count:number;processing:boolean;eanConflict:boolean;error:string;onConfirm:(matchId?:string)=>void}) {
    const [open,setOpen]=useState(false);
    const [matches,setMatches]=useState<Match[]>([]);
    const [selected,setSelected]=useState('');
    const [loading,setLoading]=useState(false);
    const [loadError,setLoadError]=useState('');
    const [retry,setRetry]=useState(0);
    useEffect(()=>{
        if(!open) return;
        const controller=new AbortController();setLoading(true);setLoadError('');setMatches([]);
        axios.get<{matches:Match[];reviewToken:string}>(`/admin/catalog-photo-queue/${taskId}/matches/${index}`,{signal:controller.signal})
            .then(({data})=>{if(data.reviewToken!==token) {setLoadError('Фотографии изменились. Обновите список перед подтверждением.');return;}setMatches(data.matches);setSelected(data.matches[0]?.id || '');})
            .catch(error=>{if(!axios.isCancel(error))setLoadError('Не удалось загрузить совпадения. Попробуйте ещё раз.');})
            .finally(()=>{if(!controller.signal.aborted)setLoading(false);});
        return ()=>controller.abort();
    },[open,taskId,index,token,retry]);
    const match=matches.find(item=>item.id===selected);
    return <Dialog open={open} onOpenChange={value=>{if(!processing)setOpen(value);}}>
        <DialogTrigger asChild>
            <Button variant="outline" className="w-full min-w-0 justify-start" disabled={processing} aria-label={`Сравнить похожие фотографии: ${count}`} title="Открыть сравнение фотографий">
                <Images className="size-4 shrink-0" aria-hidden="true"/>
                <span className="min-w-0 flex-1 truncate text-left">Похожие фото</span>
                <Badge variant="secondary" className="px-1.5 py-0 text-xs tabular-nums">{count}</Badge>
                <ChevronRight className="size-4 shrink-0" aria-hidden="true"/>
            </Button>
        </DialogTrigger>
        <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-5xl">
            <DialogHeader><DialogTitle>Сравнение фотографий</DialogTitle><DialogDescription>Визуальное сходство не гарантирует совпадение модели. Проверьте товар и выберите подходящее фото.</DialogDescription></DialogHeader>
            {loading && <p role="status" className="flex items-center gap-2 py-6"><Loader2 className="size-4 animate-spin"/>Загружаем совпадения…</p>}
            {loadError && <div role="alert" className="space-y-2"><p className="text-destructive">{loadError}</p><Button variant="outline" onClick={()=>setRetry(value=>value+1)}>Повторить</Button></div>}
            {!loading && !loadError && <>
                <div className="admin-photo-comparison">
                    <section className="flex min-w-0 flex-col gap-4">
                        <h3 className="font-medium">Текущее фото {index+1}</h3>
                        <img src={image.url} alt={name} className="h-64 w-full rounded-md bg-white object-contain"/>
                        <p className="line-clamp-2 text-sm" title={name}>{name}</p>
                        <p className="text-xs text-muted-foreground">{image.source} · {image.width} × {image.height}</p>
                        {image.flags.length>0 && <p className="text-xs text-destructive">Текущее фото не проходит проверку качества.</p>}
                        <Button className="mt-auto" variant="outline" disabled={processing || eanConflict || image.flags.length>0} onClick={()=>onConfirm()}>Подтвердить текущее фото</Button>
                    </section>
                    {match ? <section className="flex min-w-0 flex-col gap-4">
                        <h3 className="font-medium">Фото из наличия</h3>
                        <img src={match.url} alt={match.name} className="h-64 w-full rounded-md bg-white object-contain"/>
                        <a href={`/admin/products/${match.productId}/edit`} target="_blank" rel="noreferrer" className="line-clamp-2 text-sm underline-offset-4 hover:underline" title={match.name}>{match.name}</a>
                        <p className="text-xs text-muted-foreground">{match.sku} · {match.source} · {match.width} × {match.height}</p>
                        <p className="text-xs text-muted-foreground">{match.exact ? 'Тот же файл' : 'Визуально похожее изображение'}</p>
                        {match.flags.length>0 && <p className="text-xs text-destructive">Это фото не проходит проверку качества.</p>}
                        <Button className="mt-auto" disabled={processing || eanConflict || match.flags.length>0} onClick={()=>onConfirm(match.id)}>Выбрать и подтвердить</Button>
                    </section> : <p className="p-4 text-sm text-muted-foreground">Совпадения больше не доступны.</p>}
                </div>
                {matches.length>1 && <div className="flex flex-wrap gap-2" role="group" aria-label="Найденные совпадения">{matches.map(item=><button key={item.id} type="button" disabled={processing} onClick={()=>setSelected(item.id)} aria-pressed={selected===item.id} title={`${item.name} · ${item.width} × ${item.height}`} aria-label={`Сравнить: ${item.name}, ${item.width} × ${item.height}`} className="rounded-lg border p-1 aria-pressed:border-primary aria-pressed:ring-1 aria-pressed:ring-primary"><img src={item.url} alt="" className="h-16 w-20 rounded bg-white object-contain"/></button>)}</div>}
                {count>100 && <p className="text-xs text-muted-foreground">Показаны первые 100 совпадений из {count}.</p>}
            </>}
            {eanConflict && <p className="text-sm text-destructive">Для подтверждения сначала устраните конфликт EAN товара.</p>}
            {error && <p role="alert" className="text-sm text-destructive">{error}</p>}
        </DialogContent>
    </Dialog>;
}
