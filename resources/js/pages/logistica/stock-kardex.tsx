import { Paginacion, type Paginado } from '@/components/paginacion';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import LogisticaLayout from '@/layouts/logistica/logistica-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowDownCircle, ArrowUpCircle, FileDown, MinusCircle, PlusCircle, Search } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface StockItem {
    id: number;
    cantidad: string;
    almacen: { id: number; codigo: string; nombre: string; sede?: { nombre: string } | null };
    material: {
        id: number;
        codigo: string;
        nombre: string;
        stock_minimo: string;
        categoria?: { nombre: string } | null;
        unidad_medida?: { abreviatura: string } | null;
    };
}

interface MovimientoItem {
    id: number;
    tipo: string;
    cantidad: string;
    saldo_resultante: string;
    fecha_hora: string;
    observacion: string | null;
    almacen: { id: number; nombre: string };
    material: { id: number; codigo: string; nombre: string; unidad_medida?: { abreviatura: string } | null };
    usuario?: { name: string } | null;
}

interface AlmacenOption {
    id: number;
    codigo: string;
    nombre: string;
}

interface CategoriaOption {
    id: number;
    nombre: string;
}

interface MaterialOption {
    id: number;
    codigo: string;
    nombre: string;
    unidad_medida?: { abreviatura: string } | null;
}

interface StockKardexProps {
    pestanaActiva: 'stock' | 'kardex';
    stocks: Paginado<StockItem> | null;
    movimientos: Paginado<MovimientoItem> | null;
    almacenes: AlmacenOption[];
    categorias: CategoriaOption[];
    materiales: MaterialOption[];
    tiposMovimiento: Array<{ value: string; label: string }>;
    filtros: Record<string, string | number | boolean | null>;
    esRecepcionPataz: boolean;
}

