import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Enable Pusher logging for debugging
Pusher.logToConsole = true;

const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1';

if (!pusherKey) {
    console.error('[Pusher] VITE_PUSHER_APP_KEY is not set in .env');
} else {
    console.log('[Pusher] Initializing with key:', pusherKey, 'cluster:', pusherCluster);
}

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: pusherKey,
    cluster: pusherCluster,
    forceTLS: true,
    encrypted: true,
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        },
    },
});


