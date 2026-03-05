<?php

use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\QuizRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns question image urls in room state payload', function () {
    $quiz = Quiz::query()->create([
        'title' => 'Image quiz',
        'description' => 'Test image payload.',
        'is_active' => true,
    ]);

    $question = QuizQuestion::query()->create([
        'quiz_id' => $quiz->id,
        'question_text' => null,
        'question_images' => [
            'quiz-questions/question-1.png',
            'quiz-questions/question-2.png',
        ],
        'question_order' => 1,
        'answer_seconds' => 30,
        'has_correct_option' => false,
    ]);

    QuizOption::query()->create([
        'quiz_question_id' => $question->id,
        'option_text' => 'Option A',
        'option_order' => 1,
        'is_correct' => false,
    ]);

    QuizOption::query()->create([
        'quiz_question_id' => $question->id,
        'option_text' => 'Option B',
        'option_order' => 2,
        'is_correct' => false,
    ]);

    QuizRoom::query()->create([
        'quiz_id' => $quiz->id,
        'room_code' => 'ROOMTEXT',
        'status' => 'question_open',
        'current_question_id' => $question->id,
        'current_question_started_at' => now(),
        'current_question_ends_at' => now()->addMinutes(1),
    ]);

    $response = $this->getJson('/api/quiz/rooms/ROOMTEXT/state');

    $response
        ->assertSuccessful()
        ->assertJsonPath('question.text', null)
        ->assertJsonPath('question.image_urls.0', '/storage/quiz-questions/question-1.png')
        ->assertJsonPath('question.image_urls.1', '/storage/quiz-questions/question-2.png');
});

it('returns question image urls in room results payload', function () {
    $quiz = Quiz::query()->create([
        'title' => 'Result image quiz',
        'description' => 'Test result image payload.',
        'is_active' => true,
    ]);

    $question = QuizQuestion::query()->create([
        'quiz_id' => $quiz->id,
        'question_text' => 'Question has text and images.',
        'question_images' => [
            'quiz-questions/question-a.png',
            'quiz-questions/question-b.png',
        ],
        'question_order' => 1,
        'answer_seconds' => 25,
        'has_correct_option' => false,
    ]);

    QuizRoom::query()->create([
        'quiz_id' => $quiz->id,
        'room_code' => 'ROOMIMG',
        'status' => 'showing_result',
        'current_question_id' => $question->id,
    ]);

    $response = $this->getJson('/api/quiz/rooms/ROOMIMG/results?question_id='.$question->id);

    $response
        ->assertSuccessful()
        ->assertJsonPath('question_text', 'Question has text and images.')
        ->assertJsonPath('question_image_urls.0', '/storage/quiz-questions/question-a.png')
        ->assertJsonPath('question_image_urls.1', '/storage/quiz-questions/question-b.png');
});
