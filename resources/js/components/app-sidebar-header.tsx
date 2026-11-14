import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { auth } = usePage<SharedData>().props;
    const usuario = auth?.user;

    return (
        <header className="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-zinc-800 bg-[#09090b] px-4 md:px-6">
            <div className="flex items-center gap-3">
                <SidebarTrigger className="-ml-1 text-zinc-400 hover:text-zinc-100" />
                <div className="flex items-center text-xs text-zinc-400">
                    <span className="font-medium text-zinc-500">ERP Morillas</span>
                    <span className="mx-2 text-zinc-600">&gt;</span>
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>

            {usuario && (
                <div className="flex items-center gap-3 text-xs">
                    {/* Badge Sincronizado */}
                    <div className="hidden items-center gap-2 rounded-full border border-zinc-800 bg-zinc-900 px-3 py-1 text-[12px] font-medium text-zinc-300 sm:flex">
                        <span className="size-1.5 rounded-full bg-emerald-500 animate-pulse" />
                        <span>Sincronizado</span>
                    </div>

                    {/* Sede */}
                    {usuario.sede_nombre && (
                        <span className="hidden items-center rounded-md border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-medium text-zinc-400 md:inline-flex">
                            {usuario.sede_nombre}
                        </span>
                    )}

                    {/* Botón Salir */}
                    <Link
                        method="post"
                        href={route('logout')}
                        as="button"
                        className="text-xs font-medium text-zinc-400 transition-colors hover:text-zinc-100"
                    >
                        Cerrar sesión
                    </Link>
                </div>
            )}
        </header>
    );
}
