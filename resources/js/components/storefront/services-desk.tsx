import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { useEffect, useId, useRef } from 'react';
import artwork from './services-desk-art.json';
import type { Direction } from './services-overview';
import '../../../css/services-desk.css';

export function ServicesDesk({ directions, title }: { directions: Direction[]; title: string }) {
    const root = useRef<HTMLElement>(null);
    const id = useId().replace(/:/g, '');
    // Only checked-in, decorative artwork is rendered as markup. Unique IDs keep SVG paint servers local.
    const localize = (markup: string) => markup.replace(/id="([^"]+)"/g, `id="${id}-$1"`).replace(/url\(#([^)]+)\)/g, `url(#${id}-$1)`);

    useEffect(() => {
        const media = matchMedia('(hover: hover) and (prefers-reduced-motion: no-preference)');
        const cleanups = Array.from(root.current?.querySelectorAll<HTMLElement>('.desk-card') ?? []).map(card => {
            let frame = 0;
            const leave = () => {
                cancelAnimationFrame(frame);
                frame = 0;
                card.style.removeProperty('--px');
                card.style.removeProperty('--py');
            };
            const move = (event: PointerEvent) => {
                if (!media.matches) return;
                cancelAnimationFrame(frame);
                frame = requestAnimationFrame(() => {
                    const rect = card.getBoundingClientRect();
                    card.style.setProperty('--px', (((event.clientX - rect.left) / rect.width - .5) * 2).toFixed(3));
                    card.style.setProperty('--py', (((event.clientY - rect.top) / rect.height - .5) * 2).toFixed(3));
                });
            };
            card.addEventListener('pointermove', move, { passive: true });
            card.addEventListener('pointerleave', leave);
            media.addEventListener('change', leave);
            return () => {
                leave();
                card.removeEventListener('pointermove', move);
                card.removeEventListener('pointerleave', leave);
                media.removeEventListener('change', leave);
            };
        });
        return () => cleanups.forEach(cleanup => cleanup());
    }, []);

    return <section ref={root} className="storefront-services desk-services" aria-label={title}>
        <div aria-hidden="true" dangerouslySetInnerHTML={{ __html: localize(artwork.defs) }} />
        <h2>{title}</h2>
        <div className="desk-grid">{artwork.cards.map(card => {
            const direction = directions.find(item => item.slug === card.slug);
            if (!direction) return null;
            return <article key={card.slug} className={card.classes} data-direction={card.slug}>
                <div dangerouslySetInnerHTML={{ __html: localize(card.art) }} />
                <h3 className="desk-card__title"><Link className="desk-card__link" href={direction.url}>{card.title}<ArrowUpRight className="desk-arr" aria-hidden="true" /></Link></h3>
                <p className="desk-card__text">{card.description}</p>
            </article>;
        })}</div>
    </section>;
}
