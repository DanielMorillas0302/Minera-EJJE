import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

export interface DataTableCardProps {
    title?: string;
    description?: string;
    count?: number;
    actions?: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    className?: string;
}

export function DataTableCard({
    title,
    description,
    count,
    actions,
    children,
    footer,
    className,
}: DataTableCardProps) {
    const hasHeader = Boolean(title || actions || count !== undefined);

    return (
        <div className={cn('overflow-hidden rounded-lg border border-zinc-800 bg-[#0f0f13]', className)}>
            {hasHeader && (
                <div className="flex flex-col gap-2 border-b border-zinc-800 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            {title && <h3 className="text-[14px] font-medium text-zinc-100">{title}</h3>}
                            {count !== undefined && (
                                <span className="rounded border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-medium text-zinc-400 tabular-nums">
                                    {count} {count === 1 ? 'registro' : 'registros'}
                                </span>
                            )}
                        </div>
                        {description && <p className="text-[12px] text-zinc-400 mt-0.5">{description}</p>}
                    </div>
                    {actions && <div className="flex items-center gap-2">{actions}</div>}
                </div>
            )}

            <div className="overflow-x-auto">{children}</div>

            {footer && <div className="border-t border-zinc-800 bg-[#0f0f13]">{footer}</div>}
        </div>
    );
}

export function DataTableThead({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <thead className={cn('border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase tracking-wider text-zinc-400', className)}>
            {children}
        </thead>
    );
}

export function DataTableTbody({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <tbody className={cn('divide-y divide-zinc-800/60 text-[13px] text-zinc-300', className)}>
            {children}
        </tbody>
    );
}

export function DataTableTr({ children, className, onClick }: { children: ReactNode; className?: string; onClick?: () => void }) {
    return (
        <tr
            onClick={onClick}
            className={cn('transition-colors hover:bg-zinc-900/40', onClick && 'cursor-pointer', className)}
        >
            {children}
        </tr>
    );
}

export function DataTableTh({ children, className }: { children: ReactNode; className?: string }) {
    return <th className={cn('px-4 py-3 text-left font-medium', className)}>{children}</th>;
}

export function DataTableTd({ children, className }: { children: ReactNode; className?: string }) {
    return <td className={cn('px-4 py-3 text-zinc-300', className)}>{children}</td>;
}

export function DataTableEmpty({ colSpan, mensaje = 'No se encontraron registros.' }: { colSpan: number; mensaje?: string }) {
    return (
        <tr>
            <td colSpan={colSpan} className="px-4 py-12 text-center text-[13px] text-zinc-500">
                {mensaje}
            </td>
        </tr>
    );
}

export default DataTableCard;

