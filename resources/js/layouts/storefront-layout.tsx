import '../../css/storefront-card-typography.css';
import type { PropsWithChildren, ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import type { CatalogProps } from '@/types/catalog';
import { StorefrontHeader } from '@/components/storefront/header';
import { Logo } from '@/components/storefront/logo';
import { ContactDialog } from '@/components/storefront/contact-dialog';

export default function StorefrontLayout({ children, navigation, subheader }: PropsWithChildren<Pick<CatalogProps, 'navigation'> & { subheader?: ReactNode }>) {
    const { url } = usePage();
    return <>
        <a href="#main" className="sr-only focus:not-sr-only focus:p-4">Перейти к содержимому</a>
        <StorefrontHeader navigation={navigation} subheader={subheader} />
        <main id="main" tabIndex={-1} className={url.split('?')[0] === '/' ? 'storefront-home-main' : 'storefront-page-main'}>{children}</main>
        <footer id="contact" className="storefront-footer">
            <div className="storefront-footer-top">
                <Link href={navigation.home} className="storefront-footer-brand" aria-label="Алтех — главная"><Logo /><span>ИТ-решения<br />для вашего бизнеса</span></Link>
                <div className="storefront-footer-contacts"><a href="tel:+74952753533">+7 (495) 275-35-33</a><a href="mailto:office@rualtech.ru">office@rualtech.ru</a></div>
                <ContactDialog label="Написать" className="storefront-write-button storefront-footer-write" icon={<img src="/images/figma-message.svg" width="16" height="16" alt="" />} />
            </div>
            <div className="storefront-footer-bottom"><span>Алтех · Поставщик и интегратор ИТ-решений</span><nav aria-label="Информация о компании">{[{name: 'Каталог', url: navigation.catalog}, {name: 'Услуги', url: navigation.services}, {name: 'Блог', url: navigation.blog}, {name: 'Доставка', url: '/delivery'}, ...(navigation.pages ?? [])].map(link => <Link key={link.url} href={link.url}>{link.name}</Link>)}</nav></div>
        </footer>
    </>;
}
