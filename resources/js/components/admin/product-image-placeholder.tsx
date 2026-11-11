import { ImageIcon } from 'lucide-react';
import { EmptyMedia } from '@/components/ui/empty';

export function ProductImagePlaceholder() {
    return <EmptyMedia className="m-0 size-full rounded-none text-muted-foreground" role="img" aria-label="Фото отсутствует"><ImageIcon aria-hidden="true"/></EmptyMedia>;
}
