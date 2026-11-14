import { Link } from '@inertiajs/react';
import { Box } from 'lucide-react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-[#09090b] p-6 text-zinc-100 md:p-10">
            <div className="w-full max-w-sm">
                <div className="rounded-lg border border-zinc-800 bg-[#0f0f13] p-6 md:p-8">
                    <div className="mb-6 flex flex-col items-center text-center">
                        <Link href="/" className="mb-3 flex items-center gap-2.5">
                            <div className="flex h-9 w-9 items-center justify-center rounded-md border border-zinc-800 bg-zinc-900 text-amber-500">
                                <Box size={20} />
                            </div>
                            <div className="text-left">
                                <p className="text-[14px] font-semibold tracking-tight text-zinc-100">
                                    MINERA MORILLAS
                                </p>
                                <p className="text-[10px] font-semibold tracking-wider text-zinc-500 uppercase">
                                    TRUJILLO · PATAZ
                                </p>
                            </div>
                        </Link>

                        {title && <h1 className="text-xl font-semibold tracking-tight text-zinc-100">{title}</h1>}
                        {description && <p className="mt-1 text-[13px] text-zinc-400">{description}</p>}
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
