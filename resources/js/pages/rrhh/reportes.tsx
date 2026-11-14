import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import RrhhLayout from '@/layouts/rrhh/rrhh-layout';
import { type BreadcrumbItem } from '@/types';
import { router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface ReportesRrhhProps {
    tipo: string;
    reportesPermitidos: string[];
    filtros: {
        tipo: string;
        sede_id: string;
        area_id: string;
        estado_examen: string;
        anio: string;
        desde: string;
        hasta: string;
    };
    encabezados: string[];
    filas: Array<Array<string | number | null>>;
    sedes: Array<{ id: number; nombre: string }>;
    areas: Array<{ id: number; nombre: string }>;
}

const NOMBRES_REPORTE: Record<string, string> = {
    personal: 'Personal por sede y área',
    examenes: 'Exámenes vencidos / por vencer',
    vacaciones: 'Vacaciones del período',
    movimientos: 'Movimientos de personal',
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'RR.HH.', href: '/rrhh' },
    { title: 'Reportes RR.HH.', href: '/rrhh/reportes' },
];

export default function ReportesRrhhPage({
    tipo,
    reportesPermitidos,
    filtros,
    encabezados,
    filas,
    sedes,
    areas,
}: ReportesRrhhProps) {
    const [sedeId, setSedeId] = useState(filtros.sede_id);
    const [areaId, setAreaId] = useState(filtros.area_id);
    const [estadoExamen, setEstadoExamen] = useState(filtros.estado_examen);
    const [anio, setAnio] = useState(filtros.anio);
    const [desde, setDesde] = useState(filtros.desde);
    const [hasta, setHasta] = useState(filtros.hasta);

    const cambiarTipo = (nuevoTipo: string) => {
        router.get('/rrhh/reportes', { tipo: nuevoTipo }, { preserveState: false });
    };

    const aplicar = (e?: FormEvent) => {
        e?.preventDefault();
        router.get(
            '/rrhh/reportes',
            { tipo, sede_id: sedeId, area_id: areaId, estado_examen: estadoExamen, anio, desde, hasta },
            { preserveState: true, replace: true },
        );
    };

    const queryExport = new URLSearchParams({
        sede_id: sedeId,
        area_id: areaId,
        estado_examen: estadoExamen,
        anio,
        desde,
        hasta,
    }).toString();

    return (
        <RrhhLayout
            titulo="Reportes de RR.HH."
            descripcion="Consultas operativas y exportación directa a Excel (.xlsx) según permisos del rol."
            breadcrumbs={breadcrumbs}
            acciones={
                <a href={`/rrhh/reportes/exportar/${tipo}?${queryExport}`}>
                    <Button className="min-h-9 border border-emerald-900/60 bg-emerald-950/40 text-emerald-400 hover:bg-emerald-900/40 text-[13px] font-medium">
                        <Download className="mr-2 h-4 w-4" />
                        Exportar Excel (.xlsx)
                    </Button>
                </a>
            }
        >
            <div className="flex flex-wrap gap-2">
                {reportesPermitidos.map((clave) => (
                    <Button
                        key={clave}
                        type="button"
                        variant={tipo === clave ? 'default' : 'outline'}
                        onClick={() => cambiarTipo(clave)}
                        className={tipo === clave ? 'min-h-9 bg-zinc-100 text-zinc-900 font-medium' : 'min-h-9 border-zinc-800 text-zinc-400 hover:text-white hover:bg-zinc-900'}
                    >
                        {NOMBRES_REPORTE[clave] ?? clave}
                    </Button>
                ))}
            </div>

            <Card className="border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-4">
                    <form onSubmit={aplicar} className="grid gap-3 sm:grid-cols-2 md:grid-cols-5">
                        {(tipo === 'personal' || tipo === 'examenes' || tipo === 'vacaciones') && (
                            <select
                                value={sedeId}
                                onChange={(e) => setSedeId(e.target.value)}
                                className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                <option value="">Todas las sedes</option>
                                {sedes.map((s) => (
                                    <option key={s.id} value={String(s.id)}>
                                        {s.nombre}
                                    </option>
                                ))}
                            </select>
                        )}

                        {(tipo === 'personal' || tipo === 'vacaciones') && (
                            <select
                                value={areaId}
                                onChange={(e) => setAreaId(e.target.value)}
                                className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                <option value="">Todas las áreas</option>
                                {areas.map((a) => (
                                    <option key={a.id} value={String(a.id)}>
                                        {a.nombre}
                                    </option>
                                ))}
                            </select>
                        )}

                        {tipo === 'examenes' && (
                            <select
                                value={estadoExamen}
                                onChange={(e) => setEstadoExamen(e.target.value)}
                                className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                <option value="todos">Todos los exámenes</option>
                                <option value="por_vencer">Por vencer ≤ 30 días</option>
                                <option value="vencidos">Vencidos</option>
                                <option value="vigentes">Vigentes</option>
                            </select>
                        )}

                        {tipo === 'vacaciones' && (
                            <Input type="number" value={anio} onChange={(e) => setAnio(e.target.value)} placeholder="Año" className="min-h-10 bg-zinc-900 border-zinc-800 text-zinc-200" />
                        )}

                        {tipo === 'movimientos' && (
                            <>
                                <Input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} className="min-h-10 bg-zinc-900 border-zinc-800 text-zinc-200" />
                                <Input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} className="min-h-10 bg-zinc-900 border-zinc-800 text-zinc-200" />
                            </>
                        )}

                        <Button type="submit" variant="secondary" className="min-h-10 border border-zinc-800 bg-zinc-900 text-zinc-200 hover:bg-zinc-800">
                            Aplicar filtros
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card className="border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">
                                <tr>
                                    {encabezados.map((col) => (
                                        <th key={col} className="px-4 py-3 whitespace-nowrap">
                                            {col}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {filas.length === 0 ? (
                                    <tr>
                                        <td colSpan={Math.max(1, encabezados.length)} className="px-4 py-10 text-center text-zinc-500">
                                            No hay datos para el reporte seleccionado.
                                        </td>
                                    </tr>
                                ) : (
                                    filas.map((fila, idx) => (
                                        <tr key={idx} className="hover:bg-zinc-900/40 transition-colors">
                                            {fila.map((celda, cIdx) => (
                                                <td key={cIdx} className="px-4 py-2.5 text-zinc-300">
                                                    {celda ?? '—'}
                                                </td>
                                            ))}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </RrhhLayout>
    );
}

