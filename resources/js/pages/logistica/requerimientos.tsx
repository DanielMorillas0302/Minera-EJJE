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
import { router, useForm, usePage } from '@inertiajs/react';
import { Check, Plus, Send, Trash2, X } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface DetalleReq {
    id: number;
    material_id: number;
    cantidad_solicitada: string;
    cantidad_atendida: string;
    observacion: string | null;
    material?: { codigo: string; nombre: string; unidad_medida?: { abreviatura: string } | null } | null;
}

interface RequerimientoItem {
    id: number;
    codigo: string;
    fecha_solicitud: string;
    area_id: number;
    sede_id: number;
    solicitante_id: number;
    prioridad: string;
    estado: string;
    observaciones: string | null;
    motivo_rechazo: string | null;
    area?: { nombre: string } | null;
    sede?: { nombre: string } | null;
    solicitante?: { id: number; name: string } | null;
    aprobado_por?: { name: string } | null;
    detalles: DetalleReq[];
}

interface OpcionSelect {
    id: number;
    nombre: string;
    codigo?: string;
    unidad_medida?: { abreviatura: string } | null;
}

interface RequerimientosProps {
    requerimientos: Paginado<RequerimientoItem>;
    areas: OpcionSelect[];
    sedes: OpcionSelect[];
    materiales: OpcionSelect[];
    estados: Array<{ value: string; label: string }>;
    prioridades: Array<{ value: string; label: string }>;
    filtros: Record<string, string | number | null>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Logística', href: '/logistica' },
    { title: 'Requerimientos', href: '/logistica/requerimientos' },
];

