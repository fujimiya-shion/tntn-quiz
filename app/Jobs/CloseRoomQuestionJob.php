<?php

namespace App\Jobs;

use App\Events\QuizRoomUpdated;
use App\Models\QuizRoom;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CloseRoomQuestionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $roomId,
        public int $questionId,
    ) {}

    public function handle(): void
    {
        $room = QuizRoom::query()->find($this->roomId);

        if ($room === null) {
            return;
        }

        if ($room->status !== 'question_open') {
            return;
        }

        if ((int) $room->current_question_id !== $this->questionId) {
            return;
        }

        if ($room->current_question_ends_at === null || now()->lt($room->current_question_ends_at)) {
            return;
        }

        $room->update([
            'status' => 'showing_result',
        ]);

        event(new QuizRoomUpdated($room->room_code, 'question_closed', [
            'question_id' => $this->questionId,
        ]));
    }
}
