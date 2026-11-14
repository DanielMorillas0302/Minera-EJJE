import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Briefcase, Building2, ClipboardList, Layers, LayoutDashboard, Ruler, Users } from 'lucide-react';

interface AdminLayoutProps {
    titulo: string;
    descripcion?: string;
    breadcrumbs: BreadcrumbItem[];
    children: React.ReactNode;
}

const PESTAÑAS = [
    { titulo: 'Resumen', href: '/administracion', icono: LayoutDashboard, exacto: true },
    { titulo: 'Usuarios', href: '/administracion/usuarios', icono: Users },
    { titulo: 'Sedes', href: '/administracion/catalogos/sedes', icono: Building2 },
    { titulo: 'Áreas', href: '/administracion/catalogos/areas', icono: Layers },
    { titulo: 'Cargos', href: '/administracion/catalogos/cargos', icono: Briefcase },
    { titulo: 'Unidades', href: '/administracion/catalogos/unidades-medida', icono: Ruler },
    { titulo: 'Auditoría', href: '/administracion/auditoria', icono: ClipboardList },
];

export default function AdminLayout({ titulo, descripcion, breadcrumbs, children }: AdminLayoutProps) {
    const page = usePage();
    const rutaActual = page.url.split('?')[0];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={titulo} />
            <div className="flex flex-1 flex-col gap-6 bg-[#09090b] p-6 md:p-8">
                <div className="flex flex-col gap-1 border-b border-zinc-800 pb-4">
                    <h1 className="text-2xl font-semibold tracking-tight text-zinc-100">{titulo}</h1>
                    {descripcion && <p className="text-[13px] leading-relaxed text-zinc-400">{descripcion}</p>}
                </div>

                <nav aria-label="Navegación de Administración" className="flex flex-wrap gap-1.5 border-b border-zinc-800 pb-3">
                    {PESTAÑAS.map((item) => {
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
