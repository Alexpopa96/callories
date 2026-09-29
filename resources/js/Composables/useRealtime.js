import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import {ref} from 'vue';

/**
 * One websocket connection per tab, on the signed-in user's private channel.
 * Events only say that something changed; pages then fetch through their usual routes,
 * and keep polling slowly as a fallback for when the connection is down (iOS drops it in the background).
 */

export const realtimeConnected = ref(false);

const EVENTS = ['message.sent', 'messages.read'];
const handlers = Object.fromEntries(EVENTS.map((name) => [name, new Set()]));

let echo = null;
let userId = null;

function start(id) {
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    if (typeof window === 'undefined' || !key || !id) return;
    if (echo && userId === id) return;

    stop();
    window.Pusher = Pusher;

    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'https';
    const port = Number(import.meta.env.VITE_REVERB_PORT) || (scheme === 'https' ? 443 : 80);

    echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        // same session cookie and XSRF header as every other request of the app
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                axios.post('/broadcasting/auth', {socket_id: socketId, channel_name: channel.name})
                    .then((response) => callback(null, response.data))
                    .catch((error) => callback(error, null));
            },
        }),
    });

    echo.connector.pusher.connection.bind('state_change', ({current}) => {
        realtimeConnected.value = current === 'connected';
    });

    userId = id;
    const channel = echo.private(`App.Models.User.${id}`);
    EVENTS.forEach((name) => channel.listen(`.${name}`, (payload) => handlers[name].forEach((handler) => handler(payload))));
}

function stop() {
    echo?.disconnect();
    echo = null;
    userId = null;
    realtimeConnected.value = false;
}

/** Subscribes `handler` to one event for the given user; returns the unsubscribe function. */
export function onRealtime(id, name, handler) {
    start(id);
    handlers[name].add(handler);

    return () => handlers[name].delete(handler);
}
