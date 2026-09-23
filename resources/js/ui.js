import { animate, stagger, hover, press, inView } from 'motion';
import {
    createIcons, AlarmClock, ArrowDownToLine, ArrowUpFromLine, BookText, BookUser, Building2, Calculator,
    ClipboardList, Database, FileBadge, HandCoins, Landmark, LayoutDashboard, ListPlus, LogIn, LogOut, Menu, Receipt,
    Scale, ShieldCheck, Ship, SquareKanban, Stethoscope, TrendingUp, UserCog, UserSearch, Users, Wallet,
    Workflow, X,
} from 'lucide';
import { repeaters, previews, certificateTypes, contracts, pickers } from './forms';

const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const EASE = [0.22, 1, 0.36, 1];

/* Icons: <i data-lucide="ship" class="size-4"></i> becomes an inline SVG. */
function icons() {
    createIcons({
        icons: {
            AlarmClock, ArrowDownToLine, ArrowUpFromLine, BookText, BookUser, Building2, Calculator, ClipboardList,
            Database, FileBadge, HandCoins, Landmark, LayoutDashboard, ListPlus, LogIn, LogOut, Menu, Receipt, Scale,
            ShieldCheck, Ship, SquareKanban, Stethoscope, TrendingUp, UserCog, UserSearch, Users, Wallet, Workflow, X,
        },
        attrs: { 'stroke-width': 1.75, 'aria-hidden': 'true' },
    });
}

/*
 * Page entrance: the blocks of the page rise in one after another, then the first
 * rows of each table. Entrance only — it runs once and gets out of the way.
 */
function enter() {
    const root = document.documentElement;
    const blocks = [...document.querySelectorAll('main > *, [data-enter] > *')];

    if (reduced || blocks.length === 0) {
        root.classList.remove('motion-ready');
        return;
    }

    const runs = [
        animate(blocks, { opacity: [0, 1], y: [12, 0] }, { duration: 0.4, delay: stagger(0.05), ease: EASE }),
    ];

    const rows = [...document.querySelectorAll('main tbody')].flatMap((body) => [...body.rows].slice(0, 20));
    if (rows.length) {
        runs.push(animate(rows, { opacity: [0, 1], x: [-4, 0] }, { duration: 0.25, delay: stagger(0.02, { startDelay: 0.15 }), ease: 'easeOut' }));
    }

    Promise.all(runs.map((run) => run.finished)).finally(() => {
        root.classList.remove('motion-ready');
        // A leftover transform would turn these blocks into containing blocks for
        // anything position: fixed inside them.
        [...blocks, ...rows].forEach((el) => { el.style.transform = ''; el.style.opacity = ''; });
    });
}

/* Numbers marked data-count tick up from zero, keeping their thousands separator. */
function counters() {
    document.querySelectorAll('[data-count]').forEach((el) => {
        const raw = el.textContent.trim();
        if (reduced || !/^\d{1,3}([.,]\d{3})*$|^\d+$/.test(raw)) return;

        const sep = raw.match(/[.,]/)?.[0] ?? '';
        const target = parseInt(raw.replace(/[.,]/g, ''), 10);
        const format = (n) => String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, sep);

        el.textContent = format(0);
        animate(0, target, { duration: 0.9, delay: 0.2, ease: EASE, onUpdate: (v) => { el.textContent = format(v); } });
    });
}

/*
 * Chart marks grow from their baseline the first time their chart scrolls into view:
 * [data-bar] sideways (horizontal bars), [data-col] upwards (columns). They are
 * collapsed from JS, not CSS, so without JS the chart simply shows at full size.
 */
