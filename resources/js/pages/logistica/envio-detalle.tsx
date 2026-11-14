import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import LogisticaLayout from '@/layouts/logistica/logistica-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Ban, CheckCircle2, Download, Paperclip, Send, Truck } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface EnvioDetalleItem {
    id: number;
    material_id: number;
    cantidad_enviada: string;
    cantidad_recibida: string | null;
    observacion_recepcion: string | null;
    material: {
        codigo: string;
        nombre: string;
        unidad_medida?: { abreviatura: string } | null;
    };
}

interface AdjuntoItem {
    id: number;
    nombre_original: string;
    descripcion: string | null;
    tamano: number;
}

interface MovimientoRelacionado {
    id: number;
    tipo: string;
    cantidad: string;
    saldo_resultante: string;
    fecha_hora: string;
    observacion: string | null;
    almacen: { nombre: string };
    material: { codigo: string; nombre: string };
    usuario?: { name: string } | null;
}

interface EnvioCompleto {
    id: number;
    codigo: string;
    estado: string;
    fecha_envio: string | null;
    fecha_estimada_llegada: string | null;
    fecha_recepcion: string | null;
    guia_remision: string | null;
    observaciones: string | null;
    motivo_anulacion: string | null;
    almacen_origen: { id: number; nombre: string; sede?: { nombre: string } | null };
    almacen_destino: { id: number; nombre: string; sede?: { nombre: string } | null };
    requerimiento?: { id: number; codigo: string; area?: { nombre: string } | null } | null;
    responsable_envio?: { name: string } | null;
    recibido_por?: { name: string } | null;
    detalles: EnvioDetalleItem[];
    adjuntos: AdjuntoItem[];
}

interface EnvioDetalleProps {
    envio: EnvioCompleto;
    retrasado: boolean;
    movimientos: MovimientoRelacionado[];
    puedeRecibirEnDestino: boolean;
}

