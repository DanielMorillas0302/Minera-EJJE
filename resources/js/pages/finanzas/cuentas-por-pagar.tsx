import { Paginacion, type Paginado } from '@/components/paginacion';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FinanzasLayout from '@/layouts/finanzas/finanzas-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    Clock,
    RotateCcw,
    Search,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface CuentaItem {
    id: number;
    fecha: string | null;
    monto: number;
    moneda: string;
    concepto: string;
    proveedor_id: number | null;
    proveedor_ruc: string | null;
    proveedor_nombre: string;
    centro_costo_codigo: string;
    centro_costo_nombre: string;
    categoria_nombre: string;
    comprobante_tipo: string;
    comprobante_tipo_etiqueta: string;
    comprobante_numero: string;
    fecha_vencimiento: string;
    fecha_vencimiento_raw: string | null;
    dias_para_vencer: number | null;
    estado_semaforo: 'normal' | 'por_vencer' | 'vencido';
    adjuntos_count: number;
}

interface CuentasPorPagarProps {
    cuentas: Paginado<CuentaItem>;
    proveedores: Array<{ id: number; ruc: string; razon_social: string }>;
    centrosCosto: Array<{ id: number; codigo: string; nombre: string }>;
    totales: {
        total_pendiente: number;
        cantidad_pendiente: number;
        total_vencido: number;
        cantidad_vencido: number;
        total_por_vencer: number;
        cantidad_por_vencer: number;
    };
    filtros: {
        proveedor_id: string;
        centro_costo_id: string;
        estado_vencimiento: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Finanzas', href: '/finanzas' },
    { title: 'Cuentas por Pagar', href: '/finanzas/cuentas-por-pagar' },
];

export default function CuentasPorPagarPage({
    cuentas,
    proveedores,
    centrosCosto,
    totales,
    filtros,
}: CuentasPorPagarProps) {
    const page = usePage<SharedData>();
    const permisos = page.props.auth?.user?.permisos?.movimientos_financieros ?? '';
    const puedePagar = permisos.includes('E');

    const [filtroProveedor, setFiltroProveedor] = useState(filtros.proveedor_id);
    const [filtroCentroCosto, setFiltroCentroCosto] = useState(filtros.centro_costo_id);
    const [filtroVencimiento, setFiltroVencimiento] = useState(filtros.estado_vencimiento);

    const [modalPagoAbierto, setModalPagoAbierto] = useState(false);
    const [cuentaParaPagar, setCuentaParaPagar] = useState<CuentaItem | null>(null);

    const {
        data: formPago,
        setData: setFormPago,
        post: postPago,
        processing: procesandoPago,
        reset: resetFormPago,
    } = useForm({
        fecha_pago: new Date().toISOString().split('T')[0],
        comprobante_numero: '',
    });

    const aplicarFiltros = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            '/finanzas/cuentas-por-pagar',
            {
                proveedor_id: filtroProveedor,
                centro_costo_id: filtroCentroCosto,
                estado_vencimiento: filtroVencimiento,
            },
            { preserveState: true, replace: true },
        );
    };

    const limpiarFiltros = () => {
        setFiltroProveedor('');
        setFiltroCentroCosto('');
        setFiltroVencimiento('');
        router.get('/finanzas/cuentas-por-pagar', {}, { replace: true });
    };

    const abrirModalPagar = (c: CuentaItem) => {
        setCuentaParaPagar(c);
        setFormPago({
            fecha_pago: new Date().toISOString().split('T')[0],
            comprobante_numero: c.comprobante_numero || '',
        });
        setModalPagoAbierto(true);
    };

    const confirmarPago = (e: FormEvent) => {
        e.preventDefault();
        if (!cuentaParaPagar) return;
        postPago(`/finanzas/movimientos/${cuentaParaPagar.id}/pagar`, {
            onSuccess: () => {
                setModalPagoAbierto(false);
                resetFormPago();
            },
        });
    };

    const formatearMonto = (valor: number) => {
        return new Intl.NumberFormat('es-PE', {
            style: 'currency',
            currency: 'PEN',
            minimumFractionDigits: 2,
        }).format(valor);
    };

    return (
        <FinanzasLayout
            breadcrumbs={breadcrumbs}
            titulo="Cuentas por Pagar"
            descripcion="Control de pasivos operativos, comprobantes por liquidar y alertas por fecha de vencimiento."
        >
            {/* Tarjetas KPI de Cuentas por Pagar */}
            <div className="grid gap-4 sm:grid-cols-3">
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                            Total Pendiente por Pagar
                        </CardTitle>
                        <Clock size={16} className="text-zinc-400" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-zinc-100 tabular-nums tracking-tight">
                            {formatearMonto(totales.total_pendiente)}
                        </div>
                        <p className="mt-1 text-[12px] text-zinc-400">
                            {totales.cantidad_pendiente} comprobantes en cartera pendiente
                        </p>
                    </CardContent>
                </Card>

                <Card className={`border ${totales.cantidad_vencido > 0 ? 'border-red-900/50 bg-[#0f0f13]' : 'border-zinc-800 bg-[#0f0f13]'}`}>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-[11px] font-semibold uppercase tracking-wider text-red-400">
                            Comprobantes Vencidos (Urgente)
                        </CardTitle>
                        <AlertCircle size={16} className="text-red-400" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-red-400 tabular-nums tracking-tight">
                            {formatearMonto(totales.total_vencido)}
                        </div>
                        <p className="mt-1 text-[12px] font-medium text-red-300">
                            {totales.cantidad_vencido} facturas con fecha límite sobrepasada
                        </p>
                    </CardContent>
                </Card>

                <Card className={`border ${totales.cantidad_por_vencer > 0 ? 'border-amber-900/50 bg-[#0f0f13]' : 'border-zinc-800 bg-[#0f0f13]'}`}>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-[11px] font-semibold uppercase tracking-wider text-amber-400">
                            Por Vencer (Próximos 7 Días)
                        </CardTitle>
                        <AlertTriangle size={16} className="text-amber-400" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-amber-300 tabular-nums tracking-tight">
                            {formatearMonto(totales.total_por_vencer)}
                        </div>
                        <p className="mt-1 text-[12px] font-medium text-amber-400">
                            {totales.cantidad_por_vencer} facturas programadas a corto plazo
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Barra de Filtros */}
            <Card className="border border-zinc-800 bg-[#0f0f13]">
                <CardHeader className="pb-3">
                    <CardTitle className="text-base font-semibold text-zinc-100">Filtros de Cartera</CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={aplicarFiltros} className="grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Filtrar por Proveedor</Label>
                            <select
                                aria-label="Filtrar por Proveedor"
                                value={filtroProveedor}
                                onChange={(e) => setFiltroProveedor(e.target.value)}
                                className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                <option value="">Todos los proveedores</option>
                                {proveedores.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.razon_social}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Centro de Costo</Label>
                            <select
                                aria-label="Filtrar por Centro de Costo"
                                value={filtroCentroCosto}
                                onChange={(e) => setFiltroCentroCosto(e.target.value)}
                                className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                <option value="">Todos los centros de costo</option>
                                {centrosCosto.map((cc) => (
                                    <option key={cc.id} value={cc.id}>
                                        {cc.codigo} - {cc.nombre}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Semáforo de Vencimiento</Label>
                            <select
                                aria-label="Filtrar por Semáforo de Vencimiento"
                                value={filtroVencimiento}
                                onChange={(e) => setFiltroVencimiento(e.target.value)}
                                className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                <option value="">Todas las pendientes</option>
                                <option value="vencidas">Solo Vencidas (Rojo)</option>
                                <option value="por_vencer">Por vencer en 7 días (Ámbar)</option>
                            </select>
                        </div>
                        <div className="flex items-end gap-2">
                            <Button
                                type="submit"
                                variant="outline"
                                className="h-9 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 text-[13px]"
                            >
                                <Search size={14} className="mr-1.5" /> Filtrar
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={limpiarFiltros}
                                className="h-9 border-zinc-800 bg-zinc-900 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200 text-[13px]"
                            >
                                <RotateCcw size={14} className="mr-1.5" /> Limpiar
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            {/* Tabla de Cuentas por Pagar */}
            <Card className="border border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-[13px]">
                            <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] uppercase tracking-wider font-semibold text-zinc-400">
                                <tr>
                                    <th className="px-3.5 py-3">Proveedor / RUC</th>
                                    <th className="px-3.5 py-3">Comprobante</th>
                                    <th className="px-3.5 py-3">Concepto / Glosa</th>
                                    <th className="px-3.5 py-3">Centro Costo</th>
                                    <th className="px-3.5 py-3">Fecha Vencimiento</th>
                                    <th className="px-3.5 py-3 text-center">Estado Semáforo</th>
                                    <th className="px-3.5 py-3 text-right">Monto (PEN)</th>
                                    <th className="px-3.5 py-3 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {cuentas.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="py-10 text-center text-[13px] text-zinc-500">
                                            No se registran cuentas por pagar con los filtros seleccionados.
                                        </td>
                                    </tr>
                                ) : (
                                    cuentas.data.map((c) => (
                                        <tr
                                            key={c.id}
                                            className="hover:bg-zinc-900/40 transition-colors"
                                        >
                                            <td className="px-3.5 py-3">
                                                <div className="flex flex-col gap-0.5">
                                                    <span className="font-medium text-zinc-100">{c.proveedor_nombre}</span>
                                                    {c.proveedor_ruc && (
                                                        <span className="text-[11px] text-zinc-500 font-mono">RUC: {c.proveedor_ruc}</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-3.5 py-3 text-[12px]">
                                                <div className="font-medium text-zinc-200">
                                                    {c.comprobante_tipo_etiqueta}
                                                </div>
                                                <div className="text-zinc-500 font-mono">{c.comprobante_numero}</div>
                                            </td>
                                            <td className="px-3.5 py-3">
                                                <span className="text-zinc-200">{c.concepto}</span>
                                                <div className="text-[11px] text-zinc-500">{c.categoria_nombre}</div>
                                            </td>
                                            <td className="px-3.5 py-3 text-[12px]">
                                                <span className="font-mono text-zinc-300">{c.centro_costo_codigo}</span>
                                                <div className="text-zinc-500">{c.centro_costo_nombre}</div>
                                            </td>
                                            <td className="px-3.5 py-3 text-[12px]">
                                                <span className="font-medium text-zinc-200">{c.fecha_vencimiento}</span>
                                                {c.fecha && (
                                                    <div className="text-zinc-500 text-[11px]">Emisión: {c.fecha}</div>
                                                )}
                                            </td>
                                            <td className="px-3.5 py-3 text-center">
                                                {c.estado_semaforo === 'vencido' && (
                                                    <span className="inline-flex items-center gap-1 rounded-md border border-red-900/50 bg-red-950/40 px-2 py-0.5 text-[11px] font-medium text-red-300">
                                                        <AlertCircle size={12} />
                                                        Vencida ({Math.abs(c.dias_para_vencer || 0)} d)
                                                    </span>
                                                )}
                                                {c.estado_semaforo === 'por_vencer' && (
                                                    <span className="inline-flex items-center gap-1 rounded-md border border-amber-900/50 bg-amber-950/40 px-2 py-0.5 text-[11px] font-medium text-amber-300">
                                                        <AlertTriangle size={12} />
                                                        Vence en {c.dias_para_vencer} d
                                                    </span>
                                                )}
                                                {c.estado_semaforo === 'normal' && (
                                                    <span className="inline-flex items-center rounded-md border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-medium text-zinc-400">
                                                        Al día ({c.dias_para_vencer !== null ? `${c.dias_para_vencer} d` : 'Sin fecha'})
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3.5 py-3 text-right font-semibold text-zinc-100 tabular-nums">
                                                {formatearMonto(c.monto)}
                                            </td>
                                            <td className="px-3.5 py-3 text-right">
                                                {puedePagar && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() => abrirModalPagar(c)}
                                                        className="h-8 bg-zinc-100 text-zinc-900 font-medium hover:bg-white text-[12px]"
                                                    >
                                                        Registrar Pago
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            {/* Paginación */}
            <Paginacion datos={cuentas} />

            {/* Modal de Pago Rápido */}
            <Dialog open={modalPagoAbierto} onOpenChange={setModalPagoAbierto}>
                <DialogContent className="max-w-md bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold text-zinc-100">Confirmar Pago de Cuenta</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={confirmarPago} className="space-y-4 py-2">
                        <div className="rounded-md border border-zinc-800 bg-[#09090b] p-3 text-[12px] space-y-1">
                            <div>
                                <strong className="text-zinc-400">Proveedor:</strong> <span className="text-zinc-200">{cuentaParaPagar?.proveedor_nombre}</span>
                            </div>
                            <div>
                                <strong className="text-zinc-400">Comprobante:</strong>{' '}
                                <span className="text-zinc-200">{cuentaParaPagar?.comprobante_tipo_etiqueta} {cuentaParaPagar?.comprobante_numero}</span>
                            </div>
                            <div>
                                <strong className="text-zinc-400">Concepto:</strong> <span className="text-zinc-200">{cuentaParaPagar?.concepto}</span>
                            </div>
                            <div className="pt-1 text-[13px] font-semibold text-zinc-100 tabular-nums">
                                Monto a liquidar: {formatearMonto(cuentaParaPagar?.monto || 0)}
                            </div>
                        </div>

                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha en que se liquidó el pago *</Label>
                            <Input
                                type="date"
                                value={formPago.fecha_pago}
                                onChange={(e) => setFormPago('fecha_pago', e.target.value)}
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                required
                            />
                        </div>

                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Número de Operación Bancaria / Comprobante</Label>
                            <Input
                                value={formPago.comprobante_numero}
                                onChange={(e) => setFormPago('comprobante_numero', e.target.value)}
                                placeholder="Ej: Operación BCP 892345"
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                            />
                        </div>

                        <DialogFooter className="gap-2 sm:gap-0 pt-3 border-t border-zinc-800">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalPagoAbierto(false)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={procesandoPago}
                                className="bg-emerald-600/90 text-white hover:bg-emerald-600 font-medium text-[13px]"
                            >
                                {procesandoPago ? 'Procesando...' : 'Confirmar y Marcar Pagado'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </FinanzasLayout>
    );
}
