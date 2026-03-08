<?php

namespace App\Filament\Resources\QuizQuestions\Schemas;

use App\QuizQuestionType;
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
                Select::make('quiz_question_type')
                    ->label('Loại câu hỏi')
                    ->options(QuizQuestionType::options())
                    ->default(QuizQuestionType::MultipleChoice->value)
                    ->native(false)
                    ->live()
                    ->required(),
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
                    ->default(false)
                    ->visible(fn (callable $get): bool => (int) $get('quiz_question_type') === QuizQuestionType::MultipleChoice->value)
                    ->dehydrated(fn (callable $get): bool => (int) $get('quiz_question_type') === QuizQuestionType::MultipleChoice->value),
                TextInput::make('fill_blank_answer')
                    ->label('Đáp án điền khuyết')
                    ->maxLength(255)
                    ->required(fn (callable $get): bool => (int) $get('quiz_question_type') === QuizQuestionType::FillInTheBlank->value)
                    ->visible(fn (callable $get): bool => (int) $get('quiz_question_type') === QuizQuestionType::FillInTheBlank->value)
                    ->dehydrated(fn (callable $get): bool => (int) $get('quiz_question_type') === QuizQuestionType::FillInTheBlank->value),
            ]);
    }
}
