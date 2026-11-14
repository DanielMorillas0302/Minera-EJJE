<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros_costo', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 120);
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('descripcion', 255)->nullable();
            $table->camposBase();

            $table->index('nombre', 'idx_centros_costo_nombre');
        });

        Schema::create('categorias_gasto', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 120);
            $table->string('tipo', 20)->default('EGRESO'); // INGRESO, EGRESO
            $table->string('descripcion', 255)->nullable();
            $table->camposBase();

            $table->unique(['nombre', 'tipo'], 'uq_cat_gasto_nombre_tipo');
        });

        Schema::create('presupuestos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('centro_costo_id')->constrained('centros_costo');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes'); // 1 a 12
            $table->decimal('monto_presupuestado', 14, 2)->default(0);
            $table->text('notas')->nullable();
            $table->camposBase();

            $table->unique(['centro_costo_id', 'anio', 'mes'], 'uq_presupuesto_cc_anio_mes');
            $table->index(['anio', 'mes'], 'idx_presupuesto_anio_mes');
        });

        Schema::create('movimientos_financieros', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo', 20); // INGRESO, EGRESO
            $table->date('fecha');
            $table->decimal('monto', 14, 2);
            $table->string('moneda', 5)->default('PEN');
            $table->foreignId('categoria_id')->constrained('categorias_gasto');
            $table->foreignId('centro_costo_id')->constrained('centros_costo');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('concepto', 255);
            $table->string('comprobante_tipo', 20)->default('FACTURA'); // FACTURA, BOLETA, RECIBO, OTRO
            $table->string('comprobante_numero', 60)->nullable();
            $table->string('estado_pago', 20)->default('PAGADO'); // PENDIENTE, PAGADO
            $table->date('fecha_vencimiento')->nullable();
            $table->date('fecha_pago')->nullable();
            $table->foreignId('envio_id')->nullable()->constrained('envios')->nullOnDelete();
            $table->unsignedBigInteger('orden_compra_id')->nullable();
            $table->foreignId('trabajador_id')->nullable()->constrained('trabajadores')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->camposBase();

            $table->index('fecha', 'idx_mov_fin_fecha');
            $table->index(['tipo', 'fecha'], 'idx_mov_fin_tipo_fecha');
            $table->index(['estado_pago', 'fecha_vencimiento'], 'idx_mov_fin_pago_venc');
            $table->index('envio_id', 'idx_mov_fin_envio');
            $table->index('trabajador_id', 'idx_mov_fin_trabajador');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_financieros');
        Schema::dropIfExists('presupuestos');
        Schema::dropIfExists('categorias_gasto');
        Schema::dropIfExists('centros_costo');
    }
};
