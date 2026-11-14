import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FinanzasLayout from '@/layouts/finanzas/finanzas-layout';
import { type BreadcrumbItem } from '@/types';
import { router } from '@inertiajs/react';
import {
    Clock,
    FileSpreadsheet,
    FileText,
    Filter,
    FolderTree,
    Scale,
    Tag,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface FilaCentroCosto {
    centro_costo_id: number;
    codigo: string;
    nombre: string;
    area_nombre?: string | null;
    total_movimientos: number;
    total_monto: number;
}

interface FilaCategoriaGasto {
    categoria_id: number;
    nombre: string;
    total_movimientos: number;
    total_monto: number;
}

interface FilaCuentaPagar {
    id: number;
    fecha: string | null;
    proveedor: string;
    comprobante: string;
    concepto: string;
    centro_costo: string;
    fecha_vencimiento: string;
    vencida: boolean;
    monto: number;
}

interface ItemPresupuestoComp {
    centro_costo_id: number;
    codigo: string;
    nombre: string;
    area_nombre: string;
    presupuesto: number;
    ejecutado: number;
    diferencia: number;
    porcentaje: number;
    estado: string;
}

interface DatosPresupuestoComp {
    anio: number;
    mes: number;
    mes_nombre: string;
    items: ItemPresupuestoComp[];
    totales: {
        presupuesto: number;
        ejecutado: number;
        diferencia: number;
        porcentaje: number;
    };
}

interface ReportesProps {
    tab: 'egresos_centro_costo' | 'egresos_categoria' | 'cuentas_por_pagar' | 'presupuesto_real';
    datos: FilaCentroCosto[] | FilaCategoriaGasto[] | FilaCuentaPagar[] | DatosPresupuestoComp;
    filtros: {
        tab: string;
        anio: number;
        mes: number;
        desde: string;
        hasta: string;
    };
    aniosDisponibles: number[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Finanzas', href: '/finanzas' },
    { title: 'Reportes', href: '/finanzas/reportes' },
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
    { id: 9, nombre: 'Septiembre' },
    { id: 10, nombre: 'Octubre' },
    { id: 11, nombre: 'Noviembre' },
    { id: 12, nombre: 'Diciembre' },
];

export default function FinanzasReportesPage({
    tab,
    datos,
    filtros,
    aniosDisponibles,
}: ReportesProps) {
    const [desde, setDesde] = useState(filtros.desde || '');
    const [hasta, setHasta] = useState(filtros.hasta || '');
    const [anio, setAnio] = useState(filtros.anio);
    const [mes, setMes] = useState(filtros.mes);

    const cambiarTab = (nuevoTab: string) => {
        router.get(
            '/finanzas/reportes',
            {
                tab: nuevoTab,
                desde: desde || undefined,
                hasta: hasta || undefined,
                anio,
                mes,
            },
            { preserveState: true, replace: true },
        );
    };

    const filtrar = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            '/finanzas/reportes',
            {
                tab,
                desde: desde || undefined,
                hasta: hasta || undefined,
                anio,
                mes,
            },
            { preserveState: true, replace: true },
        );
    };

    const urlExportarExcel = () => {
        const params = new URLSearchParams();
        if (tab === 'egresos_categoria') {
            params.set('tipo_reporte', 'egresos_categoria');
            params.set('desde', desde);
            params.set('hasta', hasta);
        } else if (tab === 'cuentas_por_pagar') {
            params.set('tipo_reporte', 'cuentas_pagar');
        } else if (tab === 'presupuesto_real') {
            params.set('tipo_reporte', 'presupuesto_real');
            params.set('anio', String(anio));
            params.set('mes', String(mes));
        } else {
            params.set('tipo_reporte', 'egresos_cc');
            params.set('desde', desde);
            params.set('hasta', hasta);
        }
        return `/finanzas/reportes/exportar?${params.toString()}`;
    };

    const formatearMonto = (valor: number) => {
        return new Intl.NumberFormat('es-PE', {
            style: 'currency',
            currency: 'PEN',
            minimumFractionDigits: 2,
        }).format(valor);
    };

    const filasCentroCosto = tab === 'egresos_centro_costo' ? (datos as FilaCentroCosto[]) : [];
    const filasCategoria = tab === 'egresos_categoria' ? (datos as FilaCategoriaGasto[]) : [];
    const filasCuentasPagar = tab === 'cuentas_por_pagar' ? (datos as FilaCuentaPagar[]) : [];
    const datosPresupuesto = tab === 'presupuesto_real' ? (datos as DatosPresupuestoComp) : null;

    return (
        <FinanzasLayout
            breadcrumbs={breadcrumbs}
            titulo="Reportes Financieros"
            descripcion="Generación y exportación de reportes analíticos de costos, gastos, presupuestos y pasivos a Excel y PDF."
        >
            {/* Navegación por pestañas de reportes */}
            <div className="flex flex-wrap gap-2">
                <button
                    onClick={() => cambiarTab('egresos_centro_costo')}
                    className={`inline-flex h-9 items-center gap-2 rounded-md border px-3.5 text-[13px] font-medium transition-colors ${
                        tab === 'egresos_centro_costo'
                            ? 'border-zinc-700 bg-zinc-900 text-zinc-100'
                            : 'border-zinc-800 bg-[#0f0f13] text-zinc-400 hover:border-zinc-700 hover:text-zinc-200'
                    }`}
                >
                    <FolderTree size={15} /> Gasto por Centro de Costo
                </button>
                <button
                    onClick={() => cambiarTab('egresos_categoria')}
                    className={`inline-flex h-9 items-center gap-2 rounded-md border px-3.5 text-[13px] font-medium transition-colors ${
                        tab === 'egresos_categoria'
                            ? 'border-zinc-700 bg-zinc-900 text-zinc-100'
                            : 'border-zinc-800 bg-[#0f0f13] text-zinc-400 hover:border-zinc-700 hover:text-zinc-200'
                    }`}
                >
                    <Tag size={15} /> Gasto por Categoría
                </button>
                <button
                    onClick={() => cambiarTab('cuentas_por_pagar')}
                    className={`inline-flex h-9 items-center gap-2 rounded-md border px-3.5 text-[13px] font-medium transition-colors ${
                        tab === 'cuentas_por_pagar'
                            ? 'border-zinc-700 bg-zinc-900 text-zinc-100'
                            : 'border-zinc-800 bg-[#0f0f13] text-zinc-400 hover:border-zinc-700 hover:text-zinc-200'
                    }`}
                >
                    <Clock size={15} /> Cuentas por Pagar
                </button>
                <button
                    onClick={() => cambiarTab('presupuesto_real')}
                    className={`inline-flex h-9 items-center gap-2 rounded-md border px-3.5 text-[13px] font-medium transition-colors ${
                        tab === 'presupuesto_real'
                            ? 'border-zinc-700 bg-zinc-900 text-zinc-100'
                            : 'border-zinc-800 bg-[#0f0f13] text-zinc-400 hover:border-zinc-700 hover:text-zinc-200'
                    }`}
                >
                    <Scale size={15} /> Presupuesto vs Real (PDF)
                </button>
            </div>

            {/* Barra de Filtros y Botones de Descarga */}
            <Card className="border border-zinc-800 bg-[#0f0f13]">
                <CardHeader className="pb-3">
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <CardTitle className="text-base font-semibold text-zinc-100">
                            Parámetros del Reporte
                        </CardTitle>
                        <div className="flex flex-wrap items-center gap-2">
                            <a
                                href={urlExportarExcel()}
                                className="inline-flex h-9 items-center gap-1.5 rounded-md bg-zinc-100 px-3.5 text-[13px] font-medium text-zinc-900 hover:bg-white transition-colors"
                            >
                                <FileSpreadsheet size={15} /> Exportar a Excel (.xlsx)
                            </a>
                            {tab === 'presupuesto_real' && (
                                <a
                                    href={`/finanzas/reportes/presupuesto-pdf?anio=${anio}&mes=${mes}`}
                                    className="inline-flex h-9 items-center gap-1.5 rounded-md border border-zinc-800 bg-zinc-900 px-3.5 text-[13px] font-medium text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 transition-colors"
                                >
                                    <FileText size={15} /> Descargar PDF
                                </a>
                            )}
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <form onSubmit={filtrar} className="flex flex-wrap items-end gap-3">
                        {tab === 'presupuesto_real' ? (
                            <>
                                <div>
                                    <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Año Fiscal</Label>
                                    <select
                                        aria-label="Año Fiscal"
                                        value={anio}
                                        onChange={(e) => setAnio(Number(e.target.value))}
                                        className="mt-1 block h-9 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                    >
                                        {aniosDisponibles.map((a) => (
                                            <option key={a} value={a}>
                                                {a}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Mes a Evaluar</Label>
                                    <select
                                        aria-label="Mes a Evaluar"
                                        value={mes}
                                        onChange={(e) => setMes(Number(e.target.value))}
                                        className="mt-1 block h-9 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                    >
                                        {MESES.map((m) => (
                                            <option key={m.id} value={m.id}>
                                                {m.nombre}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </>
                        ) : tab !== 'cuentas_por_pagar' ? (
                            <>
                                <div>
                                    <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha Desde</Label>
                                    <Input
                                        type="date"
                                        value={desde}
                                        onChange={(e) => setDesde(e.target.value)}
                                        className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                    />
                                </div>
                                <div>
                                    <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha Hasta</Label>
                                    <Input
                                        type="date"
                                        value={hasta}
                                        onChange={(e) => setHasta(e.target.value)}
                                        className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                    />
                                </div>
                            </>
                        ) : (
                            <p className="text-[13px] text-zinc-400">
                                Muestra todas las facturas y comprobantes pendientes de pago en cartera al día de hoy.
                            </p>
                        )}
                        {tab !== 'cuentas_por_pagar' && (
                            <Button
                                type="submit"
                                variant="outline"
                                className="h-9 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 text-[13px]"
                            >
                                <Filter size={14} className="mr-1.5" /> Actualizar Datos
                            </Button>
                        )}
                    </form>
                </CardContent>
            </Card>

            {/* TAB 1: Gasto por Centro de Costo */}
            {tab === 'egresos_centro_costo' && (
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base font-semibold text-zinc-100">
                            Egresos Consolidados por Centro de Costo
                        </CardTitle>
                        <CardDescription className="text-[13px] text-zinc-400">
                            Rango: {desde || 'Inicio'} al {hasta || 'Actual'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-[13px]">
                                <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] uppercase tracking-wider font-semibold text-zinc-400">
                                    <tr>
                                        <th className="px-4 py-3">Código</th>
                                        <th className="px-4 py-3">Centro de Costo</th>
                                        <th className="px-4 py-3">Área de la Empresa</th>
                                        <th className="px-4 py-3 text-center">Nro Movimientos</th>
                                        <th className="px-4 py-3 text-right">Total Egresos (PEN)</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {filasCentroCosto.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="py-10 text-center text-[13px] text-zinc-500">
                                                No se encontraron egresos en el rango de fechas especificado.
                                            </td>
                                        </tr>
                                    ) : (
                                        <>
                                            {filasCentroCosto.map((r) => (
                                                <tr key={r.centro_costo_id} className="hover:bg-zinc-900/40 transition-colors">
                                                    <td className="px-4 py-2.5 font-mono text-[12px] text-zinc-400">{r.codigo}</td>
                                                    <td className="px-4 py-2.5 font-medium text-zinc-100">{r.nombre}</td>
                                                    <td className="px-4 py-2.5 text-zinc-400">{r.area_nombre || 'Sin área'}</td>
                                                    <td className="px-4 py-2.5 text-center">
                                                        <span className="rounded-md border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-semibold text-zinc-300 tabular-nums">
                                                            {r.total_movimientos}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-2.5 text-right font-medium text-zinc-100 tabular-nums">
                                                        {formatearMonto(Number(r.total_monto))}
                                                    </td>
                                                </tr>
                                            ))}
                                            <tr className="border-t-2 border-zinc-800 bg-zinc-900/70 font-semibold text-zinc-100">
                                                <td colSpan={3} className="px-4 py-3 text-[12px] uppercase tracking-wider text-zinc-300">
                                                    TOTAL EGRESOS DEL PERÍODO
                                                </td>
                                                <td className="px-4 py-3 text-center tabular-nums text-zinc-300">
                                                    {filasCentroCosto.reduce((acc, r) => acc + Number(r.total_movimientos), 0)}
                                                </td>
                                                <td className="px-4 py-3 text-right text-zinc-100 tabular-nums text-base">
                                                    {formatearMonto(
                                                        filasCentroCosto.reduce((acc, r) => acc + Number(r.total_monto), 0)
                                                    )}
                                                </td>
                                            </tr>
                                        </>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* TAB 2: Gasto por Categoría */}
            {tab === 'egresos_categoria' && (
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base font-semibold text-zinc-100">
                            Egresos Consolidados por Categoría de Gasto
                        </CardTitle>
                        <CardDescription className="text-[13px] text-zinc-400">
                            Rango: {desde || 'Inicio'} al {hasta || 'Actual'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-[13px]">
                                <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] uppercase tracking-wider font-semibold text-zinc-400">
                                    <tr>
                                        <th className="px-4 py-3">Categoría de Gasto</th>
                                        <th className="px-4 py-3 text-center">Nro Movimientos</th>
                                        <th className="px-4 py-3 text-right">Total Egresos (PEN)</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {filasCategoria.length === 0 ? (
                                        <tr>
                                            <td colSpan={3} className="py-10 text-center text-[13px] text-zinc-500">
                                                No se encontraron egresos en el rango de fechas especificado.
                                            </td>
                                        </tr>
                                    ) : (
                                        <>
                                            {filasCategoria.map((r) => (
                                                <tr key={r.categoria_id} className="hover:bg-zinc-900/40 transition-colors">
                                                    <td className="px-4 py-2.5 font-medium text-zinc-100">{r.nombre}</td>
                                                    <td className="px-4 py-2.5 text-center">
                                                        <span className="rounded-md border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-semibold text-zinc-300 tabular-nums">
                                                            {r.total_movimientos}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-2.5 text-right font-medium text-zinc-100 tabular-nums">
                                                        {formatearMonto(Number(r.total_monto))}
                                                    </td>
                                                </tr>
                                            ))}
                                            <tr className="border-t-2 border-zinc-800 bg-zinc-900/70 font-semibold text-zinc-100">
                                                <td className="px-4 py-3 text-[12px] uppercase tracking-wider text-zinc-300">TOTAL GENERAL</td>
                                                <td className="px-4 py-3 text-center tabular-nums text-zinc-300">
                                                    {filasCategoria.reduce((acc, r) => acc + Number(r.total_movimientos), 0)}
                                                </td>
                                                <td className="px-4 py-3 text-right text-zinc-100 tabular-nums text-base">
                                                    {formatearMonto(
                                                        filasCategoria.reduce((acc, r) => acc + Number(r.total_monto), 0)
                                                    )}
                                                </td>
                                            </tr>
                                        </>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* TAB 3: Cuentas por Pagar */}
            {tab === 'cuentas_por_pagar' && (
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base font-semibold text-zinc-100">
                            Reporte Consolidado de Cuentas por Pagar
                        </CardTitle>
                        <CardDescription className="text-[13px] text-zinc-400">
                            Comprobantes de pago pendientes de liquidación a la fecha
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-[13px]">
                                <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] uppercase tracking-wider font-semibold text-zinc-400">
                                    <tr>
                                        <th className="px-4 py-3">Fecha</th>
                                        <th className="px-4 py-3">Proveedor</th>
                                        <th className="px-4 py-3">Comprobante</th>
                                        <th className="px-4 py-3">Concepto</th>
                                        <th className="px-4 py-3">Centro Costo</th>
                                        <th className="px-4 py-3">Fecha Vencimiento</th>
                                        <th className="px-4 py-3 text-right">Monto (PEN)</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {filasCuentasPagar.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="py-10 text-center text-[13px] text-zinc-500">
                                                No hay comprobantes pendientes de pago en este momento.
                                            </td>
                                        </tr>
                                    ) : (
                                        <>
                                            {filasCuentasPagar.map((c) => (
                                                <tr key={c.id} className="hover:bg-zinc-900/40 transition-colors">
                                                    <td className="px-4 py-2.5 text-zinc-400">{c.fecha}</td>
                                                    <td className="px-4 py-2.5 font-medium text-zinc-100">{c.proveedor}</td>
                                                    <td className="px-4 py-2.5 text-[12px] text-zinc-400 font-mono">{c.comprobante}</td>
                                                    <td className="px-4 py-2.5 text-zinc-200">{c.concepto}</td>
                                                    <td className="px-4 py-2.5 text-[12px] text-zinc-400">{c.centro_costo}</td>
                                                    <td className="px-4 py-2.5">
                                                        <span
                                                            className={`rounded-md border px-2 py-0.5 text-[11px] font-medium ${
                                                                c.vencida ? 'border-red-900/50 bg-red-950/40 text-red-300' : 'border-zinc-800 bg-zinc-900 text-zinc-300'
                                                            }`}
                                                        >
                                                            {c.fecha_vencimiento}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-2.5 text-right font-medium text-zinc-100 tabular-nums">
                                                        {formatearMonto(Number(c.monto))}
                                                    </td>
                                                </tr>
                                            ))}
                                            <tr className="border-t-2 border-zinc-800 bg-zinc-900/70 font-semibold text-zinc-100">
                                                <td colSpan={6} className="px-4 py-3 text-[12px] uppercase tracking-wider text-zinc-300">
                                                    TOTAL PASIVO PENDIENTE
                                                </td>
                                                <td className="px-4 py-3 text-right text-zinc-100 tabular-nums text-base">
                                                    {formatearMonto(
                                                        filasCuentasPagar.reduce((acc, c) => acc + Number(c.monto), 0)
                                                    )}
                                                </td>
                                            </tr>
                                        </>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* TAB 4: Presupuesto vs Real */}
            {tab === 'presupuesto_real' && datosPresupuesto && (
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base font-semibold text-zinc-100">
                            Presupuesto vs Real: {datosPresupuesto.mes_nombre} {datosPresupuesto.anio}
                        </CardTitle>
                        <CardDescription className="text-[13px] text-zinc-400">
                            Control mensual de ejecución presupuestaria por centro de costo
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-[13px]">
                                <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] uppercase tracking-wider font-semibold text-zinc-400">
                                    <tr>
                                        <th className="px-4 py-3">Código</th>
                                        <th className="px-4 py-3">Centro de Costo</th>
                                        <th className="px-4 py-3">Área</th>
                                        <th className="px-4 py-3 text-right">Presupuestado</th>
                                        <th className="px-4 py-3 text-right">Ejecutado Real</th>
                                        <th className="px-4 py-3 text-right">Diferencia</th>
                                        <th className="px-4 py-3 text-right">% Ejecución</th>
                                        <th className="px-4 py-3 text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {datosPresupuesto.items.map((item) => (
                                        <tr key={item.centro_costo_id} className="hover:bg-zinc-900/40 transition-colors">
                                            <td className="px-4 py-2.5 font-mono text-[12px] text-zinc-400">{item.codigo}</td>
                                            <td className="px-4 py-2.5 font-medium text-zinc-100">{item.nombre}</td>
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
                                                        Advertencia
                                                    </span>
                                                )}
                                                {item.estado === 'ALERTA' && (
                                                    <span className="inline-flex items-center rounded-md border border-amber-800 bg-amber-950/50 px-2 py-0.5 text-[11px] font-medium text-amber-300">
                                                        Alerta
                                                    </span>
                                                )}
                                                {item.estado === 'SOBREGIRADO' && (
                                                    <span className="inline-flex items-center rounded-md border border-red-900/50 bg-red-950/40 px-2 py-0.5 text-[11px] font-medium text-red-400">
                                                        Sobregirado
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    <tr className="border-t-2 border-zinc-800 bg-zinc-900/70 font-semibold text-zinc-100">
                                        <td colSpan={3} className="px-4 py-3 text-[12px] uppercase tracking-wider text-zinc-300">
                                            TOTALES
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums text-zinc-200">
                                            {formatearMonto(datosPresupuesto.totales.presupuesto)}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums text-zinc-100">
                                            {formatearMonto(datosPresupuesto.totales.ejecutado)}
                                        </td>
                                        <td
                                            className={`px-4 py-3 text-right tabular-nums ${
                                                datosPresupuesto.totales.diferencia < 0 ? 'text-red-400' : 'text-emerald-400'
                                            }`}
                                        >
                                            {formatearMonto(datosPresupuesto.totales.diferencia)}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums text-zinc-100">
                                            {datosPresupuesto.totales.porcentaje}%
                                        </td>
                                        <td className="px-4 py-3 text-center text-zinc-500">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}
        </FinanzasLayout>
    );
}
