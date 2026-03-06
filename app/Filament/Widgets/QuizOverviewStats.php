<?php

namespace App\Filament\Widgets;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizRoom;
use App\Models\RoomAnswer;
use App\Models\RoomPlayer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QuizOverviewStats extends BaseWidget
{
    protected ?string $heading = 'Tổng Quan TNTN Quiz';

    protected function getStats(): array
    {
        $totalQuizzes = Quiz::query()->count();
        $totalQuestions = QuizQuestion::query()->count();
        $totalRooms = QuizRoom::query()->count();
        $openRooms = QuizRoom::query()->where('status', 'question_open')->count();
        $totalPlayers = RoomPlayer::query()->where('is_host', false)->count();

        $totalAnswers = RoomAnswer::query()->count();
        $answersToday = RoomAnswer::query()->whereDate('answered_at', now()->toDateString())->count();
        $correctAnswers = RoomAnswer::query()
            ->join('quiz_options', 'quiz_options.id', '=', 'room_answers.quiz_option_id')
            ->where('quiz_options.is_correct', true)
            ->count();

        $correctRate = $totalAnswers > 0
            ? number_format(($correctAnswers / $totalAnswers) * 100, 1)
            : '0.0';

        return [
            Stat::make('Bộ Quiz', number_format($totalQuizzes))
                ->description(number_format($totalQuestions).' câu hỏi')
                ->color('primary'),
            Stat::make('Room Đang Mở Câu', number_format($openRooms))
                ->description('Tổng room đã tạo: '.number_format($totalRooms))
                ->color($openRooms > 0 ? 'success' : 'gray'),
            Stat::make('Người Chơi', number_format($totalPlayers))
                ->description('Không tính host')
                ->color('info'),
            Stat::make('Tỷ Lệ Trả Lời Đúng', $correctRate.'%')
                ->description(number_format($correctAnswers).'/'.number_format($totalAnswers).' lượt | Hôm nay: '.number_format($answersToday))
                ->color($totalAnswers > 0 ? 'warning' : 'gray'),
        ];
    }
}
