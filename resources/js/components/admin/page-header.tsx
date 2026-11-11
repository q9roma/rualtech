import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function AdminPageHeader({ title, description, back, actions, navigation }: {
    title: string;
    description?: ReactNode;
    back?: { href: string; label: string };
    actions?: ReactNode;
    navigation?: ReactNode;
}) {
    return <header className="admin-page-heading">
        <div className="admin-page-heading-main">
            {back && <Button asChild variant="ghost" size="icon" className="admin-page-back"><Link href={back.href} aria-label={back.label} title={back.label}><ArrowLeft/></Link></Button>}
            <div className="admin-page-heading-copy"><h1>{title}</h1>{description && <div className="admin-page-description">{description}</div>}</div>
        </div>
        {actions && <div className="admin-page-heading-actions">{actions}</div>}
        {navigation && <div className="admin-page-heading-navigation">{navigation}</div>}
    </header>;
}
