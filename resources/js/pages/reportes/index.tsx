import GerenciaLayout from '@/layouts/gerencia/gerencia-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowLeftRight,
    Boxes,
    Calendar,
    Clock,
    Download,
    ExternalLink,
    FileCheck,
    FileSpreadsheet,
    FileText,
    FolderTree,
    Package,
    Scale,
    Search,
    Shield,
    Tag,
    TrendingDown,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';

interface ReporteItem {
    id: string;
    titulo: string;
    descripcion: string;
    formato: string;
    icono: string;
    url_vista: string;
    url_descarga: string;
    recurso: string;
}

interface ModuloReporte {
    modulo: string;
    clave: string;
    descripcion: string;
    items: ReporteItem[];
}

interface Props {
    modulos: ModuloReporte[];
    totalReportes: number;
    rolEtiqueta: string;
}

const ICONOS_MAP: Record<string, typeof FileText> = {
    Users,
    Activity,
    Calendar,
    ArrowLeftRight,
    Package,
    Boxes,
    FileSpreadsheet,
    Scale,
    TrendingDown,
    Clock,
    FolderTree,
    Tag,
    FileCheck,
};

export default function ReportesIndex({ modulos = [], totalReportes = 0, rolEtiqueta }: Props) {
    const [busqueda, setBusqueda] = useState('');
    const [filtroFormato, setFiltroFormato] = useState<'todos' | 'XLSX' | 'PDF'>('todos');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Resumen Ejecutivo', href: '/dashboard' },
        { title: 'Analítica de Datos', href: '/reportes' },
    ];

    const modulosFiltrados = useMemo(() => {
        const query = busqueda.trim().toLowerCase();

        return modulos
            .map((m) => {
                const itemsFiltrados = m.items.filter((item) => {
                    const matchTexto =
                        !query ||
                        item.titulo.toLowerCase().includes(query) ||
                        item.descripcion.toLowerCase().includes(query) ||
                        m.modulo.toLowerCase().includes(query);

                    const matchFormato =
                        filtroFormato === 'todos' ||
                        (filtroFormato === 'XLSX' && item.formato.includes('XLSX')) ||
                        (filtroFormato === 'PDF' && item.formato.includes('PDF'));

                    return matchTexto && matchFormato;
                });

                return {
                    ...m,
                    items: itemsFiltrados,
                };
            })
            .filter((m) => m.items.length > 0);
    }, [modulos, busqueda, filtroFormato]);

    const cantidadReportesVisibles = useMemo(() => {
        return modulosFiltrados.reduce((acc, m) => acc + m.items.length, 0);
    }, [modulosFiltrados]);

    return (
        <GerenciaLayout
            breadcrumbs={breadcrumbs}
            titulo="Analítica de Datos y Reportes"
            descripcion="Catálogo centralizado de informes operativos, contables y gerenciales. Descarga en Excel (XLSX) o PDF."
        >
            <Head title="Analítica de Datos - ERP Morillas" />

            {/* Barra de Búsqueda y Filtros Rápidos */}
            <div className="flex flex-col gap-4 rounded-lg border border-zinc-800 bg-[#0f0f13] p-5 md:flex-row md:items-center md:justify-between">
                <div className="relative flex-1 max-w-md">
                    <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500" />
                    <input
                        type="text"
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                        placeholder="Buscar reporte por nombre o palabra clave..."
                        className="w-full rounded-md border border-zinc-800 bg-zinc-900 py-1.5 pl-8 pr-3 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500 focus:outline-none transition-colors"
                    />
                </div>

                <div className="flex flex-wrap items-center gap-1.5">
                    <span className="text-xs text-zinc-500 mr-1">Formato:</span>
                    <button
                        type="button"
                        onClick={() => setFiltroFormato('todos')}
                        className={`rounded-md px-3 py-1 text-xs font-medium transition-colors ${
                            filtroFormato === 'todos'
                                ? 'bg-zinc-100 text-zinc-900'
                                : 'border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-200'
                        }`}
                    >
                        Todos
                    </button>
                    <button
                        type="button"
                        onClick={() => setFiltroFormato('XLSX')}
                        className={`rounded-md px-3 py-1 text-xs font-medium transition-colors ${
                            filtroFormato === 'XLSX'
                                ? 'bg-zinc-100 text-zinc-900'
                                : 'border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-200'
                        }`}
                    >
                        Solo Excel (XLSX)
                    </button>
                    <button
                        type="button"
                        onClick={() => setFiltroFormato('PDF')}
                        className={`rounded-md px-3 py-1 text-xs font-medium transition-colors ${
                            filtroFormato === 'PDF'
                                ? 'bg-zinc-100 text-zinc-900'
                                : 'border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-200'
                        }`}
                    >
                        Solo PDF
                    </button>
                </div>
            </div>

            {/* Aviso informativo de permisos del rol */}
            <div className="flex items-center justify-between rounded-lg border border-zinc-800 bg-[#0f0f13] px-4 py-2.5 text-xs text-zinc-400">
                <div className="flex items-center gap-2">
                    <Shield size={14} className="text-zinc-500" />
                    <span>
                        Perfil: <strong className="text-zinc-200">{rolEtiqueta}</strong>. Mostrando{' '}
                        <strong className="text-zinc-200 tabular-nums">{cantidadReportesVisibles}</strong> de{' '}
                        <strong className="tabular-nums">{totalReportes}</strong> reportes disponibles.
                    </span>
                </div>
            </div>

            {/* Catálogo de Reportes por Módulo */}
            {modulosFiltrados.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-lg border border-zinc-800 bg-[#0f0f13] p-12 text-center">
                    <FileSpreadsheet size={24} className="text-zinc-600 mb-2" />
                    <h3 className="text-[14px] font-medium text-zinc-300">No se encontraron reportes</h3>
                    <p className="mt-1 text-[13px] text-zinc-500">
                        Intenta ajustar la búsqueda o seleccionar otro filtro de formato.
                    </p>
                    <button
                        type="button"
                        onClick={() => {
                            setBusqueda('');
                            setFiltroFormato('todos');
                        }}
                        className="mt-4 rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-xs font-medium text-zinc-300 hover:bg-zinc-800 hover:text-white"
                    >
                        Limpiar filtros
                    </button>
                </div>
            ) : (
                <div className="flex flex-col gap-6">
                    {modulosFiltrados.map((mod) => (
                        <div key={mod.clave} className="flex flex-col gap-3">
                            <div className="flex items-baseline justify-between border-b border-zinc-800 pb-2">
                                <div>
                                    <h3 className="text-[14px] font-medium text-zinc-100">{mod.modulo}</h3>
                                    <p className="text-[12px] text-zinc-500">{mod.descripcion}</p>
                                </div>
                                <span className="rounded border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-medium text-zinc-400 tabular-nums">
                                    {mod.items.length} {mod.items.length === 1 ? 'reporte' : 'reportes'}
                                </span>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {mod.items.map((item) => {
                                    const IconoComponente = ICONOS_MAP[item.icono] ?? FileText;

                                    return (
                                        <div
                                            key={item.id}
                                            className="flex flex-col justify-between rounded-lg border border-zinc-800 bg-[#0f0f13] p-5 transition-colors hover:border-zinc-700"
                                        >
                                            <div className="space-y-2">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="rounded-md border border-zinc-800 bg-zinc-900 p-2 text-zinc-400">
                                                        <IconoComponente size={16} />
                                                    </div>
                                                    <div className="flex flex-wrap gap-1">
                                                        {item.formato.includes('PDF') && (
                                                            <span className="rounded border border-red-900/50 bg-red-950/40 px-1.5 py-0.5 text-[10px] font-medium text-red-400">
                                                                PDF
                                                            </span>
                                                        )}
                                                        {item.formato.includes('XLSX') && (
                                                            <span className="rounded border border-emerald-900/50 bg-emerald-950/40 px-1.5 py-0.5 text-[10px] font-medium text-emerald-400">
                                                                XLSX
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>

                                                <h3 className="text-[14px] font-medium text-zinc-100">{item.titulo}</h3>
                                                <p className="text-[13px] text-zinc-400 leading-relaxed line-clamp-2">{item.descripcion}</p>
                                            </div>

                                            <div className="mt-4 flex items-center justify-between gap-2 border-t border-zinc-800 pt-3">
                                                <Link
                                                    href={item.url_vista}
                                                    className="inline-flex items-center gap-1.5 text-xs font-medium text-zinc-400 hover:text-zinc-200 transition-colors"
                                                >
                                                    <ExternalLink size={12} />
                                                    <span>Ver detalle</span>
                                                </Link>

                                                <a
                                                    href={item.url_descarga}
                                                    className="inline-flex items-center gap-1.5 rounded-md bg-zinc-100 px-3 py-1.5 text-xs font-medium text-zinc-900 hover:bg-white transition-colors"
                                                >
                                                    <Download size={12} />
                                                    <span>Descargar</span>
                                                </a>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </GerenciaLayout>
    );
}
