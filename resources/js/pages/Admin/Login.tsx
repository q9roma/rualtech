import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card } from '@/components/ui/card';
import { CheckBox } from '@/components/admin/controls';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });
    return <main className="admin-login"><Head title="Вход"/><Card className="admin-login-card"><div className="admin-brand"><span className="admin-brand-mark">a</span><span>Алтех</span></div><div><h1>Вход в управление</h1></div><form onSubmit={e => { e.preventDefault(); form.post('/admin/login', { onFinish: () => form.reset('password') }); }} className="space-y-4"><div className="space-y-2"><Label htmlFor="email">Email</Label><Input id="email" type="email" autoComplete="username" required autoFocus value={form.data.email} onChange={e => form.setData('email', e.target.value)} aria-invalid={!!form.errors.email}/>{form.errors.email && <p role="alert" className="admin-error">{form.errors.email}</p>}</div><div className="space-y-2"><Label htmlFor="password">Пароль</Label><Input id="password" type="password" autoComplete="current-password" required value={form.data.password} onChange={e => form.setData('password', e.target.value)}/>{form.errors.password && <p role="alert" className="admin-error">{form.errors.password}</p>}</div><label className="flex items-center gap-2 text-sm"><CheckBox label="Запомнить меня" checked={form.data.remember} onChange={v => form.setData('remember', v)}/>Запомнить меня</label><Button type="submit" className="w-full" disabled={form.processing}>{form.processing ? 'Входим…' : 'Войти'}</Button></form><a href="/" className="text-center text-sm text-muted-foreground">Вернуться на сайт ↗</a></Card></main>;
}
