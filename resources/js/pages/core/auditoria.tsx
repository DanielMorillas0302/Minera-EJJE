import { Paginacion, type Paginado } from '@/components/paginacion';
import AdminLayout from '@/layouts/core/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { router } from '@inertiajs/react';
import { RotateCcw, Search } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface ActividadFila {
    id: number;
    evento: string | null;
    evento_etiqueta: string;
    entidad: string;
    entidad_id: number | null;
    descripcion: string;
    usuario: string;
    propiedades: Record<string, unknown>;
    fecha: string | null;
}

interface AuditoriaProps {
    registros: Paginado<ActividadFila>;
    filtros: {
        q: string;
        evento: string;
        usuario_id: string;
    };
    eventos: { value: string; label: string }[];
    usuarios: { id: number; name: string }[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Administración', href: '/administracion' },
    { title: 'Auditoría', href: '/administracion/auditoria' },
];

export default function AuditoriaPage({ registros, filtros, eventos, usuarios }: AuditoriaProps) {
    const [q, setQ] = useState(filtros.q);
    const [evento, setEvento] = useState(filtros.evento);
    const [usuarioId, setUsuarioId] = useState(filtros.usuario_id);

    const filtrar = (e: FormEvent) => {
        e.preventDefault();
        router.get('/administracion/auditoria', { q, evento, usuario_id: usuarioId }, { preserveState: true, replace: true });
    };

    const limpiar = () => {
        setQ('');
        setEvento('');
        setUsuarioId('');
        router.get('/administracion/auditoria', {}, { preserveState: true, replace: true });
    };

    const badgeEvento = (ev: string | null, etiqueta: string) => {
        switch (ev) {
            case 'crear':
                return (
                    <span className="rounded border border-emerald-900/60 bg-emerald-950/40 px-2 py-0.5 text-[11px] font-medium text-emerald-400">
                        {etiqueta}
                    </span>
                );
            case 'actualizar':
                return (
                    <span className="rounded border border-blue-900/60 bg-blue-950/40 px-2 py-0.5 text-[11px] font-medium text-blue-400">
                        {etiqueta}
                    </span>
                );
            case 'eliminar':
            case 'desactivar':
                return (
                    <span className="rounded border border-red-900/60 bg-red-950/40 px-2 py-0.5 text-[11px] font-medium text-red-400">
                        {etiqueta}
                    </span>
                );
            default:
                return (
                    <span className="rounded border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-medium text-zinc-300">
                        {etiqueta}
                    </span>
                );
        }
    };

    return (
        <AdminLayout
            titulo="Registro de Auditoría"
            descripcion="Historial inmutable de acciones realizadas sobre usuarios, operaciones y registros del sistema."
            breadcrumbs={breadcrumbs}
        >
            {/* Barra de Filtros */}
            <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-4">
                <form onSubmit={filtrar} className="grid gap-3 md:grid-cols-4">
                    <div className="relative md:col-span-2">
                        <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500" />
                        <input
                            type="text"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Buscar por descripción o entidad..."
                            className="w-full rounded-md border border-zinc-800 bg-zinc-900 py-1.5 pl-8 pr-3 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500 focus:outline-none transition-colors"
                        />
                    </div>

                    <select
                        value={evento}
                        onChange={(e) => setEvento(e.target.value)}
                        className="rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                    >
                        <option value="">Todos los eventos</option>
                        {eventos.map((ev) => (
                            <option key={ev.value} value={ev.value}>
                                {ev.label}
                            </option>
                        ))}
                    </select>

                    <div className="flex gap-2">
                        <select
                            value={usuarioId}
                            onChange={(e) => setUsuarioId(e.target.value)}
                            className="flex-1 rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                        >
                            <option value="">Todos los usuarios</option>
                            {usuarios.map((u) => (
                                <option key={u.id} value={String(u.id)}>
                                    {u.name}
                                </option>
                            ))}
                        </select>
                        <button
                            type="submit"
                            className="rounded-md border border-zinc-800 bg-zinc-900 px-3.5 py-1.5 text-[13px] font-medium text-zinc-300 transition-colors hover:bg-zinc-800 hover:text-zinc-100"
                        >
                            Filtrar
                        </button>
                        <button
                            type="button"
                            onClick={limpiar}
                            className="rounded-md border border-zinc-800 bg-zinc-900 px-2.5 py-1.5 text-[13px] text-zinc-500 transition-colors hover:text-zinc-300"
                            title="Limpiar filtros"
                        >
                            <RotateCcw size={14} />
                        </button>
                    </div>
                </form>

                <div className="mt-3 flex items-center justify-between border-t border-zinc-800/60 pt-3">
                    <span className="text-[12px] text-zinc-500">
                        Eventos registrados:{' '}
                        <strong className="text-zinc-300 tabular-nums">{registros.total}</strong> acciones
                    </span>
                </div>
            </div>

            {/* Tabla de Auditoría */}
            <div className="overflow-hidden rounded-lg border border-zinc-800 bg-[#0f0f13]">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-[13px]">
                        <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
                            <tr>
                                <th className="px-4 py-3">Fecha y hora</th>
                                <th className="px-4 py-3">Usuario responsable</th>
                                <th className="px-4 py-3">Evento</th>
                                <th className="px-4 py-3">Entidad</th>
                                <th className="px-4 py-3">Detalle del cambio</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-zinc-800/60">
                            {registros.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-12 text-center text-zinc-500">
                                        No se encontraron eventos registrados con los criterios seleccionados.
                                    </td>
                                </tr>
                            ) : (
                                registros.data.map((r) => (
                                    <tr key={r.id} className="align-top transition-colors hover:bg-zinc-900/40">
                                        <td className="px-4 py-3 whitespace-nowrap text-zinc-400 tabular-nums font-mono text-[12px]">
                                            {r.fecha}
                                        </td>
                                        <td className="px-4 py-3 font-medium text-zinc-200">{r.usuario}</td>
                                        <td className="px-4 py-3">{badgeEvento(r.evento, r.evento_etiqueta)}</td>
                                        <td className="px-4 py-3 text-zinc-300">
                                            {r.entidad}
                                            {r.entidad_id ? (
                                                <span className="ml-1 text-zinc-500 font-mono text-[11px]">#{r.entidad_id}</span>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-3 text-[12px] text-zinc-400">
                                            <div className="leading-relaxed">{r.descripcion}</div>
                                            {Object.keys(r.propiedades).length > 0 && (
                                                <pre className="mt-2 max-w-lg overflow-x-auto rounded border border-zinc-800/80 bg-zinc-950 p-2 text-[11px] font-mono text-zinc-300">
                                                    {JSON.stringify(r.propiedades, null, 2)}
                                                </pre>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
                <Paginacion datos={registros} />
            </div>
        </AdminLayout>
    );
}
