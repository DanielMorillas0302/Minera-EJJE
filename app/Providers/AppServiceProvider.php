<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Compatibilidad con índices utf8mb4 en MySQL/MariaDB (Hostinger)
        Schema::defaultStringLength(191);

        Carbon::setLocale('es');
        date_default_timezone_set((string) config('app.timezone', 'America/Lima'));

        Blueprint::macro('camposBase', function (): void {
            /** @var Blueprint $this */
            $this->boolean('activo')->default(true)->index();
            $this->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $this->timestamp('creado_en')->nullable();
            $this->timestamp('actualizado_en')->nullable();
            $this->timestamp('eliminado_en')->nullable();
        });

        Blueprint::macro('dropCamposBase', function (): void {
            /** @var Blueprint $this */
            $this->dropConstrainedForeignId('creado_por');
            $this->dropConstrainedForeignId('actualizado_por');
            $this->dropColumn(['activo', 'creado_en', 'actualizado_en', 'eliminado_en']);
        });
    }
}
