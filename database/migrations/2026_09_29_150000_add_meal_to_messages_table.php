<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // copy of a shared meal (no photo), so it reads the same after the meal is edited or deleted
        Schema::table('messages', function (Blueprint $table) {
            $table->text('meal')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('meal');
        });
    }
};