function bars() {
    if (reduced) return;

    const groups = new Map();
    document.querySelectorAll('[data-bar], [data-col]').forEach((mark) => {
        const chart = mark.closest('[data-chart]') ?? document.body;
        if (!groups.has(chart)) groups.set(chart, []);
        groups.get(chart).push(mark);
    });

    groups.forEach((marks, chart) => {
        marks.forEach((m) => { m.style.transform = m.hasAttribute('data-col') ? 'scaleY(0)' : 'scaleX(0)'; });

        const stop = inView(chart, () => {
            stop();
            marks.forEach((m, i) => {
                const vertical = m.hasAttribute('data-col');
                animate(m, vertical ? { scaleY: [0, 1] } : { scaleX: [0, 1] }, {
                    type: 'spring', stiffness: 170, damping: 24, delay: 0.1 + i * 0.035,
                });
            });
        }, { amount: 0.25 });
    });
}

/*
 * One shared tooltip: every [data-tip] (bar rows, columns) and the area charts'
 * crosshair use it. It sits above the point it describes, clamped to the viewport.
 */
let tipEl = null;
let tipOwner = null;

function tipBox() {
    if (tipEl) return tipEl;
    tipEl = document.createElement('div');
    tipEl.setAttribute('role', 'tooltip');
    tipEl.className = 'pointer-events-none fixed z-50 max-w-64 rounded-lg bg-navy px-3 py-2 text-xs text-white shadow-lg shadow-navy/20 opacity-0';
    tipEl.innerHTML = '<div class="font-semibold" data-t></div><div class="text-slate-300 mt-0.5" data-b></div>';
    document.body.appendChild(tipEl);
    return tipEl;
}

/** Show the tooltip centred on x, above `top` (or below `bottom` when there is no room). */
function showTip(owner, x, top, bottom, title, body) {
    const tip = tipBox();
    const fresh = tipOwner === null;
    tipOwner = owner;
    tip.querySelector('[data-t]').textContent = title ?? '';
    tip.querySelector('[data-b]').textContent = body ?? '';

    const { width, height } = tip.getBoundingClientRect();
    const left = Math.min(Math.max(8, x - width / 2), window.innerWidth - width - 8);
    const above = top - height - 10;
    tip.style.left = `${left}px`;
    tip.style.top = `${above > 8 ? above : bottom + 10}px`;

    if (fresh && !reduced) animate(tip, { opacity: [0, 1], y: [4, 0] }, { duration: 0.15, ease: EASE });
    else tip.style.opacity = '1';
}

function hideTip(owner) {
    if (!tipEl || tipOwner !== owner) return;
    tipOwner = null;
    if (reduced) tipEl.style.opacity = '0';
    else animate(tipEl, { opacity: 0 }, { duration: 0.1 });
}

function tooltips() {
    document.querySelectorAll('[data-tip]').forEach((el) => {
        const show = () => {
            const box = (el.querySelector('[data-col], [data-bar]') ?? el).getBoundingClientRect();
            showTip(el, box.left + box.width / 2, box.top, box.bottom, el.dataset.tipTitle, el.dataset.tipBody);
        };
        el.addEventListener('pointerenter', show);
        el.addEventListener('focus', show);
        el.addEventListener('pointerleave', () => hideTip(el));
        el.addEventListener('blur', () => hideTip(el));
    });
    window.addEventListener('scroll', () => tipOwner && hideTip(tipOwner), { passive: true });
}

/*
 * Area charts ([data-area-chart]), Google Finance style: a 2px line over a gradient
 * wash, coloured by the trend across the selected range, with range buttons and a
 * crosshair that snaps to the nearest month (pointer or arrow keys).
 */
const TREND = { up: '#1e8e3e', down: '#d93025', flat: '#1a73e8' };
const SVG_NS = 'http://www.w3.org/2000/svg';
let areaSeq = 0;

function niceScale(peak) {
    const raw = Math.max(peak, 4) / 4;
    const mag = 10 ** Math.floor(Math.log10(raw));
    const step = Math.max(1, Math.ceil([1, 2, 5, 10].map((m) => m * mag).find((v) => v >= raw)));
    return { step, top: step * 4 };
}

function svgEl(name, attrs = {}) {
    const node = document.createElementNS(SVG_NS, name);
    Object.entries(attrs).forEach(([k, v]) => node.setAttribute(k, v));
    return node;
}

