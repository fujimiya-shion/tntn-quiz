import './bootstrap';
import '../css/app.css';

import Pusher from 'pusher-js';
import { createRoot } from 'react-dom/client';
import AppRouter from './quiz/AppRouter';

window.Pusher = Pusher;

const rootElement = document.getElementById('app');

if (rootElement) {
    const page = rootElement.dataset.page || 'landing';
    const roomCode = rootElement.dataset.roomCode || '';

    createRoot(rootElement).render(<AppRouter page={page} roomCode={roomCode} />);
}
