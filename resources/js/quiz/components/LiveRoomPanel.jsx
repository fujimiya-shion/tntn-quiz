import { lazy, Suspense, useMemo, useState } from 'react';
import { getStatusLabel } from '../statusLabels';

const ResultCharts = lazy(() => import('./ResultCharts'));
const ResultDetailSheet = lazy(() => import('./ResultDetailSheet'));

export default function LiveRoomPanel({
    roomCode,
    role,
    status,
    question,
    remainingSeconds,
    results,
    resultOverview,
    resultDetails,
    hasAnswered,
    canFinishQuiz,
    onNext,
    onFinish,
    onAnswer,
}) {
    const [chartType, setChartType] = useState('pie');
    const [isDetailSheetOpen, setIsDetailSheetOpen] = useState(false);

    const progressPercent = question && question.answer_seconds > 0
        ? Math.max(0, Math.min(100, (remainingSeconds / question.answer_seconds) * 100))
        : 0;
    const questionImageUrls = Array.isArray(question?.image_urls)
        ? question.image_urls
        : Array.isArray(question?.question_image_urls)
            ? question.question_image_urls
            : Array.isArray(question?.question_images)
                ? question.question_images
                : [];
    const hasSingleImage = questionImageUrls.length === 1;
    const questionOrder = Number(question?.question_order || 0);
    const totalQuestions = Number(question?.total_questions || 0);
    const quizProgressPercent = totalQuestions > 0
        ? Math.max(0, Math.min(100, (questionOrder / totalQuestions) * 100))
        : 0;

    const totalAnswers = useMemo(() => {
        if (resultOverview?.total_answers !== undefined && resultOverview?.total_answers !== null) {
            return Number(resultOverview.total_answers) || 0;
        }

        return results.reduce((sum, item) => sum + Number(item.total_count || 0), 0);
    }, [resultOverview, results]);

    const maleAnswers = Number(resultOverview?.male_answers ?? results.reduce((sum, item) => sum + Number(item.male_count || 0), 0));
    const femaleAnswers = Number(resultOverview?.female_answers ?? results.reduce((sum, item) => sum + Number(item.female_count || 0), 0));

    const chartData = useMemo(() => results.map((row) => {
        const totalCount = Number(row.total_count || 0);
        const ratioPercent = totalAnswers > 0 ? (totalCount / totalAnswers) * 100 : 0;

        return {
            name: row.option_text,
            totalCount,
            ratioPercent,
        };
    }), [results, totalAnswers]);

    const maleRatio = totalAnswers > 0 ? Math.round((maleAnswers / totalAnswers) * 100) : 0;
    const femaleRatio = totalAnswers > 0 ? Math.round((femaleAnswers / totalAnswers) * 100) : 0;
    const hasCorrectOption = Boolean(resultOverview?.has_correct_option);
    const selectedOptionId = Number(resultOverview?.selected_option_id || 0);
    const selectedOptionIsCorrect = hasCorrectOption
        && selectedOptionId > 0
        && results.some((row) => Number(row.option_id) === selectedOptionId && row.is_correct);

    return (
        <div className="mt-6 rounded-[2rem] bg-gradient-to-b from-indigo-900 to-violet-700 p-4 text-white shadow-[0_24px_80px_-28px_rgba(59,7,100,0.7)]">
            <div className="space-y-4 rounded-[1.65rem] border border-white/15 bg-white/5 p-4 backdrop-blur-sm">
                <div className="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-white/85">
                    <span>Room: {roomCode}</span>
                    <span>{role === 'host' ? 'Host' : 'Player'}</span>
                </div>
                {questionOrder > 0 && totalQuestions > 0 ? (
                    <div className="space-y-1">
                        <div className="flex items-center justify-between text-xs font-semibold text-amber-100">
                            <span>Câu {questionOrder}/{totalQuestions}</span>
                            <span>{Math.round(quizProgressPercent)}%</span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-white/20">
                            <div
                                className="h-full rounded-full bg-gradient-to-r from-amber-300 to-orange-400 transition-all duration-700"
                                style={{ width: `${quizProgressPercent}%` }}
                            />
                        </div>
                    </div>
                ) : null}
                <div className="h-2 overflow-hidden rounded-full bg-white/20">
                    <div
                        className="h-full rounded-full bg-emerald-300 transition-all duration-1000"
                        style={{ width: `${progressPercent}%` }}
                    />
                </div>
                <div className="flex items-center justify-between text-xs text-white/80">
                    <span>{getStatusLabel(status)}</span>
                    <span>{remainingSeconds}s</span>
                </div>
            </div>

            {role === 'host' && status !== 'finished' && canFinishQuiz ? (
                <button
                    className="mt-4 w-full rounded-xl bg-gradient-to-r from-rose-500 to-orange-500 px-4 py-2.5 font-semibold text-white transition hover:from-rose-400 hover:to-orange-400"
                    onClick={onFinish}
                >
                    Kết Thúc Quiz
                </button>
            ) : null}

            {role === 'host' && status !== 'finished' && !canFinishQuiz ? (
                <button
                    className="mt-4 w-full rounded-xl bg-white px-4 py-2.5 font-semibold text-indigo-700 transition hover:bg-indigo-50"
                    onClick={onNext}
                >
                    Next Câu Hỏi
                </button>
            ) : null}

            {question ? (
                <div className="mt-4 rounded-2xl border border-white/15 bg-white/95 p-4 text-indigo-900">
                    {question.text ? (
                        <h3 className="text-center text-lg font-bold">{question.text}</h3>
                    ) : (
                        <h3 className="text-center text-lg font-bold">Câu hỏi bằng hình ảnh</h3>
                    )}
                    {questionImageUrls.length > 0 ? (
                        <div className={`mt-4 grid gap-3 ${hasSingleImage ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2'}`}>
                            {questionImageUrls.map((imageUrl, index) => (
                                <div key={`${imageUrl}-${index}`} className="overflow-hidden rounded-xl border border-violet-200 bg-violet-50 p-2">
                                    <img
                                        src={imageUrl}
                                        alt={`Question image ${index + 1}`}
                                        className={`w-full rounded-lg object-contain ${hasSingleImage ? 'max-h-[420px]' : 'max-h-[300px]'}`}
                                        loading="lazy"
                                    />
                                </div>
                            ))}
                        </div>
                    ) : null}
                    <div className={`mt-4 grid gap-3 ${question.options && question.options.length >= 4 ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-1'}`}>
                        {question.options?.map((option) => (
                            <button
                                key={option.id}
                                className="rounded-xl border border-violet-300 bg-violet-100 px-4 py-3 text-left font-semibold text-violet-900 transition hover:bg-violet-200 disabled:cursor-not-allowed disabled:opacity-45"
                                onClick={() => onAnswer(option.id)}
                                disabled={role === 'host' || hasAnswered || status !== 'question_open'}
                            >
                                <span className="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-violet-700 text-xs font-bold text-white">
                                    {option.option_order}
                                </span>
                                {option.option_text}
                            </button>
                        ))}
                    </div>
                </div>
            ) : null}

            {status === 'showing_result' ? (
                <div className="mt-4 rounded-2xl border border-white/15 bg-white/95 p-4 text-indigo-900">
                    <div className="grid grid-cols-3 gap-2">
                        <div className="rounded-xl border border-violet-200 bg-violet-50 p-3">
                            <div className="text-[11px] uppercase tracking-wide text-violet-600">Tổng trả lời</div>
                            <div className="text-xl font-black text-violet-900">{totalAnswers}</div>
                        </div>
                        <div className="rounded-xl border border-sky-200 bg-sky-50 p-3">
                            <div className="text-[11px] uppercase tracking-wide text-sky-600">Nam ratio</div>
                            <div className="text-xl font-black text-sky-700">{maleRatio}%</div>
                        </div>
                        <div className="rounded-xl border border-fuchsia-200 bg-fuchsia-50 p-3">
                            <div className="text-[11px] uppercase tracking-wide text-fuchsia-600">Nữ ratio</div>
                            <div className="text-xl font-black text-fuchsia-700">{femaleRatio}%</div>
                        </div>
                    </div>

                    <Suspense fallback={<div className="mt-4 rounded-xl border border-indigo-100 bg-white p-4 text-sm font-semibold text-indigo-700">Đang tải biểu đồ...</div>}>
                        <ResultCharts
                            chartType={chartType}
                            setChartType={setChartType}
                            chartData={chartData}
                        />
                    </Suspense>

                    <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {results.map((row) => {
                            const rowRatio = totalAnswers > 0 ? Math.round((Number(row.total_count || 0) / totalAnswers) * 100) : 0;
                            const isCorrectRow = hasCorrectOption && row.is_correct;
                            const isWrongSelectedRow = role === 'player'
                                && hasCorrectOption
                                && !selectedOptionIsCorrect
                                && selectedOptionId > 0
                                && Number(row.option_id) === selectedOptionId;
                            const rowClassName = isCorrectRow
                                ? 'border-emerald-300 bg-emerald-50'
                                : isWrongSelectedRow
                                    ? 'border-rose-300 bg-rose-50'
                                    : 'border-violet-200 bg-violet-50';
                            const titleClassName = isCorrectRow
                                ? 'text-emerald-900'
                                : isWrongSelectedRow
                                    ? 'text-rose-900'
                                    : 'text-violet-900';
                            const statClassName = isCorrectRow
                                ? 'text-emerald-700'
                                : isWrongSelectedRow
                                    ? 'text-rose-700'
                                    : 'text-violet-700';

                            return (
                                <div
                                    key={row.option_id}
                                    className={`rounded-xl border px-3 py-2 ${rowClassName}`}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <div className={`font-semibold ${titleClassName}`}>{row.option_text}</div>
                                        <div className="flex items-center gap-1.5">
                                            {isCorrectRow ? (
                                                <span className="rounded-full bg-emerald-600 px-2 py-0.5 text-xs font-bold text-white">
                                                    Đúng
                                                </span>
                                            ) : null}
                                            {isWrongSelectedRow ? (
                                                <span className="rounded-full bg-rose-600 px-2 py-0.5 text-xs font-bold text-white">
                                                    Bạn chọn
                                                </span>
                                            ) : null}
                                        </div>
                                    </div>
                                    <div className={`text-sm ${statClassName}`}>Nam: {row.male_count} | Nữ: {row.female_count} | Tổng: {row.total_count} | Ratio: {rowRatio}%</div>
                                </div>
                            );
                        })}
                    </div>

                    {role === 'host' ? (
                        <button
                            type="button"
                            onClick={() => setIsDetailSheetOpen(true)}
                            className="mt-4 w-full rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-[0_12px_26px_-12px_rgba(79,70,229,0.8)] transition hover:from-indigo-500 hover:to-violet-500"
                        >
                            Xem chi tiết ai đã trả lời
                        </button>
                    ) : null}
                </div>
            ) : null}

            <Suspense fallback={null}>
                <ResultDetailSheet
                    isOpen={isDetailSheetOpen}
                    onClose={() => setIsDetailSheetOpen(false)}
                    resultDetails={resultDetails}
                />
            </Suspense>
        </div>
    );
}
