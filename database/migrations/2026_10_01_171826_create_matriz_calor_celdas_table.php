<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matriz_calor_celdas', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('probabilidad');
            $table->unsignedTinyInteger('impacto');

            $table->foreignId('nivel_riesgo_id')
                ->constrained('niveles_riesgo')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(['probabilidad', 'impacto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriz_calor_celdas');
    }
};