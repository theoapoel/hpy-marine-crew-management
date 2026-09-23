import { animate } from 'motion';

const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const EASE = [0.22, 1, 0.36, 1];

/*
 * Repeaters: [data-repeater="prefix"] holds [data-repeater-rows] of [data-repeater-row].
 * Adding clones the last row with its fields emptied and renamed prefix[n][…] with a
 * fresh n — never a reused one, so a removed row cannot make two rows share a name.
 * Removing the only row empties it instead. [data-repeater-clear] inside a row (a link
 * to the saved file, say) belongs to that row alone and is dropped from clones.
 */
export function repeaters() {
    document.querySelectorAll('[data-repeater]').forEach((root) => {
        const prefix = root.dataset.repeater;
        const list = root.querySelector('[data-repeater-rows]');
        if (!list) return;

        const pattern = new RegExp(`^${prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\[(\\d+)]`);
        let next = 1 + Math.max(-1, ...[...list.querySelectorAll('[name]')].map((f) => Number(f.name.match(pattern)?.[1] ?? -1)));

        const empty = (row) => {
            row.querySelectorAll('input, select, textarea').forEach((field) => {
                if (field.type === 'checkbox' || field.type === 'radio') field.checked = false;
                else if (field.tagName === 'SELECT') field.selectedIndex = 0;
                else field.value = '';
            });
            row.querySelectorAll('[data-repeater-clear]').forEach((el) => el.remove());
        };

        root.querySelector('[data-repeater-add]')?.addEventListener('click', () => {
            const rows = list.querySelectorAll('[data-repeater-row]');
            const clone = rows[rows.length - 1].cloneNode(true);
            const index = next++;

            clone.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(pattern, `${prefix}[${index}]`);
            });
            empty(clone);
            list.appendChild(clone);

            if (!reduced) animate(clone, { opacity: [0, 1], y: [-6, 0] }, { duration: 0.25, ease: EASE }).finished.then(() => { clone.style.transform = ''; });
            clone.querySelector('input:not([type=hidden]), select, textarea')?.focus();
        });

        list.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-repeater-remove]');
            if (!button) return;

            const row = button.closest('[data-repeater-row]');
            if (list.querySelectorAll('[data-repeater-row]').length === 1) {
                empty(row);
                return;
            }
            if (!reduced) await animate(row, { opacity: 0, scale: 0.98 }, { duration: 0.15 }).finished;
            row.remove();
        });
    });
}

/*
 * Document preview: any [data-preview="url"] opens the file in a dialog over the page —
 * images and PDFs inline, anything else as a download. The url is the app's own
 * erp.file proxy, whose `path` query carries the file name.
 */
