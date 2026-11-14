import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, ShieldAlert } from 'lucide-react';

interface ErrorProps {
    status: number;
}

const MENSAJES: Record<number, { titulo: string; detalle: string }> = {
    403: {
        titulo: 'Acceso denegado',
        detalle: 'No dispones de los permisos operativos requeridos para acceder a este recurso o acción.',
    },
    404: {
        titulo: 'Página no encontrada',
        detalle: 'La ruta solicitada no existe en el ERP o fue reubicada.',
    },
    500: {
        titulo: 'Error interno del servidor',
        detalle: 'Ocurrió un problema inesperado al procesar la solicitud. Notifica al equipo de soporte.',
    },
    503: {
        titulo: 'Servicio en mantenimiento',
        detalle: 'El sistema se encuentra en labores de optimización y mantenimiento de infraestructura.',
    },
};

export default function ErrorPage({ status }: ErrorProps) {
    const info = MENSAJES[status] ?? {
        titulo: 'Error inesperado',
        detalle: 'No fue posible completar la solicitud en el servidor.',
    };

    return (
        <>
            <Head title={`Error ${status} - ERP Minera Morillas`} />
            <div className="flex min-h-screen items-center justify-center bg-[#09090b] p-6">
                <div className="w-full max-w-md rounded-lg border border-zinc-800 bg-[#0f0f13] p-8 text-center">
                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-lg border border-red-900/60 bg-red-950/40 text-red-400">
                        {status === 403 ? <ShieldAlert size={22} /> : <AlertTriangle size={22} />}
                    </div>
                    <p className="mt-4 text-[11px] font-semibold uppercase tracking-wider text-red-400 tabular-nums font-mono">
                        Código de estado {status}
                    </p>
                    <h1 className="mt-2 text-2xl font-semibold tracking-tight text-zinc-100">{info.titulo}</h1>
                    <p className="mt-2 text-[13px] leading-relaxed text-zinc-400">{info.detalle}</p>
                    <div className="mt-6">
                        <Link
                            href="/dashboard"
                            className="inline-flex items-center gap-2 rounded-md bg-zinc-100 px-4 py-2 text-[13px] font-medium text-zinc-900 transition-colors hover:bg-white"
                        >
                            <ArrowLeft size={14} />
                            Volver al Resumen Ejecutivo
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
