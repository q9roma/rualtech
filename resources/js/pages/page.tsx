import { Page, RichContent, type PageProps } from '@/components/storefront/content';
export default function CmsPage(props: PageProps & { updatedAt: string | null }) {
    return <Page {...props}><article className="max-w-3xl space-y-6"><RichContent html={props.content} />{props.updatedAt && <p className="text-sm text-muted-foreground">Обновлено: {props.updatedAt}</p>}</article></Page>;
}
