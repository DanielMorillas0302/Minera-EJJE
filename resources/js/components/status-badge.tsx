import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

export type StatusBadgeVariant = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

export interface StatusBadgeProps {
    status?: string | null;
    variant?: StatusBadgeVariant;
    label?: string;
    children?: ReactNode;
    className?: string;
}

const VARIANT_STYLES: Record<StatusBadgeVariant, string> = {
    success: 'border-emerald-900/60 bg-emerald-950/40 text-emerald-400',
    warning: 'border-amber-900/60 bg-amber-950/40 text-amber-400',
    danger: 'border-rose-900/60 bg-rose-950/40 text-rose-400',
    info: 'border-blue-900/60 bg-blue-950/40 text-blue-400',
    neutral: 'border-zinc-800 bg-zinc-900 text-zinc-400',
};

function resolverVariante(status?: string | null): StatusBadgeVariant {
    if (!status) return 'neutral';
    const s = status.toUpperCase().replace(/\s+/g, '_');

    // Success
    if (['APTO', 'ENTREGADO', 'PAGADO', 'APROBADO', 'ACTIVO', 'COMPLETADO', 'VIGENTE', 'APROBADA', 'RECEPCIONADO'].includes(s)) {
        return 'success';
    }

    // Warning
    if (['POR_VENCER', 'EN_TRANSITO', 'PENDIENTE', 'CON_RESTRICCIONES', 'EN_PROCESO', 'PARCIAL', 'EMITIDA', 'SOLICITADO'].includes(s)) {
        return 'warning';
    }

    // Danger
    if (['VENCIDO', 'NO_APTO', 'RECHAZADO', 'RECHAZADA', 'CANCELADO', 'CANCELADA', 'INACTIVO', 'SIN_EXAMEN', 'RETRASADO', 'ANULADO', 'CESADO'].includes(s)) {
        return 'danger';
    }

    // Info
    if (['TRUJILLO', 'PATAZ', 'INFO', 'REVISION'].includes(s)) {
        return 'info';
    }

    return 'neutral';
}

export function StatusBadge({ status, variant, label, children, className }: StatusBadgeProps) {
    const finalVariant = variant ?? resolverVariante(status);
    const displayText = label ?? children ?? status ?? '';

    return (
        <span
            className={cn(
                'inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-medium tracking-tight',
                VARIANT_STYLES[finalVariant],
                className,
            )}
        >
            {displayText}
        </span>
    );
}

export default StatusBadge;

