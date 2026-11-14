import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FinanzasLayout from '@/layouts/finanzas/finanzas-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import {
    Download,
    Edit3,
    FileSpreadsheet,
    Plus,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';

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

interface ItemMatrizAnual {
    centro_costo_id: number;
    codigo: string;
    nombre: string;
    area_nombre: string;
    meses: Record<number, number>;
    total_anual: number;
}

interface PresupuestosProps {
    comparativo: ComparativoData;
    matrizAnual: ItemMatrizAnual[];
    centrosCosto: Array<{ id: number; codigo: string; nombre: string }>;
    filtros: {
        anio: number;
        mes: number;
    };
    aniosDisponibles: number[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Finanzas', href: '/finanzas' },
    { title: 'Presupuestos', href: '/finanzas/presupuestos' },
];

const MESES = [
    { id: 1, nombre: 'Ene' },
    { id: 2, nombre: 'Feb' },
    { id: 3, nombre: 'Mar' },
    { id: 4, nombre: 'Abr' },
    { id: 5, nombre: 'May' },
    { id: 6, nombre: 'Jun' },
    { id: 7, nombre: 'Jul' },
    { id: 8, nombre: 'Ago' },
    { id: 9, nombre: 'Set' },
    { id: 10, nombre: 'Oct' },
    { id: 11, nombre: 'Nov' },
    { id: 12, nombre: 'Dic' },
];

export default function PresupuestosPage({
    comparativo,
    matrizAnual,
    centrosCosto,
    filtros,
    aniosDisponibles,
}: PresupuestosProps) {
    const page = usePage<SharedData>();
    const permisos = page.props.auth?.user?.permisos?.presupuesto ?? '';
    const puedeEditar = permisos.includes('C') || permisos.includes('E');

    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalLoteAbierto, setModalLoteAbierto] = useState(false);

    // Formulario Presupuesto Individual
    const {
        data: formPresupuesto,
        setData: setFormPresupuesto,
        post: postPresupuesto,
        processing: guardando,
        errors,
        reset: resetPresupuesto,
    } = useForm({
        centro_costo_id: centrosCosto[0]?.id ?? 0,
        anio: filtros.anio,
        mes: filtros.mes,
        monto_presupuestado: '',
        notas: '',
    });

    // Formulario Ajuste Masivo por Lote
    const {
        data: formLote,
        setData: setFormLote,
        post: postLote,
        processing: guardandoLote,
    } = useForm({
        anio: filtros.anio,
        mes: filtros.mes,
        presupuestos: centrosCosto.map((cc) => {
            const itemActual = comparativo.items.find((i) => i.centro_costo_id === cc.id);
            return {
                centro_costo_id: cc.id,
                monto: itemActual ? itemActual.presupuesto : 0,
            };
        }),
    });

    const formatearMonto = (valor: number) => {
        return new Intl.NumberFormat('es-PE', {
            style: 'currency',
            currency: 'PEN',
            minimumFractionDigits: 2,
        }).format(valor);
    };

    const cambiarPeriodo = (nuevoAnio: number, nuevoMes: number) => {
        router.get(
            '/finanzas/presupuestos',
            { anio: nuevoAnio, mes: nuevoMes },
            { preserveState: true, replace: true },
        );
    };

    const abrirModalNuevo = (item?: ItemPresupuesto) => {
        if (item) {
            setFormPresupuesto({
                centro_costo_id: item.centro_costo_id,
                anio: filtros.anio,
                mes: filtros.mes,
                monto_presupuestado: String(item.presupuesto),
                notas: '',
            });
        } else {
            resetPresupuesto();
            setFormPresupuesto({
                centro_costo_id: centrosCosto[0]?.id ?? 0,
                anio: filtros.anio,
                mes: filtros.mes,
                monto_presupuestado: '',
                notas: '',
            });
        }
        setModalAbierto(true);
    };

    const abrirModalLote = () => {
        setFormLote({
            anio: filtros.anio,
            mes: filtros.mes,
            presupuestos: centrosCosto.map((cc) => {
                const itemActual = comparativo.items.find((i) => i.centro_costo_id === cc.id);
                return {
                    centro_costo_id: cc.id,
                    monto: itemActual ? itemActual.presupuesto : 0,
                };
            }),
        });
        setModalLoteAbierto(true);
    };

    const guardarPresupuesto = (e: FormEvent) => {
        e.preventDefault();
        postPresupuesto('/finanzas/presupuestos', {
            onSuccess: () => {
                setModalAbierto(false);
                resetPresupuesto();
            },
        });
    };

