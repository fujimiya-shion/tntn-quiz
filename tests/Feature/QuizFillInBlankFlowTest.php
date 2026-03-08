<?php

use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\QuizRoom;
use App\Models\RoomAnswer;
use App\Models\RoomPlayer;
use App\QuizQuestionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('normalizes fill in the blank answers and compares with the expected answer', function () {
    $quiz = Quiz::query()->create([
        'title' => 'Fill blank quiz',
        'description' => 'Test fill in the blank answer.',
        'is_active' => true,
    ]);

    $question = QuizQuestion::query()->create([
        'quiz_id' => $quiz->id,
        'question_text' => 'Thủ đô Việt Nam là gì?',
        'quiz_question_type' => QuizQuestionType::FillInTheBlank->value,
        'question_order' => 1,
        'answer_seconds' => 30,
        'has_correct_option' => false,
        'fill_blank_answer' => 'Hà Nội',
    ]);

    $room = QuizRoom::query()->create([
        'quiz_id' => $quiz->id,
        'room_code' => 'FILLTEST',
        'status' => 'question_open',
        'current_question_id' => $question->id,
        'current_question_started_at' => now(),
        'current_question_ends_at' => now()->addMinute(),
    ]);

    $player = RoomPlayer::query()->create([
        'quiz_room_id' => $room->id,
        'player_token' => (string) Str::uuid(),
        'display_name' => 'Player A',
        'gender' => 'male',
        'is_host' => false,
        'joined_at' => now(),
        'last_seen_at' => now(),
    ]);

    $this->postJson('/api/quiz/rooms/FILLTEST/answers', [
        'player_token' => $player->player_token,
        'answer_text' => 'ha noi',
    ])->assertSuccessful();

    $answer = RoomAnswer::query()
        ->where('quiz_room_id', $room->id)
        ->where('quiz_question_id', $question->id)
        ->where('room_player_id', $player->id)
        ->first();

    expect($answer)->not->toBeNull();
    expect($answer?->normalized_answer_text)->toBe('ha-noi');
    expect($answer?->is_correct)->toBeTrue();

    $this->getJson('/api/quiz/rooms/FILLTEST/results?question_id='.$question->id.'&player_token='.$player->player_token)
        ->assertSuccessful()
        ->assertJsonPath('quiz_question_type', QuizQuestionType::FillInTheBlank->value)
        ->assertJsonPath('overview.selected_answer_text', 'ha noi')
        ->assertJsonPath('overview.selected_normalized_answer_text', 'ha-noi')
        ->assertJsonPath('overview.selected_answer_is_correct', true)
        ->assertJsonPath('options.0.normalized_answer_text', 'ha-noi')
        ->assertJsonPath('options.0.is_correct', true);
});

it('supports mixed question types in the same quiz room state', function () {
    $quiz = Quiz::query()->create([
        'title' => 'Mixed quiz',
        'description' => 'Test mixed question types.',
        'is_active' => true,
    ]);

    $multipleChoiceQuestion = QuizQuestion::query()->create([
        'quiz_id' => $quiz->id,
        'question_text' => '2 + 2 bằng?',
        'quiz_question_type' => QuizQuestionType::MultipleChoice->value,
        'question_order' => 1,
        'answer_seconds' => 20,
        'has_correct_option' => true,
    ]);

    QuizOption::query()->create([
        'quiz_question_id' => $multipleChoiceQuestion->id,
        'option_text' => '3',
        'option_order' => 1,
        'is_correct' => false,
    ]);

    QuizOption::query()->create([
        'quiz_question_id' => $multipleChoiceQuestion->id,
        'option_text' => '4',
        'option_order' => 2,
        'is_correct' => true,
    ]);

    $fillBlankQuestion = QuizQuestion::query()->create([
        'quiz_id' => $quiz->id,
        'question_text' => 'Điền vào chỗ trống: Việt Nam - Hà ____',
        'quiz_question_type' => QuizQuestionType::FillInTheBlank->value,
        'question_order' => 2,
        'answer_seconds' => 20,
        'has_correct_option' => false,
        'fill_blank_answer' => 'Nội',
    ]);

    $room = QuizRoom::query()->create([
        'quiz_id' => $quiz->id,
        'room_code' => 'MIXED001',
        'status' => 'question_open',
        'current_question_id' => $multipleChoiceQuestion->id,
        'current_question_started_at' => now(),
        'current_question_ends_at' => now()->addMinute(),
    ]);

    $this->getJson('/api/quiz/rooms/MIXED001/state')
        ->assertSuccessful()
        ->assertJsonPath('question.quiz_question_type', QuizQuestionType::MultipleChoice->value)
        ->assertJsonCount(2, 'question.options');

    $room->update([
        'current_question_id' => $fillBlankQuestion->id,
    ]);

    $this->getJson('/api/quiz/rooms/MIXED001/state')
        ->assertSuccessful()
        ->assertJsonPath('question.quiz_question_type', QuizQuestionType::FillInTheBlank->value)
        ->assertJsonCount(0, 'question.options');
});
