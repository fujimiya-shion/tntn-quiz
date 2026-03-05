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
use Symfony\Component\HttpFoundation\Cookie;

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
        $ttlMinutes = (int) config('quiz.host_auth.ttl_minutes', 480);
        Cache::put($this->hostAccessTokenKey($accessToken), $user->id, now()->addMinutes($ttlMinutes));

        $cookie = Cookie::create(
            name: (string) config('quiz.host_auth.cookie_name', 'quiz_host_access_token'),
            value: $accessToken,
            expire: now()->addMinutes($ttlMinutes),
            path: '/',
            domain: null,
            secure: request()->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );

        return response()->json([
            'host_name' => $user->name,
            'email' => $user->email,
        ])->cookie($cookie);
    }

    public function quizzes(Request $request): JsonResponse
    {
        $hostUserId = $this->resolveHostUserId($this->resolveHostAccessToken($request));

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

    public function rooms(Request $request): JsonResponse
    {
        $hostUserId = $this->resolveHostUserId($this->resolveHostAccessToken($request));

        if ($hostUserId === null) {
            return response()->json([
                'message' => 'Invalid host access token.',
            ], 401);
        }

        $rooms = QuizRoom::query()
            ->with('quiz:id,title')
            ->withCount([
                'players as players_count' => fn ($query) => $query->where('is_host', false),
            ])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $transformedRooms = $rooms->getCollection()
            ->map(function (QuizRoom $room): array {
                return [
                    'room_code' => $room->room_code,
                    'status' => $room->status,
                    'quiz_id' => $room->quiz_id,
                    'quiz_title' => $room->quiz?->title,
                    'players_count' => (int) ($room->players_count ?? 0),
                    'current_question_id' => $room->current_question_id,
                    'created_at' => optional($room->created_at)->toISOString(),
                    'updated_at' => optional($room->updated_at)->toISOString(),
                ];
            })
            ->values();

        $rooms->setCollection($transformedRooms);

        return response()->json([
            'rooms' => $rooms->items(),
            'pagination' => [
                'current_page' => $rooms->currentPage(),
                'last_page' => $rooms->lastPage(),
                'per_page' => $rooms->perPage(),
                'total' => $rooms->total(),
            ],
        ]);
    }

    public function roomDetail(Request $request, string $roomCode): JsonResponse
    {
        $hostUserId = $this->resolveHostUserId($this->resolveHostAccessToken($request));

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

        $room = QuizRoom::query()
            ->with('quiz:id,title')
            ->where('room_code', $roomCode)
            ->first();

        if ($room === null) {
            return response()->json([
                'message' => 'Room not found.',
            ], 404);
        }

        $hostPlayer = RoomPlayer::query()
            ->where('quiz_room_id', $room->id)
            ->where('is_host', true)
            ->orderBy('id')
            ->first();

        if ($hostPlayer === null) {
            $hostPlayer = RoomPlayer::query()->create([
                'quiz_room_id' => $room->id,
                'player_token' => (string) Str::uuid(),
                'display_name' => $user->name ?: $user->email,
                'gender' => 'male',
                'is_host' => true,
                'joined_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        return response()->json([
            'room_code' => $room->room_code,
            'status' => $room->status,
            'quiz_id' => $room->quiz_id,
            'quiz_title' => $room->quiz?->title,
            'host_token' => $hostPlayer->player_token,
            'join_url' => url('/room/join/'.$room->room_code),
        ]);
    }

    public function createForHost(HostCreateRoomRequest $request): JsonResponse
    {
        $hostUserId = $this->resolveHostUserId($this->resolveHostAccessToken($request));

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

        $host = RoomPlayer::query()->create([
            'quiz_room_id' => $room->id,
            'player_token' => (string) Str::uuid(),
            'display_name' => $hostName !== '' ? $hostName : ($user->name ?: $user->email),
            'gender' => 'male',
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

        $requestedDisplayName = trim($request->string('display_name')->value());

        if ($requestedDisplayName !== '') {
            $isDuplicatedName = RoomPlayer::query()
                ->where('quiz_room_id', $room->id)
                ->whereRaw('LOWER(display_name) = ?', [mb_strtolower($requestedDisplayName)])
                ->exists();

            if ($isDuplicatedName) {
                return response()->json([
                    'message' => 'Tên người chơi đã tồn tại trong phòng. Vui lòng chọn tên khác.',
                ], 409);
            }
        }

        $displayName = $requestedDisplayName;

        if ($displayName === '') {
            do {
                $displayName = 'Anonymous-'.Str::upper(Str::random(4));
            } while (RoomPlayer::query()
                ->where('quiz_room_id', $room->id)
                ->where('display_name', $displayName)
                ->exists());
        }

        $player = RoomPlayer::query()->create([
            'quiz_room_id' => $room->id,
            'player_token' => (string) Str::uuid(),
            'display_name' => $displayName,
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
        $totalQuestions = QuizQuestion::query()
            ->where('quiz_id', $room->quiz_id)
            ->count();

        event(new QuizRoomUpdated($room->room_code, 'question_opened', [
            'question_id' => $nextQuestion->id,
            'question_text' => $nextQuestion->question_text,
            'question_image_urls' => $nextQuestion->questionImageUrls(),
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
                'image_urls' => $nextQuestion->questionImageUrls(),
                'question_order' => $nextQuestion->question_order,
                'total_questions' => $totalQuestions,
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

    public function dissolve(HostFinishQuizRequest $request, string $roomCode): JsonResponse
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

        if ($room->status === 'finished') {
            return response()->json([
                'message' => 'Room already finished.',
            ], 422);
        }

        $room->update([
            'status' => 'finished',
            'current_question_id' => null,
            'current_question_started_at' => null,
            'current_question_ends_at' => null,
        ]);

        event(new QuizRoomUpdated($room->room_code, 'room_dissolved', []));

        return response()->json([
            'room_code' => $room->room_code,
            'status' => 'finished',
            'message' => 'Room dissolved.',
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

    private function resolveHostAccessToken(Request $request): string
    {
        $cookieName = (string) config('quiz.host_auth.cookie_name', 'quiz_host_access_token');

        return (string) ($request->bearerToken()
            ?? $request->header('X-Host-Token')
            ?? $request->cookie($cookieName)
            ?? $request->string('host_access_token')->value());
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
