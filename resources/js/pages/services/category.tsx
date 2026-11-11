import { Link } from '@inertiajs/react';
import { Page, Entries, Pagination, type Entry, type PageProps, type PaginationData } from '@/components/storefront/content';
import { Button } from '@/components/ui/button';
export default function Category(props: PageProps & { services: PaginationData<Entry> }) {
    return <Page {...props} actions={<Button variant="outline" asChild><Link href={props.navigation.services}>Все услуги</Link></Button>}><Entries items={props.services.data} empty="В этой категории пока нет услуг." /><Pagination data={props.services} /></Page>;
}
