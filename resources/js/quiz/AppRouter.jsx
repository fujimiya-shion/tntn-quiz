import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes, useParams } from 'react-router-dom';

const HostPage = lazy(() => import('./pages/HostPage'));
const PlayerPage = lazy(() => import('./pages/PlayerPage'));
const RoleSelectPage = lazy(() => import('./pages/RoleSelectPage'));

function HostRoomRoute() {
    const { roomCode = '' } = useParams();

    return <HostPage initialRoomCode={roomCode} />;
}

function PlayerRoomRoute() {
    const { roomCode = '' } = useParams();

    return <PlayerPage initialRoomCode={roomCode} />;
}

export default function AppRouter({ page, roomCode }) {
    const fallbackPath = (() => {
        if (page === 'player') {
            return roomCode ? `/room/join/${roomCode}` : '/room/join';
        }

        if (page === 'host') {
            return roomCode ? `/host/${roomCode}` : '/host';
        }

        return '/';
    })();

    return (
        <Suspense fallback={<div className="flex min-h-screen items-center justify-center text-sm font-semibold text-indigo-700">Đang tải...</div>}>
            <Routes>
                <Route path="/" element={<RoleSelectPage />} />
                <Route path="/host" element={<HostPage initialRoomCode="" />} />
                <Route path="/host/:roomCode" element={<HostRoomRoute />} />
                <Route path="/room/join" element={<PlayerPage initialRoomCode="" />} />
                <Route path="/room/join/:roomCode" element={<PlayerRoomRoute />} />
                <Route path="/player" element={<Navigate to="/room/join" replace />} />
                <Route path="/player/:roomCode" element={<PlayerRoomRoute />} />
                <Route path="*" element={<Navigate to={fallbackPath} replace />} />
            </Routes>
        </Suspense>
    );
}
