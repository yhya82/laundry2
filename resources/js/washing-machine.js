// Renders the .wm washing-machine graphic used on the Machines catalog page
// (card grid + detail panel). Trimmed from the original standalone widget --
// this app already has its own click target (the card button) and its own
// detail panel with real order/customer data, so the widget's built-in
// click-to-open info popover is dropped entirely; only the visual rendering
// (drum, load, water, suds, dial, display) is kept.

const STATES = {
    off: { label: 'Off', level: 0, water: false },
    idle: { label: 'Ready', level: 0, water: false },
    filling: { label: 'Filling', level: 34, water: true },
    washing: { label: 'Washing', level: 30, water: true },
    rinsing: { label: 'Rinsing', level: 26, water: true },
    spinning: { label: 'Spinning', level: 0, water: false },
    done: { label: 'Cycle complete', level: 0, water: false },
    error: { label: 'Needs attention', level: 0, water: false },
};
const RUNNING = ['filling', 'washing', 'rinsing', 'spinning'];
const CLOTH = ['#D8607A', '#3F7FBF', '#E8C25C', '#5FBF9A', '#C9CFD6', '#8A6FC4'];

function drumSVG() {
    const R = 50;
    const rings = [[46, 40, 1.05], [39.5, 34, 1.15], [32.5, 28, 1.25], [25, 22, 1.35], [17, 15, 1.45], [9, 8, 1.5]];
    let holes = '';
    rings.forEach(([r, n, size]) => {
        for (let i = 0; i < n; i++) {
            const a = (i / n) * Math.PI * 2 + (r % 2) * 0.1;
            const x = (R + Math.cos(a) * r).toFixed(2);
            const y = (R + Math.sin(a) * r).toFixed(2);
            holes += `<circle cx="${x}" cy="${y}" r="${size}" fill="url(#hole)"/>`;
        }
    });
    let paddles = '';
    for (let i = 0; i < 3; i++) {
        paddles += `<g transform="rotate(${i * 120} 50 50)">
            <path d="M50 6 L58 16 Q50 22 42 16 Z" fill="url(#pad)" opacity=".95"/>
        </g>`;
    }
    return `<svg viewBox="0 0 100 100" aria-hidden="true">
        <defs>
            <radialGradient id="face" cx="38%" cy="30%">
                <stop offset="0%" stop-color="#7C8996"/><stop offset="55%" stop-color="#4A555F"/>
                <stop offset="100%" stop-color="#232B32"/>
            </radialGradient>
            <radialGradient id="hole" cx="35%" cy="30%">
                <stop offset="0%" stop-color="#0A0E12"/><stop offset="70%" stop-color="#05080A"/>
                <stop offset="100%" stop-color="#161C21"/>
            </radialGradient>
            <linearGradient id="pad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#96A2AD"/><stop offset="100%" stop-color="#4B555E"/>
            </linearGradient>
        </defs>
        <circle cx="50" cy="50" r="50" fill="url(#face)"/>
        <circle cx="50" cy="50" r="49" fill="none" stroke="rgba(255,255,255,.16)" stroke-width=".7"/>
        ${holes}${paddles}
    </svg>`;
}

function buildLoad(el) {
    if (!el) return;
    el.innerHTML = '';
    for (let i = 0; i < 7; i++) {
        const s = document.createElement('span');
        const size = 12 + Math.random() * 12;
        const ang = Math.random() * Math.PI * 2;
        const rad = 8 + Math.random() * 24;
        s.style.cssText = `width:${size}%;height:${size * 0.8}%;
            left:${50 + Math.cos(ang) * rad - size / 2}%;top:${50 + Math.sin(ang) * rad - size / 2}%;
            background:${CLOTH[i % CLOTH.length]};opacity:.82;rotate:${Math.random() * 360}deg`;
        el.appendChild(s);
    }
}

function buildSuds(el) {
    if (!el) return;
    el.innerHTML = '';
    for (let i = 0; i < 14; i++) {
        const b = document.createElement('b');
        const d = 1.4 + Math.random() * 3.4;
        b.style.cssText = `width:${d}em;height:${d}em;left:${8 + Math.random() * 84}%;
            bottom:${Math.random() * 22}%;animation-duration:${3 + Math.random() * 4}s;
            animation-delay:${-Math.random() * 5}s`;
        el.appendChild(b);
    }
}

const pad = n => String(Math.max(0, Math.floor(n))).padStart(2, '0');

/**
 * Pure rendering -- no click handling, no info popover (the app supplies its
 * own click target and its own real detail panel around this graphic).
 */
function mount(target, opts = {}) {
    const root = typeof target === 'string' ? document.querySelector(target) : target;
    if (!root) return null;

    root.querySelector('.drum').innerHTML = drumSVG();
    buildLoad(root.querySelector('.load'));
    buildSuds(root.querySelector('.suds'));

    const brandEl = root.querySelector('.wm__brand');
    if (opts.brand && brandEl) brandEl.textContent = opts.brand;

    const el = {
        time: root.querySelector('.wm__time'),
        program: root.querySelector('.wm__program'),
        water: root.querySelector('.water'),
        dial: root.querySelector('.wm__dial'),
        door: root.querySelector('.wm__door'),
    };

    const model = {
        id: opts.id || '',
        state: opts.state || 'idle',
        program: opts.program || 'Ready',
        remaining: opts.remaining ?? null,
        progress: opts.progress ?? 0,
    };

    function render() {
        const cfg = STATES[model.state] || STATES.idle;
        root.dataset.state = model.state;
        RUNNING.includes(model.state)
            ? root.setAttribute('data-running', '')
            : root.removeAttribute('data-running');

        el.time.textContent = model.remaining == null
            ? (model.state === 'done' ? '00:00' : '--:--')
            : `${pad(model.remaining / 60)}:${pad(model.remaining % 60)}`;
        el.program.textContent = model.state === 'idle' ? model.program : cfg.label;

        el.water.style.setProperty('--level', cfg.level + '%');
        el.door.style.setProperty('--p', model.progress);
        el.dial.style.setProperty('--dial', (-38 + Object.keys(STATES).indexOf(model.state) * 26) + 'deg');

        root.setAttribute('aria-label', `Washing machine${model.id ? ' ' + model.id : ''}, ${cfg.label}`);
    }

    render();

    return {
        set(patch) { Object.assign(model, patch); render(); },
        setState(s) { Object.assign(model, { state: s }); render(); },
        get state() { return { ...model }; },
    };
}

window.WashingMachine = { mount, STATES };
