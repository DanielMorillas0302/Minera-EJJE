<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 40)->nullable()->after('password')->index();
            $table->unsignedBigInteger('sede_id')->nullable()->after('rol')->index();
            $table->string('telefono', 20)->nullable()->after('sede_id');
            $table->boolean('activo')->default(true)->after('telefono')->index();
            $table->timestamp('eliminado_en')->nullable()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rol', 'sede_id', 'telefono', 'activo', 'eliminado_en']);
        });
    }
};
