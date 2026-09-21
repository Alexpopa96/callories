import axios from 'axios';

export const pushSupported = () => (
    typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
);

function keyToBytes(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (char) => char.charCodeAt(0));
}

/** Asks for permission, subscribes this device and registers it on the server. */
export async function enablePush(publicKey) {
    if (!pushSupported()) return {ok: false, reason: 'Browserul acesta nu suportă notificări.'};
    if (!publicKey) return {ok: false, reason: 'Notificările nu sunt configurate pe server.'};

    if ((await Notification.requestPermission()) !== 'granted') {
        return {ok: false, reason: 'Notificările sunt blocate. Permite-le din setările browserului.'};
    }

    try {
        await navigator.serviceWorker.register('/sw.js');
        const registration = await navigator.serviceWorker.ready;
        const subscription = (await registration.pushManager.getSubscription())
            ?? await registration.pushManager.subscribe({userVisibleOnly: true, applicationServerKey: keyToBytes(publicKey)});

        await axios.post('/push/subscribe', subscription.toJSON());

        return {ok: true};
    } catch {
        return {ok: false, reason: 'Nu am putut activa notificările pe acest dispozitiv.'};
    }
}

export async function disablePush() {
    if (!pushSupported()) return;

    try {
        const registration = await navigator.serviceWorker.getRegistration('/sw.js');
        const subscription = await registration?.pushManager.getSubscription();
        if (!subscription) return;

        await axios.post('/push/unsubscribe', {endpoint: subscription.endpoint});
        await subscription.unsubscribe();
    } catch {
        // nothing to clean up on this device
    }
}
