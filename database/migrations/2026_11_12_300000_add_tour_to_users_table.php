<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado del recorrido guiado por usuario, en el servidor: se conserva
     * aunque el usuario cambie de navegador o de equipo.
     *
     * Null para todos los usuarios existentes: la próxima vez que entren
     * recibirán la invitación (que pueden omitir).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // completado | omitido (App\Enums\EstadoTour); null = nunca lo vio.
            $table->string('tour_estado', 20)->nullable()->after('activo');
            $table->timestamp('tour_actualizado_at')->nullable()->after('tour_estado');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tour_estado', 'tour_actualizado_at']);
        });
    }
};