    const guardarLotePresupuestos = (e: FormEvent) => {
        e.preventDefault();
        postLote('/finanzas/presupuestos/lote', {
            onSuccess: () => {
                setModalLoteAbierto(false);
            },
        });
    };

    return (
        <FinanzasLayout
            breadcrumbs={breadcrumbs}
            titulo="Gestión de Presupuestos Operativos"
            descripcion="Control mensual y matriz anual de techos presupuestarios por Centro de Costo (Trujillo y Pataz)."
        >
            {/* Barra de Filtros y Acciones */}
            <div className="flex flex-col gap-3 rounded-lg border border-zinc-800 bg-[#0f0f13] p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-3">
                    <div>
                        <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Año fiscal</Label>
                        <select
                            aria-label="Año fiscal"
                            value={filtros.anio}
                            onChange={(e) => cambiarPeriodo(Number(e.target.value), filtros.mes)}
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
                        <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Mes a evaluar</Label>
                        <select
                            aria-label="Mes a evaluar"
                            value={filtros.mes}
                            onChange={(e) => cambiarPeriodo(filtros.anio, Number(e.target.value))}
                            className="mt-1 block h-9 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                        >
                            {MESES.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.nombre} (Mes {m.id})
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <a
                        href={`/finanzas/reportes/presupuesto-pdf?anio=${filtros.anio}&mes=${filtros.mes}`}
                        className="inline-flex h-9 items-center gap-1.5 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[12px] font-medium text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 transition-colors"
                    >
                        <Download size={14} /> Exportar PDF
                    </a>
                    {puedeEditar && (
                        <>
                            <Button
                                variant="outline"
                                onClick={abrirModalLote}
                                className="h-9 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 text-[12px]"
                            >
                                <FileSpreadsheet size={14} className="mr-1.5 text-zinc-400" /> Ajuste Masivo Mes
                            </Button>
                            <Button
                                onClick={() => abrirModalNuevo()}
                                className="h-9 bg-zinc-100 text-zinc-900 font-medium px-4 py-2 rounded-md hover:bg-white text-[13px]"
                            >
                                <Plus size={16} className="mr-1.5" /> Asignar Presupuesto
                            </Button>
                        </>
                    )}
                </div>
            </div>

            {/* Resumen KPI del Mes Evaluado */}
            <div className="grid gap-4 sm:grid-cols-4">
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                            Presupuesto Mensual Aprobado
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold tabular-nums text-zinc-100 tracking-tight">
                            {formatearMonto(comparativo.totales.presupuesto)}
                        </div>
                        <p className="mt-1 text-[12px] text-zinc-400">{comparativo.mes_nombre} {comparativo.anio}</p>
                    </CardContent>
                </Card>

                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                            Gasto Real Ejecutado
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold tabular-nums text-zinc-100 tracking-tight">
                            {formatearMonto(comparativo.totales.ejecutado)}
                        </div>
                        <p className="mt-1 text-[12px] text-zinc-400">Egresos registrados en el mes</p>
                    </CardContent>
                </Card>

                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                            Variación Presupuestal (Saldo)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            className={`text-2xl font-bold tabular-nums tracking-tight ${
                                comparativo.totales.diferencia < 0 ? 'text-red-400' : 'text-emerald-400'
                            }`}
                        >
                            {formatearMonto(comparativo.totales.diferencia)}
                        </div>
                        <p className="mt-1 text-[12px] text-zinc-400">
                            {comparativo.totales.diferencia < 0 ? 'Sobregiro presupuestario' : 'Saldo remanente disponible'}
                        </p>
                    </CardContent>
                </Card>

                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                            % Ejecución Presupuestal
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold tabular-nums text-zinc-100 tracking-tight">
                            {comparativo.totales.porcentaje}%
                        </div>
                        <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-zinc-900 border border-zinc-800">
                            <div
                                className={`h-full ${
                                    comparativo.totales.porcentaje > 100
                                        ? 'bg-red-500'
                                        : comparativo.totales.porcentaje > 90
                                        ? 'bg-amber-500'
                                        : 'bg-blue-500'
                                }`}
                                style={{ width: `${Math.min(comparativo.totales.porcentaje, 100)}%` }}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Tabla Comparativa: Presupuesto vs Real del Mes */}
            <Card className="border border-zinc-800 bg-[#0f0f13]">
                <CardHeader className="pb-3">
                    <CardTitle className="text-base font-semibold text-zinc-100">
                        Presupuesto vs Real – {comparativo.mes_nombre} {comparativo.anio}
                    </CardTitle>
                    <CardDescription className="text-[13px] text-zinc-400">
                        Desglose por Centro de Costo con indicadores de control y alertas tempranas
                    </CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-[13px]">
                            <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] uppercase tracking-wider font-semibold text-zinc-400">
                                <tr>
                                    <th className="px-3.5 py-3">Código</th>
                                    <th className="px-3.5 py-3">Centro de Costo</th>
                                    <th className="px-3.5 py-3">Área</th>
                                    <th className="px-3.5 py-3 text-right">Presupuesto</th>
                                    <th className="px-3.5 py-3 text-right">Ejecutado</th>
                                    <th className="px-3.5 py-3 text-right">Diferencia</th>
                                    <th className="px-3.5 py-3 text-right">% Avance</th>
                                    <th className="px-3.5 py-3 text-center">Estado</th>
                                    {puedeEditar && <th className="px-3.5 py-3 text-right">Acción</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {comparativo.items.map((item) => (
                                    <tr key={item.centro_costo_id} className="hover:bg-zinc-900/40 transition-colors">
                                        <td className="px-3.5 py-2.5 font-mono text-[12px] text-zinc-400">{item.codigo}</td>
                                        <td className="px-3.5 py-2.5 font-medium text-zinc-100">{item.nombre}</td>
                                        <td className="px-3.5 py-2.5 text-[12px] text-zinc-400">{item.area_nombre}</td>
                                        <td className="px-3.5 py-2.5 text-right font-medium text-zinc-300 tabular-nums">
                                            {formatearMonto(item.presupuesto)}
                                        </td>
                                        <td className="px-3.5 py-2.5 text-right font-medium text-zinc-100 tabular-nums">
                                            {formatearMonto(item.ejecutado)}
                                        </td>
                                        <td
                                            className={`px-3.5 py-2.5 text-right font-medium tabular-nums ${
                                                item.diferencia < 0 ? 'text-red-400' : 'text-emerald-400'
                                            }`}
                                        >
                                            {formatearMonto(item.diferencia)}
                                        </td>
                                        <td className="px-3.5 py-2.5 text-right font-medium tabular-nums text-zinc-300">
                                            {item.porcentaje}%
                                        </td>
                                        <td className="px-3.5 py-2.5 text-center">
                                            {item.estado === 'NORMAL' && (
                                                <span className="inline-flex items-center rounded-md border border-emerald-900/50 bg-emerald-950/30 px-2 py-0.5 text-[11px] font-medium text-emerald-400">
                                                    Normal (&lt;80%)
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
                                        {puedeEditar && (
                                            <td className="px-3.5 py-2.5 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => abrirModalNuevo(item)}
                                                    className="h-8 w-8 p-0 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800"
                                                    title="Editar presupuesto"
                                                >
                                                    <Edit3 size={14} />
                                                </Button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            {/* Matriz Anual: Vista de 12 Meses */}
            <Card className="border border-zinc-800 bg-[#0f0f13]">
                <CardHeader className="pb-3">
                    <CardTitle className="text-base font-semibold text-zinc-100">
                        Matriz Anual de Presupuestos ({filtros.anio})
                    </CardTitle>
                    <CardDescription className="text-[13px] text-zinc-400">
                        Consolidado mensual asignado a lo largo del año completo
                    </CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-[12px]">
                            <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] uppercase tracking-wider font-semibold text-zinc-400">
                                <tr>
                                    <th className="px-3 py-2.5">Código</th>
                                    <th className="px-3 py-2.5">Centro de Costo</th>
                                    {MESES.map((m) => (
                                        <th key={m.id} className="px-2 py-2.5 text-right">
                                            {m.nombre}
                                        </th>
                                    ))}
                                    <th className="px-3 py-2.5 text-right font-semibold text-zinc-200">Total Anual</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {matrizAnual.map((row) => (
                                    <tr key={row.centro_costo_id} className="hover:bg-zinc-900/40 transition-colors">
                                        <td className="px-3 py-2 font-mono text-zinc-400">{row.codigo}</td>
                                        <td className="px-3 py-2 font-medium text-zinc-200">{row.nombre}</td>
                                        {MESES.map((m) => (
                                            <td key={m.id} className="px-2 py-2 text-right tabular-nums text-zinc-400">
                                                {(row.meses[m.id] ?? 0) > 0 ? (row.meses[m.id] / 1000).toFixed(1) + 'k' : '—'}
                                            </td>
                                        ))}
                                        <td className="px-3 py-2 text-right font-medium tabular-nums text-zinc-100">
                                            {formatearMonto(row.total_anual)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            {/* Modal: Asignar Presupuesto Individual */}
            <Dialog open={modalAbierto} onOpenChange={setModalAbierto}>
                <DialogContent className="max-w-md bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold text-zinc-100">Asignar Presupuesto Mensual</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={guardarPresupuesto} className="space-y-4 py-2">
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Centro de Costo *</Label>
                            <select
                                aria-label="Centro de Costo"
                                value={formPresupuesto.centro_costo_id}
                                onChange={(e) => setFormPresupuesto('centro_costo_id', Number(e.target.value))}
                                className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                {centrosCosto.map((cc) => (
                                    <option key={cc.id} value={cc.id}>
                                        {cc.codigo} - {cc.nombre}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Año *</Label>
                                <Input
                                    type="number"
                                    value={formPresupuesto.anio}
                                    onChange={(e) => setFormPresupuesto('anio', Number(e.target.value))}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                    required
                                />
                            </div>
                            <div>
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Mes *</Label>
                                <select
                                    aria-label="Mes"
                                    value={formPresupuesto.mes}
                                    onChange={(e) => setFormPresupuesto('mes', Number(e.target.value))}
                                    className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {MESES.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.nombre} ({m.id})
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Monto Presupuestado (S/) *</Label>
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                value={formPresupuesto.monto_presupuestado}
                                onChange={(e) => setFormPresupuesto('monto_presupuestado', e.target.value)}
                                placeholder="0.00"
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-100 font-semibold"
                                required
                            />
                            {errors.monto_presupuestado && (
                                <p className="mt-1 text-[12px] text-red-400">{errors.monto_presupuestado}</p>
                            )}
                        </div>

                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Notas u observaciones</Label>
                            <Input
                                value={formPresupuesto.notas}
                                onChange={(e) => setFormPresupuesto('notas', e.target.value)}
                                placeholder="Justificación o resolución de directorio"
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                            />
                        </div>

                        <DialogFooter className="gap-2 sm:gap-0 pt-2 border-t border-zinc-800">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalAbierto(false)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={guardando}
                                className="bg-zinc-100 text-zinc-900 font-medium hover:bg-white text-[13px]"
                            >
                                {guardando ? 'Guardando...' : 'Guardar Presupuesto'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal: Ajuste Masivo en Lote */}
            <Dialog open={modalLoteAbierto} onOpenChange={setModalLoteAbierto}>
                <DialogContent className="max-w-lg max-h-[85vh] overflow-y-auto bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold text-zinc-100">
                            Ajuste Masivo de Presupuesto ({comparativo.mes_nombre} {filtros.anio})
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={guardarLotePresupuestos} className="space-y-3 py-2">
                        <p className="text-[13px] text-zinc-400">
                            Ingresa los montos presupuestados para cada centro de costo para el mes seleccionado:
                        </p>
                        <div className="space-y-2">
                            {centrosCosto.map((cc) => {
                                const val = formLote.presupuestos.find((p) => p.centro_costo_id === cc.id)?.monto ?? 0;
                                return (
                                    <div key={cc.id} className="flex items-center justify-between gap-3 rounded-md border border-zinc-800 bg-[#09090b] p-2.5 text-[12px]">
                                        <div className="flex flex-col">
                                            <span className="font-medium text-zinc-200">{cc.codigo}</span>
                                            <span className="text-[11px] text-zinc-500">{cc.nombre}</span>
                                        </div>
                                        <div className="w-36">
                                            <Input
                                                type="number"
                                                step="100"
                                                min="0"
                                                value={val}
                                                onChange={(e) => {
                                                    const nuevoValor = Number(e.target.value);
                                                    const copia = [...formLote.presupuestos];
                                                    const found = copia.find((p) => p.centro_costo_id === cc.id);
                                                    if (found) {
                                                        found.monto = nuevoValor;
                                                    } else {
                                                        copia.push({ centro_costo_id: cc.id, monto: nuevoValor });
                                                    }
                                                    setFormLote('presupuestos', copia);
                                                }}
                                                className="h-8 bg-zinc-900 border-zinc-800 text-[12px] text-right font-medium text-zinc-100"
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <DialogFooter className="gap-2 sm:gap-0 pt-3 border-t border-zinc-800">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalLoteAbierto(false)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={guardandoLote}
                                className="bg-zinc-100 text-zinc-900 font-medium hover:bg-white text-[13px]"
                            >
                                {guardandoLote ? 'Guardando...' : 'Actualizar Todos'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </FinanzasLayout>
    );
}
