<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('window_days');
            $table->unsignedInteger('avg_intake_calories');
            $table->decimal('weight_change_kg', 6, 2);
            $table->unsignedInteger('implied_tdee');
            $table->unsignedInteger('current_calories');
            $table->unsignedInteger('suggested_calories');
            $table->json('suggested_macros');
            $table->text('message');
            $table->string('status')->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_adjustments');
    }
};
