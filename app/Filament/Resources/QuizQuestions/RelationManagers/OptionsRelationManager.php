<?php

namespace App\Filament\Resources\QuizQuestions\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('option_text')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('option_order')
                    ->required()
                    ->numeric()
                    ->default(fn (): int => ((int) ($this->ownerRecord->options()->max('option_order') ?? 0)) + 1)
                    ->minValue(1)
                    ->unique(
                        table: 'quiz_options',
                        column: 'option_order',
                        ignorable: fn () => $this->getMountedTableActionRecord(),
                        modifyRuleUsing: function (Unique $rule): Unique {
                            return $rule->where('quiz_question_id', $this->ownerRecord->getKey());
                        }
                    )
                    ->validationMessages([
                        'unique' => 'Thứ tự đáp án đã tồn tại trong câu hỏi này.',
                    ]),
                Toggle::make('is_correct')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('option_text')
            ->columns([
                TextColumn::make('option_order')
                    ->label('Thứ tự')
                    ->sortable(),
                TextColumn::make('option_text')
                    ->label('Đáp án')
                    ->searchable(),
                IconColumn::make('is_correct')
                    ->label('Đúng')
                    ->boolean(),
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
