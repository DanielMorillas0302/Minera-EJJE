import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-medium transition-colors focus:outline-hidden',
    {
        variants: {
            variant: {
                default: 'border-zinc-800 bg-zinc-900 text-zinc-300',
                secondary: 'border-zinc-800 bg-[#0f0f13] text-zinc-400',
                destructive: 'border-red-900/60 bg-red-950/40 text-red-400',
                outline: 'border-zinc-700 text-zinc-300',
                success: 'border-emerald-900/60 bg-emerald-950/40 text-emerald-400',
                warning: 'border-amber-900/60 bg-amber-950/40 text-amber-400',
                info: 'border-blue-900/60 bg-blue-950/40 text-blue-400',
                amber: 'border-amber-900/60 bg-amber-950/40 text-amber-400',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

export interface BadgeProps extends React.HTMLAttributes<HTMLDivElement>, VariantProps<typeof badgeVariants> {}

function Badge({ className, variant, ...props }: BadgeProps) {
    return <div className={cn(badgeVariants({ variant }), className)} {...props} />;
}

export { Badge, badgeVariants };
