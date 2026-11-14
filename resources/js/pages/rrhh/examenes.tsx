import InputError from '@/components/input-error';
import { ModalConfirmacion } from '@/components/modal-confirmacion';
import { Paginacion, type Paginado } from '@/components/paginacion';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RrhhLayout from '@/layouts/rrhh/rrhh-layout';
import { type BreadcrumbItem } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { Download, Paperclip, Pencil, Plus, RotateCcw, Search, Trash2, X } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface ExamenFila {
    id: number;
    trabajador_id: number;
    trabajador_dni: string;
    trabajador_nombre: string;
    cargo_nombre: string;
    tipo: string;
    tipo_etiqueta: string;
    fecha_examen: string | null;
    fecha_examen_fmt: string | null;
    fecha_vencimiento: string | null;
    fecha_vencimiento_fmt: string | null;
    vigente: boolean;
    dias_para_vencer: number;
    clinica: string;
    resultado: string | null;
    resultado_etiqueta: string;
    observaciones: string | null;
    activo: boolean;
    adjuntos: Array<{ id: number; nombre: string; tamano: number | null }>;
}

interface ExamenesProps {
    examenes: Paginado<ExamenFila>;
    filtros: {
        q: string;
        estado_venc: string;
        trabajador_id: string;
        tipo: string;
        ver: string;
    };
    trabajadores: Array<{ id: number; label: string }>;
    tipos: Array<{ value: string; label: string }>;
    resultados: Array<{ value: string; label: string }>;
    esMedicoAutorizado: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'RR.HH.', href: '/rrhh' },
    { title: 'Exámenes Ocupacionales', href: '/rrhh/examenes' },
];

