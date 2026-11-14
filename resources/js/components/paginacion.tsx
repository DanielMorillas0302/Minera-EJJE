import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';

export interface PaginacionLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginado<T> {
    data: T[];
    links: PaginacionLink[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
}

interface PaginacionProps<T> {
    datos: Paginado<T>;
}

function traducirEtiqueta(etiqueta: string): string {
    if (etiqueta.includes('Previous')) return 'Anterior';
    if (etiqueta.includes('Next')) return 'Siguiente';
    return etiqueta.replace(/&laquo;|&raquo;/g, '').trim();
}

export function Paginacion<T>({ datos }: PaginacionProps<T>) {
    if (datos.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col items-center justify-between gap-3 border-t border-zinc-800 bg-[#0f0f13] px-4 py-3 text-[13px] text-zinc-400 sm:flex-row">
            <p>
                Mostrando <span className="font-medium text-zinc-200 tabular-nums">{datos.from ?? 0}</span> a{' '}
                <span className="font-medium text-zinc-200 tabular-nums">{datos.to ?? 0}</span> de{' '}
                <span className="font-medium text-zinc-200 tabular-nums">{datos.total}</span> registros
            </p>

            {datos.last_page > 1 && (
                <nav aria-label="Paginación" className="flex flex-wrap items-center gap-1">
                    {datos.links.map((link, idx) => {
                        const etiqueta = traducirEtiqueta(link.label);
                        if (!link.url) {
                            return (
                                <span
                                    key={`${etiqueta}-${idx}`}
                                    className="inline-flex min-h-8 items-center rounded-md border border-zinc-800 bg-zinc-900/50 px-3 text-xs text-zinc-600"
                                >
                                    {etiqueta}
                                </span>
                            );
                        }

                        return (
                            <Link
                                key={`${etiqueta}-${idx}`}
                                href={link.url}
                                preserveScroll
                                preserveState
                                className={cn(
                                    'inline-flex min-h-8 items-center rounded-md border px-3 text-xs font-medium transition-colors',
                                    link.active
                                        ? 'border-zinc-700 bg-zinc-100 text-zinc-900 font-semibold'
                                        : 'border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-white',
                                )}
                            >
                                {etiqueta}
                            </Link>
                        );
                    })}
                </nav>
            )}
        </div>
    );
}

export default Paginacion;
