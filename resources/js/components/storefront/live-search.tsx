import { useEffect, useId, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowRight, Search, LoaderCircle } from 'lucide-react';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { ProductPlaceholder } from './product-placeholder';
import { ProductStock } from './product-purchase';
import type { Product } from '@/types/catalog';

function Highlight({ text, query }: { text: string; query: string }) {
    const words = query.trim().split(/\s+/).filter(Boolean).map(word => word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const parts = words.length ? text.split(new RegExp(`(${words.join('|')})`, 'gi')) : [text];
    return <>{parts.map((part, index) => index % 2 ? <mark key={index}>{part}</mark> : part)}</>;
}

function SearchCover({ product }: { product: Product }) {
    const image = product.image || product.images?.[0];
    const [failed, setFailed] = useState(false);
    return image && !failed ? <img src={image} alt="" onError={() => setFailed(true)} /> : <ProductPlaceholder brand={product.brand} category={product.category} />;
}

export function LiveSearch({ catalogUrl }: { catalogUrl: string }) {
    const [query, setQuery] = useState('');
    const [focused, setFocused] = useState(false);
    const [items, setItems] = useState<Product[]>([]);
    const [total, setTotal] = useState(0);
    const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('ready');
    const [active, setActive] = useState(-1);
    const id = useId();
    const root = useRef<HTMLDivElement>(null);
    const term = query.trim();
    const open = focused && term.length >= 2;
    const resultsUrl = `${catalogUrl}?search=${encodeURIComponent(term)}&sort=relevance`;
    useEffect(() => {
        if (!open) return;
        setStatus('loading'); setItems([]); setTotal(0); setActive(-1);
        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const response = await fetch(`${catalogUrl}/suggestions?search=${encodeURIComponent(term)}`, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Search failed');
                const data = await response.json();
                if (!controller.signal.aborted) { setItems(data.items); setTotal(data.total); setStatus('ready'); }
            } catch { if (!controller.signal.aborted) setStatus('error'); }
        }, 250);
        return () => { clearTimeout(timer); controller.abort(); };
    }, [term, open, catalogUrl]);
    useEffect(() => {
        if (active >= 0) document.getElementById(`${id}-${active}`)?.scrollIntoView({ block: 'nearest' });
    }, [active, id]);
    return <div ref={root} className="storefront-live-search" onBlur={event => { if (!event.currentTarget.contains(event.relatedTarget)) setFocused(false); }}>
        <InputGroup className="storefront-hero-input">
            <InputGroupAddon><Search size={18} aria-hidden="true" /></InputGroupAddon>
            <InputGroupInput name="search" type="search" role="combobox" autoComplete="off" maxLength={200}
                placeholder="Название, бренд или артикул" aria-label="Поиск по каталогу" aria-expanded={open} aria-controls={open ? id : undefined}
                aria-autocomplete="list" aria-activedescendant={open && active >= 0 ? `${id}-${active}` : undefined}
                value={query} onFocus={() => setFocused(true)}
                onChange={event => { setQuery(event.target.value); setFocused(true); setItems([]); setActive(-1); setStatus('loading'); }}
                onKeyDown={event => {
                    if (event.key === 'Escape') { event.preventDefault(); setFocused(false); setActive(-1); }
                    if (open && items.length && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
                        event.preventDefault(); setActive(index => index < 0 ? (event.key === 'ArrowDown' ? 0 : items.length - 1) : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length);
                    }
                    if (open && event.key === 'Enter') { event.preventDefault(); setFocused(false); router.visit(active >= 0 && items[active] ? items[active].url : resultsUrl); }
                }} />
        </InputGroup>
        {open && <SearchSuggestions id={id} items={items} total={total} status={status} active={active} term={term} resultsUrl={resultsUrl} onActive={setActive} onSelect={() => setFocused(false)} />}
    </div>;
}

export type SearchSuggestionsProps = {
    id: string; items: Product[]; total: number; status: 'loading' | 'ready' | 'error';
    active: number; term: string; resultsUrl: string; onActive: (index: number) => void; onSelect: () => void;
};

/** Shared presentation for live search and isolated Storybook states. */
export function SearchSuggestions({ id, items, total, status, active, term, resultsUrl, onActive, onSelect }: SearchSuggestionsProps) {
    return <div className="storefront-search-suggestions">
        <div className="storefront-search-scroll">
            <div role="status" className="storefront-search-status">{status === 'loading' ? <><LoaderCircle size={14} className="search-spinner" />Ищем товары…</> : status === 'error' ? 'Подсказки недоступны. Откройте полный поиск.' : total ? <><span>Товары</span><span>Найдено: {total.toLocaleString('ru-RU')}</span></> : 'Ничего не найдено'}</div>
            {status === 'ready' && !items.length && <div className="storefront-search-empty"><Search size={24} /><p>Попробуйте название модели, бренд или артикул.</p></div>}
            <div id={id} role="listbox" aria-label="Найденные товары" aria-busy={status === 'loading'}>
                {items.map((item, index) => <Link id={`${id}-${index}`} key={item.id} href={item.url} role="option" aria-selected={index === active}
                    className="storefront-search-result" onMouseEnter={() => onActive(index)} onClick={() => onSelect()}>
                    <div className="search-result-cover"><SearchCover key={item.id} product={item} /></div>
                    <div className="search-result-copy"><small>{item.category || item.brand}</small><span className="search-result-title"><Highlight text={item.name.split(/\s+формат поставки/i)[0]} query={term} /></span>{item.sku && <small>Артикул: <Highlight text={item.sku} query={term} /></small>}{item.name.match(/формат поставки\s*[-—:]?\s*(.+?)(?:\s*\(вкл\.|$)/i)?.[1] && <small>{item.name.match(/формат поставки\s*[-—:]?\s*(.+?)(?:\s*\(вкл\.|$)/i)?.[1]}</small>}</div>
                    <div className="search-result-price"><strong>{item.formattedPrice}</strong><ProductStock value={item.availability} /></div>
                </Link>)}
            </div>
        </div>
            {(status === 'error' || status === 'ready' && total > 0) && <Link className="storefront-search-all" href={resultsUrl} onClick={() => onSelect()}><span className="storefront-search-all-label">{status === 'error' ? 'Повторить поиск в каталоге' : 'Показать все результаты'}{status === 'ready' && <span className="storefront-search-all-count">{total.toLocaleString('ru-RU')}</span>}</span><ArrowRight size={18} aria-hidden="true" /></Link>}
        </div>;
}
