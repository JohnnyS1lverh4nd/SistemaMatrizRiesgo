<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El código RT-01-NI-ONA-156 es del formulario institucional, igual para
     * todos los departamentos: pasa a config/matrices.php. La columna estaba
     * vacía en todas las bases revisadas (oct. 2026).
     */
    public function up(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropColumn('codigo_referencia');
        });
    }

    public function down(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->string('codigo_referencia')->nullable()->after('direccion');
        });
    }
};
