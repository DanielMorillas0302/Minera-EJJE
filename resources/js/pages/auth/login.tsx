import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
};

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout
            title="Ingreso al ERP Minero"
            description="Ingresa tus credenciales corporativas para acceder"
        >
            <Head title="Iniciar sesión - ERP Morillas" />

            <form className="flex flex-col gap-4" onSubmit={submit}>
                <div className="grid gap-1.5">
                    <label htmlFor="email" className="text-xs font-medium text-zinc-300">
                        Correo electrónico
                    </label>
                    <input
                        id="email"
                        type="email"
                        required
                        autoFocus
                        tabIndex={1}
                        autoComplete="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="usuario@morillas.pe"
                        className="w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500 focus:outline-none transition-colors"
                    />
                    <InputError message={errors.email} />
                </div>

                <div className="grid gap-1.5">
                    <div className="flex items-center justify-between">
                        <label htmlFor="password" className="text-xs font-medium text-zinc-300">
                            Contraseña
                        </label>
                        {canResetPassword && (
                            <a
                                href={route('password.request')}
                                className="text-[12px] text-zinc-500 transition-colors hover:text-zinc-300"
                                tabIndex={5}
                            >
                                ¿Olvidaste tu contraseña?
                            </a>
                        )}
                    </div>
                    <input
                        id="password"
                        type="password"
                        required
                        tabIndex={2}
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="••••••••"
                        className="w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-[13px] text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500 focus:outline-none transition-colors"
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="flex items-center space-x-2 pt-1">
                    <input
                        id="remember"
                        type="checkbox"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                        className="h-4 w-4 rounded border-zinc-800 bg-zinc-900 text-blue-600 focus:ring-0"
                        tabIndex={3}
                    />
                    <label htmlFor="remember" className="text-xs text-zinc-400">
                        Recordar sesión en este equipo
                    </label>
                </div>

                <Button
                    type="submit"
                    className="mt-2 w-full bg-zinc-100 text-zinc-900 hover:bg-white text-[13px] font-medium py-2 rounded-md transition-colors"
                    tabIndex={4}
                    disabled={processing}
                >
                    {processing && <LoaderCircle size={14} className="mr-2 animate-spin" />}
                    Iniciar sesión
                </Button>
            </form>

            {status && (
                <div className="mt-4 rounded-md border border-emerald-900/60 bg-emerald-950/30 p-2.5 text-center text-[12px] text-emerald-400">
                    {status}
                </div>
            )}
        </AuthLayout>
    );
}
