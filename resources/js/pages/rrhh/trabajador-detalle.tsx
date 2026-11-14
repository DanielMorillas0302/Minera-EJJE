import InputError from '@/components/input-error';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RrhhLayout from '@/layouts/rrhh/rrhh-layout';
import { type BreadcrumbItem } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowLeftRight,
    CalendarCheck2,
    CheckCircle2,
    Download,
    HeartPulse,
    MapPin,
    ShieldCheck,
    User,
    XCircle,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface TrabajadorDetalleProps {
    trabajador: {
        id: number;
        dni: string;
        nombres: string;
        apellidos: string;
        nombre_completo: string;
        fecha_nacimiento: string | null;
        sexo_etiqueta: string | null;
        telefono: string | null;
        email: string | null;
        direccion: string | null;
        contacto_emergencia_nombre: string | null;
        contacto_emergencia_telefono: string | null;
        cargo_nombre: string | null;
        area_nombre: string | null;
        sede_base_id: number;
        sede_nombre: string | null;
        fecha_ingreso: string | null;
        fecha_cese: string | null;
        estado: string;
        estado_etiqueta: string;
        ubicacion_actual: string;
        ubicacion_etiqueta: string;
        esquema_rotacion: string | null;
        foto_url: string | null;
        activo: boolean;
        aptitud_visible: boolean;
        apto_viaje: boolean | null;
        aptitud_motivo: string | null;
    };
    examenes: Array<{
        id: number;
        tipo_etiqueta: string;
        fecha_examen_fmt: string;
        fecha_vencimiento_fmt: string;
        vigente: boolean;
        dias_para_vencer: number;
        clinica: string;
        resultado: string | null;
        resultado_etiqueta: string;
        observaciones: string | null;
        adjuntos: Array<{ id: number; nombre: string; tamano: number | null }>;
    }>;
    periodos: Array<{
        id: number;
        anio: number;
        dias_ganados: number;
        dias_tomados: number;
        saldo: number;
    }>;
    vacaciones: Array<{
        id: number;
        anio: number | null;
        fecha_inicio: string;
        fecha_fin: string;
        dias: number;
        estado: string;
        estado_etiqueta: string;
        solicitado_por: string;
        aprobado_por: string;
        comentario: string | null;
    }>;
    permisos: Array<{
        id: number;
        tipo_etiqueta: string;
        con_goce: boolean;
        fecha_inicio: string;
        fecha_fin: string;
        motivo: string;
        estado: string;
        estado_etiqueta: string;
        aprobado_por: string;
    }>;
    movimientos: Array<{
        id: number;
        tipo_etiqueta: string;
        fecha_hora: string;
        origen: string;
        destino: string;
        transporte: string | null;
        observaciones: string | null;
        registrado_por: string;
    }>;
    sedes: Array<{ id: number; nombre: string; tipo: string }>;
    permisosModulo: {
        examenes: boolean;
        medico_detalle: boolean;
        vacaciones: boolean;
        movimientos: boolean;
        crear_movimiento: boolean;
        crear_examen: boolean;
    };
}

type PestanaDetalle = 'datos' | 'examenes' | 'vacaciones' | 'permisos' | 'movimientos';