function areaCharts() {
    document.querySelectorAll('[data-area-chart]').forEach((root) => {
        const all = JSON.parse(root.dataset.points || '[]');
        const unit = root.dataset.unit || '';
        const plot = root.querySelector('[data-area-plot]');
        const totalEl = root.querySelector('[data-area-total]');
        const deltaEl = root.querySelector('[data-area-delta]');
        const buttons = [...root.querySelectorAll('[data-area-range]')];
        const id = `area-grad-${++areaSeq}`;
        if (!plot || all.length < 2) return;

        let months = Number(buttons.find((b) => b.getAttribute('aria-pressed') === 'true')?.dataset.areaRange ?? all.length);
        let seen = false;
        let active = null;
        let geo = null;

        const render = (animated) => {
            const pts = all.slice(-months);
            const W = plot.clientWidth;
            const H = plot.clientHeight;
            const pad = { l: 34, r: 14, t: 14, b: 26 };
            const { step, top } = niceScale(Math.max(...pts.map((p) => p.value)));
            const x = (i) => pad.l + (i * (W - pad.l - pad.r)) / (pts.length - 1);
            const y = (v) => pad.t + (1 - v / top) * (H - pad.t - pad.b);

            const first = pts[0].value;
            const last = pts[pts.length - 1].value;
            const trend = last > first ? 'up' : last < first ? 'down' : 'flat';
            const color = TREND[trend];

            // Headline: total in range, and how the range ended against how it began.
            const total = pts.reduce((sum, p) => sum + p.value, 0);
            if (totalEl) {
                if (animated && !reduced) {
                    const from = Number(totalEl.textContent.replace(/[^\d]/g, '')) || 0;
                    animate(from, total, { duration: 0.6, ease: EASE, onUpdate: (v) => { totalEl.textContent = Math.round(v).toLocaleString('en-US'); } });
                } else {
                    totalEl.textContent = total.toLocaleString('en-US');
                }
            }
            if (deltaEl) {
                const diff = last - first;
                deltaEl.style.color = color;
                deltaEl.textContent = trend === 'flat'
                    ? `No change · ${pts[0].label} → ${pts[pts.length - 1].label}`
                    : `${diff > 0 ? '▲' : '▼'} ${Math.abs(diff)} · ${pts[pts.length - 1].label} vs ${pts[0].label}`;
            }

            const svg = svgEl('svg', { width: W, height: H, viewBox: `0 0 ${W} ${H}`, class: 'block overflow-visible', 'aria-hidden': 'true' });

            const defs = svgEl('defs');
            const grad = svgEl('linearGradient', { id, x1: 0, y1: 0, x2: 0, y2: 1 });
            grad.append(
                svgEl('stop', { offset: '0%', 'stop-color': color, 'stop-opacity': 0.22 }),
                svgEl('stop', { offset: '100%', 'stop-color': color, 'stop-opacity': 0 }),
            );
            defs.append(grad);
            svg.append(defs);

            // Hairline grid + y ticks, recessive.
            for (let v = 0; v <= top; v += step) {
                svg.append(svgEl('line', { x1: pad.l, x2: W - pad.r, y1: y(v), y2: y(v), stroke: v === 0 ? '#cbd5e1' : '#eef2f6', 'stroke-width': 1 }));
                const t = svgEl('text', { x: pad.l - 8, y: y(v) + 4, 'text-anchor': 'end', fill: '#94a3b8', 'font-size': 11 });
                t.textContent = v;
                svg.append(t);
            }

            // X labels; thin them out when the slots get narrow.
            const every = (W - pad.l - pad.r) / pts.length < 34 ? 2 : 1;
            pts.forEach((p, i) => {
                if ((pts.length - 1 - i) % every) return;
                const isLast = i === pts.length - 1;
                const t = svgEl('text', { x: x(i), y: H - 6, 'text-anchor': 'middle', fill: isLast ? '#0f172a' : '#94a3b8', 'font-size': 11, 'font-weight': isLast ? 500 : 400 });
                t.textContent = p.label;
                svg.append(t);
            });

            const line = pts.map((p, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(p.value).toFixed(1)}`).join(' ');
            const area = svgEl('path', { d: `${line} L${x(pts.length - 1)},${y(0)} L${x(0)},${y(0)} Z`, fill: `url(#${id})` });
            const path = svgEl('path', { d: line, fill: 'none', stroke: color, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' });
            const endDot = svgEl('circle', { cx: x(pts.length - 1), cy: y(last), r: 4, fill: color, stroke: '#fff', 'stroke-width': 2 });

            // Crosshair layer, hidden until hover/focus.
            const cross = svgEl('line', { y1: pad.t, y2: y(0), stroke: '#94a3b8', 'stroke-width': 1, opacity: 0 });
            const dot = svgEl('circle', { r: 5, fill: color, stroke: '#fff', 'stroke-width': 2, opacity: 0 });

            svg.append(area, path, endDot, cross, dot);
            plot.replaceChildren(svg);
            geo = { pts, x, y, cross, dot };

            if (animated && !reduced) {
                const len = path.getTotalLength();
                path.style.strokeDasharray = `${len}`;
                path.style.strokeDashoffset = `${len}`;
                animate(path, { strokeDashoffset: [len, 0] }, { duration: 0.9, ease: EASE })
                    .finished.then(() => { path.style.strokeDasharray = ''; path.style.strokeDashoffset = ''; });
                animate(area, { opacity: [0, 1] }, { duration: 0.6, delay: 0.3 });
                animate(endDot, { opacity: [0, 1] }, { duration: 0.3, delay: 0.8 });
            }
        };

        const point = (i) => {
            if (!geo) return;
            active = Math.max(0, Math.min(geo.pts.length - 1, i));
            const p = geo.pts[active];
            const px = geo.x(active);
            const py = geo.y(p.value);
            geo.cross.setAttribute('x1', px);
            geo.cross.setAttribute('x2', px);
            geo.cross.setAttribute('opacity', 1);
            geo.dot.setAttribute('cx', px);
            geo.dot.setAttribute('cy', py);
            geo.dot.setAttribute('opacity', 1);
            const box = plot.getBoundingClientRect();
            showTip(root, box.left + px, box.top + py - 4, box.top + py + 4, p.full, `${p.value} ${unit}`);
        };

        const clear = () => {
            active = null;
            geo?.cross.setAttribute('opacity', 0);
            geo?.dot.setAttribute('opacity', 0);
            hideTip(root);
        };

        plot.addEventListener('pointermove', (e) => {
            if (!geo) return;
            const rel = e.clientX - plot.getBoundingClientRect().left;
            let nearest = 0;
            for (let i = 1; i < geo.pts.length; i++) {
                if (Math.abs(geo.x(i) - rel) < Math.abs(geo.x(nearest) - rel)) nearest = i;
            }
            if (nearest !== active) point(nearest);
        });
        plot.addEventListener('pointerleave', clear);
        plot.addEventListener('blur', clear);
        plot.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                e.preventDefault();
                point(active === null ? geo.pts.length - 1 : active + (e.key === 'ArrowRight' ? 1 : -1));
            } else if (e.key === 'Escape') {
                clear();
            }
        });

        buttons.forEach((b) => b.addEventListener('click', () => {
            months = Number(b.dataset.areaRange);
            buttons.forEach((o) => o.setAttribute('aria-pressed', String(o === b)));
            clear();
            render(true);
        }));

        new ResizeObserver(() => { if (seen) { clear(); render(false); } }).observe(plot);

        // Drawn at once, but only revealed — line drawing itself — when scrolled into view.
        render(false);
        if (reduced) { seen = true; return; }
        plot.firstChild.style.opacity = '0';
        const stop = inView(root, () => {
            stop();
            seen = true;
            render(true);
        }, { amount: 0.3 });
    });
}

/* Tactile feedback: primary buttons give under the press, link-cards lift on hover. */
function gestures() {
    if (reduced) return;

    press('a.bg-brand, button.bg-brand, [data-press]', (el) => {
        animate(el, { scale: 0.97 }, { duration: 0.1 });
        return () => animate(el, { scale: 1 }, { type: 'spring', stiffness: 500, damping: 28 });
    });

    hover('a.bg-panel', (el) => {
        animate(el, { y: -2 }, { duration: 0.2, ease: EASE });
        return () => animate(el, { y: 0 }, { duration: 0.2, ease: EASE });
    });
}

/* Flash messages slide in and can be dismissed. */
function flashes() {
    document.querySelectorAll('[data-flash]').forEach((flash) => {
        flash.querySelector('[data-flash-close]')?.addEventListener('click', async () => {
            if (!reduced) {
                await animate(flash, { opacity: 0, y: -6 }, { duration: 0.15 }).finished;
            }
            flash.remove();
        });
    });
}

/* Sidebar as a drawer below lg. */
function drawer() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    if (!sidebar || !backdrop || !toggle) return;

    const desktop = window.matchMedia('(min-width: 1024px)');
    let open = false;

    const setOpen = async (next) => {
        if (open === next) return;
        open = next;
        toggle.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('overflow-hidden', open);

        if (open) {
            backdrop.classList.remove('hidden');
            sidebar.classList.remove('-translate-x-full');
            if (!reduced) {
                animate(backdrop, { opacity: [0, 1] }, { duration: 0.2 });
                await animate(sidebar, { x: ['-100%', '0%'] }, { type: 'spring', stiffness: 380, damping: 36 }).finished;
            }
            sidebar.querySelector('a')?.focus();
        } else {
            if (!reduced) {
                animate(backdrop, { opacity: 0 }, { duration: 0.15 });
                await animate(sidebar, { x: '-100%' }, { duration: 0.2, ease: 'easeIn' }).finished;
            }
            sidebar.style.transform = '';
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
            toggle.focus();
        }
    };

    toggle.addEventListener('click', () => setOpen(!open));
    backdrop.addEventListener('click', () => setOpen(false));
    sidebar.querySelector('[data-sidebar-close]')?.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && open) setOpen(false); });
    desktop.addEventListener('change', () => {
        if (!desktop.matches) return;
        open = false;
        sidebar.style.transform = '';
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        toggle.setAttribute('aria-expanded', 'false');
    });
}

