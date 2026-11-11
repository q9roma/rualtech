import { useState, useId } from 'react';
import { Check, ChevronDown, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { categoryIcon } from '@/components/storefront/category-icon';

type Category={id:number;name:string;parentId:number|null};
export function PriceCategoryFilter({categories,category,subcategory,membership,hasBaseline,appearedCount,missingCount,onSelect}:{categories:Category[];category:string;subcategory:string;membership:string;hasBaseline:boolean;appearedCount:number;missingCount:number;onSelect:(category:string,subcategory:string,membership:string)=>void}) {
    const [open,setOpen]=useState(false);
    const [active,setActive]=useState(category);
    const panelId=useId();
    const roots=categories.filter(c=>!c.parentId);
    const root=roots.find(c=>String(c.id)===active) || roots[0];
    const selected=categories.find(c=>String(c.id)===(subcategory || category));
    const choose=(parent:string,child='')=>{onSelect(parent,child,membership);setOpen(false);};
    return <Popover open={open} onOpenChange={value=>{setOpen(value);if(value) setActive(category);}}><PopoverTrigger asChild><Button variant="outline" className="w-full min-w-0 justify-between gap-2 font-normal" aria-label="Фильтры позиций и каталога"><span className="truncate">{selected?.name || (category==='0' ? 'Без категории' : 'Фильтры')}{membership ? ` · ${membership==='appeared' ? 'Появились' : 'Исчезли'}` : ''}</span><ChevronDown className="size-4 shrink-0"/></Button></PopoverTrigger>
        <PopoverContent align="end" className="w-[min(680px,calc(100vw-32px))] p-0" aria-label="Фильтры позиций и каталога">
            {hasBaseline && <div className="border-b p-2"><div className="px-2 pb-2 pt-1 text-sm font-medium">Позиции</div><div className="flex flex-wrap gap-1">{[{value:'',label:'Все позиции'},{value:'appeared',label:`Появились · ${appearedCount}`},{value:'missing',label:`Исчезли · ${missingCount}`}].map(item=><Button key={item.value} variant={membership===item.value ? 'secondary' : 'ghost'} aria-pressed={membership===item.value} onClick={()=>onSelect(category,subcategory,item.value)}>{item.label}</Button>)}</div></div>}
            <div className="flex items-center gap-2 border-b p-2"><Button variant={!category ? 'secondary' : 'ghost'} onClick={()=>choose('')}>Все категории</Button><Button variant={category==='0' ? 'secondary' : 'ghost'} onClick={()=>choose('0')}>Без категории</Button></div>
            <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)]"><ScrollArea className="h-80 border-r"><nav className="p-2" aria-label="Категории">{roots.map(item=>{const Icon=categoryIcon(item.name);return <button key={item.id} type="button" aria-expanded={root?.id===item.id} aria-controls={panelId} onFocus={()=>setActive(String(item.id))} onClick={()=>setActive(String(item.id))} className={`flex w-full items-center gap-2 rounded-md p-2 text-left text-sm hover:bg-muted ${root?.id===item.id ? 'bg-muted font-medium' : ''}`}><Icon className="hidden size-4 shrink-0 sm:block"/><span className="flex-1">{item.name}</span><ChevronRight className="size-4 shrink-0"/></button>;})}</nav></ScrollArea>
            <ScrollArea className="h-80"><nav id={panelId} className="p-2" aria-label="Подкатегории">{root && <><button type="button" className="mb-2 w-full rounded-md p-2 text-left text-sm font-semibold hover:bg-muted" onClick={()=>choose(String(root.id))}>Вся категория: {root.name}</button>{categories.filter(c=>c.parentId===root.id).map(item=><button key={item.id} type="button" onClick={()=>choose(String(root.id),String(item.id))} className="flex w-full items-center justify-between gap-2 rounded-md p-2 text-left text-sm hover:bg-muted"><span>{item.name}</span>{subcategory===String(item.id) && <Check className="size-4 shrink-0"/>}</button>)}</>}</nav></ScrollArea></div>
        </PopoverContent></Popover>;
}
