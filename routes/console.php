<?php

use App\Services\Rrhh\VacacionesPermisosService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rrhh:actualizar-estados', function (VacacionesPermisosService $servicio): void {
    $actualizados = $servicio->actualizarEstadosTomados();
    $this->info("Registros RR.HH. actualizados a TOMADA: {$actualizados}");
})->purpose('Marca como TOMADA las vacaciones y permisos aprobados cuya fecha de fin ya venció.');

Schedule::command('rrhh:actualizar-estados')->dailyAt('00:15');
