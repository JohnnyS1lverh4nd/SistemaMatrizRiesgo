<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riesgos de una edición de matriz: las 13 columnas del Excel (RF-3).
     * El seguimiento/evidencias es una entidad aparte (Fase 6).
     */
    public function up(): void
    {
        Schema::create('riesgos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('matriz_id')->constrained('matrices')->restrictOnDelete();
            // Mismo riesgo en la edición de la que se clonó (comparación, RF-5).
            $table->foreignId('riesgo_origen_id')->nullable()->constrained('riesgos')->restrictOnDelete();
            $table->unsignedInteger('orden');

            // Identificación
            $table->text('actividad');
            $table->text('descripcion');
            $table->text('causas')->nullable();
            $table->text('control_actual')->nullable();

            // Análisis: P e I vacías = riesgo aún sin evaluar (solo en borradores).
            $table->unsignedTinyInteger('probabilidad')->nullable();
            $table->unsignedTinyInteger('impacto')->nullable();
            // Nivel copiado de la matriz de calor al guardar: el histórico no cambia
            // si después se modifica la matriz de calor.
            $table->foreignId('nivel_calculado_id')->nullable()->constrained('niveles_riesgo')->restrictOnDelete();
            $table->foreignId('nivel_ajustado_id')->nullable()->constrained('niveles_riesgo')->restrictOnDelete();
            $table->text('justificacion_ajuste')->nullable();
            $table->text('sintoma')->nullable();

            // Seguimiento y control
            $table->text('tratamiento')->nullable();
            $table->text('responsable')->nullable();
            $table->foreignId('periodicidad_id')->nullable()->constrained('periodicidades')->restrictOnDelete();
            $table->string('estado_tratamiento', 20)->default('pendiente');

            $table->timestamps();
            // Ningún riesgo se borra físicamente (requisito de trazabilidad).
            $table->softDeletes();

            $table->index(['matriz_id', 'orden']);
        });

        Schema::create('riesgo_factor', function (Blueprint $table) {
            $table->foreignId('riesgo_id')->constrained('riesgos')->restrictOnDelete();
            $table->foreignId('factor_riesgo_id')->constrained('factores_riesgo')->restrictOnDelete();

            $table->primary(['riesgo_id', 'factor_riesgo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riesgo_factor');
        Schema::dropIfExists('riesgos');
    }
};
