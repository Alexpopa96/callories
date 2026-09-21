<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('calorie_goal')->default(2000);
            $table->unsignedInteger('steps_goal')->default(10000);
            $table->unsignedInteger('water_goal_ml')->default(2500);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['calorie_goal', 'steps_goal', 'water_goal_ml']);
        });
    }
};
