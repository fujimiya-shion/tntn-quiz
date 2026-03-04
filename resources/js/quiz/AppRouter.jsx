import HostPage from './pages/HostPage';
import PlayerPage from './pages/PlayerPage';
import RoleSelectPage from './pages/RoleSelectPage';

export default function AppRouter({ page, roomCode }) {
    if (page === 'player') {
        return <PlayerPage initialRoomCode={roomCode} />;
    }

    if (page === 'landing') {
        return <RoleSelectPage />;
    }

    return <HostPage />;
}
