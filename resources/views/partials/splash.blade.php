{{-- Loading screen shown on cold start until the Inertia app mounts (see window.hideSplash in app.js). --}}
<style>
    #splash {
        position: fixed; inset: 0; z-index: 9999;
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 22px;
        background: radial-gradient(circle at 50% 42%, #16213A 0%, #0A0E1A 65%);
        transition: opacity .45s ease, visibility .45s ease;
    }
    #splash.is-hidden { opacity: 0; visibility: hidden; }
    #splash svg { width: 112px; height: 112px; overflow: visible; }
    #splash .ring { stroke-dasharray: 100; stroke-dashoffset: 100; animation: splash-draw 1.1s cubic-bezier(.65, 0, .35, 1) forwards; }
    #splash .star { transform-origin: 512px 512px; transform: scale(0); animation: splash-pop .6s .45s cubic-bezier(.34, 1.56, .64, 1) forwards, splash-pulse 1.8s 1.2s ease-in-out infinite; }
    #splash .spark { transform-origin: 704.84px 282.19px; transform: scale(0); animation: splash-pop .5s .85s cubic-bezier(.34, 1.56, .64, 1) forwards, splash-twinkle 1.8s 1.4s ease-in-out infinite; }
    #splash .name { font: 800 26px/1 Figtree, ui-sans-serif, system-ui, sans-serif; letter-spacing: -.02em; color: #fff; opacity: 0; animation: splash-fade .6s .7s ease forwards; }
    #splash .name span { color: #B8F34A; }
    @keyframes splash-draw { to { stroke-dashoffset: 0; } }
    @keyframes splash-pop { to { transform: scale(1); } }
    @keyframes splash-pulse { 50% { transform: scale(.88); } }
    @keyframes splash-twinkle { 50% { transform: scale(.6) rotate(45deg); opacity: .7; } }
    @keyframes splash-fade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    @media (prefers-reduced-motion: reduce) {
        #splash .ring, #splash .star, #splash .spark, #splash .name { animation: none; stroke-dashoffset: 0; transform: none; opacity: 1; }
    }
</style>

<div id="splash" aria-hidden="true">
    <svg viewBox="160 160 704 704">
        <defs>
            <linearGradient id="splash-r" x1="200" y1="820" x2="820" y2="200" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#38BDF8"/>
                <stop offset="1" stop-color="#B8F34A"/>
            </linearGradient>
        </defs>
        <path class="ring" pathLength="100" d="M776.88 371.16A300 300 0 1 1 604.71 226.68" fill="none" stroke="url(#splash-r)" stroke-width="88" stroke-linecap="round"/>
        <path class="star" d="M512 342C539.2 484.8 539.2 484.8 682 512C539.2 539.2 539.2 539.2 512 682C484.8 539.2 484.8 539.2 342 512C484.8 484.8 484.8 484.8 512 342Z" fill="#F7FFE8"/>
        <path class="spark" d="M704.84 224.19C714.12 272.91 714.12 272.91 762.84 282.19C714.12 291.47 714.12 291.47 704.84 340.19C695.56 291.47 695.56 291.47 646.84 282.19C695.56 272.91 695.56 272.91 704.84 224.19Z" fill="#B8F34A"/>
    </svg>
    <div class="name">Kalo <span>Mind</span></div>
</div>

<script>
    (function () {
        var shownAt = Date.now(), MIN_MS = 1200, done = false;
        window.hideSplash = function () {
            if (done) return;
            done = true;
            setTimeout(function () {
                var el = document.getElementById('splash');
                if (!el) return;
                el.classList.add('is-hidden');
                setTimeout(function () { el.remove(); }, 500);
            }, Math.max(0, MIN_MS - (Date.now() - shownAt)));
        };
        // Never trap the user behind the splash if the app fails to boot.
        setTimeout(window.hideSplash, 10000);
    })();
</script>
