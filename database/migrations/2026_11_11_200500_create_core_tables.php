<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('tipo', 30);
            $table->string('ubicacion', 150)->nullable();
            $table->camposBase();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->camposBase();

            $table->unique(['nombre', 'sede_id']);
        });

        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->camposBase();
        });

        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->string('abreviatura', 20)->unique();
            $table->camposBase();
        });

        Schema::create('adjuntos', function (Blueprint $table) {
            $table->id();
            $table->string('archivo', 255);
            $table->string('nombre_original', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('tamano')->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->morphs('entidad');
            $table->camposBase();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('sede_id')
                ->references('id')
                ->on('sedes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['sede_id']);
        });

        Schema::dropIfExists('adjuntos');
        Schema::dropIfExists('unidades_medida');
        Schema::dropIfExists('cargos');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('sedes');
    }
};
