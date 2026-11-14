import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, type LucideIcon } from 'lucide-react';
import { useState } from 'react';

export interface NavSubItem {
    title: string;
    url: string;
}

export interface NavMainItem {
    clave: string;
    title: string;
    url: string;
    icon: LucideIcon;
    badge?: string | number;
    badgeVariant?: 'danger' | 'warning' | 'info' | 'default';
    subItems?: NavSubItem[];
}

export interface NavGroupSection {
    label?: string;
    items: NavMainItem[];
}

export function NavMain({ sections = [] }: { sections: NavGroupSection[] }) {
    const page = usePage();
    const [openSubmenus, setOpenSubmenus] = useState<Record<string, boolean>>({});

    const toggleSubmenu = (title: string) => {
        setOpenSubmenus((prev) => ({
            ...prev,
            [title]: !(prev[title] ?? true),
        }));
    };

    return (
        <div className="flex flex-col gap-3 py-1 text-[13px]">
            {sections.map((section, idx) => {
                if (section.items.length === 0) return null;

                return (
                    <SidebarGroup key={section.label ?? `section-${idx}`} className="px-2 py-0">
                        {section.label && (
                            <SidebarGroupLabel className="mb-1 text-[11px] font-semibold tracking-wider text-zinc-500 uppercase">
                                {section.label}
                            </SidebarGroupLabel>
                        )}
                        <SidebarMenu className="gap-0.5">
                            {section.items.map((item) => {
                                const isItemActive =
                                    item.url === '/dashboard'
                                        ? page.url === '/dashboard' || page.url === '/'
                                        : page.url === item.url || page.url.startsWith(`${item.url}/`);

                                const hasSubItems = item.subItems && item.subItems.length > 0;
                                const isSubmenuOpen = openSubmenus[item.title] ?? isItemActive;

                                return (
                                    <SidebarMenuItem key={item.title}>
                                        <div className="flex w-full items-center">
                                            <SidebarMenuButton
                                                asChild
                                                isActive={isItemActive}
                                                tooltip={{ children: item.title }}
                                                className={`group flex w-full items-center justify-between rounded-md px-3 py-2 text-[13px] font-medium transition-colors ${
                                                    isItemActive
                                                        ? 'bg-zinc-800/80 text-zinc-100 border border-zinc-700/60 font-medium'
                                                        : 'text-zinc-300 hover:bg-zinc-900/80 hover:text-zinc-100'
                                                }`}
                                            >
                                                <div className="flex w-full items-center justify-between">
                                                    <Link href={item.url} prefetch className="flex flex-1 items-center gap-2.5 min-w-0">
                                                        <item.icon
                                                            size={16}
                                                            className={`shrink-0 transition-colors ${
                                                                isItemActive ? 'text-zinc-200' : 'text-zinc-400 group-hover:text-zinc-300'
                                                            }`}
                                                        />
                                                        <span className="truncate">{item.title}</span>
                                                    </Link>

                                                    {item.badge !== undefined && (
                                                        <span className="ml-auto rounded border border-zinc-800 bg-zinc-800 px-1.5 py-0.5 text-[11px] font-medium text-zinc-300 tabular-nums">
                                                            {item.badge}
                                                        </span>
                                                    )}

                                                    {hasSubItems && (
                                                        <button
                                                            type="button"
                                                            onClick={(e) => {
                                                                e.preventDefault();
                                                                e.stopPropagation();
                                                                toggleSubmenu(item.title);
                                                            }}
                                                            className="ml-auto flex h-6 w-5 shrink-0 items-center justify-center text-zinc-500 hover:text-zinc-300 transition-colors"
                                                            title="Alternar submenú"
                                                        >
                                                            <ChevronRight
                                                                size={14}
                                                                className={`transition-transform duration-150 ${isSubmenuOpen ? 'rotate-90 text-zinc-400' : ''}`}
                                                            />
                                                        </button>
                                                    )}
                                                </div>
                                            </SidebarMenuButton>
                                        </div>

                                        {hasSubItems && isSubmenuOpen && (
                                            <SidebarMenuSub className="my-0.5 ml-3.5 border-l border-zinc-800 pl-2">
                                                {item.subItems?.map((sub) => {
                                                    const isSubActive =
                                                        sub.url === item.url
                                                            ? page.url === sub.url
                                                            : page.url === sub.url || page.url.startsWith(`${sub.url}/`);

                                                    return (
                                                        <SidebarMenuSubItem key={sub.title}>
                                                            <SidebarMenuSubButton
                                                                asChild
                                                                isActive={isSubActive}
                                                                size="sm"
                                                                className={`rounded px-2 py-1 text-[12px] transition-colors ${
                                                                    isSubActive
                                                                        ? 'font-medium text-zinc-100 bg-zinc-900/80'
                                                                        : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900/40'
                                                                }`}
                                                            >
                                                                <Link href={sub.url} prefetch>
                                                                    <span>{sub.title}</span>
                                                                </Link>
                                                            </SidebarMenuSubButton>
                                                        </SidebarMenuSubItem>
                                                    );
                                                })}
                                            </SidebarMenuSub>
                                        )}
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroup>
                );
            })}
        </div>
    );
}
