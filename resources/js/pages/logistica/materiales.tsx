import { Paginacion, type Paginado } from '@/components/paginacion';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import LogisticaLayout from '@/layouts/logistica/logistica-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { Edit, Plus, Power, Search, Warehouse } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface CategoriaItem {
    id: number;
    nombre: string;
    descripcion: string | null;
    activo: boolean;
}

interface UnidadItem {
    id: number;
    nombre: string;
    abreviatura: string;
}

interface SedeItem {
    id: number;
    nombre: string;
}

interface AlmacenItem {
    id: number;
    codigo: string;
    nombre: string;
    sede_id: number;
    es_principal: boolean;
    sede?: SedeItem | null;
}

interface MaterialItem {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    categoria_id: number;
    unidad_medida_id: number;
    stock_minimo: string;
    costo_referencial: string;
    stock_total: string | null;
    activo: boolean;
    categoria?: CategoriaItem | null;
    unidad_medida?: UnidadItem | null;
}

interface MaterialesProps {
    materiales: Paginado<MaterialItem>;
    categorias: CategoriaItem[];
    unidadesMedida: UnidadItem[];
    almacenes: AlmacenItem[];
    sedes: SedeItem[];
    filtros: {
        buscar: string;
        categoria_id: number | null;
        estado: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Logística', href: '/logistica' },
    { title: 'Materiales y Catálogos', href: '/logistica/materiales' },
];

export default function LogisticaMateriales({
    materiales,
    categorias,
    unidadesMedida,
    almacenes,
    sedes,
    filtros,
}: MaterialesProps) {
    const { auth } = usePage<SharedData>().props;
    const permisosMat = auth?.user?.permisos?.materiales_proveedores ?? '';
    const puedeCrear = permisosMat.includes('C');
    const puedeEditar = permisosMat.includes('E');

    const [buscar, setBuscar] = useState(filtros.buscar);
    const [categoriaFiltro, setCategoriaFiltro] = useState<string>(filtros.categoria_id ? String(filtros.categoria_id) : '');
    const [estadoFiltro, setEstadoFiltro] = useState(filtros.estado);

    const [modalMaterialAbierto, setModalMaterialAbierto] = useState(false);
    const [materialEditando, setMaterialEditando] = useState<MaterialItem | null>(null);
    const [modalCategoriaAbierto, setModalCategoriaAbierto] = useState(false);
    const [modalAlmacenAbierto, setModalAlmacenAbierto] = useState(false);

    const formMaterial = useForm({
        codigo: '',
        nombre: '',
        descripcion: '',
        categoria_id: categorias[0]?.id ? String(categorias[0].id) : '',
        unidad_medida_id: unidadesMedida[0]?.id ? String(unidadesMedida[0].id) : '',
        stock_minimo: '0',
        costo_referencial: '0',
    });

    const formCategoria = useForm({
        nombre: '',
        descripcion: '',
    });

    const formAlmacen = useForm<{
        codigo: string;
        nombre: string;
        sede_id: string;
        es_principal: boolean;
    }>({
        codigo: '',
        nombre: '',
        sede_id: sedes[0]?.id ? String(sedes[0].id) : '',
        es_principal: false,
    });

    const aplicarFiltros = (e?: FormEvent) => {
        e?.preventDefault();
        router.get(
            '/logistica/materiales',
            {
                buscar: buscar || undefined,
                categoria_id: categoriaFiltro || undefined,
                estado: estadoFiltro,
            },
            { preserveState: true, replace: true },
        );
    };

    const abrirNuevoMaterial = () => {
        setMaterialEditando(null);
        formMaterial.setData({
            codigo: '',
            nombre: '',
            descripcion: '',
            categoria_id: categorias[0]?.id ? String(categorias[0].id) : '',
            unidad_medida_id: unidadesMedida[0]?.id ? String(unidadesMedida[0].id) : '',
            stock_minimo: '0',
            costo_referencial: '0',
        });
        formMaterial.clearErrors();
        setModalMaterialAbierto(true);
    };

    const abrirEditarMaterial = (mat: MaterialItem) => {
        setMaterialEditando(mat);
        formMaterial.setData({
            codigo: mat.codigo,
            nombre: mat.nombre,
            descripcion: mat.descripcion ?? '',
            categoria_id: String(mat.categoria_id),
            unidad_medida_id: String(mat.unidad_medida_id),
            stock_minimo: String(mat.stock_minimo),
            costo_referencial: String(mat.costo_referencial),
        });
        formMaterial.clearErrors();
        setModalMaterialAbierto(true);
    };

    const guardarMaterial = (e: FormEvent) => {
        e.preventDefault();
        if (materialEditando) {
            formMaterial.put(`/logistica/materiales/${materialEditando.id}`, {
                onSuccess: () => setModalMaterialAbierto(false),
            });
        } else {
            formMaterial.post('/logistica/materiales', {
                onSuccess: () => setModalMaterialAbierto(false),
            });
        }
    };

    const guardarCategoria = (e: FormEvent) => {
        e.preventDefault();
        formCategoria.post('/logistica/categorias', {
            onSuccess: () => {
                formCategoria.reset();
                setModalCategoriaAbierto(false);
            },
        });
    };

    const guardarAlmacen = (e: FormEvent) => {
        e.preventDefault();
        formAlmacen.post('/logistica/almacenes', {
            onSuccess: () => {
                formAlmacen.reset();
                setModalAlmacenAbierto(false);
            },
        });
    };

    return (
        <LogisticaLayout
            breadcrumbs={breadcrumbs}
            titulo="Catálogo de Materiales, Categorías y Almacenes"
            descripcion="Administre ítems de consumo minero, stock mínimo de reposición, categorías y sedes de almacenamiento."
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <form onSubmit={aplicarFiltros} className="flex flex-wrap items-center gap-2">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-3 left-3 h-4 w-4 text-zinc-500" />
                        <Input
                            value={buscar}
                            onChange={(e) => setBuscar(e.target.value)}
                            placeholder="Buscar código o nombre..."
                            className="min-h-10 w-64 pl-9 bg-zinc-900 border-zinc-800 text-zinc-200"
                        />
                    </div>
                    <select
                        value={categoriaFiltro}
                        onChange={(e) => setCategoriaFiltro(e.target.value)}
                        className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                    >
                        <option value="">Todas las categorías</option>
                        {categorias.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.nombre}
                            </option>
                        ))}
                    </select>
                    <select
                        value={estadoFiltro}
                        onChange={(e) => setEstadoFiltro(e.target.value)}
                        className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                    >
                        <option value="activos">Activos</option>
                        <option value="inactivos">Inactivos</option>
                        <option value="todos">Todos</option>
                    </select>
                    <Button type="submit" variant="secondary" className="min-h-10 border border-zinc-800 bg-zinc-900 text-zinc-200 hover:bg-zinc-800">
                        Filtrar
                    </Button>
                </form>

                {puedeCrear && (
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" onClick={() => setModalCategoriaAbierto(true)} className="min-h-9 border-zinc-800 text-zinc-300">
                            <Plus className="mr-1.5 h-4 w-4" /> Categoría
                        </Button>
                        <Button type="button" variant="outline" onClick={() => setModalAlmacenAbierto(true)} className="min-h-9 border-zinc-800 text-zinc-300">
                            <Warehouse className="mr-1.5 h-4 w-4" /> Almacén
                        </Button>
                        <Button type="button" onClick={abrirNuevoMaterial} className="min-h-9 bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                            <Plus className="mr-1.5 h-4 w-4" /> Nuevo Material
                        </Button>
                    </div>
                )}
            </div>

            <Card className="border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-0">
                    {materiales.data.length === 0 ? (
                        <div className="p-10 text-center text-sm text-zinc-500">
                            No se encontraron materiales con los filtros aplicados.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
                                    <tr>
                                        <th className="px-4 py-3">Código</th>
                                        <th className="px-4 py-3">Material</th>
                                        <th className="px-4 py-3">Categoría</th>
                                        <th className="px-4 py-3">Unidad</th>
                                        <th className="px-4 py-3 text-right">Stock Mínimo</th>
                                        <th className="px-4 py-3 text-right">Stock Total</th>
                                        <th className="px-4 py-3 text-right">Costo Ref. (S/)</th>
                                        <th className="px-4 py-3">Estado</th>
                                        {puedeEditar && <th className="px-4 py-3 text-right">Acciones</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {materiales.data.map((mat) => (
                                        <tr key={mat.id} className="hover:bg-zinc-900/40 transition-colors">
                                            <td className="px-4 py-3 font-semibold text-zinc-200 font-mono">{mat.codigo}</td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-zinc-200">{mat.nombre}</div>
                                                {mat.descripcion && <div className="text-xs text-zinc-500">{mat.descripcion}</div>}
                                            </td>
                                            <td className="px-4 py-3 text-zinc-400">{mat.categoria?.nombre ?? '—'}</td>
                                            <td className="px-4 py-3 text-zinc-400">{mat.unidad_medida?.abreviatura ?? '—'}</td>
                                            <td className="px-4 py-3 text-right tabular-nums text-zinc-400">
                                                {Number(mat.stock_minimo).toFixed(2)}
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold tabular-nums text-zinc-100">
                                                {Number(mat.stock_total ?? 0).toFixed(2)}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums text-zinc-400">
                                                S/ {Number(mat.costo_referencial).toFixed(2)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={mat.activo ? 'ACTIVO' : 'INACTIVO'} label={mat.activo ? 'Activo' : 'Inactivo'} />
                                            </td>
                                            {puedeEditar && (
                                                <td className="px-4 py-3 text-right">
                                                    <div className="inline-flex items-center gap-1">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => abrirEditarMaterial(mat)}
                                                            aria-label={`Editar ${mat.nombre}`}
                                                            className="text-zinc-400 hover:text-white"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => router.post(`/logistica/materiales/${mat.id}/toggle`)}
                                                            aria-label={`Cambiar estado de ${mat.nombre}`}
                                                            className={mat.activo ? 'text-zinc-500 hover:text-rose-400' : 'text-zinc-500 hover:text-emerald-400'}
                                                        >
                                                            <Power className="h-4 w-4" />
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
                    <Paginacion datos={materiales} />
                </CardContent>
            </Card>

            <div className="grid gap-4 md:grid-cols-2">
                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardHeader>
                        <CardTitle className="text-base text-zinc-100">Almacenes registrados</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {almacenes.map((alm) => (
                            <div key={alm.id} className="flex items-center justify-between rounded-md border border-zinc-800 bg-zinc-900/50 px-3 py-2 text-sm">
                                <div>
                                    <span className="font-semibold text-zinc-200 font-mono">{alm.codigo}</span> · <span className="text-zinc-300">{alm.nombre}</span>
                                    <div className="text-xs text-zinc-500">Sede: {alm.sede?.nombre ?? '—'}</div>
                                </div>
                                {alm.es_principal && <Badge variant="secondary">Principal</Badge>}
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardHeader>
                        <CardTitle className="text-base text-zinc-100">Categorías de material ({categorias.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        {categorias.map((cat) => (
                            <Badge key={cat.id} variant={cat.activo ? 'secondary' : 'outline'}>
                                {cat.nombre}
                            </Badge>
                        ))}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={modalMaterialAbierto} onOpenChange={setModalMaterialAbierto}>
                <DialogContent className="sm:max-w-lg bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle>{materialEditando ? 'Editar material' : 'Registrar nuevo material'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={guardarMaterial} className="space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label htmlFor="mat-codigo">Código *</Label>
                                <Input
                                    id="mat-codigo"
                                    value={formMaterial.data.codigo}
                                    onChange={(e) => formMaterial.setData('codigo', e.target.value)}
                                    required
                                />
                                {formMaterial.errors.codigo && <p className="mt-1 text-xs text-red-600">{formMaterial.errors.codigo}</p>}
                            </div>
                            <div>
                                <Label htmlFor="mat-unidad">Unidad de medida *</Label>
                                <select
                                    id="mat-unidad"
                                    value={formMaterial.data.unidad_medida_id}
                                    onChange={(e) => formMaterial.setData('unidad_medida_id', e.target.value)}
                                    className="mt-1 flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                    required
                                >
                                    {unidadesMedida.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.nombre} ({u.abreviatura})
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="mat-nombre">Nombre *</Label>
                            <Input
                                id="mat-nombre"
                                value={formMaterial.data.nombre}
                                onChange={(e) => formMaterial.setData('nombre', e.target.value)}
                                required
                            />
                            {formMaterial.errors.nombre && <p className="mt-1 text-xs text-red-600">{formMaterial.errors.nombre}</p>}
                        </div>

                        <div>
                            <Label htmlFor="mat-categoria">Categoría *</Label>
                            <select
                                id="mat-categoria"
                                value={formMaterial.data.categoria_id}
                                onChange={(e) => formMaterial.setData('categoria_id', e.target.value)}
                                className="mt-1 flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                required
                            >
                                {categorias.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.nombre}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label htmlFor="mat-min">Stock mínimo *</Label>
                                <Input
                                    id="mat-min"
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    value={formMaterial.data.stock_minimo}
                                    onChange={(e) => formMaterial.setData('stock_minimo', e.target.value)}
                                    required
                                />
                            </div>
                            <div>
                                <Label htmlFor="mat-costo">Costo referencial (S/) *</Label>
                                <Input
                                    id="mat-costo"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={formMaterial.data.costo_referencial}
                                    onChange={(e) => formMaterial.setData('costo_referencial', e.target.value)}
                                    required
                                />
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="mat-desc">Descripción</Label>
                            <Input
                                id="mat-desc"
                                value={formMaterial.data.descripcion}
                                onChange={(e) => formMaterial.setData('descripcion', e.target.value)}
                            />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setModalMaterialAbierto(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={formMaterial.processing} className="bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                                {formMaterial.processing ? 'Guardando...' : 'Guardar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={modalCategoriaAbierto} onOpenChange={setModalCategoriaAbierto}>
                <DialogContent className="bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle>Nueva categoría de material</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={guardarCategoria} className="space-y-4">
                        <div>
                            <Label htmlFor="cat-nombre">Nombre *</Label>
                            <Input
                                id="cat-nombre"
                                value={formCategoria.data.nombre}
                                onChange={(e) => formCategoria.setData('nombre', e.target.value)}
                                required
                            />
                            {formCategoria.errors.nombre && <p className="mt-1 text-xs text-red-600">{formCategoria.errors.nombre}</p>}
                        </div>
                        <div>
                            <Label htmlFor="cat-desc">Descripción</Label>
                            <Input
                                id="cat-desc"
                                value={formCategoria.data.descripcion}
                                onChange={(e) => formCategoria.setData('descripcion', e.target.value)}
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setModalCategoriaAbierto(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={formCategoria.processing} className="bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                                Crear categoría
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={modalAlmacenAbierto} onOpenChange={setModalAlmacenAbierto}>
                <DialogContent className="bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle>Nuevo almacén</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={guardarAlmacen} className="space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label htmlFor="alm-codigo">Código *</Label>
                                <Input
                                    id="alm-codigo"
                                    value={formAlmacen.data.codigo}
                                    onChange={(e) => formAlmacen.setData('codigo', e.target.value)}
                                    required
                                />
                                {formAlmacen.errors.codigo && <p className="mt-1 text-xs text-red-600">{formAlmacen.errors.codigo}</p>}
                            </div>
                            <div>
                                <Label htmlFor="alm-sede">Sede *</Label>
                                <select
                                    id="alm-sede"
                                    value={formAlmacen.data.sede_id}
                                    onChange={(e) => formAlmacen.setData('sede_id', e.target.value)}
                                    className="mt-1 flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                    required
                                >
                                    {sedes.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div>
                            <Label htmlFor="alm-nombre">Nombre del almacén *</Label>
                            <Input
                                id="alm-nombre"
                                value={formAlmacen.data.nombre}
                                onChange={(e) => formAlmacen.setData('nombre', e.target.value)}
                                required
                            />
                        </div>
                        <label className="flex items-center gap-2 text-[13px] text-zinc-300">
                            <input
                                type="checkbox"
                                checked={formAlmacen.data.es_principal}
                                onChange={(e) => formAlmacen.setData('es_principal', e.target.checked)}
                            />
                            Marcar como almacén principal
                        </label>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setModalAlmacenAbierto(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={formAlmacen.processing} className="bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                                Guardar almacén
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </LogisticaLayout>
    );
}
