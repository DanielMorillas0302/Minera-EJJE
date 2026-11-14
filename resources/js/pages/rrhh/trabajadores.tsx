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
import { CheckCircle2, Eye, Pencil, Plus, RotateCcw, Search, Trash2, X, XCircle } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface Opcion {
    value: string;
    label: string;
}

interface SedeOpcion {
    id: number;
    nombre: string;
    tipo: string;
}

interface AreaOpcion {
    id: number;
    nombre: string;
    sede_id: number;
}

interface CargoOpcion {
    id: number;
    nombre: string;
}

interface TrabajadorFila {
    id: number;
    dni: string;
    nombres: string;
    apellidos: string;
    nombre_completo: string;
    fecha_nacimiento: string | null;
    sexo: string | null;
    sexo_etiqueta: string | null;
    telefono: string | null;
    email: string | null;
    direccion: string | null;
    contacto_emergencia_nombre: string | null;
    contacto_emergencia_telefono: string | null;
    cargo_id: number;
    cargo_nombre: string | null;
    area_id: number;
    area_nombre: string | null;
    sede_base_id: number;
    sede_nombre: string | null;
    fecha_ingreso: string | null;
    fecha_ingreso_fmt: string | null;
    fecha_cese: string | null;
    estado: string;
    estado_etiqueta: string;
    ubicacion_actual: string;
    ubicacion_etiqueta: string;
    esquema_rotacion: string | null;
    tiene_foto: boolean;
    activo: boolean;
    aptitud_visible: boolean;
    apto_viaje: boolean | null;
    aptitud_motivo: string | null;
}

interface TrabajadoresProps {
    trabajadores: Paginado<TrabajadorFila>;
    filtros: {
        q: string;
        sede_id: string;
        area_id: string;
        estado: string;
        ubicacion: string;
        aptitud: string;
        ver: string;
    };
    catalogos: {
        sedes: SedeOpcion[];
        areas: AreaOpcion[];
        cargos: CargoOpcion[];
        sexos: Opcion[];
        estados: Opcion[];
        ubicaciones: Opcion[];
    };
    puedeGestionar: boolean;
    puedeVerAptitud: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'RR.HH.', href: '/rrhh' },
    { title: 'Trabajadores', href: '/rrhh/trabajadores' },
];

