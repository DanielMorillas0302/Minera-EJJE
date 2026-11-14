import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Wrench } from 'lucide-react';

interface ModuloPendienteProps {
    titulo: string;
    fase: string;
    descripcion: string;
    ruta: string;
}

export default function ModuloEnConstruccion({ titulo, fase, descripcion, ruta }: ModuloPendienteProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Inicio', href: '/dashboard' },
        { title: titulo, href: ruta },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={titulo} />
            <div className="flex flex-1 flex-col items-center justify-center bg-[#09090b] p-6 md:p-8">
                <div className="w-full max-w-xl rounded-lg border border-zinc-800 bg-[#0f0f13] p-6 text-center md:p-8">
                    <div className="mx-auto inline-flex items-center gap-2 rounded-full border border-amber-900/60 bg-amber-950/40 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-amber-400">
                        <Wrench size={12} />
                        Planificado en {fase}
                    </div>

                    <h1 className="mt-4 text-2xl font-semibold tracking-tight text-zinc-100">{titulo}</h1>
                    <p className="mt-2 text-[13px] leading-relaxed text-zinc-400">{descripcion}</p>

                    <div className="mt-6 rounded-md border border-zinc-800 bg-zinc-900/50 p-4 text-left text-[13px] leading-relaxed text-zinc-300">
                        Tu rol cuenta con permiso de acceso verificado para este módulo. La funcionalidad completa se implementará siguiendo el orden estricto de fases del plan de desarrollo del sistema minero.
                    </div>

                    <div className="mt-6 flex justify-center">
                        <Link
                            href="/dashboard"
                            className="inline-flex items-center gap-2 rounded-md bg-zinc-100 px-4 py-2 text-[13px] font-medium text-zinc-900 transition-colors hover:bg-white"
                        >
                            <ArrowLeft size={14} />
                            Volver al Resumen Ejecutivo
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
