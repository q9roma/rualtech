import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card } from '@/components/ui/card';

export default function ResetPassword({email,token}: {email:string;token:string}) {
    const form=useForm({email,token,password:'',password_confirmation:''});
    return <main className="admin-login"><Head title="Новый пароль"><meta name="robots" content="noindex,nofollow"/><meta name="referrer" content="no-referrer"/></Head><Card className="admin-login-card"><h1>Задайте новый пароль</h1><p className="text-sm text-muted-foreground">Локальный стенд Алтех · {email}<br/>Не менее 12 символов. Ссылка одноразовая и действует 60 минут.</p><form className="space-y-5" onSubmit={event=>{event.preventDefault();form.post('/admin/local-reset-password',{onFinish:()=>form.reset('password','password_confirmation')});}}><div className="space-y-2"><Label htmlFor="new-password">Новый пароль</Label><Input id="new-password" type="password" autoComplete="new-password" minLength={12} maxLength={128} required value={form.data.password} onChange={event=>form.setData('password',event.target.value)}/></div><div className="space-y-2"><Label htmlFor="confirm-password">Повторите пароль</Label><Input id="confirm-password" type="password" autoComplete="new-password" minLength={12} maxLength={128} required value={form.data.password_confirmation} onChange={event=>form.setData('password_confirmation',event.target.value)}/></div>{Object.values(form.errors).map((error,index)=><p role="alert" className="admin-error" key={index}>{error}</p>)}<Button type="submit" disabled={form.processing}>Сохранить пароль</Button></form></Card></main>;
}
