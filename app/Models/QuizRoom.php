<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'room_code',
        'status',
        'current_question_id',
        'current_question_started_at',
        'current_question_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'current_question_started_at' => 'datetime',
            'current_question_ends_at' => 'datetime',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'current_question_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(RoomPlayer::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(RoomAnswer::class);
    }
}
