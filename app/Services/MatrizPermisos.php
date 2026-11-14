<?php

namespace App\Services;

use App\Enums\RolUsuario;
use App\Models\User;

/**
 * Implementa la matriz de roles y permisos definida en 06-roles-y-permisos.md.
 * Acciones soportadas: C (crear), L (leer), E (editar), A (aprobar).
 */
class MatrizPermisos
{
    /**
     * @var array<string, array<string, string>>
     */
    private const MATRIZ = [
        'trabajadores' => [
            RolUsuario::ADMIN->value => 'CLE',
            RolUsuario::RRHH_JEFE->value => 'CLE',
            RolUsuario::RRHH_ASISTENTE->value => 'CLE',
            RolUsuario::FINANZAS->value => 'L',
            RolUsuario::GERENTE->value => 'L',
        ],
        'examenes' => [
            RolUsuario::ADMIN->value => 'CLE',
            RolUsuario::RRHH_JEFE->value => 'CLE',
            RolUsuario::RRHH_ASISTENTE->value => 'CLE',
            RolUsuario::GERENTE->value => 'L',
        ],
        'vacaciones_permisos' => [
            RolUsuario::ADMIN->value => 'CLEA',
            RolUsuario::RRHH_JEFE->value => 'CLEA',
            RolUsuario::RRHH_ASISTENTE->value => 'CLE',
            RolUsuario::GERENTE->value => 'L',
        ],
        'movimientos_personal' => [
            RolUsuario::ADMIN->value => 'CLE',
            RolUsuario::RRHH_JEFE->value => 'CLE',
            RolUsuario::RRHH_ASISTENTE->value => 'CLE',
            RolUsuario::LOGISTICA_JEFE->value => 'L',
            RolUsuario::GERENTE->value => 'L',
        ],
        'materiales_proveedores' => [
            RolUsuario::ADMIN->value => 'CLE',
            RolUsuario::LOGISTICA_JEFE->value => 'CLE',
            RolUsuario::LOGISTICA_ASISTENTE->value => 'CLE',
            RolUsuario::FINANZAS->value => 'L',
            RolUsuario::GERENTE->value => 'L',
            RolUsuario::RECEPCION_PATAZ->value => 'L',
        ],
        'requerimientos' => [
            RolUsuario::ADMIN->value => 'CLEA',
            RolUsuario::LOGISTICA_JEFE->value => 'CLEA',
            RolUsuario::LOGISTICA_ASISTENTE->value => 'CLE',
            RolUsuario::FINANZAS->value => 'L',
            RolUsuario::GERENTE->value => 'L',
            RolUsuario::RECEPCION_PATAZ->value => 'CLE',
        ],
        'ordenes_compra' => [
            RolUsuario::ADMIN->value => 'CLEA',
            RolUsuario::LOGISTICA_JEFE->value => 'CLEA',
            RolUsuario::LOGISTICA_ASISTENTE->value => 'CLE',
            RolUsuario::FINANZAS->value => 'L',
            RolUsuario::GERENTE->value => 'L',
        ],
        'envios' => [
            RolUsuario::ADMIN->value => 'CLE',
            RolUsuario::LOGISTICA_JEFE->value => 'CLE',
            RolUsuario::LOGISTICA_ASISTENTE->value => 'CLE',
            RolUsuario::FINANZAS->value => 'L',
            RolUsuario::GERENTE->value => 'L',
            RolUsuario::RECEPCION_PATAZ->value => 'L',
        ],
        'recepciones_consumos' => [
            RolUsuario::ADMIN->value => 'CLE',
            RolUsuario::LOGISTICA_JEFE->value => 'CLE',
            RolUsuario::LOGISTICA_ASISTENTE->value => 'CLE',
            RolUsuario::FINANZAS->value => 'L',
            RolUsuario::GERENTE->value => 'L',
            RolUsuario::RECEPCION_PATAZ->value => 'CLE',
        ],
        'stock_kardex' => [
            RolUsuario::ADMIN->value => 'L',
            RolUsuario::LOGISTICA_JEFE->value => 'L',
            RolUsuario::LOGISTICA_ASISTENTE->value => 'L',
            RolUsuario::FINANZAS->value => 'L',
            RolUsuario::GERENTE->value => 'L',
            RolUsuario::RECEPCION_PATAZ->value => 'L',
        ],
        'movimientos_financieros' => [
            RolUsuario::ADMIN->value => 'CLE',
            RolUsuario::FINANZAS->value => 'CLE',
            RolUsuario::GERENTE->value => 'L',
        ],
        'presupuesto' => [
            RolUsuario::ADMIN->value => 'CLE',
            RolUsuario::FINANZAS->value => 'CLE',
            RolUsuario::GERENTE->value => 'L',
        ],
        'dashboard_gerencial' => [
            RolUsuario::ADMIN->value => 'L',
            RolUsuario::RRHH_JEFE->value => 'L',
            RolUsuario::LOGISTICA_JEFE->value => 'L',
            RolUsuario::FINANZAS->value => 'L',
            RolUsuario::GERENTE->value => 'L',
        ],
        'usuarios_catalogos' => [
            RolUsuario::ADMIN->value => 'CLE',
        ],
        'importar_rrhh' => [
            RolUsuario::ADMIN->value => 'C',
            RolUsuario::RRHH_JEFE->value => 'C',
            RolUsuario::RRHH_ASISTENTE->value => 'C',
        ],
        'importar_logistica' => [
            RolUsuario::ADMIN->value => 'C',
            RolUsuario::LOGISTICA_JEFE->value => 'C',
            RolUsuario::LOGISTICA_ASISTENTE->value => 'C',
        ],
        'importar_recepcion' => [
            RolUsuario::ADMIN->value => 'C',
            RolUsuario::LOGISTICA_JEFE->value => 'C',
            RolUsuario::LOGISTICA_ASISTENTE->value => 'C',
            RolUsuario::RECEPCION_PATAZ->value => 'C',
        ],
    ];

    public static function puede(?User $usuario, string $recurso, string $accion): bool
    {
        if (! $usuario || ! $usuario->activo || ! $usuario->rol) {
            return false;
        }

        $permisos = self::MATRIZ[$recurso][$usuario->rol->value] ?? '';

        return str_contains($permisos, strtoupper($accion));
    }

    /**
     * @return array<string, string>
     */
    public static function permisosParaRol(?RolUsuario $rol): array
    {
        if (! $rol) {
            return [];
        }

        $resultado = [];
        foreach (self::MATRIZ as $recurso => $roles) {
            if (isset($roles[$rol->value])) {
                $resultado[$recurso] = $roles[$rol->value];
            }
        }

        return $resultado;
    }
}
