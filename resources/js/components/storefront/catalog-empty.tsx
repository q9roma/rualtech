import { Package } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle, EmptyDescription, EmptyContent } from '@/components/ui/empty';

export function CatalogEmpty({ onReset, loading = false }: { onReset?: () => void; loading?: boolean }) {
    return <Empty className="storefront-category-empty">
        <EmptyHeader>
            <EmptyMedia><Package aria-hidden="true" /></EmptyMedia>
            <EmptyTitle>Товары пока не добавлены</EmptyTitle>
            <EmptyDescription>В этом разделе пока нет позиций каталога.</EmptyDescription>
        </EmptyHeader>
        {onReset && <EmptyContent><Button variant="outline" size="sm" onClick={onReset} disabled={loading}>Все категории</Button></EmptyContent>}
    </Empty>;
}