export default function ExamenesPage({
    examenes,
    filtros,
    trabajadores,
    tipos,
    resultados,
    esMedicoAutorizado,
}: ExamenesProps) {
    const [q, setQ] = useState(filtros.q);
    const [estadoVenc, setEstadoVenc] = useState(filtros.estado_venc);
    const [tipoFiltro, setTipoFiltro] = useState(filtros.tipo);
    const [modalAbierto, setModalAbierto] = useState(false);
    const [editando, setEditando] = useState<ExamenFila | null>(null);
    const [paraBaja, setParaBaja] = useState<ExamenFila | null>(null);
    const [procesandoBaja, setProcesandoBaja] = useState(false);

    const form = useForm<{
        trabajador_id: string;
        tipo: string;
        fecha_examen: string;
        fecha_vencimiento: string;
        clinica: string;
        resultado: string;
        observaciones: string;
        archivo: File | null;
        _method?: string;
    }>({
        trabajador_id: trabajadores[0] ? String(trabajadores[0].id) : '',
        tipo: tipos[0]?.value ?? 'PERIODICO',
        fecha_examen: new Date().toISOString().slice(0, 10),
        fecha_vencimiento: new Date(Date.now() + 365 * 86400 * 1000).toISOString().slice(0, 10),
        clinica: '',
        resultado: resultados[0]?.value ?? 'APTO',
        observaciones: '',
        archivo: null,
    });

    const aplicarFiltros = (e?: FormEvent) => {
        e?.preventDefault();
        router.get('/rrhh/examenes', { q, estado_venc: estadoVenc, tipo: tipoFiltro }, { preserveState: true, replace: true });
    };

    const limpiarFiltros = () => {
        setQ('');
        setEstadoVenc('todos');
        setTipoFiltro('');
        router.get('/rrhh/examenes', {}, { preserveState: true, replace: true });
    };

    const abrirCrear = () => {
        setEditando(null);
        form.clearErrors();
        form.setData({
            trabajador_id: filtros.trabajador_id || (trabajadores[0] ? String(trabajadores[0].id) : ''),
            tipo: 'PERIODICO',
            fecha_examen: new Date().toISOString().slice(0, 10),
            fecha_vencimiento: new Date(Date.now() + 365 * 86400 * 1000).toISOString().slice(0, 10),
            clinica: '',
            resultado: 'APTO',
            observaciones: '',
            archivo: null,
        });
        setModalAbierto(true);
    };

    const abrirEditar = (ex: ExamenFila) => {
        setEditando(ex);
        form.clearErrors();
        form.setData({
            trabajador_id: String(ex.trabajador_id),
            tipo: ex.tipo,
            fecha_examen: ex.fecha_examen ?? new Date().toISOString().slice(0, 10),
            fecha_vencimiento: ex.fecha_vencimiento ?? new Date().toISOString().slice(0, 10),
            clinica: ex.clinica,
            resultado: ex.resultado ?? 'APTO',
            observaciones: ex.observaciones ?? '',
            archivo: null,
            _method: 'put',
        });
        setModalAbierto(true);
    };

    const guardar = (e: FormEvent) => {
        e.preventDefault();
        if (editando) {
            form.post(`/rrhh/examenes/${editando.id}`, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setModalAbierto(false),
            });
        } else {
            form.post('/rrhh/examenes', {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setModalAbierto(false),
            });
        }
    };

    const confirmarBaja = () => {
        if (!paraBaja) return;
        setProcesandoBaja(true);
        router.delete(`/rrhh/examenes/${paraBaja.id}`, {
            preserveScroll: true,
            onFinish: () => {
                setProcesandoBaja(false);
                setParaBaja(null);
            },
        });
    };

    const restaurar = (id: number) => {
        router.post(`/rrhh/examenes/${id}/restaurar`, {}, { preserveScroll: true });
    };

    return (
        <RrhhLayout
            titulo="Exámenes Ocupacionales"
            descripcion="Control médico ocupacional, vigencia, alertas por vencer ≤ 30 días y certificados adjuntos."
            breadcrumbs={breadcrumbs}
            acciones={
                esMedicoAutorizado && (
                    <Button onClick={abrirCrear} className="min-h-9 bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                        <Plus className="mr-2 h-4 w-4" />
                        Registrar examen
                    </Button>
                )
            }
        >
            <Card className="border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-4">
                    <form onSubmit={aplicarFiltros} className="grid gap-3 md:grid-cols-5">
                        <div className="relative md:col-span-2">
                            <Search className="pointer-events-none absolute top-3 left-3 h-4 w-4 text-zinc-500" />
                            <Input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Buscar por trabajador, DNI o clínica..."
                                className="min-h-10 pl-9 bg-zinc-900 border-zinc-800 text-zinc-200"
                            />
                        </div>

                        <select
                            value={estadoVenc}
                            onChange={(e) => setEstadoVenc(e.target.value)}
                            className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                        >
                            <option value="todos">Todos los estados de vigencia</option>
                            <option value="alertas">Vencidos o vencen en ≤ 30 días</option>
                            <option value="por_vencer">Por vencer ≤ 30 días</option>
                            <option value="vencidos">Vencidos</option>
                            <option value="vigentes">Vigentes</option>
                            {esMedicoAutorizado && <option value="no_aptos">No aptos / con restricciones</option>}
                        </select>

                        <select
                            value={tipoFiltro}
                            onChange={(e) => setTipoFiltro(e.target.value)}
                            className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                        >
                            <option value="">Todos los tipos</option>
                            {tipos.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>

                        <div className="flex gap-2">
                            <Button type="submit" variant="secondary" className="min-h-10 flex-1 border border-zinc-800 bg-zinc-900 text-zinc-200 hover:bg-zinc-800">
                                Filtrar
                            </Button>
                            <Button type="button" variant="outline" onClick={limpiarFiltros} className="min-h-10 px-3 border-zinc-800 text-zinc-400 hover:text-white" title="Limpiar">
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card className="border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">
                                <tr>
                                    <th className="px-4 py-3">Trabajador</th>
                                    <th className="px-4 py-3">Tipo</th>
                                    <th className="px-4 py-3">Fecha / Vencimiento</th>
                                    <th className="px-4 py-3">Clínica</th>
                                    <th className="px-4 py-3">Resultado</th>
                                    {esMedicoAutorizado && <th className="px-4 py-3">Certificado</th>}
                                    {esMedicoAutorizado && <th className="px-4 py-3 text-right">Acciones</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {examenes.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={esMedicoAutorizado ? 7 : 5} className="px-4 py-10 text-center text-zinc-500">
                                            No se encontraron exámenes ocupacionales con los filtros actuales.
                                        </td>
                                    </tr>
                                ) : (
                                    examenes.data.map((ex) => (
                                        <tr key={ex.id} className="hover:bg-zinc-900/40 transition-colors">
                                            <td className="px-4 py-3">
                                                <Link href={`/rrhh/trabajadores/${ex.trabajador_id}`} className="font-medium text-zinc-200 hover:text-white hover:underline transition-colors">
                                                    {ex.trabajador_nombre}
                                                </Link>
                                                <div className="text-xs text-zinc-500">DNI {ex.trabajador_dni}</div>
                                            </td>
                                            <td className="px-4 py-3 text-zinc-300">{ex.tipo_etiqueta}</td>
                                            <td className="px-4 py-3 tabular-nums">
                                                <div className="text-zinc-300">{ex.fecha_examen_fmt}</div>
                                                <div
                                                    className={`text-xs font-medium ${
                                                        ex.dias_para_vencer < 0
                                                            ? 'text-rose-400'
                                                            : ex.dias_para_vencer <= 30
                                                              ? 'text-amber-400'
                                                              : 'text-zinc-500'
                                                    }`}
                                                >
                                                    Vence: {ex.fecha_vencimiento_fmt} (
                                                    {ex.dias_para_vencer < 0 ? 'Vencido' : `${ex.dias_para_vencer}d`})
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-zinc-400">{ex.clinica}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={ex.resultado} label={ex.resultado_etiqueta} />
                                            </td>
                                            {esMedicoAutorizado && (
                                                <td className="px-4 py-3">
                                                    {ex.adjuntos.length === 0 ? (
                                                        <span className="text-xs text-zinc-500">Sin adjunto</span>
                                                    ) : (
                                                        <div className="flex flex-wrap gap-1">
                                                            {ex.adjuntos.map((adj) => (
                                                                <a
                                                                    key={adj.id}
                                                                    href={`/rrhh/examenes/adjuntos/${adj.id}/descargar`}
                                                                    className="inline-flex items-center gap-1 rounded border border-zinc-800 bg-zinc-900 px-2 py-1 text-xs font-medium text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors"
                                                                >
                                                                    <Download className="h-3 w-3" />
                                                                    {adj.nombre}
                                                                </a>
                                                            ))}
                                                        </div>
                                                    )}
                                                </td>
                                            )}
                                            {esMedicoAutorizado && (
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex justify-end gap-1.5">
                                                        <Button variant="outline" size="sm" onClick={() => abrirEditar(ex)} className="min-h-8 px-2.5 border-zinc-800 text-zinc-400 hover:text-zinc-200">
                                                            <Pencil className="h-3.5 w-3.5" />
                                                        </Button>
                                                        {ex.activo ? (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => setParaBaja(ex)}
                                                                className="min-h-8 px-2.5 border-zinc-800 text-rose-400 hover:text-rose-300"
                                                            >
                                                                <Trash2 className="h-3.5 w-3.5" />
                                                            </Button>
                                                        ) : (
                                                            <Button variant="outline" size="sm" onClick={() => restaurar(ex.id)} className="min-h-8 px-2.5 border-zinc-800 text-emerald-400 hover:text-emerald-300">
                                                                <RotateCcw className="h-3.5 w-3.5" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Paginacion datos={examenes} />
                </CardContent>
            </Card>

            <Dialog open={modalAbierto} onOpenChange={setModalAbierto}>
                <DialogContent className="sm:max-w-lg bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar examen ocupacional' : 'Registrar examen ocupacional'}</DialogTitle>
                        <DialogDescription>Solo RR.HH. y Administración acceden a resultados médicos y archivos adjuntos.</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={guardar} className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="ex-trab">Trabajador</Label>
                            <select
                                id="ex-trab"
                                value={form.data.trabajador_id}
                                onChange={(e) => form.setData('trabajador_id', e.target.value)}
                                className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                {trabajadores.map((t) => (
                                    <option key={t.id} value={String(t.id)}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.trabajador_id} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="ex-tipo">Tipo</Label>
                                <select
                                    id="ex-tipo"
                                    value={form.data.tipo}
                                    onChange={(e) => form.setData('tipo', e.target.value)}
                                    className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {tipos.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ex-res">Resultado</Label>
                                <select
                                    id="ex-res"
                                    value={form.data.resultado}
                                    onChange={(e) => form.setData('resultado', e.target.value)}
                                    className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {resultados.map((r) => (
                                        <option key={r.value} value={r.value}>
                                            {r.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="ex-fe">Fecha del examen</Label>
                                <Input
                                    id="ex-fe"
                                    type="date"
                                    value={form.data.fecha_examen}
                                    onChange={(e) => form.setData('fecha_examen', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.fecha_examen} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ex-fv">Fecha de vencimiento</Label>
                                <Input
                                    id="ex-fv"
                                    type="date"
                                    value={form.data.fecha_vencimiento}
                                    onChange={(e) => form.setData('fecha_vencimiento', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.fecha_vencimiento} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="ex-clinica">Clínica / Centro médico</Label>
                            <Input
                                id="ex-clinica"
                                value={form.data.clinica}
                                onChange={(e) => form.setData('clinica', e.target.value)}
                                placeholder="Clínica San Pablo Trujillo"
                                required
                            />
                            <InputError message={form.errors.clinica} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="ex-obs">Observaciones médicas</Label>
                            <Input
                                id="ex-obs"
                                value={form.data.observaciones}
                                onChange={(e) => form.setData('observaciones', e.target.value)}
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="ex-archivo" className="flex items-center gap-1.5">
                                <Paperclip className="h-3.5 w-3.5" />
                                Adjuntar certificado (PDF/JPG/PNG, máx. 5 MB)
                            </Label>
                            <Input
                                id="ex-archivo"
                                type="file"
                                accept=".pdf,image/png,image/jpeg"
                                onChange={(e) => form.setData('archivo', e.target.files?.[0] ?? null)}
                            />
                            <InputError message={form.errors.archivo} />
                        </div>

                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button type="button" variant="outline" onClick={() => setModalAbierto(false)} className="min-h-10">
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing} className="min-h-9 bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                                {form.processing ? 'Guardando...' : editando ? 'Guardar cambios' : 'Registrar examen'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ModalConfirmacion
                abierto={Boolean(paraBaja)}
                onCerrar={() => setParaBaja(null)}
                onConfirmar={confirmarBaja}
                procesando={procesandoBaja}
                titulo="Desactivar examen ocupacional"
                descripcion={`¿Deseas desactivar el examen de ${paraBaja?.trabajador_nombre ?? ''}?`}
                textoConfirmar="Desactivar"
            />
        </RrhhLayout>
    );
}

