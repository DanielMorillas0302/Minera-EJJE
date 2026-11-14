import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    FileSpreadsheet,
    LayoutDashboard,
} from 'lucide-react';
import { type ReactNode } from 'react';

interface GerenciaLayoutProps {
    children: ReactNode;
    breadcrumbs: BreadcrumbItem[];
    titulo: string;
    descripcion?: string;
}

interface PestanaGerencia {
    titulo: string;
    href: string;
    recurso?: string;
    icono: typeof LayoutDashboard;
}

const PESTANAS_GERENCIA: PestanaGerencia[] = [
    { titulo: 'Dashboard Gerencial', href: '/gerencia', recurso: 'dashboard_gerencial', icono: LayoutDashboard },
    { titulo: 'Centro de Reportes', href: '/reportes', icono: FileSpreadsheet },
];

export default function GerenciaLayout({ children, breadcrumbs, titulo, descripcion }: GerenciaLayoutProps) {
    const page = usePage<SharedData>();
    const permisos = page.props.auth?.user?.permisos ?? {};
    const flashExito = page.props.flash?.exito;
    const flashError = page.props.flash?.error;
    const rutaActual = page.url.split('?')[0];

    const pestanasVisibles = PESTANAS_GERENCIA.filter((tab) => {
        if (!tab.recurso) return true;
        return (permisos[tab.recurso] ?? '').includes('L');
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 bg-[#09090b] p-6 md:p-8">
                <div className="flex flex-col gap-1 border-b border-zinc-800 pb-4">
                    <h1 className="text-2xl font-semibold tracking-tight text-zinc-100">{titulo}</h1>
                    {descripcion && <p className="text-[13px] leading-relaxed text-zinc-400">{descripcion}</p>}
                </div>

                <nav aria-label="Navegación de Gerencia y Reportes" className="flex flex-wrap gap-1.5 border-b border-zinc-800 pb-3">
                    {pestanasVisibles.map((tab) => {
                        const Icono = tab.icono;
                        const activa = rutaActual === tab.href;

                        return (
                            <Link
                                key={tab.href}
                                href={tab.href}
                                className={cn(
                                    'inline-flex items-center gap-2 rounded-md border px-3.5 py-1.5 text-[13px] font-medium transition-colors',
                                    activa
                                        ? 'border-zinc-700 bg-zinc-900 text-zinc-100'
                                        : 'border-zinc-800 bg-[#0f0f13] text-zinc-400 hover:border-zinc-700 hover:text-zinc-200',
                                )}
                            >
                                <Icono size={15} className={activa ? 'text-zinc-200' : 'text-zinc-500'} />
                                <span>{tab.titulo}</span>
                            </Link>
                        );
                    })}
                </nav>

                {flashExito && (
                    <div className="flex items-center gap-2 rounded-md border border-emerald-900/50 bg-[#0f0f13] px-4 py-2.5 text-[13px] text-emerald-400">
                        <CheckCircle2 size={16} className="text-emerald-500 shrink-0" />
                        <span>{flashExito}</span>
                    </div>
                )}

                {flashError && (
                    <div className="flex items-center gap-2 rounded-md border border-red-900/50 bg-[#0f0f13] px-4 py-2.5 text-[13px] text-red-400">
                        <AlertCircle size={16} className="text-red-500 shrink-0" />
                        <span>{flashError}</span>
                    </div>
                )}

                {children}
            </div>
        </AppLayout>
    );
}
