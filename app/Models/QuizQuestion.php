<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'question_text',
        'question_images',
        'question_order',
        'answer_seconds',
        'has_correct_option',
    ];

    protected function casts(): array
    {
        return [
            'has_correct_option' => 'boolean',
            'question_images' => 'array',
        ];
    }

    public function questionImageUrls(): array
    {
        return collect($this->question_images ?? [])
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): string => Storage::disk('public')->url($path))
            ->values()
            ->all();
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(RoomAnswer::class);
    }
}
