import * as React from 'react';
import { Slider as SliderPrimitive } from 'radix-ui';
import { cn } from '@/lib/utils';

export function Slider({className, value, defaultValue = [0], min = 0, max = 100, 'aria-label': label, ...props}: React.ComponentProps<typeof SliderPrimitive.Root>) {
    return <SliderPrimitive.Root data-slot="slider" className={cn('relative flex w-full touch-none items-center select-none data-[disabled]:opacity-50', className)} min={min} max={max} value={value} defaultValue={defaultValue} {...props}>
        <SliderPrimitive.Track className="relative h-1.5 w-full grow overflow-hidden rounded-full bg-muted">
            <SliderPrimitive.Range className="absolute h-full bg-primary"/>
        </SliderPrimitive.Track>
        {(value ?? defaultValue).map((_, index) => <SliderPrimitive.Thumb key={index} aria-label={label} className="block size-4 shrink-0 rounded-full border border-primary bg-background shadow-sm transition-shadow hover:ring-4 hover:ring-ring/20 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-ring/50 disabled:pointer-events-none"/>)}
    </SliderPrimitive.Root>;
}
