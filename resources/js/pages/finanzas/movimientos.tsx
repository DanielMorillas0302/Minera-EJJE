import { Paginacion, type Paginado } from '@/components/paginacion';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FinanzasLayout from '@/layouts/finanzas/finanzas-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    Paperclip,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Trash2,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface AdjuntoItem {
    id: number;
    nombre_original: string;
    tamano: number;
    mime_type: string;
    descripcion: string | null;
}

interface MovimientoItem {
    id: number;
    tipo: 'INGRESO' | 'EGRESO';
    tipo_etiqueta: string;
    fecha: string;
    fecha_fmt: string;
    monto: number;
    moneda: string;
    categoria_id: number;
    categoria_nombre: string;
    centro_costo_id: number;
    centro_costo_codigo: string;
    centro_costo_nombre: string;
    proveedor_id: number | null;
    proveedor_nombre: string;
    concepto: string;
    comprobante_tipo: string;
    comprobante_tipo_etiqueta: string;
    comprobante_numero: string | null;
    estado_pago: 'PENDIENTE' | 'PAGADO';
    estado_pago_etiqueta: string;
    fecha_vencimiento: string | null;
    fecha_vencimiento_fmt: string | null;
    fecha_pago: string | null;
    fecha_pago_fmt: string | null;
    envio_id: number | null;
    envio_codigo: string | null;
    orden_compra_id: number | null;
    trabajador_id: number | null;
    trabajador_nombre: string | null;
    observaciones: string | null;
    adjuntos_count: number;
    adjuntos: AdjuntoItem[];
}

