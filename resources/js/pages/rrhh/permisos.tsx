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

interface PermisosProps {
    permisosLista: Paginado<{
        id: number;
        trabajador_id: number;
        trabajador_dni: string;
        trabajador_nombre: string;
        cargo_nombre: string;
        tipo: string;
        tipo_etiqueta: string;
        con_goce: boolean;
        fecha_inicio_fmt: string;
        fecha_fin_fmt: string;
        dias: number;
        motivo: string;
        estado: string;
        estado_etiqueta: string;
        solicitado_por: string;
        aprobado_por: string | null;
        puede_aprobar: boolean;
        es_propia: boolean;
    }>;
    filtros: { q: string; estado: string; tipo: string; trabajador_id: string };
    trabajadores: Array<{ id: number; label: string }>;
    tipos: Array<{ value: string; label: string }>;
    estados: Array<{ value: string; label: string }>;
    permisos: { crear: boolean; aprobar: boolean; editar: boolean };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'RR.HH.', href: '/rrhh' },
    { title: 'Permisos', href: '/rrhh/permisos' },
];

export default function PermisosPage({
    permisosLista,
    filtros,
    trabajadores,
    tipos,
    estados,
    permisos,
}: PermisosProps) {
    const [q, setQ] = useState(filtros.q);
    const [estadoFiltro, setEstadoFiltro] = useState(filtros.estado);
    const [tipoFiltro, setTipoFiltro] = useState(filtros.tipo);
    const [modalAbierto, setModalAbierto] = useState(false);

    const form = useForm<{
        trabajador_id: string;
        tipo: string;
        con_goce: boolean;
        fecha_inicio: string;
        fecha_fin: string;
        motivo: string;
    }>({
        trabajador_id: trabajadores[0] ? String(trabajadores[0].id) : '',
        tipo: tipos[0]?.value ?? 'PERSONAL',
        con_goce: true,
        fecha_inicio: new Date().toISOString().slice(0, 10),
        fecha_fin: new Date().toISOString().slice(0, 10),
        motivo: '',
    });

    const aplicarFiltros = (e?: FormEvent) => {
        e?.preventDefault();
        router.get('/rrhh/permisos', { q, estado: estadoFiltro, tipo: tipoFiltro }, { preserveState: true, replace: true });
    };

    const crearPermiso = (e: FormEvent) => {
        e.preventDefault();
        form.post('/rrhh/permisos', {
            preserveScroll: true,
            onSuccess: () => {
                setModalAbierto(false);
                form.reset('motivo');
            },
        });
    };

    const resolver = (id: number, accion: 'aprobar' | 'rechazar') => {
        router.post(`/rrhh/permisos/${id}/resolver`, { accion }, { preserveScroll: true });
    };

    const cancelar = (id: number) => {
        router.post(`/rrhh/permisos/${id}/cancelar`, {}, { preserveScroll: true });
    };

    return (
        <RrhhLayout
            titulo="Permisos de Personal"
            descripcion="Permisos por salud, familiares, capacitación o personales sin solapamiento con vacaciones aprobadas."
            breadcrumbs={breadcrumbs}
            acciones={
                permisos.crear && (
                    <Button type="button" onClick={() => setModalAbierto(true)} className="min-h-9 bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                        <Plus className="mr-2 h-4 w-4" />
                        Registrar permiso
                    </Button>
                )
            }
        >
            <Card className="border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-4">
                    <form onSubmit={aplicarFiltros} className="grid gap-3 md:grid-cols-5">
                        <div className="relative md:col-span-2">
                            <Search className="pointer-events-none absolute top-3 left-3 h-4 w-4 text-zinc-500" />
                            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Trabajador, DNI o motivo..." className="min-h-10 pl-9 bg-zinc-900 border-zinc-800 text-zinc-200" />
                        </div>
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
                            <Button type="submit" variant="secondary" className="min-h-10 flex-1 border border-zinc-800 bg-zinc-900 text-zinc-200 hover:bg-zinc-800">
                                Filtrar
                            </Button>
                            <Button type="button" variant="outline" onClick={() => router.get('/rrhh/permisos')} className="min-h-10 px-3 border-zinc-800 text-zinc-400 hover:text-white" title="Limpiar">
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card className="border-zinc-800 bg-[#0f0f13]">
                <CardHeader>
                    <CardTitle className="text-base text-zinc-100">Listado de permisos</CardTitle>
                    <CardDescription>Solo la jefatura de RR.HH. o Administración (distinta al solicitante) puede aprobar permisos.</CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">
                                <tr>
                                    <th className="px-4 py-3">Trabajador</th>
                                    <th className="px-4 py-3">Tipo / Goce</th>
                                    <th className="px-4 py-3">Rango</th>
                                    <th className="px-4 py-3">Motivo</th>
                                    <th className="px-4 py-3">Estado</th>
                                    <th className="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {permisosLista.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-10 text-center text-zinc-500">
                                            No hay permisos registrados.
                                        </td>
                                    </tr>
                                ) : (
                                    permisosLista.data.map((p) => (
                                        <tr key={p.id} className="hover:bg-zinc-900/40 transition-colors">
                                            <td className="px-4 py-3">
                                                <Link href={`/rrhh/trabajadores/${p.trabajador_id}`} className="font-medium text-zinc-200 hover:text-white hover:underline transition-colors">
                                                    {p.trabajador_nombre}
                                                </Link>
                                                <div className="text-xs text-zinc-500">DNI {p.trabajador_dni}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-zinc-300">{p.tipo_etiqueta}</div>
                                                <div className="text-xs text-zinc-500">{p.con_goce ? 'Con goce de haber' : 'Sin goce de haber'}</div>
                                            </td>
                                            <td className="px-4 py-3 tabular-nums text-zinc-300">
                                                {p.fecha_inicio_fmt} al {p.fecha_fin_fmt} ({p.dias}d)
                                            </td>
                                            <td className="max-w-xs px-4 py-3 text-zinc-400">{p.motivo}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={p.estado} label={p.estado_etiqueta} />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-1.5">
                                                    {p.puede_aprobar && (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                onClick={() => resolver(p.id, 'aprobar')}
                                                                className="min-h-8 border border-emerald-900/60 bg-emerald-950/40 text-emerald-400 hover:bg-emerald-900/40 px-2.5"
                                                                title="Aprobar"
                                                            >
                                                                <Check className="h-3.5 w-3.5" />
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => resolver(p.id, 'rechazar')}
                                                                className="min-h-8 border border-rose-900/60 bg-rose-950/40 text-rose-400 hover:bg-rose-900/40 px-2.5"
                                                                title="Rechazar"
                                                            >
                                                                <Ban className="h-3.5 w-3.5" />
                                                            </Button>
                                                        </>
                                                    )}
                                                    {permisos.editar && (p.estado === 'PENDIENTE' || p.estado === 'APROBADA') && (
                                                        <Button size="sm" variant="outline" onClick={() => cancelar(p.id)} className="min-h-8 px-2.5 border-zinc-800 text-zinc-400 hover:text-white">
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
                    <Paginacion datos={permisosLista} />
                </CardContent>
            </Card>

            <Dialog open={modalAbierto} onOpenChange={setModalAbierto}>
                <DialogContent className="bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle>Registrar solicitud de permiso</DialogTitle>
                        <DialogDescription>Se verifica automáticamente que no se solape con vacaciones o permisos aprobados.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={crearPermiso} className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="perm-trab">Trabajador</Label>
                            <select
                                id="perm-trab"
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
                                <Label htmlFor="perm-tipo">Tipo</Label>
                                <select
                                    id="perm-tipo"
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
                            <div className="flex items-end pb-2">
                                <label className="flex items-center gap-2 text-[13px] font-medium text-zinc-300">
                                    <input
                                        type="checkbox"
                                        checked={form.data.con_goce}
                                        onChange={(e) => form.setData('con_goce', e.target.checked)}
                                        className="h-4 w-4 rounded border-zinc-800 bg-zinc-900 text-blue-500 focus:ring-blue-500"
                                    />
                                    Con goce de haber
                                </label>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="perm-fi">Fecha inicio</Label>
                                <Input
                                    id="perm-fi"
                                    type="date"
                                    value={form.data.fecha_inicio}
                                    onChange={(e) => form.setData('fecha_inicio', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.fecha_inicio} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="perm-ff">Fecha fin</Label>
                                <Input
                                    id="perm-ff"
                                    type="date"
                                    value={form.data.fecha_fin}
                                    onChange={(e) => form.setData('fecha_fin', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.fecha_fin} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="perm-mot">Motivo</Label>
                            <Input
                                id="perm-mot"
                                value={form.data.motivo}
                                onChange={(e) => form.setData('motivo', e.target.value)}
                                required
                            />
                            <InputError message={form.errors.motivo} />
                        </div>

                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button type="button" variant="outline" onClick={() => setModalAbierto(false)} className="min-h-9 border-zinc-800 text-zinc-400 hover:text-white">
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing} className="min-h-9 bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                                Guardar permiso
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </RrhhLayout>
    );
}

