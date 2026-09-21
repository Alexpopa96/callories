<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('eaten_on');
            $table->string('title');
            $table->string('photo_path')->nullable();
            $table->json('items');
            $table->unsignedInteger('calories');
            $table->decimal('protein_g', 6, 1)->default(0);
            $table->decimal('carbs_g', 6, 1)->default(0);
            $table->decimal('fat_g', 6, 1)->default(0);
            $table->decimal('fiber_g', 6, 1)->default(0);
            $table->string('confidence', 10)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'eaten_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
