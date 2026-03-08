<?php

namespace App;

enum QuizQuestionType: int
{
    case MultipleChoice = 1;
    case FillInTheBlank = 2;

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Trắc nghiệm',
            self::FillInTheBlank => 'Điền khuyết',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
