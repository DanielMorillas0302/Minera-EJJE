import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Boxes, FileSpreadsheet, HardHat, ShieldCheck, Wallet } from 'lucide-react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    const modulos = [
        {
            nombre: 'Gestión Humana y EMO',
            descripcion: 'Personal en faena, rotaciones 14x7, aptitud médica ocupacional y descansos.',
            icono: HardHat,
        },
        {
            nombre: 'Cadena de Suministro',
            descripcion: 'Requerimientos, órdenes de compra, control de kardex y despachos Trujillo-Pataz.',
            icono: Boxes,
        },
        {
            nombre: 'Finanzas y Tesorería',
            descripcion: 'Ejecución presupuestal, centros de costo, movimientos de caja y cuentas por pagar.',
            icono: Wallet,
        },
        {
            nombre: 'Analítica y Carga Masiva',
            descripcion: 'Informes gerenciales consolidados, reportes oficiales y plantillas Excel.',
            icono: FileSpreadsheet,
        },
    ];

    return (
        <>
            <Head title="ERP Minera Morillas - Trujillo / Pataz" />
            <div className="min-h-screen bg-[#09090b] text-zinc-100 flex flex-col justify-between">
                {/* Cabecera Superior */}
                <header className="border-b border-zinc-800 bg-[#09090b]">
                    <div className="mx-auto flex h-14 max-w-6xl items-center justify-between px-6">
                        <div className="flex items-center gap-3">
                            <div className="flex h-8 w-8 items-center justify-center rounded-md border border-zinc-800 bg-zinc-900 text-amber-500">
                                <Boxes size={18} />
                            </div>
                            <div>
                                <p className="text-[14px] font-semibold tracking-tight text-zinc-100">
                                    MINERA MORILLAS
                                </p>
                                <p className="text-[11px] font-medium tracking-wider text-zinc-500 uppercase">
                                    TRUJILLO · PATAZ
                                </p>
                            </div>
                        </div>

                        <nav className="flex items-center gap-3">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-md bg-zinc-100 px-3.5 py-1.5 text-[13px] font-medium text-zinc-900 transition-colors hover:bg-white"
                                >
                                    Ir al ERP
                                </Link>
                            ) : (
                                <Link
                                    href={route('login')}
                                    className="rounded-md bg-zinc-100 px-3.5 py-1.5 text-[13px] font-medium text-zinc-900 transition-colors hover:bg-white"
                                >
                                    Iniciar sesión
                                </Link>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero Section */}
                <main className="mx-auto w-full max-w-6xl px-6 py-12 flex-1 flex flex-col justify-center">
                    <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-8 md:p-12">
                        <div className="inline-flex items-center gap-2 rounded-full border border-emerald-900/60 bg-emerald-950/40 px-3 py-1 text-[11px] font-medium uppercase tracking-wider text-emerald-400">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                            Sistema Operacional Conectado
                        </div>

                        <h1 className="mt-4 text-3xl font-semibold tracking-tight text-zinc-100 md:text-4xl">
                            Sistema Integrado de Gestión Minera
                        </h1>
                        <p className="mt-3 max-w-2xl text-[14px] leading-relaxed text-zinc-400">
                            Plataforma corporativa centralizada para la administración de faenas, control logístico, tesorería y analítica gerencial entre la base operativa en Pataz y oficinas centrales en Trujillo.
                        </p>

                        <div className="mt-8 flex flex-wrap gap-3">
                            <Link
                                href={auth.user ? route('dashboard') : route('login')}
                                className="inline-flex items-center gap-2 rounded-md bg-zinc-100 px-5 py-2.5 text-[13px] font-medium text-zinc-900 transition-colors hover:bg-white"
                            >
                                <span>{auth.user ? 'Acceder al Resumen Ejecutivo' : 'Ingresar al sistema'}</span>
                                <ArrowRight size={14} />
                            </Link>
                        </div>
                    </div>

                    {/* Módulos Principales */}
                    <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {modulos.map((m) => {
                            const Icono = m.icono;
                            return (
                                <div
                                    key={m.nombre}
                                    className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-5 transition-colors hover:border-zinc-700"
                                >
                                    <div className="flex h-8 w-8 items-center justify-center rounded-md border border-zinc-800 bg-zinc-900 text-zinc-400">
                                        <Icono size={16} />
                                    </div>
                                    <h2 className="mt-3 text-[14px] font-medium text-zinc-200">{m.nombre}</h2>
                                    <p className="mt-1 text-[12px] leading-relaxed text-zinc-400">{m.descripcion}</p>
                                </div>
                            );
                        })}
                    </div>
                </main>

                {/* Footer */}
                <footer className="border-t border-zinc-800 bg-[#09090b] py-4 text-center text-[12px] text-zinc-500">
                    ERP Minera Morillas · Trujillo / Pataz · Todos los derechos reservados.
                </footer>
            </div>
        </>
    );
}
