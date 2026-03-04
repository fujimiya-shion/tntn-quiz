<?php

namespace Database\Seeders;

use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Illuminate\Database\Seeder;

class SampleQuizSeeder extends Seeder
{
    public function run(): void
    {
        $quiz = Quiz::query()->updateOrCreate(
            ['title' => 'Quiz Mẫu: Khảo sát nhanh'],
            [
                'description' => 'Bộ câu hỏi mẫu cho flow realtime host/player.',
                'is_active' => true,
            ]
        );

        $questionPayloads = [
            [
                'question_order' => 1,
                'question_text' => 'Bạn thích làm gì vào cuối tuần?',
                'answer_seconds' => 15,
                'has_correct_option' => false,
                'options' => [
                    ['option_order' => 1, 'option_text' => 'Ở nhà nghỉ ngơi', 'is_correct' => false],
                    ['option_order' => 2, 'option_text' => 'Đi cafe với bạn', 'is_correct' => false],
                    ['option_order' => 3, 'option_text' => 'Chơi thể thao', 'is_correct' => false],
                    ['option_order' => 4, 'option_text' => 'Du lịch ngắn ngày', 'is_correct' => false],
                ],
            ],
            [
                'question_order' => 2,
                'question_text' => 'Thời điểm bạn tỉnh táo nhất trong ngày?',
                'answer_seconds' => 12,
                'has_correct_option' => false,
                'options' => [
                    ['option_order' => 1, 'option_text' => 'Buổi sáng', 'is_correct' => false],
                    ['option_order' => 2, 'option_text' => 'Buổi trưa', 'is_correct' => false],
                    ['option_order' => 3, 'option_text' => 'Buổi chiều', 'is_correct' => false],
                    ['option_order' => 4, 'option_text' => 'Buổi tối', 'is_correct' => false],
                ],
            ],
            [
                'question_order' => 3,
                'question_text' => '2 + 2 bằng bao nhiêu?',
                'answer_seconds' => 10,
                'has_correct_option' => true,
                'options' => [
                    ['option_order' => 1, 'option_text' => '3', 'is_correct' => false],
                    ['option_order' => 2, 'option_text' => '4', 'is_correct' => true],
                    ['option_order' => 3, 'option_text' => '5', 'is_correct' => false],
                    ['option_order' => 4, 'option_text' => '6', 'is_correct' => false],
                ],
            ],
        ];

        $questionIds = [];

        foreach ($questionPayloads as $questionPayload) {
            $question = QuizQuestion::query()->updateOrCreate(
                [
                    'quiz_id' => $quiz->id,
                    'question_order' => $questionPayload['question_order'],
                ],
                [
                    'question_text' => $questionPayload['question_text'],
                    'answer_seconds' => $questionPayload['answer_seconds'],
                    'has_correct_option' => $questionPayload['has_correct_option'],
                ]
            );

            $questionIds[] = $question->id;
            $optionIds = [];

            foreach ($questionPayload['options'] as $optionPayload) {
                $option = QuizOption::query()->updateOrCreate(
                    [
                        'quiz_question_id' => $question->id,
                        'option_order' => $optionPayload['option_order'],
                    ],
                    [
                        'option_text' => $optionPayload['option_text'],
                        'is_correct' => $optionPayload['is_correct'],
                    ]
                );

                $optionIds[] = $option->id;
            }

            QuizOption::query()
                ->where('quiz_question_id', $question->id)
                ->whereNotIn('id', $optionIds)
                ->delete();
        }

        QuizQuestion::query()
            ->where('quiz_id', $quiz->id)
            ->whereNotIn('id', $questionIds)
            ->delete();
    }
}
