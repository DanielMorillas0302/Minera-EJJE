import DataTableCard, { DataTableEmpty, DataTableTbody, DataTableTd, DataTableTh, DataTableThead, DataTableTr } from '@/components/data-table-card';
import KpiCard from '@/components/kpi-card';
import StatusBadge from '@/components/status-badge';
import RrhhLayout from '@/layouts/rrhh/rrhh-layout';
import { type BreadcrumbItem } from '@/types';
import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeftRight,
    ArrowRight,
    CalendarClock,
    CheckCircle2,
    HardHat,
    HeartPulse,
    ShieldAlert,
    Users,
} from 'lucide-react';

interface AlertaExamen {
    trabajador_id: number;
    dni: string;
    nombre_completo: string;
    cargo: string | null;
    area: string | null;
    sede_base: string | null;
    ubicacion_etiqueta: string | null;
    nivel: 'POR_VENCER' | 'VENCIDO' | 'SIN_EXAMEN' | 'CON_RESTRICCIONES' | 'NO_APTO';
    mensaje: string;
    dias_restantes: number | null;
    fecha_vencimiento: string | null;
}

interface ResumenRrhhProps {
    puede: {
        trabajadores: boolean;
        examenes: boolean;
        vacaciones: boolean;
        movimientos: boolean;
        medico_detalle: boolean;
    };
    conteosPersonal: {
        activos: number;
        trujillo: number;
        pataz: number;
        en_transito: number;
    };
    totalesAptitud: {
        activos: number;
        aptos: number;
        no_aptos: number;
        por_vencer: number;
        vencidos: number;
        sin_examen: number;
    } | null;
    alertasExamenes: AlertaExamen[];
    pendientes: {
        vacaciones: number;
        permisos: number;
    };
    ultimosMovimientos: Array<{
        id: number;
        trabajador_id: number;
        trabajador_nombre: string;
        trabajador_dni: string;
        tipo: string;
        tipo_etiqueta: string;
        origen: string;
        destino: string;
        fecha_hora: string | null;
    }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Personal en Mina', href: '/rrhh' },
];

