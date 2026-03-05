import axios from 'axios';
import { useRef } from 'react';

const LOCALHOST_ALIASES = new Set(['localhost', '127.0.0.1', '::1']);

export function useRoomRealtime() {
    const echoRef = useRef(null);

    const stop = () => {
        if (echoRef.current) {
            echoRef.current.disconnect();
            echoRef.current = null;
        }
    };

    const start = async ({
        roomCode,
        playerToken,
        onRoomUpdated,
        onFallback,
        onMembersHere,
        onMemberJoining,
        onMemberLeaving,
    }) => {
        const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
        const runtimeHost = typeof window !== 'undefined' ? window.location.hostname : '';
        const configuredHost = `${import.meta.env.VITE_REVERB_HOST ?? ''}`.trim();
        const isConfiguredHostLocal = configuredHost !== '' && LOCALHOST_ALIASES.has(configuredHost);
        const shouldUseRuntimeHost = runtimeHost !== '' && isConfiguredHostLocal && !LOCALHOST_ALIASES.has(runtimeHost);
        const reverbHost = shouldUseRuntimeHost ? runtimeHost : (configuredHost || runtimeHost);
        const isHttpsPage = typeof window !== 'undefined' ? window.location.protocol === 'https:' : false;
        const reverbScheme = import.meta.env.VITE_REVERB_SCHEME || (isHttpsPage ? 'https' : 'http');
        const reverbPort = Number(import.meta.env.VITE_REVERB_PORT || (reverbScheme === 'https' ? 443 : 8080));

        const canRealtime = Boolean(reverbKey && reverbHost);

        if (!canRealtime) {
            onFallback();
            return;
        }

        const { default: Pusher } = await import('pusher-js');
        window.Pusher = Pusher;
        const { default: Echo } = await import('laravel-echo');

        const echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: reverbHost,
            wsPort: reverbPort,
            wssPort: reverbPort,
            forceTLS: reverbScheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authorizer: (channel) => ({
                authorize: (socketId, callback) => {
                    axios.post(
                        '/broadcasting/auth',
                        {
                            channel_name: channel.name,
                            socket_id: socketId,
                            player_token: playerToken,
                        },
                        {
                            headers: {
                                Authorization: `Bearer ${playerToken}`,
                                'X-Player-Token': playerToken,
                            },
                        }
                    )
                        .then((response) => callback(false, response.data))
                        .catch((error) => callback(true, error));
                },
            }),
        });

        const presenceChannel = echo.join(`quiz-room.${roomCode}`);

        if (onMembersHere) {
            presenceChannel.here(onMembersHere);
        }

        if (onMemberJoining) {
            presenceChannel.joining(onMemberJoining);
        }

        if (onMemberLeaving) {
            presenceChannel.leaving(onMemberLeaving);
        }

        presenceChannel.listen('.quiz.room.updated', onRoomUpdated);

        echoRef.current = echo;
    };

    return { start, stop };
}
