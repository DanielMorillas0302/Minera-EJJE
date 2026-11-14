import { Box } from 'lucide-react';

export default function AppLogo() {
    return (
        <div className="flex items-center gap-2.5 px-0.5 py-1">
            <div className="flex size-7 items-center justify-center rounded border border-amber-500/30 bg-amber-500/10 text-amber-500">
                <Box size={16} className="text-amber-500" />
            </div>
            <div className="grid flex-1 text-left leading-tight">
                <span className="truncate text-xs font-semibold tracking-wider text-zinc-100">MINERA MORILLAS</span>
                <span className="truncate text-[10px] font-medium tracking-wider text-zinc-400">TRUJILLO-PATAZ</span>
            </div>
        </div>
    );
}
