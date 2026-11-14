import { NavGroupSection, NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    FileSpreadsheet,
    FileUp,
    Home,
    Search,
    Settings,
    Truck,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const visibles = new Set([...(auth?.user?.modulos_visibles ?? ['inicio']), 'notificaciones']);
    const esAdmin = auth?.user?.rol === 'ADMIN';

    // 1. PRINCIPAL
    const principalItems = [
        {
            clave: 'inicio',
            title: 'Resumen Ejecutivo',
            url: '/dashboard',
            icon: Home,
        },
        {
            clave: 'notificaciones',
            title: 'Bandeja de Alertas',
            url: '/notificaciones',
            icon: Bell,
            badge: 12,
        },
    ].filter((item) => visibles.has(item.clave));

    // 2. GESTIÓN DE FAENA
    const faenaItems = [
        {
            clave: 'rrhh',
            title: 'Personal en Mina',
            url: '/rrhh',
            icon: Users,
            subItems: [
                { title: 'Resumen y Alertas', url: '/rrhh' },
                { title: 'Padrón de Personal', url: '/rrhh/trabajadores' },
                { title: 'Exámenes Médicos (EMO)', url: '/rrhh/examenes' },
                { title: 'Traslados Faena Pataz', url: '/rrhh/movimientos' },
                { title: 'Vacaciones y Permisos', url: '/rrhh/vacaciones' },
            ],
        },
        {
            clave: 'logistica',
            title: 'Despachos y Cadena',
            url: '/logistica',
            icon: Truck,
            subItems: [
                { title: 'Resumen y Stock Crítico', url: '/logistica' },
                { title: 'Catálogo de Materiales', url: '/logistica/materiales' },
                { title: 'Requerimientos de Mina', url: '/logistica/requerimientos' },
                { title: 'Órdenes de Compra (OC)', url: '/logistica/ordenes-compra' },
                { title: 'Despachos Trujillo-Pataz', url: '/logistica/envios' },
                { title: 'Kardex e Inventario', url: '/logistica/stock-kardex' },
                { title: 'Proveedores', url: '/logistica/proveedores' },
            ],
        },
    ].filter((item) => visibles.has(item.clave));

    // 3. FINANZAS Y CONTROL
    const finanzasItems = [
        {
            clave: 'finanzas',
            title: 'Control Financiero',
            url: '/finanzas',
            icon: Wallet,
            subItems: [
                { title: 'Resumen Financiero', url: '/finanzas' },
                { title: 'Presupuestos por Sede', url: '/finanzas/presupuestos' },
                { title: 'Caja y Bancos', url: '/finanzas/movimientos' },
                { title: 'Cuentas por Pagar', url: '/finanzas/cuentas-por-pagar' },
                { title: 'Centros de Costo', url: '/finanzas/catalogos' },
            ],
        },
    ].filter((item) => visibles.has(item.clave));

    // 4. DIRECCIÓN
    const direccionItems = [
        {
            clave: 'gerencia',
            title: 'Tablero Gerencial',
            url: '/gerencia',
            icon: BarChart3,
            subItems: [
                { title: 'Dashboard de KPIs', url: '/gerencia' },
                { title: 'Informe Ejecutivo PDF', url: '/gerencia/reporte-pdf' },
            ],
        },
        {
            clave: 'reportes',
            title: 'Analítica de Datos',
            url: '/reportes',
            icon: FileSpreadsheet,
        },
    ].filter((item) => visibles.has(item.clave));

    // 5. SISTEMA
    const sistemaItems = [
        ...(visibles.has('logistica') || visibles.has('rrhh') || esAdmin
            ? [
                  {
                      clave: 'importaciones',
                      title: 'Carga Masiva Excel',
                      url: '/importaciones',
                      icon: FileUp,
                  },
              ]
            : []),
        ...(visibles.has('administracion') || esAdmin
            ? [
                  {
                      clave: 'administracion',
                      title: 'Configuración',
                      url: '/administracion',
                      icon: Settings,
                      subItems: [
                          { title: 'Usuarios y Roles', url: '/administracion/usuarios' },
                          { title: 'Catálogos del Sistema', url: '/administracion/catalogos' },
                          { title: 'Bitácora de Auditoría', url: '/administracion/auditoria' },
                      ],
                  },
              ]
            : []),
    ];

    const sections: NavGroupSection[] = [
        { items: principalItems },
        { label: 'Gestión de Faena', items: faenaItems },
        { label: 'Finanzas y Control', items: finanzasItems },
        { label: 'Dirección', items: direccionItems },
        { label: 'Sistema', items: sistemaItems },
    ].filter((sec) => sec.items.length > 0);

    return (
        <Sidebar collapsible="icon" variant="sidebar" className="w-64 border-r border-zinc-800 bg-[#09090b]">
            <SidebarHeader className="border-b border-zinc-800 bg-[#09090b] px-3 py-2.5">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="hover:bg-zinc-900">
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                {/* Buscador Rápido Ctrl+K */}
                <div className="mt-1 flex items-center gap-2 rounded-md border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-xs text-zinc-500">
                    <Search size={14} className="text-zinc-500" />
                    <span>Buscar (Ctrl+K)</span>
                </div>
            </SidebarHeader>

            <SidebarContent className="bg-[#09090b] px-1 py-2">
                <NavMain sections={sections} />
            </SidebarContent>

            <SidebarFooter className="border-t border-zinc-800 bg-[#09090b] px-3 py-2">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
