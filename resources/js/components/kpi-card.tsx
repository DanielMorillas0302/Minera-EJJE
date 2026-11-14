import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { AlertTriangle, type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

export interface KpiCardProps {
    title: string;
    value: string | number;
    icon?: LucideIcon;
    badge?: string | ReactNode;
    variant?: 'default' | 'alert' | 'success' | 'info';
    action?: {
        label: string;
        href?: string;
        onClick?: () => void;
    };
    href?: string;
    className?: string;
    subtitle?: string;
}

export function KpiCard({
    title,
    value,
    icon: Icon,
    badge,
    variant = 'default',
    action,
    href,
    className,
    subtitle,
}: KpiCardProps) {
    const isAlert = variant === 'alert';

    const cardContent = (
        <div
            className={cn(
                'flex h-[120px] flex-col justify-between rounded-lg border bg-[#0f0f13] p-5 transition-colors',
                isAlert
                    ? 'border-amber-500/40 hover:border-amber-500/60'
                    : 'border-zinc-800 hover:border-zinc-700',
                className,
            )}
        >
            {/* Fila superior: Título e Ícono */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-1.5">
                    <span className={cn('text-[13px] font-medium', isAlert ? 'text-zinc-200' : 'text-zinc-300')}>
                        {title}
                    </span>
                    {isAlert && <AlertTriangle size={14} className="text-amber-500" />}
                </div>
                {Icon && !isAlert && <Icon size={16} className="text-zinc-500" />}
            </div>

            {/* Fila inferior: Valor y Badge/Acción */}
            <div className="flex items-baseline justify-between gap-2">
                <div className="flex flex-col">
                    <span
                        className={cn(
                            'text-2xl lg:text-3xl font-semibold tracking-tight tabular-nums',
                            isAlert ? 'text-amber-500' : 'text-zinc-100',
                        )}
                    >
                        {value}
                    </span>
                    {subtitle && <span className="text-[11px] text-zinc-500">{subtitle}</span>}
                </div>

                {/* Acción (ej. botón "Revisar") */}
                {action && (
                    action.href ? (
                        <Link
                            href={action.href}
                            className={cn(
                                'rounded px-2.5 py-1 text-xs font-medium transition-colors',
                                isAlert
                                    ? 'border border-amber-500/30 bg-amber-500/10 text-amber-400 hover:bg-amber-500/20'
                                    : 'border border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-white',
                            )}
                        >
                            {action.label}
                        </Link>
                    ) : (
                        <button
                            type="button"
                            onClick={action.onClick}
                            className={cn(
                                'rounded px-2.5 py-1 text-xs font-medium transition-colors',
                                isAlert
                                    ? 'border border-amber-500/30 bg-amber-500/10 text-amber-400 hover:bg-amber-500/20'
                                    : 'border border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-white',
                            )}
                        >
                            {action.label}
                        </button>
                    )
                )}

                {/* Badge (ej. "Pataz", "Unidades", "Mes actual") */}
                {!action && badge && (
                    typeof badge === 'string' ? (
                        <span className="rounded border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-medium text-zinc-400">
                            {badge}
                        </span>
                    ) : (
                        badge
                    )
                )}
            </div>
        </div>
    );

    if (href && !action) {
        return (
            <Link href={href} className="block">
                {cardContent}
            </Link>
        );
    }

    return cardContent;
}

export default KpiCard;

