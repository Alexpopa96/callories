import {ref} from 'vue';

// Face ID lock for the web app / PWA. Uses a WebAuthn platform credential only to make
// the device verify the owner (Face ID / Touch ID / passcode); nothing is checked server side.
const STORAGE_KEY = 'appLockCredential';
const RELOCK_AFTER_MS = 60_000;

const read = () => {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
};

const toBase64 = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer)));
const fromBase64 = (text) => Uint8Array.from(atob(text), (c) => c.charCodeAt(0));
const randomBytes = (length) => crypto.getRandomValues(new Uint8Array(length));

export const appLockEnabled = ref(!!read());
export const appLocked = ref(appLockEnabled.value);

export const appLockSupported = () => typeof window !== 'undefined' && !!window.PublicKeyCredential && !!navigator.credentials;

export async function enableAppLock(user) {
    if (!appLockSupported() || !(await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable())) {
        return {ok: false, reason: 'Dispozitivul acesta nu are Face ID / Touch ID disponibil în browser.'};
    }

    try {
        const credential = await navigator.credentials.create({
            publicKey: {
                challenge: randomBytes(32),
                rp: {name: document.title.split(' - ').pop() || 'Kalo Mind', id: location.hostname},
                user: {id: randomBytes(16), name: user.email, displayName: user.name},
                pubKeyCredParams: [{type: 'public-key', alg: -7}, {type: 'public-key', alg: -257}],
                authenticatorSelection: {authenticatorAttachment: 'platform', userVerification: 'required', residentKey: 'discouraged'},
                timeout: 60_000,
            },
        });
        localStorage.setItem(STORAGE_KEY, toBase64(credential.rawId));
        appLockEnabled.value = true;
        return {ok: true};
    } catch {
        return {ok: false, reason: 'Activarea a fost anulată sau nu a reușit.'};
    }
}

export function disableAppLock() {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        // storage unavailable, nothing to remove
    }
    appLockEnabled.value = false;
    appLocked.value = false;
}

let unlocking = null;

export function unlockApp() {
    const id = read();
    if (!id) {
        appLocked.value = false;
        return Promise.resolve(true);
    }

    unlocking ??= navigator.credentials.get({
        publicKey: {
            challenge: randomBytes(32),
            rpId: location.hostname,
            allowCredentials: [{type: 'public-key', id: fromBase64(id), transports: ['internal']}],
            userVerification: 'required',
            timeout: 60_000,
        },
    }).then(() => {
        appLocked.value = false;
        return true;
    }).catch(() => false).finally(() => {
        unlocking = null;
    });

    return unlocking;
}

let hiddenAt = null;

if (typeof document !== 'undefined') document.addEventListener('visibilitychange', () => {
    if (!appLockEnabled.value) return;

    if (document.visibilityState === 'hidden') {
        hiddenAt = Date.now();
    } else if (hiddenAt && Date.now() - hiddenAt > RELOCK_AFTER_MS) {
        appLocked.value = true;
        hiddenAt = null;
    }
});
