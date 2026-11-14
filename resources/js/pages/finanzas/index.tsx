import { KpiCard } from '@/components/kpi-card';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import FinanzasLayout from '@/layouts/finanzas/finanzas-layout';
import { type BreadcrumbItem } from '@/types';
import { Link, router } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    Clock,
    DollarSign,
} from 'lucide-react';

interface KpisFinanzas {
    anio: number;
    mes: number;
    mes_nombre: string;
    total_ingresos_mes: number;
    total_egresos_mes: number;
    saldo_neto_mes: number;
    presupuesto_total_mes: number;
    porcentaje_ejecucion: number;
    cuentas_pendientes_total: number;
    cuentas_pendientes_count: number;
    cuentas_vencidas_total: number;
    cuentas_vencidas_count: number;
}

interface ItemPresupuesto {
    centro_costo_id: number;
    codigo: string;
    nombre: string;
    area_nombre: string;
    presupuesto: number;
    ejecutado: number;
    diferencia: number;
    porcentaje: number;
    estado: 'NORMAL' | 'ADVERTENCIA' | 'ALERTA' | 'SOBREGIRADO';
    color: string;
}

interface ComparativoData {
    anio: number;
    mes: number;
    mes_nombre: string;
    items: ItemPresupuesto[];
    totales: {
        presupuesto: number;
        ejecutado: number;
        diferencia: number;
        porcentaje: number;
    };
}

interface MovimientoResumen {
    id: number;
    tipo: 'INGRESO' | 'EGRESO';
    tipo_etiqueta: string;
    fecha: string;
    fecha_fmt: string;
    monto: number;
    moneda: string;
    concepto: string;
    categoria: string;
    centro_costo: string;
    proveedor: string;
    estado_pago: string;
    estado_pago_etiqueta: string;
}

interface CuentaPendienteResumen {
    id: number;
    concepto: string;
    monto: number;
    moneda: string;
    proveedor: string;
    centro_costo: string;
    comprobante: string;
    fecha_vencimiento: string;
    es_vencida: boolean;
}

