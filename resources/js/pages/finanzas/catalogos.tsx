import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FinanzasLayout from '@/layouts/finanzas/finanzas-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import {
    Edit,
    FolderTree,
    Plus,
    Power,
    Tag,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface CentroCostoItem {
    id: number;
    codigo: string;
    nombre: string;
    area_id: number | null;
    area_nombre: string;
    descripcion: string | null;
    activo: boolean;
    movimientos_count: number;
}

interface CategoriaGastoItem {
    id: number;
    nombre: string;
    tipo: 'INGRESO' | 'EGRESO';
    tipo_etiqueta: string;
    descripcion: string | null;
    activo: boolean;
    movimientos_count: number;
}

interface CatalogoProps {
    centrosCosto: CentroCostoItem[];
    categorias: CategoriaGastoItem[];
    areas: Array<{ id: number; nombre: string }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Finanzas', href: '/finanzas' },
    { title: 'Catálogos', href: '/finanzas/catalogos' },
];

export default function CatalogosPage({
    centrosCosto,
    categorias,
    areas,
}: CatalogoProps) {
    const page = usePage<SharedData>();
    const permisos = page.props.auth?.user?.permisos?.movimientos_financieros ?? '';
    const puedeEditar = permisos.includes('C') || permisos.includes('E');

    const [pestanaActiva, setPestanaActiva] = useState<'centros' | 'categorias'>('centros');

    // Modales
    const [modalCCAbierto, setModalCCAbierto] = useState(false);
    const [ccEnEdicion, setCcEnEdicion] = useState<CentroCostoItem | null>(null);

    const [modalCatAbierto, setModalCatAbierto] = useState(false);
    const [catEnEdicion, setCatEnEdicion] = useState<CategoriaGastoItem | null>(null);

    // Formulario Centro de Costo
    const {
        data: formCC,
        setData: setFormCC,
        post: postCC,
        put: putCC,
        processing: guardandoCC,
        errors: erroresCC,
        reset: resetCC,
    } = useForm({
        codigo: '',
        nombre: '',
        area_id: '',
        descripcion: '',
    });

    // Formulario Categoría de Gasto
    const {
        data: formCat,
        setData: setFormCat,
        post: postCat,
        put: putCat,
        processing: guardandoCat,
        errors: erroresCat,
        reset: resetCat,
    } = useForm({
        nombre: '',
        tipo: 'EGRESO',
        descripcion: '',
    });

    const abrirCrearCC = () => {
        setCcEnEdicion(null);
        resetCC();
        setModalCCAbierto(true);
    };

    const abrirEditarCC = (cc: CentroCostoItem) => {
        setCcEnEdicion(cc);
        setFormCC({
            codigo: cc.codigo,
            nombre: cc.nombre,
            area_id: cc.area_id ? String(cc.area_id) : '',
            descripcion: cc.descripcion ?? '',
        });
        setModalCCAbierto(true);
    };

    const guardarCC = (e: FormEvent) => {
        e.preventDefault();
        if (ccEnEdicion) {
            putCC(`/finanzas/centros-costo/${ccEnEdicion.id}`, {
                onSuccess: () => {
                    setModalCCAbierto(false);
                    resetCC();
                },
            });
        } else {
            postCC('/finanzas/centros-costo', {
                onSuccess: () => {
                    setModalCCAbierto(false);
                    resetCC();
                },
            });
        }
    };

    const toggleEstadoCC = (cc: CentroCostoItem) => {
        const accion = cc.activo ? 'desactivar' : 'activar';
        if (confirm(`¿Estás seguro de ${accion} el centro de costo "${cc.nombre}"?`)) {
            router.post(`/finanzas/centros-costo/${cc.id}/toggle`);
        }
    };

    const abrirCrearCat = () => {
        setCatEnEdicion(null);
        resetCat();
        setModalCatAbierto(true);
    };

    const abrirEditarCat = (cat: CategoriaGastoItem) => {
        setCatEnEdicion(cat);
        setFormCat({
            nombre: cat.nombre,
            tipo: cat.tipo,
            descripcion: cat.descripcion ?? '',
        });
        setModalCatAbierto(true);
    };

    const guardarCat = (e: FormEvent) => {
        e.preventDefault();
        if (catEnEdicion) {
            putCat(`/finanzas/categorias-gasto/${catEnEdicion.id}`, {
                onSuccess: () => {
                    setModalCatAbierto(false);
                    resetCat();
                },
            });
        } else {
            postCat('/finanzas/categorias-gasto', {
                onSuccess: () => {
                    setModalCatAbierto(false);
                    resetCat();
                },
            });
        }
    };

    const toggleEstadoCat = (cat: CategoriaGastoItem) => {
        const accion = cat.activo ? 'desactivar' : 'activar';
        if (confirm(`¿Estás seguro de ${accion} la categoría "${cat.nombre}"?`)) {
            router.post(`/finanzas/categorias-gasto/${cat.id}/toggle`);
        }
    };

    return (
        <FinanzasLayout
            breadcrumbs={breadcrumbs}
            titulo="Catálogos Financieros"
            descripcion="Administración de Centros de Costo estructurados y Categorías analíticas de Gasto e Ingreso."
        >
            {/* Navegación interna entre Centros de Costo y Categorías */}
            <div className="flex gap-2">
                <button
                    onClick={() => setPestanaActiva('centros')}
                    className={`inline-flex h-9 items-center gap-2 rounded-md border px-3.5 text-[13px] font-medium transition-colors ${
                        pestanaActiva === 'centros'
                            ? 'border-zinc-700 bg-zinc-900 text-zinc-100'
                            : 'border-zinc-800 bg-[#0f0f13] text-zinc-400 hover:border-zinc-700 hover:text-zinc-200'
                    }`}
                >
                    <FolderTree size={15} /> Centros de Costo ({centrosCosto.length})
                </button>
                <button
                    onClick={() => setPestanaActiva('categorias')}
                    className={`inline-flex h-9 items-center gap-2 rounded-md border px-3.5 text-[13px] font-medium transition-colors ${
                        pestanaActiva === 'categorias'
                            ? 'border-zinc-700 bg-zinc-900 text-zinc-100'
                            : 'border-zinc-800 bg-[#0f0f13] text-zinc-400 hover:border-zinc-700 hover:text-zinc-200'
                    }`}
                >
                    <Tag size={15} /> Categorías de Gasto ({categorias.length})
                </button>
            </div>

            {/* Sección: Centros de Costo */}
            {pestanaActiva === 'centros' && (
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                        <div>
                            <CardTitle className="text-base font-semibold text-zinc-100">
                                Centros de Costo Operativos
                            </CardTitle>
                            <CardDescription className="text-[13px] text-zinc-400">
                                Estructura de imputación presupuestal para mina, planta, logística y administración
                            </CardDescription>
                        </div>
                        {puedeEditar && (
                            <Button
                                onClick={abrirCrearCC}
                                className="h-9 bg-zinc-100 text-zinc-900 font-medium px-4 py-2 rounded-md hover:bg-white text-[13px]"
                            >
                                <Plus size={16} className="mr-1.5" /> Nuevo Centro de Costo
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-[13px]">
                                <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] uppercase tracking-wider font-semibold text-zinc-400">
                                    <tr>
                                        <th className="px-4 py-3">Código</th>
                                        <th className="px-4 py-3">Nombre</th>
                                        <th className="px-4 py-3">Área de la Empresa</th>
                                        <th className="px-4 py-3">Descripción</th>
                                        <th className="px-4 py-3 text-center">Movimientos</th>
                                        <th className="px-4 py-3 text-center">Estado</th>
                                        {puedeEditar && <th className="px-4 py-3 text-right">Acciones</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {centrosCosto.map((cc) => (
                                        <tr key={cc.id} className="hover:bg-zinc-900/40 transition-colors">
                                            <td className="px-4 py-3 font-mono font-medium text-zinc-200">{cc.codigo}</td>
                                            <td className="px-4 py-3 font-medium text-zinc-100">{cc.nombre}</td>
                                            <td className="px-4 py-3 text-zinc-400">{cc.area_nombre}</td>
                                            <td className="px-4 py-3 text-[12px] text-zinc-500 max-w-xs truncate">
                                                {cc.descripcion || '—'}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <span className="rounded-md border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-semibold text-zinc-300 tabular-nums">
                                                    {cc.movimientos_count}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <StatusBadge status={cc.activo ? 'ACTIVO' : 'INACTIVO'} />
                                            </td>
                                            {puedeEditar && (
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="h-8 w-8 p-0 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800"
                                                            onClick={() => abrirEditarCC(cc)}
                                                            title="Editar centro de costo"
                                                        >
                                                            <Edit size={14} />
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className={`h-8 w-8 p-0 hover:bg-zinc-800 ${
                                                                cc.activo ? 'text-zinc-400 hover:text-amber-400' : 'text-zinc-400 hover:text-emerald-400'
                                                            }`}
                                                            onClick={() => toggleEstadoCC(cc)}
                                                            title={cc.activo ? 'Desactivar' : 'Activar'}
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
                    </CardContent>
                </Card>
            )}

            {/* Sección: Categorías de Gasto */}
            {pestanaActiva === 'categorias' && (
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                        <div>
                            <CardTitle className="text-base font-semibold text-zinc-100">
                                Categorías de Gasto e Ingreso
                            </CardTitle>
                            <CardDescription className="text-[13px] text-zinc-400">
                                Clasificación analítica de operaciones contables y flujos de caja
                            </CardDescription>
                        </div>
                        {puedeEditar && (
                            <Button
                                onClick={abrirCrearCat}
                                className="h-9 bg-zinc-100 text-zinc-900 font-medium px-4 py-2 rounded-md hover:bg-white text-[13px]"
                            >
                                <Plus size={16} className="mr-1.5" /> Nueva Categoría
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-[13px]">
                                <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] uppercase tracking-wider font-semibold text-zinc-400">
                                    <tr>
                                        <th className="px-4 py-3">Categoría</th>
                                        <th className="px-4 py-3">Tipo de Flujo</th>
                                        <th className="px-4 py-3">Descripción</th>
                                        <th className="px-4 py-3 text-center">Movimientos</th>
                                        <th className="px-4 py-3 text-center">Estado</th>
                                        {puedeEditar && <th className="px-4 py-3 text-right">Acciones</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {categorias.map((cat) => (
                                        <tr key={cat.id} className="hover:bg-zinc-900/40 transition-colors">
                                            <td className="px-4 py-3 font-medium text-zinc-100">{cat.nombre}</td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`inline-flex rounded-md border px-2 py-0.5 text-[11px] font-medium ${
                                                        cat.tipo === 'INGRESO'
                                                            ? 'border-emerald-900/50 bg-emerald-950/40 text-emerald-300'
                                                            : 'border-red-900/50 bg-red-950/40 text-red-300'
                                                    }`}
                                                >
                                                    {cat.tipo_etiqueta}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-[12px] text-zinc-500 max-w-sm truncate">
                                                {cat.descripcion || '—'}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <span className="rounded-md border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-semibold text-zinc-300 tabular-nums">
                                                    {cat.movimientos_count}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <StatusBadge status={cat.activo ? 'ACTIVO' : 'INACTIVO'} />
                                            </td>
                                            {puedeEditar && (
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="h-8 w-8 p-0 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800"
                                                            onClick={() => abrirEditarCat(cat)}
                                                            title="Editar categoría"
                                                        >
                                                            <Edit size={14} />
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className={`h-8 w-8 p-0 hover:bg-zinc-800 ${
                                                                cat.activo ? 'text-zinc-400 hover:text-amber-400' : 'text-zinc-400 hover:text-emerald-400'
                                                            }`}
                                                            onClick={() => toggleEstadoCat(cat)}
                                                            title={cat.activo ? 'Desactivar' : 'Activar'}
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
                    </CardContent>
                </Card>
            )}

            {/* Modal: Crear / Editar Centro de Costo */}
            <Dialog open={modalCCAbierto} onOpenChange={setModalCCAbierto}>
                <DialogContent className="max-w-md bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold text-zinc-100">
                            {ccEnEdicion ? 'Editar Centro de Costo' : 'Nuevo Centro de Costo'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={guardarCC} className="space-y-4 py-2">
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Código *</Label>
                            <Input
                                value={formCC.codigo}
                                onChange={(e) => setFormCC('codigo', e.target.value.toUpperCase())}
                                placeholder="Ej: CC-06"
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200 uppercase font-mono"
                                required
                            />
                            {erroresCC.codigo && <p className="mt-1 text-[12px] text-red-400">{erroresCC.codigo}</p>}
                        </div>
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Nombre del Centro de Costo *</Label>
                            <Input
                                value={formCC.nombre}
                                onChange={(e) => setFormCC('nombre', e.target.value)}
                                placeholder="Ej: Seguridad y Medio Ambiente"
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                required
                            />
                            {erroresCC.nombre && <p className="mt-1 text-[12px] text-red-400">{erroresCC.nombre}</p>}
                        </div>
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Área Asignada (Opcional)</Label>
                            <select
                                aria-label="Área Asignada"
                                value={formCC.area_id}
                                onChange={(e) => setFormCC('area_id', e.target.value)}
                                className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                <option value="">Sin área específica</option>
                                {areas.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.nombre}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Descripción de actividades</Label>
                            <textarea
                                value={formCC.descripcion}
                                onChange={(e) => setFormCC('descripcion', e.target.value)}
                                rows={2}
                                className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 p-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                placeholder="Propósito del centro de costo..."
                            />
                        </div>
                        <DialogFooter className="gap-2 sm:gap-0 pt-3 border-t border-zinc-800">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalCCAbierto(false)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={guardandoCC}
                                className="bg-zinc-100 text-zinc-900 font-medium hover:bg-white text-[13px]"
                            >
                                {guardandoCC ? 'Guardando...' : 'Guardar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal: Crear / Editar Categoría de Gasto */}
            <Dialog open={modalCatAbierto} onOpenChange={setModalCatAbierto}>
                <DialogContent className="max-w-md bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold text-zinc-100">
                            {catEnEdicion ? 'Editar Categoría' : 'Nueva Categoría de Gasto / Ingreso'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={guardarCat} className="space-y-4 py-2">
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Tipo de Movimiento *</Label>
                            <select
                                aria-label="Tipo de Movimiento"
                                value={formCat.tipo}
                                onChange={(e) => setFormCat('tipo', e.target.value)}
                                className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                <option value="EGRESO">Egreso (Gasto)</option>
                                <option value="INGRESO">Ingreso (Cobro / Venta)</option>
                            </select>
                        </div>
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Nombre de la Categoría *</Label>
                            <Input
                                value={formCat.nombre}
                                onChange={(e) => setFormCat('nombre', e.target.value)}
                                placeholder="Ej: Servicios de Terceros"
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                required
                            />
                            {erroresCat.nombre && <p className="mt-1 text-[12px] text-red-400">{erroresCat.nombre}</p>}
                        </div>
                        <div>
                            <Label className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Descripción</Label>
                            <textarea
                                value={formCat.descripcion}
                                onChange={(e) => setFormCat('descripcion', e.target.value)}
                                rows={2}
                                className="mt-1 w-full rounded-md border border-zinc-800 bg-zinc-900 p-2 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                placeholder="Detalle de conceptos incluidos..."
                            />
                        </div>
                        <DialogFooter className="gap-2 sm:gap-0 pt-3 border-t border-zinc-800">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalCatAbierto(false)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={guardandoCat}
                                className="bg-zinc-100 text-zinc-900 font-medium hover:bg-white text-[13px]"
                            >
                                {guardandoCat ? 'Guardando...' : 'Guardar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </FinanzasLayout>
    );
}
