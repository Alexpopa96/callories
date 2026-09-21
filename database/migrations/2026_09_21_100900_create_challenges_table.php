<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('goal', 12)->default('lose_weight');
            $table->string('status', 10)->default('active');
            $table->decimal('start_weight_kg', 5, 1);
            $table->decimal('target_weight_kg', 5, 1);
            $table->unsignedSmallInteger('days');
            $table->date('started_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('calorie_goal');
            $table->unsignedSmallInteger('protein_goal_g');
            $table->unsignedSmallInteger('carbs_goal_g');
            $table->unsignedSmallInteger('fat_goal_g');
            $table->unsignedSmallInteger('water_goal_ml');
            $table->smallInteger('daily_calorie_delta');
            $table->boolean('adjusted')->default(false);
            $table->string('explanation', 500);
            $table->unsignedTinyInteger('notified_milestone_pct')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
