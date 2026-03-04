<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quiz_rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quiz_id');
            $table->string('room_code', 32)->unique();
            $table->enum('status', ['waiting', 'question_open', 'showing_result', 'finished'])->default('waiting');
            $table->unsignedBigInteger('current_question_id')->nullable();
            $table->timestamp('current_question_started_at')->nullable();
            $table->timestamp('current_question_ends_at')->nullable();
            $table->timestamps();

            $table->index('quiz_id');
            $table->index('current_question_id');
            $table->index(['status', 'current_question_ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_rooms');
    }
};
