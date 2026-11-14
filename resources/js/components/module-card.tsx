import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { type LucideIcon } from 'lucide-react';

export interface ModuleCardProps {
    title: string;
    description: string;
    icon: LucideIcon;
    href: string;
    badge?: string;
    className?: string;
}

export function ModuleCard({ title, description, icon: Icon, href, badge, className }: ModuleCardProps) {
    return (
        <Link
            href={href}
            className={cn(
                'group flex flex-col justify-between rounded-lg border border-zinc-800 bg-[#0f0f13] p-5 transition-colors hover:border-zinc-700',
                className,
            )}
        >
            <div className="space-y-2">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-md border border-zinc-800 bg-zinc-900 text-zinc-400 transition-colors group-hover:border-zinc-700 group-hover:text-zinc-200">
                            <Icon size={16} />
                        </div>
                        <h3 className="text-[14px] font-medium text-zinc-100 transition-colors group-hover:text-white">
                            {title}
                        </h3>
                    </div>
                    {badge && (
                        <span className="rounded border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[11px] font-medium text-zinc-400">
                            {badge}
                        </span>
                    )}
                </div>
                <p className="text-[13px] leading-relaxed text-zinc-400">
                    {description}
                </p>
            </div>
        </Link>
    );
}

export default ModuleCard;

