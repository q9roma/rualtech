import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { AuthForm } from '@/components/storefront/auth-form';

export function AccountButton({ enabled, home }: { enabled: boolean; home: string }) {
    const { authenticated } = usePage<{ authenticated: boolean }>().props;
    const [open, setOpen] = useState(false);
    const [register, setRegister] = useState(false);
    const label = <><span className="storefront-account-icon"><img src="/images/figma-account.svg" alt="" /></span>Кабинет</>;
    if (authenticated && enabled) return <Button variant="outline" className="storefront-account-button" asChild><Link href="/account">{label}</Link></Button>;
    return <Dialog open={open} onOpenChange={value => { setOpen(value); if (!value) setRegister(false); }}>
        <DialogTrigger asChild><Button disabled={!enabled} variant="outline" className="storefront-account-button">{label}</Button></DialogTrigger>
        <DialogContent className="storefront-auth-modal" aria-describedby={undefined}>
            <DialogTitle className="sr-only">{register ? 'Регистрация' : 'Вход в кабинет'}</DialogTitle>
            <AuthForm key={register ? 'register' : 'login'} register={register} home={home} onSwitch={() => setRegister(value => !value)} />
        </DialogContent>
    </Dialog>;
}