export default function LogisticaStockKardex({
    pestanaActiva,
    stocks,
    movimientos,
    almacenes,
    categorias,
    materiales,
    tiposMovimiento,
    filtros,
    esRecepcionPataz,
}: StockKardexProps) {
    const { auth } = usePage<SharedData>().props;
    const permisos = auth?.user?.permisos ?? {};
    const puedeIngresoAjuste = (permisos.envios ?? '').includes('C');
    const puedeConsumo = (permisos.recepciones_consumos ?? '').includes('C');

    const [almacenId, setAlmacenId] = useState(filtros.almacen_id ? String(filtros.almacen_id) : '');
    const [categoriaId, setCategoriaId] = useState(filtros.categoria_id ? String(filtros.categoria_id) : '');
    const [materialId, setMaterialId] = useState(filtros.material_id ? String(filtros.material_id) : '');
    const [tipoMov, setTipoMov] = useState(filtros.tipo ? String(filtros.tipo) : '');
    const [buscar, setBuscar] = useState(filtros.buscar ? String(filtros.buscar) : '');
    const [bajoMinimo, setBajoMinimo] = useState(Boolean(filtros.bajo_minimo));
    const [desde, setDesde] = useState(filtros.desde ? String(filtros.desde) : '');
    const [hasta, setHasta] = useState(filtros.hasta ? String(filtros.hasta) : '');

    const [modalConsumo, setModalConsumo] = useState(false);
    const [modalAjuste, setModalAjuste] = useState(false);

    const formConsumo = useForm({
        almacen_id: almacenes[0]?.id ? String(almacenes[0].id) : '',
        material_id: materiales[0]?.id ? String(materiales[0].id) : '',
        cantidad: '1',
        observacion: '',
    });

    const formAjuste = useForm({
        almacen_id: almacenes[0]?.id ? String(almacenes[0].id) : '',
        material_id: materiales[0]?.id ? String(materiales[0].id) : '',
        tipo: 'ENTRADA_COMPRA',
        cantidad: '1',
        es_salida: false as boolean,
        observacion: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Inicio', href: '/dashboard' },
        { title: 'Logística', href: '/logistica' },
        {
            title: pestanaActiva === 'stock' ? 'Stock por Almacén' : 'Kardex de Inventario',
            href: pestanaActiva === 'stock' ? '/logistica/stock' : '/logistica/kardex',
        },
    ];

    const filtrar = (e: FormEvent) => {
        e.preventDefault();
        if (pestanaActiva === 'stock') {
            router.get(
                '/logistica/stock',
                {
                    almacen_id: almacenId || undefined,
                    categoria_id: categoriaId || undefined,
                    buscar: buscar || undefined,
                    bajo_minimo: bajoMinimo ? 1 : undefined,
                },
                { preserveState: true, replace: true },
            );
        } else {
            router.get(
                '/logistica/kardex',
                {
                    almacen_id: almacenId || undefined,
                    material_id: materialId || undefined,
                    tipo: tipoMov || undefined,
                    desde: desde || undefined,
                    hasta: hasta || undefined,
                },
                { preserveState: true, replace: true },
            );
        }
    };

    const enviarConsumo = (e: FormEvent) => {
        e.preventDefault();
        formConsumo.post('/logistica/consumos', {
            onSuccess: () => {
                formConsumo.reset('cantidad', 'observacion');
                setModalConsumo(false);
            },
        });
    };

    const enviarAjuste = (e: FormEvent) => {
        e.preventDefault();
        formAjuste.post('/logistica/stock/movimiento', {
            onSuccess: () => {
                formAjuste.reset('cantidad', 'observacion');
                setModalAjuste(false);
            },
        });
    };

    return (
        <LogisticaLayout
            breadcrumbs={breadcrumbs}
            titulo={pestanaActiva === 'stock' ? 'Stock por Almacén' : 'Kardex de Inventario'}
            descripcion={
                esRecepcionPataz
                    ? 'Consulta de saldos, movimientos y registro de consumo diario en su almacén.'
                    : 'Saldos en tiempo real Trujillo / Pataz, alertas de reposición y trazabilidad completa de movimientos.'
            }
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex gap-2">
                    <Link
                        href="/logistica/stock"
                        className={`inline-flex h-9 items-center rounded-md border px-3.5 text-[13px] font-medium transition-colors ${
                            pestanaActiva === 'stock'
                                ? 'border-zinc-700 bg-zinc-900 text-zinc-100'
                                : 'border-zinc-800 bg-[#0f0f13] text-zinc-400 hover:border-zinc-700 hover:text-zinc-200'
                        }`}
                    >
                        Stock Actual
                    </Link>
                    <Link
                        href="/logistica/kardex"
                        className={`inline-flex h-9 items-center rounded-md border px-3.5 text-[13px] font-medium transition-colors ${
                            pestanaActiva === 'kardex'
                                ? 'border-zinc-700 bg-zinc-900 text-zinc-100'
                                : 'border-zinc-800 bg-[#0f0f13] text-zinc-400 hover:border-zinc-700 hover:text-zinc-200'
                        }`}
                    >
                        Kardex de Movimientos
                    </Link>
                </div>

                <div className="flex flex-wrap gap-2">
                    {pestanaActiva === 'kardex' && (
                        <a
                            href={`/logistica/reportes/kardex-pdf?${new URLSearchParams({
                                ...(almacenId ? { almacen_id: almacenId } : {}),
                                ...(materialId ? { material_id: materialId } : {}),
                                ...(desde ? { desde } : {}),
                                ...(hasta ? { hasta } : {}),
                            }).toString()}`}
                            className="inline-flex h-9 items-center gap-1.5 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] font-medium text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 transition-colors"
                        >
                            <FileDown size={15} className="text-zinc-400" /> Exportar Kardex PDF
                        </a>
                    )}
                    {puedeConsumo && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setModalConsumo(true)}
                            className="h-9 border-amber-900/50 bg-amber-950/20 text-amber-300 hover:bg-amber-900/40 text-[13px]"
                        >
                            <MinusCircle size={15} className="mr-1.5 text-amber-400" /> Registrar Consumo
                        </Button>
                    )}
                    {puedeIngresoAjuste && (
                        <Button
                            type="button"
                            onClick={() => setModalAjuste(true)}
                            className="h-9 bg-zinc-100 text-zinc-900 font-medium px-4 py-2 rounded-md hover:bg-white text-[13px]"
                        >
                            <PlusCircle size={15} className="mr-1.5" /> Ingreso / Ajuste
                        </Button>
                    )}
                </div>
            </div>

            <form onSubmit={filtrar} className="flex flex-wrap items-center gap-2.5 rounded-lg border border-zinc-800 bg-[#0f0f13] p-3">
                <select
                    value={almacenId}
                    onChange={(e) => setAlmacenId(e.target.value)}
                    className="h-9 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-300 focus:outline-none focus:border-blue-500"
                >
                    <option value="">Todos los almacenes permitidos</option>
                    {almacenes.map((a) => (
                        <option key={a.id} value={a.id}>
                            {a.nombre}
                        </option>
                    ))}
                </select>

                {pestanaActiva === 'stock' ? (
                    <>
                        <select
                            value={categoriaId}
                            onChange={(e) => setCategoriaId(e.target.value)}
                            className="h-9 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-300 focus:outline-none focus:border-blue-500"
                        >
                            <option value="">Todas las categorías</option>
                            {categorias.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.nombre}
                                </option>
                            ))}
                        </select>
                        <div className="relative">
                            <Search size={14} className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-zinc-500" />
                            <Input
                                value={buscar}
                                onChange={(e) => setBuscar(e.target.value)}
                                placeholder="Código o material..."
                                className="h-9 w-56 pl-8 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500"
                            />
                        </div>
                        <label className="flex items-center gap-2 px-1 text-[13px] text-zinc-300 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={bajoMinimo}
                                onChange={(e) => setBajoMinimo(e.target.checked)}
                                className="rounded border-zinc-800 bg-zinc-900 text-amber-500 focus:ring-0 focus:ring-offset-0"
                            />
                            Solo bajo mínimo
                        </label>
                    </>
                ) : (
                    <>
                        <select
                            value={materialId}
                            onChange={(e) => setMaterialId(e.target.value)}
                            className="h-9 max-w-60 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-300 focus:outline-none focus:border-blue-500"
                        >
                            <option value="">Todos los materiales</option>
                            {materiales.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.codigo} - {m.nombre}
                                </option>
                            ))}
                        </select>
                        <select
                            value={tipoMov}
                            onChange={(e) => setTipoMov(e.target.value)}
                            className="h-9 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-300 focus:outline-none focus:border-blue-500"
                        >
                            <option value="">Todos los tipos</option>
                            {tiposMovimiento.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                        <Input
                            type="date"
                            value={desde}
                            onChange={(e) => setDesde(e.target.value)}
                            className="h-9 w-36 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                        />
                        <Input
                            type="date"
                            value={hasta}
                            onChange={(e) => setHasta(e.target.value)}
                            className="h-9 w-36 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                        />
                    </>
                )}

                <Button
                    type="submit"
                    variant="outline"
                    className="h-9 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 text-[13px]"
                >
                    Aplicar filtros
                </Button>
            </form>

            {pestanaActiva === 'stock' && stocks && (
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardContent className="p-0">
                        {stocks.data.length === 0 ? (
                            <div className="p-10 text-center text-[13px] text-zinc-500">No hay registros de stock para los filtros aplicados.</div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-[13px]">
                                    <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">
                                        <tr>
                                            <th className="px-4 py-3">Almacén</th>
                                            <th className="px-4 py-3">Código</th>
                                            <th className="px-4 py-3">Material</th>
                                            <th className="px-4 py-3">Categoría</th>
                                            <th className="px-4 py-3 text-right">Stock Actual</th>
                                            <th className="px-4 py-3 text-right">Stock Mínimo</th>
                                            <th className="px-4 py-3">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-zinc-800/60">
                                        {stocks.data.map((s) => {
                                            const qty = Number(s.cantidad);
                                            const min = Number(s.material.stock_minimo);
                                            const critico = min > 0 && qty <= min;

                                            return (
                                                <tr key={s.id} className="hover:bg-zinc-900/40 transition-colors">
                                                    <td className="px-4 py-3 font-medium text-zinc-200">{s.almacen.nombre}</td>
                                                    <td className="px-4 py-3 font-mono text-[12px] text-zinc-400">{s.material.codigo}</td>
                                                    <td className="px-4 py-3 text-zinc-100 font-medium">{s.material.nombre}</td>
                                                    <td className="px-4 py-3 text-zinc-400">{s.material.categoria?.nombre ?? '—'}</td>
                                                    <td className={`px-4 py-3 text-right font-semibold tabular-nums ${critico ? 'text-amber-400' : 'text-zinc-100'}`}>
                                                        {qty.toFixed(3)} {s.material.unidad_medida?.abreviatura ?? ''}
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums text-zinc-400">{min.toFixed(3)}</td>
                                                    <td className="px-4 py-3">
                                                        {critico ? (
                                                            <Badge variant="outline" className="border-amber-900/50 bg-amber-950/40 text-amber-300 text-[11px]">
                                                                Bajo mínimo
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="outline" className="border-emerald-900/50 bg-emerald-950/40 text-emerald-300 text-[11px]">
                                                                Óptimo
                                                            </Badge>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <Paginacion datos={stocks} />
                    </CardContent>
                </Card>
            )}

            {pestanaActiva === 'kardex' && movimientos && (
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardContent className="p-0">
                        {movimientos.data.length === 0 ? (
                            <div className="p-10 text-center text-[13px] text-zinc-500">No hay movimientos en el kardex para los filtros aplicados.</div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-[13px]">
                                    <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">
                                        <tr>
                                            <th className="px-4 py-3">Fecha / Hora</th>
                                            <th className="px-4 py-3">Almacén</th>
                                            <th className="px-4 py-3">Material</th>
                                            <th className="px-4 py-3">Tipo</th>
                                            <th className="px-4 py-3 text-right">Cantidad</th>
                                            <th className="px-4 py-3 text-right">Saldo</th>
                                            <th className="px-4 py-3">Responsable</th>
                                            <th className="px-4 py-3">Observación</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-zinc-800/60">
                                        {movimientos.data.map((m) => {
                                            const esSalida = m.tipo === 'SALIDA_ENVIO' || m.tipo === 'CONSUMO';
                                            return (
                                                <tr key={m.id} className="hover:bg-zinc-900/40 transition-colors">
                                                    <td className="px-4 py-3 text-[12px] tabular-nums text-zinc-400">
                                                        {new Date(m.fecha_hora).toLocaleString('es-PE')}
                                                    </td>
                                                    <td className="px-4 py-3 text-zinc-200">{m.almacen.nombre}</td>
                                                    <td className="px-4 py-3">
                                                        <span className="font-mono text-[11px] text-zinc-500 mr-1.5">{m.material.codigo}</span>
                                                        <span className="font-medium text-zinc-200">{m.material.nombre}</span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge variant="outline" className="gap-1 text-[11px] border-zinc-800 bg-zinc-900 text-zinc-300">
                                                            {esSalida ? (
                                                                <ArrowUpCircle size={12} className="text-red-400" />
                                                            ) : (
                                                                <ArrowDownCircle size={12} className="text-emerald-400" />
                                                            )}
                                                            {m.tipo}
                                                        </Badge>
                                                    </td>
                                                    <td className={`px-4 py-3 text-right font-medium tabular-nums ${esSalida ? 'text-red-400' : 'text-emerald-400'}`}>
                                                        {esSalida ? '-' : '+'}
                                                        {Number(m.cantidad).toFixed(3)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-semibold tabular-nums text-zinc-100">
                                                        {Number(m.saldo_resultante).toFixed(3)}
                                                    </td>
                                                    <td className="px-4 py-3 text-zinc-300">{m.usuario?.name ?? '—'}</td>
                                                    <td className="px-4 py-3 text-[12px] text-zinc-400">{m.observacion ?? '—'}</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <Paginacion datos={movimientos} />
                    </CardContent>
                </Card>
            )}

            <Dialog open={modalConsumo} onOpenChange={setModalConsumo}>
                <DialogContent className="bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold text-zinc-100">Registrar consumo de material</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={enviarConsumo} className="space-y-4">
                        <div>
                            <Label htmlFor="cons-alm" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Almacén *</Label>
                            <select
                                id="cons-alm"
                                value={formConsumo.data.almacen_id}
                                onChange={(e) => formConsumo.setData('almacen_id', e.target.value)}
                                className="mt-1 flex h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                required
                            >
                                {almacenes.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.nombre}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label htmlFor="cons-mat" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Material *</Label>
                            <select
                                id="cons-mat"
                                value={formConsumo.data.material_id}
                                onChange={(e) => formConsumo.setData('material_id', e.target.value)}
                                className="mt-1 flex h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                required
                            >
                                {materiales.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.codigo} - {m.nombre} ({m.unidad_medida?.abreviatura ?? ''})
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label htmlFor="cons-qty" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Cantidad consumida *</Label>
                            <Input
                                id="cons-qty"
                                type="number"
                                step="0.001"
                                min="0.001"
                                value={formConsumo.data.cantidad}
                                onChange={(e) => formConsumo.setData('cantidad', e.target.value)}
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                required
                            />
                            {(formConsumo.errors as Record<string, string>).stock && (
                                <p className="mt-1 text-[12px] text-red-400">{(formConsumo.errors as Record<string, string>).stock}</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="cons-obs" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Frente / Sustento de consumo *</Label>
                            <Input
                                id="cons-obs"
                                value={formConsumo.data.observacion}
                                onChange={(e) => formConsumo.setData('observacion', e.target.value)}
                                placeholder="Ej. Nivel 2140 - Galería Norte"
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                required
                            />
                        </div>
                        <DialogFooter className="border-t border-zinc-800 pt-3 gap-2 sm:gap-0">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalConsumo(false)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={formConsumo.processing}
                                className="bg-zinc-100 text-zinc-900 font-medium hover:bg-white text-[13px]"
                            >
                                Registrar consumo
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={modalAjuste} onOpenChange={setModalAjuste}>
                <DialogContent className="bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold text-zinc-100">Ingreso directo / Ajuste de inventario</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={enviarAjuste} className="space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label htmlFor="aj-alm" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Almacén *</Label>
                                <select
                                    id="aj-alm"
                                    value={formAjuste.data.almacen_id}
                                    onChange={(e) => formAjuste.setData('almacen_id', e.target.value)}
                                    className="mt-1 flex h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                    required
                                >
                                    {almacenes.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="aj-tipo" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Tipo *</Label>
                                <select
                                    id="aj-tipo"
                                    value={formAjuste.data.tipo}
                                    onChange={(e) => formAjuste.setData('tipo', e.target.value)}
                                    className="mt-1 flex h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    <option value="ENTRADA_COMPRA">Entrada por compra</option>
                                    <option value="AJUSTE">Ajuste de inventario</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <Label htmlFor="aj-mat" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Material *</Label>
                            <select
                                id="aj-mat"
                                value={formAjuste.data.material_id}
                                onChange={(e) => formAjuste.setData('material_id', e.target.value)}
                                className="mt-1 flex h-9 w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                required
                            >
                                {materiales.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.codigo} - {m.nombre}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label htmlFor="aj-qty" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Cantidad *</Label>
                            <Input
                                id="aj-qty"
                                type="number"
                                step="0.001"
                                min="0.001"
                                value={formAjuste.data.cantidad}
                                onChange={(e) => formAjuste.setData('cantidad', e.target.value)}
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                required
                            />
                            {(formAjuste.errors as Record<string, string>).stock && (
                                <p className="mt-1 text-[12px] text-red-400">{(formAjuste.errors as Record<string, string>).stock}</p>
                            )}
                        </div>
                        {formAjuste.data.tipo === 'AJUSTE' && (
                            <label className="flex items-center gap-2 text-[13px] text-zinc-300 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={formAjuste.data.es_salida}
                                    onChange={(e) => formAjuste.setData('es_salida', e.target.checked)}
                                    className="rounded border-zinc-800 bg-zinc-900 text-blue-500 focus:ring-0 focus:ring-offset-0"
                                />
                                Es ajuste negativo (disminuye el stock)
                            </label>
                        )}
                        <div>
                            <Label htmlFor="aj-obs" className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400">Motivo / Documento *</Label>
                            <Input
                                id="aj-obs"
                                value={formAjuste.data.observacion}
                                onChange={(e) => formAjuste.setData('observacion', e.target.value)}
                                placeholder="Ej. Factura F001-2340 / Conteo físico"
                                className="mt-1 h-9 bg-zinc-900 border-zinc-800 text-[13px] text-zinc-200"
                                required
                            />
                        </div>
                        <DialogFooter className="border-t border-zinc-800 pt-3 gap-2 sm:gap-0">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalAjuste(false)}
                                className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 text-[13px]"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={formAjuste.processing}
                                className="bg-zinc-100 text-zinc-900 font-medium hover:bg-white text-[13px]"
                            >
                                Guardar movimiento
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </LogisticaLayout>
    );
}
