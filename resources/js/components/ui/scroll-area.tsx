import * as React from 'react';
import { ScrollArea as ScrollAreaPrimitive } from 'radix-ui';
import { cn } from '@/lib/utils';

function ScrollArea({ className, children, fadeEdges = false, viewportProps, ...props }: React.ComponentProps<typeof ScrollAreaPrimitive.Root> & { fadeEdges?: boolean; viewportProps?: React.ComponentProps<typeof ScrollAreaPrimitive.Viewport> }) {
    const viewportRef = React.useRef<HTMLDivElement>(null);
    const [edges, setEdges] = React.useState({ top: false, bottom: false });
    const updateEdges = React.useCallback(() => {
        const viewport = viewportRef.current;
        if (!viewport) return;
        const top = viewport.scrollTop > 1;
        const bottom = viewport.scrollHeight - viewport.clientHeight - viewport.scrollTop > 1;
        setEdges(previous => previous.top === top && previous.bottom === bottom ? previous : { top, bottom });
    }, []);
    React.useEffect(() => {
        if (!fadeEdges) return;
        const viewport = viewportRef.current;
        if (!viewport) return;
        updateEdges();
        const observer = new ResizeObserver(updateEdges);
        observer.observe(viewport);
        if (viewport.firstElementChild) observer.observe(viewport.firstElementChild);
        return () => observer.disconnect();
    }, [fadeEdges, children, updateEdges]);
    const mask = fadeEdges ? `linear-gradient(to bottom, transparent, #000 ${edges.top ? '20px' : '0px'}, #000 calc(100% - ${edges.bottom ? '24px' : '0px'}), transparent)` : undefined;
    return <ScrollAreaPrimitive.Root data-slot="scroll-area" className={cn('relative', className)} {...props}>
        <ScrollAreaPrimitive.Viewport {...viewportProps} ref={viewportRef} onScroll={fadeEdges ? updateEdges : undefined} data-slot="scroll-area-viewport" data-fade-top={fadeEdges && edges.top} data-fade-bottom={fadeEdges && edges.bottom} style={{ ...viewportProps?.style, maskImage: mask, WebkitMaskImage: mask }} className="size-full rounded-[inherit] outline-none transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:ring-ring/50">{children}</ScrollAreaPrimitive.Viewport>
        <ScrollBar />
        <ScrollAreaPrimitive.Corner />
    </ScrollAreaPrimitive.Root>;
}

function ScrollBar({ className, orientation = 'vertical', ...props }: React.ComponentProps<typeof ScrollAreaPrimitive.ScrollAreaScrollbar>) {
    return <ScrollAreaPrimitive.ScrollAreaScrollbar data-slot="scroll-area-scrollbar" orientation={orientation} className={cn('flex touch-none select-none p-px transition-colors', orientation === 'vertical' ? 'h-full w-2.5 border-l border-l-transparent' : 'h-2.5 flex-col border-t border-t-transparent', className)} {...props}>
        <ScrollAreaPrimitive.ScrollAreaThumb data-slot="scroll-area-thumb" className="relative flex-1 rounded-full bg-border" />
    </ScrollAreaPrimitive.ScrollAreaScrollbar>;
}

export { ScrollArea, ScrollBar };
