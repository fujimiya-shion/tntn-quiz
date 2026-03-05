import { Link } from 'react-router-dom';
import { PiCrownSimpleFill, PiUsersThreeFill } from 'react-icons/pi';

export default function RoleSelectPage() {
    return (
        <div className="min-h-screen bg-[radial-gradient(circle_at_20%_15%,#a5f3fc_0,#dbeafe_36%,#ede9fe_100%)] text-slate-800">
            <div className="mx-auto flex min-h-screen w-full max-w-5xl flex-col justify-center px-4 py-10">
                <div className="mx-auto mb-8 text-center">
                    <p className="inline-flex items-center rounded-full bg-white/75 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700 shadow-sm">
                        Quiz realtime
                    </p>
                    <h1 className="mt-3 text-4xl font-black tracking-tight text-indigo-900 sm:text-5xl">Chọn Vai Trò</h1>
                    <p className="mt-2 text-sm text-indigo-700/80 sm:text-base">Bạn là chủ phòng hay người chơi?</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Link
                        to="/host"
                        className="group rounded-3xl border border-indigo-200/70 bg-white/85 p-6 shadow-[0_24px_80px_-34px_rgba(79,70,229,0.55)] backdrop-blur transition hover:-translate-y-1 hover:border-indigo-300 hover:shadow-[0_32px_90px_-35px_rgba(79,70,229,0.65)]"
                    >
                        <div className="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-lg">
                            <PiCrownSimpleFill className="text-2xl" />
                        </div>
                        <h2 className="mt-4 text-2xl font-black text-indigo-900">Chủ Phòng</h2>
                        <p className="mt-1 text-sm text-indigo-700/85">Đăng nhập, tạo phòng, điều khiển next câu hỏi cho toàn bộ người chơi.</p>
                        <div className="mt-5 inline-flex rounded-xl bg-indigo-100 px-3 py-2 text-sm font-semibold text-indigo-700 transition group-hover:bg-indigo-200">
                            Vào /host
                        </div>
                    </Link>

                    <Link
                        to="/room/join"
                        className="group rounded-3xl border border-fuchsia-200/70 bg-white/85 p-6 shadow-[0_24px_80px_-34px_rgba(168,85,247,0.45)] backdrop-blur transition hover:-translate-y-1 hover:border-fuchsia-300 hover:shadow-[0_32px_90px_-35px_rgba(168,85,247,0.6)]"
                    >
                        <div className="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-600 to-fuchsia-600 text-white shadow-lg">
                            <PiUsersThreeFill className="text-2xl" />
                        </div>
                        <h2 className="mt-4 text-2xl font-black text-violet-900">Người Chơi</h2>
                        <p className="mt-1 text-sm text-violet-700/85">Nhập mã phòng, chọn giới tính, tham gia và trả lời câu hỏi realtime.</p>
                        <div className="mt-5 inline-flex rounded-xl bg-fuchsia-100 px-3 py-2 text-sm font-semibold text-fuchsia-700 transition group-hover:bg-fuchsia-200">
                            Vào /room/join/{'{ROOM_CODE}'}
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    );
}
