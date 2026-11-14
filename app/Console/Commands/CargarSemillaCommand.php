<?php

namespace App\Console\Commands;

use Database\Seeders\CoreSeeder;
use Database\Seeders\Fase7Seeder;
use Database\Seeders\FinanzasSeeder;
use Database\Seeders\LogisticaSeeder;
use Database\Seeders\RrhhSeeder;
use Illuminate\Console\Command;

class CargarSemillaCommand extends Command
{
    protected $signature = 'cargar_semilla';

    protected $description = 'Carga datos iniciales idempotentes del Sistema Integrado de Minería (catálogos core, usuarios y datos demo en DEBUG).';

    public function handle(
        CoreSeeder $coreSeeder,
        RrhhSeeder $rrhhSeeder,
        LogisticaSeeder $logisticaSeeder,
        FinanzasSeeder $finanzasSeeder,
        Fase7Seeder $fase7Seeder
    ): int {
        $this->info('Iniciando carga de datos semilla (Core, RR.HH., Logística, Finanzas y Fase 7)...');

        $coreSeeder->run();
        $rrhhSeeder->run();
        $logisticaSeeder->run();
        $finanzasSeeder->run();
        $fase7Seeder->run();

        $this->info('Datos semilla cargados correctamente.');

        if (config('app.debug')) {
            $this->line('Usuarios demo disponibles (contraseña: Demo1234!): admin@mineria.pe, rrhh.jefe@mineria.pe, rrhh.asistente@mineria.pe, logistica.jefe@mineria.pe, logistica.asistente@mineria.pe, finanzas@mineria.pe, gerente@mineria.pe, recepcion.pataz@mineria.pe');
        } else {
            $this->warn('Modo DEBUG desactivado: no se crearon usuarios demo.');
        }

        return self::SUCCESS;
    }
}
