<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Enums\TipoSede;
use App\Models\Core\Area;
use App\Models\Core\Cargo;
use App\Models\Core\Sede;
use App\Models\Core\UnidadMedida;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CoreSeeder extends Seeder
{
    /**
     * Carga idempotente de catálogos base (Fase 1) y usuarios demo en modo DEBUG.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (RolUsuario::cases() as $rol) {
                Role::findOrCreate($rol->value, 'web');
            }

            $trujillo = Sede::updateOrCreate(
                ['nombre' => 'Trujillo'],
                ['tipo' => TipoSede::ADMINISTRATIVA, 'ubicacion' => 'Oficinas Administrativas - Trujillo', 'activo' => true]
            );

            $pataz = Sede::updateOrCreate(
                ['nombre' => 'Pataz'],
                ['tipo' => TipoSede::OPERATIVA, 'ubicacion' => 'Operación Minera y Campamentos - Pataz', 'activo' => true]
            );

            $areasPorSede = [
                ['nombre' => 'RR.HH.', 'sede_id' => $trujillo->id],
                ['nombre' => 'Logística', 'sede_id' => $trujillo->id],
                ['nombre' => 'Finanzas', 'sede_id' => $trujillo->id],
                ['nombre' => 'Gerencia', 'sede_id' => $trujillo->id],
                ['nombre' => 'Operaciones', 'sede_id' => $pataz->id],
            ];

            foreach ($areasPorSede as $area) {
                Area::updateOrCreate(
                    ['nombre' => $area['nombre'], 'sede_id' => $area['sede_id']],
                    ['activo' => true]
                );
            }

            $cargos = [
                'Gerente General',
                'Jefe de RR.HH.',
                'Asistente de RR.HH.',
                'Jefe de Logística',
                'Almacenero',
                'Contador',
                'Operario de mina',
                'Supervisor de seguridad',
            ];

            foreach ($cargos as $nombreCargo) {
                Cargo::updateOrCreate(
                    ['nombre' => $nombreCargo],
                    ['activo' => true]
                );
            }

            $unidades = [
                ['nombre' => 'unidad', 'abreviatura' => 'und'],
                ['nombre' => 'kilogramo', 'abreviatura' => 'kg'],
                ['nombre' => 'litro', 'abreviatura' => 'L'],
                ['nombre' => 'metro', 'abreviatura' => 'm'],
                ['nombre' => 'caja', 'abreviatura' => 'caja'],
                ['nombre' => 'par', 'abreviatura' => 'par'],
                ['nombre' => 'galón', 'abreviatura' => 'gal'],
            ];

            foreach ($unidades as $unidad) {
                UnidadMedida::updateOrCreate(
                    ['abreviatura' => $unidad['abreviatura']],
                    ['nombre' => $unidad['nombre'], 'activo' => true]
                );
            }

            if (config('app.debug')) {
                $this->crearUsuariosDemo($trujillo->id, $pataz->id);
            }
        });
    }

    private function crearUsuariosDemo(int $sedeTrujilloId, int $sedePatazId): void
    {
        $passwordDemo = Hash::make('Demo1234!');

        $usuarios = [
            ['email' => 'admin@mineria.pe', 'name' => 'Administrador General', 'rol' => RolUsuario::ADMIN, 'sede_id' => $sedeTrujilloId],
            ['email' => 'rrhh.jefe@mineria.pe', 'name' => 'Jefe de RR.HH.', 'rol' => RolUsuario::RRHH_JEFE, 'sede_id' => $sedeTrujilloId],
            ['email' => 'rrhh.asistente@mineria.pe', 'name' => 'Asistente de RR.HH.', 'rol' => RolUsuario::RRHH_ASISTENTE, 'sede_id' => $sedeTrujilloId],
            ['email' => 'logistica.jefe@mineria.pe', 'name' => 'Jefe de Logística', 'rol' => RolUsuario::LOGISTICA_JEFE, 'sede_id' => $sedeTrujilloId],
            ['email' => 'logistica.asistente@mineria.pe', 'name' => 'Asistente de Logística', 'rol' => RolUsuario::LOGISTICA_ASISTENTE, 'sede_id' => $sedeTrujilloId],
            ['email' => 'finanzas@mineria.pe', 'name' => 'Responsable de Finanzas', 'rol' => RolUsuario::FINANZAS, 'sede_id' => $sedeTrujilloId],
            ['email' => 'gerente@mineria.pe', 'name' => 'Gerente General', 'rol' => RolUsuario::GERENTE, 'sede_id' => $sedeTrujilloId],
            ['email' => 'recepcion.pataz@mineria.pe', 'name' => 'Recepción Pataz', 'rol' => RolUsuario::RECEPCION_PATAZ, 'sede_id' => $sedePatazId],
        ];

        foreach ($usuarios as $datos) {
            User::firstOrCreate(
                ['email' => $datos['email']],
                [
                    'name' => $datos['name'],
                    'password' => $passwordDemo,
                    'rol' => $datos['rol'],
                    'sede_id' => $datos['sede_id'],
                    'activo' => true,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