export default function LogisticaRequerimientos({
    requerimientos,
    areas,
    sedes,
    materiales,
    estados,
    prioridades,
    filtros,
}: RequerimientosProps) {
    const { auth } = usePage<SharedData>().props;
    const usuarioActualId = auth?.user?.id;
    const permisosReq = auth?.user?.permisos?.requerimientos ?? '';
    const puedeCrear = permisosReq.includes('C');
    const puedeEditar = permisosReq.includes('E');
    const puedeAprobar = permisosReq.includes('A');

    const [estadoFiltro, setEstadoFiltro] = useState(filtros.estado ? String(filtros.estado) : '');
    const [prioridadFiltro, setPrioridadFiltro] = useState(filtros.prioridad ? String(filtros.prioridad) : '');
    const [buscar, setBuscar] = useState(filtros.buscar ? String(filtros.buscar) : '');

    const [modalCrearAbierto, setModalCrearAbierto] = useState(false);
    const [reqRechazo, setReqRechazo] = useState<RequerimientoItem | null>(null);
    const [motivoRechazo, setMotivoRechazo] = useState('');

    const form = useForm<{
        fecha_solicitud: string;
        area_id: string;
        sede_id: string;
        prioridad: string;
        observaciones: string;
        enviar: boolean;
        items: Array<{ material_id: string; cantidad_solicitada: string; observacion: string }>;
    }>({
        fecha_solicitud: new Date().toISOString().slice(0, 10),
        area_id: areas[0]?.id ? String(areas[0].id) : '',
        sede_id: sedes[0]?.id ? String(sedes[0].id) : '',
        prioridad: 'MEDIA',
        observaciones: '',
        enviar: true,
        items: [{ material_id: materiales[0]?.id ? String(materiales[0].id) : '', cantidad_solicitada: '1', observacion: '' }],
    });

    const filtrar = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            '/logistica/requerimientos',
            {
                estado: estadoFiltro || undefined,
                prioridad: prioridadFiltro || undefined,
                buscar: buscar || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const agregarLinea = () => {
        form.setData('items', [
            ...form.data.items,
            { material_id: materiales[0]?.id ? String(materiales[0].id) : '', cantidad_solicitada: '1', observacion: '' },
        ]);
    };

    const quitarLinea = (idx: number) => {
        if (form.data.items.length <= 1) return;
        form.setData(
            'items',
            form.data.items.filter((_, i) => i !== idx),
        );
    };

    const actualizarLinea = (idx: number, campo: 'material_id' | 'cantidad_solicitada' | 'observacion', valor: string) => {
        const nuevos = [...form.data.items];
        nuevos[idx] = { ...nuevos[idx], [campo]: valor };
        form.setData('items', nuevos);
    };

    const guardarRequerimiento = (e: FormEvent) => {
        e.preventDefault();
        form.post('/logistica/requerimientos', {
            onSuccess: () => {
                form.reset();
                setModalCrearAbierto(false);
            },
        });
    };

    const confirmarRechazo = (e: FormEvent) => {
        e.preventDefault();
        if (!reqRechazo) return;
        router.post(
            `/logistica/requerimientos/${reqRechazo.id}/rechazar`,
            { motivo_rechazo: motivoRechazo },
            {
                onSuccess: () => {
                    setReqRechazo(null);
                    setMotivoRechazo('');
                },
            },
        );
    };

    return (
        <LogisticaLayout
            breadcrumbs={breadcrumbs}
            titulo="Requerimientos de Materiales"
            descripcion="Flujo de solicitud, envío, aprobación jerárquica y seguimiento de atención hacia almacenes."
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <form onSubmit={filtrar} className="flex flex-wrap items-center gap-2">
                    <Input
                        value={buscar}
                        onChange={(e) => setBuscar(e.target.value)}
                        placeholder="Buscar REQ-..."
                        className="h-9 w-52 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500"
                    />
                    <select
                        value={estadoFiltro}
                        onChange={(e) => setEstadoFiltro(e.target.value)}
                        className="h-9 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-300 focus:outline-none focus:border-blue-500"
                    >
                        <option value="">Todos los estados</option>
                        {estados.map((est) => (
                            <option key={est.value} value={est.value}>
                                {est.label}
                            </option>
                        ))}
                    </select>
                    <select
                        value={prioridadFiltro}
                        onChange={(e) => setPrioridadFiltro(e.target.value)}
                        className="h-9 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-300 focus:outline-none focus:border-blue-500"
                    >
                        <option value="">Todas las prioridades</option>
                        {prioridades.map((p) => (
                            <option key={p.value} value={p.value}>
                                {p.label}
                            </option>
                        ))}
                    </select>
                    <Button
                        type="submit"
                        variant="outline"
                        className="h-9 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 text-[13px]"
                    >
                        Filtrar
                    </Button>
                </form>

                {puedeCrear && (
                    <Button
                        type="button"
                        onClick={() => setModalCrearAbierto(true)}
                        className="h-9 bg-zinc-100 text-zinc-900 font-medium px-4 py-2 rounded-md hover:bg-white text-[13px]"
                    >
                        <Plus size={16} className="mr-1.5" /> Nuevo Requerimiento
                    </Button>
                )}
            </div>

            <Card className="border border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-0">
                    {requerimientos.data.length === 0 ? (
                        <div className="p-10 text-center text-[13px] text-zinc-500">No se encontraron requerimientos registrados.</div>
                    ) : (
                        <div className="divide-y divide-zinc-800/60">
                            {requerimientos.data.map((req) => {
                                const esPropio = req.solicitante_id === usuarioActualId;
                                const puedeDecidir = puedeAprobar && req.estado === 'ENVIADO' && !esPropio;

                                return (
                                    <div key={req.id} className="flex flex-col gap-3 p-5 hover:bg-zinc-900/30 transition-colors">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex flex-wrap items-center gap-2.5">
                                                <span className="text-sm font-semibold text-zinc-100 tracking-tight">{req.codigo}</span>
                                                <StatusBadge status={req.estado} />
                                                <Badge variant="outline" className="border-zinc-800 bg-zinc-900/60 text-zinc-400 text-[11px] font-medium">
                                                    {req.prioridad}
                                                </Badge>
                                                <span className="text-[12px] text-zinc-400">
                                                    {req.area?.nombre} · {req.sede?.nombre} · Solicitado por <strong className="text-zinc-300 font-medium">{req.solicitante?.name}</strong>
                                                </span>
                                            </div>

                                            <div className="flex flex-wrap items-center gap-2">
                                                {puedeEditar && req.estado === 'BORRADOR' && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-8 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 text-[12px]"
                                                        onClick={() => router.post(`/logistica/requerimientos/${req.id}/enviar`)}
                                                    >
                                                        <Send size={13} className="mr-1.5 text-zinc-400" /> Enviar a aprobación
                                                    </Button>
                                                )}
                                                {puedeDecidir && (
                                                    <>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            className="h-8 bg-emerald-600/90 text-white hover:bg-emerald-600 text-[12px]"
                                                            onClick={() => router.post(`/logistica/requerimientos/${req.id}/aprobar`)}
                                                        >
                                                            <Check size={14} className="mr-1" /> Aprobar
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="destructive"
                                                            className="h-8 border border-red-900/50 bg-red-950/40 text-red-300 hover:bg-red-900/60 text-[12px]"
                                                            onClick={() => {
                                                                setReqRechazo(req);
                                                                setMotivoRechazo('');
                                                            }}
                                                        >
                                                            <X size={14} className="mr-1" /> Rechazar
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </div>

                                        {req.observaciones && <p className="text-[12px] text-zinc-400">{req.observaciones}</p>}
                                        {req.motivo_rechazo && (
                                            <p className="rounded-md border border-red-900/40 bg-red-950/20 px-3 py-1.5 text-[12px] text-red-300">
                                                Motivo de rechazo: {req.motivo_rechazo}
                                            </p>
                                        )}

                                        <div className="overflow-x-auto rounded-md border border-zinc-800 bg-[#09090b]">
                                            <table className="w-full text-left text-[12px]">
                                                <thead className="border-b border-zinc-800 bg-zinc-900/80 text-[11px] uppercase tracking-wider text-zinc-400 font-semibold">
                                                    <tr>
                                                        <th className="px-3.5 py-2">Material</th>
                                                        <th className="px-3.5 py-2 text-right">Solicitado</th>
                                                        <th className="px-3.5 py-2 text-right">Atendido</th>
                                                        <th className="px-3.5 py-2">Observación</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-zinc-800/60">
                                                    {req.detalles.map((d) => (
                                                        <tr key={d.id} className="hover:bg-zinc-900/30">
                                                            <td className="px-3.5 py-2 font-medium text-zinc-200">
                                                                <span className="text-zinc-500 font-mono text-[11px] mr-1.5">{d.material?.codigo}</span>
                                                                {d.material?.nombre}
                                                            </td>
                                                            <td className="px-3.5 py-2 text-right tabular-nums text-zinc-300">
                                                                {Number(d.cantidad_solicitada).toFixed(2)} {d.material?.unidad_medida?.abreviatura ?? ''}
                                                            </td>
                                                            <td className="px-3.5 py-2 text-right font-medium tabular-nums text-blue-400">
                                                                {Number(d.cantidad_atendida).toFixed(2)}
                                                            </td>
                                                            <td className="px-3.5 py-2 text-zinc-500">{d.observacion ?? '—'}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                    <Paginacion datos={requerimientos} />
                </CardContent>
            </Card>

            <Dialog open={modalCrearAbierto} onOpenChange={setModalCrearAbierto}>
                <DialogContent className="sm:max-w-2xl bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-semibold text-zinc-100">Nuevo requerimiento de materiales</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={guardarRequerimiento} className="space-y-4">
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div>
                                <Label htmlFor="req-fecha" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha *</Label>
                                <Input
                                    id="req-fecha"
                                    type="date"
                                    value={form.data.fecha_solicitud}
                                    onChange={(e) => form.setData('fecha_solicitud', e.target.value)}
                                    required
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                            </div>
                            <div>
                                <Label htmlFor="req-area" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Área *</Label>
                                <select
                                    id="req-area"
                                    value={form.data.area_id}
                                    onChange={(e) => form.setData('area_id', e.target.value)}
                                    className="mt-1 flex h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-2.5 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {areas.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="req-sede" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Sede destino *</Label>
                                <select
                                    id="req-sede"
                                    value={form.data.sede_id}
                                    onChange={(e) => form.setData('sede_id', e.target.value)}
                                    className="mt-1 flex h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-2.5 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {sedes.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="req-prio" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Prioridad *</Label>
                                <select
                                    id="req-prio"
                                    value={form.data.prioridad}
                                    onChange={(e) => form.setData('prioridad', e.target.value)}
                                    className="mt-1 flex h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-2.5 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {prioridades.map((p) => (
                                        <option key={p.value} value={p.value}>
                                            {p.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Ítems solicitados *</Label>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={agregarLinea}
                                    className="h-7 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[12px]"
                                >
                                    <Plus size={13} className="mr-1 text-zinc-400" /> Agregar ítem
                                </Button>
                            </div>
                            <div className="max-h-56 space-y-2 overflow-y-auto rounded-md border border-zinc-800 bg-[#09090b] p-2.5">
                                {form.data.items.map((item, idx) => (
                                    <div key={idx} className="grid grid-cols-12 items-center gap-2">
                                        <select
                                            value={item.material_id}
                                            onChange={(e) => actualizarLinea(idx, 'material_id', e.target.value)}
                                            className="col-span-6 flex h-8 rounded border border-zinc-800 bg-zinc-900 px-2 text-[12px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                        >
                                            {materiales.map((m) => (
                                                <option key={m.id} value={m.id}>
                                                    {m.codigo} - {m.nombre} ({m.unidad_medida?.abreviatura ?? ''})
                                                </option>
                                            ))}
                                        </select>
                                        <Input
                                            type="number"
                                            step="0.001"
                                            min="0.001"
                                            value={item.cantidad_solicitada}
                                            onChange={(e) => actualizarLinea(idx, 'cantidad_solicitada', e.target.value)}
                                            className="col-span-3 h-8 bg-zinc-900 border-zinc-800 text-[12px] text-zinc-200"
                                            placeholder="Cantidad"
                                            required
                                        />
                                        <Input
                                            value={item.observacion}
                                            onChange={(e) => actualizarLinea(idx, 'observacion', e.target.value)}
                                            className="col-span-2 h-8 bg-zinc-900 border-zinc-800 text-[12px] text-zinc-200"
                                            placeholder="Nota"
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => quitarLinea(idx)}
                                            disabled={form.data.items.length <= 1}
                                            className="col-span-1 h-8 px-0 text-red-400 hover:text-red-300 hover:bg-red-950/30"
                                        >
                                            <Trash2 size={14} />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                            {form.errors.items && <p className="text-[12px] text-red-400">{form.errors.items}</p>}
                        </div>

                        <div>
                            <Label htmlFor="req-obs" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Observaciones generales</Label>
                            <Input
                                id="req-obs"
                                value={form.data.observaciones}
                                onChange={(e) => form.setData('observaciones', e.target.value)}
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                            />
                        </div>

                        <label className="flex items-center gap-2 text-[13px] text-zinc-300 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={form.data.enviar}
                                onChange={(e) => form.setData('enviar', e.target.checked)}
                                className="rounded border-zinc-800 bg-zinc-900 text-blue-500 focus:ring-0 focus:ring-offset-0"
                            />
                            Enviar inmediatamente a aprobación (desmarcar para guardar como BORRADOR)
                        </label>

                        <DialogFooter className="gap-2 sm:gap-0 pt-2 border-t border-zinc-800">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalCrearAbierto(false)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="bg-zinc-100 text-zinc-900 font-medium hover:bg-white text-[13px]"
                            >
                                Guardar requerimiento
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={Boolean(reqRechazo)} onOpenChange={(open) => !open && setReqRechazo(null)}>
                <DialogContent className="bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold text-zinc-100">Rechazar requerimiento {reqRechazo?.codigo}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={confirmarRechazo} className="space-y-4">
                        <div>
                            <Label htmlFor="req-motivo" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Motivo del rechazo *</Label>
                            <Input
                                id="req-motivo"
                                value={motivoRechazo}
                                onChange={(e) => setMotivoRechazo(e.target.value)}
                                placeholder="Explique el motivo del rechazo..."
                                required
                                className="mt-1 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                            />
                        </div>
                        <DialogFooter className="gap-2 sm:gap-0 pt-2 border-t border-zinc-800">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setReqRechazo(null)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                className="border border-red-900/50 bg-red-950/50 text-red-300 hover:bg-red-900/70 text-[13px]"
                            >
                                Confirmar rechazo
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </LogisticaLayout>
    );
}
