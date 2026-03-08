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
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->unsignedTinyInteger('quiz_question_type')
                ->default(1)
                ->after('question_text');
            $table->string('fill_blank_answer')
                ->nullable()
                ->after('has_correct_option');
            $table->index('quiz_question_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropIndex(['quiz_question_type']);
            $table->dropColumn(['quiz_question_type', 'fill_blank_answer']);
        });
    }
};
