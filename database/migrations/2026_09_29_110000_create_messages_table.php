<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['sender_id', 'recipient_id', 'id']);
            $table->index(['recipient_id', 'read_at']);
        });

        Schema::create('message_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            // copy of the text at report time, kept even if the conversation changes later
            $table->text('body');
            $table->string('reason', 500)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['reporter_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reports');
        Schema::dropIfExists('messages');
    }
};
