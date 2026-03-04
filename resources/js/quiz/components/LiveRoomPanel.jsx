import { useMemo, useState } from 'react';
import { Sheet } from 'react-modal-sheet';
import { Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const CHART_COLORS = ['#7c3aed', '#2563eb', '#ec4899', '#14b8a6', '#f59e0b', '#ef4444'];

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

    return (
        <div className="mt-6 rounded-[2rem] bg-gradient-to-b from-indigo-900 to-violet-700 p-4 text-white shadow-[0_24px_80px_-28px_rgba(59,7,100,0.7)]">
            <div className="space-y-4 rounded-[1.65rem] border border-white/15 bg-white/5 p-4 backdrop-blur-sm">
                <div className="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-white/85">
                    <span>Room: {roomCode}</span>
                    <span>{role === 'host' ? 'Host' : 'Player'}</span>
                </div>
                <div className="h-2 overflow-hidden rounded-full bg-white/20">
                    <div
                        className="h-full rounded-full bg-emerald-300 transition-all duration-1000"
                        style={{ width: `${progressPercent}%` }}
                    />
                </div>
                <div className="flex items-center justify-between text-xs text-white/80">
                    <span>{status}</span>
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
                    <h3 className="text-center text-lg font-bold">{question.text}</h3>
                    <div className="mt-4 grid gap-3">
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
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <h3 className="text-lg font-bold">Thống Kê Tổng Quan</h3>
                        <div className="flex items-center gap-2 rounded-lg bg-indigo-50 p-1">
                            <button
                                type="button"
                                onClick={() => setChartType('pie')}
                                className={`rounded-md px-3 py-1 text-xs font-semibold transition ${chartType === 'pie' ? 'bg-indigo-600 text-white' : 'text-indigo-700 hover:bg-indigo-100'}`}
                            >
                                Pie
                            </button>
                            <button
                                type="button"
                                onClick={() => setChartType('column')}
                                className={`rounded-md px-3 py-1 text-xs font-semibold transition ${chartType === 'column' ? 'bg-indigo-600 text-white' : 'text-indigo-700 hover:bg-indigo-100'}`}
                            >
                                Column
                            </button>
                        </div>
                    </div>

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

                    <div className="mt-4 h-64 rounded-xl border border-indigo-100 bg-white p-2">
                        <ResponsiveContainer width="100%" height="100%">
                            {chartType === 'pie' ? (
                                <PieChart>
                                    <Pie
                                        data={chartData}
                                        dataKey="totalCount"
                                        nameKey="name"
                                        cx="50%"
                                        cy="48%"
                                        outerRadius={95}
                                        label={({ ratioPercent }) => `${Math.round(ratioPercent)}%`}
                                    >
                                        {chartData.map((entry, index) => (
                                            <Cell key={`${entry.name}-${index}`} fill={CHART_COLORS[index % CHART_COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <Tooltip formatter={(value) => [`${value}`, 'Lượt chọn']} />
                                    <Legend />
                                </PieChart>
                            ) : (
                                <BarChart data={chartData} margin={{ top: 8, right: 12, left: 0, bottom: 8 }}>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                    <XAxis hide dataKey="name" />
                                    <YAxis allowDecimals={false} />
                                    <Tooltip formatter={(value, name) => [name === 'totalCount' ? `${value}` : `${Number(value).toFixed(1)}%`, name === 'totalCount' ? 'Lượt chọn' : 'Tỉ lệ']} />
                                    <Bar dataKey="totalCount" name="Lượt chọn" radius={[8, 8, 0, 0]}>
                                        {chartData.map((entry, index) => (
                                            <Cell key={`${entry.name}-${index}`} fill={CHART_COLORS[index % CHART_COLORS.length]} />
                                        ))}
                                    </Bar>
                                </BarChart>
                            )}
                        </ResponsiveContainer>
                    </div>

                    <div className="mt-3 space-y-2">
                        {results.map((row) => {
                            const rowRatio = totalAnswers > 0 ? Math.round((Number(row.total_count || 0) / totalAnswers) * 100) : 0;

                            return (
                                <div key={row.option_id} className="rounded-xl border border-violet-200 bg-violet-50 px-3 py-2">
                                    <div className="font-semibold text-violet-900">{row.option_text}</div>
                                    <div className="text-sm text-violet-700">Nam: {row.male_count} | Nữ: {row.female_count} | Tổng: {row.total_count} | Ratio: {rowRatio}%</div>
                                </div>
                            );
                        })}
                    </div>

                    <button
                        type="button"
                        onClick={() => setIsDetailSheetOpen(true)}
                        className="mt-4 w-full rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-[0_12px_26px_-12px_rgba(79,70,229,0.8)] transition hover:from-indigo-500 hover:to-violet-500"
                    >
                        Xem chi tiết ai đã trả lời
                    </button>
                </div>
            ) : null}

            <Sheet isOpen={isDetailSheetOpen} onClose={() => setIsDetailSheetOpen(false)} snapPoints={[0.85, 0.5]}>
                <Sheet.Container>
                    <Sheet.Header />
                    <Sheet.Content>
                        <div className="h-full overflow-y-auto px-4 pb-6">
                            <h4 className="text-base font-bold text-slate-900">Danh sách người trả lời</h4>
                            <p className="mt-1 text-sm text-slate-500">Vuốt xuống để đóng.</p>

                            <div className="mt-4 space-y-2">
                                {resultDetails.length === 0 ? (
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-500">
                                        Chưa có dữ liệu trả lời cho câu này.
                                    </div>
                                ) : (
                                    resultDetails.map((row, index) => (
                                        <div key={`${row.room_player_id}-${row.option_id}-${index}`} className="rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <div className="font-semibold text-indigo-900">{row.display_name}</div>
                                                <span className={`rounded-full px-2 py-1 text-xs font-semibold ${row.gender === 'male' ? 'bg-sky-100 text-sky-700' : 'bg-fuchsia-100 text-fuchsia-700'}`}>
                                                    {row.gender === 'male' ? 'Nam' : 'Nữ'}
                                                </span>
                                            </div>
                                            <div className="mt-1 text-sm text-indigo-700">Đáp án: {row.option_text}</div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </Sheet.Content>
                </Sheet.Container>
                <Sheet.Backdrop onTap={() => setIsDetailSheetOpen(false)} />
            </Sheet>
        </div>
    );
}
