import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Logo } from '@/components/storefront/logo';

export function AuthForm(props: { register: boolean; home: string; onSwitch?: () => void }) {
    const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });
    const fields = props.register ? ['name', 'email', 'password', 'password_confirmation'] as const : ['email', 'password'] as const;
    const labels = { name: 'Имя', email: 'Email', password: 'Пароль', password_confirmation: 'Повторите пароль' };
    return <section className="storefront-auth" aria-labelledby="auth-title">
        <header className="storefront-auth-heading"><Link href={props.home} aria-label="Алтех — главная"><Logo /></Link><h1 id="auth-title">{props.register ? 'Создайте аккаунт Алтех' : 'Добро пожаловать в Алтех'}</h1><p>{props.register ? 'Уже есть аккаунт? ' : 'Нет аккаунта? '}{props.onSwitch ? <button type="button" className="storefront-auth-switch" onClick={props.onSwitch}>{props.register ? 'Войти' : 'Зарегистрироваться'}</button> : <Link href={props.register ? '/login' : '/register'}>{props.register ? 'Войти' : 'Зарегистрироваться'}</Link>}</p></header>
        <form className="storefront-auth-form" onSubmit={e => {
            e.preventDefault(); form.post(props.register ? '/register' : '/login', { onFinish: () => form.reset('password', 'password_confirmation') });
        }}>
            {fields.map(field => <div className="space-y-2" key={field}><Label htmlFor={field}>{labels[field]}</Label>
                <Input id={field} type={field.includes('password') ? 'password' : field === 'email' ? 'email' : 'text'} required
                    placeholder={field === 'email' ? 'name@company.ru' : undefined}
                    autoComplete={field.includes('password') ? (props.register ? 'new-password' : 'current-password') : field}
                    value={form.data[field]} onChange={e => form.setData(field, e.target.value)}
                    maxLength={field.includes('password') ? 128 : field === 'name' ? 100 : 255} disabled={form.processing} aria-describedby={form.errors[field] ? `${field}-error` : undefined} aria-invalid={!!form.errors[field]} />
                {form.errors[field] && <p id={`${field}-error`} role="alert" className="text-sm text-destructive">{form.errors[field]}</p>}</div>)}
            {props.register && <p className="text-sm text-muted-foreground">Пароль — не менее 12 символов. Гостевые заказы можно привязать после регистрации.</p>}
            <Button className="storefront-auth-submit" type="submit" disabled={form.processing}>{form.processing ? 'Подождите…' : props.register ? 'Зарегистрироваться' : 'Войти'}</Button>

        </form>
        {!props.onSwitch && <Link className="storefront-auth-back" href={props.home}>Вернуться на сайт</Link>}
        </section>;
}
