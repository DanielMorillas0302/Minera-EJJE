import { LucideIcon } from 'lucide-react';

export type RolUsuarioCodigo =
    | 'ADMIN'
    | 'RRHH_JEFE'
    | 'RRHH_ASISTENTE'
    | 'LOGISTICA_JEFE'
    | 'LOGISTICA_ASISTENTE'
    | 'FINANZAS'
    | 'GERENTE'
    | 'RECEPCION_PATAZ';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface FlashMessages {
    exito?: string | null;
    error?: string | null;
}

export interface SharedData {
    name: string;
    auth: Auth;
    flash?: FlashMessages;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    rol: RolUsuarioCodigo | null;
    rol_etiqueta: string;
    sede_id: number | null;
    sede_nombre: string | null;
    telefono: string | null;
    activo: boolean;
    modulos_visibles: string[];
    permisos?: Record<string, string>;
    email_verified_at: string | null;
    [key: string]: unknown;
}
