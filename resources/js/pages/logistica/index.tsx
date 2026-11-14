import DataTableCard, { DataTableEmpty, DataTableTbody, DataTableTd, DataTableTh, DataTableThead, DataTableTr } from '@/components/data-table-card';
import KpiCard from '@/components/kpi-card';
import StatusBadge from '@/components/status-badge';
import LogisticaLayout from '@/layouts/logistica/logistica-layout';
import { type BreadcrumbItem } from '@/types';
import { Link } from '@inertiajs/react';
import { AlertTriangle, Boxes, ClipboardList, Clock, Package, Truck, Users2 } from 'lucide-react';

interface StockAlertaItem {
    id: number;
    cantidad: string;
    almacen: { id: number; nombre: string; sede?: { nombre: string } | null };
    material: {
        id: number;
        codigo: string;
        nombre: string;
        stock_minimo: string;
        unidad_medida?: { abreviatura: string } | null;
    };
}

interface EnvioTransitoItem {
    id: number;
    codigo: string;
    origen: string | null;
    destino: string | null;
    fecha_envio: string | null;
    fecha_estimada_llegada: string | null;
    retrasado: boolean;
    requerimiento_codigo: string | null;
}

interface RequerimientoPendienteItem {
    id: number;
    codigo: string;
    fecha_solicitud: string;
    prioridad: string;
    estado: string;
    area?: { nombre: string } | null;
    sede?: { nombre: string } | null;
    solicitante?: { name: string } | null;
}

interface LogisticaIndexProps {
    kpis: {
        alertas_stock_bajo: number;
        envios_en_transito: number;
        envios_retrasados: number;
        requerimientos_pendientes: number;
        materiales_activos: number;
        proveedores_activos: number;
    };
    alertasStock: StockAlertaItem[];
    enviosEnTransito: EnvioTransitoItem[];
    requerimientosPendientes: RequerimientoPendienteItem[];
    esRecepcionPataz: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Despachos y Cadena', href: '/logistica' },
];

