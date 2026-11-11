import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';

type Suggestion = { label: string; city: string; street: string; postcode: string };
export function AddressSuggest({ id, value, onChange, onSelect, city = '', cities = false, invalid = false }: {
    id: string; value: string; onChange: (value: string) => void; onSelect: (value: Suggestion) => void;
    city?: string; cities?: boolean; invalid?: boolean;
}) {
    const [focused, setFocused] = useState(false);
    const [items, setItems] = useState<Suggestion[]>([]);
    const [status, setStatus] = useState('');
    const [active, setActive] = useState(-1);
    const selected = useRef(value);
    useEffect(() => {
        setItems([]); setActive(-1); setStatus('');
        if (!focused || value.trim().length < 3 || value === selected.current) return;
        let disposed = false;
        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setStatus('Ищем адрес…');
            const timeout = window.setTimeout(() => controller.abort(), 8000);
            try {
                const params = new URLSearchParams({ q: cities ? value : `${city} ${value}`.trim(), limit: '6', countrycode: 'RU' });
                if (cities) params.append('layer', 'city');
                const response = await fetch(`https://photon.komoot.io/api/?${params}`, { signal: controller.signal });
                if (!response.ok) throw new Error('unavailable');
                const data = await response.json();
                const results: Suggestion[] = (data.features ?? []).filter((f: { properties: { countrycode?: string } }) => f.properties.countrycode?.toUpperCase() === 'RU').map((f: { properties: Record<string, string> }) => {
                    const p = f.properties;
                    const locality = p.city || p.town || p.village || (cities ? p.name : '') || '';
                    const street = [p.street || (!cities ? p.name : ''), p.housenumber].filter(Boolean).join(', ');
                    return { city: locality, street, postcode: p.postcode || '', label: [...new Set([locality, cities ? p.state : street].filter(Boolean))].join(', ') };
                }).filter((s: Suggestion) => s.label);
                if (controller.signal.aborted) return;
                setItems(results); setStatus(results.length ? '' : 'Адрес не найден. Можно ввести вручную.');
            } catch { if (!disposed) setStatus('Подсказки недоступны. Введите адрес вручную.'); }
            finally { window.clearTimeout(timeout); }
        }, 600);
        return () => { disposed = true; window.clearTimeout(timer); controller.abort(); };
    }, [value, city, cities, focused]);
    const choose = (item: Suggestion) => { selected.current = cities ? item.city : item.street; onSelect(item); setFocused(false); setItems([]); setStatus(''); };
    return <div className="address-suggest">
        <Input id={id} name={id} required maxLength={cities ? 100 : 255} autoComplete="off" value={value} aria-invalid={invalid}
            role="combobox" aria-autocomplete="list" aria-expanded={focused && items.length > 0} aria-controls={`${id}-suggestions`} aria-activedescendant={active >= 0 ? `${id}-suggestion-${active}` : undefined}
            onFocus={() => setFocused(true)} onBlur={() => setFocused(false)} onChange={e => onChange(e.target.value)}
            onKeyDown={e => { if (e.key === 'Escape') setFocused(false); if (items.length && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { e.preventDefault(); setActive(i => (i + (e.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length); } if (e.key === 'Enter' && active >= 0 && focused && items[active]) { e.preventDefault(); choose(items[active]); } }} />
        {focused && items.length > 0 && <ul id={`${id}-suggestions`} role="listbox" className="address-suggest-list">{items.map((item, i) => <li id={`${id}-suggestion-${i}`} role="option" aria-selected={i === active} key={`${item.label}-${i}`} onMouseDown={e => e.preventDefault()} onClick={() => choose(item)}>{item.label}</li>)}</ul>}
        {status && <small className="address-suggest-caption" role="status">{status}</small>}
    </div>;
}
