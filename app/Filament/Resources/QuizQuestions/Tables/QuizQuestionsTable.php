<?php

namespace App\Filament\Resources\QuizQuestions\Tables;

use App\QuizQuestionType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuizQuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quiz.title')
                    ->label('Quiz')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('question_order')
                    ->label('Thứ tự')
                    ->sortable(),
                TextColumn::make('question_text')
                    ->label('Câu hỏi')
                    ->placeholder('Câu hỏi bằng hình ảnh')
                    ->limit(80)
                    ->searchable(),
                TextColumn::make('quiz_question_type')
                    ->label('Loại')
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state instanceof QuizQuestionType) {
                            return $state->label();
                        }

                        $questionType = QuizQuestionType::tryFrom((int) $state);

                        return $questionType?->label() ?? 'N/A';
                    }),
                TextColumn::make('question_images')
                    ->label('Ảnh')
                    ->formatStateUsing(function (mixed $state): int {
                        if (is_array($state)) {
                            return count($state);
                        }

                        if (! is_string($state) || $state === '') {
                            return 0;
                        }

                        $decoded = json_decode($state, true);

                        return is_array($decoded) ? count($decoded) : 0;
                    })
                    ->suffix(' ảnh')
                    ->sortable(false),
                TextColumn::make('answer_seconds')
                    ->label('Giây')
                    ->suffix('s')
                    ->sortable(),
                IconColumn::make('has_correct_option')
                    ->label('Có đáp án đúng')
                    ->state(fn ($record): bool => $record->isFillInTheBlank() ? true : (bool) $record->has_correct_option)
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
