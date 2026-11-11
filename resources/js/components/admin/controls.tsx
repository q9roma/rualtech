import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { Check, ChevronsLeft, ChevronsRight, ChevronLeft, ChevronRight, Copy } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import type { Options, Pagination } from './types';

export function CheckBox({ checked, onChange, label, disabled }: { checked: boolean; onChange: (checked: boolean) => void; label: string; disabled?: boolean }) {
    return <Checkbox aria-label={label} disabled={disabled} checked={checked} onCheckedChange={v => onChange(v === true)}/>;
}
export function Choice({ value, onChange, options, label, placeholder = 'Не выбрано', searchable = false, id }: { value: string; onChange: (value: string) => void; options: Options; label: string; placeholder?: string; searchable?: boolean; id?: string }) {
    const [search, setSearch] = useState('');
    return <Select value={value || '__empty'} onValueChange={v => onChange(v === '__empty' ? '' : v)} onOpenChange={() => setSearch('')}><SelectTrigger id={id} aria-label={label} className="w-full"><SelectValue placeholder={placeholder}/></SelectTrigger><SelectContent position="popper">
        {searchable && <div className="p-2"><Input aria-label={`Поиск: ${label}`} value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.stopPropagation()} placeholder="Поиск…" /></div>}
        <SelectItem value="__empty">{placeholder}</SelectItem>
        {Object.entries(options).filter(([key, name]) => key === value || `${name} ${key}`.toLocaleLowerCase().includes(search.toLocaleLowerCase())).map(([key, name]) => <SelectItem key={key} value={key}>{name}</SelectItem>)}
    </SelectContent></Select>;
}
export function Confirm({ open, onOpenChange, onConfirm, busy, title = 'Удалить запись?', description = 'Это действие нельзя отменить. Запись будет удалена.' }: { open: boolean; onOpenChange: (value: boolean) => void; onConfirm: () => void; busy?: boolean; title?: string; description?: string }) {
    return <Dialog open={open} onOpenChange={onOpenChange}><DialogContent><DialogHeader><DialogTitle>{title}</DialogTitle><DialogDescription>{description}</DialogDescription></DialogHeader><DialogFooter><Button variant="outline" onClick={() => onOpenChange(false)} disabled={busy}>Отмена</Button><Button variant="destructive" onClick={onConfirm} disabled={busy}>{busy ? 'Удаляем…' : 'Удалить'}</Button></DialogFooter></DialogContent></Dialog>;
}
export function Pager({ data, onPageSize, selected }: { data: Pagination<unknown>; onPageSize?: (size: string) => void; selected?: number }) {
    if (data.last_page<=1 && data.total<=10) return null;
    return <div className="admin-pagination"><span className="text-muted-foreground">{selected ? `Выбрано: ${selected} · ` : ''}{data.from ?? 0}–{data.to ?? 0} из {data.total}</span><div className="flex flex-wrap items-center gap-4">
        {onPageSize && <div className="admin-page-size"><span>Строк на странице</span><Choice label="Строк на странице" value={String(data.per_page)} onChange={v => v && onPageSize(v)} options={{ 10: '10', 25: '25', 50: '50', 100: '100' }}/></div>}
        {data.last_page>1 && <><span>Страница {data.current_page} из {data.last_page}</span><div className="flex gap-2">{[[data.current_page > 1 ? data.first_page_url : null, ChevronsLeft, 'Первая страница'], [data.prev_page_url, ChevronLeft, 'Предыдущая страница'], [data.next_page_url, ChevronRight, 'Следующая страница'], [data.current_page < data.last_page ? data.last_page_url : null, ChevronsRight, 'Последняя страница']].map(([href, Icon, label], i) => {
            const I = Icon as typeof ChevronLeft;
            return href ? <Button key={i} asChild variant="outline" size="icon"><Link href={String(href)} aria-label={String(label)} preserveState><I/></Link></Button> : <Button key={i} variant="outline" size="icon" aria-label={String(label)} disabled><I/></Button>;
        })}</div></>}</div></div>;
}
export function CopyValue({ value }: { value: string }) {
    const [copied, setCopied] = useState(false);
    return <button type="button" className="admin-copy" aria-label={`Скопировать ${value}`} onClick={async () => { try { await navigator.clipboard.writeText(value); setCopied(true); setTimeout(() => setCopied(false), 1500); } catch { setCopied(false); } }}>{value}{copied ? <Check size={12}/> : <Copy size={12}/>}</button>;
}
export function date(value: unknown) { if (!value) return '—'; const d = new Date(String(value)); return Number.isNaN(d.getTime()) ? String(value) : d.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
