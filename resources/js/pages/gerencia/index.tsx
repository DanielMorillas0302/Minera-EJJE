import GerenciaLayout from '@/layouts/gerencia/gerencia-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import {
    AlertOctagon,
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    CheckCircle,
    Clock,
    DollarSign,
    Download,
    FileSpreadsheet,
    HardHat,
    Package,
    ShieldAlert,
    TrendingUp,
    Truck,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { Bar, Doughnut, Line } from 'react-chartjs-2';

// Registrar componentes de Chart.js
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler,
);

interface SedeSimple {
    id: number;
    nombre: string;
    tipo: string;
}

interface PeriodoKpi {
    anio: number;
    mes: number;
    mes_nombre: string;
}

interface RrhhKpis {
    personal_activo_total: number;
    personal_trujillo: number;
    personal_pataz: number;
    personal_transito: number;
    personal_apto: number;
    personal_no_apto: number;
    examenes_por_vencer_30d: number;
    examenes_vencidos: number;
}

interface LogisticaKpis {
    envios_en_transito: number;
    envios_retrasados: number;
    materiales_stock_critico: number;
    requerimientos_pendientes: number;
}

interface FinanzasKpis {
    total_ingresos_mes: number;
    total_egresos_mes: number;
    saldo_neto_mes: number;
    presupuesto_total_mes: number;
    porcentaje_ejecucion: number;
    cuentas_pendientes_total: number;
    cuentas_pendientes_count: number;
    cuentas_vencidas_total: number;
    cuentas_vencidas_count: number;
}

interface KpisConsolidados {
    periodo: PeriodoKpi;
    sede_id: number | null;
    rrhh: RrhhKpis;
    logistica: LogisticaKpis;
    finanzas: FinanzasKpis;
}

interface GraficosData {
    gasto_mensual: {
        meses: string[];
        ingresos: number[];
        egresos: number[];
    };
    presupuesto_vs_real: {
        centros: string[];
        presupuesto: number[];
        ejecutado: number[];
    };
    envios_por_estado: {
        labels: string[];
        valores: number[];
    };
    personal_por_area: {
        labels: string[];
        valores: number[];
    };
    aptitud_medica: {
        labels: string[];
        valores: number[];
    };
}

