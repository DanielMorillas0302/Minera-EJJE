import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface PageHeaderProps {
    title: string;
    description?: string;
    actions?: ReactNode;
    badge?: ReactNode;
    className?: string;
}

export function PageHeader({ title, description, actions, badge, className }: PageHeaderProps) {
    return (
        <div className={cn('flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-4', className)}>
            <div className="space-y-1">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight text-zinc-100">{title}</h1>
                    {badge && <div>{badge}</div>}
                </div>
                {description && <p className="text-[13px] leading-relaxed text-zinc-400">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}

export default PageHeader;

