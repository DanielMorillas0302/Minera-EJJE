import { Paginacion, type Paginado } from '@/components/paginacion';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import LogisticaLayout from '@/layouts/logistica/logistica-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Ban,
    Boxes,
    Check,
    Download,
    Eye,
    Plus,
    Search,
    ShoppingCart,
    Trash2,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface ProveedorItem {
    id: number;
    razon_social: string;
    ruc: string;
}

interface AlmacenItem {
    id: number;
    nombre: string;
    codigo: string;
}

interface MaterialItem {
    id: number;
    nombre: string;
    codigo: string;
    precio_referencial?: string | number | null;
}

interface CentroCostoItem {
    id: number;
    codigo: string;
    nombre: string;
}

interface CategoriaGastoItem {
    id: number;
    codigo: string;
    nombre: string;
}

interface RequerimientoItem {
    id: number;
    codigo: string;
    detalles: Array<{ material_id: number; cantidad_solicitada: string; material?: { nombre: string } }>;
}

interface OrdenCompraDetalleItem {
    id: number;
    material_id: number;
    cantidad: string | number;
    precio_unitario: string | number;
    subtotal: string | number;
    cantidad_recibida: string | number;
    observaciones?: string | null;
    material?: { id: number; codigo: string; nombre: string } | null;
}

interface OrdenCompraItem {
    id: number;
    codigo: string;
    fecha: string;
    fecha_entrega_esperada?: string | null;
    estado: string;
    moneda: string;
    subtotal: string | number;
    igv: string | number;
    total: string | number;
    condiciones_pago?: string | null;
    observaciones?: string | null;
    proveedor_id: number;
    almacen_destino_id: number;
    requerimiento_id?: number | null;
    proveedor?: ProveedorItem | null;
    almacen_destino?: AlmacenItem | null;
    aprobado_por?: { name: string } | null;
    detalles: OrdenCompraDetalleItem[];
}

