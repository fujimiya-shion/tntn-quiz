<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('quiz-room.{roomCode}', function ($player, string $roomCode): array|bool {
    if ($player === null) {
        return false;
    }

    if (($player->room_code ?? null) !== $roomCode) {
        return false;
    }

    return [
        'id' => $player->room_player_id ?? $player->id,
        'name' => $player->display_name ?? $player->name,
        'display_name' => $player->display_name ?? $player->name,
        'gender' => $player->gender,
        'is_host' => (bool) $player->is_host,
    ];
});
