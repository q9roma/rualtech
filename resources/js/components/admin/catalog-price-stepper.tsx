import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useRef } from 'react';
export type PriceSection = 'upload' | 'compare' | 'apply' | 'photos' | 'result';
const steps: {key: PriceSection; title: string; description: string}[] = [
    {key:'upload',title:'Загрузка',description:'Файл и параметры'},
    {key:'compare',title:'Сравнение',description:'Позиции и изменения'},
    {key:'apply',title:'Применение',description:'Обновление каталога'},
    {key:'photos',title:'Фотографии',description:'Прогресс и кандидаты'},
    {key:'result',title:'Результат',description:'Итоги и отчёт'},
];
// Compact workflow navigation; keep the current step visible at every width.
export function CatalogPriceStepper({status,hasSummary,uploading,uploadError,id,section='upload'}: {status?: string;hasSummary:boolean;uploading:boolean;uploadError:boolean;id?:string;section?:PriceSection}) {
    const navigation = useRef<HTMLElement>(null);
    useEffect(() => {
        const nav = navigation.current;
        const active = nav?.querySelector<HTMLElement>('[data-active="true"]');
        if (!nav || !active) return;
        const reveal = () => {
            if (nav.scrollWidth > nav.clientWidth) {
                nav.scrollLeft += active.getBoundingClientRect().left - nav.getBoundingClientRect().left - (nav.clientWidth - active.offsetWidth) / 2;
            }
        };
        reveal();
        const observer = new ResizeObserver(reveal);
        observer.observe(nav);
        return () => observer.disconnect();
    }, [section]);
    return <nav ref={navigation} className="price-stage-nav" aria-label="Разделы обновления прайса"><ol>{steps.map((step,index)=>{
        const enabled = !!id && (['upload','compare'].includes(step.key) || (['apply','photos'].includes(step.key) && hasSummary) || status==='completed');
        const active = section === step.key;
        // Only persisted workflow results count as completed, not merely visited tabs.
        const completed = !active && ((step.key === 'upload' && hasSummary) || (['compare','apply'].includes(step.key) && status === 'completed'));
        const content = <>
            <span className="price-stage-header"><span className="price-stage-number" aria-hidden="true">{completed ? <Check className="size-3"/> : index+1}</span>{index < steps.length-1 && <span className="price-stage-connector" aria-hidden="true"/>}</span>
            <span className="price-stage-content"><span className="price-stage-title">{step.title}</span><span className="price-stage-description">{step.description}</span></span>
            {completed && <span className="sr-only">Завершено</span>}
        </>;
        return <li key={step.key} data-active={active} data-completed={completed} data-disabled={!enabled && !active}>{enabled ? <Link aria-current={active ? 'step' : undefined} href={`/admin/catalog-prices/${id}?section=${step.key}`}>{content}</Link> : <span className="price-stage-label" aria-current={active ? 'step' : undefined} aria-disabled={!active || undefined}>{content}</span>}</li>;
    })}</ol>{(uploadError || uploading) && <p role="status" className="mt-4 text-sm text-muted-foreground">{uploadError ? 'Проверьте сообщение у формы.' : 'Файл загружается…'}</p>}</nav>;
}