export function previews() {
    let dialog = null;
    let returnTo = null;

    const kind = (url) => {
        let name = url;
        try { name = new URL(url, window.location.href).searchParams.get('path') || url; } catch { /* keep url */ }
        const ext = name.split('?')[0].split('.').pop().toLowerCase();
        if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'].includes(ext)) return 'image';
        if (ext === 'pdf') return 'pdf';
        return 'other';
    };

    const close = async () => {
        if (!dialog || dialog.hidden) return;
        if (!reduced) {
            animate(dialog.querySelector('[data-backdrop]'), { opacity: 0 }, { duration: 0.15 });
            await animate(dialog.querySelector('[data-panel]'), { opacity: 0, scale: 0.97 }, { duration: 0.15 }).finished;
        }
        dialog.hidden = true;
        dialog.querySelector('[data-body]').replaceChildren();
        document.body.classList.remove('overflow-hidden');
        returnTo?.focus();
    };

    const build = () => {
        dialog = document.createElement('div');
        dialog.hidden = true;
        dialog.className = 'fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-6';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'preview-title');
        dialog.innerHTML = `
          <div data-backdrop class="absolute inset-0 bg-navy/60 backdrop-blur-sm"></div>
          <div data-panel class="relative w-full max-w-5xl max-h-full flex flex-col rounded-2xl bg-white shadow-2xl shadow-navy/40 overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-3 border-b border-line">
              <h2 id="preview-title" class="flex-1 min-w-0 truncate text-sm font-semibold text-slate-900"></h2>
              <a data-open target="_blank" rel="noopener" class="text-xs font-medium text-brand hover:text-brand-d">Open in new tab ↗</a>
              <button type="button" data-close aria-label="Close preview"
                      class="size-9 -mr-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 flex items-center justify-center">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <div data-body class="flex-1 min-h-[50vh] overflow-auto bg-slate-100 flex items-center justify-center"></div>
          </div>`;
        document.body.appendChild(dialog);

        dialog.querySelector('[data-close]').addEventListener('click', close);
        dialog.querySelector('[data-backdrop]').addEventListener('click', close);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    };

    const open = (url, title, trigger) => {
        if (!dialog) build();
        returnTo = trigger;

        dialog.querySelector('#preview-title').textContent = title || 'Document';
        dialog.querySelector('[data-open]').href = url;
        const body = dialog.querySelector('[data-body]');

        const type = kind(url);
        if (type === 'image') {
            const img = document.createElement('img');
            img.src = url;
            img.alt = title || 'Document';
            img.className = 'max-w-full max-h-[80vh] object-contain';
            body.replaceChildren(img);
        } else if (type === 'pdf') {
            const frame = document.createElement('iframe');
            frame.src = url;
            frame.title = title || 'Document';
            frame.className = 'w-full h-[80vh] bg-white';
            body.replaceChildren(frame);
        } else {
            body.innerHTML = `<div class="text-center p-10">
                <p class="text-sm text-slate-700">This file type cannot be previewed here.</p>
                <a href="${url}" download class="mt-3 inline-block rounded-md bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-d">Download</a>
              </div>`;
        }

        dialog.hidden = false;
        document.body.classList.add('overflow-hidden');
        dialog.querySelector('[data-close]').focus();

        if (!reduced) {
            animate(dialog.querySelector('[data-backdrop]'), { opacity: [0, 1] }, { duration: 0.2 });
            animate(dialog.querySelector('[data-panel]'), { opacity: [0, 1], scale: [0.96, 1], y: [8, 0] }, { type: 'spring', stiffness: 380, damping: 32 });
        }
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-preview]');
        if (!trigger || !trigger.dataset.preview) return;
        event.preventDefault();
        open(trigger.dataset.preview, trigger.dataset.previewTitle, trigger);
    });
}

/*
 * "+ New type" buttons: [data-type-new="key"] with data-url opens a small inline field
 * (after the matching [data-type-anchor="key"]) that creates the type in its ERP HPY
 * master — Crew Certificate Type, COC Type — and adds it to every
 * select[data-type-select="key"] on the page, selected in the one that is still empty.
 */
export function certificateTypes() {
    const token = document.querySelector('input[name="_token"]')?.value;

    document.querySelectorAll('[data-type-new]').forEach((button) => {
        const key = button.dataset.typeNew;
        const noun = button.dataset.typeLabel || 'type';
        const anchor = document.querySelector(`[data-type-anchor="${key}"]`) ?? button.parentElement;
        let box = null;

        button.addEventListener('click', () => {
            if (box) { box.querySelector('input').focus(); return; }

            const id = `new-type-${key}`;
            box = document.createElement('div');
            box.className = 'flex flex-wrap items-center gap-2 rounded-lg border border-line bg-slate-50 p-3 my-3';
            box.innerHTML = `
              <label for="${id}" class="text-xs text-muted">New ${noun}</label>
              <input id="${id}" type="text" maxlength="140"
                     class="flex-1 min-w-48 bg-white border border-line rounded-md py-1.5 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
              <button type="button" data-save class="bg-brand hover:bg-brand-d text-white text-xs font-medium rounded-md px-3 py-2">Add</button>
              <button type="button" data-cancel class="text-xs text-muted hover:text-slate-900 px-2 py-2">Cancel</button>
              <p data-msg class="basis-full text-[11px]" role="status"></p>`;
            anchor.after(box);

            const input = box.querySelector('input');
            const msg = box.querySelector('[data-msg]');
            const done = () => { box.remove(); box = null; button.focus(); };

            const save = async () => {
                const name = input.value.trim();
                if (!name) { input.focus(); return; }

                msg.className = 'basis-full text-[11px] text-muted';
                msg.textContent = 'Saving to ERP HPY…';

                try {
                    const res = await fetch(button.dataset.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ name }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(data.message || `Failed (${res.status})`);

                    const selects = [...document.querySelectorAll(`select[data-type-select="${key}"]`)];
                    selects.forEach((select) => {
                        if (![...select.options].some((o) => o.value === data.name)) select.add(new Option(data.name, data.name));
                    });
                    const empty = selects.find((select) => !select.value);
                    if (empty) empty.value = data.name;
                    else if (selects.length === 1) selects[0].value = data.name;

                    msg.className = 'basis-full text-[11px] text-emerald-700';
                    msg.textContent = `“${data.name}” added.`;
                    input.value = '';
                    setTimeout(() => box && done(), 1400);
                } catch (error) {
                    msg.className = 'basis-full text-[11px] text-rose-700';
                    msg.textContent = error.message;
                }
            };

            box.querySelector('[data-save]').addEventListener('click', save);
            box.querySelector('[data-cancel]').addEventListener('click', done);
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); save(); }
                if (e.key === 'Escape') { e.preventDefault(); done(); }
            });
            input.focus();
        });
    });
}