/*
 * Tabs: any [data-tab-group] gets its panels switched instead of stacked, so long
 * forms and detail pages stay on one screen. Hidden panels still submit their inputs;
 * an invalid field simply pulls its own tab open first.
 */
function tabs() {
    document.querySelectorAll('[data-tab-group]').forEach((group) => {
        const buttons = group.querySelectorAll('[data-tab-target]');
        const panels = group.querySelectorAll('[data-tab-panel]');

        const show = (key, animated = false) => {
            panels.forEach((panel) => {
                const on = panel.dataset.tabPanel === key;
                panel.classList.toggle('hidden', !on);
                if (on && animated && !reduced) {
                    animate(panel, { opacity: [0, 1], y: [4, 0] }, { duration: 0.2, ease: EASE })
                        .finished.then(() => { panel.style.transform = ''; });
                }
            });
            buttons.forEach((button) => {
                const on = button.dataset.tabTarget === key;
                button.classList.toggle('border-brand', on);
                button.classList.toggle('text-brand', on);
                button.classList.toggle('font-medium', on);
                button.classList.toggle('border-transparent', !on);
                button.classList.toggle('text-muted', !on);
                button.setAttribute('aria-selected', String(on));
            });
            group.dataset.activeTab = key;
        };

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                if (group.dataset.activeTab !== button.dataset.tabTarget) show(button.dataset.tabTarget, true);
            });
        });

        // A panel holding a field the server rejected wins; otherwise the first one.
        let withError = null;
        (window.__formErrors || []).some((name) => {
            const field = group.querySelector(`[name="${CSS.escape(name)}"]`);
            withError = field ? field.closest('[data-tab-panel]') : null;
            return !!withError;
        });

        show(withError ? withError.dataset.tabPanel : (panels[0] ? panels[0].dataset.tabPanel : ''));

        // Native validation cannot focus a hidden field: open its tab first.
        group.addEventListener('invalid', (event) => {
            const panel = event.target.closest('[data-tab-panel]');
            if (panel && panel.classList.contains('hidden')) {
                show(panel.dataset.tabPanel);
                setTimeout(() => event.target.focus(), 0);
            }
        }, true);
    });
}

