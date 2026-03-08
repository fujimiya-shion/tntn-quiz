<?php

namespace App\Http\Controllers\Api;

use App\Events\QuizRoomUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitAnswerRequest;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\QuizRoom;
use App\Models\RoomAnswer;
use App\Models\RoomPlayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class QuizPlayController extends Controller
{
    public function submitAnswer(SubmitAnswerRequest $request, string $roomCode): JsonResponse
    {
        $room = QuizRoom::query()->where('room_code', $roomCode)->first();

        if ($room === null) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        $this->syncRoomResultState($room);

        if ($room->status !== 'question_open' || $room->current_question_id === null) {
            return response()->json([
                'message' => 'Question is not open.',
            ], 422);
        }

        $player = RoomPlayer::query()
            ->where('quiz_room_id', $room->id)
            ->where('player_token', $request->string('player_token')->value())
            ->first();

        if ($player === null) {
            return response()->json([
                'message' => 'Invalid player token.',
            ], 403);
        }

        $question = QuizQuestion::query()
            ->where('id', $room->current_question_id)
            ->where('quiz_id', $room->quiz_id)
            ->first();

        if ($question === null) {
            return response()->json([
                'message' => 'Current question not found.',
            ], 422);
        }

        $alreadyAnswered = RoomAnswer::query()
            ->where('room_player_id', $player->id)
            ->where('quiz_question_id', $room->current_question_id)
            ->exists();

        if ($alreadyAnswered) {
            return response()->json([
                'message' => 'You already answered this question.',
            ], 409);
        }

        if ($question->isMultipleChoice()) {
            return $this->submitMultipleChoiceAnswer($request, $room, $question, $player);
        }

        return $this->submitFillInTheBlankAnswer($request, $room, $question, $player);
    }

    public function state(Request $request, string $roomCode): JsonResponse
    {
        $room = QuizRoom::query()->where('room_code', $roomCode)->first();

        if ($room === null) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        $this->syncRoomResultState($room);

        $question = null;
        $remainingSeconds = 0;
        $totalQuestions = QuizQuestion::query()
            ->where('quiz_id', $room->quiz_id)
            ->count();

        if ($room->current_question_id !== null) {
            $questionModel = QuizQuestion::query()->where('id', $room->current_question_id)->first();

            if ($questionModel !== null) {
                $question = [
                    'id' => $questionModel->id,
                    'text' => $questionModel->question_text,
                    'quiz_question_type' => (int) $questionModel->quiz_question_type->value,
                    'image_urls' => $questionModel->questionImageUrls(),
                    'question_order' => $questionModel->question_order,
                    'total_questions' => $totalQuestions,
                    'answer_seconds' => $questionModel->answer_seconds,
                    'options' => $questionModel->isMultipleChoice()
                        ? $questionModel->options()->orderBy('option_order')->get(['id', 'option_text', 'option_order'])
                        : [],
                ];
            }
        }

        if ($room->status === 'question_open' && $room->current_question_ends_at !== null) {
            $remainingSeconds = max(0, now()->diffInSeconds($room->current_question_ends_at, false));
        }

        $hasAnswered = false;
        $canFinishQuiz = false;
        $playerToken = $request->query('player_token');

        if (is_string($playerToken) && $room->current_question_id !== null) {
            $playerId = RoomPlayer::query()
                ->where('quiz_room_id', $room->id)
                ->where('player_token', $playerToken)
                ->value('id');

            if ($playerId !== null) {
                $hasAnswered = RoomAnswer::query()
                    ->where('room_player_id', $playerId)
                    ->where('quiz_question_id', $room->current_question_id)
                    ->exists();
            }
        }

        if ($room->current_question_id !== null && in_array($room->status, ['showing_result', 'finished'], true)) {
            $currentOrder = QuizQuestion::query()
                ->where('id', $room->current_question_id)
                ->value('question_order');

            if ($currentOrder !== null) {
                $hasNextQuestion = QuizQuestion::query()
                    ->where('quiz_id', $room->quiz_id)
                    ->where('question_order', '>', $currentOrder)
                    ->exists();

                $canFinishQuiz = ! $hasNextQuestion;
            }
        }

        return response()->json([
            'room_code' => $room->room_code,
            'status' => $room->status,
            'current_question_id' => $room->current_question_id,
            'remaining_seconds' => $remainingSeconds,
            'question' => $question,
            'has_answered' => $hasAnswered,
            'can_finish_quiz' => $canFinishQuiz,
        ]);
    }

    public function results(Request $request, string $roomCode): JsonResponse
    {
        $room = QuizRoom::query()->where('room_code', $roomCode)->first();

        if ($room === null) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        $questionId = (int) ($request->integer('question_id') ?: (int) $room->current_question_id);

        if ($questionId === 0) {
            return response()->json([
                'message' => 'question_id is required.',
            ], 422);
        }

        $question = QuizQuestion::query()
            ->where('id', $questionId)
            ->where('quiz_id', $room->quiz_id)
            ->first();

        if ($question === null) {
            return response()->json([
                'message' => 'Question not found in this room quiz.',
            ], 404);
        }

        $playerToken = $request->query('player_token');
        $selectedAnswer = null;

        if (is_string($playerToken) && $playerToken !== '') {
            $playerId = RoomPlayer::query()
                ->where('quiz_room_id', $room->id)
                ->where('player_token', $playerToken)
                ->value('id');

            if ($playerId !== null) {
                $selectedAnswer = RoomAnswer::query()
                    ->where('quiz_room_id', $room->id)
                    ->where('quiz_question_id', $question->id)
                    ->where('room_player_id', $playerId)
                    ->first(['quiz_option_id', 'answer_text', 'normalized_answer_text', 'is_correct']);
            }
        }

        if ($question->isMultipleChoice()) {
            return $this->multipleChoiceResults($room, $question, $selectedAnswer);
        }

        return $this->fillInTheBlankResults($room, $question, $selectedAnswer);
    }

    private function submitMultipleChoiceAnswer(
        SubmitAnswerRequest $request,
        QuizRoom $room,
        QuizQuestion $question,
        RoomPlayer $player,
    ): JsonResponse {
        $option = QuizOption::query()
            ->where('id', (int) $request->integer('quiz_option_id'))
            ->where('quiz_question_id', $room->current_question_id)
            ->first();

        if ($option === null) {
            return response()->json([
                'message' => 'Option does not belong to current question.',
            ], 422);
        }

        RoomAnswer::query()->create([
            'quiz_room_id' => $room->id,
            'room_player_id' => $player->id,
            'quiz_question_id' => $room->current_question_id,
            'quiz_option_id' => $option->id,
            'answer_text' => null,
            'normalized_answer_text' => null,
            'is_correct' => $question->has_correct_option ? (bool) $option->is_correct : null,
            'is_late' => false,
            'answered_at' => now(),
        ]);

        event(new QuizRoomUpdated($room->room_code, 'answer_submitted', [
            'question_id' => $room->current_question_id,
        ]));

        return response()->json([
            'message' => 'Answer submitted.',
        ]);
    }

    private function submitFillInTheBlankAnswer(
        SubmitAnswerRequest $request,
        QuizRoom $room,
        QuizQuestion $question,
        RoomPlayer $player,
    ): JsonResponse {
        $answerText = trim($request->string('answer_text')->value());

        if ($answerText === '') {
            return response()->json([
                'message' => 'answer_text is required for fill in the blank question.',
            ], 422);
        }

        $normalizedAnswerText = QuizQuestion::normalizeFillBlankText($answerText);

        if ($normalizedAnswerText === '') {
            return response()->json([
                'message' => 'answer_text is invalid.',
            ], 422);
        }

        $normalizedCorrectAnswer = $question->normalizedFillBlankAnswer();

        RoomAnswer::query()->create([
            'quiz_room_id' => $room->id,
            'room_player_id' => $player->id,
            'quiz_question_id' => $room->current_question_id,
            'quiz_option_id' => null,
            'answer_text' => $answerText,
            'normalized_answer_text' => $normalizedAnswerText,
            'is_correct' => $normalizedCorrectAnswer !== '' ? $normalizedAnswerText === $normalizedCorrectAnswer : null,
            'is_late' => false,
            'answered_at' => now(),
        ]);

        event(new QuizRoomUpdated($room->room_code, 'answer_submitted', [
            'question_id' => $room->current_question_id,
        ]));

        return response()->json([
            'message' => 'Answer submitted.',
        ]);
    }

    private function multipleChoiceResults(QuizRoom $room, QuizQuestion $question, ?RoomAnswer $selectedAnswer): JsonResponse
    {
        $stats = RoomAnswer::query()
            ->selectRaw('room_answers.quiz_option_id, SUM(CASE WHEN room_players.gender = "male" THEN 1 ELSE 0 END) AS male_count, SUM(CASE WHEN room_players.gender = "female" THEN 1 ELSE 0 END) AS female_count, COUNT(*) AS total_count')
            ->join('room_players', 'room_players.id', '=', 'room_answers.room_player_id')
            ->where('room_answers.quiz_room_id', $room->id)
            ->where('room_answers.quiz_question_id', $question->id)
            ->groupBy('room_answers.quiz_option_id')
            ->get()
            ->keyBy('quiz_option_id');

        $options = $question->options()
            ->orderBy('option_order')
            ->get()
            ->map(function (QuizOption $option) use ($stats, $question): array {
                $row = $stats->get($option->id);
                $hasCorrectOption = (bool) $question->has_correct_option;

                return [
                    'option_id' => $option->id,
                    'option_order' => $option->option_order,
                    'option_text' => $option->option_text,
                    'is_correct' => $hasCorrectOption ? (bool) $option->is_correct : false,
                    'male_count' => (int) ($row->male_count ?? 0),
                    'female_count' => (int) ($row->female_count ?? 0),
                    'total_count' => (int) ($row->total_count ?? 0),
                ];
            })
            ->values();

        $optionMap = $options->keyBy('option_id');

        $answerDetails = RoomAnswer::query()
            ->select('room_answers.quiz_option_id', 'room_answers.answered_at', 'room_answers.is_correct', 'room_players.id as room_player_id', 'room_players.display_name', 'room_players.gender')
            ->join('room_players', 'room_players.id', '=', 'room_answers.room_player_id')
            ->where('room_answers.quiz_room_id', $room->id)
            ->where('room_answers.quiz_question_id', $question->id)
            ->orderBy('room_answers.answered_at')
            ->get()
            ->map(function ($row) use ($optionMap): array {
                $option = $optionMap->get((int) $row->quiz_option_id);
                $displayName = trim((string) ($row->display_name ?? ''));

                return [
                    'room_player_id' => (int) $row->room_player_id,
                    'display_name' => $displayName !== '' ? $displayName : 'Người chơi #'.((int) $row->room_player_id),
                    'gender' => $row->gender,
                    'option_id' => (int) $row->quiz_option_id,
                    'answer_text' => $option['option_text'] ?? 'N/A',
                    'option_text' => $option['option_text'] ?? 'N/A',
                    'normalized_answer_text' => null,
                    'is_correct' => (bool) ($row->is_correct ?? false),
                    'answered_at' => $row->answered_at !== null ? Carbon::parse($row->answered_at)->toISOString() : null,
                ];
            })
            ->values();

        $overview = [
            'has_correct_option' => (bool) $question->has_correct_option,
            'selected_option_id' => $selectedAnswer?->quiz_option_id !== null ? (int) $selectedAnswer->quiz_option_id : null,
            'selected_answer_text' => null,
            'selected_normalized_answer_text' => null,
            'selected_answer_is_correct' => $selectedAnswer?->is_correct,
            'total_answers' => $answerDetails->count(),
            'male_answers' => $answerDetails->where('gender', 'male')->count(),
            'female_answers' => $answerDetails->where('gender', 'female')->count(),
            'correct_fill_blank_answer' => null,
        ];

        return response()->json([
            'room_code' => $room->room_code,
            'question_id' => $question->id,
            'quiz_question_type' => (int) $question->quiz_question_type->value,
            'question_text' => $question->question_text,
            'question_image_urls' => $question->questionImageUrls(),
            'options' => $options,
            'overview' => $overview,
            'answer_details' => $answerDetails,
        ]);
    }

    private function fillInTheBlankResults(QuizRoom $room, QuizQuestion $question, ?RoomAnswer $selectedAnswer): JsonResponse
    {
        $stats = RoomAnswer::query()
            ->selectRaw('room_answers.normalized_answer_text, MIN(room_answers.answer_text) AS answer_text, SUM(CASE WHEN room_players.gender = "male" THEN 1 ELSE 0 END) AS male_count, SUM(CASE WHEN room_players.gender = "female" THEN 1 ELSE 0 END) AS female_count, COUNT(*) AS total_count, MAX(CASE WHEN room_answers.is_correct = 1 THEN 1 ELSE 0 END) AS is_correct')
            ->join('room_players', 'room_players.id', '=', 'room_answers.room_player_id')
            ->where('room_answers.quiz_room_id', $room->id)
            ->where('room_answers.quiz_question_id', $question->id)
            ->whereNotNull('room_answers.normalized_answer_text')
            ->where('room_answers.normalized_answer_text', '!=', '')
            ->groupBy('room_answers.normalized_answer_text')
            ->orderByDesc('total_count')
            ->get();

        $options = $stats
            ->values()
            ->map(function ($row, int $index): array {
                return [
                    'result_id' => $index + 1,
                    'option_id' => null,
                    'option_order' => $index + 1,
                    'option_text' => (string) ($row->answer_text ?? $row->normalized_answer_text ?? 'N/A'),
                    'normalized_answer_text' => (string) ($row->normalized_answer_text ?? ''),
                    'is_correct' => (bool) ((int) ($row->is_correct ?? 0) === 1),
                    'male_count' => (int) ($row->male_count ?? 0),
                    'female_count' => (int) ($row->female_count ?? 0),
                    'total_count' => (int) ($row->total_count ?? 0),
                ];
            })
            ->values();

        $groupedMap = $options->keyBy('normalized_answer_text');

        $answerDetails = RoomAnswer::query()
            ->select('room_answers.answer_text', 'room_answers.normalized_answer_text', 'room_answers.answered_at', 'room_answers.is_correct', 'room_players.id as room_player_id', 'room_players.display_name', 'room_players.gender')
            ->join('room_players', 'room_players.id', '=', 'room_answers.room_player_id')
            ->where('room_answers.quiz_room_id', $room->id)
            ->where('room_answers.quiz_question_id', $question->id)
            ->orderBy('room_answers.answered_at')
            ->get()
            ->map(function ($row) use ($groupedMap): array {
                $displayName = trim((string) ($row->display_name ?? ''));
                $normalizedAnswerText = (string) ($row->normalized_answer_text ?? '');
                $groupedAnswer = $groupedMap->get($normalizedAnswerText);

                return [
                    'room_player_id' => (int) $row->room_player_id,
                    'display_name' => $displayName !== '' ? $displayName : 'Người chơi #'.((int) $row->room_player_id),
                    'gender' => $row->gender,
                    'option_id' => null,
                    'answer_text' => (string) ($row->answer_text ?? ''),
                    'option_text' => (string) ($groupedAnswer['option_text'] ?? $row->answer_text ?? ''),
                    'normalized_answer_text' => $normalizedAnswerText,
                    'is_correct' => (bool) ($row->is_correct ?? false),
                    'answered_at' => $row->answered_at !== null ? Carbon::parse($row->answered_at)->toISOString() : null,
                ];
            })
            ->values();

        $normalizedCorrectAnswer = $question->normalizedFillBlankAnswer();

        $overview = [
            'has_correct_option' => $normalizedCorrectAnswer !== '',
            'selected_option_id' => null,
            'selected_answer_text' => $selectedAnswer?->answer_text,
            'selected_normalized_answer_text' => $selectedAnswer?->normalized_answer_text,
            'selected_answer_is_correct' => $selectedAnswer?->is_correct,
            'total_answers' => $answerDetails->count(),
            'male_answers' => $answerDetails->where('gender', 'male')->count(),
            'female_answers' => $answerDetails->where('gender', 'female')->count(),
            'correct_fill_blank_answer' => $question->fill_blank_answer,
        ];

        return response()->json([
            'room_code' => $room->room_code,
            'question_id' => $question->id,
            'quiz_question_type' => (int) $question->quiz_question_type->value,
            'question_text' => $question->question_text,
            'question_image_urls' => $question->questionImageUrls(),
            'options' => $options,
            'overview' => $overview,
            'answer_details' => $answerDetails,
        ]);
    }

    private function syncRoomResultState(QuizRoom $room): void
    {
        if ($room->status !== 'question_open') {
            return;
        }

        if ($room->current_question_ends_at === null) {
            return;
        }

        if (now()->lt($room->current_question_ends_at)) {
            return;
        }

        $room->update([
            'status' => 'showing_result',
        ]);

        event(new QuizRoomUpdated($room->room_code, 'question_closed', [
            'question_id' => $room->current_question_id,
        ]));
    }
}
