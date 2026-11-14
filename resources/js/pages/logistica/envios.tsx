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
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Eye, FileSpreadsheet, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface EnvioRow {
    id: number;
    codigo: string;
    estado: string;
    estado_etiqueta: string;
    almacen_origen?: { id: number; nombre: string } | null;
    almacen_destino?: { id: number; nombre: string } | null;
    requerimiento?: { id: number; codigo: string } | null;
    fecha_envio: string | null;
    fecha_estimada_llegada: string | null;
    fecha_recepcion: string | null;
    guia_remision: string | null;
    detalles_count: number;
    responsable_envio?: { name: string } | null;
    recibido_por?: { name: string } | null;
    retrasado: boolean;
}

interface AlmacenOption {
    id: number;
    codigo: string;
    nombre: string;
    sede?: { nombre: string } | null;
}

interface MaterialOption {
    id: number;
    codigo: string;
    nombre: string;
    unidad_medida?: { abreviatura: string } | null;
}

interface ReqDisponible {
    id: number;
    codigo: string;
    detalles: Array<{
        id: number;
        material_id: number;
        cantidad_solicitada: string;
        cantidad_atendida: string;
        material?: MaterialOption | null;
    }>;
}

interface EnviosProps {
    envios: Paginado<EnvioRow>;
    almacenes: AlmacenOption[];
    materiales: MaterialOption[];
    requerimientosDisponibles: ReqDisponible[];
    estados: Array<{ value: string; label: string }>;
    filtros: Record<string, string | number | boolean | null>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Logística', href: '/logistica' },
    { title: 'Envíos y Recepciones', href: '/logistica/envios' },
];

