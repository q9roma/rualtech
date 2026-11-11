import { Checkbox } from '@/components/ui/checkbox';
import { createPortal } from 'react-dom';
import { useId, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';

type Item={id:number;name:string;group:string;reason?:string;reviewUrl?:string};
export type ReleasePreview={groups:{key:string;label:string;total:number;published:number}[];published:number;total:number;token:string;photos:Item[];publish:Item[];blocked:Item[]};
export function CatalogRelease({preview,uploadId,photosOnly=false,actionHost,title}:{title?:string;actionHost?:HTMLElement|null;preview:ReleasePreview;uploadId:string;photosOnly?:boolean}) {
    const selectionId=useId();
    const selectableGroups=preview.groups.filter(group=>group.key!=='skipped');
    const [selected,setSelected]=useState<'photos'|'release'|null>(null);
    const [groups,setGroups]=useState(['new','updated','unchanged']);
    const allSelected=selectableGroups.every(group=>groups.includes(group.key));
    const [confirmedGroups,setConfirmedGroups]=useState(groups);
    const [snapshot,setSnapshot]=useState(preview);
    const form=useForm({action:'photos',token:preview.token,groups});
    const selectedPhotos=snapshot.photos.filter(item=>confirmedGroups.includes(item.group));
    const selectedPublish=selected==='release' ? snapshot.publish.filter(item=>confirmedGroups.includes(item.group)) : [];
    const items=selected ? [...new Map([...selectedPhotos,...selectedPublish].map(item=>[item.id,item])).values()] : [];
    const open=(action:'photos'|'release')=>{form.clearErrors();setSnapshot(preview);setConfirmedGroups([...groups]);setSelected(action);};
    const submit=()=>{form.transform(()=>({action:selected,token:snapshot.token,groups:confirmedGroups}));form.post(`/admin/catalog-prices/${uploadId}/release`,{preserveScroll:true,onSuccess:()=>setSelected(null)});};
    const publishCount=preview.publish.filter(item=>groups.includes(item.group)).length;
    const actionControl=(<Button disabled={!publishCount} onClick={()=>open('release')}>Опубликовать товары</Button>);
    return <div>
        <fieldset className="min-w-0">
            <legend className="sr-only">{photosOnly ? 'Добавление фотографий' : 'Публикация товаров'}</legend>
            <div className="mb-4 flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
                <div className="space-y-1">{title && <h2>{title}</h2>}<p className="text-sm text-muted-foreground">{photosOnly ? 'Выберите группы для добавления фото.' : 'Выберите группы для публикации.'}</p></div>
                <Button type="button" variant="ghost" onClick={()=>setGroups(allSelected ? [] : selectableGroups.map(group=>group.key))}>{allSelected ? 'Снять выбор' : 'Выбрать все группы'}</Button>
            </div>
            <div className="release-groups">
                {selectableGroups.map(group=>{
                    const checked=groups.includes(group.key);
                    const stats=photosOnly
                        ? [{label:'Товары с новыми фото',value:preview.photos.filter(item=>item.group===group.key).length}]
                        : [{label:'Черновики готовы к публикации',value:preview.publish.filter(item=>item.group===group.key).length},
                           {label:'На сайте',value:group.published},
                           {label:'Черновики требуют проверки',value:preview.blocked.filter(item=>item.group===group.key).length}];
                    return <label key={group.key} className="release-group" data-selected={checked}>
                        <span className="flex items-center justify-between gap-4">
                            <span id={selectionId+group.key} className="text-sm font-medium">{group.key==='unchanged' ? 'Цена и остатки без изменений' : group.label}</span>
                            <Checkbox aria-labelledby={selectionId+group.key} checked={checked} onCheckedChange={value=>setGroups(previous=>value===true ? [...new Set([...previous,group.key])] : previous.filter(key=>key!==group.key))}/>
                        </span>
                        <span className="mt-1.5 mb-4 flex items-baseline gap-2"><span className="text-2xl font-medium tracking-tight tabular-nums">{group.total.toLocaleString('ru-RU')}</span><span className="text-xs text-muted-foreground">товаров</span></span>
                        <span className="grid gap-1.5 text-sm">
                            {stats.map(({label,value})=><span key={label} className="flex items-center justify-between gap-4"><span className="text-muted-foreground">{label}</span><span className={value ? 'tabular-nums font-medium text-foreground' : 'tabular-nums text-muted-foreground'}>{value.toLocaleString('ru-RU')}</span></span>)}
                        </span>
                    </label>;
                })}
            </div>
        </fieldset>
        {actionHost ? createPortal(actionControl,actionHost) : actionControl}

        {!photosOnly && preview.blocked.length>0 && <section className="admin-surface mt-4" aria-labelledby={selectionId+'-review'}>
            <h3 id={selectionId+'-review'} className="text-base font-medium">Требуют проверки · {preview.blocked.length.toLocaleString('ru-RU')}</h3>
            <ul className="divide-y text-sm">{preview.blocked.map(item=><li key={item.id}>
                <Link className="release-review-link -mx-2 block rounded-md px-2 py-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring" href={item.reviewUrl || `/admin/products/${item.id}/edit`}>
                    <span className="underline underline-offset-4">{item.name} ↗</span>
                    <span className="mt-1 block text-muted-foreground">{item.reason}</span>
                </Link>
            </li>)}</ul>
        </section>}
        <Dialog open={!!selected} onOpenChange={value=>{if(!value && !form.processing)setSelected(null);}}>
            <DialogContent className="admin-release-dialog sm:max-w-lg" showCloseButton={!form.processing}>
                <DialogHeader>
                    <DialogTitle>{selected==='photos' ? 'Добавить проверенные фото?' : 'Опубликовать товары?'}</DialogTitle>
                    <DialogDescription>{selected==='photos' ? 'Фото будут добавлены товарам выбранных групп.' : 'Готовые черновики выбранных групп появятся на сайте.'}</DialogDescription>
                </DialogHeader>
                <ScrollArea className="min-h-0" viewportProps={{style:{maxHeight:'calc(100dvh - 240px)'}}}>
                    <div className="space-y-4 p-4">
                        <dl className="space-y-2 text-sm">
                            {selected==='release' && <div className="flex items-center justify-between gap-4"><dt>Опубликовать товаров</dt><dd className="font-semibold tabular-nums">{selectedPublish.length.toLocaleString('ru-RU')}</dd></div>}
                        </dl>
                        <p className="text-xs text-muted-foreground">{selected==='photos' ? 'Черновики останутся скрытыми.' : 'Товары, требующие проверки, останутся черновиками.'}</p>
                        {Object.values(form.errors).map((error,index)=><p key={index} role="alert" className="text-sm text-destructive">{error}</p>)}
                    </div>
                </ScrollArea>
                <DialogFooter>
                    <Button type="button" variant="outline" disabled={form.processing} onClick={()=>setSelected(null)}>Отмена</Button>
                    <Button type="button" disabled={form.processing || !items.length} onClick={submit}>{form.processing ? 'Сохраняем…' : selected==='photos' ? 'Добавить фото' : 'Опубликовать товары'}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>;
}
