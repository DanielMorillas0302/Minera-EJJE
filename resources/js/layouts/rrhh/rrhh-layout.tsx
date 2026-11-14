import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    CalendarCheck2,
    FileSpreadsheet,
    FileUp,
    HeartPulse,
    LayoutDashboard,
    ShieldCheck,
    Users,
} from 'lucide-react';

interface RrhhLayoutProps {
    titulo: string;
    descripcion?: string;
    breadcrumbs: BreadcrumbItem[];
    acciones?: React.ReactNode;
    children: React.ReactNode;
}

interface PestanaRrhh {
    titulo: string;
    href: string;
    icono: typeof LayoutDashboard;
    exacto?: boolean;
    recurso?: string;
}

const PESTAÑAS: PestanaRrhh[] = [
    { titulo: 'Resumen y Alertas', href: '/rrhh', icono: LayoutDashboard, exacto: true },
    { titulo: 'Trabajadores', href: '/rrhh/trabajadores', icono: Users, recurso: 'trabajadores' },
    { titulo: 'Exámenes Ocupacionales', href: '/rrhh/examenes', icono: HeartPulse, recurso: 'examenes' },
    { titulo: 'Vacaciones', href: '/rrhh/vacaciones', icono: CalendarCheck2, recurso: 'vacaciones_permisos' },
    { titulo: 'Permisos', href: '/rrhh/permisos', icono: ShieldCheck, recurso: 'vacaciones_permisos' },
    { titulo: 'Movimientos Trujillo ↔ Pataz', href: '/rrhh/movimientos', icono: ArrowLeftRight, recurso: 'movimientos_personal' },
    { titulo: 'Reportes RR.HH.', href: '/rrhh/reportes', icono: FileSpreadsheet },
    { titulo: 'Importar Excel', href: '/importaciones?tipo=trabajadores', icono: FileUp, recurso: 'importar_rrhh' },
];

export default function RrhhLayout({ titulo, descripcion, breadcrumbs, acciones, children }: RrhhLayoutProps) {
    const page = usePage<SharedData>();
    const rutaActual = page.url.split('?')[0];
    const permisos = page.props.auth?.user?.permisos ?? {};

    const pestanasVisibles = PESTAÑAS.filter((item) => {
        if (item.href === '/rrhh/reportes') {
            return Boolean(permisos.trabajadores || permisos.examenes || permisos.vacaciones_permisos || permisos.movimientos_personal);
        }

        if (!item.recurso) {
            return true;
        }

        return Boolean(permisos[item.recurso]);
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={titulo} />
            <div className="flex flex-1 flex-col gap-6 bg-[#09090b] p-6 md:p-8">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight text-zinc-100">{titulo}</h1>
                        {descripcion && <p className="text-[13px] leading-relaxed text-zinc-400">{descripcion}</p>}
                    </div>
                    {acciones && <div className="flex flex-wrap items-center gap-2">{acciones}</div>}
                </div>

                <nav aria-label="Navegación de RR.HH." className="flex flex-wrap gap-1.5 border-b border-zinc-800 pb-3">
                    {pestanasVisibles.map((item) => {
                        const activa = item.exacto ? rutaActual === item.href : rutaActual.startsWith(item.href);
                        const Icono = item.icono;

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={cn(
                                    'inline-flex items-center gap-2 rounded-md border px-3.5 py-1.5 text-[13px] font-medium transition-colors',
                                    activa
                                        ? 'border-zinc-700 bg-zinc-900 text-zinc-100'
                                        : 'border-zinc-800 bg-[#0f0f13] text-zinc-400 hover:border-zinc-700 hover:text-zinc-200',
                                )}
                            >
                                <Icono size={15} className={activa ? 'text-zinc-200' : 'text-zinc-500'} />
                                <span>{item.titulo}</span>
                            </Link>
                        );
                    })}
                </nav>

                {children}
            </div>
        </AppLayout>
    );
}
