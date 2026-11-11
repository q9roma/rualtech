import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';

const brands = ['Supermicro', 'Lenovo', 'Supermicro', 'Lenovo', 'Huawei', 'Dell', 'Cisco', 'MikroTik', 'Xiaomi', 'NetApp', 'Yealink', 'Samsung', 'Apple', 'Lenovo', 'MSI', 'Acer', 'Asus', 'Microsoft', 'Kaspersky', 'PostgreSQL', 'Астра', 'Apple', 'Microsoft'];

export function BrandRibbon({ items = brands, label = 'Бренды', className = '' }: { items?: string[]; label?: string; className?: string }) {
    const [paused, setPaused] = useState(false);
    const [repetitions, setRepetitions] = useState(1);
    const controlRef = useRef<HTMLButtonElement>(null);
    const groupRef = useRef<HTMLSpanElement>(null);
    useEffect(() => {
        const control = controlRef.current;
        const group = groupRef.current;
        if (!control || !group) return;
        const resize = new ResizeObserver(() => {
            const sequenceWidth = group.getBoundingClientRect().width / repetitions;
            if (sequenceWidth > 0) setRepetitions(Math.max(1, Math.ceil(control.clientWidth / sequenceWidth)));
        });
        resize.observe(control);
        resize.observe(group);
        return () => resize.disconnect();
    }, [repetitions]);
    return <div className={`storefront-brand-ribbon ${className}`}>
        <p className="sr-only">{label}: {[...new Set(items)].join(', ')}</p>
        <button ref={controlRef} type="button" className="storefront-brand-control" aria-label={`Приостановить движение: ${label.toLowerCase()}`} aria-pressed={paused} title={paused ? 'Продолжить движение' : 'Приостановить движение'} onClick={() => setPaused(value => !value)}>
            <span className="storefront-brand-track" style={{ animationDuration: `${45 * repetitions}s` }} data-paused={paused} aria-hidden="true">
                {[0, 1].map(copy => <span ref={copy === 0 ? groupRef : undefined} className="storefront-brand-group" key={copy}>{Array.from({ length: repetitions }, (_, repetition) => items.map((brand, index) => <Badge variant="outline" key={`${repetition}-${index}`}>{brand}</Badge>))}</span>)}
            </span>
        </button>
    </div>;
}
