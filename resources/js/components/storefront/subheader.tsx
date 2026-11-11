import { Fragment, type ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { CatalogProps } from '@/types/catalog';

export type Crumb = { name: string; url: string };

export function Subheader({ title, description, navigation, breadcrumbs, actions }: Pick<CatalogProps, 'navigation'> & { title: string; description?: string | null; breadcrumbs?: Crumb[]; actions?: ReactNode }) {
    const { url, component } = usePage();
    const path = url.split('?')[0];
    const section = component.startsWith('services/') ? { name: 'Услуги', url: navigation.services }
        : component.startsWith('blog/') ? { name: 'Блог', url: navigation.blog }
        : component.startsWith('products/') ? { name: 'Каталог', url: navigation.catalog }
        : path === '/checkout' ? { name: 'Корзина', url: '/cart' }
        : component === 'commerce/order' ? { name: 'Личный кабинет', url: '/account' } : null;
    const parents = breadcrumbs ?? (section && section.url !== path ? [section] : []);
    return <div className="storefront-subheader">
        {path !== '/cart' && <nav aria-label="Хлебные крошки" className="storefront-breadcrumbs"><ol>
            <li><Link href={navigation.home}>Главная</Link></li>
            {parents.map(crumb => <Fragment key={crumb.url}><li aria-hidden="true"><ChevronRight size={12} /></li><li><Link href={crumb.url}>{crumb.name}</Link></li></Fragment>)}
        </ol></nav>}
        <div className="storefront-subheader-row"><div className="storefront-subheader-copy"><h1>{title}</h1>{description && <p>{description}</p>}</div>
            {actions && <div className="storefront-subheader-actions">{actions}</div>}
        </div>
    </div>;
}
