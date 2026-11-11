import { useId, type ReactNode } from 'react';
import { Search, X, ChevronDown, SlidersHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Choice } from '@/components/admin/controls';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

export function CatalogFilter({label, ...props}: Parameters<typeof Choice>[0]) {
    const id = useId();
    return <div className="min-w-0 space-y-2"><label htmlFor={id} className="text-xs font-medium text-muted-foreground">{label}</label><Choice {...props} id={id} label={label}/></div>;
}

export function CatalogSearchPanel({search,onSearch,onSubmit,total,children,chips,onReset,filterLabel='Фильтры',placeholder='Название или артикул',searchLabel='Поиск товаров',actions,loading=false}: {
    actions?:ReactNode;loading?:boolean;placeholder?:string;searchLabel?:string;filterLabel?:string;search:string;onSearch:(value:string)=>void;onSubmit:()=>void;total:number;children:ReactNode;
    chips:{key:string;label:string;remove:()=>void}[];onReset:()=>void;
}) {
    const id = useId();
    return <section className="admin-catalog-search flex min-w-0 items-center gap-2" aria-label="Поиск и фильтры" aria-busy={loading}>
        {children && <Popover>
            <PopoverTrigger asChild><Button type="button" variant="outline" className="shrink-0 gap-2"><SlidersHorizontal className="size-4"/><span>{filterLabel}</span>{chips.length>0 && <span className="rounded bg-muted px-1.5 text-xs tabular-nums">{chips.length}</span>}<ChevronDown className="size-4"/></Button></PopoverTrigger>
            <PopoverContent align="start" className="w-[min(360px,calc(100vw-32px))] space-y-4">
                {children}
                {chips.length>0 && <div className="flex flex-wrap gap-1">{chips.map(chip=><Button key={chip.key} type="button" variant="secondary" className="h-auto max-w-full whitespace-normal text-left" aria-label={'Снять фильтр: '+chip.label} onClick={chip.remove}>{chip.label}<X className="size-3 shrink-0"/></Button>)}</div>}
                {(search || chips.length>0) && <Button type="button" variant="ghost" onClick={onReset}>Сбросить всё</Button>}
            </PopoverContent>
        </Popover>}
        <form className="relative min-w-0 flex-1" role="search" onSubmit={event=>{event.preventDefault();onSubmit();}}>
            <label htmlFor={id} className="sr-only">{searchLabel}</label>
            <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground"/>
            <Input id={id} className="h-10 pl-9 pr-20" placeholder={placeholder} value={search} onChange={event=>onSearch(event.target.value)}/>
            <div className="absolute right-2 top-0 flex h-10 items-center gap-1"><span role="status" aria-label={'Найдено записей: '+total} className="text-xs tabular-nums text-muted-foreground">{total.toLocaleString('ru-RU')}</span>{search && <Button type="button" variant="ghost" size="icon" aria-label="Очистить поиск" onClick={()=>onSearch('')}><X className="size-4"/></Button>}</div>
        </form>
        {actions && <div className="admin-search-actions flex shrink-0 items-center gap-2">{actions}</div>}
    </section>;
}

export function CatalogToolbar({filter,...searchProps}: Omit<Parameters<typeof CatalogSearchPanel>[0], 'children' | 'chips'> & {filter:ReactNode}) {
    return <div className="catalog-toolbar flex min-w-0 items-center gap-2">{filter}<div className="min-w-0 flex-1"><CatalogSearchPanel {...searchProps} chips={[]}>{null}</CatalogSearchPanel></div></div>;
}
