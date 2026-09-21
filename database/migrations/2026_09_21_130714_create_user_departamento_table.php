<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_departamento', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('departamento_id')
                ->constrained('departamentos')
                ->cascadeOnDelete();

            $table->unique(['user_id', 'departamento_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_departamento');
    }
};