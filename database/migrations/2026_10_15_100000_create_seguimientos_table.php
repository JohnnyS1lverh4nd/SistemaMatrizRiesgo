<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seguimiento de riesgos (RF-4): histórico que no se edita ni se borra.
     * Un registro equivocado se anula con motivo y sigue visible.
     */
    public function up(): void
    {
        Schema::create('seguimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('riesgo_id')->constrained('riesgos')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->date('fecha');
            $table->text('descripcion');

            // Cambio de estado del tratamiento hecho con este registro (si lo hubo).
            $table->string('estado_tratamiento_anterior', 20)->nullable();
            $table->string('estado_tratamiento_nuevo', 20)->nullable();

            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('motivo_anulacion')->nullable();

            $table->timestamps();

            $table->index(['riesgo_id', 'fecha']);
        });

        Schema::create('evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seguimiento_id')->constrained('seguimientos')->restrictOnDelete();

            $table->string('nombre_original');
            // Ruta interna en el disco privado; nunca se expone en la API.
            $table->string('ruta')->unique();
            $table->string('mime', 150);
            $table->unsignedBigInteger('tamano');
            // Huella del archivo: permite verificar que no se alteró.
            $table->char('sha256', 64);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidencias');
        Schema::dropIfExists('seguimientos');
    }
};