/*
 * Grouped column charts ([data-column-chart]) for money: one group per period, one
 * column per series, a shared zero line so losses hang below it. `diverging` colours
 * a single series by sign (green gain / red loss, ▲/▼ in the tooltip). Hovering or
 * arrowing onto a period highlights it and shows every series' value.
 */
export function idr(value) {
    const abs = Math.abs(value);
    const [div, unit] = abs >= 1e12 ? [1e12, 'T'] : abs >= 1e9 ? [1e9, 'B'] : abs >= 1e6 ? [1e6, 'M'] : abs >= 1e3 ? [1e3, 'K'] : [1, ''];
    const n = abs / div;
    const digits = div === 1 || n >= 100 ? 0 : n >= 10 ? 1 : 2;
    return `${value < 0 ? '−' : ''}${n.toLocaleString('en-US', { maximumFractionDigits: digits })}${unit}`;
}

function moneyScale(min, max) {
    const span = Math.max(max - Math.min(min, 0), 1);
    const raw = span / 4;
    const mag = 10 ** Math.floor(Math.log10(raw));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * mag).find((v) => v >= raw);
    const lo = min < 0 ? -Math.ceil(-min / step) * step : 0;
    const hi = Math.max(Math.ceil(max / step) * step, lo + step);
    return { lo, hi, step };
}

