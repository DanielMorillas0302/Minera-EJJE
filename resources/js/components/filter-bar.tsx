import { cn } from '@/lib/utils';
import { type FormEvent, type ReactNode } from 'react';

export interface FilterBarProps {
    children: ReactNode;
    onSubmit?: (e: FormEvent) => void;
    className?: string;
}

export function FilterBar({ children, onSubmit, className }: FilterBarProps) {
    if (onSubmit) {
        return (
            <div className={cn('rounded-lg border border-zinc-800 bg-[#0f0f13] p-4', className)}>
                <form onSubmit={onSubmit} className="flex flex-wrap items-center gap-3">
                    {children}
                </form>
            </div>
        );
    }

    return (
        <div className={cn('flex flex-wrap items-center gap-3 rounded-lg border border-zinc-800 bg-[#0f0f13] p-4', className)}>
            {children}
        </div>
    );
}

export default FilterBar;

