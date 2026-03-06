<?php

namespace App\Filament\Resources\Quizzes\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->label('Thứ tự câu')
                    ->required()
                    ->numeric()
                    ->default(fn (): int => ((int) ($this->ownerRecord->questions()->max('question_order') ?? 0)) + 1)
                    ->minValue(1)
                    ->unique(
                        table: 'quiz_questions',
                        column: 'question_order',
                        ignorable: fn () => $this->getMountedTableActionRecord(),
                        modifyRuleUsing: function (Unique $rule): Unique {
                            return $rule->where('quiz_id', $this->ownerRecord->getKey());
                        }
                    )
                    ->validationMessages([
                        'unique' => 'Thứ tự câu đã tồn tại trong quiz này.',
                    ]),
                TextInput::make('answer_seconds')
                    ->label('Số giây trả lời')
                    ->required()
                    ->numeric()
                    ->default(10)
                    ->minValue(3)
                    ->maxValue(300),
                Toggle::make('has_correct_option')
                    ->label('Có đáp án đúng')
                    ->default(false),
                Repeater::make('options')
                    ->label('Đáp án')
                    ->relationship('options')
                    ->orderColumn('option_order')
                    ->schema([
                        TextInput::make('option_text')
                            ->label('Nội dung đáp án')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Toggle::make('is_correct')
                            ->label('Đúng')
                            ->default(false),
                    ])
                    ->addActionLabel('Add to đáp án')
                    ->defaultItems(2)
                    ->minItems(2)
                    ->reorderableWithButtons()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question_text')
            ->columns([
                TextColumn::make('question_order')
                    ->label('Thứ tự')
                    ->sortable(),
                TextColumn::make('question_text')
                    ->label('Câu hỏi')
                    ->placeholder('Câu hỏi bằng hình ảnh')
                    ->limit(80)
                    ->searchable(),
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
                    ->suffix(' ảnh'),
                TextColumn::make('answer_seconds')
                    ->label('Giây')
                    ->suffix('s')
                    ->sortable(),
                IconColumn::make('has_correct_option')
                    ->label('Có đáp án đúng')
                    ->boolean(),
                TextColumn::make('options_count')
                    ->label('Số đáp án')
                    ->counts('options'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
