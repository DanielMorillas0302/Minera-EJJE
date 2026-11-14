import InputError from '@/components/input-error';
import { Paginacion, type Paginado } from '@/components/paginacion';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RrhhLayout from '@/layouts/rrhh/rrhh-layout';
import { type BreadcrumbItem } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Plus, Search, X } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface MovimientosProps {
    movimientos: Paginado<{
        id: number;
        trabajador_id: number;
        trabajador_dni: string;
        trabajador_nombre: string;
        cargo_nombre: string;
        tipo: string;
        tipo_etiqueta: string;
        fecha_hora: string;
        origen: string;
        destino: string;
        transporte: string | null;
        observaciones: string | null;
        registrado_por: string;
    }>;
    filtros: { q: string; tipo: string; trabajador_id: string; desde: string; hasta: string };
    trabajadores: Array<{
        id: number;
        label: string;
        ubicacion_actual: string;
        ubicacion_etiqueta: string;
        apto_viaje: boolean;
        aptitud_motivo: string;
    }>;
    sedes: Array<{ id: number; nombre: string; tipo: string }>;
    tipos: Array<{ value: string; label: string }>;
    puedeCrear: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'RR.HH.', href: '/rrhh' },
    { title: 'Movimientos Trujillo ↔ Pataz', href: '/rrhh/movimientos' },
];

