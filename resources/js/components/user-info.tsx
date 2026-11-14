import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { type User } from '@/types';

export function UserInfo({ user, showEmail = false }: { user: User; showEmail?: boolean }) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar className="h-7 w-7 overflow-hidden rounded-md border border-zinc-800">
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback className="rounded-md bg-zinc-900 text-xs font-medium text-zinc-300">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-xs leading-tight">
                <span className="truncate font-medium text-zinc-200">{user.name}</span>
                <span className="truncate text-[11px] text-zinc-500">
                    {showEmail ? user.email : user.rol_etiqueta}
                </span>
            </div>
        </>
    );
}
