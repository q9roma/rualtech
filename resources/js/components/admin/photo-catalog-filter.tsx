import { useState } from 'react';
import { Check, LayoutGrid, Folder, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { categoryIcon } from '@/components/storefront/category-icon';

export function PhotoCatalogFilter({categories,category,subcategory,onChange}:{
    categories:{id:number;name:string;parentId:number|null}[];category:string;subcategory:string;
    onChange:(category:string,subcategory:string)=>void;
}) {    const [open,setOpen]=useState(false);
    const [activeId,setActiveId]=useState(category);
    const roots=categories.filter(c=>!c.parentId);
    const active=roots.find(c=>String(c.id)===(activeId || category)) || roots[0];
    const choose=(parent:string,child='')=>{onChange(parent,child);setOpen(false);};
    const selected=categories.find(c=>String(c.id)===(subcategory || category));
    return <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild><Button variant="outline" className="gap-2"><LayoutGrid className="size-4"/>{category==='0' ? 'Без категории' : 'Каталог'}{category && <span className="size-2 rounded-full bg-primary"/>}</Button></PopoverTrigger>
        <PopoverContent align="start" className="w-[min(760px,calc(100vw-32px))] overflow-hidden p-0" aria-label="Выбор категории">
            <div className="flex h-[min(400px,var(--radix-popover-content-available-height))] flex-col">
            <div className="grid min-h-0 flex-1 grid-cols-[minmax(130px,2fr)_minmax(0,3fr)] overflow-hidden">
                <ScrollArea className="h-full min-h-0 min-w-0 border-r border-border bg-muted/40 [&_[data-slot=scroll-area-viewport]]:overscroll-contain"><nav className="space-y-1 p-2 pr-3" aria-label="Категории каталога">
                    <button type="button" onClick={()=>choose('')} className="flex w-full rounded-md px-3 py-2 text-left text-sm hover:bg-background">Все категории</button>
                    <button type="button" onClick={()=>choose('0')} aria-pressed={category==='0'} className="flex w-full rounded-md px-3 py-2 text-left text-sm hover:bg-background aria-pressed:bg-background">Без категории</button>
                    {roots.map(c=>{const Icon=categoryIcon(c.name);return <button type="button" key={c.id} onMouseEnter={()=>setActiveId(String(c.id))} onFocus={()=>setActiveId(String(c.id))} onClick={()=>setActiveId(String(c.id))} aria-expanded={active?.id===c.id} className={'flex w-full items-center gap-2 rounded-md px-3 py-2.5 text-left text-sm font-normal '+(active?.id===c.id ? 'bg-background shadow-sm' : 'hover:bg-background/70')}><Icon className="size-4 shrink-0"/><span className="flex-1">{c.name}</span><ChevronRight className="size-3 shrink-0"/></button>;})}
                </nav></ScrollArea>
                <ScrollArea key={active?.id} className="h-full min-h-0 min-w-0 [&_[data-slot=scroll-area-viewport]]:overscroll-contain"><div className="p-4 pr-5">{active && <><h3 className="mb-4 text-sm font-semibold">{active.name}</h3><button type="button" onClick={()=>choose(String(active.id))} className="mb-4 text-sm underline underline-offset-4">Все товары раздела</button><nav aria-label="Подкатегории" className="space-y-1">{categories.filter(c=>c.parentId===active.id).map(c=><button type="button" key={c.id} onClick={()=>choose(String(active.id),String(c.id))} aria-pressed={subcategory===String(c.id)} className={'block w-full rounded-md px-2 py-2 text-left text-sm hover:bg-muted '+(subcategory===String(c.id) ? 'bg-muted font-medium' : '')}>{c.name}</button>)}</nav></>}</div></ScrollArea>
            </div>
            </div>
        </PopoverContent>
    </Popover>;
}
