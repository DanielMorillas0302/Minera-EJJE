import { Paginacion, type Paginado } from '@/components/paginacion';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import LogisticaLayout from '@/layouts/logistica/logistica-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { Edit, Plus, Power, Search } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface ProveedorItem {
    id: number;
    ruc: string;
    razon_social: string;
    contacto: string | null;
    telefono: string | null;
    email: string | null;
    direccion: string | null;
    condicion_pago: string;
    observaciones: string | null;
    activo: boolean;
}

interface ProveedoresProps {
    proveedores: Paginado<ProveedorItem>;
    filtros: {
        buscar: string;
        estado: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Logística', href: '/logistica' },
    { title: 'Proveedores', href: '/logistica/proveedores' },
];

export default function LogisticaProveedores({ proveedores, filtros }: ProveedoresProps) {
    const { auth } = usePage<SharedData>().props;
    const permisosMat = auth?.user?.permisos?.materiales_proveedores ?? '';
    const puedeCrear = permisosMat.includes('C');
    const puedeEditar = permisosMat.includes('E');

    const [buscar, setBuscar] = useState(filtros.buscar);
    const [estado, setEstado] = useState(filtros.estado);
    const [modalAbierto, setModalAbierto] = useState(false);
    const [editando, setEditando] = useState<ProveedorItem | null>(null);

    const form = useForm({
        ruc: '',
        razon_social: '',
        contacto: '',
        telefono: '',
        email: '',
        direccion: '',
        condicion_pago: 'CONTADO',
        observaciones: '',
    });

    const filtrar = (e: FormEvent) => {
        e.preventDefault();
        router.get('/logistica/proveedores', { buscar: buscar || undefined, estado }, { preserveState: true, replace: true });
    };

    const abrirNuevo = () => {
        setEditando(null);
        form.reset();
        form.setData('condicion_pago', 'CONTADO');
        form.clearErrors();
        setModalAbierto(true);
    };

    const abrirEditar = (p: ProveedorItem) => {
        setEditando(p);
        form.setData({
            ruc: p.ruc,
            razon_social: p.razon_social,
            contacto: p.contacto ?? '',
            telefono: p.telefono ?? '',
            email: p.email ?? '',
            direccion: p.direccion ?? '',
            condicion_pago: p.condicion_pago,
            observaciones: p.observaciones ?? '',
        });
        form.clearErrors();
        setModalAbierto(true);
    };

    const guardar = (e: FormEvent) => {
        e.preventDefault();
        if (editando) {
            form.put(`/logistica/proveedores/${editando.id}`, { onSuccess: () => setModalAbierto(false) });
        } else {
            form.post('/logistica/proveedores', { onSuccess: () => setModalAbierto(false) });
        }
    };

    return (
        <LogisticaLayout
            breadcrumbs={breadcrumbs}
            titulo="Directorio de Proveedores"
            descripcion="Proveedores homologados de insumos mineros, EPP, ferretería y transporte Trujillo–Pataz."
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <form onSubmit={filtrar} className="flex flex-wrap items-center gap-2.5">
                    <div className="relative">
                        <Search size={14} className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-zinc-500" />
                        <Input
                            value={buscar}
                            onChange={(e) => setBuscar(e.target.value)}
                            placeholder="Buscar RUC o razón social..."
                            className="h-9 w-64 pl-8 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500"
                        />
                    </div>
                    <select
                        value={estado}
                        onChange={(e) => setEstado(e.target.value)}
                        className="h-9 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-300 focus:outline-none focus:border-blue-500"
                    >
                        <option value="activos">Activos</option>
                        <option value="inactivos">Inactivos</option>
                        <option value="todos">Todos</option>
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
                        onClick={abrirNuevo}
                        className="h-9 bg-zinc-100 text-zinc-900 font-medium px-4 py-2 rounded-md hover:bg-white text-[13px]"
                    >
                        <Plus size={16} className="mr-1.5" /> Nuevo Proveedor
                    </Button>
                )}
            </div>

