import KpiCard from '@/components/kpi-card';
import ModuleCard from '@/components/module-card';
import PageHeader from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Box,
    FileSpreadsheet,
    FileUp,
    HardHat,
    Package,
    Settings,
    Truck,
    Users,
    Wallet,
} from 'lucide-react';

interface ModuloResumen {
    titulo: string;
    fase: string;
    descripcion: string;
    ruta: string;
}

interface DashboardProps {
    resumenModulos: Record<string, ModuloResumen>;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Resumen Ejecutivo',
        href: '/dashboard',
    },
];

const MODULO_CONFIG: Record<
    string,
    {
        titulo: string;
        descripcion: string;
        icon: typeof HardHat;
    }
> = {
    rrhh: {
        titulo: 'Gestión Humana',
        descripcion: 'Administración de personal, rotación, permisos y control de EMOs para campamento.',
        icon: HardHat,
    },
    logistica: {
        titulo: 'Cadena de Suministro',
        descripcion: 'Proveedores, órdenes de compra, almacenes y control de kardex en tiempo real.',
        icon: Package,
    },
    finanzas: {
        titulo: 'Finanzas y Tesorería',
        descripcion: 'Presupuestos operativos, centros de costo, movimientos de caja y cuentas por pagar.',
        icon: Wallet,
    },
    gerencia: {
        titulo: 'Dirección Estratégica',
        descripcion: 'Tablero de control ejecutivo, indicadores de faena y reportes consolidados en PDF.',
        icon: BarChart3,
    },
    reportes: {
        titulo: 'Analítica de Datos',
        descripcion: 'Hub centralizado de reportes parametrizados y exportaciones oficiales a Excel.',
        icon: FileSpreadsheet,
    },
    importaciones: {
        titulo: 'Carga Masiva Excel',
        descripcion: 'Importación masiva de trabajadores, materiales, proveedores y stock inicial.',
        icon: FileUp,
    },
    administracion: {
        titulo: 'Administración y Seguridad',
        descripcion: 'Control de acceso basado en roles, catálogos transversales y bitácora de auditoría.',
        icon: Settings,
    },
};

export default function Dashboard({ resumenModulos }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const usuario = auth.user;
    const visibles = new Set(usuario.modulos_visibles);

    const modulosDisponibles = Object.entries(resumenModulos).filter(([clave]) => visibles.has(clave));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Resumen Ejecutivo" />

            <div className="flex flex-1 flex-col gap-6 bg-[#09090b] p-6 md:p-8">
                {/* 1. TÍTULO Y JERARQUÍA */}
                <PageHeader
                    title="Bienvenido de nuevo"
                    description="Resumen de operaciones y acceso a módulos del sistema. Actualizado al instante."
                    className="border-b-0 pb-0"
                />

                {/* 2. 4 TARJETAS KPIS TIPO ZINC */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {/* KPI 1: Personal en Mina */}
                    <KpiCard
                        title="Personal en Mina"
                        value={482}
                        icon={Users}
                        badge="Pataz"
                    />

                    {/* KPI 2: Despachos Ruta */}
                    <KpiCard
                        title="Despachos Ruta"
                        value={14}
                        icon={Truck}
                        badge="Unidades"
                    />

                    {/* KPI 3: Ejecución Presupuestal */}
                    <KpiCard
                        title="Ejecución Presup."
                        value="74%"
                        icon={Wallet}
                        badge="Mes actual"
                    />

                    {/* KPI 4: Alertas Críticas (Acento Amber) */}
                    <KpiCard
                        title="Alertas Críticas"
                        value={3}
                        variant="alert"
                        action={{
                            label: 'Revisar',
                            href: '/notificaciones',
                        }}
                    />
                </div>

                {/* 3. MÓDULOS DEL SISTEMA */}
                <div className="space-y-3">
                    <h3 className="text-[14px] font-medium text-zinc-100">Módulos del Sistema</h3>

                    {modulosDisponibles.length === 0 ? (
                        <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-8 text-center text-[13px] text-zinc-400">
                            Tu usuario no tiene módulos asignados en este momento.
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {modulosDisponibles.map(([clave, modulo]) => {
                                const conf = MODULO_CONFIG[clave] ?? {
                                    titulo: modulo.titulo,
                                    descripcion: modulo.descripcion,
                                    icon: Box,
                                };

                                return (
                                    <ModuleCard
                                        key={clave}
                                        title={conf.titulo}
                                        description={conf.descripcion}
                                        icon={conf.icon}
                                        href={modulo.ruta}
                                    />
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
