import * as DarkScenes from './collection-scenes-dark';
import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { WorkplaceScene, HomeScene, MeetingScene, NetworkScene } from './collection-scenes';

export const taskCollections = [
    { title: 'Рабочее место', text: 'Компьютер, монитор и периферия — соберите всё в одном месте.', Scene: WorkplaceScene, tone: 'blue', ready: true },
    { title: 'Работа из дома', text: 'Техника для работы и связи, которая поместится дома.', Scene: HomeScene, tone: 'warm', ready: false },
    { title: 'Переговорная', text: 'Чтобы каждого участника встречи было видно и слышно.', Scene: MeetingScene, tone: 'green', ready: false },
    { title: 'Сеть для офиса', text: 'Подключение рабочих мест и стабильный Wi-Fi.', Scene: NetworkScene, tone: 'sky', ready: false },
];

export function TaskCollections({ catalogUrl, children, appearance = 'light' }: { catalogUrl: string; children?: ReactNode; appearance?: 'light' | 'dark' }) {
    return <section className="storefront-task-collections" data-appearance={appearance} aria-labelledby="task-collections-heading">
        <div className="storefront-section-heading storefront-popular-heading"><h2 id="task-collections-heading">подборки</h2></div>
        <div className="storefront-task-grid">{taskCollections.map((item, index) => {
            const DarkScene = [DarkScenes.WorkplaceScene, DarkScenes.HomeScene, DarkScenes.MeetingScene, DarkScenes.NetworkScene][index];
            const content = <><div className="storefront-task-copy"><h3>{item.title}{item.ready && <ArrowUpRight size={16} aria-hidden="true" />}</h3><p>{item.text}</p>{!item.ready && <span className="storefront-task-pending">Скоро</span>}</div><div className="storefront-task-art" aria-hidden="true">{appearance === 'dark' ? <DarkScene /> : <item.Scene />}</div></>;
            return item.ready ? <Link key={item.title} href={`${catalogUrl}?collection=workplace`} className="storefront-task-card" data-tone={item.tone}>{content}</Link>
                : <article key={item.title} className="storefront-task-card" data-tone={item.tone} data-pending="true">{content}</article>;
        })}</div>
        {children && <div className="storefront-task-brand">{children}</div>}
    </section>;
}





