import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Clock3, FileSpreadsheet } from 'lucide-react';

export type PriceSource = {id:number;source:string;supplierId:string|null;validated:string|null;missingSince:string|null;freshness:'unknown'|'stale'|'recent';upload:{id:string;name:string;at:string;canOpen:boolean}|null};
export const sourceLabel=(source:string)=>source==='supplier-price-32' ? 'Основной прайс' : source==='altech-price' ? 'Прайс Алтех' : 'Прайс поставщика';
export const historyDate=(value:string)=>new Date(value).toLocaleString('ru-RU',{day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});

export function ProductPriceSources({sources}:{sources:PriceSource[]}) {
    if (!sources.length) return null;
    return <section aria-label="Данные поставщика" className="admin-surface mb-4">
        <h2 className="mb-4 text-sm font-semibold">Данные поставщика</h2>
        <div className="divide-y">{sources.map(source=><div key={source.id} className="space-y-2 py-4 first:pt-0 last:pb-0">
            <div className="flex flex-wrap items-center gap-2"><span className="text-sm font-medium">{sourceLabel(source.source)}</span><Badge variant="outline" className={source.freshness==='stale' ? 'border-amber-300 text-amber-800 dark:border-amber-800 dark:text-amber-300' : 'text-muted-foreground'}>{source.freshness==='unknown' ? 'Дата проверки неизвестна' : source.freshness==='stale' ? 'Проверьте актуальность' : 'Недавно обновлено'}</Badge>{source.missingSince && <Badge variant="secondary">Отсутствует в прайсе</Badge>}</div>
            <p className="flex items-start gap-2 text-sm text-muted-foreground"><Clock3 className="mt-0.5 size-4 shrink-0"/>{source.validated ? `Цена и наличие проверены ${historyDate(source.validated)}` : 'Дата проверки этой позиции ещё не зафиксирована.'}</p>
            <div className="flex items-start gap-2 text-sm text-muted-foreground"><FileSpreadsheet className="mt-0.5 size-4 shrink-0"/><div>{source.upload ? <>Прайс: {source.upload.canOpen ? <Link href={`/admin/catalog-prices/${source.upload.id}`} className="text-foreground underline underline-offset-4">{source.upload.name}</Link> : <span>{source.upload.name}</span>}</> : 'Источник последнего обновления не зафиксирован.'}</div></div>
            {source.supplierId && <p className="text-xs text-muted-foreground">Код поставщика: {source.supplierId}</p>}
        </div>)}</div>
    </section>;
}
