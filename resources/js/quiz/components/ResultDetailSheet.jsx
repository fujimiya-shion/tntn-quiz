import { Sheet } from 'react-modal-sheet';

export default function ResultDetailSheet({ isOpen, onClose, resultDetails }) {
    return (
        <Sheet isOpen={isOpen} onClose={onClose} snapPoints={[0.85, 0.5]}>
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
                                resultDetails.map((detail, index) => (
                                    <div key={`${detail.room_player_id}-${index}`} className="rounded-xl border border-slate-200 bg-white px-3 py-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="font-semibold text-slate-900">{detail.display_name}</div>
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${detail.gender === 'male' ? 'bg-sky-100 text-sky-700' : 'bg-fuchsia-100 text-fuchsia-700'}`}>
                                                {detail.gender === 'male' ? 'Nam' : 'Nữ'}
                                            </span>
                                        </div>
                                        <div className="mt-1 text-sm text-slate-600">Đáp án: <span className="font-semibold text-indigo-700">{detail.option_text}</span></div>
                                        {detail.answered_at ? (
                                            <div className="mt-1 text-xs text-slate-500">
                                                {new Date(detail.answered_at).toLocaleTimeString()}
                                            </div>
                                        ) : null}
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
