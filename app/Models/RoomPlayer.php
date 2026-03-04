<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_room_id',
        'player_token',
        'display_name',
        'gender',
        'is_host',
        'joined_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_host' => 'boolean',
            'joined_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(QuizRoom::class, 'quiz_room_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(RoomAnswer::class);
    }
}
