import { useEffect, useState } from 'react';
import emoji from 'react-easy-emoji';
import { Toaster, toast } from 'sonner';
import LiveRoomPanel from '../components/LiveRoomPanel';
import { api, getErrorMessage } from '../api';
import { useRoomRealtime } from '../hooks/useRoomRealtime';

export default function HostPage() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [hostAccessToken, setHostAccessToken] = useState('');

    const [quizzes, setQuizzes] = useState([]);
    const [quizId, setQuizId] = useState('');
    const [hostName, setHostName] = useState('');
    const [hostGender, setHostGender] = useState('');

    const [roomCode, setRoomCode] = useState('');
    const [joinUrl, setJoinUrl] = useState('');
    const [hostToken, setHostToken] = useState('');

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

    const syncResults = async (code, questionId) => {
        const response = await api.get(`/quiz/rooms/${code}/results`, { params: { question_id: questionId } });
        setResults(response.data.options ?? []);
        setResultOverview(response.data.overview ?? null);
        setResultDetails(response.data.answer_details ?? []);
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
        setQuestion(response.data.question);
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
            const loginResponse = await api.post('/quiz/host/login', { email, password });
            const token = loginResponse.data.host_access_token;
            setHostAccessToken(token);

            const quizzesResponse = await api.get('/quiz/host/quizzes', {
                headers: { 'X-Host-Token': token },
            });

            setQuizzes(quizzesResponse.data.quizzes ?? []);
            toast.success('Đăng nhập thành công. Chọn bộ câu hỏi để bắt đầu phòng.');
        } catch (error) {
            toast.error(getErrorMessage(error, 'Đăng nhập host thất bại.'));
        }
    };

    const onStartRoom = async (event) => {
        event.preventDefault();

        try {
            const response = await api.post('/quiz/host/rooms', {
                host_access_token: hostAccessToken,
                quiz_id: Number(quizId),
                host_name: hostName || undefined,
                host_gender: hostGender || undefined,
            });

            setRoomCode(response.data.room_code);
            setJoinUrl(response.data.join_url);
            setHostToken(response.data.host_token);
            setStatus(response.data.status);
            setCanFinishQuiz(false);
            setResults([]);
            setResultOverview(null);
            setResultDetails([]);
            setPlayers([]);

            await syncState(response.data.room_code, response.data.host_token);
            await syncPlayers(response.data.room_code, response.data.host_token);

            realtime.start({
                roomCode: response.data.room_code,
                playerToken: response.data.host_token,
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
                        await syncResults(response.data.room_code, eventData.payload.question_id);
                    }

                    await syncPlayers(response.data.room_code, response.data.host_token);
                    await syncState(response.data.room_code, response.data.host_token);
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
            toast.success(`Tạo phòng thành công: ${response.data.room_code}`);
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
            setQuestion(response.data.question || null);
            setCanFinishQuiz(Boolean(response.data.can_finish_quiz));
            setResults([]);
            setResultOverview(null);
            setResultDetails([]);

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
            toast.success('Đã kết thúc quiz.');
        } catch (error) {
            toast.error(getErrorMessage(error, 'Kết thúc quiz thất bại.'));
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
    const isSetupMode = roomCode === '';

    return (
        <div className="min-h-screen bg-[radial-gradient(circle_at_15%_20%,#cffafe_0,#f8fbff_32%,#eef2ff_100%)] text-slate-800">
            <Toaster position="top-right" richColors closeButton duration={3000} />
            <div className={isSetupMode ? 'mx-auto flex min-h-screen max-w-5xl items-center justify-center px-4 py-10' : 'mx-auto max-w-5xl px-4 py-8'}>
                <div className={isSetupMode ? 'w-full max-w-xl' : 'w-full'}>
                    <div className={isSetupMode ? 'mb-6 text-center' : ''}>
                        <p className="inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700 shadow-sm">
                            {emoji('👑')} Host console
                        </p>
                        <h1 className="mt-3 text-4xl font-black tracking-tight text-indigo-900">Host Quiz</h1>
                        <p className="mt-2 text-sm text-indigo-700/80">Đăng nhập, chọn bộ câu hỏi, bắt đầu phòng và điều phối câu hỏi realtime.</p>
                    </div>

                    {!hostAccessToken ? (
                        <form className="mt-6 space-y-3 rounded-3xl border border-indigo-100 bg-white/90 p-6 shadow-[0_32px_95px_-36px_rgba(99,102,241,0.55)] backdrop-blur-sm" onSubmit={onLogin}>
                            <h2 className="flex items-center gap-2 text-lg font-bold text-indigo-900">{emoji('🔐')} Đăng Nhập Chủ Phòng</h2>
                            <input className="w-full rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2.5 transition focus:border-indigo-400 focus:bg-white focus:outline-none" type="email" placeholder="Email" value={email} onChange={(e) => setEmail(e.target.value)} />
                            <input className="w-full rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2.5 transition focus:border-indigo-400 focus:bg-white focus:outline-none" type="password" placeholder="Password" value={password} onChange={(e) => setPassword(e.target.value)} />
                            <button className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2.5 font-semibold text-white shadow-[0_14px_30px_-12px_rgba(79,70,229,0.75)] transition hover:from-indigo-500 hover:to-violet-500" type="submit">
                                {emoji('🚀')} Đăng Nhập
                            </button>
                        </form>
                    ) : null}

                    {hostAccessToken && !roomCode ? (
                        <form className="mt-6 space-y-3 rounded-3xl border border-indigo-100 bg-white/90 p-6 shadow-[0_32px_95px_-36px_rgba(99,102,241,0.55)] backdrop-blur-sm" onSubmit={onStartRoom}>
                            <h2 className="flex items-center gap-2 text-lg font-bold text-indigo-900">{emoji('🧩')} Chọn Bộ Câu Hỏi Và Bắt Đầu</h2>
                            <select className="w-full rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2.5 transition focus:border-indigo-400 focus:bg-white focus:outline-none" value={quizId} onChange={(e) => setQuizId(e.target.value)}>
                                <option value="">Chọn bộ quiz</option>
                                {quizzes.map((quiz) => (
                                    <option key={quiz.id} value={quiz.id}>{quiz.title} ({quiz.questions_count} câu)</option>
                                ))}
                            </select>
                            <select className="w-full rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2.5 transition focus:border-indigo-400 focus:bg-white focus:outline-none" value={hostGender} onChange={(e) => setHostGender(e.target.value)}>
                                <option value="">Gender host (optional)</option>
                                <option value="male">Nam</option>
                                <option value="female">Nữ</option>
                            </select>
                            <button className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 px-4 py-2.5 font-semibold text-emerald-950 shadow-[0_14px_30px_-12px_rgba(16,185,129,0.7)] transition hover:from-emerald-400 hover:to-teal-400" type="submit">
                                {emoji('🎯')} Bắt Đầu Phòng
                            </button>
                        </form>
                    ) : null}

                    {roomCode ? (
                        <div>
                            <div className="mt-6 rounded-2xl border border-indigo-100 bg-white/90 p-4 text-sm shadow-[0_20px_80px_-30px_rgba(99,102,241,0.45)]">
                                <div className="mb-3 flex items-center justify-between gap-2 rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2">
                                    <div>
                                        <div className="text-[11px] font-semibold uppercase tracking-wide text-indigo-600">Room code</div>
                                        <div className="text-lg font-black tracking-wide text-indigo-900">{roomCode}</div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={onCopyRoomCode}
                                        className="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500"
                                    >
                                        Copy room code
                                    </button>
                                </div>
                                <div>{emoji('🔗')} Link join: <a className="font-semibold text-indigo-600 underline" href={joinUrl}>{joinUrl}</a></div>
                            </div>
                            <div className="mt-4 rounded-2xl border border-indigo-100 bg-white/90 p-4 shadow-[0_20px_80px_-30px_rgba(99,102,241,0.45)]">
                                <div className="mb-3 flex items-center justify-between">
                                    <h3 className="text-base font-bold text-indigo-900">{emoji('🙋')} Người Chơi Đã Vào</h3>
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
                </div>
            </div>
        </div>
    );
}
