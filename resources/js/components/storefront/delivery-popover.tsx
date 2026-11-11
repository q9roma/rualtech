import { useId, useState } from 'react';
import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { deliveryContent } from '@/lib/delivery-content';

export function DeliveryPopover({ label = 'Условия доставки', className }: { label?: string; className?: string }) {
    const [open, setOpen] = useState(false);
    const titleId = useId();
    return <Popover open={open} onOpenChange={setOpen} modal={false}>
        <PopoverTrigger asChild><Button variant="outline" className={className}>{label}</Button></PopoverTrigger>
        <PopoverContent align="end" side="top" sideOffset={12} collisionPadding={16}
            aria-labelledby={titleId}
            className="w-[360px] max-w-[calc(100vw-32px)] max-h-[var(--radix-popover-content-available-height)] overflow-y-auto rounded-2xl border-black/10 p-0 shadow-[0_8px_32px_-8px_rgba(0,0,0,0.18),0_2px_6px_rgba(0,0,0,0.04)]">
            <div className="px-5 pt-5">
            <div className="relative mb-4 flex min-h-5 items-center pr-8">
                <h2 id={titleId} className="text-sm leading-5 font-medium">Условия доставки</h2>
                <Button variant="ghost" size="icon" className="absolute -top-3 -right-3 size-11 rounded-lg text-muted-foreground hover:text-foreground" aria-label="Закрыть условия доставки" onClick={() => setOpen(false)}><X size={16} /></Button>
            </div>
            <div className="flex flex-col gap-1">
                <p className="m-0 text-xs leading-4 text-muted-foreground">{deliveryContent.localArea}</p>
                <p className="m-0 text-xl leading-6 font-semibold tracking-tight">{deliveryContent.localTime}</p>
                <p className="m-0 text-sm leading-5"><span className="font-medium text-[#22633f]">Бесплатно</span> при заказе <span className="whitespace-nowrap">от {deliveryContent.freeFrom}</span></p>
            </div>
            <div className="mt-4 border-t pt-4">
                <h3 className="text-sm leading-5 font-medium">В другие регионы</h3>
                <p className="mt-2 text-sm leading-5 text-muted-foreground">Любой транспортной компанией.<br />Сроки и стоимость уточнит менеджер.</p>
            </div>
            </div>
            <div className="relative mt-3 aspect-[9/4] overflow-hidden rounded-b-2xl" aria-hidden="true">
                <img src="/images/figma-delivery-van-full.png" alt="" width="3000" height="1872" className="pointer-events-none absolute top-0 left-1/2 w-[130%] max-w-none -translate-x-[54%] -translate-y-[26%] select-none mix-blend-multiply" />
            </div>
        </PopoverContent>
    </Popover>;
}
