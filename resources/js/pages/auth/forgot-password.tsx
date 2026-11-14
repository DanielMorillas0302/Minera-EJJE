import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <AuthLayout title="Recuperar contraseña" description="Ingresa tu correo para recibir un enlace de restablecimiento">
            <Head title="Recuperar contraseña - ERP Morillas" />

            {status && (
                <div className="mb-4 rounded-md border border-emerald-900/60 bg-emerald-950/30 p-2.5 text-center text-[12px] text-emerald-400">
                    {status}
                </div>
            )}

            <div className="space-y-4">
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-1.5">
                        <label htmlFor="email" className="text-xs font-medium text-zinc-300">
                            Correo electrónico
                        </label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            autoComplete="off"
                            value={data.email}
                            autoFocus
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="usuario@morillas.pe"
                            className="w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500 focus:outline-none transition-colors"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <Button
                        type="submit"
                        className="w-full bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium py-2 rounded-md transition-colors"
                        disabled={processing}
                    >
                        {processing && <LoaderCircle size={14} className="mr-2 animate-spin" />}
                        Enviar enlace de recuperación
                    </Button>
                </form>

                <div className="text-center text-[12px] text-zinc-500">
                    <span>O regresa a </span>
                    <Link href={route('login')} className="text-zinc-300 hover:underline">
                        iniciar sesión
                    </Link>
                </div>
            </div>
        </AuthLayout>
    );
}
