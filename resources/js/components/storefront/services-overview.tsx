import { Link } from '@inertiajs/react';
import { ArrowUpRight, Check, Monitor, Server, Network, Video, Printer, Zap, Cpu, PanelsTopLeft } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { ServicesDesk } from './services-desk';

export type Direction = { slug: string; title: string; description: string; icon: string; url: string };
const icons = { workplaces: Monitor, 'servers-storage': Server, networks: Network, 'meeting-rooms': Video, printing: Printer, power: Zap, 'pc-upgrade': Cpu, software: PanelsTopLeft };
const experienced = new Set(['servers-storage', 'networks', 'meeting-rooms']);

export function ServicesOverview({ directions, title = 'услуги' }: { directions: Direction[]; title?: string }) {
    if (directions.length === 8) return <ServicesDesk directions={directions} title={title} />;
    return <section className="storefront-services" aria-label={title}>
        <h2>{title}</h2>
        <div className="storefront-services-grid storefront-directions-grid">{directions.map(direction => {
            const Icon = icons[direction.slug as keyof typeof icons] ?? Monitor;
            return <Card key={direction.slug} className="storefront-service-card direction-tile" data-direction={direction.slug}>
                
                {direction.slug !== 'workplaces' && <div className="direction-tile-top">{direction.slug !== 'servers-storage' && <span className="direction-tile-icon" aria-hidden="true"><Icon size={28} strokeWidth={2} absoluteStrokeWidth /></span>}{experienced.has(direction.slug) && <span className="direction-experience"><Check size={13} strokeWidth={2.5} aria-hidden="true" />Богатый опыт</span>}</div>}
                {direction.slug === 'servers-storage' && <div className="direction-server-art" aria-hidden="true"><img src="/images/ram-tabletop.webp" alt="" loading="lazy" width="2172" height="724" /></div>}
                {direction.slug === 'workplaces' && <div className="direction-tile-art" aria-hidden="true"><img src="/images/workplace-desk.jpg" alt="" loading="lazy" width="1400" height="934" /></div>}
                <div className="direction-tile-content"><h3><Link href={direction.url}>{direction.title}</Link></h3><p>{direction.description}</p></div>
                <span className="direction-tile-arrow" aria-hidden="true"><ArrowUpRight size={20} /></span>
            </Card>;
        })}</div>
    </section>;
}







