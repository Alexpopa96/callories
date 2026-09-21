import {Capacitor} from '@capacitor/core';
import {LocalNotifications} from '@capacitor/local-notifications';
import {router} from '@inertiajs/vue3';

export const isNativeApp = () => Capacitor.isNativePlatform();

// Local notifications can't look at today's log, so they fire at fixed hours with generic copy.
const SCHEDULE = {
    meals: [
        {id: 101, hour: 13, title: 'Ai mâncat azi?', body: 'Fotografiază prânzul în câteva secunde.', url: '/scan'},
        {id: 102, hour: 20, title: 'Cum a fost cina?', body: 'Adaugă ce ai mâncat ca să-ți închizi ziua.', url: '/scan'},
    ],
    water: [
        {id: 201, hour: 11, title: 'Bea un pahar cu apă', body: 'Notează-l în aplicație.', url: '/today'},
        {id: 202, hour: 15, title: 'Bea un pahar cu apă', body: 'Ești la jumătatea zilei, hidratează-te.', url: '/today'},
        {id: 203, hour: 19, title: 'Bea un pahar cu apă', body: 'Mai ai timp să-ți atingi obiectivul.', url: '/today'},
    ],
};

/** Replaces this device's scheduled reminders with the ones matching `enabled` ({meals, water}). */
export async function syncNativeReminders(enabled) {
    const all = Object.values(SCHEDULE).flat();
    const wanted = Object.entries(SCHEDULE).filter(([key]) => enabled[key]).flatMap(([, items]) => items);

    await LocalNotifications.cancel({notifications: all.map(({id}) => ({id}))});
    if (!wanted.length) return {ok: true};

    const permission = await LocalNotifications.requestPermissions();
    if (permission.display !== 'granted') {
        return {ok: false, reason: 'Notificările sunt blocate. Permite-le din Setări → Calorii → Notificări.'};
    }

    await LocalNotifications.schedule({
        notifications: wanted.map(({id, hour, title, body, url}) => ({
            id, title, body,
            extra: {url},
            schedule: {on: {hour, minute: 0}, allowWhileIdle: true},
        })),
    });

    return {ok: true};
}

/** Opens the screen a notification points to when the user taps it. */
export function listenForNotificationTaps() {
    if (!isNativeApp()) return;

    LocalNotifications.addListener('localNotificationActionPerformed', ({notification}) => {
        const url = notification.extra?.url;
        if (url) router.visit(url);
    });
}
