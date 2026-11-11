import { Head } from '@inertiajs/react';
import type { PageProps } from '@/components/storefront/content';
import { AuthForm } from '@/components/storefront/auth-form';

export default function AuthPage(props: PageProps & { register: boolean }) {
    return <main className="storefront-auth-page"><Head title={props.register ? 'Регистрация' : 'Вход'}><meta name="robots" content="noindex, nofollow" /></Head><AuthForm register={props.register} home={props.navigation.home} /></main>;
}
