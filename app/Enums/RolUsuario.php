<?php

namespace App\Enums;

enum RolUsuario: string
{
    case ADMIN = 'ADMIN';
    case RRHH_JEFE = 'RRHH_JEFE';
    case RRHH_ASISTENTE = 'RRHH_ASISTENTE';
    case LOGISTICA_JEFE = 'LOGISTICA_JEFE';
    case LOGISTICA_ASISTENTE = 'LOGISTICA_ASISTENTE';
    case FINANZAS = 'FINANZAS';
    case GERENTE = 'GERENTE';
    case RECEPCION_PATAZ = 'RECEPCION_PATAZ';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrador',
            self::RRHH_JEFE => 'Jefe de RR.HH.',
            self::RRHH_ASISTENTE => 'Asistente de RR.HH.',
            self::LOGISTICA_JEFE => 'Jefe de Logística',
            self::LOGISTICA_ASISTENTE => 'Asistente de Logística',
            self::FINANZAS => 'Finanzas',
            self::GERENTE => 'Gerente',
            self::RECEPCION_PATAZ => 'Recepción Pataz',
        };
    }

    /**
     * Módulos visibles en la navegación según la matriz de 06-roles-y-permisos.md.
     *
     * @return list<string>
     */
    public function modulosVisibles(): array
    {
        return match ($this) {
            self::ADMIN => ['inicio', 'rrhh', 'logistica', 'finanzas', 'gerencia', 'reportes', 'administracion'],
            self::RRHH_JEFE => ['inicio', 'rrhh', 'gerencia', 'reportes'],
            self::RRHH_ASISTENTE => ['inicio', 'rrhh', 'reportes'],
            self::LOGISTICA_JEFE => ['inicio', 'rrhh', 'logistica', 'gerencia', 'reportes'],
            self::LOGISTICA_ASISTENTE => ['inicio', 'logistica', 'reportes'],
            self::FINANZAS => ['inicio', 'rrhh', 'logistica', 'finanzas', 'gerencia', 'reportes'],
            self::GERENTE => ['inicio', 'rrhh', 'logistica', 'finanzas', 'gerencia', 'reportes'],
            self::RECEPCION_PATAZ => ['inicio', 'logistica'],
        };
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(static fn (self $rol): string => $rol->value, self::cases());
    }
}
