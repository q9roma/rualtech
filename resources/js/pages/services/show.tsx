import { Link } from '@inertiajs/react';
import { Page, Entries, RichContent, type Entry, type PageProps } from '@/components/storefront/content';
import { ContactDialog } from '@/components/storefront/contact-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
export default function Service(props: PageProps & { service: Entry; category: Entry; features: string[]; related: Entry[] }) {
    return <Page {...props} breadcrumbs={[{ name: "Услуги", url: props.navigation.services }, { name: props.category.name, url: props.category.url }]} actions={<Button variant="outline" asChild><Link href={props.category.url}>{props.category.name}</Link></Button>}>
        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]"><div className="min-w-0 space-y-6"><RichContent html={props.content} />{props.features.length > 0 && <Card><CardHeader><CardTitle>Особенности</CardTitle></CardHeader><CardContent><ul className="list-disc space-y-2 pl-5">{props.features.map((feature, i) => <li key={i}>{feature}</li>)}</ul></CardContent></Card>}</div><Card><CardHeader><CardTitle>{props.service.price}</CardTitle></CardHeader><CardContent className="space-y-4"><p className="text-sm text-muted-foreground">Обсудим задачу, состав работ и сроки.</p><ContactDialog service={props.service.name} /></CardContent></Card></div>
        {props.related.length > 0 && <section className="space-y-4"><h2 className="text-xl font-semibold">Другие услуги</h2><Entries items={props.related} /></section>}
    </Page>;
}
