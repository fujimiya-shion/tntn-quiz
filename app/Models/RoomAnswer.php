<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_room_id',
        'room_player_id',
        'quiz_question_id',
        'quiz_option_id',
        'is_late',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_late' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(QuizRoom::class, 'quiz_room_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(RoomPlayer::class, 'room_player_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuizOption::class, 'quiz_option_id');
    }
}
