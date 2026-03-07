import { Sheet } from 'react-modal-sheet';

export default function ResultDetailSheet({ isOpen, onClose, resultDetails }) {
    return (
        <Sheet isOpen={isOpen} onClose={onClose} snapPoints={[0.85, 0.5]}>
            <Sheet.Container className="overflow-hidden rounded-t-3xl border border-slate-200 bg-slate-100">
                <Sheet.Header />
                <Sheet.Content>
                    <div className="h-full overflow-y-auto px-4 pb-6">
                        <div className="sticky top-0 z-10 -mx-4 border-b border-slate-200 bg-slate-100/95 px-4 pb-3 pt-2 backdrop-blur">
                            <h4 className="text-base font-bold text-slate-900">Danh sách người trả lời</h4>
                            <p className="mt-1 text-sm text-slate-500">Vuốt xuống để đóng.</p>
                        </div>

                        <div className="mt-4 space-y-3">
                            {resultDetails.length === 0 ? (
                                <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-4 text-sm text-slate-500">
                                    Chưa có dữ liệu trả lời cho câu này.
                                </div>
                            ) : (
                                resultDetails.map((detail, index) => (
                                    <div key={`${detail.room_player_id}-${index}`} className="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="font-semibold text-slate-900">{detail.display_name}</div>
                                            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${detail.gender === 'male' ? 'bg-sky-100 text-sky-700' : 'bg-fuchsia-100 text-fuchsia-700'}`}>
                                                {detail.gender === 'male' ? 'Nam' : 'Nữ'}
                                            </span>
                                        </div>
                                        <div className="mt-2 rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-sm text-slate-700">
                                            Đáp án: <span className="font-semibold text-indigo-700">{detail.option_text}</span>
                                        </div>
                                        <div className="mt-2 text-xs text-slate-500">
                                            {detail.answered_at ? new Date(detail.answered_at).toLocaleTimeString() : 'Chưa có thời gian trả lời'}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </Sheet.Content>
            </Sheet.Container>
            <Sheet.Backdrop onTap={onClose} />
        </Sheet>
    );
}
