<?php

namespace App\Filament\Widgets;

use App\Models\QuizRoom;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentRoomsTable extends BaseWidget
{
    protected static ?string $heading = 'Room Gần Đây';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->defaultPaginationPageOption(10)
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('room_code')
                    ->label('Room')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('quiz.title')
                    ->label('Quiz')
                    ->limit(45)
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'waiting' => 'gray',
                        'question_open' => 'success',
                        'showing_result' => 'warning',
                        'finished' => 'primary',
                        'dissolved' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('players_count')
                    ->label('Người chơi')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('answers_count')
                    ->label('Lượt trả lời')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->since()
                    ->sortable(),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return QuizRoom::query()
            ->with('quiz:id,title')
            ->withCount([
                'players as players_count' => fn (Builder $query): Builder => $query->where('is_host', false),
                'answers',
            ]);
    }
}