export default function LogisticaEnvioDetalle({
    envio,
    retrasado,
    movimientos,
    puedeRecibirEnDestino,
}: EnvioDetalleProps) {
    const { auth } = usePage<SharedData>().props;
    const permisos = auth?.user?.permisos ?? {};
    const puedeGestionarEnvio = (permisos.envios ?? '').includes('E');
    const puedeRecibir = puedeRecibirEnDestino && (permisos.recepciones_consumos ?? '').includes('C');

    const [modalAnular, setModalAnular] = useState(false);
    const [motivoAnulacion, setMotivoAnulacion] = useState('');

    const formDespacho = useForm({
        fecha_envio: envio.fecha_envio ?? new Date().toISOString().slice(0, 10),
        fecha_estimada_llegada: envio.fecha_estimada_llegada ?? '',
        guia_remision: envio.guia_remision ?? '',
    });

    const formRecepcion = useForm<{
        fecha_recepcion: string;
        observaciones: string;
        guia_adjunto: File | null;
        items: Array<{ detalle_id: number; cantidad_recibida: string; observacion_recepcion: string }>;
    }>({
        fecha_recepcion: new Date().toISOString().slice(0, 10),
        observaciones: envio.observaciones ?? '',
        guia_adjunto: null,
        items: envio.detalles.map((d) => ({
            detalle_id: d.id,
            cantidad_recibida: d.cantidad_recibida ?? d.cantidad_enviada,
            observacion_recepcion: d.observacion_recepcion ?? '',
        })),
    });

    const formAdjunto = useForm<{
        archivo: File | null;
        descripcion: string;
    }>({
        archivo: null,
        descripcion: 'Guía de remisión firmada',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Inicio', href: '/dashboard' },
        { title: 'Logística', href: '/logistica' },
        { title: 'Envíos', href: '/logistica/envios' },
        { title: envio.codigo, href: `/logistica/envios/${envio.id}` },
    ];

    const despacharEnvio = (e: FormEvent) => {
        e.preventDefault();
        formDespacho.post(`/logistica/envios/${envio.id}/despachar`);
    };

    const confirmarRecepcion = (e: FormEvent) => {
        e.preventDefault();
        formRecepcion.post(`/logistica/envios/${envio.id}/recibir`);
    };

    const confirmarAnulacion = (e: FormEvent) => {
        e.preventDefault();
        router.post(
            `/logistica/envios/${envio.id}/anular`,
            { motivo: motivoAnulacion },
            {
                onSuccess: () => {
                    setModalAnular(false);
                    setMotivoAnulacion('');
                },
            },
        );
    };

    const subirAdjunto = (e: FormEvent) => {
        e.preventDefault();
        if (!formAdjunto.data.archivo) return;
        formAdjunto.post(`/logistica/envios/${envio.id}/adjuntos`, {
            onSuccess: () => formAdjunto.reset('archivo'),
        });
    };

    return (
        <LogisticaLayout
            breadcrumbs={breadcrumbs}
            titulo={`Envío ${envio.codigo}`}
            descripcion={`${envio.almacen_origen.nombre} → ${envio.almacen_destino.nombre}`}
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Link
                    href="/logistica/envios"
                    className="inline-flex items-center gap-1.5 text-[13px] font-medium text-zinc-400 hover:text-zinc-200 transition-colors"
                >
                    <ArrowLeft size={15} /> Volver al listado de envíos
                </Link>

                <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge status={envio.estado} />
                    {retrasado && (
                        <Badge variant="outline" className="border-red-900/50 bg-red-950/40 text-red-400 text-[11px]">
                            Retrasado
                        </Badge>
                    )}
                    {puedeGestionarEnvio && (envio.estado === 'PREPARADO' || envio.estado === 'EN_TRANSITO') && (
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={() => setModalAnular(true)}
                            className="h-8 border border-red-900/50 bg-red-950/40 text-red-300 hover:bg-red-900/60 text-[12px]"
                        >
                            <Ban size={14} className="mr-1.5" /> Anular envío
                        </Button>
                    )}
                </div>
            </div>

            {envio.estado === 'ANULADO' && envio.motivo_anulacion && (
                <div className="rounded-lg border border-red-900/40 bg-red-950/20 p-4 text-[13px] text-red-300">
                    <strong>Envío anulado:</strong> {envio.motivo_anulacion}
                </div>
            )}

            <div className="grid gap-4 md:grid-cols-4">
                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardContent className="p-4 text-[13px]">
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Origen / Destino</p>
                        <p className="mt-1 font-medium text-zinc-100">{envio.almacen_origen.nombre}</p>
                        <p className="text-[12px] text-zinc-400">→ {envio.almacen_destino.nombre}</p>
                    </CardContent>
                </Card>
                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardContent className="p-4 text-[13px]">
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Requerimiento / Guía</p>
                        <p className="mt-1 font-medium text-zinc-100 font-mono text-[13px]">{envio.requerimiento?.codigo ?? 'Envío directo'}</p>
                        <p className="text-[12px] text-zinc-400">Guía: {envio.guia_remision ?? 'Sin guía'}</p>
                    </CardContent>
                </Card>
                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardContent className="p-4 text-[13px]">
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Despacho</p>
                        <p className="mt-1 font-medium text-zinc-100">{envio.fecha_envio ?? 'Sin despachar'}</p>
                        <p className="text-[12px] text-zinc-400">Por: {envio.responsable_envio?.name ?? '—'}</p>
                    </CardContent>
                </Card>
                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardContent className="p-4 text-[13px]">
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Recepción</p>
                        <p className="mt-1 font-medium text-zinc-100">{envio.fecha_recepcion ?? 'Pendiente'}</p>
                        <p className="text-[12px] text-zinc-400">Recibió: {envio.recibido_por?.name ?? '—'}</p>
                    </CardContent>
                </Card>
            </div>

            {envio.estado === 'PREPARADO' && puedeGestionarEnvio && (
                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base font-semibold text-zinc-100">
                            <Truck size={16} className="text-blue-400" /> Despachar envío (Salida de almacén origen)
                        </CardTitle>
                        <CardDescription className="text-[13px] text-zinc-400">
                            Al despachar se descontará automáticamente el stock del almacén origen y el envío pasará a EN_TRANSITO.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={despacharEnvio} className="grid gap-3 sm:grid-cols-4">
                            <div>
                                <Label htmlFor="desp-fecha" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha de salida</Label>
                                <Input
                                    id="desp-fecha"
                                    type="date"
                                    value={formDespacho.data.fecha_envio}
                                    onChange={(e) => formDespacho.setData('fecha_envio', e.target.value)}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                            </div>
                            <div>
                                <Label htmlFor="desp-est" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Llegada estimada</Label>
                                <Input
                                    id="desp-est"
                                    type="date"
                                    value={formDespacho.data.fecha_estimada_llegada}
                                    onChange={(e) => formDespacho.setData('fecha_estimada_llegada', e.target.value)}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                            </div>
                            <div>
                                <Label htmlFor="desp-guia" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Guía de remisión</Label>
                                <Input
                                    id="desp-guia"
                                    value={formDespacho.data.guia_remision}
                                    onChange={(e) => formDespacho.setData('guia_remision', e.target.value)}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                    placeholder="T001-000123"
                                />
                            </div>
                            <div className="flex items-end">
                                <Button
                                    type="submit"
                                    disabled={formDespacho.processing}
                                    className="h-9 w-full bg-zinc-100 text-zinc-900 font-medium hover:bg-white text-[13px]"
                                >
                                    <Send size={15} className="mr-1.5" /> Confirmar Despacho
                                </Button>
                            </div>
                        </form>
                        {(formDespacho.errors as Record<string, string>).stock && (
                            <p className="mt-2 text-[12px] text-red-400">{(formDespacho.errors as Record<string, string>).stock}</p>
                        )}
                    </CardContent>
                </Card>
            )}

            {envio.estado === 'EN_TRANSITO' && puedeRecibir ? (
                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base font-semibold text-zinc-100">
                            <CheckCircle2 size={16} className="text-emerald-400" /> Registrar Recepción en Destino
                        </CardTitle>
                        <CardDescription className="text-[13px] text-zinc-400">
                            Verifique la cantidad física recibida por material. Si difiere de lo enviado, el envío se marcará como RECIBIDO_CON_OBSERVACIONES.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={confirmarRecepcion} className="space-y-4">
                            <div className="overflow-x-auto rounded-md border border-zinc-800 bg-[#09090b]">
                                <table className="w-full text-left text-[13px]">
                                    <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">
                                        <tr>
                                            <th className="px-4 py-2.5">Material</th>
                                            <th className="px-4 py-2.5 text-right">Enviado</th>
                                            <th className="px-4 py-2.5">Cantidad Recibida *</th>
                                            <th className="px-4 py-2.5 text-right">Diferencia</th>
                                            <th className="px-4 py-2.5">Observación del ítem</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-zinc-800/60">
                                        {envio.detalles.map((d, idx) => {
                                            const itemForm = formRecepcion.data.items[idx];
                                            const diff = Number(itemForm?.cantidad_recibida ?? 0) - Number(d.cantidad_enviada);
                                            const hayDiff = Math.abs(diff) > 0.0005;

                                            return (
                                                <tr key={d.id} className="hover:bg-zinc-900/30">
                                                    <td className="px-4 py-2.5 font-medium text-zinc-200">
                                                        <span className="font-mono text-[11px] text-zinc-500 mr-1.5">{d.material.codigo}</span>
                                                        {d.material.nombre}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-right tabular-nums text-zinc-300 font-medium">
                                                        {Number(d.cantidad_enviada).toFixed(3)} {d.material.unidad_medida?.abreviatura ?? ''}
                                                    </td>
                                                    <td className="px-4 py-2.5">
                                                        <Input
                                                            type="number"
                                                            step="0.001"
                                                            min="0"
                                                            value={itemForm?.cantidad_recibida ?? ''}
                                                            onChange={(e) => {
                                                                const copia = [...formRecepcion.data.items];
                                                                copia[idx] = { ...copia[idx], cantidad_recibida: e.target.value };
                                                                formRecepcion.setData('items', copia);
                                                            }}
                                                            className="h-8 w-32 tabular-nums bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                                            required
                                                        />
                                                    </td>
                                                    <td className={`px-4 py-2.5 text-right font-medium tabular-nums ${hayDiff ? 'text-amber-400' : 'text-emerald-400'}`}>
                                                        {hayDiff ? (
                                                            <span className="inline-flex items-center gap-1">
                                                                <AlertTriangle size={13} />
                                                                {diff > 0 ? `+${diff.toFixed(3)}` : diff.toFixed(3)}
                                                            </span>
                                                        ) : (
                                                            'Conforme'
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-2.5">
                                                        <Input
                                                            value={itemForm?.observacion_recepcion ?? ''}
                                                            onChange={(e) => {
                                                                const copia = [...formRecepcion.data.items];
                                                                copia[idx] = { ...copia[idx], observacion_recepcion: e.target.value };
                                                                formRecepcion.setData('items', copia);
                                                            }}
                                                            placeholder={hayDiff ? 'Detalle el faltante o daño...' : 'Opcional'}
                                                            className="h-8 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                                        />
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <Label htmlFor="rec-fecha" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha de recepción *</Label>
                                    <Input
                                        id="rec-fecha"
                                        type="date"
                                        value={formRecepcion.data.fecha_recepcion}
                                        onChange={(e) => formRecepcion.setData('fecha_recepcion', e.target.value)}
                                        className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                        required
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="rec-obs" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Observaciones generales</Label>
                                    <Input
                                        id="rec-obs"
                                        value={formRecepcion.data.observaciones}
                                        onChange={(e) => formRecepcion.setData('observaciones', e.target.value)}
                                        className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="rec-file" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Adjuntar guía firmada (PDF/Imagen)</Label>
                                    <Input
                                        id="rec-file"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={(e) => formRecepcion.setData('guia_adjunto', e.target.files?.[0] ?? null)}
                                        className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[12px] text-zinc-200"
                                    />
                                </div>
                            </div>

                            <Button
                                type="submit"
                                disabled={formRecepcion.processing}
                                className="h-9 bg-emerald-600/90 text-white hover:bg-emerald-600 font-medium text-[13px]"
                            >
                                <CheckCircle2 size={15} className="mr-1.5" /> Confirmar Ingreso a Almacén Destino
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            ) : (
                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base font-semibold text-zinc-100">Detalle de materiales del envío</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-[13px]">
                                <thead className="border-y border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">
                                    <tr>
                                        <th className="px-4 py-2.5">Código</th>
                                        <th className="px-4 py-2.5">Material</th>
                                        <th className="px-4 py-2.5 text-right">Enviado</th>
                                        <th className="px-4 py-2.5 text-right">Recibido</th>
                                        <th className="px-4 py-2.5">Observación recepción</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {envio.detalles.map((d) => {
                                        const hayDiff =
                                            d.cantidad_recibida !== null &&
                                            Math.abs(Number(d.cantidad_enviada) - Number(d.cantidad_recibida)) > 0.0005;

                                        return (
                                            <tr key={d.id} className="hover:bg-zinc-900/30">
                                                <td className="px-4 py-2.5 font-mono text-[12px] text-zinc-400">{d.material.codigo}</td>
                                                <td className="px-4 py-2.5 font-medium text-zinc-200">{d.material.nombre}</td>
                                                <td className="px-4 py-2.5 text-right tabular-nums text-zinc-300">
                                                    {Number(d.cantidad_enviada).toFixed(3)} {d.material.unidad_medida?.abreviatura ?? ''}
                                                </td>
                                                <td className={`px-4 py-2.5 text-right font-medium tabular-nums ${hayDiff ? 'text-amber-400' : 'text-zinc-100'}`}>
                                                    {d.cantidad_recibida !== null ? Number(d.cantidad_recibida).toFixed(3) : 'Pendiente'}
                                                </td>
                                                <td className="px-4 py-2.5 text-zinc-400">{d.observacion_recepcion ?? '—'}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}

            <div className="grid gap-6 lg:grid-cols-2">
                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base font-semibold text-zinc-100">
                            <Paperclip size={16} className="text-zinc-400" /> Guías y adjuntos del envío ({envio.adjuntos.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {envio.adjuntos.length === 0 ? (
                            <p className="text-[13px] text-zinc-500">No hay documentos adjuntos.</p>
                        ) : (
                            <div className="space-y-2">
                                {envio.adjuntos.map((adj) => (
                                    <div key={adj.id} className="flex items-center justify-between rounded-md border border-zinc-800 bg-[#09090b] px-3 py-2 text-[13px]">
                                        <div>
                                            <p className="font-medium text-zinc-200">{adj.nombre_original}</p>
                                            <p className="text-[11px] text-zinc-500">{adj.descripcion}</p>
                                        </div>
                                        <a
                                            href={`/logistica/envios/${envio.id}/adjuntos/${adj.id}`}
                                            className="inline-flex items-center gap-1 text-[12px] font-medium text-blue-400 hover:text-blue-300 transition-colors"
                                        >
                                            <Download size={14} /> Descargar
                                        </a>
                                    </div>
                                ))}
                            </div>
                        )}

                        {(puedeGestionarEnvio || puedeRecibir) && (
                            <form onSubmit={subirAdjunto} className="flex flex-wrap items-center gap-2 border-t border-zinc-800 pt-3">
                                <Input
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    onChange={(e) => formAdjunto.setData('archivo', e.target.files?.[0] ?? null)}
                                    className="max-w-xs h-8 bg-zinc-900 border-zinc-800 text-[12px] text-zinc-200"
                                    required
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                    disabled={formAdjunto.processing}
                                    className="h-8 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[12px]"
                                >
                                    Subir adjunto
                                </Button>
                            </form>
                        )}
                    </CardContent>
                </Card>

                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base font-semibold text-zinc-100">Trazabilidad en Kardex ({movimientos.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {movimientos.length === 0 ? (
                            <p className="text-[13px] text-zinc-500">Este envío aún no genera movimientos de inventario.</p>
                        ) : (
                            movimientos.map((mov) => (
                                <div key={mov.id} className="rounded-md border border-zinc-800 bg-[#09090b] p-2.5 text-[12px]">
                                    <div className="flex items-center justify-between font-medium text-zinc-200">
                                        <span>
                                            {mov.tipo} · {mov.almacen.nombre}
                                        </span>
                                        <span className="tabular-nums text-zinc-300">
                                            <span className="text-zinc-500 font-mono text-[11px] mr-1">{mov.material.codigo}</span>
                                            {Number(mov.cantidad).toFixed(3)} (Saldo: <span className="text-blue-400">{Number(mov.saldo_resultante).toFixed(3)}</span>)
                                        </span>
                                    </div>
                                    {mov.observacion && <p className="mt-1 text-[11px] text-zinc-500">{mov.observacion}</p>}
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={modalAnular} onOpenChange={setModalAnular}>
                <DialogContent className="bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold text-zinc-100">Anular envío {envio.codigo}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={confirmarAnulacion} className="space-y-4">
                        <p className="text-[13px] text-zinc-400">
                            {envio.estado === 'EN_TRANSITO'
                                ? 'El envío ya salió de origen. Al anularlo se revertirá automáticamente todo el stock descontado al almacén de origen.'
                                : 'El envío pasará a estado ANULADO.'}
                        </p>
                        <div>
                            <Label htmlFor="anul-motivo" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Motivo de anulación *</Label>
                            <Input
                                id="anul-motivo"
                                value={motivoAnulacion}
                                onChange={(e) => setMotivoAnulacion(e.target.value)}
                                className="mt-1 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                required
                            />
                        </div>
                        <DialogFooter className="border-t border-zinc-800 pt-3 gap-2 sm:gap-0">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalAnular(false)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                className="border border-red-900/50 bg-red-950/50 text-red-300 hover:bg-red-900/70 text-[13px]"
                            >
                                Confirmar anulación
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </LogisticaLayout>
    );
}
