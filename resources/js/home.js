// The home page (home page plan H3): the opening scene and the product tour.
// Loaded only on the home page. Without it the page still reads in full:
// the scene shows its end state and the first tour tab is open.

function startScene(scene) {
    const pill = scene.querySelector('[data-scene-pill]');
    const cash = scene.querySelector('[data-scene-cash]');
    const cashIn = scene.querySelector('[data-scene-cash-in]');
    const bar = scene.querySelector('[data-scene-bar]');
    const replay = scene.querySelector('[data-scene-replay]');
    if (!pill || !cash || !cashIn || !bar) {
        return;
    }

    const from = Number(scene.dataset.cashFrom);
    const amount = Number(scene.dataset.amount);
    const paidText = scene.dataset.paidText || '';
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let timers = [];

    const fmt = (n) => '₦' + n.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const setPill = (kind, text) => { pill.className = 'pill ' + kind; pill.textContent = text; };
    const setStage = (n) => {
        scene.classList.remove('s1', 's2', 's3');
        if (n) scene.classList.add('s' + n);
    };

    function count(to, ms) {
        if (reduce) { cash.textContent = fmt(to); return; }
        const t0 = performance.now();
        const step = (t) => {
            const k = Math.min(1, (t - t0) / ms);
            const e = 1 - Math.pow(1 - k, 3);
            cash.textContent = fmt(from + (to - from) * e);
            if (k < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

    function reset() {
        timers.forEach(clearTimeout);
        timers = [];
        setStage(0);
        setPill('sent', 'Sent');
        cash.textContent = fmt(from);
        cashIn.textContent = '';
        bar.classList.remove('h92');
        bar.style.height = '';
    }

    function play() {
        reset();
        const d = reduce ? 0 : 1;
        timers.push(setTimeout(() => { setPill('late', 'Overdue'); setStage(1); }, 900 * d));
        timers.push(setTimeout(() => setStage(2), 1700 * d));
        timers.push(setTimeout(() => {
            setStage(3);
            setPill('paid', 'Paid');
            cashIn.textContent = paidText;
            bar.style.height = '92%';
            count(from + amount, 1100);
        }, 3200 * d));
    }

    // Hide the reminder and payment until their turn (CSS keys off .js).
    scene.classList.add('js');
    if (replay) {
        replay.hidden = false;
        replay.addEventListener('click', play);
    }

    // Play once, when the scene first comes into view.
    if ('IntersectionObserver' in window) {
        const seen = new IntersectionObserver((entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                seen.disconnect();
                play();
            }
        }, { threshold: 0.3 });
        seen.observe(scene);
    } else {
        play();
    }
}

function startTabs(list) {
    const tabs = Array.from(list.querySelectorAll('[role="tab"]'));
    const select = (tab) => {
        tabs.forEach((t) => {
            const on = t === tab;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.tabIndex = on ? 0 : -1;
            const panel = document.getElementById(t.getAttribute('aria-controls'));
            if (panel) panel.hidden = !on;
        });
    };
    tabs.forEach((t, i) => {
        t.addEventListener('click', () => select(t));
        t.addEventListener('keydown', (e) => {
            let n = null;
            if (e.key === 'ArrowDown' || e.key === 'ArrowRight') n = tabs[(i + 1) % tabs.length];
            if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') n = tabs[(i - 1 + tabs.length) % tabs.length];
            if (e.key === 'Home') n = tabs[0];
            if (e.key === 'End') n = tabs[tabs.length - 1];
            if (n) { e.preventDefault(); select(n); n.focus(); }
        });
    });
}

function boot() {
    document.querySelectorAll('[data-scene]').forEach(startScene);
    document.querySelectorAll('[data-tour-tabs]').forEach(startTabs);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