function columnCharts() {
    document.querySelectorAll('[data-column-chart]').forEach((root) => {
        const data = JSON.parse(root.dataset.columnChart || '{}');
        const plot = root.querySelector('[data-column-plot]');
        const labels = data.labels ?? [];
        const series = data.series ?? [];
        if (!plot || !labels.length || !series.length) return;

        let active = -1;
        let grown = reduced;
        let geo = null;

        const valueColor = (s, v) => (data.diverging ? (v < 0 ? TREND.down : TREND.up) : s.color);

        const render = () => {
            const W = plot.clientWidth;
            const H = plot.clientHeight;
            const pad = { l: 52, r: 8, t: 10, b: 28 };
            const all = series.flatMap((s) => s.values);
            const { lo, hi, step } = moneyScale(Math.min(...all, 0), Math.max(...all, 0));
            const y = (v) => pad.t + ((hi - v) / (hi - lo)) * (H - pad.t - pad.b);
            const band = (W - pad.l - pad.r) / labels.length;
            const inner = Math.min(band * 0.72, series.length * 26);
            const colW = Math.max(2, (inner - (series.length - 1) * 2) / series.length);
            geo = { pad, band, W, H, y };

            const svg = svgEl('svg', { width: W, height: H, viewBox: `0 0 ${W} ${H}`, class: 'block overflow-visible', 'aria-hidden': 'true' });

            for (let v = lo; v <= hi + step / 2; v += step) {
                const yy = Math.round(y(v)) + 0.5;
                svg.appendChild(svgEl('line', { x1: pad.l, x2: W - pad.r, y1: yy, y2: yy, stroke: v === 0 ? '#94a3b8' : '#e2e8f0', 'stroke-width': 1 }));
                const t = svgEl('text', { x: pad.l - 8, y: yy + 3.5, 'text-anchor': 'end', class: 'fill-slate-400', 'font-size': 10 });
                t.textContent = idr(v);
                svg.appendChild(t);
            }

            const every = Math.ceil(labels.length / Math.max(1, Math.floor((W - pad.l) / 56)));
            labels.forEach((label, i) => {
                const cx = pad.l + band * i + band / 2;
                if (i === active) svg.appendChild(svgEl('rect', { x: pad.l + band * i + 1, y: pad.t, width: band - 2, height: H - pad.t - pad.b, rx: 4, fill: '#f1f5f9' }));
                if (i % every === 0 || i === labels.length - 1) {
                    const t = svgEl('text', { x: cx, y: H - 8, 'text-anchor': 'middle', class: i === active ? 'fill-slate-900' : 'fill-slate-500', 'font-size': 10.5 });
                    t.textContent = label;
                    svg.appendChild(t);
                }

                series.forEach((s, k) => {
                    const v = s.values[i] ?? 0;
                    if (!v) return;
                    const x = cx - inner / 2 + k * (colW + 2);
                    const top = y(Math.max(v, 0));
                    const h = Math.max(1, Math.abs(y(v) - y(0)));
                    const r = Math.min(4, colW / 2, h);
                    // Round the data end only: the top of a gain, the bottom of a loss.
                    const d = v >= 0
                        ? `M${x},${top + h}V${top + r}q0,-${r} ${r},-${r}h${colW - 2 * r}q${r},0 ${r},${r}V${top + h}Z`
                        : `M${x},${top}V${top + h - r}q0,${r} ${r},${r}h${colW - 2 * r}q${r},0 ${r},-${r}V${top}Z`;
                    const path = svgEl('path', { d, fill: valueColor(s, v), opacity: active === -1 || active === i ? 1 : 0.35 });
                    if (!grown) path.style.transformOrigin = `0 ${y(0)}px`, path.style.transform = 'scaleY(0)', path.dataset.grow = '';
                    svg.appendChild(path);
                });
            });

            plot.replaceChildren(svg);
        };

        const tip = (i) => {
            const box = plot.getBoundingClientRect();
            const x = box.left + geo.pad.l + geo.band * i + geo.band / 2;
            const body = series.map((s) => {
                const v = s.values[i] ?? 0;
                const arrow = data.diverging ? (v < 0 ? '▼ ' : '▲ ') : '';
                return `${series.length > 1 ? s.name + ': ' : arrow}Rp ${idr(v)}`;
            });
            if (data.notes?.[i]) body.push(data.notes[i]);
            showTip(plot, x, box.top + geo.pad.t, box.bottom, data.full?.[i] ?? labels[i], body.join(' · '));
        };

        const focusAt = (i) => {
            active = i;
            render();
            if (i >= 0) tip(i); else hideTip(plot);
        };

        plot.addEventListener('pointermove', (e) => {
            const box = plot.getBoundingClientRect();
            const i = Math.floor((e.clientX - box.left - geo.pad.l) / geo.band);
            if (i >= 0 && i < labels.length && i !== active) focusAt(i);
        });
        plot.addEventListener('pointerleave', () => focusAt(-1));
        plot.addEventListener('blur', () => focusAt(-1));
        plot.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
            e.preventDefault();
            focusAt(Math.min(labels.length - 1, Math.max(0, (active < 0 ? labels.length : active) + (e.key === 'ArrowRight' ? 1 : -1))));
        });

        render();
        new ResizeObserver(() => render()).observe(plot);

        if (!grown) {
            const stop = inView(root, () => {
                // Later renders (hover, resize) draw full columns; these grow in place.
                grown = true;
                const cols = [...plot.querySelectorAll('[data-grow]')];
                animate(cols, { transform: ['scaleY(0)', 'scaleY(1)'] }, { duration: 0.6, ease: EASE, delay: stagger(0.02) });
                stop();
            }, { amount: 0.3 });
        }
    });
}

export function boot() {
    icons();
    tabs();
    repeaters();
    previews();
    certificateTypes();
    contracts();
    pickers();
    drawer();
    flashes();
    enter();
    counters();
    bars();
    tooltips();
    areaCharts();
    columnCharts();
    gestures();
}