            <Card className="border border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-0">
                    {proveedores.data.length === 0 ? (
                        <div className="p-10 text-center text-[13px] text-zinc-500">No se encontraron proveedores registrados.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-[13px]">
                                <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">
                                    <tr>
                                        <th className="px-4 py-3">RUC</th>
                                        <th className="px-4 py-3">Razón Social</th>
                                        <th className="px-4 py-3">Contacto</th>
                                        <th className="px-4 py-3">Teléfono / Correo</th>
                                        <th className="px-4 py-3">Condición</th>
                                        <th className="px-4 py-3">Estado</th>
                                        {puedeEditar && <th className="px-4 py-3 text-right">Acciones</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {proveedores.data.map((p) => (
                                        <tr key={p.id} className="hover:bg-zinc-900/40 transition-colors">
                                            <td className="px-4 py-3 font-mono font-medium tabular-nums text-zinc-300">{p.ruc}</td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-zinc-100">{p.razon_social}</div>
                                                {p.direccion && <div className="text-[11px] text-zinc-500">{p.direccion}</div>}
                                            </td>
                                            <td className="px-4 py-3 text-zinc-300">{p.contacto ?? '—'}</td>
                                            <td className="px-4 py-3 text-[12px] text-zinc-400">
                                                <div>{p.telefono ?? '—'}</div>
                                                <div className="text-zinc-500">{p.email ?? ''}</div>
                                            </td>
                                            <td className="px-4 py-3 text-zinc-300 text-[12px]">{p.condicion_pago}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={p.activo ? 'ACTIVO' : 'INACTIVO'} />
                                            </td>
                                            {puedeEditar && (
                                                <td className="px-4 py-3 text-right">
                                                    <div className="inline-flex gap-1">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => abrirEditar(p)}
                                                            className="h-8 w-8 p-0 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800"
                                                        >
                                                            <Edit size={14} />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => router.post(`/logistica/proveedores/${p.id}/toggle`)}
                                                            className="h-8 w-8 p-0 text-zinc-400 hover:text-amber-400 hover:bg-zinc-800"
                                                        >
                                                            <Power size={14} />
                                                        </Button>
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <Paginacion datos={proveedores} />
                </CardContent>
            </Card>

            <Dialog open={modalAbierto} onOpenChange={setModalAbierto}>
                <DialogContent className="sm:max-w-lg bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold text-zinc-100">{editando ? 'Editar proveedor' : 'Nuevo proveedor'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={guardar} className="space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label htmlFor="prov-ruc" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">RUC (11 dígitos) *</Label>
                                <Input
                                    id="prov-ruc"
                                    maxLength={11}
                                    value={form.data.ruc}
                                    onChange={(e) => form.setData('ruc', e.target.value)}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                    required
                                />
                                {form.errors.ruc && <p className="mt-1 text-[12px] text-red-400">{form.errors.ruc}</p>}
                            </div>
                            <div>
                                <Label htmlFor="prov-cond" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Condición de pago *</Label>
                                <select
                                    id="prov-cond"
                                    value={form.data.condicion_pago}
                                    onChange={(e) => form.setData('condicion_pago', e.target.value)}
                                    className="mt-1 flex h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    <option value="CONTADO">CONTADO</option>
                                    <option value="CREDITO 15 DIAS">CRÉDITO 15 DÍAS</option>
                                    <option value="CREDITO 30 DIAS">CRÉDITO 30 DÍAS</option>
                                    <option value="CREDITO 45 DIAS">CRÉDITO 45 DÍAS</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="prov-razon" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Razón social *</Label>
                            <Input
                                id="prov-razon"
                                value={form.data.razon_social}
                                onChange={(e) => form.setData('razon_social', e.target.value)}
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                required
                            />
                            {form.errors.razon_social && <p className="mt-1 text-[12px] text-red-400">{form.errors.razon_social}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label htmlFor="prov-contacto" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Contacto</Label>
                                <Input
                                    id="prov-contacto"
                                    value={form.data.contacto}
                                    onChange={(e) => form.setData('contacto', e.target.value)}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                            </div>
                            <div>
                                <Label htmlFor="prov-tel" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Teléfono</Label>
                                <Input
                                    id="prov-tel"
                                    value={form.data.telefono}
                                    onChange={(e) => form.setData('telefono', e.target.value)}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label htmlFor="prov-email" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Correo</Label>
                                <Input
                                    id="prov-email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                            </div>
                            <div>
                                <Label htmlFor="prov-dir" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Dirección</Label>
                                <Input
                                    id="prov-dir"
                                    value={form.data.direccion}
                                    onChange={(e) => form.setData('direccion', e.target.value)}
                                    className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                />
                            </div>
                        </div>

                        <DialogFooter className="border-t border-zinc-800 pt-3 gap-2 sm:gap-0">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalAbierto(false)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="bg-zinc-100 text-zinc-900 font-medium hover:bg-white text-[13px]"
                            >
                                {form.processing ? 'Guardando...' : 'Guardar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </LogisticaLayout>
    );
}
