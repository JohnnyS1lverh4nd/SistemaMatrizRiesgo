<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada fila es una EDICIÓN de la matriz de riesgos de un proceso
     * ("Edición 3", "Enero-Marzo 2026"...), como cada pestaña de los Excel.
     */
    public function up(): void
    {
        Schema::create('matrices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('proceso_id')
                ->constrained('procesos')
                ->restrictOnDelete();

            $table->string('nombre');
            $table->date('fecha_revision');
            $table->string('estado', 20)->default('borrador');

            // Edición de la que se clonó: permite comparar ediciones (RF-5).
            $table->foreignId('edicion_anterior_id')
                ->nullable()
                ->constrained('matrices')
                ->restrictOnDelete();

            $table->timestamp('publicada_at')->nullable();
            $table->timestamp('archivada_at')->nullable();
            $table->timestamps();
            // Solo se usa para descartar borradores nunca publicados.
            $table->softDeletes();

            $table->index(['proceso_id', 'estado']);
            $table->index('fecha_revision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matrices');
    }
};
