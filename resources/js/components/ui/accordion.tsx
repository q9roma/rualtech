import * as React from 'react';
import { Accordion as Primitive } from 'radix-ui';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

const Accordion = Primitive.Root;
function AccordionItem({ className, ...props }: React.ComponentProps<typeof Primitive.Item>) {
    return <Primitive.Item data-slot="accordion-item" className={cn('border-b last:border-b-0', className)} {...props}/>;
}
function AccordionTrigger({ className, children, ...props }: React.ComponentProps<typeof Primitive.Trigger>) {
    return <Primitive.Header className="flex"><Primitive.Trigger data-slot="accordion-trigger" className={cn('flex flex-1 items-start justify-between gap-4 rounded-md py-4 text-left text-sm font-medium transition-all outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50 [&[data-state=open]>svg]:rotate-180', className)} {...props}>{children}<ChevronDown className="text-muted-foreground pointer-events-none size-4 shrink-0 translate-y-0.5 transition-transform duration-200"/></Primitive.Trigger></Primitive.Header>;
}
function AccordionContent({ className, children, ...props }: React.ComponentProps<typeof Primitive.Content>) {
    return <Primitive.Content data-slot="accordion-content" className="overflow-hidden text-sm" {...props}><div className={cn('pt-0 pb-4', className)}>{children}</div></Primitive.Content>;
}
export { Accordion, AccordionItem, AccordionTrigger, AccordionContent };
