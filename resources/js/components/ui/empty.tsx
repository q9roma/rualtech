import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export function Empty({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="empty" className={cn('flex min-w-0 flex-col items-center justify-center gap-6 rounded-xl border border-dashed p-6 text-center text-balance md:p-12', className)} {...props} />;
}
export function EmptyHeader({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="empty-header" className={cn('flex max-w-sm flex-col items-center gap-2 text-center', className)} {...props} />;
}
export function EmptyMedia({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="empty-media" className={cn('mb-2 flex size-10 items-center justify-center rounded-lg bg-muted text-foreground [&_svg]:size-5', className)} {...props} />;
}
export function EmptyTitle({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="empty-title" className={cn('text-sm font-medium tracking-tight', className)} {...props} />;
}
export function EmptyDescription({ className, ...props }: ComponentProps<'p'>) {
    return <p data-slot="empty-description" className={cn('text-sm leading-relaxed text-muted-foreground', className)} {...props} />;
}
export function EmptyContent({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="empty-content" className={cn('flex w-full max-w-sm items-center justify-center gap-2 text-sm', className)} {...props} />;
}
