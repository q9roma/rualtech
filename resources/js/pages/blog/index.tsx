import { Page, Entries, Pagination, type Entry, type PageProps, type PaginationData } from '@/components/storefront/content';
export default function Blog(props: PageProps & { posts: PaginationData<Entry>; featured: Entry[] }) {
    return <Page {...props}><Entries items={props.posts.data} empty="В блоге пока нет публикаций." /><Pagination data={props.posts} />{props.posts.current_page > 1 && props.featured.length > 0 && <section className="space-y-4"><h2 className="text-xl font-semibold">Последние публикации</h2><Entries items={props.featured} /></section>}</Page>;
}