interface Props {
    ordenes: Paginado<OrdenCompraItem>;
    filtros: {
        buscar?: string;
        estado?: string;
        proveedor_id?: string | number;
        almacen_id?: string | number;
    };
    proveedores: ProveedorItem[];
    almacenes: AlmacenItem[];
    materiales: MaterialItem[];
    requerimientos: RequerimientoItem[];
    centrosCosto: CentroCostoItem[];
    categoriasGasto: CategoriaGastoItem[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Logística', href: '/logistica' },
    { title: 'Órdenes de Compra', href: '/logistica/ordenes-compra' },
];

export default function LogisticaOrdenesCompra({
    ordenes,
    filtros,
    proveedores,
    almacenes,
    materiales,
    requerimientos,
    centrosCosto,
    categoriasGasto,
}: Props) {
    const { auth } = usePage<SharedData>().props;
    const permisosOC = auth?.user?.permisos?.ordenes_compra ?? '';
    const puedeCrear = permisosOC.includes('C');
    const puedeEditar = permisosOC.includes('E');
    const puedeAprobar = permisosOC.includes('A');

    const [buscarTexto, setBuscarTexto] = useState(filtros.buscar ?? '');
    const [estadoFiltro, setEstadoFiltro] = useState(filtros.estado ?? '');

    // Modales
    const [modalCrear, setModalCrear] = useState(false);
    const [modalDetalle, setModalDetalle] = useState<OrdenCompraItem | null>(null);
    const [modalRecepcion, setModalRecepcion] = useState<OrdenCompraItem | null>(null);
    const [modalAnular, setModalAnular] = useState<OrdenCompraItem | null>(null);

    const estados = [
        { id: 'BORRADOR', nombre: 'Borrador' },
        { id: 'EMITIDA', nombre: 'Emitida' },
        { id: 'RECIBIDA_PARCIAL', nombre: 'Recibida Parcial' },
        { id: 'RECIBIDA', nombre: 'Recibida Total' },
        { id: 'ANULADA', nombre: 'Anulada' },
    ];

    const aplicarFiltros = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            '/logistica/ordenes-compra',
            {
                buscar: buscarTexto || undefined,
                estado: estadoFiltro || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const formatearMoneda = (val: string | number) => {
        const num = typeof val === 'string' ? parseFloat(val) : val;
        return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(num || 0);
    };

    // Formulario de Creación
    const { data, setData, post, processing, errors, reset } = useForm({
        proveedor_id: '',
        almacen_destino_id: '',
        requerimiento_id: '',
        fecha: new Date().toISOString().slice(0, 10),
        fecha_entrega_esperada: '',
        moneda: 'PEN',
        condiciones_pago: 'Crédito 30 días',
        observaciones: '',
        emitir_inmediata: false as boolean,
        detalles: [
            {
                material_id: '',
                cantidad: '1',
                precio_unitario: '0',
                observaciones: '',
            },
        ],
    });

    const agregarFilaMaterial = () => {
        setData('detalles', [
            ...data.detalles,
            { material_id: '', cantidad: '1', precio_unitario: '0', observaciones: '' },
        ]);
    };

    const eliminarFilaMaterial = (index: number) => {
        if (data.detalles.length <= 1) return;
        setData(
            'detalles',
            data.detalles.filter((_, i) => i !== index),
        );
    };

    const cambiarFilaMaterial = (index: number, campo: string, valor: string) => {
        const nuevos = [...data.detalles];
        nuevos[index] = { ...nuevos[index], [campo]: valor };

        if (campo === 'material_id') {
            const mat = materiales.find((m) => String(m.id) === valor);
            if (mat && mat.precio_referencial) {
                nuevos[index].precio_unitario = String(mat.precio_referencial);
            }
        }

        setData('detalles', nuevos);
    };

    const calcularTotalesForm = () => {
        let subtotal = 0;
        data.detalles.forEach((d) => {
            const c = parseFloat(d.cantidad) || 0;
            const p = parseFloat(d.precio_unitario) || 0;
            subtotal += c * p;
        });
        const igv = subtotal * 0.18;
        const total = subtotal + igv;
        return { subtotal, igv, total };
    };

    const submitCrear = (e: FormEvent) => {
        e.preventDefault();
        post('/logistica/ordenes-compra', {
            onSuccess: () => {
                setModalCrear(false);
                reset();
            },
        });
    };

    // Formulario de Recepción
    const formRecepcion = useForm({
        recepciones: [] as Array<{ detalle_id: number; cantidad_recibir: string }>,
        generar_cuenta_pagar: false as boolean,
        comprobante_tipo: 'FACTURA',
        comprobante_numero: '',
        fecha_comprobante: new Date().toISOString().slice(0, 10),
        fecha_vencimiento: '',
        centro_costo_id: '',
        categoria_id: '',
    });

    const abrirModalRecepcion = (orden: OrdenCompraItem) => {
        setModalRecepcion(orden);
        formRecepcion.setData({
            recepciones: orden.detalles.map((d) => ({
                detalle_id: d.id,
                cantidad_recibir: String(Math.max(0, Number(d.cantidad) - Number(d.cantidad_recibida))),
            })),
            generar_cuenta_pagar: false,
            comprobante_tipo: 'FACTURA',
            comprobante_numero: '',
            fecha_comprobante: new Date().toISOString().slice(0, 10),
            fecha_vencimiento: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
            centro_costo_id: centrosCosto[0]?.id ? String(centrosCosto[0].id) : '',
            categoria_id: categoriasGasto[0]?.id ? String(categoriasGasto[0].id) : '',
        });
    };

    const submitRecepcion = (e: FormEvent) => {
        e.preventDefault();
        if (!modalRecepcion) return;

        formRecepcion.post(`/logistica/ordenes-compra/${modalRecepcion.id}/recepcionar`, {
            onSuccess: () => {
                setModalRecepcion(null);
            },
        });
    };

    // Formulario de Anulación
    const formAnular = useForm({ motivo: '' });

    const submitAnular = (e: FormEvent) => {
        e.preventDefault();
        if (!modalAnular) return;

        formAnular.post(`/logistica/ordenes-compra/${modalAnular.id}/anular`, {
            onSuccess: () => {
                setModalAnular(null);
                formAnular.reset();
            },
        });
    };

    const emitirOrden = (orden: OrdenCompraItem) => {
        if (!confirm(`¿Confirmas la aprobación y emisión de la orden de compra ${orden.codigo}?`)) {
            return;
        }
        router.post(`/logistica/ordenes-compra/${orden.id}/emitir`);
    };

    const totalesActuales = calcularTotalesForm();

    return (
        <LogisticaLayout
            breadcrumbs={breadcrumbs}
            titulo="Órdenes de Compra a Proveedores"
            descripcion="Emisión de compras formales a proveedores mineros, control de entrega y recepción en almacén con actualización automática de kardex y cuentas por pagar."
        >
            <Head title="Logística - Órdenes de Compra" />

            {/* Barra superior de Búsqueda y Creación */}
            <div className="flex flex-col gap-4 rounded-lg border border-zinc-800 bg-[#0f0f13] p-4 md:flex-row md:items-center md:justify-between">
                <form onSubmit={aplicarFiltros} className="flex flex-wrap items-center gap-2.5">
                    <div className="relative">
                        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500" />
                        <Input
                            placeholder="Buscar código o proveedor..."
                            value={buscarTexto}
                            onChange={(e) => setBuscarTexto(e.target.value)}
                            className="h-9 w-64 bg-zinc-900 border-zinc-800 pl-9 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500"
                        />
                    </div>

                    <select
                        value={estadoFiltro}
                        onChange={(e) => setEstadoFiltro(e.target.value)}
                        className="h-9 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-300 focus:outline-none focus:border-blue-500"
                    >
                        <option value="">Todos los Estados</option>
                        {estados.map((est) => (
                            <option key={est.id} value={est.id}>
                                {est.nombre}
                            </option>
                        ))}
                    </select>

                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        className="h-9 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 text-[13px]"
                    >
                        Filtrar
                    </Button>
                </form>

                {puedeCrear && (
                    <Button
                        onClick={() => setModalCrear(true)}
                        className="h-9 bg-zinc-100 text-zinc-900 font-medium px-4 py-2 rounded-md hover:bg-white text-[13px]"
                    >
                        <Plus size={16} className="mr-1.5" />
                        Nueva Orden de Compra
                    </Button>
                )}
            </div>

            {/* Tabla de Órdenes de Compra */}
            <Card className="border border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-[13px]">
                            <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] uppercase font-semibold text-zinc-400 tracking-wider">
                                <tr>
                                    <th className="px-4 py-3">Código</th>
                                    <th className="px-4 py-3">Fecha</th>
                                    <th className="px-4 py-3">Proveedor</th>
                                    <th className="px-4 py-3">Almacén Destino</th>
                                    <th className="px-4 py-3 text-right">Total</th>
                                    <th className="px-4 py-3 text-center">Estado</th>
                                    <th className="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {ordenes.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="p-10 text-center text-[13px] text-zinc-500">
                                            <ShoppingCart size={32} className="mx-auto mb-2 text-zinc-600" />
                                            No se encontraron órdenes de compra registradas.
                                        </td>
                                    </tr>
                                ) : (
                                    ordenes.data.map((oc) => (
                                        <tr key={oc.id} className="hover:bg-zinc-900/40 transition-colors">
                                            <td className="px-4 py-3 font-semibold text-zinc-100 tracking-tight">
                                                {oc.codigo}
                                            </td>
                                            <td className="px-4 py-3 text-zinc-400 whitespace-nowrap">
                                                {oc.fecha}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-zinc-200">{oc.proveedor?.razon_social}</div>
                                                <div className="text-[11px] text-zinc-500 font-mono">RUC: {oc.proveedor?.ruc}</div>
                                            </td>
                                            <td className="px-4 py-3 text-zinc-300">
                                                {oc.almacen_destino?.nombre}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium text-zinc-100 tabular-nums whitespace-nowrap">
                                                {formatearMoneda(oc.total)}
                                            </td>
                                            <td className="px-4 py-3 text-center whitespace-nowrap">
                                                <StatusBadge status={oc.estado} />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setModalDetalle(oc)}
                                                        title="Ver detalles"
                                                        className="h-8 w-8 p-0 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800"
                                                    >
                                                        <Eye size={15} />
                                                    </Button>

                                                    <a
                                                        href={`/logistica/ordenes-compra/${oc.id}/pdf`}
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100 transition-colors"
                                                        title="Descargar PDF"
                                                    >
                                                        <Download size={15} />
                                                    </a>

                                                    {oc.estado === 'BORRADOR' && puedeAprobar && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 w-8 p-0 text-blue-400 hover:text-blue-300 hover:bg-blue-950/40"
                                                            onClick={() => emitirOrden(oc)}
                                                            title="Aprobar y Emitir"
                                                        >
                                                            <Check size={15} />
                                                        </Button>
                                                    )}

                                                    {['EMITIDA', 'RECIBIDA_PARCIAL'].includes(oc.estado) && puedeEditar && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-7 border-emerald-900/50 bg-emerald-950/30 text-emerald-300 hover:bg-emerald-900/50 text-[12px]"
                                                            onClick={() => abrirModalRecepcion(oc)}
                                                        >
                                                            <Boxes size={13} className="mr-1 text-emerald-400" />
                                                            Recepcionar
                                                        </Button>
                                                    )}

                                                    {oc.estado === 'BORRADOR' && puedeEditar && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 w-8 p-0 text-red-400 hover:text-red-300 hover:bg-red-950/40"
                                                            onClick={() => setModalAnular(oc)}
                                                            title="Anular orden"
                                                        >
                                                            <Ban size={15} />
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

            <Paginacion datos={ordenes} />

            {/* MODAL 1: NUEVA ORDEN DE COMPRA */}
            <Dialog open={modalCrear} onOpenChange={setModalCrear}>
                <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-semibold text-zinc-100">Nueva Orden de Compra</DialogTitle>
                    </DialogHeader>

                    <form onSubmit={submitCrear} className="space-y-4">
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <Label htmlFor="proveedor_id" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Proveedor Adjudicado *</Label>
                                <select
                                    id="proveedor_id"
                                    value={data.proveedor_id}
                                    onChange={(e) => setData('proveedor_id', e.target.value)}
                                    className="w-full rounded-md border border-zinc-800 bg-zinc-900 p-2 text-[13px] text-zinc-200 mt-1 focus:outline-none focus:border-blue-500"
                                    required
                                >
                                    <option value="">Selecciona un proveedor...</option>
                                    {proveedores.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.razon_social} (RUC: {p.ruc})
                                        </option>
                                    ))}
                                </select>
                                {errors.proveedor_id && <p className="text-[12px] text-red-400 mt-1">{errors.proveedor_id}</p>}
                            </div>

                            <div>
                                <Label htmlFor="almacen_destino_id" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Almacén de Ingreso *</Label>
                                <select
                                    id="almacen_destino_id"
                                    value={data.almacen_destino_id}
                                    onChange={(e) => setData('almacen_destino_id', e.target.value)}
                                    className="w-full rounded-md border border-zinc-800 bg-zinc-900 p-2 text-[13px] text-zinc-200 mt-1 focus:outline-none focus:border-blue-500"
                                    required
                                >
                                    <option value="">Selecciona almacén...</option>
                                    {almacenes.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.nombre} ({a.codigo})
                                        </option>
                                    ))}
                                </select>
                                {errors.almacen_destino_id && (
                                    <p className="text-[12px] text-red-400 mt-1">{errors.almacen_destino_id}</p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="requerimiento_id" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Requerimiento Vinculado (Opcional)</Label>
                                <select
                                    id="requerimiento_id"
                                    value={data.requerimiento_id}
                                    onChange={(e) => setData('requerimiento_id', e.target.value)}
                                    className="w-full rounded-md border border-zinc-800 bg-zinc-900 p-2 text-[13px] text-zinc-200 mt-1 focus:outline-none focus:border-blue-500"
                                >
                                    <option value="">Sin requerimiento directo</option>
                                    {requerimientos.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.codigo}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <Label htmlFor="fecha" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha de Emisión *</Label>
                                <Input
                                    id="fecha"
                                    type="date"
                                    value={data.fecha}
                                    onChange={(e) => setData('fecha', e.target.value)}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                    required
                                />
                            </div>

                            <div>
                                <Label htmlFor="fecha_entrega_esperada" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha Entrega Prevista</Label>
                                <Input
                                    id="fecha_entrega_esperada"
                                    type="date"
                                    value={data.fecha_entrega_esperada}
                                    onChange={(e) => setData('fecha_entrega_esperada', e.target.value)}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                            </div>

                            <div>
                                <Label htmlFor="condiciones_pago" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Condición de Pago</Label>
                                <Input
                                    id="condiciones_pago"
                                    value={data.condiciones_pago}
                                    onChange={(e) => setData('condiciones_pago', e.target.value)}
                                    placeholder="Ej. Contado contra entrega, Crédito 30 días"
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                            </div>
                        </div>

                        {/* Tabla de Ítems */}
                        <div className="border border-zinc-800 rounded-md p-3 bg-[#09090b]">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-zinc-400">Materiales e Insumos</span>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={agregarFilaMaterial}
                                    className="h-7 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[12px]"
                                >
                                    <Plus size={13} className="mr-1 text-zinc-400" />
                                    Agregar Ítem
                                </Button>
                            </div>

                            <div className="space-y-2">
                                {data.detalles.map((fila, idx) => (
                                    <div key={idx} className="flex items-center gap-2">
                                        <div className="flex-1">
                                            <select
                                                value={fila.material_id}
                                                onChange={(e) => cambiarFilaMaterial(idx, 'material_id', e.target.value)}
                                                className="w-full rounded border border-zinc-800 bg-zinc-900 p-1.5 text-[12px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                                required
                                            >
                                                <option value="">Selecciona material...</option>
                                                {materiales.map((m) => (
                                                    <option key={m.id} value={m.id}>
                                                        {m.nombre} ({m.codigo})
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <div className="w-24">
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                placeholder="Cant."
                                                value={fila.cantidad}
                                                onChange={(e) => cambiarFilaMaterial(idx, 'cantidad', e.target.value)}
                                                className="h-8 bg-zinc-900 border-zinc-800 text-[12px] text-zinc-200"
                                                required
                                            />
                                        </div>

                                        <div className="w-28">
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                placeholder="P. Unit."
                                                value={fila.precio_unitario}
                                                onChange={(e) => cambiarFilaMaterial(idx, 'precio_unitario', e.target.value)}
                                                className="h-8 bg-zinc-900 border-zinc-800 text-[12px] text-zinc-200"
                                                required
                                            />
                                        </div>

                                        <div className="w-28 text-right text-[12px] font-medium text-zinc-200 tabular-nums">
                                            {formatearMoneda((parseFloat(fila.cantidad) || 0) * (parseFloat(fila.precio_unitario) || 0))}
                                        </div>

                                        {data.detalles.length > 1 && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => eliminarFilaMaterial(idx)}
                                                className="h-8 w-8 p-0 text-red-400 hover:text-red-300 hover:bg-red-950/30"
                                            >
                                                <Trash2 size={14} />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>

                            {/* Resumen de Totales */}
                            <div className="mt-4 flex flex-col items-end border-t border-zinc-800 pt-2 text-[12px] text-zinc-400 space-y-0.5">
                                <div>Subtotal: <strong className="text-zinc-200 tabular-nums">{formatearMoneda(totalesActuales.subtotal)}</strong></div>
                                <div>I.G.V. (18%): <strong className="text-zinc-200 tabular-nums">{formatearMoneda(totalesActuales.igv)}</strong></div>
                                <div className="text-[13px] font-semibold text-zinc-100 mt-1">
                                    TOTAL: <span className="tabular-nums">{formatearMoneda(totalesActuales.total)}</span>
                                </div>
                            </div>
                        </div>

                        {puedeAprobar && (
                            <label className="flex items-center gap-2 text-[13px] text-zinc-300 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.emitir_inmediata}
                                    onChange={(e) => setData('emitir_inmediata', e.target.checked)}
                                    className="rounded border-zinc-800 bg-zinc-900 text-blue-500 focus:ring-0 focus:ring-offset-0"
                                />
                                <span>Aprobar y emitir inmediatamente esta orden</span>
                            </label>
                        )}

                        <DialogFooter className="gap-2 sm:gap-0 pt-2 border-t border-zinc-800">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalCrear(false)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="bg-zinc-100 text-zinc-900 font-medium hover:bg-white text-[13px]"
                            >
                                {processing ? 'Guardando...' : 'Crear Orden'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* MODAL 2: DETALLES DE LA ORDEN */}
            {modalDetalle && (
                <Dialog open={Boolean(modalDetalle)} onOpenChange={() => setModalDetalle(null)}>
                    <DialogContent className="max-w-2xl bg-[#0f0f13] border-zinc-800 text-zinc-100">
                        <DialogHeader>
                            <DialogTitle className="flex items-center justify-between text-base font-semibold text-zinc-100">
                                <span>Orden de Compra: {modalDetalle.codigo}</span>
                                <StatusBadge status={modalDetalle.estado} />
                            </DialogTitle>
                        </DialogHeader>

                        <div className="space-y-4 text-[13px]">
                            <div className="grid grid-cols-2 gap-3 rounded-md border border-zinc-800 p-3 bg-[#09090b] text-[12px]">
                                <div>
                                    <span className="text-zinc-500">Proveedor:</span>{' '}
                                    <strong className="text-zinc-200">{modalDetalle.proveedor?.razon_social}</strong>{' '}
                                    <span className="text-zinc-500">(RUC: {modalDetalle.proveedor?.ruc})</span>
                                </div>
                                <div>
                                    <span className="text-zinc-500">Almacén Destino:</span>{' '}
                                    <strong className="text-zinc-200">{modalDetalle.almacen_destino?.nombre}</strong>
                                </div>
                                <div>
                                    <span className="text-zinc-500">Fecha Emisión:</span> <strong className="text-zinc-200">{modalDetalle.fecha}</strong>
                                </div>
                                <div>
                                    <span className="text-zinc-500">Entrega Estimada:</span>{' '}
                                    <strong className="text-zinc-200">{modalDetalle.fecha_entrega_esperada ?? 'No definida'}</strong>
                                </div>
                            </div>

                            <div>
                                <h4 className="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 mb-2">Ítems Solicitados</h4>
                                <div className="rounded-md border border-zinc-800 overflow-hidden bg-[#09090b]">
                                    <table className="w-full text-[12px]">
                                        <thead className="bg-zinc-900 border-b border-zinc-800 text-zinc-400 font-semibold text-[11px] uppercase tracking-wider">
                                            <tr>
                                                <th className="p-2.5 text-left">Material</th>
                                                <th className="p-2.5 text-right">Cant. Pedida</th>
                                                <th className="p-2.5 text-right">Cant. Recibida</th>
                                                <th className="p-2.5 text-right">P. Unit.</th>
                                                <th className="p-2.5 text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-zinc-800/60">
                                            {modalDetalle.detalles.map((d) => (
                                                <tr key={d.id} className="hover:bg-zinc-900/30">
                                                    <td className="p-2.5">
                                                        <div className="font-medium text-zinc-200">{d.material?.nombre}</div>
                                                        <div className="text-[10px] text-zinc-500 font-mono">{d.material?.codigo}</div>
                                                    </td>
                                                    <td className="p-2.5 text-right tabular-nums text-zinc-300">{d.cantidad}</td>
                                                    <td className="p-2.5 text-right font-medium text-emerald-400 tabular-nums">
                                                        {d.cantidad_recibida}
                                                    </td>
                                                    <td className="p-2.5 text-right tabular-nums text-zinc-300">S/ {d.precio_unitario}</td>
                                                    <td className="p-2.5 text-right font-medium text-zinc-100 tabular-nums">S/ {d.subtotal}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div className="flex justify-end pt-1">
                                <div className="text-right">
                                    <div className="text-[11px] text-zinc-500 uppercase tracking-wider">Total con IGV (18%):</div>
                                    <div className="text-lg font-semibold text-zinc-100 tabular-nums">{formatearMoneda(modalDetalle.total)}</div>
                                </div>
                            </div>
                        </div>

                        <DialogFooter className="border-t border-zinc-800 pt-3">
                            <Button
                                variant="outline"
                                onClick={() => setModalDetalle(null)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                            >
                                Cerrar
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}

            {/* MODAL 3: RECEPCIÓN DE ÍTEMS EN ALMACÉN */}
            {modalRecepcion && (
                <Dialog open={Boolean(modalRecepcion)} onOpenChange={() => setModalRecepcion(null)}>
                    <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto bg-[#0f0f13] border-zinc-800 text-zinc-100">
                        <DialogHeader>
                            <DialogTitle className="text-base font-semibold text-zinc-100">Recepción de Compra: {modalRecepcion.codigo}</DialogTitle>
                        </DialogHeader>

                        <form onSubmit={submitRecepcion} className="space-y-4">
                            <p className="text-[13px] text-zinc-400">
                                Registra las cantidades ingresadas al <strong className="text-zinc-200">{modalRecepcion.almacen_destino?.nombre}</strong>.
                                Se generará el movimiento de kardex tipo <code className="text-[11px] text-blue-400 font-mono">ENTRADA_COMPRA</code>.
                            </p>

                            <div className="rounded-md border border-zinc-800 overflow-hidden bg-[#09090b]">
                                <table className="w-full text-[12px]">
                                    <thead className="bg-zinc-900 border-b border-zinc-800 text-zinc-400 font-semibold text-[11px] uppercase tracking-wider">
                                        <tr>
                                            <th className="p-2.5 text-left">Material</th>
                                            <th className="p-2.5 text-right">Pedida</th>
                                            <th className="p-2.5 text-right">Recibida</th>
                                            <th className="p-2.5 text-right">Pendiente</th>
                                            <th className="p-2.5 text-right w-28">A Recibir Hoy</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-zinc-800/60">
                                        {modalRecepcion.detalles.map((d, idx) => {
                                            const pendiente = Math.max(0, Number(d.cantidad) - Number(d.cantidad_recibida));
                                            return (
                                                <tr key={d.id} className="hover:bg-zinc-900/30">
                                                    <td className="p-2.5">
                                                        <div className="font-medium text-zinc-200">{d.material?.nombre}</div>
                                                    </td>
                                                    <td className="p-2.5 text-right tabular-nums text-zinc-300">{d.cantidad}</td>
                                                    <td className="p-2.5 text-right text-emerald-400 font-medium tabular-nums">{d.cantidad_recibida}</td>
                                                    <td className="p-2.5 text-right font-medium text-amber-400 tabular-nums">{pendiente}</td>
                                                    <td className="p-2.5 text-right">
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            max={pendiente}
                                                            value={formRecepcion.data.recepciones[idx]?.cantidad_recibir ?? '0'}
                                                            onChange={(e) => {
                                                                const nuevas = [...formRecepcion.data.recepciones];
                                                                nuevas[idx] = { detalle_id: d.id, cantidad_recibir: e.target.value };
                                                                formRecepcion.setData('recepciones', nuevas);
                                                            }}
                                                            className="h-7 bg-zinc-900 border-zinc-800 text-[12px] text-right text-zinc-200"
                                                        />
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            {/* Sección Cuentas por Pagar Integradas */}
                            <div className="rounded-md border border-zinc-800 bg-[#09090b] p-3 space-y-3">
                                <label className="flex items-center gap-2 text-[13px] font-medium text-zinc-200 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={formRecepcion.data.generar_cuenta_pagar}
                                        onChange={(e) => formRecepcion.setData('generar_cuenta_pagar', e.target.checked)}
                                        className="rounded border-zinc-800 bg-zinc-900 text-blue-500 focus:ring-0 focus:ring-offset-0"
                                    />
                                    <span>Registrar Comprobante y Cuenta por Pagar en Finanzas</span>
                                </label>

                                {formRecepcion.data.generar_cuenta_pagar && (
                                    <div className="grid grid-cols-2 gap-3 text-[12px] pt-3 border-t border-zinc-800">
                                        <div>
                                            <Label htmlFor="comprobante_numero" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">N° de Factura / Boleta</Label>
                                            <Input
                                                id="comprobante_numero"
                                                placeholder="Ej. F001-000492"
                                                value={formRecepcion.data.comprobante_numero}
                                                onChange={(e) => formRecepcion.setData('comprobante_numero', e.target.value)}
                                                className="h-8 bg-zinc-900 border-zinc-800 text-[12px] text-zinc-200 mt-1"
                                                required
                                            />
                                        </div>

                                        <div>
                                            <Label htmlFor="fecha_vencimiento" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha de Vencimiento</Label>
                                            <Input
                                                id="fecha_vencimiento"
                                                type="date"
                                                value={formRecepcion.data.fecha_vencimiento}
                                                onChange={(e) => formRecepcion.setData('fecha_vencimiento', e.target.value)}
                                                className="h-8 bg-zinc-900 border-zinc-800 text-[12px] text-zinc-200 mt-1"
                                                required
                                            />
                                        </div>

                                        <div>
                                            <Label htmlFor="centro_costo_id" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Centro de Costo</Label>
                                            <select
                                                id="centro_costo_id"
                                                value={formRecepcion.data.centro_costo_id}
                                                onChange={(e) => formRecepcion.setData('centro_costo_id', e.target.value)}
                                                className="w-full rounded border border-zinc-800 bg-zinc-900 p-1.5 text-[12px] text-zinc-200 mt-1 focus:outline-none focus:border-blue-500"
                                                required
                                            >
                                                {centrosCosto.map((cc) => (
                                                    <option key={cc.id} value={cc.id}>
                                                        {cc.codigo} - {cc.nombre}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <div>
                                            <Label htmlFor="categoria_id" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Categoría de Gasto</Label>
                                            <select
                                                id="categoria_id"
                                                value={formRecepcion.data.categoria_id}
                                                onChange={(e) => formRecepcion.setData('categoria_id', e.target.value)}
                                                className="w-full rounded border border-zinc-800 bg-zinc-900 p-1.5 text-[12px] text-zinc-200 mt-1 focus:outline-none focus:border-blue-500"
                                                required
                                            >
                                                {categoriasGasto.map((cat) => (
                                                    <option key={cat.id} value={cat.id}>
                                                        {cat.codigo} - {cat.nombre}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    </div>
                                )}
                            </div>

                            <DialogFooter className="border-t border-zinc-800 pt-3 gap-2 sm:gap-0">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setModalRecepcion(null)}
                                    className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={formRecepcion.processing}
                                    className="bg-emerald-600/90 text-white hover:bg-emerald-600 text-[13px] font-medium"
                                >
                                    {formRecepcion.processing ? 'Registrando...' : 'Confirmar Recepción'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            )}

            {/* MODAL 4: ANULAR ORDEN */}
            {modalAnular && (
                <Dialog open={Boolean(modalAnular)} onOpenChange={() => setModalAnular(null)}>
                    <DialogContent className="bg-[#0f0f13] border-zinc-800 text-zinc-100">
                        <DialogHeader>
                            <DialogTitle className="text-base font-semibold text-zinc-100">Anular Orden de Compra: {modalAnular.codigo}</DialogTitle>
                        </DialogHeader>

                        <form onSubmit={submitAnular} className="space-y-4">
                            <p className="text-[13px] text-zinc-400">
                                Esta acción anulará la orden de compra y no podrá revertirse.
                            </p>

                            <div>
                                <Label htmlFor="motivo" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Motivo de Anulación *</Label>
                                <Input
                                    id="motivo"
                                    placeholder="Indica la razón de la anulación..."
                                    value={formAnular.data.motivo}
                                    onChange={(e) => formAnular.setData('motivo', e.target.value)}
                                    className="mt-1 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                    required
                                />
                                {formAnular.errors.motivo && (
                                    <p className="text-[12px] text-red-400 mt-1">{formAnular.errors.motivo}</p>
                                )}
                            </div>

                            <DialogFooter className="border-t border-zinc-800 pt-3 gap-2 sm:gap-0">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setModalAnular(null)}
                                    className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={formAnular.processing}
                                    className="border border-red-900/50 bg-red-950/50 text-red-300 hover:bg-red-900/70 text-[13px]"
                                >
                                    {formAnular.processing ? 'Anulando...' : 'Confirmar Anulación'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            )}
        </LogisticaLayout>
    );
}
