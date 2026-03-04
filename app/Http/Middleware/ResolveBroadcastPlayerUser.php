<?php

namespace App\Http\Middleware;

use App\Models\RoomPlayer;
use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveBroadcastPlayerUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->path() !== 'broadcasting/auth') {
            return $next($request);
        }

        $token = $request->bearerToken()
            ?? $request->header('X-Player-Token')
            ?? $request->string('player_token')->value();

        $token = is_string($token) ? trim($token, "\"' ") : '';

        if ($token === '') {
            return $next($request);
        }

        $rawChannelName = (string) $request->input('channel_name', '');
        $channelName = preg_replace('/^(private-|presence-)/', '', $rawChannelName);

        if (! is_string($channelName) || ! Str::startsWith($channelName, 'quiz-room.')) {
            return $next($request);
        }

        $roomCode = Str::after($channelName, 'quiz-room.');

        $player = RoomPlayer::query()
            ->select('room_players.id', 'room_players.display_name', 'room_players.gender', 'room_players.is_host', 'quiz_rooms.room_code')
            ->join('quiz_rooms', 'quiz_rooms.id', '=', 'room_players.quiz_room_id')
            ->where('quiz_rooms.room_code', $roomCode)
            ->where('room_players.player_token', $token)
            ->first();

        if ($player !== null) {
            $request->setUserResolver(function () use ($player, $token): GenericUser {
                return new GenericUser([
                    'id' => $player->id,
                    'name' => $player->display_name,
                    'display_name' => $player->display_name,
                    'gender' => $player->gender,
                    'is_host' => (bool) $player->is_host,
                    'room_player_id' => $player->id,
                    'room_code' => $player->room_code,
                    'player_token' => $token,
                ]);
            });
        }

        return $next($request);
    }
}