export default function TrabajadorDetallePage({
    trabajador,
    examenes,
    periodos,
    vacaciones,
    permisos,
    movimientos,
    sedes,
    permisosModulo,
}: TrabajadorDetalleProps) {
    const [tab, setTab] = useState<PestanaDetalle>('datos');
    const [modalMovimiento, setModalMovimiento] = useState(false);

    const sedeTrujillo = sedes.find((s) => s.nombre.toLowerCase().includes('trujillo')) ?? sedes[0];
    const sedePataz = sedes.find((s) => s.nombre.toLowerCase().includes('pataz')) ?? sedes[sedes.length - 1];

    const enTrujillo = trabajador.ubicacion_actual !== 'PATAZ';

    const formMov = useForm({
        trabajador_id: trabajador.id,
        tipo: enTrujillo ? 'SALIDA_A_PATAZ' : 'RETORNO_A_TRUJILLO',
        fecha_hora: new Date().toISOString().slice(0, 16),
        origen_sede_id: enTrujillo ? String(sedeTrujillo?.id ?? '') : String(sedePataz?.id ?? ''),
        destino_sede_id: enTrujillo ? String(sedePataz?.id ?? '') : String(sedeTrujillo?.id ?? ''),
        transporte: '',
        observaciones: '',
    });

    const abrirMovimientoRapido = (tipoMov: 'SALIDA_A_PATAZ' | 'RETORNO_A_TRUJILLO') => {
        formMov.clearErrors();
        formMov.setData({
            trabajador_id: trabajador.id,
            tipo: tipoMov,
            fecha_hora: new Date().toISOString().slice(0, 16),
            origen_sede_id: tipoMov === 'SALIDA_A_PATAZ' ? String(sedeTrujillo?.id ?? '') : String(sedePataz?.id ?? ''),
            destino_sede_id: tipoMov === 'SALIDA_A_PATAZ' ? String(sedePataz?.id ?? '') : String(sedeTrujillo?.id ?? ''),
            transporte: '',
            observaciones: '',
        });
        setModalMovimiento(true);
    };

    const registrarMovimiento = (e: FormEvent) => {
        e.preventDefault();
        formMov.post('/rrhh/movimientos', {
            preserveScroll: true,
            onSuccess: () => {
                setModalMovimiento(false);
                setTab('movimientos');
            },
        });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Inicio', href: '/dashboard' },
        { title: 'RR.HH.', href: '/rrhh' },
        { title: 'Trabajadores', href: '/rrhh/trabajadores' },
        { title: trabajador.nombre_completo, href: `/rrhh/trabajadores/${trabajador.id}` },
    ];

    const tabsDisponibles: Array<{ key: PestanaDetalle; label: string; icon: typeof User }> = [
        { key: 'datos', label: 'Datos', icon: User },
        ...(permisosModulo.examenes ? [{ key: 'examenes' as const, label: `Exámenes (${examenes.length})`, icon: HeartPulse }] : []),
        ...(permisosModulo.vacaciones ? [{ key: 'vacaciones' as const, label: `Vacaciones (${vacaciones.length})`, icon: CalendarCheck2 }] : []),
        ...(permisosModulo.vacaciones ? [{ key: 'permisos' as const, label: `Permisos (${permisos.length})`, icon: ShieldCheck }] : []),
        ...(permisosModulo.movimientos ? [{ key: 'movimientos' as const, label: `Movimientos (${movimientos.length})`, icon: ArrowLeftRight }] : []),
    ];

    return (
        <RrhhLayout
            titulo={trabajador.nombre_completo}
            descripcion={`DNI ${trabajador.dni} · ${trabajador.cargo_nombre ?? 'Sin cargo'} · ${trabajador.area_nombre ?? 'Sin área'}`}
            breadcrumbs={breadcrumbs}
            acciones={
                <div className="flex flex-wrap gap-2">
                    <Link href="/rrhh/trabajadores">
                        <Button variant="outline" className="min-h-10">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Volver al padrón
                        </Button>
                    </Link>
                    {permisosModulo.crear_movimiento && (
                        <>
                            <Button
                                type="button"
                                onClick={() => abrirMovimientoRapido('SALIDA_A_PATAZ')}
                                className="min-h-9 bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium"
                            >
                                <ArrowLeftRight className="mr-2 h-4 w-4" />
                                Enviar a Pataz
                            </Button>
                            <Button type="button" variant="secondary" onClick={() => abrirMovimientoRapido('RETORNO_A_TRUJILLO')} className="min-h-9 border border-zinc-800 bg-zinc-900 text-zinc-200 hover:bg-zinc-800 text-[13px]">
                                Retorno a Trujillo
                            </Button>
                        </>
                    )}
                </div>
            }
        >
            <Card className="border-zinc-800 bg-[#0f0f13]">
                <CardContent className="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-4">
                        {trabajador.foto_url ? (
                            <img
                                src={trabajador.foto_url}
                                alt={trabajador.nombre_completo}
                                className="h-16 w-16 rounded-full border border-zinc-800 object-cover"
                            />
                        ) : (
                            <div className="flex h-16 w-16 items-center justify-center rounded-full border border-zinc-800 bg-zinc-900 text-lg font-bold text-zinc-200">
                                {trabajador.nombres.slice(0, 1)}
                                {trabajador.apellidos.slice(0, 1)}
                            </div>
                        )}
                        <div className="space-y-1.5">
                            <div className="flex flex-wrap items-center gap-2">
                                <StatusBadge status={trabajador.estado} label={trabajador.estado_etiqueta} />
                                <StatusBadge status={trabajador.ubicacion_actual} label={`Ubicación: ${trabajador.ubicacion_etiqueta}`} />
                                {trabajador.esquema_rotacion && <Badge variant="outline">Rotación: {trabajador.esquema_rotacion}</Badge>}
                            </div>
                            <p className="text-xs text-zinc-400">
                                Sede base: <strong className="text-zinc-300">{trabajador.sede_nombre ?? '—'}</strong> · Ingreso: <strong className="text-zinc-300">{trabajador.fecha_ingreso ?? '—'}</strong>
                            </p>
                        </div>
                    </div>

                    {trabajador.aptitud_visible && (
                        <div
                            className={`flex max-w-md items-start gap-2.5 rounded-lg border px-4 py-3 ${
                                trabajador.apto_viaje ? 'border-emerald-900/60 bg-emerald-950/40 text-emerald-400' : 'border-rose-900/60 bg-rose-950/40 text-rose-400'
                            }`}
                        >
                            {trabajador.apto_viaje ? (
                                <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" />
                            ) : (
                                <XCircle className="mt-0.5 h-5 w-5 shrink-0 text-rose-400" />
                            )}
                            <div>
                                <div className={`text-sm font-semibold ${trabajador.apto_viaje ? 'text-emerald-400' : 'text-rose-400'}`}>
                                    {trabajador.apto_viaje ? 'Apto para Pataz' : 'No apto'}
                                </div>
                                <p className="text-xs text-zinc-300">{trabajador.aptitud_motivo}</p>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            <div className="flex flex-wrap gap-2 border-b border-zinc-800 pb-2">
                {tabsDisponibles.map((item) => {
                    const Icono = item.icon;
                    const activo = tab === item.key;
                    return (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => setTab(item.key)}
                            className={`inline-flex min-h-9 items-center gap-2 rounded-md px-3.5 py-1.5 text-[13px] font-medium transition-colors ${
                                activo
                                    ? 'border border-zinc-700 bg-zinc-900 text-zinc-100'
                                    : 'border border-transparent text-zinc-400 hover:border-zinc-800 hover:bg-zinc-900/60 hover:text-zinc-200'
                            }`}
                        >
                            <Icono className="h-4 w-4" />
                            <span>{item.label}</span>
                        </button>
                    );
                })}
            </div>

            {tab === 'datos' && (
                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardHeader>
                        <CardTitle className="text-base text-zinc-100">Información personal y laboral</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <DatoCampo label="DNI" valor={trabajador.dni} />
                        <DatoCampo label="Fecha de nacimiento" valor={trabajador.fecha_nacimiento} />
                        <DatoCampo label="Sexo" valor={trabajador.sexo_etiqueta} />
                        <DatoCampo label="Teléfono" valor={trabajador.telefono} />
                        <DatoCampo label="Correo" valor={trabajador.email} />
                        <DatoCampo label="Dirección" valor={trabajador.direccion} />
                        <DatoCampo label="Contacto de emergencia" valor={trabajador.contacto_emergencia_nombre} />
                        <DatoCampo label="Tel. emergencia" valor={trabajador.contacto_emergencia_telefono} />
                        <DatoCampo label="Fecha de cese" valor={trabajador.fecha_cese} />
                    </CardContent>
                </Card>
            )}

            {tab === 'examenes' && permisosModulo.examenes && (
                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="text-base text-zinc-100">Historial de exámenes ocupacionales</CardTitle>
                            <CardDescription>Certificados médicos de ingreso, periódicos y retiro.</CardDescription>
                        </div>
                        {permisosModulo.crear_examen && (
                            <Link href={`/rrhh/examenes?trabajador_id=${trabajador.id}`}>
                                <Button variant="outline" size="sm" className="min-h-9">
                                    Gestionar en Exámenes
                                </Button>
                            </Link>
                        )}
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">
                                    <tr>
                                        <th className="px-4 py-3">Tipo</th>
                                        <th className="px-4 py-3">Fecha examen</th>
                                        <th className="px-4 py-3">Vencimiento</th>
                                        <th className="px-4 py-3">Clínica</th>
                                        <th className="px-4 py-3">Resultado</th>
                                        {permisosModulo.medico_detalle && <th className="px-4 py-3">Adjuntos</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {examenes.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="px-4 py-8 text-center text-zinc-500">
                                                Sin exámenes registrados.
                                            </td>
                                        </tr>
                                    ) : (
                                        examenes.map((ex) => (
                                            <tr key={ex.id}>
                                                <td className="px-4 py-3 font-medium">{ex.tipo_etiqueta}</td>
                                                <td className="px-4 py-3 tabular-nums">{ex.fecha_examen_fmt}</td>
                                                <td className="px-4 py-3 tabular-nums">
                                                    {ex.fecha_vencimiento_fmt}{' '}
                                                    {!ex.vigente && <span className="ml-1 text-xs font-semibold text-rose-400">(Vencido)</span>}
                                                </td>
                                                <td className="px-4 py-3 text-zinc-400">{ex.clinica}</td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge status={ex.resultado} label={ex.resultado_etiqueta} />
                                                </td>
                                                {permisosModulo.medico_detalle && (
                                                    <td className="px-4 py-3">
                                                        {ex.adjuntos.length === 0 ? (
                                                            <span className="text-xs text-zinc-500">Sin archivo</span>
                                                        ) : (
                                                            <div className="flex flex-wrap gap-1.5">
                                                                {ex.adjuntos.map((adj) => (
                                                                    <a
                                                                        key={adj.id}
                                                                        href={`/rrhh/examenes/adjuntos/${adj.id}/descargar`}
                                                                        className="inline-flex items-center gap-1 rounded border border-zinc-800 bg-zinc-900 px-2 py-1 text-xs font-medium text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors"
                                                                    >
                                                                        <Download className="h-3 w-3" />
                                                                        {adj.nombre}
                                                                    </a>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </td>
                                                )}
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}

            {tab === 'vacaciones' && permisosModulo.vacaciones && (
                <div className="grid gap-4">
                    <div className="grid gap-3 sm:grid-cols-3">
                        {periodos.map((p) => (
                            <Card key={p.id} className="border-zinc-800 bg-[#0f0f13]">
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm text-zinc-100">Período {p.anio}</CardTitle>
                                </CardHeader>
                                <CardContent className="text-sm text-zinc-300">
                                    Ganados: <strong>{p.dias_ganados}</strong> · Tomados: <strong>{p.dias_tomados}</strong> · Saldo:{' '}
                                    <strong className="text-emerald-400">{p.saldo} días</strong>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                    <Card className="border-zinc-800 bg-[#0f0f13]">
                        <CardContent className="p-0">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">
                                    <tr>
                                        <th className="px-4 py-3">Período</th>
                                        <th className="px-4 py-3">Rango</th>
                                        <th className="px-4 py-3">Días</th>
                                        <th className="px-4 py-3">Estado</th>
                                        <th className="px-4 py-3">Aprobado por</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {vacaciones.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-4 py-8 text-center text-zinc-500">
                                                Sin solicitudes de vacaciones.
                                            </td>
                                        </tr>
                                    ) : (
                                        vacaciones.map((v) => (
                                            <tr key={v.id}>
                                                <td className="px-4 py-3 font-medium">{v.anio ?? '—'}</td>
                                                <td className="px-4 py-3 tabular-nums">
                                                    {v.fecha_inicio} al {v.fecha_fin}
                                                </td>
                                                <td className="px-4 py-3 font-semibold tabular-nums">{v.dias}</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant="outline">{v.estado_etiqueta}</Badge>
                                                </td>
                                                <td className="px-4 py-3 text-zinc-400">{v.aprobado_por}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>
            )}

            {tab === 'permisos' && permisosModulo.vacaciones && (
                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardContent className="p-0">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">
                                <tr>
                                    <th className="px-4 py-3">Tipo</th>
                                    <th className="px-4 py-3">Rango</th>
                                    <th className="px-4 py-3">Goce</th>
                                    <th className="px-4 py-3">Motivo</th>
                                    <th className="px-4 py-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {permisos.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-8 text-center text-zinc-500">
                                            Sin permisos registrados.
                                        </td>
                                    </tr>
                                ) : (
                                    permisos.map((p) => (
                                        <tr key={p.id}>
                                            <td className="px-4 py-3 font-medium">{p.tipo_etiqueta}</td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {p.fecha_inicio} al {p.fecha_fin}
                                            </td>
                                            <td className="px-4 py-3">{p.con_goce ? 'Con goce' : 'Sin goce'}</td>
                                            <td className="px-4 py-3 text-zinc-400">{p.motivo}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant="outline">{p.estado_etiqueta}</Badge>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            )}

            {tab === 'movimientos' && permisosModulo.movimientos && (
                <Card className="border-zinc-800 bg-[#0f0f13]">
                    <CardContent className="p-0">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">
                                <tr>
                                    <th className="px-4 py-3">Fecha y hora</th>
                                    <th className="px-4 py-3">Tipo</th>
                                    <th className="px-4 py-3">Origen → Destino</th>
                                    <th className="px-4 py-3">Transporte</th>
                                    <th className="px-4 py-3">Registrado por</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/60">
                                {movimientos.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-8 text-center text-zinc-500">
                                            Sin movimientos de personal registrados.
                                        </td>
                                    </tr>
                                ) : (
                                    movimientos.map((m) => (
                                        <tr key={m.id}>
                                            <td className="px-4 py-3 tabular-nums">{m.fecha_hora}</td>
                                            <td className="px-4 py-3 font-medium">{m.tipo_etiqueta}</td>
                                            <td className="px-4 py-3">
                                                {m.origen} → {m.destino}
                                            </td>
                                            <td className="px-4 py-3 text-zinc-400">{m.transporte ?? '—'}</td>
                                            <td className="px-4 py-3 text-zinc-400">{m.registrado_por}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            )}

            <Dialog open={modalMovimiento} onOpenChange={setModalMovimiento}>
                <DialogContent className="bg-[#0f0f13] border-zinc-800 text-zinc-100">
                    <DialogHeader>
                        <DialogTitle>Registrar movimiento de personal</DialogTitle>
                        <DialogDescription>{trabajador.nombre_completo}</DialogDescription>
                    </DialogHeader>

                    {formMov.data.tipo === 'SALIDA_A_PATAZ' && trabajador.aptitud_visible && !trabajador.apto_viaje && (
                        <div className="flex items-start gap-2 rounded-md border border-rose-900/60 bg-rose-950/40 p-3 text-xs text-rose-400">
                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                            <span>{trabajador.aptitud_motivo}</span>
                        </div>
                    )}

                    <form onSubmit={registrarMovimiento} className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="m-tipo">Tipo de movimiento</Label>
                            <select
                                id="m-tipo"
                                value={formMov.data.tipo}
                                onChange={(e) => formMov.setData('tipo', e.target.value)}
                                className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                            >
                                <option value="SALIDA_A_PATAZ">Salida a Pataz</option>
                                <option value="RETORNO_A_TRUJILLO">Retorno a Trujillo</option>
                                <option value="OTRO">Otro traslado</option>
                            </select>
                            <InputError message={formMov.errors.trabajador_id} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="m-orig">Origen</Label>
                                <select
                                    id="m-orig"
                                    value={formMov.data.origen_sede_id}
                                    onChange={(e) => formMov.setData('origen_sede_id', e.target.value)}
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
                                <Label htmlFor="m-dest">Destino</Label>
                                <select
                                    id="m-dest"
                                    value={formMov.data.destino_sede_id}
                                    onChange={(e) => formMov.setData('destino_sede_id', e.target.value)}
                                    className="min-h-10 rounded-md border border-zinc-800 bg-zinc-900 px-3 text-[13px] text-zinc-200 focus:outline-none focus:border-blue-500"
                                >
                                    {sedes.map((s) => (
                                        <option key={s.id} value={String(s.id)}>
                                            {s.nombre}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={formMov.errors.destino_sede_id} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="m-fh">Fecha y hora</Label>
                                <Input
                                    id="m-fh"
                                    type="datetime-local"
                                    value={formMov.data.fecha_hora}
                                    onChange={(e) => formMov.setData('fecha_hora', e.target.value)}
                                    required
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="m-transp">Transporte</Label>
                                <Input
                                    id="m-transp"
                                    value={formMov.data.transporte}
                                    onChange={(e) => formMov.setData('transporte', e.target.value)}
                                    placeholder="Bus corporativo / Camioneta"
                                />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="m-obs">Observaciones</Label>
                            <Input
                                id="m-obs"
                                value={formMov.data.observaciones}
                                onChange={(e) => formMov.setData('observaciones', e.target.value)}
                            />
                        </div>

                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button type="button" variant="outline" onClick={() => setModalMovimiento(false)} className="min-h-9 border-zinc-800 text-zinc-300">
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={formMov.processing} className="min-h-9 bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium">
                                {formMov.processing ? 'Registrando...' : 'Confirmar movimiento'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </RrhhLayout>
    );
}

function DatoCampo({ label, valor }: { label: string; valor: string | null }) {
    return (
        <div>
            <div className="text-[11px] font-semibold text-zinc-500 uppercase tracking-wider">{label}</div>
            <div className="mt-1 text-[13px] font-medium text-zinc-200">{valor || '—'}</div>
        </div>
    );
}