interface FinanzasIndexProps {
    kpis: KpisFinanzas;
    comparativo: ComparativoData;
    ultimosMovimientos: MovimientoResumen[];
    cuentasPendientes: CuentaPendienteResumen[];
    filtros: {
        anio: number;
        mes: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Finanzas', href: '/finanzas' },
];

const MESES = [
    { id: 1, nombre: 'Enero' },
    { id: 2, nombre: 'Febrero' },
    { id: 3, nombre: 'Marzo' },
    { id: 4, nombre: 'Abril' },
    { id: 5, nombre: 'Mayo' },
    { id: 6, nombre: 'Junio' },
    { id: 7, nombre: 'Julio' },
    { id: 8, nombre: 'Agosto' },
    { id: 9, nombre: 'Setiembre' },
    { id: 10, nombre: 'Octubre' },
    { id: 11, nombre: 'Noviembre' },
    { id: 12, nombre: 'Diciembre' },
];

export default function FinanzasIndex({
    kpis,
    comparativo,
    ultimosMovimientos,
    cuentasPendientes,
    filtros,
}: FinanzasIndexProps) {
    const formatearMonto = (valor: number) => {
        return new Intl.NumberFormat('es-PE', {
            style: 'currency',
            currency: 'PEN',
            minimumFractionDigits: 2,
        }).format(valor);
    };

    const handlePeriodoChange = (nuevoAnio: number, nuevoMes: number) => {
        router.get(
            '/finanzas',
            { anio: nuevoAnio, mes: nuevoMes },
            { preserveState: true, replace: true },
        );
    };

    return (
        <FinanzasLayout
            breadcrumbs={breadcrumbs}
            titulo="Control Financiero y Presupuestal"
            descripcion="Supervisión en tiempo real de ingresos, egresos operativos, ejecución presupuestal y cuentas por pagar."
        >
            {/* Barra de Filtro de Período */}
            <div className="flex flex-col gap-3 rounded-lg border border-zinc-800 bg-[#0f0f13] p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-sm font-semibold text-zinc-100">
                        Período Operativo: {kpis.mes_nombre} {filtros.anio}
                    </h2>
                    <p className="text-[12px] text-zinc-400">
                        Datos contables consolidados para Trujillo y Pataz
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <select
                        aria-label="Seleccionar mes"
                        value={filtros.mes}
                        onChange={(e) => handlePeriodoChange(filtros.anio, Number(e.target.value))}
                        className="h-9 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                    >
                        {MESES.map((m) => (
                            <option key={m.id} value={m.id}>
                                {m.nombre}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Seleccionar año"
                        value={filtros.anio}
                        onChange={(e) => handlePeriodoChange(Number(e.target.value), filtros.mes)}
                        className="h-9 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                    >
                        {[filtros.anio - 1, filtros.anio, filtros.anio + 1].map((a) => (
                            <option key={a} value={a}>
                                {a}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {/* Fila de Tarjetas KPI */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <KpiCard
                    title="Ingresos del Mes"
                    value={formatearMonto(kpis.total_ingresos_mes)}
                    icon={ArrowUpRight}
                    badge="Cobranzas"
                />

                <KpiCard
                    title="Egresos del Mes"
                    value={formatearMonto(kpis.total_egresos_mes)}
                    icon={ArrowDownRight}
                    badge="Operativo"
                />

                <KpiCard
                    title="Saldo Neto del Mes"
                    value={formatearMonto(kpis.saldo_neto_mes)}
                    icon={DollarSign}
                    badge={kpis.saldo_neto_mes >= 0 ? 'Superávit' : 'Déficit'}
                    variant={kpis.saldo_neto_mes >= 0 ? 'default' : 'alert'}
                />

                <KpiCard
                    title="Cuentas por Pagar"
                    value={formatearMonto(kpis.cuentas_pendientes_total)}
                    icon={Clock}
                    badge={kpis.cuentas_vencidas_count > 0 ? `${kpis.cuentas_vencidas_count} vencidas` : `${kpis.cuentas_pendientes_count} pendientes`}
                    variant={kpis.cuentas_vencidas_count > 0 ? 'alert' : 'default'}
                    action={kpis.cuentas_vencidas_count > 0 ? { label: 'Revisar', href: '/finanzas/cuentas-por-pagar' } : undefined}
                />
            </div>

            {/* Barra de Ejecución Presupuestal Global */}
            <Card className="border border-zinc-800 bg-[#0f0f13]">
                <CardHeader className="pb-3">
                    <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                        <div>
                            <CardTitle className="text-base font-semibold text-zinc-100">
                                Ejecución Presupuestal Mensual
                            </CardTitle>
                            <CardDescription className="text-[13px] text-zinc-400">
                                Presupuesto global aprobado: {formatearMonto(kpis.presupuesto_total_mes)} · Ejecutado: {formatearMonto(kpis.total_egresos_mes)}
                            </CardDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge
                                variant="outline"
                                className={`text-[12px] font-semibold tabular-nums border-zinc-800 ${
                                    kpis.porcentaje_ejecucion > 100
                                        ? 'bg-red-950/40 text-red-400 border-red-900/50'
                                        : kpis.porcentaje_ejecucion > 90
                                        ? 'bg-amber-950/40 text-amber-400 border-amber-900/50'
                                        : 'bg-zinc-900 text-zinc-300'
                                }`}
                            >
                                {kpis.porcentaje_ejecucion}% Ejecutado
                            </Badge>
                            <Link
                                href={`/finanzas/presupuestos?anio=${filtros.anio}&mes=${filtros.mes}`}
                                className="inline-flex items-center text-[12px] font-medium text-blue-400 hover:text-blue-300 transition-colors"
                            >
                                Gestionar <ArrowRight size={13} className="ml-1" />
                            </Link>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="h-3 w-full overflow-hidden rounded-full bg-zinc-900 border border-zinc-800/80">
                        <div
                            className={`h-full transition-all ${
                                kpis.porcentaje_ejecucion > 100
                                    ? 'bg-red-500'
                                    : kpis.porcentaje_ejecucion > 90
                                    ? 'bg-amber-500'
                                    : kpis.porcentaje_ejecucion >= 80
                                    ? 'bg-amber-400'
                                    : 'bg-blue-500'
                            }`}
                            style={{ width: `${Math.min(kpis.porcentaje_ejecucion, 100)}%` }}
                        />
                    </div>
                    <div className="mt-2 flex justify-between text-[11px] text-zinc-500 font-mono">
                        <span>0%</span>
                        <span>80% (Advertencia)</span>
                        <span>90% (Alerta)</span>
                        <span>100% (Límite Presupuestal)</span>
                    </div>
                </CardContent>
            </Card>

            {/* Matriz Presupuesto vs Real por Centro de Costo */}
            <Card className="border border-zinc-800 bg-[#0f0f13]">
                <CardHeader className="flex flex-row items-center justify-between pb-3">
                    <div>
                        <CardTitle className="text-base font-semibold text-zinc-100">
                            Presupuesto vs Real por Centro de Costo
                        </CardTitle>
                        <CardDescription className="text-[13px] text-zinc-400">
                            Control mensual de desviaciones con alertas de sobregiro
                        </CardDescription>
                    </div>
                    <Link
                        href={`/finanzas/reportes/presupuesto-pdf?anio=${filtros.anio}&mes=${filtros.mes}`}
                        className="inline-flex items-center gap-1.5 rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[12px] font-medium text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 transition-colors"
                    >
                        Descargar PDF
                    </Link>
                </CardHeader>
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-[13px]">
                            <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] uppercase tracking-wider font-semibold text-zinc-400">
                                <tr>
                                    <th className="px-4 py-3">Código</th>
                                    <th className="px-4 py-3">Centro de Costo</th>
                                    <th className="px-4 py-3">Área</th>
                                    <th className="px-4 py-3 text-right">Presupuesto</th>
                                    <th className="px-4 py-3 text-right">Ejecutado</th>
                                    <th className="px-4 py-3 text-right">Diferencia</th>
                                    <th className="px-4 py-3 text-right">% Ejecución</th>
                                    <th className="px-4 py-3 text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {comparativo.items.map((item) => (
                                    <tr key={item.centro_costo_id} className="hover:bg-zinc-900/40 transition-colors">
                                        <td className="px-4 py-2.5 font-mono text-[12px] text-zinc-400">{item.codigo}</td>
                                        <td className="px-4 py-2.5 text-zinc-100 font-medium">{item.nombre}</td>
                                        <td className="px-4 py-2.5 text-zinc-400">{item.area_nombre}</td>
                                        <td className="px-4 py-2.5 text-right font-medium text-zinc-300 tabular-nums">
                                            {formatearMonto(item.presupuesto)}
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-medium text-zinc-100 tabular-nums">
                                            {formatearMonto(item.ejecutado)}
                                        </td>
                                        <td
                                            className={`px-4 py-2.5 text-right font-medium tabular-nums ${
                                                item.diferencia < 0 ? 'text-red-400' : 'text-emerald-400'
                                            }`}
                                        >
                                            {formatearMonto(item.diferencia)}
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-medium tabular-nums text-zinc-300">
                                            {item.porcentaje}%
                                        </td>
                                        <td className="px-4 py-2.5 text-center">
                                            {item.estado === 'NORMAL' && (
                                                <span className="inline-flex items-center rounded-md border border-emerald-900/50 bg-emerald-950/30 px-2 py-0.5 text-[11px] font-medium text-emerald-400">
                                                    Normal
                                                </span>
                                            )}
                                            {item.estado === 'ADVERTENCIA' && (
                                                <span className="inline-flex items-center rounded-md border border-amber-900/50 bg-amber-950/30 px-2 py-0.5 text-[11px] font-medium text-amber-400">
                                                    Advertencia (80-90%)
                                                </span>
                                            )}
                                            {item.estado === 'ALERTA' && (
                                                <span className="inline-flex items-center rounded-md border border-amber-800 bg-amber-950/50 px-2 py-0.5 text-[11px] font-medium text-amber-300">
                                                    Alerta (&gt;90%)
                                                </span>
                                            )}
                                            {item.estado === 'SOBREGIRADO' && (
                                                <span className="inline-flex items-center rounded-md border border-red-900/50 bg-red-950/40 px-2 py-0.5 text-[11px] font-medium text-red-400">
                                                    Sobregirado (&gt;100%)
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                <tr className="border-t-2 border-zinc-800 bg-zinc-900/70 font-semibold text-zinc-100">
                                    <td colSpan={3} className="px-4 py-3 text-zinc-300 text-[12px] uppercase tracking-wider">
                                        TOTALES DEL PERÍODO
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums text-zinc-200">
                                        {formatearMonto(comparativo.totales.presupuesto)}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums text-zinc-100">
                                        {formatearMonto(comparativo.totales.ejecutado)}
                                    </td>
                                    <td
                                        className={`px-4 py-3 text-right tabular-nums ${
                                            comparativo.totales.diferencia < 0 ? 'text-red-400' : 'text-emerald-400'
                                        }`}
                                    >
                                        {formatearMonto(comparativo.totales.diferencia)}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums text-zinc-100">
                                        {comparativo.totales.porcentaje}%
                                    </td>
                                    <td className="px-4 py-3 text-center text-zinc-500">—</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            {/* Dos Columnas: Cuentas por Pagar Próximas y Últimos Movimientos */}
            <div className="grid gap-6 lg:grid-cols-2">
                {/* Cuentas por Pagar */}
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                        <div>
                            <CardTitle className="text-base font-semibold text-zinc-100">
                                Cuentas por Pagar Próximas / Vencidas
                            </CardTitle>
                            <CardDescription className="text-[13px] text-zinc-400">Comprobantes pendientes de liquidación</CardDescription>
                        </div>
                        <Link
                            href="/finanzas/cuentas-por-pagar"
                            className="inline-flex items-center text-[12px] font-medium text-blue-400 hover:text-blue-300 transition-colors"
                        >
                            Ver todas <ArrowRight size={13} className="ml-1" />
                        </Link>
                    </CardHeader>
                    <CardContent className="pt-0">
                        {cuentasPendientes.length === 0 ? (
                            <p className="py-8 text-center text-[13px] text-zinc-500">
                                No hay comprobantes pendientes de pago actualmente.
                            </p>
                        ) : (
                            <div className="divide-y divide-zinc-800/60">
                                {cuentasPendientes.map((cuenta) => (
                                    <div key={cuenta.id} className="flex items-center justify-between py-3 text-[13px]">
                                        <div className="flex flex-col gap-0.5">
                                            <span className="font-medium text-zinc-200">{cuenta.concepto}</span>
                                            <span className="text-[11px] text-zinc-500">
                                                {cuenta.proveedor} · <span className="font-mono">{cuenta.comprobante}</span> · {cuenta.centro_costo}
                                            </span>
                                        </div>
                                        <div className="flex flex-col items-end gap-1">
                                            <span className="font-semibold text-zinc-100 tabular-nums">{formatearMonto(cuenta.monto)}</span>
                                            <span
                                                className={`rounded px-1.5 py-0.5 text-[10px] font-medium border ${
                                                    cuenta.es_vencida
                                                        ? 'bg-red-950/40 text-red-400 border-red-900/50'
                                                        : 'bg-amber-950/40 text-amber-300 border-amber-900/50'
                                                }`}
                                            >
                                                {cuenta.es_vencida ? 'Vencida: ' : 'Vence: '}
                                                {cuenta.fecha_vencimiento}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Últimos Movimientos */}
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                        <div>
                            <CardTitle className="text-base font-semibold text-zinc-100">
                                Últimos Movimientos Registrados
                            </CardTitle>
                            <CardDescription className="text-[13px] text-zinc-400">Ingresos y egresos recientes</CardDescription>
                        </div>
                        <Link
                            href="/finanzas/movimientos"
                            className="inline-flex items-center text-[12px] font-medium text-blue-400 hover:text-blue-300 transition-colors"
                        >
                            Ver todos <ArrowRight size={13} className="ml-1" />
                        </Link>
                    </CardHeader>
                    <CardContent className="pt-0">
                        {ultimosMovimientos.length === 0 ? (
                            <p className="py-8 text-center text-[13px] text-zinc-500">
                                No se registran movimientos en el sistema aún.
                            </p>
                        ) : (
                            <div className="divide-y divide-zinc-800/60">
                                {ultimosMovimientos.map((m) => (
                                    <div key={m.id} className="flex items-center justify-between py-3 text-[13px]">
                                        <div className="flex items-start gap-2.5">
                                            <span
                                                className={`mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold ${
                                                    m.tipo === 'INGRESO'
                                                        ? 'bg-emerald-950/50 text-emerald-400 border border-emerald-900/50'
                                                        : 'bg-red-950/50 text-red-400 border border-red-900/50'
                                                }`}
                                            >
                                                {m.tipo === 'INGRESO' ? '+' : '-'}
                                            </span>
                                            <div className="flex flex-col gap-0.5">
                                                <span className="font-medium text-zinc-200">{m.concepto}</span>
                                                <span className="text-[11px] text-zinc-500">
                                                    {m.fecha_fmt} · {m.categoria} · {m.centro_costo}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex flex-col items-end gap-1">
                                            <span
                                                className={`font-semibold tabular-nums ${
                                                    m.tipo === 'INGRESO' ? 'text-emerald-400' : 'text-zinc-100'
                                                }`}
                                            >
                                                {m.tipo === 'INGRESO' ? '+' : '-'} {formatearMonto(m.monto)}
                                            </span>
                                            <span className="text-[10px] text-zinc-500">
                                                {m.estado_pago_etiqueta}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </FinanzasLayout>
    );
}
