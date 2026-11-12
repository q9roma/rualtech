import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { useId } from 'react';
import artwork from './services-desk-art.json';
import type { Direction } from './services-overview';
import '../../../css/services-desk.css';

export function ServicesDesk({ directions, title }: { directions: Direction[]; title: string }) {
    const id = useId().replace(/:/g, '');
    // Only checked-in, decorative artwork is rendered as markup. Unique IDs keep SVG paint servers local.
    const localize = (markup: string) => markup.replace(/id="([^"]+)"/g, `id="${id}-$1"`).replace(/url\(#([^)]+)\)/g, `url(#${id}-$1)`);


    return <section className="storefront-services desk-services" aria-label={title}>
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