export default function LogisticaIndex({
    kpis,
    alertasStock,
    enviosEnTransito,
    requerimientosPendientes,
    esRecepcionPataz,
}: LogisticaIndexProps) {
    return (
        <LogisticaLayout
            breadcrumbs={breadcrumbs}
            titulo="Cadena de Suministro y Despachos"
            descripcion={
                esRecepcionPataz
                    ? 'Control de stock en campamento Pataz, recepción de despachos, consumos y requerimientos de mina.'
                    : 'Gestión integral de materiales, proveedores, órdenes de compra, stock multi-almacén Trujillo/Pataz y despachos.'
            }
        >
            {/* 4 KPIs tipo Zinc */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <KpiCard
                    title="Alertas Stock Crítico"
                    value={kpis.alertas_stock_bajo}
                    variant={kpis.alertas_stock_bajo > 0 ? 'alert' : 'default'}
                    icon={AlertTriangle}
                    action={{
                        label: 'Ver críticos',
                        href: '/logistica/stock?bajo_minimo=1',
                    }}
                />

                <KpiCard
                    title="Envíos en Tránsito"
                    value={kpis.envios_en_transito}
                    icon={Truck}
                    badge={kpis.envios_retrasados > 0 ? `${kpis.envios_retrasados} retrasado(s)` : 'En ruta'}
                    action={{
                        label: 'Ir a envíos',
                        href: '/logistica/envios?estado=EN_TRANSITO',
                    }}
                />

                <KpiCard
                    title="Requerimientos Activos"
                    value={kpis.requerimientos_pendientes}
                    icon={ClipboardList}
                    action={{
                        label: 'Gestionar',
                        href: '/logistica/requerimientos',
                    }}
                />

                <KpiCard
                    title="Catálogo Activo"
                    value={kpis.materiales_activos}
                    icon={Package}
                    badge={`${kpis.proveedores_activos} proveedores`}
                />
            </div>

            {/* Tablas lado a lado */}
            <div className="grid gap-6 lg:grid-cols-2">
                <DataTableCard
                    title="Alertas de Stock Bajo Mínimo"
                    description="Materiales cuyo saldo actual iguala o está por debajo del umbral de seguridad."
                    count={alertasStock.length}
                >
                    <table className="w-full text-left text-sm">
                        <DataTableThead>
                            <tr>
                                <DataTableTh>Material</DataTableTh>
                                <DataTableTh>Almacén</DataTableTh>
                                <DataTableTh className="text-right">Stock</DataTableTh>
                                <DataTableTh className="text-right">Mínimo</DataTableTh>
                            </tr>
                        </DataTableThead>
                        <DataTableTbody>
                            {alertasStock.length === 0 ? (
                                <DataTableEmpty colSpan={4} mensaje="No hay materiales bajo stock mínimo en este momento." />
                            ) : (
                                alertasStock.map((item) => (
                                    <DataTableTr key={item.id}>
                                        <DataTableTd>
                                            <div className="font-medium text-zinc-200">{item.material.nombre}</div>
                                            <div className="text-xs text-zinc-500 font-mono">{item.material.codigo}</div>
                                        </DataTableTd>
                                        <DataTableTd className="text-zinc-400">{item.almacen.nombre}</DataTableTd>
                                        <DataTableTd className="text-right font-semibold tabular-nums text-rose-400">
                                            {Number(item.cantidad).toFixed(2)} {item.material.unidad_medida?.abreviatura ?? ''}
                                        </DataTableTd>
                                        <DataTableTd className="text-right tabular-nums text-zinc-400">
                                            {Number(item.material.stock_minimo).toFixed(2)}
                                        </DataTableTd>
                                    </DataTableTr>
                                ))
                            )}
                        </DataTableTbody>
                    </table>
                </DataTableCard>

                <DataTableCard
                    title="Envíos en Tránsito y Seguimiento"
                    description="Despachos pendientes de confirmación de llegada en almacén destino."
                    count={enviosEnTransito.length}
                >
                    <table className="w-full text-left text-sm">
                        <DataTableThead>
                            <tr>
                                <DataTableTh>Código</DataTableTh>
                                <DataTableTh>Ruta</DataTableTh>
                                <DataTableTh>Llegada Est.</DataTableTh>
                                <DataTableTh className="text-right">Acción</DataTableTh>
                            </tr>
                        </DataTableThead>
                        <DataTableTbody>
                            {enviosEnTransito.length === 0 ? (
                                <DataTableEmpty colSpan={4} mensaje="No hay envíos en tránsito actualmente." />
                            ) : (
                                enviosEnTransito.map((envio) => (
                                    <DataTableTr key={envio.id}>
                                        <DataTableTd className="font-medium text-zinc-200">
                                            <div>{envio.codigo}</div>
                                            {envio.requerimiento_codigo && (
                                                <div className="text-xs text-zinc-500">{envio.requerimiento_codigo}</div>
                                            )}
                                        </DataTableTd>
                                        <DataTableTd className="text-xs text-zinc-300">
                                            {envio.origen} → {envio.destino}
                                        </DataTableTd>
                                        <DataTableTd>
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs tabular-nums text-zinc-300">
                                                    {envio.fecha_estimada_llegada ?? 'Sin fecha'}
                                                </span>
                                                {envio.retrasado && (
                                                    <StatusBadge status="RETRASADO" label="Retrasado" />
                                                )}
                                            </div>
                                        </DataTableTd>
                                        <DataTableTd className="text-right">
                                            <Link
                                                href={`/logistica/envios/${envio.id}`}
                                                className="text-xs font-medium text-zinc-300 hover:text-white hover:underline transition-colors"
                                            >
                                                Ver detalle
                                            </Link>
                                        </DataTableTd>
                                    </DataTableTr>
                                ))
                            )}
                        </DataTableTbody>
                    </table>
                </DataTableCard>
            </div>

            {/* Requerimientos pendientes */}
            <DataTableCard
                title="Requerimientos Pendientes de Aprobación o Atención"
                description="Últimas solicitudes en curso para las operaciones Trujillo / Pataz."
                count={requerimientosPendientes.length}
                actions={
                    <Link
                        href="/logistica/requerimientos"
                        className="text-xs font-medium text-zinc-400 hover:text-zinc-200 transition-colors"
                    >
                        Ver todos
                    </Link>
                }
            >
                <table className="w-full text-left text-sm">
                    <DataTableThead>
                        <tr>
                            <DataTableTh>Código</DataTableTh>
                            <DataTableTh>Área / Sede</DataTableTh>
                            <DataTableTh>Solicitante</DataTableTh>
                            <DataTableTh>Prioridad</DataTableTh>
                            <DataTableTh>Estado</DataTableTh>
                        </tr>
                    </DataTableThead>
                    <DataTableTbody>
                        {requerimientosPendientes.length === 0 ? (
                            <DataTableEmpty colSpan={5} mensaje="No existen requerimientos pendientes." />
                        ) : (
                            requerimientosPendientes.map((req) => (
                                <DataTableTr key={req.id}>
                                    <DataTableTd className="font-semibold text-zinc-200 font-mono">{req.codigo}</DataTableTd>
                                    <DataTableTd className="text-zinc-300">
                                        {req.area?.nombre ?? '—'} · {req.sede?.nombre ?? '—'}
                                    </DataTableTd>
                                    <DataTableTd className="text-zinc-400">{req.solicitante?.name ?? '—'}</DataTableTd>
                                    <DataTableTd>
                                        <StatusBadge status={req.prioridad} label={req.prioridad} />
                                    </DataTableTd>
                                    <DataTableTd>
                                        <StatusBadge status={req.estado} label={req.estado} />
                                    </DataTableTd>
                                </DataTableTr>
                            ))
                        )}
                    </DataTableTbody>
                </table>
            </DataTableCard>
        </LogisticaLayout>
    );
}
