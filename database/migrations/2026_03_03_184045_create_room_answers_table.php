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
        Schema::create('room_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quiz_room_id');
            $table->unsignedBigInteger('room_player_id');
            $table->unsignedBigInteger('quiz_question_id');
            $table->unsignedBigInteger('quiz_option_id');
            $table->boolean('is_late')->default(false);
            $table->timestamp('answered_at');
            $table->timestamps();

            $table->unique(['room_player_id', 'quiz_question_id']);
            $table->index('quiz_room_id');
            $table->index('room_player_id');
            $table->index('quiz_question_id');
            $table->index('quiz_option_id');
            $table->index(['quiz_room_id', 'quiz_question_id', 'quiz_option_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_answers');
    }
};
