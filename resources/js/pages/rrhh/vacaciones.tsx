import InputError from '@/components/input-error';
import { Paginacion, type Paginado } from '@/components/paginacion';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RrhhLayout from '@/layouts/rrhh/rrhh-layout';
import { type BreadcrumbItem } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { Ban, Check, Plus, Search, X } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface VacacionesProps {
    solicitudes: Paginado<{
        id: number;
        trabajador_id: number;
        trabajador_dni: string;
        trabajador_nombre: string;
        cargo_nombre: string;
        anio: number | null;
        saldo_periodo: number;
        fecha_inicio_fmt: string;
        fecha_fin_fmt: string;
        dias: number;
        estado: string;
        estado_etiqueta: string;
        solicitado_por: string;
        aprobado_por: string | null;
        comentario: string | null;
        puede_aprobar: boolean;
        es_propia: boolean;
    }>;
    periodos: Array<{
        id: number;
        trabajador_id: number;
        trabajador_nombre: string;
        trabajador_dni: string;
        anio: number;
        dias_ganados: number;
        dias_tomados: number;
        saldo: number;
    }>;
    filtros: { q: string; estado: string; trabajador_id: string; anio: string };
    trabajadores: Array<{
        id: number;
        label: string;
        periodos: Array<{ id: number; anio: number; saldo: number }>;
    }>;
    estados: Array<{ value: string; label: string }>;
    permisos: { crear: boolean; aprobar: boolean; editar: boolean };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'RR.HH.', href: '/rrhh' },
    { title: 'Vacaciones', href: '/rrhh/vacaciones' },
];

