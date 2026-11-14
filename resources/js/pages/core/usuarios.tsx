import InputError from '@/components/input-error';
import { ModalConfirmacion } from '@/components/modal-confirmacion';
import { Paginacion, type Paginado } from '@/components/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AdminLayout from '@/layouts/core/admin-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { KeyRound, Pencil, Plus, RotateCcw, Search, UserX } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface UsuarioFila {
    id: number;
    name: string;
    email: string;
    rol: string | null;
    rol_etiqueta: string;
    sede_id: number | null;
    sede_nombre: string | null;
    telefono: string | null;
    activo: boolean;
    eliminado_en: string | null;
    creado_en: string | null;
}

interface Opcion {
    value: string;
    label: string;
}

interface SedeOpcion {
    id: number;
    nombre: string;
}

interface UsuariosProps {
    usuarios: Paginado<UsuarioFila>;
    filtros: {
        q: string;
        rol: string;
        sede_id: string;
        estado: string;
        orden: string;
        dir: string;
    };
    roles: Opcion[];
    sedes: SedeOpcion[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Administración', href: '/administracion' },
    { title: 'Usuarios', href: '/administracion/usuarios' },
];

export default function UsuariosPage({ usuarios, filtros, roles, sedes }: UsuariosProps) {
    const { auth } = usePage<SharedData>().props;
    const [q, setQ] = useState(filtros.q);
    const [rolFiltro, setRolFiltro] = useState(filtros.rol);
    const [sedeFiltro, setSedeFiltro] = useState(filtros.sede_id);
    const [estadoFiltro, setEstadoFiltro] = useState(filtros.estado);

    const [modalAbierto, setModalAbierto] = useState(false);
    const [usuarioEditando, setUsuarioEditando] = useState<UsuarioFila | null>(null);
    const [usuarioPassword, setUsuarioPassword] = useState<UsuarioFila | null>(null);
    const [usuarioBaja, setUsuarioBaja] = useState<UsuarioFila | null>(null);
    const [procesandoBaja, setProcesandoBaja] = useState(false);

    const form = useForm<{
        name: string;
        email: string;
        password: string;
        password_confirmation: string;
        rol: string;
        sede_id: string;
        telefono: string;
        activo: boolean;
    }>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        rol: roles[0]?.value ?? 'GERENTE',
        sede_id: '',
        telefono: '',
        activo: true,
    });

    const formPassword = useForm({
        password: '',
        password_confirmation: '',
    });

    const aplicarFiltros = (e?: FormEvent) => {
        e?.preventDefault();
        router.get(
            '/administracion/usuarios',
            { q, rol: rolFiltro, sede_id: sedeFiltro, estado: estadoFiltro },
            { preserveState: true, replace: true },
        );
    };

    const limpiarFiltros = () => {
        setQ('');
        setRolFiltro('');
        setSedeFiltro('');
        setEstadoFiltro('activos');
        router.get('/administracion/usuarios', {}, { preserveState: true, replace: true });
    };

    const abrirCrear = () => {
        setUsuarioEditando(null);
        form.clearErrors();
        form.setData({
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
            rol: roles[0]?.value ?? 'GERENTE',
            sede_id: '',
            telefono: '',
            activo: true,
        });
        setModalAbierto(true);
    };

    const abrirEditar = (u: UsuarioFila) => {
        setUsuarioEditando(u);
        form.clearErrors();
        form.setData({
            name: u.name,
            email: u.email,
            password: '',
            password_confirmation: '',
            rol: u.rol ?? roles[0]?.value ?? 'GERENTE',
            sede_id: u.sede_id ? String(u.sede_id) : '',
            telefono: u.telefono ?? '',
            activo: u.activo,
        });
        setModalAbierto(true);
    };

    const guardarUsuario = (e: FormEvent) => {
        e.preventDefault();
        if (usuarioEditando) {
            form.put(`/administracion/usuarios/${usuarioEditando.id}`, {
                preserveScroll: true,
                onSuccess: () => setModalAbierto(false),
            });
        } else {
            form.post('/administracion/usuarios', {
                preserveScroll: true,
                onSuccess: () => setModalAbierto(false),
            });
        }
    };

    const abrirResetPassword = (u: UsuarioFila) => {
        formPassword.reset();
        formPassword.clearErrors();
        setUsuarioPassword(u);
    };

    const guardarPassword = (e: FormEvent) => {
        e.preventDefault();
        if (!usuarioPassword) return;
        formPassword.put(`/administracion/usuarios/${usuarioPassword.id}/password`, {
            preserveScroll: true,
            onSuccess: () => setUsuarioPassword(null),
        });
    };

    const confirmarBaja = () => {
        if (!usuarioBaja) return;
        setProcesandoBaja(true);
        router.delete(`/administracion/usuarios/${usuarioBaja.id}`, {
            preserveScroll: true,
            onFinish: () => {
                setProcesandoBaja(false);
                setUsuarioBaja(null);
            },
        });
    };

    const reactivarUsuario = (u: UsuarioFila) => {
        router.post(`/administracion/usuarios/${u.id}/restaurar`, {}, { preserveScroll: true });
    };

    return (
        <AdminLayout
            titulo="Gestión de Usuarios y Roles"
            descripcion="Administra cuentas de acceso, roles jerárquicos, sedes de operación y contraseñas."
            breadcrumbs={breadcrumbs}
        >
            {/* Barra de Filtros */}
            <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-4">
                <form onSubmit={aplicarFiltros} className="grid gap-3 md:grid-cols-5">
                    <div className="relative md:col-span-2">
                        <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500" />
                        <input
                            type="text"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Buscar por nombre, correo o teléfono..."
                            className="w-full rounded-md border border-zinc-800 bg-zinc-900 py-1.5 pl-8 pr-3 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500 focus:outline-none transition-colors"
                        />
                    </div>

                    <select
                        value={rolFiltro}
                        onChange={(e) => setRolFiltro(e.target.value)}
                        className="rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                    >
                        <option value="">Todos los roles</option>
                        {roles.map((r) => (
                            <option key={r.value} value={r.value}>
                                {r.label}
                            </option>
                        ))}
                    </select>

                    <select
                        value={sedeFiltro}
                        onChange={(e) => setSedeFiltro(e.target.value)}
                        className="rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                    >
                        <option value="">Todas las sedes</option>
                        {sedes.map((s) => (
                            <option key={s.id} value={String(s.id)}>
                                {s.nombre}
                            </option>
                        ))}
                    </select>

                    <div className="flex gap-2">
                        <select
                            value={estadoFiltro}
                            onChange={(e) => setEstadoFiltro(e.target.value)}
                            className="flex-1 rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                        >
                            <option value="activos">Activos</option>
                            <option value="inactivos">Inactivos</option>
                            <option value="todos">Todos</option>
                        </select>
                        <button
                            type="submit"
                            className="rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] font-medium text-zinc-300 transition-colors hover:bg-zinc-800 hover:text-zinc-100"
                        >
                            Filtrar
                        </button>
                        <button
                            type="button"
                            onClick={limpiarFiltros}
                            className="rounded-md border border-zinc-800 bg-zinc-900 px-2.5 py-1.5 text-[13px] text-zinc-500 transition-colors hover:text-zinc-300"
                            title="Limpiar filtros"
                        >
                            <RotateCcw size={14} />
                        </button>
                    </div>
                </form>

                <div className="mt-3 flex items-center justify-between border-t border-zinc-800/60 pt-3">
                    <span className="text-[12px] text-zinc-500">
                        Total encontrados:{' '}
                        <strong className="text-zinc-300 tabular-nums">{usuarios.total}</strong> usuarios
                    </span>
                    <button
                        type="button"
                        onClick={abrirCrear}
                        className="inline-flex items-center gap-1.5 rounded-md bg-zinc-100 px-3 py-1.5 text-[13px] font-medium text-zinc-900 transition-colors hover:bg-white"
                    >
                        <Plus size={14} />
                        <span>Nuevo usuario</span>
                    </button>
                </div>
            </div>

            {/* Tabla de Usuarios */}
            <div className="overflow-hidden rounded-lg border border-zinc-800 bg-[#0f0f13]">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-[13px]">
                        <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
                            <tr>
                                <th className="px-4 py-3">Usuario</th>
                                <th className="px-4 py-3">Rol asignado</th>
                                <th className="px-4 py-3">Sede</th>
                                <th className="px-4 py-3">Teléfono</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-zinc-800/60">
                            {usuarios.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-12 text-center text-zinc-500">
                                        No se encontraron usuarios con los criterios de búsqueda aplicados.
                                    </td>
                                </tr>
                            ) : (
                                usuarios.data.map((u) => (
                                    <tr key={u.id} className="transition-colors hover:bg-zinc-900/40">
                                        <td className="px-4 py-3">
                                            <div className="font-medium text-zinc-200">{u.name}</div>
                                            <div className="text-[12px] text-zinc-500">{u.email}</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="rounded border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-medium text-zinc-300">
                                                {u.rol_etiqueta}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-zinc-300">{u.sede_nombre ?? 'Sin sede'}</td>
                                        <td className="px-4 py-3 text-zinc-400 tabular-nums">{u.telefono ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            {u.activo ? (
                                                <Badge variant="success">Activo</Badge>
                                            ) : (
                                                <Badge variant="destructive">Inactivo</Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="inline-flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => abrirEditar(u)}
                                                    className="rounded p-1.5 text-zinc-400 transition-colors hover:bg-zinc-800 hover:text-zinc-200"
                                                    title="Editar usuario"
                                                >
                                                    <Pencil size={14} />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => abrirResetPassword(u)}
                                                    className="rounded p-1.5 text-zinc-400 transition-colors hover:bg-zinc-800 hover:text-zinc-200"
                                                    title="Restablecer contraseña"
                                                >
                                                    <KeyRound size={14} />
                                                </button>
                                                {u.activo ? (
                                                    u.id !== auth.user.id && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setUsuarioBaja(u)}
                                                            className="rounded p-1.5 text-red-400 transition-colors hover:bg-red-950/40 hover:text-red-300"
                                                            title="Desactivar usuario"
                                                        >
                                                            <UserX size={14} />
                                                        </button>
                                                    )
                                                ) : (
                                                    <button
                                                        type="button"
                                                        onClick={() => reactivarUsuario(u)}
                                                        className="rounded p-1.5 text-emerald-400 transition-colors hover:bg-emerald-950/40 hover:text-emerald-300"
                                                        title="Reactivar usuario"
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
                <Paginacion datos={usuarios} />
            </div>

            {/* Modal Crear / Editar */}
            <Dialog open={modalAbierto} onOpenChange={setModalAbierto}>
                <DialogContent className="border-zinc-800 bg-[#0f0f13] text-zinc-100 sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="text-zinc-100 text-base">
                            {usuarioEditando ? 'Editar usuario' : 'Nuevo usuario'}
                        </DialogTitle>
                        <DialogDescription className="text-zinc-400 text-[13px]">
                            Completa los datos del usuario, perfil de acceso y asignación de sede.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={guardarUsuario} className="space-y-4 pt-2">
                        <div className="grid gap-1.5">
                            <label className="text-xs font-medium text-zinc-300">Nombre completo</label>
                            <input
                                type="text"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                required
                                className="w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500 focus:outline-none transition-colors"
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-1.5">
                            <label className="text-xs font-medium text-zinc-300">Correo electrónico</label>
                            <input
                                type="email"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                                required
                                className="w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500 focus:outline-none transition-colors"
                            />
                            <InputError message={form.errors.email} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <label className="text-xs font-medium text-zinc-300">Rol del sistema</label>
                                <select
                                    value={form.data.rol}
                                    onChange={(e) => form.setData('rol', e.target.value)}
                                    className="rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                                >
                                    {roles.map((r) => (
                                        <option key={r.value} value={r.value}>
                                            {r.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.rol} />
                            </div>

                            <div className="grid gap-1.5">
                                <label className="text-xs font-medium text-zinc-300">Sede asignada</label>
                                <select
                                    value={form.data.sede_id}
                                    onChange={(e) => form.setData('sede_id', e.target.value)}
                                    className="rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                                >
                                    <option value="">Sin sede asignada</option>
                                    {sedes.map((s) => (
                                        <option key={s.id} value={String(s.id)}>
                                            {s.nombre}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.sede_id} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <label className="text-xs font-medium text-zinc-300">Teléfono (opcional)</label>
                                <input
                                    type="text"
                                    value={form.data.telefono}
                                    onChange={(e) => form.setData('telefono', e.target.value)}
                                    className="w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500 focus:outline-none transition-colors"
                                />
                                <InputError message={form.errors.telefono} />
                            </div>

                            <div className="flex items-end pb-2">
                                <label className="flex items-center gap-2 text-xs font-medium text-zinc-300">
                                    <input
                                        type="checkbox"
                                        checked={form.data.activo}
                                        onChange={(e) => form.setData('activo', e.target.checked)}
                                        className="h-4 w-4 rounded border-zinc-800 bg-zinc-900 text-blue-600 focus:ring-0"
                                    />
                                    Usuario activo
                                </label>
                            </div>
                        </div>

                        {!usuarioEditando && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <label className="text-xs font-medium text-zinc-300">Contraseña</label>
                                    <input
                                        type="password"
                                        value={form.data.password}
                                        onChange={(e) => form.setData('password', e.target.value)}
                                        required
                                        className="w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                                    />
                                    <InputError message={form.errors.password} />
                                </div>
                                <div className="grid gap-1.5">
                                    <label className="text-xs font-medium text-zinc-300">Confirmar contraseña</label>
                                    <input
                                        type="password"
                                        value={form.data.password_confirmation}
                                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                        required
                                        className="w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                                    />
                                </div>
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
                                {form.processing ? 'Guardando...' : 'Guardar usuario'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal Restablecer Contraseña */}
            <Dialog open={Boolean(usuarioPassword)} onOpenChange={(open) => !open && setUsuarioPassword(null)}>
                <DialogContent className="border-zinc-800 bg-[#0f0f13] text-zinc-100 sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-zinc-100 text-base">Restablecer contraseña</DialogTitle>
                        <DialogDescription className="text-zinc-400 text-[13px]">
                            Asigna una nueva clave para <span className="font-medium text-zinc-200">{usuarioPassword?.name}</span>.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={guardarPassword} className="space-y-4 pt-2">
                        <div className="grid gap-1.5">
                            <label className="text-xs font-medium text-zinc-300">Nueva contraseña</label>
                            <input
                                type="password"
                                value={formPassword.data.password}
                                onChange={(e) => formPassword.setData('password', e.target.value)}
                                required
                                className="w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                            />
                            <InputError message={formPassword.errors.password} />
                        </div>
                        <div className="grid gap-1.5">
                            <label className="text-xs font-medium text-zinc-300">Confirmar contraseña</label>
                            <input
                                type="password"
                                value={formPassword.data.password_confirmation}
                                onChange={(e) => formPassword.setData('password_confirmation', e.target.value)}
                                required
                                className="w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none transition-colors"
                            />
                        </div>
                        <DialogFooter className="gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setUsuarioPassword(null)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={formPassword.processing}
                                className="bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium"
                            >
                                {formPassword.processing ? 'Guardando...' : 'Guardar contraseña'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ModalConfirmacion
                abierto={Boolean(usuarioBaja)}
                onCerrar={() => setUsuarioBaja(null)}
                onConfirmar={confirmarBaja}
                titulo="Desactivar usuario"
                descripcion={`¿Deseas desactivar el acceso de ${usuarioBaja?.name}? Se conservará su historial de auditoría y registros.`}
                textoConfirmar="Desactivar"
                procesando={procesandoBaja}
            />
        </AdminLayout>
    );
}