/** Y-m-d plus N calendar months, never spilling into the next month (31 Jan + 1 = 28/29 Feb). */
export function addMonthsNoOverflow(ymd, months) {
    const [y, m, d] = ymd.split('-').map(Number);
    const target = new Date(Date.UTC(y, m - 1 + months, 1));
    const lastDay = new Date(Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0)).getUTCDate();
    target.setUTCDate(Math.min(d, lastDay));
    return target.toISOString().slice(0, 10);
}

/*
 * Contract length → planned sign off. Inside any [data-contract]: the start is the
 * sign on date ([data-contract-start]) or, before boarding, the planned sign on
 * ([data-contract-planned]); typing the months or changing the start fills
 * [data-contract-end]. The end stays editable — a hand-typed date is kept until the
 * months or start change again. The server applies the same rule when JS is off.
 */
export function contracts() {
    const format = (ymd) => new Date(`${ymd}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

    document.querySelectorAll('[data-contract]').forEach((box) => {
        const start = box.querySelector('[data-contract-start]');
        const planned = box.querySelector('[data-contract-planned]');
        const months = box.querySelector('[data-contract-months]');
        const end = box.querySelector('[data-contract-end]');
        const hint = box.querySelector('[data-contract-hint]');
        if (!months || !end) return;

        const recalc = (overwrite) => {
            const from = start?.value || planned?.value;
            const n = parseInt(months.value, 10);
            if (!from || !(n > 0)) {
                if (hint) hint.textContent = '';
                return;
            }
            const value = addMonthsNoOverflow(from, n);
            if (overwrite || !end.value) end.value = value;
            if (hint) {
                hint.textContent = end.value === value
                    ? `Otomatis: ${format(from)} + ${n} bulan`
                    : `Diisi manual (otomatis: ${format(value)})`;
            }
        };

        [start, planned, months].filter(Boolean).forEach((field) => {
            field.addEventListener('input', () => recalc(true));
            field.addEventListener('change', () => recalc(true));
        });
        end.addEventListener('input', () => recalc(false));
        recalc(false);
    });
}

/*
 * Pickers that fill the rest of a form: choosing an option of a select[data-fill]
 * copies its data-name into crew_name (unless someone typed a name), its data-rank
 * into an empty rank, and a candidate's data-employee into the Employee select.
 */
export function pickers() {
    document.querySelectorAll('select[data-fill]').forEach((select) => {
        const form = select.form;
        const name = form?.querySelector('[name="crew_name"]');
        const rank = form?.querySelector('select[name="rank"]');
        const employee = form?.querySelector('select[name="employee_id"]');
        if (!form || !name) return;

        name.addEventListener('input', () => { delete name.dataset.auto; });

        select.addEventListener('change', () => {
            const option = select.selectedOptions[0];
            if (!option?.value) return;

            if (option.dataset.name && (!name.value || name.dataset.auto)) {
                name.value = option.dataset.name;
                name.dataset.auto = '1';
            }
            if (rank && !rank.value && option.dataset.rank && [...rank.options].some((o) => o.value === option.dataset.rank)) {
                rank.value = option.dataset.rank;
            }
            if (employee && select !== employee && option.dataset.employee && [...employee.options].some((o) => o.value === option.dataset.employee)) {
                employee.value = option.dataset.employee;
            }
        });
    });
}
