import AdminLayout from '@/layouts/core/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowRight, Briefcase, Building2, ClipboardList, Layers, Ruler, Users } from 'lucide-react';

interface ResumenProps {
    resumen: {
        usuarios: number;
        sedes: number;
        areas: number;
        cargos: number;
        unidades: number;
        auditoria: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Administración', href: '/administracion' },
];

export default function CoreIndex({ resumen }: ResumenProps) {
    const tarjetas = [
        {
            titulo: 'Usuarios y Roles',
            valor: resumen.usuarios,
            detalle: 'Cuentas activas, asignación de rol operativo y sede asignada.',
            href: '/administracion/usuarios',
            icono: Users,
        },
        {
            titulo: 'Sedes',
            valor: resumen.sedes,
            detalle: 'Oficinas administrativas y operaciones (Trujillo / Pataz).',
            href: '/administracion/catalogos/sedes',
            icono: Building2,
        },
        {
            titulo: 'Áreas Organizacionales',
            valor: resumen.areas,
            detalle: 'Estructura departamental asociada a sedes y centros de costo.',
            href: '/administracion/catalogos/areas',
            icono: Layers,
        },
        {
            titulo: 'Cargos y Perfiles',
            valor: resumen.cargos,
            detalle: 'Puestos de trabajo y requerimientos de aptitud médica EMO.',
            href: '/administracion/catalogos/cargos',
            icono: Briefcase,
        },
        {
            titulo: 'Unidades de Medida',
            valor: resumen.unidades,
            detalle: 'Unidades estándar para inventarios, despachos y compras.',
            href: '/administracion/catalogos/unidades-medida',
            icono: Ruler,
        },
        {
            titulo: 'Registro de Auditoría',
            valor: resumen.auditoria,
            detalle: 'Trazabilidad de accesos, modificaciones y eventos de seguridad.',
            href: '/administracion/auditoria',
            icono: ClipboardList,
        },
    ];

    return (
        <AdminLayout
            titulo="Administración y Seguridad"
            descripcion="Control centralizado de usuarios, roles de acceso, catálogos transversales y bitácora de auditoría."
            breadcrumbs={breadcrumbs}
        >
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {tarjetas.map((t) => {
                    const Icono = t.icono;
                    return (
                        <div
                            key={t.href}
                            className="group flex flex-col justify-between rounded-lg border border-zinc-800 bg-[#0f0f13] p-5 transition-colors hover:border-zinc-700"
                        >
                            <div>
                                <div className="flex items-center justify-between">
                                    <span className="text-[14px] font-medium text-zinc-200">{t.titulo}</span>
                                    <div className="flex h-8 w-8 items-center justify-center rounded-md border border-zinc-800 bg-zinc-900 text-zinc-400 group-hover:text-zinc-200">
                                        <Icono size={16} />
                                    </div>
                                </div>
                                <div className="mt-3 text-3xl font-semibold tracking-tight text-zinc-100 tabular-nums">
                                    {t.valor}
                                </div>
                                <p className="mt-2 text-[13px] leading-relaxed text-zinc-400">
                                    {t.detalle}
                                </p>
                            </div>

                            <div className="mt-5 border-t border-zinc-800/80 pt-3">
                                <Link
                                    href={t.href}
                                    className="inline-flex items-center gap-1.5 text-[13px] font-medium text-zinc-300 transition-colors hover:text-white"
                                >
                                    <span>Gestionar catálogo</span>
                                    <ArrowRight size={14} className="text-zinc-500 transition-transform group-hover:translate-x-0.5 group-hover:text-zinc-300" />
                                </Link>
                            </div>
                        </div>
                    );
                })}
            </div>
        </AdminLayout>
    );
}
