import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Link } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    Boxes,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    FileUp,
    HardHat,
    Loader2,
    Package,
    RotateCcw,
    Truck,
    Users2,
} from 'lucide-react';
import { type ChangeEvent, type FormEvent, useState } from 'react';

interface TipoImportacionMeta {
    clave: string;
    titulo: string;
    descripcion: string;
    permiso: string;
    archivo: string;
}

interface FilaError {
    fila: number;
    datos: Record<string, unknown>;
    errores: string[];
}

interface ResultadoPrevisualizacion {
    token: string;
    tipo: string;
    nombre_archivo: string;
    total_filas: number;
    validas_count: number;
    errores_count: number;
    columnas: string[];
    filas_validas_preview: Array<Record<string, unknown>>;
    filas_con_error: FilaError[];
}

interface ImportacionesProps {
    tiposDisponibles: TipoImportacionMeta[];
    tipoInicial: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Carga Masiva Excel', href: '/importaciones' },
];

export default function Importaciones({ tiposDisponibles, tipoInicial }: ImportacionesProps) {
    const [tipoSeleccionado, setTipoSeleccionado] = useState(tipoInicial);
    const [archivo, setArchivo] = useState<File | null>(null);
    const [cargandoPreview, setCargandoPreview] = useState(false);
    const [confirmando, setConfirmando] = useState(false);
    const [errorSubida, setErrorSubida] = useState<string | null>(null);
    const [preview, setPreview] = useState<ResultadoPrevisualizacion | null>(null);
    const [mensajeExito, setMensajeExito] = useState<string | null>(null);

    const metaActual = tiposDisponibles.find((t) => t.clave === tipoSeleccionado) ?? tiposDisponibles[0];

    const alCambiarTipo = (clave: string) => {
        setTipoSeleccionado(clave);
        setArchivo(null);
        setPreview(null);
        setErrorSubida(null);
        setMensajeExito(null);
    };

    const alSeleccionarArchivo = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setArchivo(file);
        setPreview(null);
        setErrorSubida(null);
        setMensajeExito(null);
    };

    const enviarPrevisualizacion = async (e: FormEvent) => {
        e.preventDefault();
        if (!archivo) {
            setErrorSubida('Por favor seleccione un archivo Excel (.xlsx, .xls) o CSV.');
            return;
        }

        setCargandoPreview(true);
        setErrorSubida(null);
        setMensajeExito(null);

        const formData = new FormData();
        formData.append('tipo', tipoSeleccionado);
        formData.append('archivo', archivo);

        try {
            const tokenMeta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch('/importaciones/previsualizar', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': tokenMeta,
                    Accept: 'application/json',
                },
                body: formData,
            });

            const data = await res.json();
            if (!res.ok) {
                setErrorSubida(data.error ?? data.message ?? 'No se pudo procesar el archivo.');
            } else {
                setPreview(data);
            }
        } catch (err) {
            setErrorSubida('Error de conexión al cargar el archivo: ' + String(err));
        } finally {
            setCargandoPreview(false);
        }
    };

    const confirmarImportacion = async () => {
        if (!preview) return;
        setConfirmando(true);
        setErrorSubida(null);

        try {
            const tokenMeta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch('/importaciones/confirmar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': tokenMeta,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ token: preview.token }),
            });

            const data = await res.json();
            if (!res.ok) {
                setErrorSubida(data.error ?? data.message ?? 'Error al confirmar la importación.');
            } else {
                setMensajeExito(data.mensaje);
                setPreview(null);
                setArchivo(null);
            }
        } catch (err) {
            setErrorSubida('Error de comunicación: ' + String(err));
        } finally {
            setConfirmando(false);
        }
    };

    const iconoPorTipo = (clave: string) => {
        switch (clave) {
            case 'trabajadores':
                return HardHat;
            case 'materiales':
                return Package;
            case 'proveedores':
                return Users2;
            case 'stock_inicial':
                return Boxes;
            case 'recepcion_envio':
                return Truck;
            default:
                return FileSpreadsheet;
        }
    };

    const urlModuloDestino = (clave: string) => {
        switch (clave) {
            case 'trabajadores':
                return '/rrhh/trabajadores';
            case 'materiales':
                return '/logistica/materiales';
            case 'proveedores':
                return '/logistica/proveedores';
            case 'stock_inicial':
                return '/logistica/stock';
            case 'recepcion_envio':
                return '/logistica/envios';
            default:
                return '/dashboard';
        }
    };

    const IconoActual = iconoPorTipo(metaActual.clave);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="flex flex-1 flex-col gap-6 bg-[#09090b] p-6 md:p-8">
                {/* Cabecera */}
                <div className="flex flex-col gap-1 border-b border-zinc-800 pb-4">
                    <h1 className="text-2xl font-semibold tracking-tight text-zinc-100">
                        Carga Masiva e Importador Excel
                    </h1>
                    <p className="text-[13px] leading-relaxed text-zinc-400">
                        Carga de datos por lote con previsualización, validación fila por fila, detección de errores y descarga de plantillas oficiales.
                    </p>
                </div>

                {/* Selector de Catálogos para Importar */}
                <div className="flex flex-wrap gap-2">
                    {tiposDisponibles.map((item) => {
                        const Icono = iconoPorTipo(item.clave);
                        const seleccionado = item.clave === tipoSeleccionado;

                        return (
                            <button
                                key={item.clave}
                                type="button"
                                onClick={() => alCambiarTipo(item.clave)}
                                className={`inline-flex items-center gap-2 rounded-md border px-3.5 py-2 text-[13px] font-medium transition-colors ${
                                    seleccionado
                                        ? 'border-zinc-700 bg-zinc-800 text-zinc-100'
                                        : 'border-zinc-800 bg-[#0f0f13] text-zinc-400 hover:border-zinc-700 hover:text-zinc-200'
                                }`}
                            >
                                <Icono size={15} className={seleccionado ? 'text-zinc-200' : 'text-zinc-500'} />
                                <span>{item.titulo}</span>
                            </button>
                        );
                    })}
                </div>

                {mensajeExito && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-900/60 bg-emerald-950/30 p-4 text-[13px] text-emerald-400">
                        <div className="flex items-center gap-2 font-medium">
                            <CheckCircle2 size={16} className="text-emerald-400 shrink-0" />
                            <span>{mensajeExito}</span>
                        </div>
                        <Link
                            href={urlModuloDestino(metaActual.clave)}
                            className="inline-flex items-center rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-emerald-500"
                        >
                            Ver registros en el módulo
                        </Link>
                    </div>
                )}

                {errorSubida && (
                    <div className="flex items-center gap-2 rounded-lg border border-red-900/60 bg-red-950/30 p-4 text-[13px] font-medium text-red-400">
                        <AlertCircle size={16} className="text-red-400 shrink-0" />
                        <span>{errorSubida}</span>
                    </div>
                )}

                {/* Paneles de Instrucciones y Carga */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Tarjeta de Instrucciones y Plantilla */}
                    <div className="flex flex-col justify-between rounded-lg border border-zinc-800 bg-[#0f0f13] p-5 lg:col-span-1">
                        <div>
                            <div className="flex items-center gap-2 text-[14px] font-medium text-zinc-200">
                                <IconoActual size={16} className="text-zinc-400" />
                                <span>{metaActual.titulo}</span>
                            </div>
                            <p className="mt-1 text-[13px] text-zinc-400">{metaActual.descripcion}</p>

                            <div className="mt-4 rounded-md border border-zinc-800 bg-zinc-900/60 p-3.5 text-[12px] text-zinc-400">
                                <p className="font-semibold text-zinc-300">Pasos recomendados:</p>
                                <ul className="mt-2 list-inside list-disc space-y-1 text-zinc-400">
                                    <li>Descarga la plantilla Excel (.xlsx) estructurada.</li>
                                    <li>Completa los datos sin alterar los encabezados.</li>
                                    <li>Selecciona el archivo y haz clic en <strong>Previsualizar</strong>.</li>
                                    <li>Revisa las observaciones antes de la confirmación final.</li>
                                </ul>
                            </div>
                        </div>

                        <div className="mt-6 border-t border-zinc-800/80 pt-4">
                            <a
                                href={`/importaciones/plantilla/${metaActual.clave}`}
                                className="inline-flex w-full items-center justify-center gap-2 rounded-md border border-zinc-800 bg-zinc-900 px-4 py-2 text-[13px] font-medium text-zinc-200 transition-colors hover:bg-zinc-800 hover:text-white"
                            >
                                <Download size={15} />
                                <span>Descargar plantilla oficial (.xlsx)</span>
                            </a>
                        </div>
                    </div>

                    {/* Tarjeta de Subida */}
                    <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-5 lg:col-span-2">
                        <div className="text-[14px] font-medium text-zinc-200">Subir archivo para previsualización</div>
                        <p className="mt-1 text-[13px] text-zinc-400">Formatos permitidos: .xlsx, .xls o .csv (máximo 10 MB).</p>

                        <form onSubmit={enviarPrevisualizacion} className="mt-4 space-y-4">
                            <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-zinc-800 bg-zinc-900/40 p-8 text-center transition-colors hover:border-zinc-700 hover:bg-zinc-900/70">
                                <FileUp size={32} className="text-zinc-500" />
                                <label
                                    htmlFor="input-archivo"
                                    className="mt-3 cursor-pointer text-[13px] font-medium text-blue-400 hover:underline"
                                >
                                    {archivo ? archivo.name : 'Haz clic para seleccionar o arrastra el archivo aquí'}
                                </label>
                                <input
                                    id="input-archivo"
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    onChange={alSeleccionarArchivo}
                                    className="hidden"
                                />
                                <p className="mt-1 text-[12px] text-zinc-500">
                                    {archivo ? `${(archivo.size / 1024).toFixed(1)} KB listos para validar` : 'Los encabezados deben ubicarse en la primera fila'}
                                </p>
                            </div>

                            <div className="flex justify-end gap-2">
                                {preview && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setPreview(null);
                                            setArchivo(null);
                                        }}
                                        className="inline-flex items-center gap-1.5 rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] font-medium text-zinc-400 transition-colors hover:bg-zinc-800 hover:text-zinc-200"
                                    >
                                        <RotateCcw size={14} />
                                        <span>Limpiar</span>
                                    </button>
                                )}
                                <Button
                                    type="submit"
                                    disabled={!archivo || cargandoPreview}
                                    className="bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium px-4 py-2"
                                >
                                    {cargandoPreview ? (
                                        <>
                                            <Loader2 size={14} className="mr-2 animate-spin" />
                                            Validando consistencia...
                                        </>
                                    ) : (
                                        'Previsualizar archivo'
                                    )}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Sección de Resultados de Previsualización */}
                {preview && (
                    <div className="space-y-6">
                        {/* Resumen de Métricas */}
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-4">
                                <div className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Filas leídas</div>
                                <div className="mt-1 text-2xl font-semibold tracking-tight text-zinc-100 tabular-nums">
                                    {preview.total_filas}
                                </div>
                            </div>

                            <div className="rounded-lg border border-emerald-900/60 bg-emerald-950/20 p-4">
                                <div className="text-[11px] font-semibold uppercase tracking-wider text-emerald-400">Filas válidas listas</div>
                                <div className="mt-1 text-2xl font-semibold tracking-tight text-emerald-400 tabular-nums">
                                    {preview.validas_count}
                                </div>
                            </div>

                            <div className={`rounded-lg border p-4 ${preview.errores_count > 0 ? 'border-red-900/60 bg-red-950/20' : 'border-zinc-800 bg-[#0f0f13]'}`}>
                                <div className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Filas observadas</div>
                                <div className={`mt-1 text-2xl font-semibold tracking-tight tabular-nums ${preview.errores_count > 0 ? 'text-red-400' : 'text-zinc-400'}`}>
                                    {preview.errores_count}
                                </div>
                            </div>
                        </div>

                        {/* Tabla de Errores Detectados */}
                        {preview.errores_count > 0 && (
                            <div className="overflow-hidden rounded-lg border border-red-900/60 bg-[#140b0d]">
                                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-red-900/60 p-4">
                                    <div className="flex items-center gap-2 text-red-400">
                                        <AlertTriangle size={16} />
                                        <span className="text-[14px] font-medium">
                                            Observaciones detectadas ({preview.errores_count} fila(s) rechazadas)
                                        </span>
                                    </div>
                                    <a
                                        href={`/importaciones/reporte-errores?token=${preview.token}`}
                                        className="inline-flex items-center gap-1.5 rounded-md border border-red-900/60 bg-red-950/50 px-3 py-1.5 text-[12px] font-medium text-red-300 transition-colors hover:bg-red-900/40"
                                    >
                                        <FileSpreadsheet size={14} />
                                        <span>Descargar reporte de errores (.xlsx)</span>
                                    </a>
                                </div>
                                <div className="max-h-64 overflow-y-auto">
                                    <table className="w-full text-left text-[12px]">
                                        <thead className="border-b border-red-900/60 bg-red-950/40 text-[11px] font-semibold uppercase tracking-wider text-red-300">
                                            <tr>
                                                <th className="px-4 py-2.5">Fila Excel</th>
                                                <th className="px-4 py-2.5">Datos recibidos</th>
                                                <th className="px-4 py-2.5">Motivo del error</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-red-900/30">
                                            {preview.filas_con_error.map((item, idx) => (
                                                <tr key={idx} className="transition-colors hover:bg-red-950/20">
                                                    <td className="px-4 py-2.5 font-bold tabular-nums text-red-300">#{item.fila}</td>
                                                    <td className="px-4 py-2.5 font-mono text-zinc-300">
                                                        {Object.entries(item.datos)
                                                            .filter(([, v]) => v !== null && v !== '')
                                                            .map(([k, v]) => `${k}: ${v}`)
                                                            .join(' | ') || 'Fila en blanco'}
                                                    </td>
                                                    <td className="px-4 py-2.5 font-medium text-red-400">
                                                        {item.errores.join(' • ')}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* Vista Previa de Filas Válidas */}
                        <div className="overflow-hidden rounded-lg border border-zinc-800 bg-[#0f0f13]">
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-800 p-4">
                                <div>
                                    <div className="text-[14px] font-medium text-zinc-200">
                                        Vista previa de filas conformes ({preview.validas_count})
                                    </div>
                                    <p className="text-[12px] text-zinc-400">
                                        Primeras 50 filas validadas listas para persistirse en la base de datos de producción.
                                    </p>
                                </div>
                                {preview.validas_count > 0 && (
                                    <Button
                                        type="button"
                                        onClick={confirmarImportacion}
                                        disabled={confirmando}
                                        className="bg-emerald-600 text-white hover:bg-emerald-500 font-medium text-[13px] px-4 py-2"
                                    >
                                        {confirmando ? (
                                            <>
                                                <Loader2 size={14} className="mr-2 animate-spin" />
                                                Importando registros...
                                            </>
                                        ) : (
                                            <>
                                                <CheckCircle2 size={14} className="mr-1.5" />
                                                Confirmar e importar {preview.validas_count} registro(s)
                                            </>
                                        )}
                                    </Button>
                                )}
                            </div>

                            {preview.validas_count === 0 ? (
                                <div className="p-8 text-center text-[13px] text-zinc-500">
                                    Ninguna fila del archivo pasó las validaciones obligatorias. Corrige los datos según las observaciones e intenta de nuevo.
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-[12px]">
                                        <thead className="border-b border-zinc-800 bg-zinc-900 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
                                            <tr>
                                                {preview.columnas.map((col) => (
                                                    <th key={col} className="px-3.5 py-2.5 whitespace-nowrap">
                                                        {col}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-zinc-800/60">
                                            {preview.filas_validas_preview.map((fila, idx) => (
                                                <tr key={idx} className="transition-colors hover:bg-zinc-900/40">
                                                    {preview.columnas.map((col) => (
                                                        <td key={col} className="px-3.5 py-2.5 whitespace-nowrap text-zinc-300">
                                                            {fila[col] !== undefined && fila[col] !== null
                                                                ? String(fila[col])
                                                                : '—'}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
