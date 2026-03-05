import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import AppRouter from './quiz/AppRouter';

const rootElement = document.getElementById('app');

if (rootElement) {
    const page = rootElement.dataset.page || 'landing';
    const roomCode = rootElement.dataset.roomCode || '';

    createRoot(rootElement).render(
        <BrowserRouter>
            <AppRouter page={page} roomCode={roomCode} />
        </BrowserRouter>,
    );
}
