import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Toaster, toast } from 'sonner';
import LiveRoomPanel from '../components/LiveRoomPanel';
import { api, getErrorMessage } from '../api';
import { useRoomRealtime } from '../hooks/useRoomRealtime';
import { getStatusLabel } from '../statusLabels';

export default function HostPage({ initialRoomCode = '' }) {
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [isHostAuthenticated, setIsHostAuthenticated] = useState(false);

    const [quizzes, setQuizzes] = useState([]);
    const [rooms, setRooms] = useState([]);
    const [roomsPage, setRoomsPage] = useState(1);
    const [roomsPagination, setRoomsPagination] = useState({
        currentPage: 1,
        lastPage: 1,
        perPage: 15,
        total: 0,
    });
    const [quizId, setQuizId] = useState('');

    const [roomCode, setRoomCode] = useState('');
    const [joinUrl, setJoinUrl] = useState('');
    const [hostToken, setHostToken] = useState('');
    const [isQrOverlayOpen, setIsQrOverlayOpen] = useState(false);

    const [status, setStatus] = useState('waiting');
    const [question, setQuestion] = useState(null);
    const [remainingSeconds, setRemainingSeconds] = useState(0);
    const [canFinishQuiz, setCanFinishQuiz] = useState(false);
    const [results, setResults] = useState([]);
    const [resultOverview, setResultOverview] = useState(null);
    const [resultDetails, setResultDetails] = useState([]);
    const [players, setPlayers] = useState([]);

    const realtime = useRoomRealtime();

    const normalizeSeconds = (value) => {
        const seconds = Number(value);

        if (!Number.isFinite(seconds)) {
            return 0;
        }

        return Math.max(0, Math.ceil(seconds));
    };

    const normalizeQuestion = (rawQuestion) => {
        if (!rawQuestion) {
            return null;
        }

        const rawImages = rawQuestion.image_urls ?? rawQuestion.question_image_urls ?? rawQuestion.question_images ?? [];
        const imageUrls = Array.isArray(rawImages)
            ? rawImages
                .filter((value) => typeof value === 'string' && value !== '')
                .map((value) => (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/')) ? value : `/storage/${value}`)
            : [];

        return {
            ...rawQuestion,
            image_urls: imageUrls,
        };
    };

    const syncResults = async (code, questionId) => {
        const response = await api.get(`/quiz/rooms/${code}/results`, { params: { question_id: questionId } });
        setResults(response.data.options ?? []);
        setResultOverview(response.data.overview ?? null);
        setResultDetails(response.data.answer_details ?? []);
    };

    const loadRooms = async (page = roomsPage) => {
        const response = await api.get('/quiz/host/rooms', {
            params: { page },
        });

        setRooms(response.data.rooms ?? []);
        const pagination = response.data.pagination ?? {};

        setRoomsPage(Number(pagination.current_page ?? page));
        setRoomsPagination({
            currentPage: Number(pagination.current_page ?? page),
            lastPage: Number(pagination.last_page ?? 1),
            perPage: Number(pagination.per_page ?? 15),
            total: Number(pagination.total ?? 0),
        });
    };

    const startRoomRealtime = (code, token) => {
        realtime.stop();

        realtime.start({
            roomCode: code,
            playerToken: token,
            onRoomUpdated: async (eventData) => {
                if (eventData.type === 'player_joined' && eventData.payload?.player) {
                    const joinedPlayer = eventData.payload.player;

                    setPlayers((currentPlayers) => {
                        if (currentPlayers.some((player) => player.id === joinedPlayer.id)) {
                            return currentPlayers;
                        }

                        return [...currentPlayers, joinedPlayer];
                    });

                    toast.info(`${joinedPlayer.display_name} (${joinedPlayer.gender}) vừa vào phòng.`);
                }

                if (eventData.type === 'question_closed' && eventData.payload?.question_id) {
                    await syncResults(code, eventData.payload.question_id);
                }

                if (eventData.type === 'quiz_finished') {
                    setStatus('finished');
                    setCanFinishQuiz(false);
                }

                if (eventData.type === 'room_dissolved') {
                    realtime.stop();
                    toast.warning('Phòng đã được giải tán.');
                    navigate('/host', { replace: true });
                    return;
                }

                await syncPlayers(code, token);
                await syncState(code, token);
                await loadRooms();
            },
            onMembersHere: (members) => {
                const onlinePlayers = members
                    .filter((member) => member.is_host !== true)
                    .map((member) => ({
                        id: member.id,
                        display_name: member.display_name ?? member.name,
                        gender: member.gender,
                        joined_at: null,
                    }));

                setPlayers(onlinePlayers);
            },
            onMemberJoining: (member) => {
                if (member.is_host === true) {
                    return;
                }

                setPlayers((currentPlayers) => {
                    if (currentPlayers.some((player) => player.id === member.id)) {
                        return currentPlayers;
                    }

                    return [
                        ...currentPlayers,
                        {
                            id: member.id,
                            display_name: member.display_name ?? member.name,
                            gender: member.gender,
                            joined_at: null,
                        },
                    ];
                });
            },
            onMemberLeaving: (member) => {
                if (member.is_host === true) {
                    return;
                }

                setPlayers((currentPlayers) => currentPlayers.filter((player) => player.id !== member.id));
            },
            onFallback: () => toast.warning('Realtime env chưa đủ, không thể đồng bộ ngay lập tức.'),
        });
    };

    const openRoomDetail = async (code) => {
        try {
            const response = await api.get(`/quiz/host/rooms/${code}`);

            setRoomCode(response.data.room_code);
            setJoinUrl(response.data.join_url);
            setHostToken(response.data.host_token);
            setStatus(response.data.status);
            setPlayers([]);
            setResults([]);
            setResultOverview(null);
            setResultDetails([]);

            await syncState(response.data.room_code, response.data.host_token);
            await syncPlayers(response.data.room_code, response.data.host_token);
            startRoomRealtime(response.data.room_code, response.data.host_token);
        } catch (error) {
            toast.error(getErrorMessage(error, 'Không thể mở room detail.'));
        }
    };

    const syncPlayers = async (code, token) => {
        const response = await api.get(`/quiz/rooms/${code}/players`, {
            params: { host_token: token },
        });
        setPlayers(response.data.players ?? []);
    };

    const syncState = async (code, token) => {
        const response = await api.get(`/quiz/rooms/${code}/state`, { params: { player_token: token } });
        setStatus(response.data.status);
        setQuestion(normalizeQuestion(response.data.question));
        setRemainingSeconds(normalizeSeconds(response.data.remaining_seconds));
        setCanFinishQuiz(Boolean(response.data.can_finish_quiz));

        if (response.data.status === 'showing_result' && response.data.current_question_id) {
            await syncResults(code, response.data.current_question_id);
            return;
        }

        setResults([]);
        setResultOverview(null);
        setResultDetails([]);
    };

    const onLogin = async (event) => {
        event.preventDefault();

        try {
            await api.post('/quiz/host/login', { email, password });

            const quizzesResponse = await api.get('/quiz/host/quizzes');

            setQuizzes(quizzesResponse.data.quizzes ?? []);
            setIsHostAuthenticated(true);
            await loadRooms();

            if (initialRoomCode) {
                await openRoomDetail(initialRoomCode);
            }

            toast.success('Đăng nhập thành công. Chọn bộ câu hỏi để bắt đầu phòng.');
        } catch (error) {
            toast.error(getErrorMessage(error, 'Đăng nhập host thất bại.'));
        }
    };

    const onStartRoom = async (event) => {
        event.preventDefault();

        try {
            const response = await api.post('/quiz/host/rooms', {
                quiz_id: Number(quizId),
            });

            navigate(`/host/${response.data.room_code}`);
        } catch (error) {
            toast.error(getErrorMessage(error, 'Tạo phòng thất bại.'));
        }
    };

    const onHostNext = async () => {
        try {
            const response = await api.post(`/quiz/rooms/${roomCode}/host/next`, {
                host_token: hostToken,
            });

            setStatus(response.data.status);
            setQuestion(normalizeQuestion(response.data.question));
            setCanFinishQuiz(Boolean(response.data.can_finish_quiz));
            setResults([]);
            setResultOverview(null);
            setResultDetails([]);
            await loadRooms();

            if (response.data.question?.ends_at) {
                const seconds = Math.max(0, Math.floor((new Date(response.data.question.ends_at).getTime() - Date.now()) / 1000));
                setRemainingSeconds(normalizeSeconds(seconds));
            }
        } catch (error) {
            if (error?.response?.status === 422) {
                toast.warning(getErrorMessage(error, 'Đã là câu hỏi cuối.'));
                setCanFinishQuiz(true);
                return;
            }

            toast.error(getErrorMessage(error, 'Next câu hỏi thất bại.'));
        }
    };

    const onFinishQuiz = async () => {
        try {
            const response = await api.post(`/quiz/rooms/${roomCode}/host/finish`, {
                host_token: hostToken,
            });

            setStatus(response.data.status);
            setCanFinishQuiz(false);
            await loadRooms();
            toast.success('Đã kết thúc quiz.');
        } catch (error) {
            toast.error(getErrorMessage(error, 'Kết thúc quiz thất bại.'));
        }
    };

    const onDissolveRoom = async () => {
        const shouldDissolve = window.confirm('Giải tán phòng này? Tất cả người chơi sẽ bị thoát khỏi phiên realtime.');

        if (!shouldDissolve) {
            return;
        }

        try {
            await api.post(`/quiz/rooms/${roomCode}/host/dissolve`, {
                host_token: hostToken,
            });

            realtime.stop();
            toast.success('Đã giải tán phòng.');
            navigate('/host', { replace: true });
        } catch (error) {
            if (error?.response?.status === 422) {
                toast.warning('Phòng đã kết thúc, không cần giải tán.');
                return;
            }

            toast.error(getErrorMessage(error, 'Giải tán phòng thất bại.'));
        }
    };

    const onCopyRoomCode = async () => {
        try {
            await navigator.clipboard.writeText(roomCode);
            toast.success(`Đã copy room code: ${roomCode}`);
        } catch (error) {
            toast.error('Không thể copy room code trên trình duyệt này.');
        }
    };

    const onChangeRoomsPage = async (nextPage) => {
        if (nextPage < 1 || nextPage > roomsPagination.lastPage || nextPage === roomsPage) {
            return;
        }

        await loadRooms(nextPage);
    };

    useEffect(() => {
        if (status !== 'question_open' || remainingSeconds <= 0) {
            return;
        }

        const timer = setInterval(() => {
            setRemainingSeconds((value) => Math.max(0, Math.floor(value) - 1));
        }, 1000);

        return () => clearInterval(timer);
    }, [status, remainingSeconds]);

    useEffect(() => () => realtime.stop(), []);
    useEffect(() => {
        const bootstrapHostSession = async () => {
            try {
                const quizzesResponse = await api.get('/quiz/host/quizzes');
                setQuizzes(quizzesResponse.data.quizzes ?? []);
                setIsHostAuthenticated(true);
                await loadRooms();

                if (initialRoomCode) {
                    await openRoomDetail(initialRoomCode);
                }
            } catch (error) {
                setIsHostAuthenticated(false);
            }
        };

        bootstrapHostSession();
    }, [initialRoomCode]);

    const isSetupMode = !isHostAuthenticated;
    const isRoomDetailRoute = initialRoomCode !== '';
    const qrJoinUrl = joinUrl || (roomCode ? `${window.location.origin}/room/join/${roomCode}` : '');
    const qrImageUrl = qrJoinUrl !== ''
        ? `https://api.qrserver.com/v1/create-qr-code/?size=900x900&format=png&margin=12&data=${encodeURIComponent(qrJoinUrl)}`
        : '';
    const statusBadgeClasses = {
        waiting: 'bg-slate-100 text-slate-700',
        question_open: 'bg-emerald-100 text-emerald-700',
        showing_result: 'bg-amber-100 text-amber-700',
        finished: 'bg-rose-100 text-rose-700',
    };

    return (
        <div className="min-h-screen bg-[radial-gradient(circle_at_15%_20%,#cffafe_0,#f8fbff_32%,#eef2ff_100%)] text-slate-800">
            <Toaster position="top-right" richColors closeButton duration={3000} />
            <div className={isSetupMode ? 'mx-auto flex min-h-screen max-w-5xl items-center justify-center px-4 py-10' : 'mx-auto max-w-5xl px-4 py-8'}>
                <div className={isSetupMode ? 'w-full max-w-xl' : 'w-full'}>
                    <div className={isSetupMode ? 'mb-6 text-center' : ''}>
                        <p className="inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700 shadow-sm">
                            Host Console
                        </p>
                        <h1 className="mt-3 text-4xl font-black tracking-tight text-indigo-900">Host Quiz</h1>
                        <p className="mt-2 text-sm text-indigo-700/80">Đăng nhập, chọn bộ câu hỏi, bắt đầu phòng và điều phối câu hỏi realtime.</p>
                    </div>

                    {!isHostAuthenticated ? (
                        <form className="mt-6 space-y-3 rounded-3xl border border-indigo-100 bg-white/90 p-6 shadow-[0_32px_95px_-36px_rgba(99,102,241,0.55)] backdrop-blur-sm" onSubmit={onLogin}>
                            <h2 className="text-lg font-bold text-indigo-900">Đăng Nhập Chủ Phòng</h2>
                            <input className="w-full rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2.5 transition focus:border-indigo-400 focus:bg-white focus:outline-none" type="email" placeholder="Email" value={email} onChange={(e) => setEmail(e.target.value)} />
                            <input className="w-full rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2.5 transition focus:border-indigo-400 focus:bg-white focus:outline-none" type="password" placeholder="Password" value={password} onChange={(e) => setPassword(e.target.value)} />
                            <button className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2.5 font-semibold text-white shadow-[0_14px_30px_-12px_rgba(79,70,229,0.75)] transition hover:from-indigo-500 hover:to-violet-500" type="submit">Đăng Nhập</button>
                        </form>
                    ) : null}

                    {isHostAuthenticated && !isRoomDetailRoute ? (
                        <form className="mt-6 space-y-3 rounded-3xl border border-indigo-100 bg-white/90 p-6 shadow-[0_32px_95px_-36px_rgba(99,102,241,0.55)] backdrop-blur-sm" onSubmit={onStartRoom}>
                            <h2 className="text-lg font-bold text-indigo-900">Chọn Bộ Câu Hỏi Và Bắt Đầu</h2>
                            <select className="w-full rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2.5 transition focus:border-indigo-400 focus:bg-white focus:outline-none" value={quizId} onChange={(e) => setQuizId(e.target.value)}>
                                <option value="">Chọn bộ quiz</option>
                                {quizzes.map((quiz) => (
                                    <option key={quiz.id} value={quiz.id}>{quiz.title} ({quiz.questions_count} câu)</option>
                                ))}
                            </select>
                            <button className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 px-4 py-2.5 font-semibold text-emerald-950 shadow-[0_14px_30px_-12px_rgba(16,185,129,0.7)] transition hover:from-emerald-400 hover:to-teal-400" type="submit">Bắt Đầu Phòng</button>
                        </form>
                    ) : null}

                    {isHostAuthenticated && !isRoomDetailRoute ? (
                        <div className="mt-4 rounded-2xl border border-indigo-100 bg-white/90 p-4 shadow-[0_20px_80px_-30px_rgba(99,102,241,0.45)]">
                            <div className="mb-3 flex items-center justify-between">
                                <h3 className="text-base font-bold text-indigo-900">Toàn Bộ Room</h3>
                                <span className="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">{roomsPagination.total} room</span>
                            </div>

                            {rooms.length === 0 ? (
                                <p className="text-sm text-indigo-700/70">Chưa có room nào.</p>
                            ) : (
                                <div className="space-y-2">
                                    {rooms.map((room) => (
                                        <button
                                            key={room.room_code}
                                            type="button"
                                            onClick={() => {
                                                navigate(`/host/${room.room_code}`);
                                            }}
                                            className={`w-full rounded-xl border px-3 py-2 text-left transition ${roomCode === room.room_code ? 'border-indigo-400 bg-indigo-100' : 'border-indigo-100 bg-indigo-50 hover:bg-indigo-100'}`}
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <div>
                                                    <div className="font-semibold text-indigo-900">{room.room_code}</div>
                                                    <div className="text-xs text-indigo-700/80">{room.quiz_title ?? 'N/A'} - {room.players_count} người chơi</div>
                                                </div>
                                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusBadgeClasses[room.status] ?? 'bg-slate-100 text-slate-700'}`}>
                                                    {getStatusLabel(room.status)}
                                                </span>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}

                            {roomsPagination.lastPage > 1 ? (
                                <div className="mt-4 flex items-center justify-between border-t border-indigo-100 pt-3">
                                    <button
                                        type="button"
                                        onClick={() => onChangeRoomsPage(roomsPage - 1)}
                                        disabled={roomsPage <= 1}
                                        className="rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Trang trước
                                    </button>
                                    <p className="text-xs font-semibold text-indigo-700">
                                        Trang {roomsPagination.currentPage}/{roomsPagination.lastPage} - {roomsPagination.perPage} room/trang
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => onChangeRoomsPage(roomsPage + 1)}
                                        disabled={roomsPage >= roomsPagination.lastPage}
                                        className="rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Trang sau
                                    </button>
                                </div>
                            ) : null}
                        </div>
                    ) : null}

                    {isHostAuthenticated && isRoomDetailRoute && roomCode ? (
                        <div>
                            <div className="mt-6 rounded-2xl border border-indigo-100 bg-white/90 p-4 text-sm shadow-[0_20px_80px_-30px_rgba(99,102,241,0.45)]">
                                <div className="mb-3 flex items-center gap-2 rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2">
                                    <div>
                                        <div className="text-[11px] font-semibold uppercase tracking-wide text-indigo-600">Room code</div>
                                        <div className="text-lg font-black tracking-wide text-indigo-900">{roomCode}</div>
                                    </div>
                                    <div className="ml-auto flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={onCopyRoomCode}
                                            className="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500"
                                        >
                                            Copy room code
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setIsQrOverlayOpen(true)}
                                            className="rounded-lg border border-indigo-200 bg-white px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-50"
                                        >
                                            Show QR
                                        </button>
                                    </div>
                                </div>
                                <div>Link join: <a className="font-semibold text-indigo-600 underline" href={joinUrl}>{joinUrl}</a></div>
                                <div className="mt-3">
                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            className="rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-50"
                                            onClick={() => {
                                                realtime.stop();
                                                navigate('/host');
                                            }}
                                        >
                                            Ra ngoài danh sách phòng
                                        </button>
                                        {status !== 'finished' ? (
                                            <button
                                                type="button"
                                                className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                                                onClick={onDissolveRoom}
                                            >
                                                Giải tán phòng
                                            </button>
                                        ) : null}
                                    </div>
                                </div>
                            </div>
                            <div className="mt-4 rounded-2xl border border-indigo-100 bg-white/90 p-4 shadow-[0_20px_80px_-30px_rgba(99,102,241,0.45)]">
                                <div className="mb-3 flex items-center justify-between">
                                    <h3 className="text-base font-bold text-indigo-900">Người Chơi Đã Vào</h3>
                                    <span className="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                        {players.length} người
                                    </span>
                                </div>
                                {players.length === 0 ? (
                                    <p className="text-sm text-indigo-700/70">Chưa có người chơi nào vào phòng.</p>
                                ) : (
                                    <div className="space-y-2">
                                        {players.map((player) => (
                                            <div key={player.id} className="flex items-center justify-between rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2">
                                                <div className="font-semibold text-indigo-900">{player.display_name}</div>
                                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${player.gender === 'male' ? 'bg-sky-100 text-sky-700' : 'bg-fuchsia-100 text-fuchsia-700'}`}>
                                                    {player.gender === 'male' ? 'Nam' : 'Nữ'}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                            <LiveRoomPanel
                                roomCode={roomCode}
                                role="host"
                                status={status}
                                question={question}
                                remainingSeconds={remainingSeconds}
                                canFinishQuiz={canFinishQuiz}
                                results={results}
                                resultOverview={resultOverview}
                                resultDetails={resultDetails}
                                hasAnswered={false}
                                onNext={onHostNext}
                                onFinish={onFinishQuiz}
                                onAnswer={() => { }}
                            />
                        </div>
                    ) : null}

                    {isHostAuthenticated && isRoomDetailRoute && !roomCode ? (
                        <div className="mt-6 rounded-2xl border border-indigo-100 bg-white/90 p-6 text-sm text-indigo-700 shadow-[0_20px_80px_-30px_rgba(99,102,241,0.45)]">
                            Đang tải room detail...
                        </div>
                    ) : null}
                </div>
            </div>
            {isQrOverlayOpen ? (
                <div
                    className="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-[2px]"
                    onClick={() => setIsQrOverlayOpen(false)}
                >
                    <div
                        className="flex h-[94vh] w-[94vw] max-w-none flex-col overflow-hidden rounded-3xl border border-white/25 bg-gradient-to-b from-indigo-50 to-violet-100 p-4 shadow-[0_40px_120px_-28px_rgba(15,23,42,0.9)] sm:p-6"
                        onClick={(event) => event.stopPropagation()}
                    >
                        <div className="mb-4 flex shrink-0 items-center justify-between gap-3">
                            <div>
                                <h3 className="text-xl font-black text-indigo-900 sm:text-2xl">Quét QR để vào phòng</h3>
                                <p className="mt-1 text-sm font-medium text-indigo-700/90">Room: {roomCode}</p>
                            </div>
                            <button
                                type="button"
                                className="rounded-lg border border-indigo-300 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-50"
                                onClick={() => setIsQrOverlayOpen(false)}
                            >
                                Đóng
                            </button>
                        </div>

                        <div className="mx-auto flex min-h-0 w-full flex-1 flex-col rounded-2xl border border-indigo-200 bg-white/60 p-3 shadow-[0_20px_60px_-30px_rgba(79,70,229,0.55)] sm:p-4">
                            <div className="flex min-h-0 flex-1 items-center justify-center">
                                <div className="aspect-square w-full max-w-[min(72vh,72vw)] rounded-2xl border border-indigo-200 bg-white p-3 sm:p-5">
                                    {qrImageUrl !== '' ? (
                                        <img
                                            src={qrImageUrl}
                                            alt={`QR join room ${roomCode}`}
                                            className="h-full w-full rounded-xl object-contain"
                                        />
                                    ) : (
                                        <div className="flex aspect-square items-center justify-center rounded-xl bg-slate-100 text-sm font-semibold text-slate-500">
                                            Chưa có link để tạo QR
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="mt-4 break-all rounded-xl border border-indigo-200 bg-white/90 px-3 py-2 text-xs text-indigo-700 sm:text-sm">
                                {qrJoinUrl}
                            </div>
                        </div>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
