<?php

namespace App\Models;

use App\QuizQuestionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuizQuestion extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $question): void {
            if ($question->isFillInTheBlank()) {
                $question->has_correct_option = true;
            }
        });
    }

    protected $fillable = [
        'quiz_id',
        'question_text',
        'quiz_question_type',
        'question_images',
        'question_order',
        'answer_seconds',
        'has_correct_option',
        'fill_blank_answer',
    ];

    protected function casts(): array
    {
        return [
            'has_correct_option' => 'boolean',
            'question_images' => 'array',
            'quiz_question_type' => QuizQuestionType::class,
        ];
    }

    public function isMultipleChoice(): bool
    {
        $questionType = $this->quiz_question_type;

        if ($questionType instanceof QuizQuestionType) {
            return $questionType === QuizQuestionType::MultipleChoice;
        }

        return (int) $questionType === QuizQuestionType::MultipleChoice->value;
    }

    public function isFillInTheBlank(): bool
    {
        $questionType = $this->quiz_question_type;

        if ($questionType instanceof QuizQuestionType) {
            return $questionType === QuizQuestionType::FillInTheBlank;
        }

        return (int) $questionType === QuizQuestionType::FillInTheBlank->value;
    }

    public function normalizedFillBlankAnswer(): string
    {
        return self::normalizeFillBlankText((string) ($this->fill_blank_answer ?? ''));
    }

    public static function normalizeFillBlankText(string $text): string
    {
        return (string) Str::slug(trim($text));
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
