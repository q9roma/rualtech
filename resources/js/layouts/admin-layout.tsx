import { useEffect, useState, type ReactNode } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Bell, BookOpen, Package, FolderTree, ListTree, Workflow, FileSpreadsheet, ChevronsUpDown, ChevronRight, CircleHelp, ExternalLink, FileText, House, LogOut, Mail, Moon,  Plus, Search, ShoppingBag, Sun, Wrench } from 'lucide-react';
import { Sidebar, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupContent, SidebarGroupLabel, SidebarHeader, SidebarInset, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarProvider, SidebarRail, SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Toaster } from '@/components/ui/sonner';
import { AdminPageHeader } from '@/components/admin/page-header';
import type { AdminShared } from '@/components/admin/types';

const icons: Record<string, typeof House> = { products: Package, 'product-categories': FolderTree, 'catalog-mappings': Workflow, 'catalog-prices': FileSpreadsheet, 'catalog-photo-queue': Workflow, orders: ShoppingBag, 'contact-requests': Mail, services: Wrench, 'service-categories': ListTree, 'blog-posts': BookOpen, pages: FileText };

type LayoutProps = { headingTitle?: string; description?: ReactNode; navigation?: ReactNode; footer?:ReactNode; workspaceScroll?:boolean; title: string; children: ReactNode; actions?: ReactNode; back?: { href: string; label: string } };
export default function AdminLayout(props: LayoutProps) { return <SidebarProvider className={props.workspaceScroll ? 'admin-app-shell admin-workspace-shell' : 'admin-app-shell'}><AdminLayoutContent {...props}/></SidebarProvider>; }
function AdminLayoutContent({ title, headingTitle, description, navigation, children, actions, back, footer, workspaceScroll=false }: LayoutProps) {
    const { props, url } = usePage<AdminShared>();
    const { admin, flash } = props;
    const { setOpenMobile } = useSidebar();
    const [dark, setDark] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [help, setHelp] = useState(false);
    useEffect(() => { setDark(document.documentElement.classList.contains('dark')); }, []);
    useEffect(() => { setOpenMobile(false); }, [url, setOpenMobile]);
    useEffect(() => { const listener = (e: KeyboardEvent) => { if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); setSearchOpen(v => !v); } }; window.addEventListener('keydown', listener); return () => window.removeEventListener('keydown', listener); }, []);
    const groups = [
        { label: 'Каталог и продажи', keys: ['products', 'product-categories', 'catalog-mappings', 'catalog-prices', 'catalog-photo-queue', 'orders', 'contact-requests'] },
        { label: 'Сайт и контент', keys: ['services', 'service-categories', 'blog-posts', 'pages'] },
    ];
    const isActive = (key: string) => url.split('?')[0] === '/admin/'+key || url.startsWith('/admin/'+key+'/');
    const navItem = (item: AdminShared['admin']['navigation'][number]) => { const Icon = icons[item.key] ?? FileText; return <SidebarMenuItem key={item.key}><SidebarMenuButton asChild isActive={isActive(item.key)} tooltip={item.label}><Link href={item.url} aria-current={isActive(item.key) ? 'page' : undefined}><Icon/><span>{item.label}</span></Link></SidebarMenuButton></SidebarMenuItem>; };
    const pageChrome = <div className="admin-page-chrome"><header className="admin-topbar"><SidebarTrigger aria-label="Переключить меню"/><span className="admin-topbar-divider" aria-hidden="true"/><nav className="admin-breadcrumbs" aria-label="Навигационная цепочка"><Link href="/admin">Управление</Link><ChevronRight size={14} aria-hidden="true"/><span aria-current="page">{title}</span></nav><div className="admin-topbar-actions"><Button variant="outline" className="md:hidden" onClick={()=>setSearchOpen(true)} aria-label="Поиск по админке"><Search/><span className="hidden sm:inline">Поиск</span></Button><Button variant="ghost" size="icon" aria-label={dark ? 'Светлая тема' : 'Тёмная тема'} onClick={() => { const next = !dark; setDark(next); document.documentElement.classList.toggle('dark', next); try { localStorage.setItem('admin-theme', next ? 'dark' : 'light'); } catch {} }}>{dark ? <Sun/> : <Moon/>}</Button></div></header><AdminPageHeader title={headingTitle ?? title} description={description} back={back} actions={actions} navigation={navigation}/></div>;
    return <><Toaster theme={dark ? 'dark' : 'light'} position="top-right" closeButton duration={4000}/><Head title={title}/><a href="#admin-content" className="admin-skip">К содержимому</a>
        <Sidebar collapsible="icon" variant="floating">
            <SidebarHeader>
                <Link href="/admin" aria-label="Алтех — главная" className="admin-sidebar-brand flex h-12 items-center gap-2 overflow-hidden rounded-lg">
                    <img src="/images/figma-logo-light.svg" alt="Алтех" width={32} height={32} className="size-8 shrink-0 dark:hidden"/>
                    <img src="/images/figma-logo-dark.svg" alt="Алтех" width={32} height={32} className="hidden size-8 shrink-0 dark:block"/>
                    <span className="admin-sidebar-brand-copy grid shrink-0 whitespace-nowrap text-sm leading-5"><span className="font-semibold">Алтех</span><span className="text-xs">Управление сайтом</span></span>
                </Link>
                <SidebarMenu><SidebarMenuItem><SidebarMenuButton onClick={()=>{setOpenMobile(false);setSearchOpen(true);}} tooltip="Поиск · Ctrl K" aria-label="Поиск по админке" className="border border-input text-muted-foreground"><Search/><span>Поиск</span><kbd className="ml-auto text-xs group-data-[collapsible=icon]:hidden">Ctrl K</kbd></SidebarMenuButton></SidebarMenuItem></SidebarMenu>
            </SidebarHeader>
            <SidebarContent><SidebarGroup><SidebarGroupContent><SidebarMenu><SidebarMenuItem><SidebarMenuButton asChild tooltip="Обзор" isActive={url.split('?')[0] === '/admin'}><Link href="/admin"><House/><span>Обзор</span></Link></SidebarMenuButton></SidebarMenuItem></SidebarMenu></SidebarGroupContent></SidebarGroup>
            {groups.map(group => <SidebarGroup key={group.label}><SidebarGroupLabel>{group.label}</SidebarGroupLabel><SidebarGroupContent><SidebarMenu>{admin?.navigation.filter(item => group.keys.includes(item.key)).map(navItem)}</SidebarMenu></SidebarGroupContent></SidebarGroup>)}
            {admin?.navigation.some(item => !groups.some(group => group.keys.includes(item.key))) && <SidebarGroup><SidebarGroupContent><SidebarMenu>{admin.navigation.filter(item => !groups.some(group => group.keys.includes(item.key))).map(navItem)}</SidebarMenu></SidebarGroupContent></SidebarGroup>}
            </SidebarContent>
            <SidebarFooter><SidebarMenu><SidebarMenuItem><DropdownMenu><DropdownMenuTrigger asChild><SidebarMenuButton size="lg" tooltip={admin?.name || 'Аккаунт'} className="data-[state=open]:bg-sidebar-accent"><span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-sm font-medium">{(admin?.name || 'А').slice(0,2).toUpperCase()}</span><span className="grid flex-1 text-left text-sm leading-5"><span className="truncate font-semibold">{admin?.name}</span><span className="text-xs">Администратор</span></span><ChevronsUpDown className="ml-auto size-4"/></SidebarMenuButton></DropdownMenuTrigger><DropdownMenuContent side="right" align="end" sideOffset={4} className="w-56"><DropdownMenuItem onSelect={()=>setSearchOpen(true)}><Search/>Поиск · Ctrl K</DropdownMenuItem><DropdownMenuItem asChild><Link href="/admin/notifications"><Bell/>Уведомления{admin?.unread ? ' · '+admin.unread : ''}</Link></DropdownMenuItem><DropdownMenuItem onSelect={()=>setHelp(true)}><CircleHelp/>Помощь</DropdownMenuItem><DropdownMenuItem asChild><a href="/" target="_blank" rel="noreferrer"><ExternalLink/>Открыть сайт</a></DropdownMenuItem><DropdownMenuSeparator/><DropdownMenuItem onSelect={()=>router.post('/admin/logout')}><LogOut/>Выйти</DropdownMenuItem></DropdownMenuContent></DropdownMenu></SidebarMenuItem></SidebarMenu></SidebarFooter><SidebarRail/>
        </Sidebar>
        <SidebarInset className={workspaceScroll ? "admin-workspace-inset min-w-0" : "min-w-0"}>
        {workspaceScroll ? <ScrollArea className="admin-workspace-scroll" type="always" viewportProps={{'scroll-region':'', 'aria-label':'Рабочая область',tabIndex:0} as React.ComponentProps<typeof ScrollArea>['viewportProps']}>{pageChrome}<main id="admin-content" className="admin-content">{flash?.success && <div role="status" className="admin-flash" key={url+flash.success}>{flash.success}</div>}{children}</main></ScrollArea> : <>{pageChrome}<main id="admin-content" className="admin-content">{flash?.success && <div role="status" className="admin-flash" key={url+flash.success}>{flash.success}</div>}{children}</main></>}{footer && <div className="admin-workspace-footer">{footer}</div>}</SidebarInset>
        <Dialog open={searchOpen} onOpenChange={setSearchOpen}><DialogContent><DialogHeader><DialogTitle>Поиск в управлении</DialogTitle><DialogDescription>Найдите раздел или запись по названию, артикулу и контактам.</DialogDescription></DialogHeader><Input autoFocus value={search} onChange={e => setSearch(e.target.value)} placeholder="Что найти?" aria-label="Поисковый запрос"/><div className="grid gap-1">{admin?.navigation.map(item => <Link key={item.key} className="flex items-center gap-2 rounded-md p-2 text-sm hover:bg-accent" href={`${item.url}${search ? '?search='+encodeURIComponent(search) : ''}`} onClick={() => setSearchOpen(false)}><Search/>{search ? `Искать «${search}»: ` : ''}{item.label}<ChevronRight className="ml-auto"/></Link>)}</div></DialogContent></Dialog>
        <Dialog open={help} onOpenChange={setHelp}><DialogContent><DialogHeader><DialogTitle>Работа с админкой</DialogTitle><DialogDescription>Все изменения сохраняются в действующей базе сайта.</DialogDescription></DialogHeader><div className="space-y-3 text-sm"><p>Поиск и фильтры находятся над каждой таблицей. В меню «Колонки» можно выбрать видимые поля.</p><p>Для массового удаления отметьте строки. Обновление прайса доступно в разделе «Обновить прайс»: загрузите Excel, проверьте изменения и примените обновление.</p><p>Статусы заказов меняются в карточке заказа с обязательным комментарием. История изменений сохраняется.</p></div></DialogContent></Dialog>
    </>;
}
