import * as React from 'react';
import { DropdownMenu as Primitive } from 'radix-ui';
import { cn } from '@/lib/utils';

const DropdownMenu = Primitive.Root;
const DropdownMenuTrigger = Primitive.Trigger;
const DropdownMenuPortal = Primitive.Portal;
function DropdownMenuContent({ className, sideOffset = 4, ...props }: React.ComponentProps<typeof Primitive.Content>) {
    return <Primitive.Content data-slot="dropdown-menu-content" sideOffset={sideOffset} className={cn('bg-popover text-popover-foreground z-50 max-h-[var(--radix-dropdown-menu-content-available-height)] min-w-32 overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md', className)} {...props}/>;
}
function DropdownMenuItem({ className, ...props }: React.ComponentProps<typeof Primitive.Item>) {
    return <Primitive.Item data-slot="dropdown-menu-item" className={cn('focus:bg-accent focus:text-accent-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0', className)} {...props}/>;
}
function DropdownMenuCheckboxItem({ className, ...props }: React.ComponentProps<typeof Primitive.CheckboxItem>) {
    return <Primitive.CheckboxItem data-slot="dropdown-menu-checkbox-item" className={cn('focus:bg-accent focus:text-accent-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50', className)} {...props}/>;
}
function DropdownMenuSeparator({ className, ...props }: React.ComponentProps<typeof Primitive.Separator>) {
    return <Primitive.Separator className={cn('bg-border -mx-1 my-1 h-px', className)} {...props}/>;
}
export { DropdownMenu, DropdownMenuTrigger, DropdownMenuPortal, DropdownMenuContent, DropdownMenuItem, DropdownMenuCheckboxItem, DropdownMenuSeparator };
