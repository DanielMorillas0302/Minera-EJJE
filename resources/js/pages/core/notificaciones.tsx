import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertOctagon,
    AlertTriangle,
    Bell,
    Check,
    CheckCheck,
    ExternalLink,
    Info,
} from 'lucide-react';

interface NotificacionItem {
    id: number;
    tipo: string;
    modulo: string;
    nivel: string;
    titulo: string;
    mensaje: string;
    url_accion?: string | null;
    leida: boolean;
    creado_en: string;
}

interface Props {
    notificaciones: NotificacionItem[];
    totalNoLeidas: number;
    filtros: {
        solo_no_leidas: boolean;
    };
}

export default function NotificacionesPage({ notificaciones, totalNoLeidas, filtros }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Resumen Ejecutivo', href: '/dashboard' },
        { title: 'Bandeja de Alertas', href: '/notificaciones' },
    ];

    const marcarLeida = (id: number) => {
        router.post(`/notificaciones/${id}/leer`, {}, { preserveScroll: true });
    };

    const marcarTodasLeidas = () => {
        router.post('/notificaciones/leer-todas', {}, { preserveScroll: true });
    };

    const toggleFiltroNoLeidas = (soloNoLeidas: boolean) => {
        router.get(
            '/notificaciones',
            { solo_no_leidas: soloNoLeidas ? '1' : undefined },
            { preserveState: true, preserveScroll: true },
        );
    };

    const renderIcono = (nivel: string) => {
        switch (nivel) {
            case 'URGENTE':
                return <AlertOctagon size={16} className="text-red-500" />;
            case 'ADVERTENCIA':
                return <AlertTriangle size={16} className="text-amber-500" />;
            default:
                return <Info size={16} className="text-blue-400" />;
        }
    };

    const badgeNivel = (nivel: string) => {
        switch (nivel) {
            case 'URGENTE':
                return <Badge className="border border-red-900/50 bg-red-950/40 text-red-400 text-[11px]">Urgente</Badge>;
            case 'ADVERTENCIA':
                return <Badge className="border border-amber-900/50 bg-amber-950/40 text-amber-400 text-[11px]">Advertencia</Badge>;
            default:
                return <Badge className="border border-zinc-800 bg-zinc-900 text-zinc-400 text-[11px]">Informativa</Badge>;
        }
    };

    const badgeModulo = (modulo: string) => {
        return (
            <span className="rounded border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-medium uppercase text-zinc-400">
                {modulo}
            </span>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bandeja de Alertas" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 bg-[#09090b] p-6 md:p-8">
                <div className="flex flex-col gap-2 border-b border-zinc-800 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight text-zinc-100">
                            Bandeja de Alertas
                        </h1>
                        <p className="text-[13px] leading-relaxed text-zinc-400">
                            Supervisa alertas de stock crítico, vencimientos médicos EMO, envíos en ruta y seguridad operacional.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {totalNoLeidas > 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={marcarTodasLeidas}
                                className="border border-zinc-800 bg-zinc-900 text-xs font-medium text-zinc-300 hover:bg-zinc-800 hover:text-white"
                            >
                                <CheckCheck size={14} className="mr-1.5 text-zinc-400" />
                                Marcar todas como leídas
                            </Button>
                        )}
                    </div>
                </div>

                {/* Filtros de estado */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Button
                            variant={filtros.solo_no_leidas ? 'ghost' : 'secondary'}
                            size="sm"
                            onClick={() => toggleFiltroNoLeidas(false)}
                            className={`text-xs ${
                                !filtros.solo_no_leidas
                                    ? 'border border-zinc-700 bg-zinc-900 text-zinc-100'
                                    : 'border border-zinc-800 bg-[#0f0f13] text-zinc-400'
                            }`}
                        >
                            Todas ({notificaciones.length})
                        </Button>
                        <Button
                            variant={filtros.solo_no_leidas ? 'secondary' : 'ghost'}
                            size="sm"
                            onClick={() => toggleFiltroNoLeidas(true)}
                            className={`text-xs ${
                                filtros.solo_no_leidas
                                    ? 'border border-zinc-700 bg-zinc-900 text-zinc-100'
                                    : 'border border-zinc-800 bg-[#0f0f13] text-zinc-400'
                            }`}
                        >
                            Solo no leídas ({totalNoLeidas})
                        </Button>
                    </div>
                </div>

                {/* Lista de Notificaciones */}
                <div className="space-y-3">
                    {notificaciones.length === 0 ? (
                        <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-12 text-center">
                            <Bell size={24} className="mx-auto mb-2 text-zinc-600" />
                            <h3 className="text-[14px] font-medium text-zinc-300">No hay notificaciones pendientes</h3>
                            <p className="mt-1 text-[13px] text-zinc-500">
                                El sistema no ha generado alertas operativas para tu usuario o rol en este momento.
                            </p>
                        </div>
                    ) : (
                        notificaciones.map((n) => (
                            <Card
                                key={n.id}
                                className={`rounded-lg border bg-[#0f0f13] transition-colors ${
                                    n.leida
                                        ? 'border-zinc-800 opacity-75'
                                        : 'border-zinc-700'
                                }`}
                            >
                                <CardContent className="flex items-start gap-3.5 p-4">
                                    <div className="mt-0.5 shrink-0 rounded-md border border-zinc-800 bg-zinc-900 p-2">
                                        {renderIcono(n.nivel)}
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <div className="mb-1.5 flex flex-wrap items-center gap-2">
                                            {badgeModulo(n.modulo)}
                                            {badgeNivel(n.nivel)}
                                            <span className="truncate text-[13px] font-medium text-zinc-200">
                                                {n.titulo}
                                            </span>
                                            {!n.leida && (
                                                <span className="size-1.5 shrink-0 rounded-full bg-emerald-500 animate-pulse" title="No leída" />
                                            )}
                                        </div>

                                        <p className="text-[13px] leading-relaxed text-zinc-400">{n.mensaje}</p>

                                        <div className="mt-3 flex items-center justify-between border-t border-zinc-800/80 pt-2 text-[11px] text-zinc-500">
                                            <span className="tabular-nums">{n.creado_en}</span>

                                            <div className="flex items-center gap-2">
                                                {n.url_accion && (
                                                    <Link
                                                        href={n.url_accion}
                                                        className="inline-flex items-center gap-1 text-[12px] font-medium text-blue-400 hover:underline"
                                                    >
                                                        <span>Ver detalle</span>
                                                        <ExternalLink size={12} />
                                                    </Link>
                                                )}

                                                {!n.leida && (
                                                    <button
                                                        type="button"
                                                        onClick={() => marcarLeida(n.id)}
                                                        className="inline-flex items-center gap-1 text-[12px] text-zinc-400 transition-colors hover:text-zinc-200"
                                                    >
                                                        <Check size={12} />
                                                        <span>Marcar leída</span>
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
