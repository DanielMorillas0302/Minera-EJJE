import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import LogisticaLayout from '@/layouts/logistica/logistica-layout';
import { type BreadcrumbItem } from '@/types';
import { router } from '@inertiajs/react';
import { FileDown, FileSpreadsheet } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface StockReporteRow {
    id: number;
    cantidad: string;
    almacen: { nombre: string };
    material: {
        codigo: string;
        nombre: string;
        stock_minimo: string;
        unidad_medida?: { abreviatura: string } | null;
    };
}

interface EnvioResumenRow {
    estado: string;
    etiqueta: string;
    total: number;
}

interface DiferenciaRow {
    id: number;
    cantidad_enviada: string;
    cantidad_recibida: string;
    observacion_recepcion: string | null;
    envio?: { codigo: string; fecha_recepcion: string | null } | null;
    material?: { codigo: string; nombre: string } | null;
}

interface ReqPendienteRow {
    id: number;
    codigo: string;
    fecha_solicitud: string;
    prioridad: string;
    estado: string;
    area?: { nombre: string } | null;
    sede?: { nombre: string } | null;
}

interface ReportesProps {
    filtros: {
        almacen_id: number | null;
        material_id: number | null;
        desde: string | null;
        hasta: string | null;
    };
    almacenes: Array<{ id: number; nombre: string }>;
    materiales: Array<{ id: number; codigo: string; nombre: string }>;
    stockActual: StockReporteRow[];
    enviosPorEstado: EnvioResumenRow[];
    diferenciasRecepcion: DiferenciaRow[];
    requerimientosPendientes: ReqPendienteRow[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Logística', href: '/logistica' },
    { title: 'Reportes', href: '/logistica/reportes' },
];

export default function LogisticaReportes({
    filtros,
    almacenes,
    materiales,
    stockActual,
    enviosPorEstado,
    diferenciasRecepcion,
    requerimientosPendientes,
}: ReportesProps) {
    const [almacenId, setAlmacenId] = useState(filtros.almacen_id ? String(filtros.almacen_id) : '');
    const [materialId, setMaterialId] = useState(filtros.material_id ? String(filtros.material_id) : '');
    const [desde, setDesde] = useState(filtros.desde ?? '');
    const [hasta, setHasta] = useState(filtros.hasta ?? '');

    const queryBase = () => {
        const params = new URLSearchParams();
        if (almacenId) params.set('almacen_id', almacenId);
        if (materialId) params.set('material_id', materialId);
        if (desde) params.set('desde', desde);
        if (hasta) params.set('hasta', hasta);
        return params.toString();
    };

    const filtrar = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            '/logistica/reportes',
            {
                almacen_id: almacenId || undefined,
                material_id: materialId || undefined,
                desde: desde || undefined,
                hasta: hasta || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const urlExcel = (tipo: string) => {
        const qs = queryBase();
        return `/logistica/reportes/exportar?tipo=${tipo}${qs ? `&${qs}` : ''}`;
    };

    const urlKardexPdf = () => {
        const qs = queryBase();
        return `/logistica/reportes/kardex-pdf${qs ? `?${qs}` : ''}`;
    };

    return (
        <LogisticaLayout
            breadcrumbs={breadcrumbs}
            titulo="Reportes de Logística"
            descripcion="Reportes operativos exportables a Excel (.xlsx) y Kardex en formato PDF."
        >
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
                <select
                    value={materialId}
                    onChange={(e) => setMaterialId(e.target.value)}
                    className="h-9 max-w-64 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-300 focus:outline-none focus:border-blue-500"
                >
                    <option value="">Todos los materiales</option>
                    {materiales.map((m) => (
                        <option key={m.id} value={m.id}>
                            {m.codigo} - {m.nombre}
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
                <Button
                    type="submit"
                    variant="outline"
                    className="h-9 border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 text-[13px]"
                >
                    Aplicar filtros
                </Button>
            </form>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-semibold text-zinc-100">Stock Actual por Almacén</CardTitle>
                        <CardDescription className="text-[12px] text-zinc-400">Saldos vigentes y comparación contra stock mínimo.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <a
                            href={urlExcel('stock')}
                            className="inline-flex h-9 items-center gap-2 rounded-md bg-zinc-100 px-4 text-[13px] font-medium text-zinc-900 hover:bg-white transition-colors"
                        >
                            <FileSpreadsheet size={15} /> Descargar Excel
                        </a>
                    </CardContent>
                </Card>

                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-semibold text-zinc-100">Kardex por Material / Almacén</CardTitle>
                        <CardDescription className="text-[12px] text-zinc-400">Historial completo de entradas, salidas y consumos.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        <a
                            href={urlExcel('kardex')}
                            className="inline-flex h-9 items-center gap-2 rounded-md border border-zinc-800 bg-zinc-900 px-3.5 text-[13px] font-medium text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 transition-colors"
                        >
                            <FileSpreadsheet size={15} className="text-emerald-400" /> Excel
                        </a>
                        <a
                            href={urlKardexPdf()}
                            className="inline-flex h-9 items-center gap-2 rounded-md bg-zinc-100 px-3.5 text-[13px] font-medium text-zinc-900 hover:bg-white transition-colors"
                        >
                            <FileDown size={15} /> Kardex PDF
                        </a>
                    </CardContent>
                </Card>

                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-semibold text-zinc-100">Diferencias, Envíos y Requerimientos</CardTitle>
                        <CardDescription className="text-[12px] text-zinc-400">Control de discrepancias en recepción y pendientes.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        <a
                            href={urlExcel('diferencias')}
                            className="inline-flex h-8 items-center gap-1.5 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[12px] font-medium text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 transition-colors"
                        >
                            <FileSpreadsheet size={13} className="text-amber-400" /> Diferencias
                        </a>
                        <a
                            href={urlExcel('envios')}
                            className="inline-flex h-8 items-center gap-1.5 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[12px] font-medium text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 transition-colors"
                        >
                            <FileSpreadsheet size={13} className="text-blue-400" /> Envíos
                        </a>
                        <a
                            href={urlExcel('requerimientos')}
                            className="inline-flex h-8 items-center gap-1.5 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[12px] font-medium text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 transition-colors"
                        >
                            <FileSpreadsheet size={13} className="text-emerald-400" /> Requerimientos
                        </a>
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-semibold text-zinc-100">Envíos por estado</CardTitle>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                        {enviosPorEstado.map((row) => (
                            <div key={row.estado} className="rounded-md border border-zinc-800 bg-[#09090b] p-3">
                                <p className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">{row.etiqueta}</p>
                                <p className="mt-1 text-2xl font-bold tabular-nums text-zinc-100 tracking-tight">{row.total}</p>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-semibold text-zinc-100">Diferencias detectadas en recepción ({diferenciasRecepcion.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {diferenciasRecepcion.length === 0 ? (
                            <p className="p-6 text-[13px] text-zinc-500">Sin diferencias registradas en el rango.</p>
                        ) : (
                            <table className="w-full text-left text-[12px]">
                                <thead className="border-y border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">
                                    <tr>
                                        <th className="px-4 py-2">Envío</th>
                                        <th className="px-4 py-2">Material</th>
                                        <th className="px-4 py-2 text-right">Env / Rec</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {diferenciasRecepcion.map((d) => (
                                        <tr key={d.id} className="hover:bg-zinc-900/30">
                                            <td className="px-4 py-2 font-mono text-zinc-300">{d.envio?.codigo}</td>
                                            <td className="px-4 py-2 text-zinc-200">{d.material?.nombre}</td>
                                            <td className="px-4 py-2 text-right font-medium tabular-nums text-amber-400">
                                                {Number(d.cantidad_enviada).toFixed(2)} / {Number(d.cantidad_recibida).toFixed(2)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-semibold text-zinc-100">Vista previa de Stock ({stockActual.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="max-h-72 overflow-y-auto">
                            <table className="w-full text-left text-[12px]">
                                <thead className="border-y border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">
                                    <tr>
                                        <th className="px-4 py-2">Almacén</th>
                                        <th className="px-4 py-2">Material</th>
                                        <th className="px-4 py-2 text-right">Stock</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {stockActual.map((s) => (
                                        <tr key={s.id} className="hover:bg-zinc-900/30">
                                            <td className="px-4 py-2 text-zinc-300">{s.almacen.nombre}</td>
                                            <td className="px-4 py-2 text-zinc-200">
                                                <span className="font-mono text-[11px] text-zinc-500 mr-1.5">{s.material.codigo}</span>
                                                {s.material.nombre}
                                            </td>
                                            <td className="px-4 py-2 text-right font-medium tabular-nums text-zinc-100">
                                                {Number(s.cantidad).toFixed(2)} {s.material.unidad_medida?.abreviatura ?? ''}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-semibold text-zinc-100">Requerimientos pendientes ({requerimientosPendientes.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="max-h-72 overflow-y-auto">
                            <table className="w-full text-left text-[12px]">
                                <thead className="border-y border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">
                                    <tr>
                                        <th className="px-4 py-2">Código</th>
                                        <th className="px-4 py-2">Área / Sede</th>
                                        <th className="px-4 py-2">Estado</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {requerimientosPendientes.map((r) => (
                                        <tr key={r.id} className="hover:bg-zinc-900/30">
                                            <td className="px-4 py-2 font-mono text-zinc-300">{r.codigo}</td>
                                            <td className="px-4 py-2 text-zinc-300">
                                                {r.area?.nombre} · {r.sede?.nombre}
                                            </td>
                                            <td className="px-4 py-2">
                                                <Badge variant="outline" className="border-zinc-800 bg-zinc-900 text-zinc-300 text-[11px]">
                                                    {r.estado}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </LogisticaLayout>
    );
}
