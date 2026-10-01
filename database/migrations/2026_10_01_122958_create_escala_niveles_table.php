<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escala_niveles', function (Blueprint $table) {
            $table->id();
            $table->string('tipo');
            $table->unsignedTinyInteger('valor');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->unique(['tipo', 'valor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escala_niveles');
    }
};