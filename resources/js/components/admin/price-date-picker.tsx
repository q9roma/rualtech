import { useState } from 'react';
import { ru } from 'react-day-picker/locale';
import { CalendarDays, ChevronDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

function parse(value: string) { const [year,month,day]=value.split('-').map(Number); return new Date(year,month-1,day); }
function format(date: Date) { return `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`; }

export function PriceDatePicker({value,onChange,today,disabled=false,error}:{value:string;onChange:(value:string)=>void;today:string;disabled?:boolean;error?:string}) {
    const [open,setOpen]=useState(false);
    const current=parse(today);
    const previous=parse(today); previous.setDate(previous.getDate()-1);
    const yesterday=format(previous);
    const custom=!!value && value!==today && value!==yesterday;
    return <fieldset className="space-y-2" disabled={disabled} aria-describedby={error ? 'price-date-error' : 'price-date-help'}>
        <legend className="mb-2 text-sm font-medium">Дата прайса поставщика</legend>
        <div className="flex flex-wrap gap-2">
            <Button type="button" variant={value===yesterday ? 'default' : 'outline'} aria-pressed={value===yesterday} onClick={()=>onChange(yesterday)}>Вчера</Button>
            <Button type="button" variant={value===today ? 'default' : 'outline'} aria-pressed={value===today} onClick={()=>onChange(today)}>Сегодня</Button>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild><Button type="button" variant={custom ? 'default' : 'outline'} disabled={disabled} aria-invalid={!!error} aria-label={custom ? `Другая дата: ${parse(value).toLocaleDateString('ru-RU')}` : 'Другая дата'}><CalendarDays/>{custom ? parse(value).toLocaleDateString('ru-RU') : 'Другая дата'}<ChevronDown aria-hidden="true" className={`opacity-60 transition-transform duration-200 motion-reduce:transition-none ${open ? 'rotate-180' : 'rotate-0'}`}/></Button></PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start" aria-label="Выбор даты прайса">
                    <Calendar mode="single" locale={ru} weekStartsOn={1} selected={value ? parse(value) : undefined} defaultMonth={value ? parse(value) : current} today={current} endMonth={current} disabled={{after:current}} autoFocus required onSelect={date=>{onChange(format(date));setOpen(false);}}/>
                </PopoverContent>
            </Popover>
        </div>
        <p id="price-date-help" className="text-xs text-muted-foreground">Укажите дату исходного прайса, а не день загрузки.</p>
        {error && <p id="price-date-error" role="alert" className="text-sm text-destructive">{error}</p>}
    </fieldset>;
}
