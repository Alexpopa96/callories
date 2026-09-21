<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sex', 1)->nullable();
            $table->date('birth_date')->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->string('activity_level', 12)->default('light');
            $table->string('goal_type', 10)->default('maintain');
            $table->unsignedSmallInteger('protein_goal_g')->nullable();
            $table->unsignedSmallInteger('carbs_goal_g')->nullable();
            $table->unsignedSmallInteger('fat_goal_g')->nullable();
            $table->boolean('remind_meals')->default(false);
            $table->boolean('remind_water')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'sex', 'birth_date', 'height_cm', 'activity_level', 'goal_type',
                'protein_goal_g', 'carbs_goal_g', 'fat_goal_g', 'remind_meals', 'remind_water',
            ]);
        });
    }
};
