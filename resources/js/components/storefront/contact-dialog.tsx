import { useEffect, useRef, useState, type ReactNode, type FormEvent } from 'react';
import { usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

function BitrixForm() {
    const container = useRef<HTMLDivElement>(null);
    useEffect(() => {
        const script = document.createElement('script');
        script.dataset.b24Form = 'inline/8/f73jug';
        script.dataset.skipMoving = 'true';
        script.async = true;
        script.src = 'https://cdn-ru.bitrix24.ru/b34132196/crm/form/loader_8.js';
        const target = container.current;
        target?.appendChild(script);
        return () => { target?.replaceChildren(); };
    }, []);
    return <div ref={container} className="min-h-40"><p className="text-sm text-muted-foreground">Если форма не загрузилась, напишите на <a className="underline" href="mailto:office@rualtech.ru">office@rualtech.ru</a>.</p></div>;
}

export function ContactDialog({ service, label, secondary = false, className, icon, productId, initialMessage, onMessageChange }: { initialMessage?: string; onMessageChange?: (message: string) => void; productId?: number; service?: string; label?: string; secondary?: boolean; className?: string; icon?: ReactNode }) {
    const { contactUrl } = usePage<{ contactUrl: string }>().props;
    const [busy, setBusy] = useState(false);
    const [success, setSuccess] = useState('');
    const [error, setError] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget));
        setBusy(true); setError(''); setErrors({});
        try {
            const response = await fetch(contactUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
                body: JSON.stringify({ ...data, product_id: productId, service: service?.slice(0, 200) }),
            });
            if (response.status === 419) throw new Error('Сессия истекла. Обновите страницу и отправьте заявку снова.');
            if (response.status === 429) throw new Error('Слишком много запросов. Повторите через минуту.');
            const result = await response.json();
            if (response.status === 422) { setErrors(result.errors ?? {}); return; }
            if (!response.ok || !result.success) throw new Error('Не удалось отправить заявку. Попробуйте позже.');
            setSuccess(result.message || 'Заявка отправлена. Мы свяжемся с вами.');
        } catch (e) { setError(e instanceof Error ? e.message : 'Ошибка соединения. Попробуйте снова.'); }
        finally { setBusy(false); }
    }
    return <Dialog onOpenChange={() => { if (!busy) { setSuccess(''); setError(''); setErrors({}); } }}>
        <DialogTrigger asChild><Button className={className} variant={service && !secondary ? 'default' : 'outline'}>{icon}{label ?? (service ? 'Оставить заявку' : 'Связаться с нами')}</Button></DialogTrigger>
        <DialogContent className="max-h-[90dvh] overflow-y-auto">
            <DialogHeader><DialogTitle>{productId ? 'Запросить поставку' : service ? 'Заявка' : 'Связаться с нами'}</DialogTitle><DialogDescription>{service || 'Оставьте контакты, и мы ответим на ваш запрос.'}</DialogDescription></DialogHeader>
            {!service ? <BitrixForm /> : success ? <p role="status">{success}</p> : <form onSubmit={submit} className="space-y-4">
                {([{ name: 'name', label: 'Имя', type: 'text', required: true, max: 100, min: 2, auto: 'name' }, { name: 'email', label: 'Email', type: 'email', required: true, max: 255, auto: 'email' }, { name: 'phone', label: 'Телефон', type: 'tel', max: 20, auto: 'tel' }, { name: 'company', label: 'Компания', type: 'text', max: 100, auto: 'organization' }] as const).map(field => <div key={field.name} className="space-y-2">
                    <Label htmlFor={`request-${field.name}`}>{field.label}{'required' in field && field.required ? ' *' : ''}</Label>
                    <Input id={`request-${field.name}`} name={field.name} type={field.type} autoComplete={field.auto} required={'required' in field && field.required} minLength={'min' in field ? field.min : undefined} maxLength={field.max} disabled={busy} aria-invalid={!!errors[field.name]} aria-describedby={errors[field.name] ? `error-${field.name}` : undefined} />
                    {errors[field.name] && <p id={`error-${field.name}`} className="text-sm text-destructive" role="alert">{errors[field.name].join(' ')}</p>}
                </div>)}
                {productId && <div className="space-y-2"><Label htmlFor="request-quantity">Количество, шт.</Label><Input id="request-quantity" name="quantity" type="number" min={1} max={100000} step={1} defaultValue={1} required disabled={busy} aria-invalid={!!errors.quantity}/>{errors.quantity && <p role="alert" className="text-sm text-destructive">{errors.quantity.join(' ')}</p>}<p className="text-sm text-muted-foreground">Уточним возможность поставки, стоимость и сроки и свяжемся с вами.</p></div>}
                {errors.product_id && <p role="alert" className="text-sm text-destructive">{errors.product_id.join(' ')}</p>}
                <div className="space-y-2"><Label htmlFor="request-message">Комментарий</Label><Textarea id="request-message" name="message" defaultValue={initialMessage} onChange={event => onMessageChange?.(event.target.value)} maxLength={1000} disabled={busy} aria-invalid={!!errors.message} />{errors.message && <p role="alert" className="text-sm text-destructive">{errors.message.join(' ')}</p>}</div>
                {(error || errors.service) && <p role="alert" className="text-sm text-destructive">{error || errors.service.join(' ')}</p>}
                <Button type="submit" disabled={busy}>{busy ? 'Отправка…' : 'Отправить заявку'}</Button>
            </form>}
        </DialogContent>
    </Dialog>;
}
