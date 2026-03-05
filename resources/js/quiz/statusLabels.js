export const STATUS_LABELS = {
    waiting: 'Đang chờ',
    question_open: 'Đang mở câu hỏi',
    showing_result: 'Đang hiển thị kết quả',
    finished: 'Đã kết thúc',
};

export function getStatusLabel(status) {
    return STATUS_LABELS[status] ?? status;
}
