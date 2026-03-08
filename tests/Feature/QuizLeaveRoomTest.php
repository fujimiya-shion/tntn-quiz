<?php

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizRoom;
use App\Models\RoomAnswer;
use App\Models\RoomPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('removes player and their answers from room when leaving', function () {
    $quiz = Quiz::query()->create([
        'title' => 'Leave room quiz',
        'description' => 'Leave room test.',
        'is_active' => true,
    ]);

    $question = QuizQuestion::query()->create([
        'quiz_id' => $quiz->id,
        'question_text' => 'Question 1',
        'question_order' => 1,
        'answer_seconds' => 30,
        'has_correct_option' => false,
    ]);

    $room = QuizRoom::query()->create([
        'quiz_id' => $quiz->id,
        'room_code' => 'LEAVEROOM',
        'status' => 'question_open',
        'current_question_id' => $question->id,
        'current_question_started_at' => now(),
        'current_question_ends_at' => now()->addMinute(),
    ]);

    $player = RoomPlayer::query()->create([
        'quiz_room_id' => $room->id,
        'player_token' => (string) Str::uuid(),
        'display_name' => 'Player Leave',
        'gender' => 'male',
        'is_host' => false,
        'joined_at' => now(),
        'last_seen_at' => now(),
    ]);

    RoomAnswer::query()->create([
        'quiz_room_id' => $room->id,
        'room_player_id' => $player->id,
        'quiz_question_id' => $question->id,
        'quiz_option_id' => null,
        'answer_text' => 'test',
        'normalized_answer_text' => 'test',
        'is_correct' => false,
        'is_late' => false,
        'answered_at' => now(),
    ]);

    $this->postJson('/api/quiz/rooms/LEAVEROOM/leave', [
        'player_token' => $player->player_token,
    ])->assertSuccessful();

    expect(RoomPlayer::query()->whereKey($player->id)->exists())->toBeFalse();
    expect(RoomAnswer::query()->where('room_player_id', $player->id)->exists())->toBeFalse();
});
