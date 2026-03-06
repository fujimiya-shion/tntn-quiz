import { useEffect, useState } from 'react';
import { PiGenderFemaleBold, PiGenderMaleBold } from 'react-icons/pi';
import { useNavigate } from 'react-router-dom';
import { Toaster, toast } from 'sonner';
import LiveRoomPanel from '../components/LiveRoomPanel';
import { api, getErrorMessage } from '../api';
import { useRoomRealtime } from '../hooks/useRoomRealtime';

export default function PlayerPage({ initialRoomCode }) {
    const appName = (import.meta.env.VITE_APP_NAME || '').trim() || 'TNTN Quiz';
    const navigate = useNavigate();
    const [roomCode, setRoomCode] = useState(initialRoomCode);
    const [displayName, setDisplayName] = useState('');
    const [gender, setGender] = useState('');
    const [playerToken, setPlayerToken] = useState('');

    const [status, setStatus] = useState('waiting');
    const [question, setQuestion] = useState(null);
    const [remainingSeconds, setRemainingSeconds] = useState(0);
    const [hasAnswered, setHasAnswered] = useState(false);
    const [results, setResults] = useState([]);
    const [resultOverview, setResultOverview] = useState(null);
    const [resultDetails, setResultDetails] = useState([]);

    const [errorMessage, setErrorMessage] = useState('');
    const [infoMessage, setInfoMessage] = useState('');
    const [isLeaveModalOpen, setIsLeaveModalOpen] = useState(false);
    const [leaveMode, setLeaveMode] = useState('back');

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

    const clearMessages = () => {
        setErrorMessage('');
        setInfoMessage('');
    };

    const resetPlayerSession = (nextRoomCode) => {
        setRoomCode(nextRoomCode);
        setPlayerToken('');
        setStatus('waiting');
        setQuestion(null);
        setRemainingSeconds(0);
        setHasAnswered(false);
        setResults([]);
        setResultOverview(null);
        setResultDetails([]);
        setInfoMessage('');
        setIsLeaveModalOpen(false);
    };

    const onConfirmLeave = () => {
        setIsLeaveModalOpen(false);

        if (leaveMode === 'reload') {
            window.location.reload();
            return;
        }

        realtime.stop();
        resetPlayerSession(roomCode);
        navigate('/room/join', { replace: true });
    };

    const syncResults = async (code, questionId, token = null) => {
        const params = { question_id: questionId };

        if (token) {
            params.player_token = token;
        }

        const response = await api.get(`/quiz/rooms/${code}/results`, { params });
        setResults(response.data.options ?? []);
        setResultOverview(response.data.overview ?? null);
        setResultDetails(response.data.answer_details ?? []);
    };

    const syncState = async (code, token) => {
        const response = await api.get(`/quiz/rooms/${code}/state`, { params: { player_token: token } });
        setStatus(response.data.status);
        setQuestion(normalizeQuestion(response.data.question));
        setRemainingSeconds(normalizeSeconds(response.data.remaining_seconds));
        setHasAnswered(Boolean(response.data.has_answered));

        if (response.data.status === 'showing_result' && response.data.current_question_id) {
            await syncResults(code, response.data.current_question_id, token);
            return;
        }

        setResults([]);
        setResultOverview(null);
        setResultDetails([]);
    };

    const onPlay = async (event) => {
        event.preventDefault();
        clearMessages();

        if (gender === '') {
            setErrorMessage('Vui lòng chọn giới tính trước khi vào phòng.');
            return;
        }

        try {
            const code = roomCode.trim().toUpperCase();
            const response = await api.post(`/quiz/rooms/${code}/join`, {
                display_name: displayName || undefined,
                gender,
            });

            setRoomCode(response.data.room_code);
            setPlayerToken(response.data.player_token);
            await syncState(response.data.room_code, response.data.player_token);

            realtime.start({
                roomCode: response.data.room_code,
                playerToken: response.data.player_token,
                onRoomUpdated: async (eventData) => {
                    if (eventData.type === 'quiz_finished' || eventData.type === 'room_dissolved') {
                        realtime.stop();
                        toast.success(eventData.type === 'room_dissolved' ? 'Phòng đã giải tán.' : 'Quiz đã kết thúc.');
                        resetPlayerSession(response.data.room_code);
                        navigate(`/room/join/${response.data.room_code}`, { replace: true });
                        return;
                    }

                    if (eventData.type === 'question_closed' && eventData.payload?.question_id) {
                        await syncResults(response.data.room_code, eventData.payload.question_id, response.data.player_token);
                    }

                    await syncState(response.data.room_code, response.data.player_token);
                },
                onFallback: () => setInfoMessage('Realtime env chưa đủ, không thể đồng bộ ngay lập tức.'),
            });
        } catch (error) {
            setErrorMessage(getErrorMessage(error, 'Không thể vào phòng.'));
        }
    };

    const onAnswer = async (optionId) => {
        clearMessages();

        try {
            await api.post(`/quiz/rooms/${roomCode}/answers`, {
                player_token: playerToken,
                quiz_option_id: optionId,
            });
            setHasAnswered(true);
            setInfoMessage('');
            toast.success('Đã gửi đáp án.');
        } catch (error) {
            setErrorMessage(getErrorMessage(error, 'Gửi đáp án thất bại.'));
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

    useEffect(() => {
        if (!playerToken) {
            return;
        }

        window.history.pushState({ player_guard: true }, '', window.location.href);

        const onBeforeUnload = (event) => {
            event.preventDefault();
            event.returnValue = '';
        };

        const onPopState = () => {
            window.history.pushState({ player_guard: true }, '', window.location.href);
            setLeaveMode('back');
            setIsLeaveModalOpen(true);
        };

        const onKeyDown = (event) => {
            const isReloadKey = event.key === 'F5'
                || ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'r');

            if (!isReloadKey) {
                return;
            }

            event.preventDefault();
            setLeaveMode('reload');
            setIsLeaveModalOpen(true);
        };

        window.addEventListener('beforeunload', onBeforeUnload);
        window.addEventListener('popstate', onPopState);
        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            window.removeEventListener('popstate', onPopState);
            window.removeEventListener('keydown', onKeyDown);
        };
    }, [playerToken]);

    return (
        <div className="min-h-screen bg-[radial-gradient(circle_at_20%_20%,#67e8f9_0,#bfdbfe_35%,#c4b5fd_100%)] text-slate-800">
            <Toaster position="top-right" richColors closeButton duration={3000} />
            <div className="mx-auto flex min-h-screen max-w-6xl items-center justify-center px-4 py-8">
                <div className={`w-full ${playerToken ? 'max-w-3xl' : 'max-w-sm'}`}>
                    <h1 className="mb-3 text-center text-3xl font-black tracking-tight text-indigo-900">{appName}</h1>

                    {errorMessage ? <div className="mb-3 rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-rose-700">{errorMessage}</div> : null}
                    {infoMessage ? <div className="mb-3 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-700">{infoMessage}</div> : null}

                    {!playerToken ? (
                        <form className="space-y-4 rounded-[2rem] border border-white/40 bg-white/80 p-5 shadow-[0_28px_90px_-38px_rgba(67,56,202,0.55)] backdrop-blur-sm" onSubmit={onPlay}>
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-bold text-indigo-900">Vào Phòng</h2>
                                <span className="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">Player</span>
                            </div>
                            <input className="w-full rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2.5 uppercase transition focus:border-indigo-400 focus:bg-white focus:outline-none" placeholder="Room code" value={roomCode} onChange={(e) => setRoomCode(e.target.value)} />
                            <input className="w-full rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2.5 transition focus:border-indigo-400 focus:bg-white focus:outline-none" placeholder="Tên (optional)" value={displayName} onChange={(e) => setDisplayName(e.target.value)} />
                            <div className="space-y-2">
                                <p className="text-xs font-semibold uppercase tracking-wide text-indigo-600">Giới tính (bắt buộc)</p>
                                <div className="grid grid-cols-2 gap-2">
                                    <label className={`flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 transition ${gender === 'male' ? 'border-indigo-500 bg-indigo-100 text-indigo-900' : 'border-indigo-100 bg-white text-slate-600 hover:border-indigo-300'}`}>
                                        <input
                                            type="radio"
                                            name="gender"
                                            value="male"
                                            checked={gender === 'male'}
                                            onChange={(e) => setGender(e.target.value)}
                                            className="sr-only"
                                        />
                                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-white">
                                            <PiGenderMaleBold className="text-base" />
                                        </span>
                                        <span className="font-semibold">Nam</span>
                                    </label>
                                    <label className={`flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 transition ${gender === 'female' ? 'border-fuchsia-500 bg-fuchsia-100 text-fuchsia-900' : 'border-indigo-100 bg-white text-slate-600 hover:border-indigo-300'}`}>
                                        <input
                                            type="radio"
                                            name="gender"
                                            value="female"
                                            checked={gender === 'female'}
                                            onChange={(e) => setGender(e.target.value)}
                                            className="sr-only"
                                        />
                                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-fuchsia-600 text-white">
                                            <PiGenderFemaleBold className="text-base" />
                                        </span>
                                        <span className="font-semibold">Nữ</span>
                                    </label>
                                </div>
                            </div>
                            <button className="w-full rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2.5 font-semibold text-white shadow-[0_12px_30px_-10px_rgba(79,70,229,0.75)] transition hover:from-indigo-500 hover:to-violet-500" type="submit">Play</button>
                        </form>
                    ) : (
                        <LiveRoomPanel
                            roomCode={roomCode}
                            role="player"
                            status={status}
                            question={question}
                            remainingSeconds={remainingSeconds}
                            canFinishQuiz={false}
                            results={results}
                            resultOverview={resultOverview}
                            resultDetails={resultDetails}
                            hasAnswered={hasAnswered}
                            onNext={() => {}}
                            onFinish={() => {}}
                            onAnswer={onAnswer}
                        />
                    )}
                </div>
            </div>
            {isLeaveModalOpen ? (
                <div className="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-[2px]">
                    <div className="w-full max-w-md rounded-2xl border border-white/20 bg-white p-5 shadow-[0_40px_120px_-28px_rgba(15,23,42,0.9)]">
                        <h3 className="text-lg font-black text-indigo-900">Bạn muốn rời phòng?</h3>
                        <p className="mt-2 text-sm text-slate-600">
                            {leaveMode === 'reload'
                                ? 'Bạn có chắc chắn muốn tải lại trang không?'
                                : 'Bạn có chắc chắn muốn quay lại và rời phòng hiện tại không?'}
                        </p>
                        <div className="mt-4 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                onClick={() => setIsLeaveModalOpen(false)}
                            >
                                Ở lại
                            </button>
                            <button
                                type="button"
                                className="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-rose-500"
                                onClick={onConfirmLeave}
                            >
                                Rời phòng
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
