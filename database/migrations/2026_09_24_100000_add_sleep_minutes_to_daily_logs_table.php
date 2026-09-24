<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_logs', function (Blueprint $table) {
            // the night before this date, i.e. logged on the day you woke up
            $table->unsignedSmallInteger('sleep_minutes')->default(0)->after('exercise_calories');
        });
    }

    public function down(): void
    {
        Schema::table('daily_logs', function (Blueprint $table) {
            $table->dropColumn('sleep_minutes');
        });
    }
};
