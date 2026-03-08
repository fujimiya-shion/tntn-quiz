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
        Schema::table('room_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('quiz_option_id')
                ->nullable()
                ->change();
            $table->string('answer_text')
                ->nullable()
                ->after('quiz_option_id');
            $table->string('normalized_answer_text')
                ->nullable()
                ->after('answer_text');
            $table->boolean('is_correct')
                ->nullable()
                ->after('normalized_answer_text');
            $table->index(['quiz_room_id', 'quiz_question_id', 'normalized_answer_text'], 'room_answers_room_question_normalized_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_answers', function (Blueprint $table) {
            $table->dropIndex('room_answers_room_question_normalized_index');
            $table->dropColumn(['answer_text', 'normalized_answer_text', 'is_correct']);
            $table->unsignedBigInteger('quiz_option_id')
                ->nullable(false)
                ->change();
        });
    }
};
