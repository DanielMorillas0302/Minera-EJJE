<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_material', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->camposBase();
        });

        Schema::create('materiales', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 160);
            $table->string('descripcion', 255)->nullable();
            $table->foreignId('categoria_id')->constrained('categorias_material');
            $table->foreignId('unidad_medida_id')->constrained('unidades_medida');
            $table->decimal('stock_minimo', 12, 3)->default(0);
            $table->decimal('costo_referencial', 12, 2)->default(0);
            $table->camposBase();

            $table->index('nombre', 'idx_materiales_nombre');
        });

        Schema::create('proveedores', function (Blueprint $table): void {
            $table->id();
            $table->string('ruc', 11)->unique();
            $table->string('razon_social', 180);
            $table->string('contacto', 120)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->string('condicion_pago', 60)->default('CONTADO');
            $table->text('observaciones')->nullable();
            $table->camposBase();

            $table->index('razon_social', 'idx_proveedores_razon');
        });

        Schema::create('almacenes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 120);
            $table->foreignId('sede_id')->constrained('sedes');
            $table->boolean('es_principal')->default(false);
            $table->camposBase();
        });

        Schema::create('stock_almacenes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('almacen_id')->constrained('almacenes');
            $table->foreignId('material_id')->constrained('materiales');
            $table->decimal('cantidad', 12, 3)->default(0);
            $table->camposBase();

            $table->unique(['almacen_id', 'material_id'], 'uq_stock_almacen_material');
        });

        Schema::create('movimientos_inventario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('almacen_id')->constrained('almacenes');
            $table->foreignId('material_id')->constrained('materiales');
            $table->string('tipo', 30);
            $table->decimal('cantidad', 12, 3);
            $table->decimal('saldo_resultante', 12, 3);
            $table->dateTime('fecha_hora');
            $table->foreignId('usuario_id')->constrained('users');
            $table->string('documento_tipo', 120)->nullable();
            $table->unsignedBigInteger('documento_id')->nullable();
            $table->text('observacion')->nullable();
            $table->camposBase();

            $table->index(['almacen_id', 'material_id', 'fecha_hora'], 'idx_mov_alm_mat_fecha');
            $table->index(['documento_tipo', 'documento_id'], 'idx_mov_documento');
        });

        Schema::create('correlativos_documentos', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo', 16);
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();

            $table->unique(['tipo', 'anio'], 'uq_correlativo_tipo_anio');
        });

        Schema::create('requerimientos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 24)->unique();
            $table->date('fecha_solicitud');
            $table->foreignId('area_id')->constrained('areas');
            $table->foreignId('sede_id')->constrained('sedes');
            $table->foreignId('solicitante_id')->constrained('users');
            $table->string('prioridad', 20)->default('MEDIA');
            $table->string('estado', 30)->default('BORRADOR');
            $table->foreignId('aprobado_por_id')->nullable()->constrained('users');
            $table->dateTime('fecha_aprobacion')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->text('observaciones')->nullable();
            $table->camposBase();

            $table->index(['estado', 'fecha_solicitud'], 'idx_req_estado_fecha');
        });

        Schema::create('requerimiento_detalles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requerimiento_id')->constrained('requerimientos');
            $table->foreignId('material_id')->constrained('materiales');
            $table->decimal('cantidad_solicitada', 12, 3);
            $table->decimal('cantidad_atendida', 12, 3)->default(0);
            $table->text('observacion')->nullable();
            $table->camposBase();

            $table->unique(['requerimiento_id', 'material_id'], 'uq_req_det_material');
        });

        Schema::create('envios', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 24)->unique();
            $table->foreignId('requerimiento_id')->nullable()->constrained('requerimientos');
            $table->foreignId('almacen_origen_id')->constrained('almacenes');
            $table->foreignId('almacen_destino_id')->constrained('almacenes');
            $table->date('fecha_envio')->nullable();
            $table->date('fecha_estimada_llegada')->nullable();
            $table->date('fecha_recepcion')->nullable();
            $table->foreignId('responsable_envio_id')->constrained('users');
            $table->foreignId('recibido_por_id')->nullable()->constrained('users');
            $table->string('estado', 40)->default('PREPARADO');
            $table->string('guia_remision', 60)->nullable();
            $table->text('observaciones')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->camposBase();

            $table->index(['estado', 'fecha_estimada_llegada'], 'idx_env_estado_llegada');
        });

        Schema::create('envio_detalles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('envio_id')->constrained('envios');
            $table->foreignId('material_id')->constrained('materiales');
            $table->foreignId('requerimiento_detalle_id')->nullable()->constrained('requerimiento_detalles');
            $table->decimal('cantidad_enviada', 12, 3);
            $table->decimal('cantidad_recibida', 12, 3)->nullable();
            $table->text('observacion_recepcion')->nullable();
            $table->camposBase();

            $table->unique(['envio_id', 'material_id'], 'uq_env_det_material');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envio_detalles');
        Schema::dropIfExists('envios');
        Schema::dropIfExists('requerimiento_detalles');
        Schema::dropIfExists('requerimientos');
        Schema::dropIfExists('correlativos_documentos');
        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('stock_almacenes');
        Schema::dropIfExists('almacenes');
        Schema::dropIfExists('proveedores');
        Schema::dropIfExists('materiales');
        Schema::dropIfExists('categorias_material');
    }
};
