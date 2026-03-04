<?php

namespace App\Http\Controllers\Api;

use App\Events\QuizRoomUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\HostCreateRoomRequest;
use App\Http\Requests\HostFinishQuizRequest;
use App\Http\Requests\HostLoginRequest;
use App\Http\Requests\HostNextQuestionRequest;
use App\Http\Requests\JoinRoomRequest;
use App\Jobs\CloseRoomQuestionJob;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizRoom;
use App\Models\RoomPlayer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class QuizRoomController extends Controller
{
    public function login(HostLoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->string('email')->value())
            ->first();

        if ($user === null || ! Hash::check($request->string('password')->value(), $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $accessToken = Str::random(64);
        Cache::put($this->hostAccessTokenKey($accessToken), $user->id, now()->addHours(8));

        return response()->json([
            'host_access_token' => $accessToken,
            'host_name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function quizzes(Request $request): JsonResponse
    {
        $hostAccessToken = $request->bearerToken()
            ?? $request->header('X-Host-Token')
            ?? $request->string('host_access_token')->value();

        $hostUserId = $this->resolveHostUserId($hostAccessToken);

        if ($hostUserId === null) {
            return response()->json([
                'message' => 'Invalid host access token.',
            ], 401);
        }

        $quizzes = Quiz::query()
            ->where('is_active', true)
            ->withCount('questions')
            ->orderBy('id', 'desc')
            ->get(['id', 'title', 'description']);

        return response()->json([
            'quizzes' => $quizzes,
        ]);
    }

    public function createForHost(HostCreateRoomRequest $request): JsonResponse
    {
        $hostUserId = $this->resolveHostUserId($request->string('host_access_token')->value());

        if ($hostUserId === null) {
            return response()->json([
                'message' => 'Invalid host access token.',
            ], 401);
        }

        $user = User::query()->find($hostUserId);

        if ($user === null) {
            return response()->json([
                'message' => 'Host account not found.',
            ], 401);
        }

        $room = QuizRoom::query()->create([
            'quiz_id' => (int) $request->integer('quiz_id'),
            'room_code' => $this->generateRoomCode(),
            'status' => 'waiting',
        ]);

        $hostName = $request->string('host_name')->value();
        $hostGender = $request->string('host_gender')->value();

        $host = RoomPlayer::query()->create([
            'quiz_room_id' => $room->id,
            'player_token' => (string) Str::uuid(),
            'display_name' => $hostName !== '' ? $hostName : ($user->name ?: $user->email),
            'gender' => in_array($hostGender, ['male', 'female'], true) ? $hostGender : 'male',
            'is_host' => true,
            'joined_at' => now(),
            'last_seen_at' => now(),
        ]);

        event(new QuizRoomUpdated($room->room_code, 'room_created', [
            'room_id' => $room->id,
        ]));

        return response()->json([
            'room_code' => $room->room_code,
            'host_token' => $host->player_token,
            'join_url' => url('/room/join/'.$room->room_code),
            'status' => $room->status,
        ], 201);
    }

    public function join(JoinRoomRequest $request, string $roomCode): JsonResponse
    {
        $room = QuizRoom::query()->where('room_code', $roomCode)->first();

        if ($room === null) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        if ($room->status === 'finished') {
            return response()->json([
                'message' => 'Room has finished.',
            ], 422);
        }

        $player = RoomPlayer::query()->create([
            'quiz_room_id' => $room->id,
            'player_token' => (string) Str::uuid(),
            'display_name' => $request->string('display_name')->value() !== ''
                ? $request->string('display_name')->value()
                : 'Anonymous-'.Str::upper(Str::random(4)),
            'gender' => $request->string('gender')->value(),
            'is_host' => false,
            'joined_at' => now(),
            'last_seen_at' => now(),
        ]);

        event(new QuizRoomUpdated($room->room_code, 'player_joined', [
            'player' => [
                'id' => $player->id,
                'display_name' => $player->display_name,
                'gender' => $player->gender,
                'joined_at' => optional($player->joined_at)->toISOString(),
            ],
        ]));

        return response()->json([
            'room_code' => $room->room_code,
            'player_token' => $player->player_token,
            'status' => $room->status,
        ]);
    }

    public function players(Request $request, string $roomCode): JsonResponse
    {
        $room = QuizRoom::query()->where('room_code', $roomCode)->first();

        if ($room === null) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        $hostToken = $request->string('host_token')->value();

        if ($hostToken === '') {
            return response()->json([
                'message' => 'host_token is required.',
            ], 422);
        }

        $host = RoomPlayer::query()
            ->where('quiz_room_id', $room->id)
            ->where('player_token', $hostToken)
            ->where('is_host', true)
            ->first();

        if ($host === null) {
            return response()->json([
                'message' => 'Invalid host token.',
            ], 403);
        }

        $players = RoomPlayer::query()
            ->where('quiz_room_id', $room->id)
            ->where('is_host', false)
            ->orderBy('joined_at')
            ->get(['id', 'display_name', 'gender', 'joined_at'])
            ->map(function (RoomPlayer $player): array {
                return [
                    'id' => $player->id,
                    'display_name' => $player->display_name,
                    'gender' => $player->gender,
                    'joined_at' => optional($player->joined_at)->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'room_code' => $room->room_code,
            'players' => $players,
        ]);
    }

    public function next(HostNextQuestionRequest $request, string $roomCode): JsonResponse
    {
        $room = QuizRoom::query()->where('room_code', $roomCode)->first();

        if ($room === null) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        $host = $this->resolveHostPlayer($room, $request->string('host_token')->value());

        if ($host === null) {
            return response()->json([
                'message' => 'Invalid host token.',
            ], 403);
        }

        $currentOrder = null;

        if ($room->current_question_id !== null) {
            $currentOrder = QuizQuestion::query()
                ->where('id', $room->current_question_id)
                ->value('question_order');
        }

        $nextQuestionQuery = QuizQuestion::query()
            ->where('quiz_id', $room->quiz_id)
            ->orderBy('question_order');

        if ($currentOrder !== null) {
            $nextQuestionQuery->where('question_order', '>', $currentOrder);
        }

        $nextQuestion = $nextQuestionQuery->first();

        if ($nextQuestion === null) {
            return response()->json([
                'room_code' => $room->room_code,
                'status' => $room->status,
                'can_finish_quiz' => true,
                'message' => 'Đã là câu hỏi cuối. Vui lòng bấm kết thúc quiz.',
            ], 422);
        }

        $startsAt = now();
        $endsAt = $startsAt->copy()->addSeconds($nextQuestion->answer_seconds);

        $room->update([
            'status' => 'question_open',
            'current_question_id' => $nextQuestion->id,
            'current_question_started_at' => $startsAt,
            'current_question_ends_at' => $endsAt,
        ]);

        CloseRoomQuestionJob::dispatch($room->id, $nextQuestion->id)->delay($endsAt);

        $options = $nextQuestion->options()
            ->orderBy('option_order')
            ->get(['id', 'option_text', 'option_order']);

        event(new QuizRoomUpdated($room->room_code, 'question_opened', [
            'question_id' => $nextQuestion->id,
            'question_text' => $nextQuestion->question_text,
            'answer_seconds' => $nextQuestion->answer_seconds,
            'ends_at' => $endsAt->toISOString(),
        ]));

        return response()->json([
            'room_code' => $room->room_code,
            'status' => 'question_open',
            'can_finish_quiz' => false,
            'question' => [
                'id' => $nextQuestion->id,
                'text' => $nextQuestion->question_text,
                'answer_seconds' => $nextQuestion->answer_seconds,
                'ends_at' => $endsAt->toISOString(),
                'options' => $options,
            ],
        ]);
    }

    public function finish(HostFinishQuizRequest $request, string $roomCode): JsonResponse
    {
        $room = QuizRoom::query()->where('room_code', $roomCode)->first();

        if ($room === null) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        $host = $this->resolveHostPlayer($room, $request->string('host_token')->value());

        if ($host === null) {
            return response()->json([
                'message' => 'Invalid host token.',
            ], 403);
        }

        $room->update([
            'status' => 'finished',
            'current_question_started_at' => null,
            'current_question_ends_at' => null,
        ]);

        event(new QuizRoomUpdated($room->room_code, 'quiz_finished', []));

        return response()->json([
            'room_code' => $room->room_code,
            'status' => 'finished',
        ]);
    }

    private function generateRoomCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (QuizRoom::query()->where('room_code', $code)->exists());

        return $code;
    }

    private function hostAccessTokenKey(string $accessToken): string
    {
        return 'quiz_host_access:'.$accessToken;
    }

    private function resolveHostUserId(?string $accessToken): ?int
    {
        if ($accessToken === null || $accessToken === '') {
            return null;
        }

        $value = Cache::get($this->hostAccessTokenKey($accessToken));

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    private function resolveHostPlayer(QuizRoom $room, string $hostToken): ?RoomPlayer
    {
        return RoomPlayer::query()
            ->where('quiz_room_id', $room->id)
            ->where('player_token', $hostToken)
            ->where('is_host', true)
            ->first();
    }
}
