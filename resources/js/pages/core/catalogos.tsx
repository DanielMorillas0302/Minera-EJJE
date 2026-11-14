import InputError from '@/components/input-error';
import { ModalConfirmacion } from '@/components/modal-confirmacion';
import { Paginacion, type Paginado } from '@/components/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AdminLayout from '@/layouts/core/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, RotateCcw, Search, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface CatalogoRegistro {
    id: number;
    nombre: string;
    activo: boolean;
    creado_en: string | null;
    tipo?: string;
    tipo_etiqueta?: string;
    ubicacion?: string | null;
    sede_id?: number;
    sede_nombre?: string | null;
    abreviatura?: string;
}

interface CatalogosProps {
    tipo: 'sedes' | 'areas' | 'cargos' | 'unidades-medida';
    titulo: string;
    singular: string;
    registros: Paginado<CatalogoRegistro>;
    filtros: {
        q: string;
        estado: string;
        filtro: string;
    };
    sedesDisponibles: { id: number; nombre: string }[];
    tiposSede: { value: string; label: string }[];
}

export default function CatalogosPage({
    tipo,
    titulo,
    singular,
    registros,
    filtros,
    sedesDisponibles,
    tiposSede,
}: CatalogosProps) {
    const [q, setQ] = useState(filtros.q);
    const [estado, setEstado] = useState(filtros.estado);
    const [filtroExtra, setFiltroExtra] = useState(filtros.filtro);

    const [modalAbierto, setModalAbierto] = useState(false);
    const [editando, setEditando] = useState<CatalogoRegistro | null>(null);
    const [porDesactivar, setPorDesactivar] = useState<CatalogoRegistro | null>(null);
    const [procesandoBaja, setProcesandoBaja] = useState(false);

    const form = useForm({
        nombre: '',
        tipo: tiposSede[0]?.value ?? 'ADMINISTRATIVA',
        ubicacion: '',
        sede_id: sedesDisponibles[0] ? String(sedesDisponibles[0].id) : '',
        abreviatura: '',
    });

    const urlBase = `/administracion/catalogos/${tipo}`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Inicio', href: '/dashboard' },
        { title: 'Administración', href: '/administracion' },
        { title: titulo, href: urlBase },
    ];

    const aplicarFiltros = (e?: FormEvent) => {
        e?.preventDefault();
        router.get(urlBase, { q, estado, filtro: filtroExtra }, { preserveState: true, replace: true });
    };

    const abrirCrear = () => {
        setEditando(null);
        form.clearErrors();
        form.setData({
            nombre: '',
            tipo: tiposSede[0]?.value ?? 'ADMINISTRATIVA',
            ubicacion: '',
            sede_id: sedesDisponibles[0] ? String(sedesDisponibles[0].id) : '',
            abreviatura: '',
        });
        setModalAbierto(true);
    };

    const abrirEditar = (item: CatalogoRegistro) => {
        setEditando(item);
        form.clearErrors();
        form.setData({
            nombre: item.nombre,
            tipo: item.tipo ?? tiposSede[0]?.value ?? 'ADMINISTRATIVA',
            ubicacion: item.ubicacion ?? '',
            sede_id: item.sede_id ? String(item.sede_id) : sedesDisponibles[0] ? String(sedesDisponibles[0].id) : '',
            abreviatura: item.abreviatura ?? '',
        });
        setModalAbierto(true);
    };

    const guardar = (e: FormEvent) => {
        e.preventDefault();
        if (editando) {
            form.put(`${urlBase}/${editando.id}`, {
                preserveScroll: true,
                onSuccess: () => setModalAbierto(false),
            });
        } else {
            form.post(urlBase, {
                preserveScroll: true,
                onSuccess: () => setModalAbierto(false),
            });
        }
    };

    const reactivar = (item: CatalogoRegistro) => {
        router.post(`${urlBase}/${item.id}/restaurar`, {}, { preserveScroll: true });
    };

    const confirmarBaja = () => {
        if (!porDesactivar) return;
        setProcesandoBaja(true);
        router.delete(`${urlBase}/${porDesactivar.id}`, {
            preserveScroll: true,
            onFinish: () => {
                setProcesandoBaja(false);
                setPorDesactivar(null);
            },
        });
    };

    return (
        <AdminLayout
            titulo={`Catálogo de ${titulo}`}
            descripcion={`Administra el registro maestro de ${titulo.toLowerCase()} con borrado lógico y auditoría.`}
            breadcrumbs={breadcrumbs}
        >
            {/* Barra de Filtros */}
            <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-4">
                <form onSubmit={aplicarFiltros} className="grid gap-3 md:grid-cols-4">
                    <div className="relative md:col-span-2">
                        <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500" />
                        <input
                            type="text"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder={`Buscar ${titulo.toLowerCase()}...`}
                            className="w-full rounded-md border border-zinc-800 bg-zinc-900 py-1.5 pl-8 pr-3 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500 focus:outline-none transition-colors"
                        />
                    </div>

                    {tipo === 'sedes' && (
                        <select
                            value={filtroExtra}
                            onChange={(e) => setFiltroExtra(e.target.value)}
                            className="rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                        >
                            <option value="">Todos los tipos</option>
                            {tiposSede.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                    )}

                    {tipo === 'areas' && (
                        <select
                            value={filtroExtra}
                            onChange={(e) => setFiltroExtra(e.target.value)}
                            className="rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                        >
                            <option value="">Todas las sedes</option>
                            {sedesDisponibles.map((s) => (
                                <option key={s.id} value={String(s.id)}>
                                    {s.nombre}
                                </option>
                            ))}
                        </select>
                    )}

                    <div className="flex gap-2">
                        <select
                            value={estado}
                            onChange={(e) => setEstado(e.target.value)}
                            className="flex-1 rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                        >
                            <option value="activos">Activos</option>
                            <option value="inactivos">Inactivos</option>
                            <option value="todos">Todos</option>
                        </select>
                        <button
                            type="submit"
                            className="rounded-md border border-zinc-800 bg-zinc-900 px-3.5 py-1.5 text-[13px] font-medium text-zinc-300 transition-colors hover:bg-zinc-800 hover:text-zinc-100"
                        >
                            Filtrar
                        </button>
                    </div>
                </form>

                <div className="mt-3 flex items-center justify-between border-t border-zinc-800/60 pt-3">
                    <span className="text-[12px] text-zinc-500">
                        Total:{' '}
                        <strong className="text-zinc-300 tabular-nums">{registros.total}</strong> registros
                    </span>
                    <button
                        type="button"
                        onClick={abrirCrear}
                        className="inline-flex items-center gap-1.5 rounded-md bg-zinc-100 px-3 py-1.5 text-[13px] font-medium text-zinc-900 transition-colors hover:bg-white"
                    >
                        <Plus size={14} />
                        <span>Nueva {singular.toLowerCase()}</span>
                    </button>
                </div>
            </div>

            {/* Tabla del Catálogo */}
            <div className="overflow-hidden rounded-lg border border-zinc-800 bg-[#0f0f13]">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-[13px]">
                        <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
                            <tr>
                                <th className="px-4 py-3">Nombre</th>
                                {tipo === 'sedes' && <th className="px-4 py-3">Tipo</th>}
                                {tipo === 'sedes' && <th className="px-4 py-3">Ubicación</th>}
                                {tipo === 'areas' && <th className="px-4 py-3">Sede asignada</th>}
                                {tipo === 'unidades-medida' && <th className="px-4 py-3">Abreviatura</th>}
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-zinc-800/60">
                            {registros.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-12 text-center text-zinc-500">
                                        No hay registros registrados en este catálogo.
                                    </td>
                                </tr>
                            ) : (
                                registros.data.map((item) => (
                                    <tr key={item.id} className="transition-colors hover:bg-zinc-900/40">
                                        <td className="px-4 py-3 font-medium text-zinc-200">{item.nombre}</td>
                                        {tipo === 'sedes' && (
                                            <td className="px-4 py-3">
                                                <span className="rounded border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-medium text-zinc-300">
                                                    {item.tipo_etiqueta}
                                                </span>
                                            </td>
                                        )}
                                        {tipo === 'sedes' && <td className="px-4 py-3 text-zinc-400">{item.ubicacion ?? '—'}</td>}
                                        {tipo === 'areas' && <td className="px-4 py-3 text-zinc-300">{item.sede_nombre ?? '—'}</td>}
                                        {tipo === 'unidades-medida' && (
                                            <td className="px-4 py-3">
                                                <span className="rounded border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-mono text-zinc-300">
                                                    {item.abreviatura}
                                                </span>
                                            </td>
                                        )}
                                        <td className="px-4 py-3">
                                            {item.activo ? (
                                                <Badge variant="success">Activo</Badge>
                                            ) : (
                                                <Badge variant="destructive">Inactivo</Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="inline-flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => abrirEditar(item)}
                                                    className="rounded p-1.5 text-zinc-400 transition-colors hover:bg-zinc-800 hover:text-zinc-200"
                                                    title="Editar"
                                                >
                                                    <Pencil size={14} />
                                                </button>
                                                {item.activo ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => setPorDesactivar(item)}
                                                        className="rounded p-1.5 text-red-400 transition-colors hover:bg-red-950/40 hover:text-red-300"
                                                        title="Desactivar"
                                                    >
                                                        <Trash2 size={14} />
                                                    </button>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        onClick={() => reactivar(item)}
                                                        className="rounded p-1.5 text-emerald-400 transition-colors hover:bg-emerald-950/40 hover:text-emerald-300"
                                                        title="Reactivar"
                                                    >
                                                        <RotateCcw size={14} />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
                <Paginacion datos={registros} />
            </div>

            {/* Modal Crear / Editar */}
            <Dialog open={modalAbierto} onOpenChange={setModalAbierto}>
                <DialogContent className="border-zinc-800 bg-[#0f0f13] text-zinc-100 sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-zinc-100 text-base">
                            {editando ? `Editar ${singular.toLowerCase()}` : `Nueva ${singular.toLowerCase()}`}
                        </DialogTitle>
                        <DialogDescription className="text-zinc-400 text-[13px]">
                            Ingresa los parámetros requeridos para el catálogo institucional.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={guardar} className="space-y-4 pt-2">
                        <div className="grid gap-1.5">
                            <label className="text-xs font-medium text-zinc-300">Nombre</label>
                            <input
                                type="text"
                                value={form.data.nombre}
                                onChange={(e) => form.setData('nombre', e.target.value)}
                                required
                                className="w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500 focus:outline-none transition-colors"
                            />
                            <InputError message={form.errors.nombre} />
                        </div>

                        {tipo === 'sedes' && (
                            <>
                                <div className="grid gap-1.5">
                                    <label className="text-xs font-medium text-zinc-300">Tipo de sede</label>
                                    <select
                                        value={form.data.tipo}
                                        onChange={(e) => form.setData('tipo', e.target.value)}
                                        className="rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                                    >
                                        {tiposSede.map((t) => (
                                            <option key={t.value} value={t.value}>
                                                {t.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={form.errors.tipo} />
                                </div>
                                <div className="grid gap-1.5">
                                    <label className="text-xs font-medium text-zinc-300">Ubicación física / ciudad</label>
                                    <input
                                        type="text"
                                        value={form.data.ubicacion}
                                        onChange={(e) => form.setData('ubicacion', e.target.value)}
                                        className="w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500 focus:outline-none transition-colors"
                                    />
                                    <InputError message={form.errors.ubicacion} />
                                </div>
                            </>
                        )}

                        {tipo === 'areas' && (
                            <div className="grid gap-1.5">
                                <label className="text-xs font-medium text-zinc-300">Sede asignada</label>
                                <select
                                    value={form.data.sede_id}
                                    onChange={(e) => form.setData('sede_id', e.target.value)}
                                    className="rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                                    required
                                >
                                    {sedesDisponibles.map((s) => (
                                        <option key={s.id} value={String(s.id)}>
                                            {s.nombre}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.sede_id} />
                            </div>
                        )}

                        {tipo === 'unidades-medida' && (
                            <div className="grid gap-1.5">
                                <label className="text-xs font-medium text-zinc-300">Abreviatura estándar</label>
                                <input
                                    type="text"
                                    value={form.data.abreviatura}
                                    onChange={(e) => form.setData('abreviatura', e.target.value)}
                                    placeholder="Ej. kg, und, L, m, gal"
                                    required
                                    className="w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500 focus:outline-none transition-colors"
                                />
                                <InputError message={form.errors.abreviatura} />
                            </div>
                        )}

                        <DialogFooter className="gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalAbierto(false)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium"
                            >
                                {form.processing ? 'Guardando...' : 'Guardar registro'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ModalConfirmacion
                abierto={Boolean(porDesactivar)}
                onCerrar={() => setPorDesactivar(null)}
                onConfirmar={confirmarBaja}
                titulo={`Desactivar ${singular.toLowerCase()}`}
                descripcion={`¿Deseas desactivar "${porDesactivar?.nombre}"? Se aplicará borrado lógico conservando las referencias históricas del sistema.`}
                textoConfirmar="Desactivar"
                procesando={procesandoBaja}
            />
        </AdminLayout>
    );
}