interface GerenciaDashboardProps {
    kpis: KpisConsolidados;
    graficos: GraficosData;
    sedes: SedeSimple[];
    filtros: {
        sede_id: string;
        anio: number;
        mes: number;
    };
    aniosDisponibles: number[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Gerencia General', href: '/gerencia' },
];

const MESES = [
    { id: 1, nombre: 'Enero' },
    { id: 2, nombre: 'Febrero' },
    { id: 3, nombre: 'Marzo' },
    { id: 4, nombre: 'Abril' },
    { id: 5, nombre: 'Mayo' },
    { id: 6, nombre: 'Junio' },
    { id: 7, nombre: 'Julio' },
    { id: 8, nombre: 'Agosto' },
    { id: 9, nombre: 'Setiembre' },
    { id: 10, nombre: 'Octubre' },
    { id: 11, nombre: 'Noviembre' },
    { id: 12, nombre: 'Diciembre' },
];

export default function GerenciaDashboard({
    kpis,
    graficos,
    sedes,
    filtros,
    aniosDisponibles,
}: GerenciaDashboardProps) {
    const [sedeSeleccionada, setSedeSeleccionada] = useState(filtros.sede_id || '');
    const [anioSeleccionado, setAnioSeleccionado] = useState(String(filtros.anio));
    const [mesSeleccionado, setMesSeleccionado] = useState(String(filtros.mes));

    const aplicarFiltros = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/gerencia',
            {
                sede_id: sedeSeleccionada || undefined,
                anio: anioSeleccionado,
                mes: mesSeleccionado,
            },
            { preserveState: true, replace: true },
        );
    };

    const descargarPdf = () => {
        const params = new URLSearchParams({
            ...(sedeSeleccionada ? { sede_id: sedeSeleccionada } : {}),
            anio: anioSeleccionado,
            mes: mesSeleccionado,
        });
        window.open(`/gerencia/reporte-pdf?${params.toString()}`, '_blank');
    };

    const formatearMoneda = (val: number) => {
        return new Intl.NumberFormat('es-PE', {
            style: 'currency',
            currency: 'PEN',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(val || 0);
    };

    // Configuración Gráficos Chart.js
    const dataGastoMensual = {
        labels: graficos.gasto_mensual.meses,
        datasets: [
            {
                label: 'Ingresos',
                data: graficos.gasto_mensual.ingresos,
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.3,
                fill: true,
            },
            {
                label: 'Egresos',
                data: graficos.gasto_mensual.egresos,
                borderColor: '#E11D48',
                backgroundColor: 'rgba(225, 29, 72, 0.1)',
                tension: 0.3,
                fill: true,
            },
        ],
    };

    const dataPresupuestoVsReal = {
        labels: graficos.presupuesto_vs_real.centros,
        datasets: [
            {
                label: 'Presupuesto Aprobado',
                data: graficos.presupuesto_vs_real.presupuesto,
                backgroundColor: '#3b82f6',
                borderRadius: 4,
            },
            {
                label: 'Gasto Real Ejecutado',
                data: graficos.presupuesto_vs_real.ejecutado,
                backgroundColor: '#f59e0b',
                borderRadius: 4,
            },
        ],
    };

    const dataEnvios = {
        labels: graficos.envios_por_estado.labels,
        datasets: [
            {
                data: graficos.envios_por_estado.valores,
                backgroundColor: ['#64748B', '#0284C7', '#10B981', '#F59E0B', '#E11D48'],
                borderWidth: 1,
            },
        ],
    };

    const dataPersonalArea = {
        labels: graficos.personal_por_area.labels,
        datasets: [
            {
                label: 'Trabajadores Activos',
                data: graficos.personal_por_area.valores,
                backgroundColor: '#3b82f6',
                borderRadius: 4,
            },
        ],
    };

    const dataAptitud = {
        labels: graficos.aptitud_medica.labels,
        datasets: [
            {
                data: graficos.aptitud_medica.valores,
                backgroundColor: ['#10B981', '#E11D48'],
                borderWidth: 1,
            },
        ],
    };

    return (
        <GerenciaLayout
            breadcrumbs={breadcrumbs}
            titulo="Dashboard Gerencial y Control Operativo"
            descripcion="Panel unificado con indicadores estratégicos de Recursos Humanos, Logística y Finanzas para la operación minera Trujillo - Pataz."
        >
            <Head title="Gerencia General - Dashboard" />

            {/* Barra de Filtros y Acción de Exportación */}
            <div className="flex flex-col gap-4 rounded-lg border border-zinc-800 bg-[#0f0f13] p-4 md:flex-row md:items-center md:justify-between">
                <form onSubmit={aplicarFiltros} className="flex flex-wrap items-center gap-2.5">
                    <div>
                        <label htmlFor="sede_id" className="sr-only">
                            Sede
                        </label>
                        <select
                            id="sede_id"
                            value={sedeSeleccionada}
                            onChange={(e) => setSedeSeleccionada(e.target.value)}
                            className="rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none"
                        >
                            <option value="">Todas las Sedes (Consolidado)</option>
                            {sedes.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.nombre} ({s.tipo})
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label htmlFor="anio" className="sr-only">
                            Año
                        </label>
                        <select
                            id="anio"
                            value={anioSeleccionado}
                            onChange={(e) => setAnioSeleccionado(e.target.value)}
                            className="rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none"
                        >
                            {aniosDisponibles.map((a) => (
                                <option key={a} value={a}>
                                    Año {a}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label htmlFor="mes" className="sr-only">
                            Mes
                        </label>
                        <select
                            id="mes"
                            value={mesSeleccionado}
                            onChange={(e) => setMesSeleccionado(e.target.value)}
                            className="rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 focus:border-blue-500 focus:outline-none"
                        >
                            {MESES.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.nombre}
                                </option>
                            ))}
                        </select>
                    </div>

                    <button
                        type="submit"
                        className="inline-flex items-center gap-1.5 rounded-md bg-zinc-100 px-3.5 py-1.5 text-[13px] font-medium text-zinc-900 hover:bg-white transition-colors"
                    >
                        Filtrar
                    </button>
                </form>

                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={descargarPdf}
                        className="inline-flex items-center gap-2 rounded-md border border-zinc-800 bg-zinc-900 px-3.5 py-1.5 text-[13px] font-medium text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 transition-colors"
                    >
                        <Download size={14} className="text-zinc-400" />
                        <span>Descargar Informe Ejecutivo (PDF)</span>
                    </button>
                </div>
            </div>

            {/* SECCIÓN 1: KPIs CONSOLIDADOS */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                {/* KPI RR.HH. Personal */}
                <div className="flex flex-col justify-between rounded-lg border border-zinc-800 bg-[#0f0f13] p-5">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Recursos Humanos</span>
                        <div className="rounded-md bg-zinc-900 border border-zinc-800 p-2 text-zinc-300">
                            <Users size={16} />
                        </div>
                    </div>
                    <div className="mt-3">
                        <p className="text-3xl font-bold tabular-nums text-zinc-100 tracking-tight">{kpis.rrhh.personal_activo_total}</p>
                        <p className="text-[12px] text-zinc-400">Personal Activo Registrado</p>
                    </div>
                    <div className="mt-4 flex flex-wrap gap-2 border-t border-zinc-800 pt-3 text-[11px] text-zinc-400">
                        <span className="rounded-md bg-zinc-900 border border-zinc-800 px-2 py-0.5 font-medium">Trujillo: {kpis.rrhh.personal_trujillo}</span>
                        <span className="rounded-md bg-zinc-900 border border-zinc-800 px-2 py-0.5 font-medium">Pataz: {kpis.rrhh.personal_pataz}</span>
                        {kpis.rrhh.personal_transito > 0 && (
                            <span className="rounded-md border border-amber-900/50 bg-amber-950/40 px-2 py-0.5 font-medium text-amber-300">
                                Tránsito: {kpis.rrhh.personal_transito}
                            </span>
                        )}
                    </div>
                </div>

                {/* KPI RR.HH. Aptitud y Salud */}
                <div className="flex flex-col justify-between rounded-lg border border-zinc-800 bg-[#0f0f13] p-5">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Salud y Ascenso Pataz</span>
                        <div className="rounded-md bg-zinc-900 border border-zinc-800 p-2 text-emerald-400">
                            <HardHat size={16} />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <p className="text-3xl font-bold tabular-nums text-emerald-400 tracking-tight">{kpis.rrhh.personal_apto}</p>
                            <span className="text-[12px] font-medium text-zinc-500">/ {kpis.rrhh.personal_activo_total} aptos</span>
                        </div>
                        <p className="text-[12px] text-zinc-400">Habilitados médicamente para altura</p>
                    </div>
                    <div className="mt-4 flex items-center justify-between border-t border-zinc-800 pt-3 text-[11px]">
                        <span className="flex items-center gap-1 text-amber-400">
                            <Clock size={12} /> Vencen en 30d: <strong>{kpis.rrhh.examenes_por_vencer_30d}</strong>
                        </span>
                        {kpis.rrhh.examenes_vencidos > 0 && (
                            <span className="flex items-center gap-1 font-medium text-red-400">
                                <AlertTriangle size={12} /> Vencidos: {kpis.rrhh.examenes_vencidos}
                            </span>
                        )}
                    </div>
                </div>

                {/* KPI Logística Cadena de Suministro */}
                <div className="flex flex-col justify-between rounded-lg border border-zinc-800 bg-[#0f0f13] p-5">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Cadena Logística</span>
                        <div className="rounded-md bg-zinc-900 border border-zinc-800 p-2 text-blue-400">
                            <Truck size={16} />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <p className="text-3xl font-bold tabular-nums text-zinc-100 tracking-tight">{kpis.logistica.envios_en_transito}</p>
                            <span className="text-[12px] font-medium text-zinc-400">envíos en ruta</span>
                        </div>
                        <p className="text-[12px] text-zinc-400">Trujillo ↔ Pataz</p>
                    </div>
                    <div className="mt-4 flex items-center justify-between border-t border-zinc-800 pt-3 text-[11px]">
                        <span className="text-zinc-400">Req. pendientes: <strong className="text-zinc-200">{kpis.logistica.requerimientos_pendientes}</strong></span>
                        {kpis.logistica.envios_retrasados > 0 ? (
                            <span className="flex items-center gap-1 font-medium text-red-400">
                                <AlertOctagon size={12} /> Retrasados: {kpis.logistica.envios_retrasados}
                            </span>
                        ) : (
                            <span className="text-emerald-400 font-medium">Sin retrasos</span>
                        )}
                    </div>
                </div>

                {/* KPI Logística Stock Crítico */}
                <div className="flex flex-col justify-between rounded-lg border border-zinc-800 bg-[#0f0f13] p-5">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Stock Crítico</span>
                        <div className="rounded-md bg-zinc-900 border border-zinc-800 p-2 text-zinc-300">
                            <Package size={16} className={kpis.logistica.materiales_stock_critico > 0 ? 'text-amber-400' : 'text-emerald-400'} />
                        </div>
                    </div>
                    <div className="mt-3">
                        <p
                            className={`text-3xl font-bold tabular-nums tracking-tight ${
                                kpis.logistica.materiales_stock_critico > 0 ? 'text-amber-400' : 'text-zinc-100'
                            }`}
                        >
                            {kpis.logistica.materiales_stock_critico}
                        </p>
                        <p className="text-[12px] text-zinc-400">Ítems bajo stock mínimo de seguridad</p>
                    </div>
                    <div className="mt-4 border-t border-zinc-800 pt-3 text-[11px]">
                        {kpis.logistica.materiales_stock_critico > 0 ? (
                            <span className="font-medium text-amber-400 flex items-center gap-1">
                                <ShieldAlert size={12} /> Requiere reposición inmediata
                            </span>
                        ) : (
                            <span className="text-emerald-400 font-medium flex items-center gap-1">
                                <CheckCircle size={12} /> Niveles de inventario óptimos
                            </span>
                        )}
                    </div>
                </div>
            </div>

            {/* SECCIÓN 2: KPIs FINANCIEROS DEL MES */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-5">
                    <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                        <span>Ingresos ({kpis.periodo.mes_nombre})</span>
                        <ArrowUpRight size={16} className="text-emerald-400" />
                    </div>
                    <p className="mt-2 text-2xl font-bold tabular-nums text-emerald-400 tracking-tight">{formatearMoneda(kpis.finanzas.total_ingresos_mes)}</p>
                    <p className="text-[12px] text-zinc-400 mt-1">Cobros percibidos en el período</p>
                </div>

                <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-5">
                    <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                        <span>Egresos ({kpis.periodo.mes_nombre})</span>
                        <ArrowDownRight size={16} className="text-red-400" />
                    </div>
                    <p className="mt-2 text-2xl font-bold tabular-nums text-red-400 tracking-tight">{formatearMoneda(kpis.finanzas.total_egresos_mes)}</p>
                    <p className="text-[12px] text-zinc-400 mt-1">Costos y gastos operacionales</p>
                </div>

                <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-5">
                    <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                        <span>Saldo Neto del Mes</span>
                        <TrendingUp size={16} className="text-blue-400" />
                    </div>
                    <p
                        className={`mt-2 text-2xl font-bold tabular-nums tracking-tight ${
                            kpis.finanzas.saldo_neto_mes >= 0 ? 'text-zinc-100' : 'text-red-400'
                        }`}
                    >
                        {formatearMoneda(kpis.finanzas.saldo_neto_mes)}
                    </p>
                    <p className="text-[12px] text-zinc-400 mt-1">Flujo operativo mensual</p>
                </div>

                <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-5">
                    <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                        <span>Ejecución Presupuestal</span>
                        <DollarSign size={16} className="text-amber-400" />
                    </div>
                    <div className="mt-2 flex items-baseline gap-2">
                        <p className="text-2xl font-bold tabular-nums text-zinc-100 tracking-tight">{kpis.finanzas.porcentaje_ejecucion}%</p>
                        <span className="text-[12px] text-zinc-400">de {formatearMoneda(kpis.finanzas.presupuesto_total_mes)}</span>
                    </div>
                    <div className="mt-2 h-2 w-full rounded-full bg-zinc-900 border border-zinc-800 overflow-hidden">
                        <div
                            className={`h-full rounded-full ${
                                kpis.finanzas.porcentaje_ejecucion > 100
                                    ? 'bg-red-500'
                                    : kpis.finanzas.porcentaje_ejecucion > 85
                                      ? 'bg-amber-500'
                                      : 'bg-blue-500'
                            }`}
                            style={{ width: `${Math.min(kpis.finanzas.porcentaje_ejecucion, 100)}%` }}
                        />
                    </div>
                </div>
            </div>

            {/* SECCIÓN 3: GRÁFICOS ESTADÍSTICOS INTERACTIVOS */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Gráfico 1: Evolución Mensual Ingresos vs Egresos */}
                <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-5">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h3 className="text-base font-semibold text-zinc-100">Evolución de Ingresos y Egresos ({filtros.anio})</h3>
                            <p className="text-[12px] text-zinc-400">Comparativa mensual de tesorería y gastos</p>
                        </div>
                    </div>
                    <div className="h-72 w-full">
                        <Line
                            data={dataGastoMensual}
                            options={{
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { position: 'top', labels: { color: '#a1a1aa' } },
                                    tooltip: {
                                        callbacks: {
                                            label: (context) => `${context.dataset.label}: S/ ${Number(context.raw).toLocaleString('es-PE')}`,
                                        },
                                    },
                                },
                                scales: {
                                    x: { ticks: { color: '#71717a' }, grid: { color: '#27272a' } },
                                    y: {
                                        ticks: {
                                            color: '#71717a',
                                            callback: (value) => `S/ ${Number(value).toLocaleString('es-PE')}`,
                                        },
                                        grid: { color: '#27272a' },
                                    },
                                },
                            }}
                        />
                    </div>
                </div>

                {/* Gráfico 2: Presupuesto vs Real por Centro de Costo */}
                <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-5">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h3 className="text-base font-semibold text-zinc-100">
                                Presupuesto vs Gasto Real por Centro de Costo ({kpis.periodo.mes_nombre})
                            </h3>
                            <p className="text-[12px] text-zinc-400">Desviación en soles por área imputable</p>
                        </div>
                    </div>
                    <div className="h-72 w-full">
                        <Bar
                            data={dataPresupuestoVsReal}
                            options={{
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { position: 'top', labels: { color: '#a1a1aa' } },
                                    tooltip: {
                                        callbacks: {
                                            label: (context) => `${context.dataset.label}: S/ ${Number(context.raw).toLocaleString('es-PE')}`,
                                        },
                                    },
                                },
                                scales: {
                                    x: { ticks: { color: '#71717a' }, grid: { color: '#27272a' } },
                                    y: {
                                        ticks: {
                                            color: '#71717a',
                                            callback: (value) => `S/ ${Number(value).toLocaleString('es-PE')}`,
                                        },
                                        grid: { color: '#27272a' },
                                    },
                                },
                            }}
                        />
                    </div>
                </div>
            </div>

            {/* SECCIÓN 4: DISTRIBUCIÓN OPERATIVA Y LOGÍSTICA */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Gráfico 3: Envíos por Estado */}
                <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-5">
                    <h3 className="text-base font-semibold text-zinc-100">Estado de Envíos de Suministros</h3>
                    <p className="text-[12px] text-zinc-400 mb-4">Cadena logística Trujillo - Pataz</p>
                    <div className="h-60 w-full flex items-center justify-center">
                        <Doughnut
                            data={dataEnvios}
                            options={{
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { position: 'bottom', labels: { color: '#a1a1aa' } },
                                },
                            }}
                        />
                    </div>
                </div>

                {/* Gráfico 4: Personal por Área */}
                <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-5">
                    <h3 className="text-base font-semibold text-zinc-100">Personal Activo por Área</h3>
                    <p className="text-[12px] text-zinc-400 mb-4">Dotación de fuerza laboral</p>
                    <div className="h-60 w-full">
                        <Bar
                            data={dataPersonalArea}
                            options={{
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                },
                                scales: {
                                    x: { ticks: { color: '#71717a' }, grid: { color: '#27272a' } },
                                    y: { ticks: { color: '#71717a' }, grid: { color: '#27272a' } },
                                },
                            }}
                        />
                    </div>
                </div>

                {/* Gráfico 5: Aptitud Médica */}
                <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-5">
                    <h3 className="text-base font-semibold text-zinc-100">Aptitud Médica para Pataz</h3>
                    <p className="text-[12px] text-zinc-400 mb-4">Habilitación física para ascenso a mina (&gt;3,200 msnm)</p>
                    <div className="h-60 w-full flex items-center justify-center">
                        <Doughnut
                            data={dataAptitud}
                            options={{
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { position: 'bottom', labels: { color: '#a1a1aa' } },
                                },
                            }}
                        />
                    </div>
                </div>
            </div>

            {/* Enlace directo al Hub de Reportes */}
            <div className="flex flex-col items-center justify-between gap-4 rounded-lg border border-zinc-800 bg-[#0f0f13] p-5 sm:flex-row">
                <div className="flex items-center gap-3">
                    <div className="rounded-md bg-zinc-900 border border-zinc-800 p-2 text-zinc-300">
                        <FileSpreadsheet size={20} />
                    </div>
                    <div>
                        <h4 className="text-[13px] font-semibold text-zinc-100">Centro Unificado de Reportes y Exportaciones</h4>
                        <p className="text-[12px] text-zinc-400">
                            Accede a la descarga de reportes detallados en Excel (.xlsx) y PDF de RR.HH., Logística, Finanzas y Gerencia.
                        </p>
                    </div>
                </div>
                <a
                    href="/reportes"
                    className="inline-flex shrink-0 items-center gap-2 rounded-md bg-zinc-100 px-4 py-2 text-[13px] font-medium text-zinc-900 hover:bg-white transition-colors"
                >
                    Ir al Hub de Reportes
                </a>
            </div>
        </GerenciaLayout>
    );
}
