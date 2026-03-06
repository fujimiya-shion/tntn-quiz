<?php

namespace App\Filament\Resources\QuizQuestions\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class QuizQuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('quiz_id')
                    ->relationship('quiz', 'title')
                    ->searchable()
                    ->required(),
                Textarea::make('question_text')
                    ->label('Nội dung câu hỏi')
                    ->requiredWithout('question_images')
                    ->rows(3)
                    ->maxLength(1000)
                    ->columnSpanFull(),
                FileUpload::make('question_images')
                    ->label('Ảnh câu hỏi')
                    ->image()
                    ->multiple()
                    ->disk('public')
                    ->directory('quiz-questions')
                    ->visibility('public')
                    ->reorderable()
                    ->openable()
                    ->downloadable()
                    ->columnSpanFull(),
                TextInput::make('question_order')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(1),
                TextInput::make('answer_seconds')
                    ->required()
                    ->numeric()
                    ->minValue(3)
                    ->maxValue(300)
                    ->default(10),
                Toggle::make('has_correct_option')
                    ->default(false),
            ]);
    }
}
