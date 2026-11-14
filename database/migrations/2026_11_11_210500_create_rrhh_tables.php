<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabajadores', function (Blueprint $table) {
            $table->id();
            $table->string('dni', 8)->unique();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->date('fecha_nacimiento');
            $table->string('sexo', 10);
            $table->string('telefono', 20)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->string('contacto_emergencia_nombre', 120)->nullable();
            $table->string('contacto_emergencia_telefono', 20)->nullable();
            $table->foreignId('cargo_id')->constrained('cargos')->restrictOnDelete();
            $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();
            $table->foreignId('sede_base_id')->constrained('sedes')->restrictOnDelete();
            $table->date('fecha_ingreso');
            $table->date('fecha_cese')->nullable();
            $table->string('estado', 20)->default('ACTIVO')->index();
            $table->string('ubicacion_actual', 20)->default('TRUJILLO')->index();
            $table->string('esquema_rotacion', 30)->nullable();
            $table->string('foto', 255)->nullable();
            $table->camposBase();
        });

        Schema::create('examenes_ocupacionales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->date('fecha_examen');
            $table->date('fecha_vencimiento')->index();
            $table->string('clinica', 150);
            $table->string('resultado', 30);
            $table->text('observaciones')->nullable();
            $table->camposBase();
        });

        Schema::create('periodos_vacacionales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->restrictOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedSmallInteger('dias_ganados')->default(30);
            $table->unsignedSmallInteger('dias_tomados')->default(0);
            $table->camposBase();

            $table->unique(['trabajador_id', 'anio']);
        });

        Schema::create('solicitudes_vacaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->restrictOnDelete();
            $table->foreignId('periodo_vacacional_id')->constrained('periodos_vacacionales')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedSmallInteger('dias');
            $table->string('estado', 20)->default('PENDIENTE')->index();
            $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_resolucion')->nullable();
            $table->text('comentario')->nullable();
            $table->camposBase();
        });

        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->boolean('con_goce')->default(true);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->text('motivo');
            $table->string('estado', 20)->default('PENDIENTE')->index();
            $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_resolucion')->nullable();
            $table->text('comentario')->nullable();
            $table->camposBase();
        });

        Schema::create('movimientos_personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->dateTime('fecha_hora')->index();
            $table->foreignId('origen_sede_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('destino_sede_id')->constrained('sedes')->restrictOnDelete();
            $table->string('transporte', 120)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->camposBase();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_personal');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('solicitudes_vacaciones');
        Schema::dropIfExists('periodos_vacacionales');
        Schema::dropIfExists('examenes_ocupacionales');
        Schema::dropIfExists('trabajadores');
    }
};
