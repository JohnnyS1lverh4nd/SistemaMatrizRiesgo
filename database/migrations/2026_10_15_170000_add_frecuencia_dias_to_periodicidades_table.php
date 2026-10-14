<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada cuántos días toca registrar seguimiento según la periodicidad;
     * null = sin plazo ("Cuando ocurra"). Sirve para detectar atrasos.
     */
    private const FRECUENCIAS = [
        'diario' => 1,
        'mensual' => 30,
        'trimestral' => 90,
        'cuatrimestral' => 120,
        'anual' => 365,
    ];

    public function up(): void
    {
        Schema::table('periodicidades', function (Blueprint $table) {
            $table->unsignedSmallInteger('frecuencia_dias')->nullable()->after('slug');
        });

        // Solo completa los valores vacíos de las periodicidades conocidas.
        foreach (self::FRECUENCIAS as $slug => $dias) {
            DB::table('periodicidades')->where('slug', $slug)->whereNull('frecuencia_dias')->update(['frecuencia_dias' => $dias]);
        }
    }

    public function down(): void
    {
        Schema::table('periodicidades', function (Blueprint $table) {
            $table->dropColumn('frecuencia_dias');
        });
    }
};
