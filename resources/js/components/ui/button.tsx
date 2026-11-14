import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-[13px] font-medium transition-colors focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-blue-500 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                default: 'bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium',
                destructive: 'border border-red-900/60 bg-red-950/40 text-red-300 hover:bg-red-900/60 hover:text-red-200',
                outline: 'border border-zinc-800 bg-[#0f0f13] text-zinc-300 hover:border-zinc-700 hover:bg-zinc-900 hover:text-zinc-100',
                secondary: 'border border-zinc-800 bg-zinc-900 text-zinc-200 hover:bg-zinc-800 hover:text-white',
                ghost: 'text-zinc-400 hover:bg-zinc-900 hover:text-zinc-100',
                link: 'text-blue-400 underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-8 px-3.5 py-1.5',
                sm: 'h-7 rounded-md px-2.5 text-xs',
                lg: 'h-10 rounded-md px-5 text-[14px]',
                icon: 'h-8 w-8',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement>, VariantProps<typeof buttonVariants> {
    asChild?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'button';
    return <Comp className={cn(buttonVariants({ variant, size, className }))} ref={ref} {...props} />;
});
Button.displayName = 'Button';

export { Button, buttonVariants };