export default function LogisticaEnvios({
    envios,
    almacenes,
    materiales,
    requerimientosDisponibles,
    estados,
    filtros,
}: EnviosProps) {
    const { auth } = usePage<SharedData>().props;
    const permisosEnv = auth?.user?.permisos?.envios ?? '';
    const permisosRec = auth?.user?.permisos?.recepciones_consumos ?? '';
    const puedeCrear = permisosEnv.includes('C');
    const puedeDescargarPlantilla = permisosRec.includes('L');

    const [estadoFiltro, setEstadoFiltro] = useState(filtros.estado ? String(filtros.estado) : '');
    const [buscar, setBuscar] = useState(filtros.buscar ? String(filtros.buscar) : '');
    const [soloRetrasados, setSoloRetrasados] = useState(Boolean(filtros.retrasados));
    const [modalCrear, setModalCrear] = useState(false);

    const form = useForm<{
        requerimiento_id: string;
        almacen_origen_id: string;
        almacen_destino_id: string;
        fecha_envio: string;
        fecha_estimada_llegada: string;
        guia_remision: string;
        observaciones: string;
        despachar: boolean;
        items: Array<{ material_id: string; cantidad_enviada: string; requerimiento_detalle_id?: string }>;
    }>({
        requerimiento_id: '',
        almacen_origen_id: almacenes[0]?.id ? String(almacenes[0].id) : '',
        almacen_destino_id: almacenes[1]?.id ? String(almacenes[1].id) : (almacenes[0]?.id ? String(almacenes[0].id) : ''),
        fecha_envio: new Date().toISOString().slice(0, 10),
        fecha_estimada_llegada: new Date(Date.now() + 86400000 * 2).toISOString().slice(0, 10),
        guia_remision: '',
        observaciones: '',
        despachar: true,
        items: [{ material_id: materiales[0]?.id ? String(materiales[0].id) : '', cantidad_enviada: '1' }],
    });

    const filtrar = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            '/logistica/envios',
            {
                estado: estadoFiltro || undefined,
                buscar: buscar || undefined,
                retrasados: soloRetrasados ? 1 : undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const alSeleccionarRequerimiento = (reqId: string) => {
        form.setData((prev) => {
            if (!reqId) {
                return {
                    ...prev,
                    requerimiento_id: '',
                    items: [{ material_id: materiales[0]?.id ? String(materiales[0].id) : '', cantidad_enviada: '1' }],
                };
            }
            const req = requerimientosDisponibles.find((r) => String(r.id) === reqId);
            if (!req) return { ...prev, requerimiento_id: reqId };

            const itemsFromReq = req.detalles.map((d) => {
                const pend = Math.max(0, Number(d.cantidad_solicitada) - Number(d.cantidad_atendida));
                return {
                    material_id: String(d.material_id),
                    cantidad_enviada: pend > 0 ? String(pend) : '1',
                    requerimiento_detalle_id: String(d.id),
                };
            });

            return {
                ...prev,
                requerimiento_id: reqId,
                items: itemsFromReq.length ? itemsFromReq : prev.items,
            };
        });
    };

    const guardarEnvio = (e: FormEvent) => {
        e.preventDefault();
        form.post('/logistica/envios', {
            onSuccess: () => {
                setModalCrear(false);
                form.reset();
            },
        });
    };

    return (
        <LogisticaLayout
            breadcrumbs={breadcrumbs}
            titulo="Envíos Trujillo – Pataz y Recepciones"
            descripcion="Control de traslados entre almacenes, salida de stock, confirmación de llegada y detección de diferencias."
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <form onSubmit={filtrar} className="flex flex-wrap items-center gap-2.5">
                    <Input
                        value={buscar}
                        onChange={(e) => setBuscar(e.target.value)}
                        placeholder="Código ENV- o guía..."
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
                    <label className="flex items-center gap-2 px-1 text-[13px] text-zinc-300 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={soloRetrasados}
                            onChange={(e) => setSoloRetrasados(e.target.checked)}
                            className="rounded border-zinc-800 bg-zinc-900 text-amber-500 focus:ring-0 focus:ring-offset-0"
                        />
                        Solo retrasados
                    </label>
                    <Button
                        type="submit"
                        variant="outline"
                        className="h-9 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 text-[13px]"
                    >
                        Filtrar
                    </Button>
                </form>

                <div className="flex flex-wrap gap-2">
                    {puedeDescargarPlantilla && (
                        <a
                            href="/logistica/envios/plantilla-recepcion"
                            className="inline-flex h-9 items-center gap-1.5 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] font-medium text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 transition-colors"
                        >
                            <FileSpreadsheet size={15} className="text-emerald-400" /> Plantilla Excel Recepción
                        </a>
                    )}
                    {puedeCrear && (
                        <Button
                            type="button"
                            onClick={() => setModalCrear(true)}
                            className="h-9 bg-zinc-100 text-zinc-900 font-medium px-4 py-2 rounded-md hover:bg-white text-[13px]"
                        >
                            <Plus size={16} className="mr-1.5" /> Nuevo Envío
                        </Button>
                    )}
                </div>
            </div>

            <Card className="border border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-0">
                    {envios.data.length === 0 ? (
                        <div className="p-10 text-center text-[13px] text-zinc-500">No se encontraron envíos para los filtros aplicados.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-[13px]">
                                <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">
                                    <tr>
                                        <th className="px-4 py-3">Código</th>
                                        <th className="px-4 py-3">Ruta</th>
                                        <th className="px-4 py-3">Requerimiento</th>
                                        <th className="px-4 py-3">Guía</th>
                                        <th className="px-4 py-3">Fechas</th>
                                        <th className="px-4 py-3">Estado</th>
                                        <th className="px-4 py-3 text-right">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {envios.data.map((env) => (
                                        <tr key={env.id} className="hover:bg-zinc-900/40 transition-colors">
                                            <td className="px-4 py-3 font-semibold text-zinc-100">
                                                <div>{env.codigo}</div>
                                                <div className="text-[11px] font-normal text-zinc-500">{env.detalles_count} ítem(s)</div>
                                            </td>
                                            <td className="px-4 py-3 text-zinc-200">
                                                {env.almacen_origen?.nombre ?? '—'} <span className="text-zinc-500">→</span> {env.almacen_destino?.nombre ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-zinc-300 font-mono text-[12px]">{env.requerimiento?.codigo ?? 'Envío directo'}</td>
                                            <td className="px-4 py-3 text-zinc-300">{env.guia_remision ?? '—'}</td>
                                            <td className="px-4 py-3 text-[12px] text-zinc-400 space-y-0.5">
                                                <div>Salida: <span className="text-zinc-300">{env.fecha_envio ?? 'Pendiente'}</span></div>
                                                <div>Est.: <span className="text-zinc-300">{env.fecha_estimada_llegada ?? '—'}</span></div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <StatusBadge status={env.estado} />
                                                    {env.retrasado && (
                                                        <Badge variant="outline" className="border-red-900/50 bg-red-950/40 text-red-400 text-[11px]">
                                                            Retrasado
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/logistica/envios/${env.id}`}
                                                    className="inline-flex h-8 items-center gap-1.5 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[12px] font-medium text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 transition-colors"
                                                >
                                                    <Eye size={13} className="text-zinc-400" /> Abrir
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <Paginacion datos={envios} />
                </CardContent>
            </Card>

            <Dialog open={modalCrear} onOpenChange={setModalCrear}>
                <DialogContent className="sm:max-w-2xl bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold text-zinc-100">Programar nuevo envío entre almacenes</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={guardarEnvio} className="space-y-4">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <Label htmlFor="env-req" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Requerimiento (opcional)</Label>
                                <select
                                    id="env-req"
                                    value={form.data.requerimiento_id}
                                    onChange={(e) => alSeleccionarRequerimiento(e.target.value)}
                                    className="mt-1 flex h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-2.5 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    <option value="">Envío libre</option>
                                    {requerimientosDisponibles.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.codigo}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="env-ori" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Almacén origen *</Label>
                                <select
                                    id="env-ori"
                                    value={form.data.almacen_origen_id}
                                    onChange={(e) => form.setData('almacen_origen_id', e.target.value)}
                                    className="mt-1 flex h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-2.5 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                    required
                                >
                                    {almacenes.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="env-dest" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Almacén destino *</Label>
                                <select
                                    id="env-dest"
                                    value={form.data.almacen_destino_id}
                                    onChange={(e) => form.setData('almacen_destino_id', e.target.value)}
                                    className="mt-1 flex h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-2.5 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                    required
                                >
                                    {almacenes.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.nombre}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.almacen_destino_id && (
                                    <p className="mt-1 text-[12px] text-red-400">{form.errors.almacen_destino_id}</p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <Label htmlFor="env-fecha" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha envío</Label>
                                <Input
                                    id="env-fecha"
                                    type="date"
                                    value={form.data.fecha_envio}
                                    onChange={(e) => form.setData('fecha_envio', e.target.value)}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                            </div>
                            <div>
                                <Label htmlFor="env-est" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Fecha estimada llegada</Label>
                                <Input
                                    id="env-est"
                                    type="date"
                                    value={form.data.fecha_estimada_llegada}
                                    onChange={(e) => form.setData('fecha_estimada_llegada', e.target.value)}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                            </div>
                            <div>
                                <Label htmlFor="env-guia" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Guía de remisión</Label>
                                <Input
                                    id="env-guia"
                                    value={form.data.guia_remision}
                                    onChange={(e) => form.setData('guia_remision', e.target.value)}
                                    placeholder="T001-000123"
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Materiales a enviar *</Label>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        form.setData('items', [
                                            ...form.data.items,
                                            {
                                                material_id: materiales[0]?.id ? String(materiales[0].id) : '',
                                                cantidad_enviada: '1',
                                                requerimiento_detalle_id: '',
                                            },
                                        ])
                                    }
                                    className="h-7 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[12px]"
                                >
                                    <Plus size={13} className="mr-1 text-zinc-400" /> Agregar material
                                </Button>
                            </div>
                            <div className="max-h-52 space-y-2 overflow-y-auto rounded-md border border-zinc-800 bg-[#09090b] p-2.5">
                                {form.data.items.map((item, idx) => (
                                    <div key={idx} className="grid grid-cols-12 items-center gap-2">
                                        <select
                                            value={item.material_id}
                                            onChange={(e) => {
                                                const copia = [...form.data.items];
                                                copia[idx] = { ...copia[idx], material_id: e.target.value };
                                                form.setData('items', copia);
                                            }}
                                            className="col-span-8 flex h-8 rounded border border-zinc-800 bg-zinc-900 px-2 text-[12px] text-zinc-200 focus:outline-none focus:border-blue-500"
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
                                            value={item.cantidad_enviada}
                                            onChange={(e) => {
                                                const copia = [...form.data.items];
                                                copia[idx] = { ...copia[idx], cantidad_enviada: e.target.value };
                                                form.setData('items', copia);
                                            }}
                                            className="col-span-3 h-8 bg-zinc-900 border-zinc-800 text-[12px] text-zinc-200"
                                            required
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            disabled={form.data.items.length <= 1}
                                            onClick={() =>
                                                form.setData(
                                                    'items',
                                                    form.data.items.filter((_, i) => i !== idx),
                                                )
                                            }
                                            className="col-span-1 h-8 px-0 text-red-400 hover:text-red-300 hover:bg-red-950/30"
                                        >
                                            <Trash2 size={14} />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                            {(form.errors as Record<string, string>).stock && (
                                <p className="text-[12px] text-red-400">{(form.errors as Record<string, string>).stock}</p>
                            )}
                        </div>

                        <label className="flex items-center gap-2 text-[13px] text-zinc-300 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={form.data.despachar}
                                onChange={(e) => form.setData('despachar', e.target.checked)}
                                className="rounded border-zinc-800 bg-zinc-900 text-blue-500 focus:ring-0 focus:ring-offset-0"
                            />
                            Despachar inmediatamente (descuenta stock en origen y pasa a EN_TRANSITO)
                        </label>

                        <DialogFooter className="border-t border-zinc-800 pt-3 gap-2 sm:gap-0">
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
                                disabled={form.processing}
                                className="bg-zinc-100 text-zinc-900 font-medium hover:bg-white text-[13px]"
                            >
                                Guardar envío
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </LogisticaLayout>
    );
}