export default function VacacionesPage({
    solicitudes,
    periodos,
    filtros,
    trabajadores,
    estados,
    permisos,
}: VacacionesProps) {
    const [q, setQ] = useState(filtros.q);
    const [estadoFiltro, setEstadoFiltro] = useState(filtros.estado);
    const [modalSolicitud, setModalSolicitud] = useState(false);
    const [modalPeriodo, setModalPeriodo] = useState(false);

    const formSol = useForm({
        trabajador_id: trabajadores[0] ? String(trabajadores[0].id) : '',
        periodo_vacacional_id: '',
        fecha_inicio: new Date().toISOString().slice(0, 10),
        fecha_fin: new Date(Date.now() + 6 * 86400 * 1000).toISOString().slice(0, 10),
        comentario: '',
    });

    const formPeriodo = useForm({
        trabajador_id: trabajadores[0] ? String(trabajadores[0].id) : '',
        anio: String(new Date().getFullYear()),
        dias_ganados: '30',
    });

    const trabajadorSeleccionado = trabajadores.find((t) => String(t.id) === formSol.data.trabajador_id);

    const aplicarFiltros = (e?: FormEvent) => {
        e?.preventDefault();
        router.get('/rrhh/vacaciones', { q, estado: estadoFiltro }, { preserveState: true, replace: true });
    };

    const crearSolicitud = (e: FormEvent) => {
        e.preventDefault();
        formSol.post('/rrhh/vacaciones', {
            preserveScroll: true,
            onSuccess: () => setModalSolicitud(false),
        });
    };

    const guardarPeriodo = (e: FormEvent) => {
        e.preventDefault();
        formPeriodo.post('/rrhh/vacaciones/periodos', {
            preserveScroll: true,
            onSuccess: () => setModalPeriodo(false),
        });
    };

    const resolver = (id: number, accion: 'aprobar' | 'rechazar') => {
        router.post(`/rrhh/vacaciones/${id}/resolver`, { accion }, { preserveScroll: true });
    };

    const cancelar = (id: number) => {
        router.post(`/rrhh/vacaciones/${id}/cancelar`, {}, { preserveScroll: true });
    };

    return (
        <RrhhLayout
            titulo="Vacaciones y Períodos"
            descripcion="Control de saldo vacacional por período anual, solicitudes sin solapamiento y aprobación por jefatura."
            breadcrumbs={breadcrumbs}
            acciones={
                <div className="flex flex-wrap gap-2">
                    {permisos.aprobar && (
                        <Button type="button" variant="outline" onClick={() => setModalPeriodo(true)} className="min-h-10">
                            Configurar período
                        </Button>
                    )}
                    {permisos.crear && (
                        <Button type="button" onClick={() => setModalSolicitud(true)} className="min-h-9 bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                            <Plus className="mr-2 h-4 w-4" />
                            Nueva solicitud
                        </Button>
                    )}
                </div>
            }
        >
            <Card className="border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-4">
                    <form onSubmit={aplicarFiltros} className="grid gap-3 sm:grid-cols-4">
                        <div className="relative sm:col-span-2">
                            <Search className="pointer-events-none absolute top-3 left-3 h-4 w-4 text-zinc-500" />
                            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar trabajador o DNI..." className="min-h-10 pl-9" />
                        </div>
                        <select
                            value={estadoFiltro}
                            onChange={(e) => setEstadoFiltro(e.target.value)}
                            className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                        >
                            <option value="">Todos los estados</option>
                            {estados.map((est) => (
                                <option key={est.value} value={est.value}>
                                    {est.label}
                                </option>
                            ))}
                        </select>
                        <div className="flex gap-2">
                            <Button type="submit" variant="secondary" className="min-h-10 flex-1">
                                Filtrar
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => router.get('/rrhh/vacaciones')}
                                className="min-h-10 px-3"
                                title="Limpiar"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card className="border-zinc-800 bg-[#0f0f13]">
                <CardHeader>
                    <CardTitle className="text-base text-zinc-100">Solicitudes de vacaciones</CardTitle>
                    <CardDescription>Quien registra una solicitud no puede aprobarla ni rechazarla.</CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">
                                <tr>
                                    <th className="px-4 py-3">Trabajador</th>
                                    <th className="px-4 py-3">Período / Saldo</th>
                                    <th className="px-4 py-3">Fechas</th>
                                    <th className="px-4 py-3">Días</th>
                                    <th className="px-4 py-3">Estado</th>
                                    <th className="px-4 py-3">Solicitado / Aprobado</th>
                                    <th className="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {solicitudes.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-10 text-center text-zinc-500">
                                            No hay solicitudes registradas.
                                        </td>
                                    </tr>
                                ) : (
                                    solicitudes.data.map((s) => (
                                        <tr key={s.id} className="hover:bg-slate-50/70">
                                            <td className="px-4 py-3">
                                                <Link href={`/rrhh/trabajadores/${s.trabajador_id}`} className="font-semibold text-zinc-100 hover:underline">
                                                    {s.trabajador_nombre}
                                                </Link>
                                                <div className="text-xs text-zinc-500">DNI {s.trabajador_dni}</div>
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                <div>Año {s.anio}</div>
                                                <div className="text-xs text-zinc-500">Saldo: {s.saldo_periodo} d</div>
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {s.fecha_inicio_fmt} al {s.fecha_fin_fmt}
                                            </td>
                                            <td className="px-4 py-3 font-semibold tabular-nums">{s.dias}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={s.estado} label={s.estado_etiqueta} />
                                            </td>
                                            <td className="px-4 py-3 text-xs text-zinc-400">
                                                <div>Sol: {s.solicitado_por}</div>
                                                {s.aprobado_por && <div>Res: {s.aprobado_por}</div>}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-1.5">
                                                    {s.puede_aprobar && (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                onClick={() => resolver(s.id, 'aprobar')}
                                                                className="min-h-8 border border-emerald-900/60 bg-emerald-950/40 text-emerald-400 hover:bg-emerald-900/40 px-2.5"
                                                                title="Aprobar"
                                                            >
                                                                <Check className="h-3.5 w-3.5" />
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => resolver(s.id, 'rechazar')}
                                                                className="min-h-8 border border-rose-900/60 bg-rose-950/40 text-rose-400 hover:bg-rose-900/40 px-2.5"
                                                                title="Rechazar"
                                                            >
                                                                <Ban className="h-3.5 w-3.5" />
                                                            </Button>
                                                        </>
                                                    )}
                                                    {permisos.editar && (s.estado === 'PENDIENTE' || s.estado === 'APROBADA') && (
                                                        <Button size="sm" variant="outline" onClick={() => cancelar(s.id)} className="min-h-8 px-2.5 border-zinc-800 text-zinc-400 hover:text-white">
                                                            Cancelar
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
                    <Paginacion datos={solicitudes} />
                </CardContent>
            </Card>

            <Card className="border-zinc-800 bg-[#0f0f13]">
                <CardHeader>
                    <CardTitle className="text-base text-zinc-100">Saldos por período vacacional</CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">
                                <tr>
                                    <th className="px-4 py-3">Trabajador</th>
                                    <th className="px-4 py-3">Año</th>
                                    <th className="px-4 py-3">Ganados</th>
                                    <th className="px-4 py-3">Tomados</th>
                                    <th className="px-4 py-3">Saldo disponible</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {periodos.slice(0, 15).map((p) => (
                                    <tr key={p.id} className="hover:bg-zinc-900/40 transition-colors">
                                        <td className="px-4 py-2.5 font-medium text-zinc-200">
                                            {p.trabajador_nombre} <span className="font-mono text-xs text-zinc-500">({p.trabajador_dni})</span>
                                        </td>
                                        <td className="px-4 py-2.5 tabular-nums text-zinc-400">{p.anio}</td>
                                        <td className="px-4 py-2.5 tabular-nums text-zinc-400">{p.dias_ganados}</td>
                                        <td className="px-4 py-2.5 tabular-nums text-zinc-400">{p.dias_tomados}</td>
                                        <td className="px-4 py-2.5 font-semibold text-emerald-400 tabular-nums">{p.saldo}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Dialog open={modalSolicitud} onOpenChange={setModalSolicitud}>
                <DialogContent className="bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle>Nueva solicitud de vacaciones</DialogTitle>
                        <DialogDescription>Se valida automáticamente el saldo del período y que no exista solapamiento.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={crearSolicitud} className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="vac-trab">Trabajador</Label>
                            <select
                                id="vac-trab"
                                value={formSol.data.trabajador_id}
                                onChange={(e) => {
                                    formSol.setData('trabajador_id', e.target.value);
                                    formSol.setData('periodo_vacacional_id', '');
                                }}
                                className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                {trabajadores.map((t) => (
                                    <option key={t.id} value={String(t.id)}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={formSol.errors.trabajador_id} />
                        </div>

                        {trabajadorSeleccionado && trabajadorSeleccionado.periodos.length > 0 && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="vac-per">Período vacacional (opcional: automático por año)</Label>
                                <select
                                    id="vac-per"
                                    value={formSol.data.periodo_vacacional_id}
                                    onChange={(e) => formSol.setData('periodo_vacacional_id', e.target.value)}
                                    className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    <option value="">Automático según año de inicio</option>
                                    {trabajadorSeleccionado.periodos.map((p) => (
                                        <option key={p.id} value={String(p.id)}>
                                            Año {p.anio} (saldo: {p.saldo} días)
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="vac-fi">Fecha inicio</Label>
                                <Input
                                    id="vac-fi"
                                    type="date"
                                    value={formSol.data.fecha_inicio}
                                    onChange={(e) => formSol.setData('fecha_inicio', e.target.value)}
                                    required
                                />
                                <InputError message={formSol.errors.fecha_inicio} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="vac-ff">Fecha fin</Label>
                                <Input
                                    id="vac-ff"
                                    type="date"
                                    value={formSol.data.fecha_fin}
                                    onChange={(e) => formSol.setData('fecha_fin', e.target.value)}
                                    required
                                />
                                <InputError message={formSol.errors.fecha_fin} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="vac-com">Comentario</Label>
                            <Input
                                id="vac-com"
                                value={formSol.data.comentario}
                                onChange={(e) => formSol.setData('comentario', e.target.value)}
                            />
                        </div>

                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button type="button" variant="outline" onClick={() => setModalSolicitud(false)} className="min-h-10">
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={formSol.processing} className="min-h-9 bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                                Registrar solicitud
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={modalPeriodo} onOpenChange={setModalPeriodo}>
                <DialogContent className="bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle>Configurar período vacacional</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={guardarPeriodo} className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="per-trab">Trabajador</Label>
                            <select
                                id="per-trab"
                                value={formPeriodo.data.trabajador_id}
                                onChange={(e) => formPeriodo.setData('trabajador_id', e.target.value)}
                                className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                {trabajadores.map((t) => (
                                    <option key={t.id} value={String(t.id)}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="per-anio">Año</Label>
                                <Input
                                    id="per-anio"
                                    type="number"
                                    value={formPeriodo.data.anio}
                                    onChange={(e) => formPeriodo.setData('anio', e.target.value)}
                                    required
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="per-dias">Días ganados</Label>
                                <Input
                                    id="per-dias"
                                    type="number"
                                    value={formPeriodo.data.dias_ganados}
                                    onChange={(e) => formPeriodo.setData('dias_ganados', e.target.value)}
                                    required
                                />
                                <InputError message={formPeriodo.errors.dias_ganados} />
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button type="button" variant="outline" onClick={() => setModalPeriodo(false)} className="min-h-10">
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={formPeriodo.processing} className="min-h-9 bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                                Guardar período
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </RrhhLayout>
    );
}

