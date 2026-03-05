import { Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const CHART_COLORS = ['#7c3aed', '#2563eb', '#ec4899', '#14b8a6', '#f59e0b', '#ef4444'];

export default function ResultCharts({ chartType, setChartType, chartData }) {
    return (
        <>
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

            <div className="mt-4 h-64 rounded-xl border border-indigo-100 bg-white p-2">
                <ResponsiveContainer width="100%" height="100%">
                    {chartType === 'pie' ? (
                        <PieChart>
                            <Pie
                                data={chartData}
                                dataKey="totalCount"
                                nameKey="name"
                                cx="50%"
                                cy="44%"
                                outerRadius="72%"
                                label={false}
                                labelLine={false}
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
        </>
    );
}
