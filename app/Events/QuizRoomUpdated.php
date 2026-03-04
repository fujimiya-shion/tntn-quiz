<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizRoomUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $roomCode,
        public string $type,
        public array $payload = [],
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('quiz-room.'.$this->roomCode)];
    }

    public function broadcastAs(): string
    {
        return 'quiz.room.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'room_code' => $this->roomCode,
            'payload' => $this->payload,
            'sent_at' => now()->toISOString(),
        ];
    }
}
