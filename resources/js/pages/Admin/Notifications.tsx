import { Empty, EmptyHeader, EmptyMedia, EmptyTitle, EmptyDescription } from '@/components/ui/empty';
import { Link, router } from '@inertiajs/react';
import { Bell, Check, CheckCheck, Download, X } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Pager, date } from '@/components/admin/controls';
import type { Pagination } from '@/components/admin/types';

type Notification={id:string;title:string;body:string;created_at:string;read_at:string|null;report:string|null};
export default function Notifications({notifications}: {notifications:Pagination<Notification>}) {
    return <AdminLayout title="Уведомления" actions={<Button variant="outline" disabled={!notifications.total} onClick={()=>router.post('/admin/notifications/read-all')}><CheckCheck/>Отметить все прочитанными</Button>}><div className="admin-notification-list">{notifications.data.map(n=><section key={n.id} className={`admin-notification ${!n.read_at?'admin-notification-unread':''}`}><Bell size={18}/><div className="min-w-0 flex-1"><h2>{n.title}</h2><p className="mt-1 whitespace-pre-wrap text-muted-foreground">{n.body}</p><time className="mt-2 block text-xs text-muted-foreground">{date(n.created_at)}</time>{n.report&&<Button asChild variant="outline" className="mt-4"><a href={n.report}><Download/>CSV с ошибками</a></Button>}</div>{!n.read_at&&<Button variant="ghost" size="icon" aria-label="Отметить прочитанным" onClick={()=>router.post(`/admin/notifications/${n.id}/read`,{},{preserveScroll:true})}><Check/></Button>}<Button variant="ghost" size="icon" aria-label="Удалить уведомление" onClick={()=>router.delete(`/admin/notifications/${n.id}`,{preserveScroll:true})}><X/></Button></section>)}{!notifications.data.length&&<Empty><EmptyHeader><EmptyMedia><Bell/></EmptyMedia><EmptyTitle>Пока нет уведомлений</EmptyTitle><EmptyDescription>Здесь появятся результаты импорта и сообщения системы.</EmptyDescription></EmptyHeader></Empty>}</div>{notifications.last_page>1 && <Pager data={notifications}/>}</AdminLayout>;
}
