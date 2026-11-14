<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Órdenes de compra
        Schema::create('ordenes_compra', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('requerimiento_id')->nullable()->constrained('requerimientos')->nullOnDelete();
            $table->foreignId('almacen_destino_id')->constrained('almacenes');
            $table->date('fecha');
            $table->date('fecha_entrega_esperada')->nullable();
            $table->string('estado', 25)->default('BORRADOR');
            $table->string('moneda', 5)->default('PEN');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('igv', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('condiciones_pago', 100)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('aprobado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->camposBase();

            $table->index('fecha', 'idx_oc_fecha');
            $table->index('estado', 'idx_oc_estado');
            $table->index('proveedor_id', 'idx_oc_proveedor');
            $table->index('requerimiento_id', 'idx_oc_requerimiento');
            $table->index('almacen_destino_id', 'idx_oc_almacen_destino');
        });

        // 2. Detalles de orden de compra
        Schema::create('orden_compra_detalles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materiales');
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('cantidad_recibida', 12, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->camposBase();

            $table->index('orden_compra_id', 'idx_oc_det_orden');
            $table->index('material_id', 'idx_oc_det_material');
        });

        // 3. Notificaciones del sistema
        Schema::create('notificaciones_sistema', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo', 50);
            $table->string('modulo', 30);
            $table->string('nivel', 20)->default('INFO');
            $table->string('titulo', 200);
            $table->text('mensaje');
            $table->string('url_accion', 255)->nullable();
            $table->boolean('leida')->default(false);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rol_destinatario', 50)->nullable();
            $table->timestamp('fecha_leida')->nullable();
            $table->camposBase();

            $table->index('tipo', 'idx_notif_tipo');
            $table->index(['modulo', 'nivel'], 'idx_notif_modulo_nivel');
            $table->index(['usuario_id', 'leida'], 'idx_notif_usuario_leida');
            $table->index(['rol_destinatario', 'leida'], 'idx_notif_rol_leida');
        });

        // 4. Columnas de bloqueo por intentos fallidos en users
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedTinyInteger('intentos_fallidos')->default(0)->after('activo');
            $table->timestamp('bloqueado_hasta')->nullable()->after('intentos_fallidos');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['intentos_fallidos', 'bloqueado_hasta']);
        });

        Schema::dropIfExists('notificaciones_sistema');
        Schema::dropIfExists('orden_compra_detalles');
        Schema::dropIfExists('ordenes_compra');
    }
};