export default function RrhhIndex({
    puede,
    conteosPersonal,
    totalesAptitud,
    alertasExamenes,
    pendientes,
    ultimosMovimientos,
}: ResumenRrhhProps) {
    return (
        <RrhhLayout
            titulo="Gestión Humana y Personal en Mina"
            descripcion="Control de personal, aptitud médica ocupacional (EMO), traslados Trujillo ↔ Pataz, vacaciones y permisos."
            breadcrumbs={breadcrumbs}
        >
            {/* Tarjetas KPI */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <KpiCard
                    title="Personal Activo"
                    value={conteosPersonal.activos}
                    icon={Users}
                    badge={`Trujillo: ${conteosPersonal.trujillo} · Pataz: ${conteosPersonal.pataz}`}
                />

                {totalesAptitud && (
                    <>
                        <KpiCard
                            title="Aptos para Pataz"
                            value={totalesAptitud.aptos}
                            icon={CheckCircle2}
                            badge={`No aptos: ${totalesAptitud.no_aptos}`}
                        />

                        <KpiCard
                            title="Alertas de Exámenes"
                            value={totalesAptitud.vencidos + totalesAptitud.por_vencer}
                            variant={totalesAptitud.vencidos > 0 ? 'alert' : 'default'}
                            icon={HeartPulse}
                            badge={`Vencidos: ${totalesAptitud.vencidos}`}
                            action={{
                                label: 'Revisar',
                                href: '/rrhh/examenes?estado_venc=alertas',
                            }}
                        />
                    </>
                )}

                {puede.vacaciones && (
                    <KpiCard
                        title="Solicitudes Pendientes"
                        value={pendientes.vacaciones + pendientes.permisos}
                        icon={CalendarClock}
                        badge={`Vac: ${pendientes.vacaciones} · Perm: ${pendientes.permisos}`}
                    />
                )}
            </div>

            {/* Alertas de exámenes ocupacionales */}
            {puede.examenes && (
                <DataTableCard
                    title="Alertas de Exámenes Ocupacionales y Aptitud"
                    description="Personal activo que requiere renovación de examen médico o pase ocupacional para faena en Pataz."
                    count={alertasExamenes.length}
                    actions={
                        <Link
                            href="/rrhh/examenes?estado_venc=alertas"
                            className="inline-flex items-center gap-1.5 rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-xs font-medium text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors"
                        >
                            Ver en Exámenes
                            <ArrowRight size={14} className="text-zinc-500" />
                        </Link>
                    }
                >
                    <table className="w-full text-left text-sm">
                        <DataTableThead>
                            <tr>
                                <DataTableTh>DNI</DataTableTh>
                                <DataTableTh>Trabajador</DataTableTh>
                                <DataTableTh>Cargo / Área</DataTableTh>
                                <DataTableTh>Ubicación</DataTableTh>
                                <DataTableTh>Estado</DataTableTh>
                                <DataTableTh>Vencimiento</DataTableTh>
                                <DataTableTh>Detalle</DataTableTh>
                            </tr>
                        </DataTableThead>
                        <DataTableTbody>
                            {alertasExamenes.length === 0 ? (
                                <DataTableEmpty colSpan={7} mensaje="Todo el personal activo tiene su examen ocupacional vigente y apto." />
                            ) : (
                                alertasExamenes.map((alerta) => (
                                    <DataTableTr key={`${alerta.trabajador_id}-${alerta.nivel}`}>
                                        <DataTableTd className="font-mono text-xs text-zinc-400">{alerta.dni}</DataTableTd>
                                        <DataTableTd className="font-medium text-zinc-200">
                                            {puede.trabajadores ? (
                                                <Link
                                                    href={`/rrhh/trabajadores/${alerta.trabajador_id}`}
                                                    className="text-zinc-200 hover:text-white hover:underline transition-colors"
                                                >
                                                    {alerta.nombre_completo}
                                                </Link>
                                            ) : (
                                                alerta.nombre_completo
                                            )}
                                        </DataTableTd>
                                        <DataTableTd>
                                            <div className="text-zinc-300">{alerta.cargo ?? '—'}</div>
                                            <div className="text-[11px] text-zinc-500">{alerta.area ?? '—'}</div>
                                        </DataTableTd>
                                        <DataTableTd className="text-zinc-400">{alerta.ubicacion_etiqueta ?? '—'}</DataTableTd>
                                        <DataTableTd>
                                            <StatusBadge status={alerta.nivel} />
                                        </DataTableTd>
                                        <DataTableTd className="tabular-nums text-zinc-400">{alerta.fecha_vencimiento ?? '—'}</DataTableTd>
                                        <DataTableTd className="text-xs text-zinc-400">{alerta.mensaje}</DataTableTd>
                                    </DataTableTr>
                                ))
                            )}
                        </DataTableTbody>
                    </table>
                </DataTableCard>
            )}

            {/* Últimos movimientos Trujillo ↔ Pataz */}
            {puede.movimientos && (
                <DataTableCard
                    title="Últimos Movimientos Trujillo ↔ Pataz"
                    description="Traslados recientes de personal registrados entre oficinas administrativas y campamento minero."
                    count={ultimosMovimientos.length}
                    actions={
                        <Link
                            href="/rrhh/movimientos"
                            className="inline-flex items-center gap-1.5 rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-xs font-medium text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors"
                        >
                            Ir a Movimientos
                            <ArrowRight size={14} className="text-zinc-500" />
                        </Link>
                    }
                >
                    <table className="w-full text-left text-sm">
                        <DataTableThead>
                            <tr>
                                <DataTableTh>Fecha y Hora</DataTableTh>
                                <DataTableTh>Trabajador</DataTableTh>
                                <DataTableTh>Tipo</DataTableTh>
                                <DataTableTh>Ruta</DataTableTh>
                            </tr>
                        </DataTableThead>
                        <DataTableTbody>
                            {ultimosMovimientos.length === 0 ? (
                                <DataTableEmpty colSpan={4} mensaje="Aún no hay movimientos registrados." />
                            ) : (
                                ultimosMovimientos.map((m) => (
                                    <DataTableTr key={m.id}>
                                        <DataTableTd className="whitespace-nowrap tabular-nums text-zinc-400">{m.fecha_hora}</DataTableTd>
                                        <DataTableTd className="font-medium text-zinc-200">
                                            {m.trabajador_nombre}{' '}
                                            <span className="font-mono text-xs text-zinc-500">({m.trabajador_dni})</span>
                                        </DataTableTd>
                                        <DataTableTd>
                                            <StatusBadge status={m.tipo} label={m.tipo_etiqueta} />
                                        </DataTableTd>
                                        <DataTableTd className="text-zinc-300">
                                            {m.origen} → {m.destino}
                                        </DataTableTd>
                                    </DataTableTr>
                                ))
                            )}
                        </DataTableTbody>
                    </table>
                </DataTableCard>
            )}

            {!puede.trabajadores && !puede.movimientos && (
                <div className="flex items-center gap-3 rounded-lg border border-zinc-800 bg-[#0f0f13] p-5 text-[13px] text-zinc-400">
                    <ShieldAlert size={16} className="text-amber-500" />
                    <span>Tu rol tiene acceso de solo lectura básica en RR.HH.</span>
                </div>
            )}

            {/* Accesos directos a submódulos */}
            <div className="grid gap-4 md:grid-cols-3">
                {puede.trabajadores && (
                    <Link
                        href="/rrhh/trabajadores"
                        className="group rounded-lg border border-zinc-800 bg-[#0f0f13] p-5 transition-colors hover:border-zinc-700"
                    >
                        <div className="flex items-center gap-3">
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-md border border-zinc-800 bg-zinc-900 text-zinc-400 group-hover:text-zinc-200">
                                <HardHat size={16} />
                            </div>
                            <h3 className="text-[14px] font-medium text-zinc-100 group-hover:text-white">
                                Padrón de Trabajadores
                            </h3>
                        </div>
                        <p className="mt-2 text-[13px] text-zinc-400 leading-relaxed">
                            Fichas laborales, aptitud para Pataz, historial y legajo por trabajador.
                        </p>
                    </Link>
                )}
                {puede.vacaciones && (
                    <Link
                        href="/rrhh/vacaciones"
                        className="group rounded-lg border border-zinc-800 bg-[#0f0f13] p-5 transition-colors hover:border-zinc-700"
                    >
                        <div className="flex items-center gap-3">
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-md border border-zinc-800 bg-zinc-900 text-zinc-400 group-hover:text-zinc-200">
                                <CalendarClock size={16} />
                            </div>
                            <h3 className="text-[14px] font-medium text-zinc-100 group-hover:text-white">
                                Vacaciones y Permisos
                            </h3>
                        </div>
                        <p className="mt-2 text-[13px] text-zinc-400 leading-relaxed">
                            Saldos anuales, control de solapamiento y flujo de aprobación por jefatura.
                        </p>
                    </Link>
                )}
                {(puede.trabajadores || puede.movimientos) && (
                    <Link
                        href="/rrhh/reportes"
                        className="group rounded-lg border border-zinc-800 bg-[#0f0f13] p-5 transition-colors hover:border-zinc-700"
                    >
                        <div className="flex items-center gap-3">
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-md border border-zinc-800 bg-zinc-900 text-zinc-400 group-hover:text-zinc-200">
                                <AlertTriangle size={16} />
                            </div>
                            <h3 className="text-[14px] font-medium text-zinc-100 group-hover:text-white">
                                Reportes RR.HH. (Excel)
                            </h3>
                        </div>
                        <p className="mt-2 text-[13px] text-zinc-400 leading-relaxed">
                            Personal por sede/área, vencimientos y traslados exportables a XLSX.
                        </p>
                    </Link>
                )}
            </div>
        </RrhhLayout>
    );
}
