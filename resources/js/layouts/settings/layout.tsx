import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Perfil',
        url: '/settings/profile',
        icon: null,
    },
    {
        title: 'Contraseña',
        url: '/settings/password',
        icon: null,
    },
    {
        title: 'Apariencia',
        url: '/settings/appearance',
        icon: null,
    },
];

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
    const currentPath = window.location.pathname;

    return (
        <div className="flex flex-1 flex-col gap-6 bg-[#09090b] p-6 md:p-8 text-zinc-100">
            <Heading title="Configuración de Cuenta" description="Administra tus datos de perfil, credenciales y preferencias." />

            <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-8">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1">
                        {sidebarNavItems.map((item) => {
                            const activo = currentPath === item.url;
                            return (
                                <Link
                                    key={item.url}
                                    href={item.url}
                                    className={cn(
                                        'rounded-md px-3 py-1.5 text-[13px] font-medium transition-colors',
                                        activo
                                            ? 'border border-zinc-700 bg-zinc-900 text-zinc-100'
                                            : 'text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200',
                                    )}
                                >
                                    {item.title}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                <Separator className="my-6 md:hidden bg-zinc-800" />

                <div className="flex-1 rounded-lg border border-zinc-800 bg-[#0f0f13] p-6 md:max-w-2xl">
                    <section className="max-w-xl space-y-8">{children}</section>
                </div>
            </div>
        </div>
    );
}