export default function MovimientosPage({
    movimientos,
    filtros,
    trabajadores,
    sedes,
    tipos,
    puedeCrear,
}: MovimientosProps) {
    const [q, setQ] = useState(filtros.q);
    const [tipoFiltro, setTipoFiltro] = useState(filtros.tipo);
    const [desde, setDesde] = useState(filtros.desde);
    const [hasta, setHasta] = useState(filtros.hasta);
    const [modalAbierto, setModalAbierto] = useState(false);

    const sedeTrujillo = sedes.find((s) => s.nombre.toLowerCase().includes('trujillo')) ?? sedes[0];
    const sedePataz = sedes.find((s) => s.nombre.toLowerCase().includes('pataz')) ?? sedes[sedes.length - 1];

    const form = useForm({
        trabajador_id: trabajadores[0] ? String(trabajadores[0].id) : '',
        tipo: 'SALIDA_A_PATAZ',
        fecha_hora: new Date().toISOString().slice(0, 16),
        origen_sede_id: sedeTrujillo ? String(sedeTrujillo.id) : '',
        destino_sede_id: sedePataz ? String(sedePataz.id) : '',
        transporte: '',
        observaciones: '',
    });

    const trabajadorElegido = trabajadores.find((t) => String(t.id) === form.data.trabajador_id);

    const aplicarFiltros = (e?: FormEvent) => {
        e?.preventDefault();
        router.get('/rrhh/movimientos', { q, tipo: tipoFiltro, desde, hasta }, { preserveState: true, replace: true });
    };

    const cambiarTipoMovimiento = (nuevoTipo: string) => {
        form.setData((prev) => ({
            ...prev,
            tipo: nuevoTipo,
            origen_sede_id:
                nuevoTipo === 'SALIDA_A_PATAZ'
                    ? String(sedeTrujillo?.id ?? prev.origen_sede_id)
                    : nuevoTipo === 'RETORNO_A_TRUJILLO'
                      ? String(sedePataz?.id ?? prev.origen_sede_id)
                      : prev.origen_sede_id,
            destino_sede_id:
                nuevoTipo === 'SALIDA_A_PATAZ'
                    ? String(sedePataz?.id ?? prev.destino_sede_id)
                    : nuevoTipo === 'RETORNO_A_TRUJILLO'
                      ? String(sedeTrujillo?.id ?? prev.destino_sede_id)
                      : prev.destino_sede_id,
        }));
    };

    const registrar = (e: FormEvent) => {
        e.preventDefault();
        form.post('/rrhh/movimientos', {
            preserveScroll: true,
            onSuccess: () => setModalAbierto(false),
        });
    };

    return (
        <RrhhLayout
            titulo="Movimientos de Personal Trujillo ↔ Pataz"
            descripcion="Control de ingresos a mina (con bloqueo obligatorio por aptitud médica) y retornos a Trujillo."
            breadcrumbs={breadcrumbs}
            acciones={
                puedeCrear && (
                    <Button type="button" onClick={() => setModalAbierto(true)} className="min-h-9 bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                        <Plus className="mr-2 h-4 w-4" />
                        Registrar movimiento
                    </Button>
                )
            }
        >
            <Card className="border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-4">
                    <form onSubmit={aplicarFiltros} className="grid gap-3 md:grid-cols-6">
                        <div className="relative md:col-span-2">
                            <Search className="pointer-events-none absolute top-3 left-3 h-4 w-4 text-zinc-500" />
                            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Trabajador, DNI o transporte..." className="min-h-10 pl-9 bg-zinc-900 border-zinc-800 text-zinc-200" />
                        </div>
                        <select
                            value={tipoFiltro}
                            onChange={(e) => setTipoFiltro(e.target.value)}
                            className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                        >
                            <option value="">Todos los tipos</option>
                            {tipos.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                        <Input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} className="min-h-10 bg-zinc-900 border-zinc-800 text-zinc-200" />
                        <Input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} className="min-h-10 bg-zinc-900 border-zinc-800 text-zinc-200" />
                        <div className="flex gap-2">
                            <Button type="submit" variant="secondary" className="min-h-10 flex-1 border border-zinc-800 bg-zinc-900 text-zinc-200 hover:bg-zinc-800">
                                Filtrar
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => router.get('/rrhh/movimientos')}
                                className="min-h-10 px-3 border-zinc-800 text-zinc-400 hover:text-white"
                                title="Limpiar"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card className="border-zinc-800 bg-[#0f0f13]">
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">
                                <tr>
                                    <th className="px-4 py-3">Fecha y hora</th>
                                    <th className="px-4 py-3">Trabajador</th>
                                    <th className="px-4 py-3">Tipo</th>
                                    <th className="px-4 py-3">Origen → Destino</th>
                                    <th className="px-4 py-3">Transporte</th>
                                    <th className="px-4 py-3">Registrado por</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {movimientos.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-10 text-center text-zinc-500">
                                            No se encontraron movimientos con los filtros actuales.
                                        </td>
                                    </tr>
                                ) : (
                                    movimientos.data.map((m) => (
                                        <tr key={m.id} className="hover:bg-zinc-900/40 transition-colors">
                                            <td className="px-4 py-3 whitespace-nowrap tabular-nums text-zinc-400">{m.fecha_hora}</td>
                                            <td className="px-4 py-3">
                                                <Link href={`/rrhh/trabajadores/${m.trabajador_id}`} className="font-medium text-zinc-200 hover:text-white hover:underline transition-colors">
                                                    {m.trabajador_nombre}
                                                </Link>
                                                <div className="text-xs text-zinc-500">
                                                    DNI {m.trabajador_dni} · {m.cargo_nombre}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={m.tipo} label={m.tipo_etiqueta} />
                                            </td>
                                            <td className="px-4 py-3 font-medium text-zinc-300">
                                                {m.origen} → {m.destino}
                                            </td>
                                            <td className="px-4 py-3 text-zinc-400">{m.transporte ?? '—'}</td>
                                            <td className="px-4 py-3 text-zinc-400">{m.registrado_por}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Paginacion datos={movimientos} />
                </CardContent>
            </Card>

            <Dialog open={modalAbierto} onOpenChange={setModalAbierto}>
                <DialogContent className="bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle>Registrar traslado de personal</DialogTitle>
                        <DialogDescription>Las salidas hacia Pataz exigen examen ocupacional APTO vigente.</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={registrar} className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="mv-trab">Trabajador</Label>
                            <select
                                id="mv-trab"
                                value={form.data.trabajador_id}
                                onChange={(e) => form.setData('trabajador_id', e.target.value)}
                                className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                {trabajadores.map((t) => (
                                    <option key={t.id} value={String(t.id)}>
                                        {t.label} · [{t.ubicacion_etiqueta}] {t.apto_viaje ? '✓ Apto' : '✗ No apto'}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.trabajador_id} />
                        </div>

                        {trabajadorElegido && (
                            <div
                                className={`flex items-start gap-2 rounded-md border p-3 text-xs ${
                                    trabajadorElegido.apto_viaje
                                        ? 'border-emerald-900/60 bg-emerald-950/40 text-emerald-400'
                                        : 'border-rose-900/60 bg-rose-950/40 text-rose-400'
                                }`}
                            >
                                {trabajadorElegido.apto_viaje ? (
                                    <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" />
                                ) : (
                                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-rose-400" />
                                )}
                                <span>
                                    Ubicación actual: <strong>{trabajadorElegido.ubicacion_etiqueta}</strong> · {trabajadorElegido.aptitud_motivo}
                                </span>
                            </div>
                        )}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="mv-tipo">Tipo de movimiento</Label>
                                <select
                                    id="mv-tipo"
                                    value={form.data.tipo}
                                    onChange={(e) => cambiarTipoMovimiento(e.target.value)}
                                    className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {tipos.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="mv-fh">Fecha y hora</Label>
                                <Input
                                    id="mv-fh"
                                    type="datetime-local"
                                    value={form.data.fecha_hora}
                                    onChange={(e) => form.setData('fecha_hora', e.target.value)}
                                    required
                                />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="mv-orig">Sede origen</Label>
                                <select
                                    id="mv-orig"
                                    value={form.data.origen_sede_id}
                                    onChange={(e) => form.setData('origen_sede_id', e.target.value)}
                                    className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {sedes.map((s) => (
                                        <option key={s.id} value={String(s.id)}>
                                            {s.nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="mv-dest">Sede destino</Label>
                                <select
                                    id="mv-dest"
                                    value={form.data.destino_sede_id}
                                    onChange={(e) => form.setData('destino_sede_id', e.target.value)}
                                    className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {sedes.map((s) => (
                                        <option key={s.id} value={String(s.id)}>
                                            {s.nombre}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.destino_sede_id} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="mv-transp">Transporte</Label>
                                <Input
                                    id="mv-transp"
                                    value={form.data.transporte}
                                    onChange={(e) => form.setData('transporte', e.target.value)}
                                    placeholder="Bus / Camioneta"
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="mv-obs">Observaciones</Label>
                                <Input
                                    id="mv-obs"
                                    value={form.data.observaciones}
                                    onChange={(e) => form.setData('observaciones', e.target.value)}
                                />
                            </div>
                        </div>

                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button type="button" variant="outline" onClick={() => setModalAbierto(false)} className="min-h-9 border-zinc-800 text-zinc-400 hover:text-white">
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing} className="min-h-9 bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                                {form.processing ? 'Registrando...' : 'Registrar movimiento'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </RrhhLayout>
    );
}

