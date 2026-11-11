import { Link } from '@inertiajs/react';
import { Page, Entries, RichContent, type Entry, type PageProps } from '@/components/storefront/content';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
export default function Post(props: PageProps & { post: Entry; tags: string[]; readingTime: number; related: Entry[] }) {
    return <Page {...props} actions={<Button variant="outline" asChild><Link href={props.navigation.blog}>Все статьи</Link></Button>}><article className="max-w-3xl space-y-6"><p className="text-sm text-muted-foreground">{props.post.date} · {props.readingTime} мин чтения</p>{props.post.image && <img src={props.post.image} alt={props.title} className="h-auto w-full rounded-md" />}<RichContent html={props.content} /><div className="flex flex-wrap gap-2">{props.tags.map(tag => <Badge key={tag} variant="secondary">{tag}</Badge>)}</div></article>{props.related.length > 0 && <section className="space-y-4"><h2 className="text-xl font-semibold">Читайте также</h2><Entries items={props.related} /></section>}</Page>;
}
