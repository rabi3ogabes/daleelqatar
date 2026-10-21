import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import { beacon, postJson } from './bootstrap';

Alpine.plugin(collapse);
Alpine.plugin(focus);

/* Global toast notifications ------------------------------------------------ */
Alpine.store('toast', {
    items: [],
    show(message, tone = 'success') {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, tone });
        setTimeout(() => this.dismiss(id), 4500);
    },
    dismiss(id) {
        this.items = this.items.filter((t) => t.id !== id);
    },
});

/* Confirmation dialog for destructive actions ------------------------------ */
Alpine.store('confirm', {
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    tone: 'danger',
    form: null,
    ask(form, { title, message, confirmLabel, tone = 'danger' }) {
        Object.assign(this, { form, title, message, confirmLabel, tone, open: true });
    },
    accept() {
        const form = this.form;
        this.open = false;
        this.form = null;
        if (form) {
            form.dataset.confirmed = '1';
            form.requestSubmit ? form.requestSubmit() : form.submit();
        }
    },
});

// <form data-confirm="message" data-confirm-title="…" data-confirm-label="…">
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) return;
    e.preventDefault();
    Alpine.store('confirm').ask(form, {
        title: form.dataset.confirmTitle || document.documentElement.dataset.confirmTitle,
        message: form.dataset.confirm,
        confirmLabel: form.dataset.confirmLabel || document.documentElement.dataset.confirmLabel,
        tone: form.dataset.confirmTone || 'danger',
    });
});

// Prevent double submissions and show a busy state on submit buttons.
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || e.defaultPrevented) return;
    form.querySelectorAll('button[type="submit"]:not([data-no-busy])').forEach((btn) => {
        btn.setAttribute('aria-busy', 'true');
        btn.disabled = true;
    });
});

/* Search with live suggestions ---------------------------------------------- */
Alpine.data('searchBox', (suggestUrl, initial = '') => ({
    q: initial,
    open: false,
    loading: false,
    results: { businesses: [], categories: [] },
    active: -1,
    timer: null,
    get flat() {
        return [...this.results.categories, ...this.results.businesses];
    },
    onInput() {
        clearTimeout(this.timer);
        this.active = -1;
        if (this.q.trim().length < 2) {
            this.open = false;
            return;
        }
        this.timer = setTimeout(() => this.fetch(), 220);
    },
    async fetch() {
        this.loading = true;
        try {
            const res = await fetch(`${suggestUrl}?q=${encodeURIComponent(this.q.trim())}`, { headers: { Accept: 'application/json' } });
            if (res.ok) {
                this.results = await res.json();
                this.open = this.flat.length > 0;
            }
        } finally {
            this.loading = false;
        }
    },
    move(step) {
        if (!this.open) return;
        const n = this.flat.length;
        this.active = (this.active + step + n) % n;
    },
    choose(e) {
        if (this.open && this.active >= 0 && this.flat[this.active]) {
            e.preventDefault();
            window.location.href = this.flat[this.active].url;
        }
    },
}));

/* Favorites (heart) toggle --------------------------------------------------- */
Alpine.data('favorite', (url, initial, loginUrl) => ({
    on: initial,
    busy: false,
    async toggle() {
        if (!url) {
            window.location.href = loginUrl;
            return;
        }
        if (this.busy) return;
        this.busy = true;
        this.on = !this.on;
        try {
            const data = await postJson(url);
            this.on = data.favorited;
            Alpine.store('toast').show(data.message);
        } catch (err) {
            this.on = !this.on;
            if (err.status === 401 || err.status === 403) window.location.href = loginUrl;
        } finally {
            this.busy = false;
        }
    },
}));

/* Contact interaction tracking (call, WhatsApp, website, directions, share) */
window.dqTrack = (url, type) => beacon(url, { type });

/* Share profile ------------------------------------------------------------- */
Alpine.data('share', (url, title, copiedMessage, trackUrl) => ({
    async share() {
        if (trackUrl) beacon(trackUrl, { type: 'share' });
        if (navigator.share) {
            try {
                await navigator.share({ title, url });
                return;
            } catch (_) {
                /* cancelled */
            }
        }
        await navigator.clipboard?.writeText(url);
        Alpine.store('toast').show(copiedMessage);
    },
}));

/* Image preview for file inputs ---------------------------------------------- */
Alpine.data('imagePicker', (existing = null) => ({
    preview: existing,
    name: '',
    pick(e) {
        const file = e.target.files?.[0];
        if (!file) return;
        this.name = file.name;
        const reader = new FileReader();
        reader.onload = (ev) => (this.preview = ev.target.result);
        reader.readAsDataURL(file);
    },
}));

/* Opening-hours editor -------------------------------------------------------- */
Alpine.data('hoursEditor', (initial) => ({
    days: initial,
    copyToAll(from) {
        const src = JSON.parse(JSON.stringify(this.days[from]));
        this.days = this.days.map((d) => ({ ...JSON.parse(JSON.stringify(src)), day: d.day, label: d.label }));
    },
    addPeriod(i) {
        if (this.days[i].periods.length < 2) this.days[i].periods.push({ opens: '16:00', closes: '22:00' });
    },
    removePeriod(i, p) {
        this.days[i].periods.splice(p, 1);
        if (!this.days[i].periods.length) this.days[i].periods.push({ opens: '09:00', closes: '17:00' });
    },
}));

/* Repeatable rows (services) --------------------------------------------------- */
Alpine.data('repeater', (rows, blank, max = 30) => ({
    rows: rows.length ? rows : [{ ...blank }],
    add() {
        if (this.rows.length < max) this.rows.push({ ...blank });
    },
    remove(i) {
        this.rows.splice(i, 1);
        if (!this.rows.length) this.rows.push({ ...blank });
    },
}));

/* Dependent municipality → area select ---------------------------------------- */
Alpine.data('locationPicker', (areas, municipality = '', area = '') => ({
    areas,
    municipality: String(municipality ?? ''),
    area: String(area ?? ''),
    get options() {
        return this.areas.filter((a) => String(a.m) === this.municipality);
    },
    changed() {
        if (!this.options.some((a) => String(a.id) === this.area)) this.area = '';
    },
}));

/* Tabs that remember the active tab in the URL hash ---------------------------- */
Alpine.data('tabs', (first) => ({
    tab: (location.hash || '').replace('#', '') || first,
    set(t) {
        this.tab = t;
        history.replaceState(null, '', '#' + t);
    },
}));

window.Alpine = Alpine;
Alpine.start();