interface MovimientosProps {
    movimientos: Paginado<MovimientoItem>;
    centrosCosto: Array<{ id: number; codigo: string; nombre: string }>;
    categorias: Array<{ id: number; nombre: string; tipo: string }>;
    proveedores: Array<{ id: number; ruc: string; razon_social: string }>;
    envios: Array<{ id: number; codigo: string; guia_remision: string | null; estado: string }>;
    trabajadores: Array<{ id: number; dni: string; nombres: string; apellidos: string }>;
    tiposComprobante: string[];
    filtros: {
        buscar: string;
        tipo: string;
        centro_costo_id: string;
        categoria_id: string;
        estado_pago: string;
        desde: string;
        hasta: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Finanzas', href: '/finanzas' },
    { title: 'Movimientos', href: '/finanzas/movimientos' },
];

export default function MovimientosPage({
    movimientos,
    centrosCosto,
    categorias,
    proveedores,
    envios,
    trabajadores,
    tiposComprobante,
    filtros,
}: MovimientosProps) {
    const page = usePage<SharedData>();
    const permisos = page.props.auth?.user?.permisos?.movimientos_financieros ?? '';
    const puedeCrear = permisos.includes('C');
    const puedeEditar = permisos.includes('E');

    // Estado filtros
    const [busqueda, setBusqueda] = useState(filtros.buscar || '');
    const [filtroTipo, setFiltroTipo] = useState(filtros.tipo || '');
    const [filtroCentroCosto, setFiltroCentroCosto] = useState(filtros.centro_costo_id || '');
    const [filtroCategoria, setFiltroCategoria] = useState(filtros.categoria_id || '');
    const [filtroEstadoPago, setFiltroEstadoPago] = useState(filtros.estado_pago || '');
    const [filtroDesde, setFiltroDesde] = useState(filtros.desde || '');
    const [filtroHasta, setFiltroHasta] = useState(filtros.hasta || '');

    // Modales
    const [modalMovimientoAbierto, setModalMovimientoAbierto] = useState(false);
    const [movimientoEnEdicion, setMovimientoEnEdicion] = useState<MovimientoItem | null>(null);

    const [modalPagoAbierto, setModalPagoAbierto] = useState(false);
    const [movimientoParaPagar, setMovimientoParaPagar] = useState<MovimientoItem | null>(null);

    const [modalAdjuntosAbierto, setModalAdjuntosAbierto] = useState(false);
    const [movimientoParaAdjunto, setMovimientoParaAdjunto] = useState<MovimientoItem | null>(null);

    // Formulario Registro / Edición
    const {
        data: formMov,
        setData: setFormMov,
        post: postMov,
        put: putMov,
        processing: guardandoMov,
        errors: erroresMov,
        reset: resetFormMov,
    } = useForm({
        tipo: 'EGRESO',
        fecha: new Date().toISOString().split('T')[0],
        monto: '' as number | string,
        moneda: 'PEN',
        categoria_id: (categorias[0]?.id ?? '') as number | string,
        centro_costo_id: (centrosCosto[0]?.id ?? '') as number | string,
        proveedor_id: '' as number | string,
        concepto: '',
        comprobante_tipo: 'FACTURA',
        comprobante_numero: '',
        estado_pago: 'PAGADO',
        fecha_vencimiento: '',
        fecha_pago: new Date().toISOString().split('T')[0],
        envio_id: '' as number | string,
        trabajador_id: '' as number | string,
        observaciones: '',
        archivo: null as File | null,
        descripcion_adjunto: '',
    });

    // Formulario Pago
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

    // Formulario Subida Adjunto
    const {
        data: formAdjunto,
        setData: setFormAdjunto,
        post: postAdjunto,
        processing: subiendoAdjunto,
        errors: erroresAdjunto,
        reset: resetFormAdjunto,
    } = useForm({
        archivo: null as File | null,
        descripcion: '',
    });

    const aplicarFiltros = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            '/finanzas/movimientos',
            {
                buscar: busqueda || undefined,
                tipo: filtroTipo || undefined,
                centro_costo_id: filtroCentroCosto || undefined,
                categoria_id: filtroCategoria || undefined,
                estado_pago: filtroEstadoPago || undefined,
                desde: filtroDesde || undefined,
                hasta: filtroHasta || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const limpiarFiltros = () => {
        setBusqueda('');
        setFiltroTipo('');
        setFiltroCentroCosto('');
        setFiltroCategoria('');
        setFiltroEstadoPago('');
        setFiltroDesde('');
        setFiltroHasta('');
        router.get('/finanzas/movimientos', {}, { replace: true });
    };

    const abrirCrear = () => {
        setMovimientoEnEdicion(null);
        resetFormMov();
        setFormMov({
            tipo: 'EGRESO',
            fecha: new Date().toISOString().split('T')[0],
            monto: '',
            moneda: 'PEN',
            categoria_id: categorias[0]?.id ?? '',
            centro_costo_id: centrosCosto[0]?.id ?? '',
            proveedor_id: '',
            concepto: '',
            comprobante_tipo: 'FACTURA',
            comprobante_numero: '',
            estado_pago: 'PAGADO',
            fecha_vencimiento: '',
            fecha_pago: new Date().toISOString().split('T')[0],
            envio_id: '',
            trabajador_id: '',
            observaciones: '',
            archivo: null,
            descripcion_adjunto: '',
        });
        setModalMovimientoAbierto(true);
    };

    const abrirEditar = (m: MovimientoItem) => {
        setMovimientoEnEdicion(m);
        setFormMov({
            tipo: m.tipo,
            fecha: m.fecha,
            monto: m.monto,
            moneda: m.moneda,
            categoria_id: m.categoria_id,
            centro_costo_id: m.centro_costo_id,
            proveedor_id: m.proveedor_id ?? '',
            concepto: m.concepto,
            comprobante_tipo: m.comprobante_tipo,
            comprobante_numero: m.comprobante_numero ?? '',
            estado_pago: m.estado_pago,
            fecha_vencimiento: m.fecha_vencimiento ?? '',
            fecha_pago: m.fecha_pago ?? '',
            envio_id: m.envio_id ?? '',
            trabajador_id: m.trabajador_id ?? '',
            observaciones: m.observaciones ?? '',
            archivo: null,
            descripcion_adjunto: '',
        });
        setModalMovimientoAbierto(true);
    };

    const guardarMovimiento = (e: FormEvent) => {
        e.preventDefault();
        if (movimientoEnEdicion) {
            putMov(`/finanzas/movimientos/${movimientoEnEdicion.id}`, {
                onSuccess: () => {
                    setModalMovimientoAbierto(false);
                    resetFormMov();
                },
            });
        } else {
            postMov('/finanzas/movimientos', {
                forceFormData: true,
                onSuccess: () => {
                    setModalMovimientoAbierto(false);
                    resetFormMov();
                },
            });
        }
    };

    const eliminarMovimiento = (m: MovimientoItem) => {
        if (confirm(`¿Estás seguro de eliminar el movimiento "${m.concepto}" de S/ ${m.monto.toFixed(2)}?`)) {
            router.delete(`/finanzas/movimientos/${m.id}`);
        }
    };

    const abrirPagar = (m: MovimientoItem) => {
        setMovimientoParaPagar(m);
        setFormPago({
            fecha_pago: new Date().toISOString().split('T')[0],
            comprobante_numero: m.comprobante_numero ?? '',
        });
        setModalPagoAbierto(true);
    };

    const confirmarPago = (e: FormEvent) => {
        e.preventDefault();
        if (!movimientoParaPagar) return;
        postPago(`/finanzas/movimientos/${movimientoParaPagar.id}/pagar`, {
            onSuccess: () => {
                setModalPagoAbierto(false);
                resetFormPago();
            },
        });
    };

    const abrirAdjuntos = (m: MovimientoItem) => {
        setMovimientoParaAdjunto(m);
        resetFormAdjunto();
        setModalAdjuntosAbierto(true);
    };

    const subirComprobante = (e: FormEvent) => {
        e.preventDefault();
        if (!movimientoParaAdjunto) return;
        postAdjunto(`/finanzas/movimientos/${movimientoParaAdjunto.id}/adjuntos`, {
            forceFormData: true,
            onSuccess: () => {
                resetFormAdjunto();
                setModalAdjuntosAbierto(false);
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
            titulo="Movimientos Financieros"
            descripcion="Registro y control de ingresos, gastos operacionales, comprobantes de pago y vínculos logísticos."
        >
            {/* Barra superior con Filtros y Botón Nuevo */}
            <Card className="border border-zinc-800 bg-[#0f0f13]">
                <CardHeader className="pb-3">
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <CardTitle className="text-base font-semibold text-zinc-100">Filtros de Búsqueda</CardTitle>
                        {puedeCrear && (
                            <Button
                                onClick={abrirCrear}
                                className="h-9 bg-zinc-100 text-zinc-900 font-medium px-4 py-2 rounded-md hover:bg-white text-[13px]"
                            >
                                <Plus size={16} className="mr-1.5" /> Registrar Movimiento
                            </Button>
                        )}
                    </div>
                </CardHeader>
                <CardContent>
                    <form onSubmit={aplicarFiltros} className="grid gap-3 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7">
                        <div className="lg:col-span-2">
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Buscar concepto / comprobante / RUC</Label>
                            <Input
                                value={busqueda}
                                onChange={(e) => setBusqueda(e.target.value)}
                                placeholder="Concepto, F001-..., proveedor..."
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Tipo</Label>
                            <select
                                aria-label="Filtrar por tipo"
                                value={filtroTipo}
                                onChange={(e) => setFiltroTipo(e.target.value)}
                                className="mt-1 h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-2.5 text-[13px] text-zinc-300 focus:outline-none focus:border-blue-500"
                            >
                                <option value="">Todos los tipos</option>
                                <option value="INGRESO">Ingresos (+)</option>
                                <option value="EGRESO">Egresos (-)</option>
                            </select>
                        </div>
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Centro de Costo</Label>
                            <select
                                aria-label="Filtrar por centro de costo"
                                value={filtroCentroCosto}
                                onChange={(e) => setFiltroCentroCosto(e.target.value)}
                                className="mt-1 h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-2.5 text-[13px] text-zinc-300 focus:outline-none focus:border-blue-500"
                            >
                                <option value="">Todos los centros</option>
                                {centrosCosto.map((cc) => (
                                    <option key={cc.id} value={cc.id}>
                                        {cc.codigo} - {cc.nombre}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Estado Pago</Label>
                            <select
                                aria-label="Filtrar por estado de pago"
                                value={filtroEstadoPago}
                                onChange={(e) => setFiltroEstadoPago(e.target.value)}
                                className="mt-1 h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-2.5 text-[13px] text-zinc-300 focus:outline-none focus:border-blue-500"
                            >
                                <option value="">Todos los estados</option>
                                <option value="PAGADO">Pagado</option>
                                <option value="PENDIENTE">Pendiente</option>
                            </select>
                        </div>
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Desde</Label>
                            <Input
                                type="date"
                                value={filtroDesde}
                                onChange={(e) => setFiltroDesde(e.target.value)}
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                            />
                        </div>
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Hasta</Label>
                            <Input
                                type="date"
                                value={filtroHasta}
                                onChange={(e) => setFiltroHasta(e.target.value)}
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                            />
                        </div>
                        <div className="flex items-end gap-2 lg:col-span-7 pt-1">
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

            {/* Listado de Movimientos */}
            <Card className="border border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-[13px]">
                            <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] uppercase tracking-wider font-semibold text-zinc-400">
                                <tr>
                                    <th className="px-3.5 py-3">Tipo / Fecha</th>
                                    <th className="px-3.5 py-3">Concepto / Comprobante</th>
                                    <th className="px-3.5 py-3">Centro Costo / Categoría</th>
                                    <th className="px-3.5 py-3">Entidad / Vínculo</th>
                                    <th className="px-3.5 py-3 text-right">Monto</th>
                                    <th className="px-3.5 py-3 text-center">Estado Pago</th>
                                    <th className="px-3.5 py-3 text-center">Adjuntos</th>
                                    <th className="px-3.5 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {movimientos.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="py-10 text-center text-[13px] text-zinc-500">
                                            No se encontraron movimientos financieros con los filtros aplicados.
                                        </td>
                                    </tr>
                                ) : (
                                    movimientos.data.map((m) => (
                                        <tr key={m.id} className="hover:bg-zinc-900/40 transition-colors">
                                            <td className="px-3.5 py-3">
                                                <div className="flex flex-col gap-1">
                                                    <span
                                                        className={`inline-flex w-fit items-center rounded-md px-2 py-0.5 text-[11px] font-medium border ${
                                                            m.tipo === 'INGRESO'
                                                                ? 'border-emerald-900/50 bg-emerald-950/40 text-emerald-300'
                                                                : 'border-red-900/50 bg-red-950/40 text-red-300'
                                                        }`}
                                                    >
                                                        {m.tipo_etiqueta}
                                                    </span>
                                                    <span className="text-[11px] text-zinc-500">{m.fecha_fmt}</span>
                                                </div>
                                            </td>
                                            <td className="px-3.5 py-3">
                                                <div className="flex flex-col gap-0.5">
                                                    <span className="font-medium text-zinc-100">{m.concepto}</span>
                                                    <span className="text-[11px] text-zinc-400 font-mono">
                                                        {m.comprobante_tipo_etiqueta}: {m.comprobante_numero || 'Sin número'}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-3.5 py-3">
                                                <div className="flex flex-col gap-0.5">
                                                    <span className="font-medium text-zinc-200">
                                                        {m.centro_costo_codigo} - {m.centro_costo_nombre}
                                                    </span>
                                                    <span className="text-[11px] text-zinc-500">{m.categoria_nombre}</span>
                                                </div>
                                            </td>
                                            <td className="px-3.5 py-3 text-[12px]">
                                                {m.proveedor_nombre !== '—' && (
                                                    <div className="text-zinc-300">Prov: {m.proveedor_nombre}</div>
                                                )}
                                                {m.envio_codigo && (
                                                    <div className="font-medium text-blue-400">Envío: {m.envio_codigo}</div>
                                                )}
                                                {m.trabajador_nombre && (
                                                    <div className="text-zinc-400">Trab: {m.trabajador_nombre}</div>
                                                )}
                                                {!m.proveedor_nombre && !m.envio_codigo && !m.trabajador_nombre && (
                                                    <span className="text-zinc-600">—</span>
                                                )}
                                            </td>
                                            <td className="px-3.5 py-3 text-right">
                                                <span
                                                    className={`text-[13px] font-semibold tabular-nums ${
                                                        m.tipo === 'INGRESO' ? 'text-emerald-400' : 'text-zinc-100'
                                                    }`}
                                                >
                                                    {m.tipo === 'INGRESO' ? '+' : '-'} {formatearMonto(m.monto)}
                                                </span>
                                            </td>
                                            <td className="px-3.5 py-3 text-center">
                                                {m.estado_pago === 'PAGADO' ? (
                                                    <span className="inline-flex items-center rounded-md border border-emerald-900/50 bg-emerald-950/30 px-2 py-0.5 text-[11px] font-medium text-emerald-400">
                                                        Pagado
                                                    </span>
                                                ) : (
                                                    <div className="flex flex-col items-center gap-1">
                                                        <span className="inline-flex items-center rounded-md border border-amber-900/50 bg-amber-950/30 px-2 py-0.5 text-[11px] font-medium text-amber-300">
                                                            Pendiente
                                                        </span>
                                                        {m.fecha_vencimiento_fmt && (
                                                            <span className="text-[10px] text-red-400 font-mono">
                                                                Vence: {m.fecha_vencimiento_fmt}
                                                            </span>
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-3.5 py-3 text-center">
                                                <button
                                                    onClick={() => abrirAdjuntos(m)}
                                                    className="inline-flex items-center gap-1 rounded-md border border-zinc-800 bg-zinc-900 px-2 py-1 text-[12px] text-zinc-300 hover:bg-zinc-800 transition-colors"
                                                >
                                                    <Paperclip size={13} className="text-zinc-400" />
                                                    <span>{m.adjuntos_count}</span>
                                                </button>
                                            </td>
                                            <td className="px-3.5 py-3 text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    {puedeEditar && m.estado_pago === 'PENDIENTE' && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-8 border-emerald-900/50 bg-emerald-950/30 text-emerald-300 hover:bg-emerald-900/50"
                                                            onClick={() => abrirPagar(m)}
                                                            title="Marcar como pagado"
                                                        >
                                                            <CheckCircle2 size={14} />
                                                        </Button>
                                                    )}
                                                    {puedeEditar && (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="h-8 w-8 p-0 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800"
                                                            onClick={() => abrirEditar(m)}
                                                            title="Editar movimiento"
                                                        >
                                                            <Pencil size={14} />
                                                        </Button>
                                                    )}
                                                    {puedeEditar && (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="h-8 w-8 p-0 text-red-400 hover:text-red-300 hover:bg-red-950/30"
                                                            onClick={() => eliminarMovimiento(m)}
                                                            title="Eliminar movimiento"
                                                        >
                                                            <Trash2 size={14} />
                                                        </Button>
                                                    )}
                                                </div>
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
            <Paginacion datos={movimientos} />

            {/* Modal: Registrar o Editar Movimiento */}
            <Dialog open={modalMovimientoAbierto} onOpenChange={setModalMovimientoAbierto}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold text-zinc-100">
                            {movimientoEnEdicion ? 'Editar Movimiento Financiero' : 'Registrar Nuevo Movimiento Financiero'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={guardarMovimiento} className="space-y-4 py-2">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Tipo de Movimiento *</Label>
                                <select
                                    aria-label="Tipo de Movimiento"
                                    value={formMov.tipo}
                                    onChange={(e) => setFormMov('tipo', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    <option value="EGRESO">Egreso (Gasto / Pago)</option>
                                    <option value="INGRESO">Ingreso (Venta / Cobro)</option>
                                </select>
                            </div>
                            <div>
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha de Operación *</Label>
                                <Input
                                    type="date"
                                    value={formMov.fecha}
                                    onChange={(e) => setFormMov('fecha', e.target.value)}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                                {erroresMov.fecha && <p className="mt-1 text-[12px] text-red-400">{erroresMov.fecha}</p>}
                            </div>
                            <div>
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Monto (S/) *</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    placeholder="0.00"
                                    value={formMov.monto}
                                    onChange={(e) => setFormMov('monto', e.target.value)}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-100 font-semibold"
                                />
                                {erroresMov.monto && <p className="mt-1 text-[12px] text-red-400">{erroresMov.monto}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Centro de Costo *</Label>
                                <select
                                    aria-label="Centro de Costo"
                                    value={formMov.centro_costo_id}
                                    onChange={(e) => setFormMov('centro_costo_id', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    <option value="">Selecciona un centro de costo</option>
                                    {centrosCosto.map((cc) => (
                                        <option key={cc.id} value={cc.id}>
                                            {cc.codigo} - {cc.nombre}
                                        </option>
                                    ))}
                                </select>
                                {erroresMov.centro_costo_id && (
                                    <p className="mt-1 text-[12px] text-red-400">{erroresMov.centro_costo_id}</p>
                                )}
                            </div>
                            <div>
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Categoría de Gasto / Ingreso *</Label>
                                <select
                                    aria-label="Categoría de Gasto o Ingreso"
                                    value={formMov.categoria_id}
                                    onChange={(e) => setFormMov('categoria_id', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    <option value="">Selecciona una categoría</option>
                                    {categorias
                                        .filter((cat) => cat.tipo === formMov.tipo)
                                        .map((cat) => (
                                             <option key={cat.id} value={cat.id}>
                                                 {cat.nombre}
                                             </option>
                                        ))}
                                </select>
                                {erroresMov.categoria_id && (
                                    <p className="mt-1 text-[12px] text-red-400">{erroresMov.categoria_id}</p>
                                )}
                            </div>
                        </div>

                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Concepto / Glosa *</Label>
                            <Input
                                value={formMov.concepto}
                                onChange={(e) => setFormMov('concepto', e.target.value)}
                                placeholder="Ej: Adquisición de diésel D-2 para grupo electrógeno"
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                            />
                            {erroresMov.concepto && <p className="mt-1 text-[12px] text-red-400">{erroresMov.concepto}</p>}
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Tipo de Comprobante *</Label>
                                <select
                                    aria-label="Tipo de Comprobante"
                                    value={formMov.comprobante_tipo}
                                    onChange={(e) => setFormMov('comprobante_tipo', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {tiposComprobante.map((tipo) => (
                                        <option key={tipo} value={tipo}>
                                            {tipo}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Número de Comprobante</Label>
                                <Input
                                    value={formMov.comprobante_numero}
                                    onChange={(e) => setFormMov('comprobante_numero', e.target.value)}
                                    placeholder="F001-000123"
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                            </div>
                            <div>
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Proveedor (Opcional)</Label>
                                <select
                                    aria-label="Proveedor"
                                    value={formMov.proveedor_id}
                                    onChange={(e) => setFormMov('proveedor_id', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    <option value="">Ninguno / Sin proveedor</option>
                                    {proveedores.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.ruc} - {p.razon_social}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Estado de Pago y Fechas */}
                        <div className="grid grid-cols-1 gap-3 rounded-md border border-zinc-800 bg-[#09090b] p-3 sm:grid-cols-3">
                            <div>
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Estado del Pago *</Label>
                                <select
                                    aria-label="Estado del Pago"
                                    value={formMov.estado_pago}
                                    onChange={(e) => setFormMov('estado_pago', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    <option value="PAGADO">PAGADO (Comprobante Cancelado)</option>
                                    <option value="PENDIENTE">PENDIENTE (Cuenta por Pagar)</option>
                                </select>
                            </div>
                            {formMov.estado_pago === 'PAGADO' ? (
                                <div>
                                    <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha de Pago</Label>
                                    <Input
                                        type="date"
                                        value={formMov.fecha_pago}
                                        onChange={(e) => setFormMov('fecha_pago', e.target.value)}
                                        className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                    />
                                </div>
                            ) : (
                                <div>
                                    <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha de Vencimiento</Label>
                                    <Input
                                        type="date"
                                        value={formMov.fecha_vencimiento}
                                        onChange={(e) => setFormMov('fecha_vencimiento', e.target.value)}
                                        className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                    />
                                </div>
                            )}
                            <div>
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Envío Vinculado (Opcional)</Label>
                                <select
                                    aria-label="Envío Vinculado"
                                    value={formMov.envio_id}
                                    onChange={(e) => setFormMov('envio_id', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    <option value="">Sin vínculo a envío</option>
                                    {envios.map((env) => (
                                        <option key={env.id} value={env.id}>
                                            {env.codigo} ({env.estado})
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Vínculo a Trabajador */}
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Trabajador Vinculado (Opcional - viáticos / honorarios)</Label>
                            <select
                                aria-label="Trabajador Vinculado"
                                value={formMov.trabajador_id}
                                onChange={(e) => setFormMov('trabajador_id', e.target.value)}
                                className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                <option value="">Sin vínculo a trabajador</option>
                                {trabajadores.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.dni} - {t.nombres} {t.apellidos}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Archivo adjunto */}
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Comprobante Digital (PDF, JPG, PNG - Máx. 5MB)</Label>
                            <Input
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] || null;
                                    setFormMov('archivo', file);
                                }}
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[12px] text-zinc-200"
                            />
                            {erroresMov.archivo && <p className="mt-1 text-[12px] text-red-400">{erroresMov.archivo}</p>}
                        </div>

                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Observaciones adicionales</Label>
                            <textarea
                                value={formMov.observaciones}
                                onChange={(e) => setFormMov('observaciones', e.target.value)}
                                rows={2}
                                className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 p-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                placeholder="Notas internas o justificación..."
                            />
                        </div>

                        <DialogFooter className="gap-2 sm:gap-0 pt-2 border-t border-zinc-800">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalMovimientoAbierto(false)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={guardandoMov}
                                className="bg-zinc-100 text-zinc-900 font-medium hover:bg-white text-[13px]"
                            >
                                {guardandoMov ? 'Guardando...' : 'Guardar Movimiento'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal: Marcar como Pagado */}
            <Dialog open={modalPagoAbierto} onOpenChange={setModalPagoAbierto}>
                <DialogContent className="max-w-md bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold text-zinc-100">Registrar Pago de Comprobante</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={confirmarPago} className="space-y-4 py-2">
                        <p className="text-[13px] text-zinc-400">
                            Confirmar el pago del movimiento:
                            <br />
                            <strong className="text-zinc-200">{movimientoParaPagar?.concepto}</strong>
                            <br />
                            Monto: <strong className="text-zinc-100 tabular-nums">{formatearMonto(movimientoParaPagar?.monto || 0)}</strong>
                        </p>
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha en que se realizó el pago *</Label>
                            <Input
                                type="date"
                                value={formPago.fecha_pago}
                                onChange={(e) => setFormPago('fecha_pago', e.target.value)}
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                required
                            />
                        </div>
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Número de Comprobante / Constancia de Transferencia</Label>
                            <Input
                                value={formPago.comprobante_numero}
                                onChange={(e) => setFormPago('comprobante_numero', e.target.value)}
                                placeholder="F001-000456 o Nro Operación BCP"
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
                                className="bg-emerald-600/90 text-white hover:bg-emerald-600 text-[13px] font-medium"
                            >
                                {procesandoPago ? 'Procesando...' : 'Confirmar Pago'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal: Comprobantes Adjuntos */}
            <Dialog open={modalAdjuntosAbierto} onOpenChange={setModalAdjuntosAbierto}>
                <DialogContent className="max-w-lg bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold text-zinc-100">Comprobantes Adjuntos</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <p className="text-[13px] text-zinc-400">
                            Movimiento: <strong className="text-zinc-200">{movimientoParaAdjunto?.concepto}</strong>{' '}
                            (<span className="tabular-nums text-zinc-300">S/ {movimientoParaAdjunto?.monto.toFixed(2)}</span>)
                        </p>

                        {/* Lista de adjuntos existentes */}
                        <div className="rounded-md border border-zinc-800 divide-y divide-zinc-800/60 max-h-48 overflow-y-auto bg-[#09090b]">
                            {movimientoParaAdjunto?.adjuntos.length === 0 ? (
                                <p className="p-4 text-center text-[13px] text-zinc-500">
                                    No hay archivos adjuntos en este movimiento.
                                </p>
                            ) : (
                                movimientoParaAdjunto?.adjuntos.map((adj) => (
                                    <div key={adj.id} className="flex items-center justify-between p-2.5 text-[12px]">
                                        <div className="flex flex-col">
                                            <span className="font-medium text-zinc-200">{adj.nombre_original}</span>
                                            <span className="text-[11px] text-zinc-500">
                                                {(adj.tamano / 1024).toFixed(1)} KB · {adj.descripcion || 'Sin descripción'}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <a
                                                href={`/finanzas/movimientos/adjuntos/${adj.id}/descargar`}
                                                className="rounded border border-zinc-800 bg-zinc-900 px-2 py-1 text-[11px] text-blue-400 hover:text-blue-300 hover:bg-zinc-800"
                                            >
                                                Descargar
                                            </a>
                                            {puedeEditar && (
                                                <button
                                                    onClick={() => {
                                                        if (confirm('¿Eliminar este comprobante adjunto?')) {
                                                            router.delete(`/finanzas/movimientos/adjuntos/${adj.id}`);
                                                        }
                                                    }}
                                                    className="rounded p-1 text-red-400 hover:bg-red-950/40"
                                                    title="Eliminar adjunto"
                                                >
                                                    <Trash2 size={13} />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>

                        {/* Formulario para subir nuevo comprobante */}
                        {puedeEditar && (
                            <form onSubmit={subirComprobante} className="rounded-md border border-zinc-800 bg-[#09090b] p-3 space-y-3">
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Subir Nuevo Comprobante</Label>
                                <Input
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    onChange={(e) => setFormAdjunto('archivo', e.target.files?.[0] || null)}
                                    className="h-9 bg-zinc-900 border-zinc-800 text-[12px] text-zinc-200"
                                    required
                                />
                                {erroresAdjunto.archivo && (
                                    <p className="text-[12px] text-red-400">{erroresAdjunto.archivo}</p>
                                )}
                                <Input
                                    placeholder="Descripción del documento (ej. Factura sellada de recepción)"
                                    value={formAdjunto.descripcion}
                                    onChange={(e) => setFormAdjunto('descripcion', e.target.value)}
                                    className="h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                                <div className="flex justify-end pt-1">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={subiendoAdjunto}
                                        className="bg-zinc-100 text-zinc-900 font-medium hover:bg-white text-[12px]"
                                    >
                                        {subiendoAdjunto ? 'Subiendo...' : 'Adjuntar Archivo'}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </FinanzasLayout>
    );
}