export default function TrabajadoresPage({
    trabajadores,
    filtros,
    catalogos,
    puedeGestionar,
    puedeVerAptitud,
}: TrabajadoresProps) {
    const [q, setQ] = useState(filtros.q);
    const [sedeFiltro, setSedeFiltro] = useState(filtros.sede_id);
    const [areaFiltro, setAreaFiltro] = useState(filtros.area_id);
    const [estadoFiltro, setEstadoFiltro] = useState(filtros.estado);
    const [ubicacionFiltro, setUbicacionFiltro] = useState(filtros.ubicacion);
    const [aptitudFiltro, setAptitudFiltro] = useState(filtros.aptitud);
    const [verFiltro, setVerFiltro] = useState(filtros.ver);

    const [modalAbierto, setModalAbierto] = useState(false);
    const [editando, setEditando] = useState<TrabajadorFila | null>(null);
    const [paraBaja, setParaBaja] = useState<TrabajadorFila | null>(null);
    const [procesandoBaja, setProcesandoBaja] = useState(false);

    const form = useForm<{
        dni: string;
        nombres: string;
        apellidos: string;
        fecha_nacimiento: string;
        sexo: string;
        telefono: string;
        email: string;
        direccion: string;
        contacto_emergencia_nombre: string;
        contacto_emergencia_telefono: string;
        cargo_id: string;
        area_id: string;
        sede_base_id: string;
        fecha_ingreso: string;
        fecha_cese: string;
        estado: string;
        ubicacion_actual: string;
        esquema_rotacion: string;
        foto: File | null;
        _method?: string;
    }>({
        dni: '',
        nombres: '',
        apellidos: '',
        fecha_nacimiento: '1990-01-01',
        sexo: catalogos.sexos[0]?.value ?? 'M',
        telefono: '',
        email: '',
        direccion: '',
        contacto_emergencia_nombre: '',
        contacto_emergencia_telefono: '',
        cargo_id: catalogos.cargos[0] ? String(catalogos.cargos[0].id) : '',
        area_id: catalogos.areas[0] ? String(catalogos.areas[0].id) : '',
        sede_base_id: catalogos.sedes[0] ? String(catalogos.sedes[0].id) : '',
        fecha_ingreso: new Date().toISOString().slice(0, 10),
        fecha_cese: '',
        estado: 'ACTIVO',
        ubicacion_actual: 'TRUJILLO',
        esquema_rotacion: '21x7',
        foto: null,
    });

    const aplicarFiltros = (e?: FormEvent) => {
        e?.preventDefault();
        router.get(
            '/rrhh/trabajadores',
            {
                q,
                sede_id: sedeFiltro,
                area_id: areaFiltro,
                estado: estadoFiltro,
                ubicacion: ubicacionFiltro,
                aptitud: aptitudFiltro,
                ver: verFiltro,
            },
            { preserveState: true, replace: true },
        );
    };

    const limpiarFiltros = () => {
        setQ('');
        setSedeFiltro('');
        setAreaFiltro('');
        setEstadoFiltro('');
        setUbicacionFiltro('');
        setAptitudFiltro('');
        setVerFiltro('activos');
        router.get('/rrhh/trabajadores', {}, { preserveState: true, replace: true });
    };

    const abrirCrear = () => {
        setEditando(null);
        form.clearErrors();
        form.setData({
            dni: '',
            nombres: '',
            apellidos: '',
            fecha_nacimiento: '1990-01-01',
            sexo: catalogos.sexos[0]?.value ?? 'M',
            telefono: '',
            email: '',
            direccion: '',
            contacto_emergencia_nombre: '',
            contacto_emergencia_telefono: '',
            cargo_id: catalogos.cargos[0] ? String(catalogos.cargos[0].id) : '',
            area_id: catalogos.areas[0] ? String(catalogos.areas[0].id) : '',
            sede_base_id: catalogos.sedes[0] ? String(catalogos.sedes[0].id) : '',
            fecha_ingreso: new Date().toISOString().slice(0, 10),
            fecha_cese: '',
            estado: 'ACTIVO',
            ubicacion_actual: 'TRUJILLO',
            esquema_rotacion: '21x7',
            foto: null,
        });
        setModalAbierto(true);
    };

    const abrirEditar = (t: TrabajadorFila) => {
        setEditando(t);
        form.clearErrors();
        form.setData({
            dni: t.dni,
            nombres: t.nombres,
            apellidos: t.apellidos,
            fecha_nacimiento: t.fecha_nacimiento ?? '1990-01-01',
            sexo: t.sexo ?? 'M',
            telefono: t.telefono ?? '',
            email: t.email ?? '',
            direccion: t.direccion ?? '',
            contacto_emergencia_nombre: t.contacto_emergencia_nombre ?? '',
            contacto_emergencia_telefono: t.contacto_emergencia_telefono ?? '',
            cargo_id: String(t.cargo_id),
            area_id: String(t.area_id),
            sede_base_id: String(t.sede_base_id),
            fecha_ingreso: t.fecha_ingreso ?? new Date().toISOString().slice(0, 10),
            fecha_cese: t.fecha_cese ?? '',
            estado: t.estado,
            ubicacion_actual: t.ubicacion_actual,
            esquema_rotacion: t.esquema_rotacion ?? '',
            foto: null,
            _method: 'put',
        });
        setModalAbierto(true);
    };

    const guardar = (e: FormEvent) => {
        e.preventDefault();
        if (editando) {
            form.post(`/rrhh/trabajadores/${editando.id}`, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setModalAbierto(false),
            });
        } else {
            form.post('/rrhh/trabajadores', {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setModalAbierto(false),
            });
        }
    };

    const confirmarBaja = () => {
        if (!paraBaja) return;
        setProcesandoBaja(true);
        router.delete(`/rrhh/trabajadores/${paraBaja.id}`, {
            preserveScroll: true,
            onFinish: () => {
                setProcesandoBaja(false);
                setParaBaja(null);
            },
        });
    };

    const restaurar = (id: number) => {
        router.post(`/rrhh/trabajadores/${id}/restaurar`, {}, { preserveScroll: true });
    };

    return (
        <RrhhLayout
            titulo="Trabajadores"
            descripcion="Padrón de personal minero y administrativo, ubicación actual Trujillo/Pataz y verificación de aptitud de viaje."
            breadcrumbs={breadcrumbs}
            acciones={
                puedeGestionar && (
                    <Button onClick={abrirCrear} className="min-h-9 bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                        <Plus className="mr-2 h-4 w-4" />
                        Nuevo trabajador
                    </Button>
                )
            }
        >
            <Card className="border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-4">
                    <form onSubmit={aplicarFiltros} className="grid gap-3 md:grid-cols-4 lg:grid-cols-8">
                        <div className="relative md:col-span-2">
                            <Search className="pointer-events-none absolute top-3 left-3 h-4 w-4 text-zinc-500" />
                            <Input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="DNI, nombres o apellidos..."
                                className="min-h-10 pl-9 bg-zinc-900 border-zinc-800 text-zinc-200"
                            />
                        </div>

                        <select
                            value={sedeFiltro}
                            onChange={(e) => setSedeFiltro(e.target.value)}
                            className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                        >
                            <option value="">Todas las sedes</option>
                            {catalogos.sedes.map((s) => (
                                <option key={s.id} value={String(s.id)}>
                                    {s.nombre}
                                </option>
                            ))}
                        </select>

                        <select
                            value={areaFiltro}
                            onChange={(e) => setAreaFiltro(e.target.value)}
                            className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                        >
                            <option value="">Todas las áreas</option>
                            {catalogos.areas.map((a) => (
                                <option key={a.id} value={String(a.id)}>
                                    {a.nombre}
                                </option>
                            ))}
                        </select>

                        <select
                            value={ubicacionFiltro}
                            onChange={(e) => setUbicacionFiltro(e.target.value)}
                            className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                        >
                            <option value="">Toda ubicación</option>
                            {catalogos.ubicaciones.map((u) => (
                                <option key={u.value} value={u.value}>
                                    {u.label}
                                </option>
                            ))}
                        </select>

                        <select
                            value={estadoFiltro}
                            onChange={(e) => setEstadoFiltro(e.target.value)}
                            className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                        >
                            <option value="">Todo estado</option>
                            {catalogos.estados.map((est) => (
                                <option key={est.value} value={est.value}>
                                    {est.label}
                                </option>
                            ))}
                        </select>

                        {puedeVerAptitud ? (
                            <select
                                value={aptitudFiltro}
                                onChange={(e) => setAptitudFiltro(e.target.value)}
                                className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                <option value="">Toda aptitud</option>
                                <option value="apto">Apto para Pataz</option>
                                <option value="no_apto">No apto</option>
                            </select>
                        ) : (
                            <select
                                value={verFiltro}
                                onChange={(e) => setVerFiltro(e.target.value)}
                                className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                <option value="activos">Activos</option>
                                <option value="todos">Incl. desactivados</option>
                            </select>
                        )}

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
                                    <th className="px-4 py-3">DNI</th>
                                    <th className="px-4 py-3">Trabajador</th>
                                    <th className="px-4 py-3">Cargo / Área</th>
                                    <th className="px-4 py-3">Sede base</th>
                                    <th className="px-4 py-3">Ubicación</th>
                                    <th className="px-4 py-3">Estado</th>
                                    {puedeVerAptitud && <th className="px-4 py-3">Aptitud Pataz</th>}
                                    <th className="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {trabajadores.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={puedeVerAptitud ? 8 : 7} className="px-4 py-10 text-center text-zinc-500">
                                            No se encontraron trabajadores con los filtros aplicados.
                                        </td>
                                    </tr>
                                ) : (
                                    trabajadores.data.map((t) => (
                                        <tr key={t.id} className="hover:bg-zinc-900/40 transition-colors">
                                            <td className="px-4 py-3 font-mono text-xs font-medium text-zinc-400">{t.dni}</td>
                                            <td className="px-4 py-3">
                                                <Link href={`/rrhh/trabajadores/${t.id}`} className="font-medium text-zinc-200 hover:text-white hover:underline transition-colors">
                                                    {t.nombre_completo}
                                                </Link>
                                                <div className="text-xs text-zinc-500">
                                                    {t.esquema_rotacion ? `Rotación: ${t.esquema_rotacion}` : 'Horario regular'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-zinc-300">
                                                <div>{t.cargo_nombre ?? '—'}</div>
                                                <div className="text-xs text-zinc-500">{t.area_nombre ?? '—'}</div>
                                            </td>
                                            <td className="px-4 py-3 text-zinc-400">{t.sede_nombre ?? '—'}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={t.ubicacion_actual} label={t.ubicacion_etiqueta} />
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={t.estado} label={t.estado_etiqueta} />
                                            </td>
                                            {puedeVerAptitud && (
                                                <td className="px-4 py-3">
                                                    <StatusBadge
                                                        variant={t.apto_viaje ? 'success' : 'danger'}
                                                        label={t.apto_viaje ? 'Apto para Pataz' : 'No apto'}
                                                    />
                                                </td>
                                            )}
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-1.5">
                                                    <Link href={`/rrhh/trabajadores/${t.id}`}>
                                                        <Button variant="outline" size="sm" className="min-h-8 px-2.5 border-zinc-800 text-zinc-400 hover:text-zinc-200" title="Ver ficha completa">
                                                            <Eye className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </Link>
                                                    {puedeGestionar && (
                                                        <>
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => abrirEditar(t)}
                                                                className="min-h-8 px-2.5 border-zinc-800 text-zinc-400 hover:text-zinc-200"
                                                                title="Editar trabajador"
                                                            >
                                                                <Pencil className="h-3.5 w-3.5" />
                                                            </Button>
                                                            {t.activo ? (
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() => setParaBaja(t)}
                                                                    className="min-h-8 px-2.5 border-zinc-800 text-rose-400 hover:text-rose-300 hover:border-rose-900/60"
                                                                    title="Desactivar trabajador"
                                                                >
                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                </Button>
                                                            ) : (
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() => restaurar(t.id)}
                                                                    className="min-h-8 px-2.5 border-zinc-800 text-emerald-400 hover:text-emerald-300 hover:border-emerald-900/60"
                                                                    title="Reactivar trabajador"
                                                                >
                                                                    <RotateCcw className="h-3.5 w-3.5" />
                                                                </Button>
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Paginacion datos={trabajadores} />
                </CardContent>
            </Card>

            <Dialog open={modalAbierto} onOpenChange={setModalAbierto}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar trabajador' : 'Registrar trabajador'}</DialogTitle>
                        <DialogDescription>Ingresa los datos laborales y de contacto del trabajador.</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={guardar} className="grid gap-4">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-dni">DNI (8 dígitos)</Label>
                                <Input
                                    id="t-dni"
                                    maxLength={8}
                                    value={form.data.dni}
                                    onChange={(e) => form.setData('dni', e.target.value.replace(/\D/g, ''))}
                                    required
                                />
                                <InputError message={form.errors.dni} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-nombres">Nombres</Label>
                                <Input
                                    id="t-nombres"
                                    value={form.data.nombres}
                                    onChange={(e) => form.setData('nombres', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.nombres} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-apellidos">Apellidos</Label>
                                <Input
                                    id="t-apellidos"
                                    value={form.data.apellidos}
                                    onChange={(e) => form.setData('apellidos', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.apellidos} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-fnac">Fecha nacimiento</Label>
                                <Input
                                    id="t-fnac"
                                    type="date"
                                    value={form.data.fecha_nacimiento}
                                    onChange={(e) => form.setData('fecha_nacimiento', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.fecha_nacimiento} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-sexo">Sexo</Label>
                                <select
                                    id="t-sexo"
                                    value={form.data.sexo}
                                    onChange={(e) => form.setData('sexo', e.target.value)}
                                    className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {catalogos.sexos.map((s) => (
                                        <option key={s.value} value={s.value}>
                                            {s.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.sexo} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-tel">Teléfono</Label>
                                <Input
                                    id="t-tel"
                                    value={form.data.telefono}
                                    onChange={(e) => form.setData('telefono', e.target.value)}
                                />
                                <InputError message={form.errors.telefono} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-sede">Sede base</Label>
                                <select
                                    id="t-sede"
                                    value={form.data.sede_base_id}
                                    onChange={(e) => form.setData('sede_base_id', e.target.value)}
                                    className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {catalogos.sedes.map((s) => (
                                        <option key={s.id} value={String(s.id)}>
                                            {s.nombre}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.sede_base_id} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-area">Área</Label>
                                <select
                                    id="t-area"
                                    value={form.data.area_id}
                                    onChange={(e) => form.setData('area_id', e.target.value)}
                                    className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {catalogos.areas.map((a) => (
                                        <option key={a.id} value={String(a.id)}>
                                            {a.nombre}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.area_id} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-cargo">Cargo</Label>
                                <select
                                    id="t-cargo"
                                    value={form.data.cargo_id}
                                    onChange={(e) => form.setData('cargo_id', e.target.value)}
                                    className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {catalogos.cargos.map((c) => (
                                        <option key={c.id} value={String(c.id)}>
                                            {c.nombre}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.cargo_id} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-fing">Fecha ingreso</Label>
                                <Input
                                    id="t-fing"
                                    type="date"
                                    value={form.data.fecha_ingreso}
                                    onChange={(e) => form.setData('fecha_ingreso', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.fecha_ingreso} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-estado">Estado laboral</Label>
                                <select
                                    id="t-estado"
                                    value={form.data.estado}
                                    onChange={(e) => form.setData('estado', e.target.value)}
                                    className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {catalogos.estados.map((est) => (
                                        <option key={est.value} value={est.value}>
                                            {est.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.estado} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-ubicacion">Ubicación actual</Label>
                                <select
                                    id="t-ubicacion"
                                    value={form.data.ubicacion_actual}
                                    onChange={(e) => form.setData('ubicacion_actual', e.target.value)}
                                    className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {catalogos.ubicaciones.map((u) => (
                                        <option key={u.value} value={u.value}>
                                            {u.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.ubicacion_actual} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-rotacion">Esquema rotación (ej. 21x7)</Label>
                                <Input
                                    id="t-rotacion"
                                    value={form.data.esquema_rotacion}
                                    onChange={(e) => form.setData('esquema_rotacion', e.target.value)}
                                    placeholder="21x7 / L-V"
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-email">Correo electrónico</Label>
                                <Input
                                    id="t-email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                />
                                <InputError message={form.errors.email} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-fcese">Fecha cese (opcional)</Label>
                                <Input
                                    id="t-fcese"
                                    type="date"
                                    value={form.data.fecha_cese}
                                    onChange={(e) => form.setData('fecha_cese', e.target.value)}
                                />
                                <InputError message={form.errors.fecha_cese} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-em-nom">Contacto de emergencia</Label>
                                <Input
                                    id="t-em-nom"
                                    value={form.data.contacto_emergencia_nombre}
                                    onChange={(e) => form.setData('contacto_emergencia_nombre', e.target.value)}
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-em-tel">Teléfono de emergencia</Label>
                                <Input
                                    id="t-em-tel"
                                    value={form.data.contacto_emergencia_telefono}
                                    onChange={(e) => form.setData('contacto_emergencia_telefono', e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-dir">Dirección</Label>
                                <Input
                                    id="t-dir"
                                    value={form.data.direccion}
                                    onChange={(e) => form.setData('direccion', e.target.value)}
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="t-foto">Foto (JPG/PNG opcional)</Label>
                                <Input
                                    id="t-foto"
                                    type="file"
                                    accept="image/png,image/jpeg"
                                    onChange={(e) => form.setData('foto', e.target.files?.[0] ?? null)}
                                />
                                <InputError message={form.errors.foto} />
                            </div>
                        </div>

                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button type="button" variant="outline" onClick={() => setModalAbierto(false)} className="min-h-10">
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing} className="min-h-9 bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                                {form.processing ? 'Guardando...' : editando ? 'Guardar cambios' : 'Registrar trabajador'}
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
                titulo="Desactivar trabajador"
                descripcion={`¿Deseas desactivar lógicamente a ${paraBaja?.nombre_completo ?? ''}? Sus registros históricos e historial médico se conservarán.`}
                textoConfirmar="Desactivar"
            />
        </RrhhLayout>
    );
}

