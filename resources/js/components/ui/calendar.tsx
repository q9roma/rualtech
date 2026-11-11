import * as React from 'react';
import { DayPicker, getDefaultClassNames } from 'react-day-picker';
import { ChevronLeft, ChevronRight, ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import { buttonVariants } from '@/components/ui/button';

function Calendar({ className, classNames, showOutsideDays = true, components, ...props }: React.ComponentProps<typeof DayPicker>) {
    const defaults = getDefaultClassNames();
    return <DayPicker showOutsideDays={showOutsideDays} className={cn('w-fit bg-background p-3', className)}
        classNames={{
            root: cn('relative', defaults.root),
            months: 'relative flex flex-col gap-4 sm:flex-row',
            month: 'flex w-full flex-col gap-3',
            month_caption: 'flex h-9 items-center justify-center px-10',
            caption_label: 'text-sm font-medium capitalize',
            nav: 'absolute inset-x-0 top-0 flex items-center justify-between',
            button_previous: cn(buttonVariants({variant:'outline',size:'icon-sm'}), 'z-10'),
            button_next: cn(buttonVariants({variant:'outline',size:'icon-sm'}), 'z-10'),
            month_grid: 'w-full border-collapse',
            weekdays: 'flex',
            weekday: 'w-9 rounded-md text-center text-xs font-normal text-muted-foreground',
            week: 'mt-1 flex w-full',
            day: 'relative size-9 p-0 text-center text-sm',
            day_button: cn(buttonVariants({variant:'ghost',size:'icon'}), 'size-9 p-0 font-normal aria-selected:opacity-100'),
            selected: '[&>button]:bg-primary [&>button]:text-primary-foreground [&>button]:hover:bg-primary/90 [&>button]:hover:text-primary-foreground',
            today: '[&:not([data-selected])>button]:bg-accent [&:not([data-selected])>button]:text-accent-foreground',
            outside: 'text-muted-foreground opacity-50',
            disabled: 'text-muted-foreground opacity-40',
            hidden: 'invisible',
            ...classNames,
        }}
        components={{Chevron: ({orientation,className}) => orientation==='left' ? <ChevronLeft className={cn('size-4',className)}/> : orientation==='right' ? <ChevronRight className={cn('size-4',className)}/> : <ChevronDown className={cn('size-4',className)}/>, ...components}}
        {...props}/>;
}

export { Calendar };
