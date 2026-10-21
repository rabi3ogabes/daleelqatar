import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import { beacon, postForm, postJson } from './bootstrap';
import { drawLogo, monogram, rasterizeSvg, STYLE_NAMES, toBlob } from './logo-designer';

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

// Prevent double submissions (double clicks, Enter pressed twice) and show a busy state.
// Buttons without a type attribute submit too, so they are covered as well.
const submitButtons = (form) => [
    ...form.querySelectorAll('button:not([type]), button[type="submit"], input[type="submit"]'),
    ...(form.id ? document.querySelectorAll(`button[form="${form.id}"]:not([type="button"])`) : []),
].filter((btn) => !btn.hasAttribute('data-no-busy'));

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || e.defaultPrevented) return;
    if (form.dataset.submitting) {
        e.preventDefault();
        return;
    }
    form.dataset.submitting = '1';
    submitButtons(form).forEach((btn) => {
        btn.setAttribute('aria-busy', 'true');
        btn.disabled = true;
    });
});

// Coming back with the browser's Back button restores the page from cache: re-enable its forms.
window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
        delete form.dataset.submitting;
        submitButtons(form).forEach((btn) => {
            btn.removeAttribute('aria-busy');
            btn.disabled = false;
        });
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

// Profile views are counted with a beacon so CDN-cached pages are counted too (crawlers do not run it).
const viewBeacon = document.querySelector('[data-view-beacon]');
if (viewBeacon) beacon(viewBeacon.dataset.viewBeacon);

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

/* Hidden contact details: fetched only when the visitor presses "Press to view" */
Alpine.data('reveal', (url) => ({
    items: null,
    shown: false,
    loading: false,
    async load() {
        if (this.items || this.loading) return this.items;
        this.loading = true;
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (res.status === 429) {
                Alpine.store('toast').show(document.documentElement.dataset.revealLimit || 'Too many requests', 'error');
                return null;
            }
            if (res.ok) this.items = (await res.json()).items || [];
        } finally {
            this.loading = false;
        }
        return this.items;
    },
    async toggle() {
        if (this.shown) {
            this.shown = false;
            return;
        }
        await this.load();
        this.shown = !!this.items;
    },
    async copy(value) {
        await navigator.clipboard?.writeText(value);
        Alpine.store('toast').show(document.documentElement.dataset.copied || 'Copied');
    },
}));

/* Share menu: WhatsApp, Facebook, Messenger, X, Instagram, Telegram, LinkedIn, e-mail */
Alpine.data('shareMenu', (cfg) => ({
    open: false,
    copiedNow: false,
    canNative: typeof navigator !== 'undefined' && !!navigator.share,
    get isMobile() {
        return /android|iphone|ipad|ipod/i.test(navigator.userAgent);
    },
    toggle() {
        this.open = !this.open;
    },
    sent() {
        if (cfg.trackUrl) beacon(cfg.trackUrl, { type: 'share' });
        this.open = false;
    },
    async copy(message = cfg.copied) {
        try {
            await navigator.clipboard.writeText(cfg.url);
        } catch (_) {
            const ta = Object.assign(document.createElement('textarea'), { value: cfg.url });
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
        }
        this.copiedNow = true;
        setTimeout(() => (this.copiedNow = false), 2500);
        Alpine.store('toast').show(message);
    },
    async native() {
        try {
            await navigator.share({ title: cfg.title, text: cfg.text, url: cfg.url });
            this.sent();
        } catch (_) {
            /* cancelled */
        }
    },
    messenger() {
        const link = encodeURIComponent(cfg.url);
        if (this.isMobile) {
            window.location.href = `fb-messenger://share/?link=${link}`;
        } else if (cfg.fbAppId) {
            window.open(`https://www.facebook.com/dialog/send?app_id=${encodeURIComponent(cfg.fbAppId)}&link=${link}&redirect_uri=${link}`, '_blank', 'noopener,width=640,height=560');
        } else if (this.canNative) {
            return this.native();
        } else {
            return this.copy(cfg.messenger).then(() => this.sent());
        }
        this.sent();
    },
    async instagram() {
        // Instagram has no web share link: use the phone's share sheet, otherwise copy the link.
        if (this.canNative) return this.native();
        await this.copy(cfg.instagram);
        this.sent();
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

/* Admin: spreadsheet upload (direct to storage when available) ------------- */
Alpine.data('importUploader', (signUrl, direct, maxMb, messages) => ({
    file: null,
    source: '',
    over: false,
    busy: false,
    progress: 0,
    error: '',
    accept(file) {
        this.error = '';
        if (!file) return false;
        if (!/\.(xlsx|csv|tsv|txt)$/i.test(file.name)) {
            this.error = messages.type;
            return false;
        }
        if (file.size > maxMb * 1048576) {
            this.error = messages.too_big;
            return false;
        }
        this.file = file;
        if (!this.source) this.source = file.name.replace(/\.[^.]+$/, '').replace(/^[0-9a-f]{8}-/i, '').replace(/[_-]+/g, ' ').trim();
        return true;
    },
    pick(e) {
        this.accept(e.target.files?.[0]);
    },
    drop(e) {
        this.over = false;
        const file = e.dataTransfer?.files?.[0];
        if (this.accept(file)) {
            const dt = new DataTransfer();
            dt.items.add(file);
            this.$refs.input.files = dt.files;
        }
    },
    async submit(e) {
        if (!this.file) {
            e.preventDefault();
            return;
        }
        if (!direct) {
            this.busy = true;
            this.progress = 100;
            return; // regular multipart form submit
        }
        e.preventDefault();
        this.busy = true;
        this.progress = 0;
        try {
            const sign = await postJson(signUrl, { name: this.file.name, size: this.file.size, source_name: this.source });
            await new Promise((resolve, reject) => {
                const body = new FormData();
                body.append('cacheControl', '3600');
                body.append('', this.file);
                const xhr = new XMLHttpRequest();
                xhr.open('PUT', sign.upload_url);
                xhr.upload.onprogress = (ev) => ev.lengthComputable && (this.progress = Math.round((ev.loaded / ev.total) * 100));
                xhr.onload = () => (xhr.status >= 200 && xhr.status < 300 ? resolve() : reject(new Error(messages.failed)));
                xhr.onerror = () => reject(new Error(messages.failed));
                xhr.send(body);
            });
            const done = await postJson(sign.complete_url);
            window.location.href = done.redirect;
        } catch (err) {
            this.error = err.data?.message || err.message || messages.failed;
            if (err.data?.redirect) window.location.href = err.data.redirect;
            this.busy = false;
        }
    },
}));

/* Admin: column mapping preview + chunked import runner ---------------------- */
Alpine.data('importRunner', (processUrl, initial, config = {}) => ({
    p: initial,
    busy: false,
    error: '',
    mapping: Object.fromEntries(Object.keys(config.labels || {}).map((k) => [k, config.mapping?.[k] !== undefined ? String(config.mapping[k]) : ''])),
    samples: config.samples || [],
    get previewFields() {
        return Object.entries(config.labels || {})
            .filter(([key]) => this.mapping[key] !== '' && this.mapping[key] !== undefined)
            .map(([key, label]) => ({ key, label }));
    },
    cell(row, key) {
        const i = Number(this.mapping[key]);
        return Number.isInteger(i) ? (row[i] ?? '') : '';
    },
    async start(form) {
        this.busy = true;
        this.error = '';
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || res.statusText);
            window.location.href = data.redirect;
        } catch (err) {
            this.error = err.message;
            this.busy = false;
        }
    },
    async run() {
        let failures = 0;
        while (!this.p.done) {
            try {
                this.p = await postJson(processUrl);
                failures = 0;
                this.error = '';
            } catch (err) {
                failures += 1;
                this.error = err.message;
                if (failures >= 6) return;
                await new Promise((r) => setTimeout(r, 2500 * failures));
            }
        }
        window.location.href = this.p.redirect;
    },
}));

window.Alpine = Alpine;
/* Google rating & reviews: fetched when the section scrolls into view ------- */
Alpine.data('googleReviews', (url) => ({
    data: null,
    loading: true,
    failed: false,
    init() {
        const load = () => fetch(url, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : Promise.reject(r)))
            .then((d) => (this.data = d))
            .catch(() => (this.failed = true))
            .finally(() => (this.loading = false));
        if (!('IntersectionObserver' in window)) return load();
        const io = new IntersectionObserver((entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                io.disconnect();
                load();
            }
        }, { rootMargin: '300px' });
        io.observe(this.$el);
    },
    countLabel(n) {
        const label = document.documentElement.lang === 'ar' ? 'مراجعة' : n === 1 ? 'review' : 'reviews';
        return `${Number(n).toLocaleString()} ${label}`;
    },
}));

/* Smart enrichment: bulk runner (one server step at a time, safe to stop) ---- */
Alpine.data('enrichmentRunner', (cfg) => ({
    cfg,
    mode: cfg.ai ? 'names' : 'full',
    scope: 'not_enriched',
    google: cfg.google,
    website: true,
    ai: cfg.ai,
    busy: false,
    finished: false,
    stopping: false,
    processed: 0,
    suggested: 0,
    errors: 0,
    remaining: 0,
    error: '',
    get percent() {
        const total = this.processed + this.remaining;
        return total ? Math.min(100, Math.round((this.processed / total) * 100)) : 0;
    },
    async start() {
        Object.assign(this, { busy: true, finished: false, stopping: false, processed: 0, suggested: 0, errors: 0, error: '' });
        this.remaining = this.cfg.counts[this.mode === 'names' ? 'missing_arabic' : this.scope] || 0;
        let cursor = 0;
        let failures = 0;
        while (!this.stopping) {
            try {
                const r = await postJson(this.cfg.url, { scope: this.scope, mode: this.mode, cursor, google: this.google, website: this.website, ai: this.ai });
                failures = 0;
                this.error = '';
                cursor = r.cursor;
                this.processed += r.processed;
                this.suggested += r.suggested;
                this.errors += r.errors;
                this.remaining = r.remaining;
                if (r.done || r.processed === 0) break;
            } catch (err) {
                this.error = err.message;
                if (err.status === 422 || ++failures >= 4) break;
                await new Promise((resolve) => setTimeout(resolve, 2000 * failures));
            }
        }
        this.busy = false;
        this.finished = true;
    },
    stop() {
        this.stopping = true;
    },
}));

/* Admin logo studio: find the real logo on the company website or design a branded one. */
Alpine.data('logoStudio', (cfg) => ({
    cfg,
    open: false,
    tab: 'website',
    searching: false,
    searched: false,
    sites: [],
    candidates: [],
    message: '',
    error: '',
    selected: null,
    styles: STYLE_NAMES,
    previews: {},
    initials: '',
    showArabic: true,
    saving: false,
    renderTimer: null,
    init() {
        this.initials = monogram(this.primaryName());
        this.$watch('initials', () => this.scheduleRender());
        this.$watch('showArabic', () => this.scheduleRender());
    },
    primaryName() {
        return this.cfg.nameEn || this.cfg.nameAr || '';
    },
    secondaryName() {
        return this.cfg.nameEn && this.cfg.nameAr && this.showArabic ? this.cfg.nameAr : '';
    },
    show(tab) {
        this.open = true;
        this.switchTab(tab);
    },
    close() {
        if (!this.saving) this.open = false;
    },
    switchTab(tab) {
        this.tab = tab;
        this.selected = null;
        if (tab === 'website' && !this.searched && !this.searching) this.search();
        if (tab === 'design') this.$nextTick(() => this.renderAll());
    },
    async search() {
        Object.assign(this, { searching: true, error: '', message: '', candidates: [], selected: null });
        try {
            const r = await postJson(this.cfg.findUrl);
            this.sites = r.sites || [];
            this.candidates = r.candidates || [];
            this.message = r.message || '';
            if (this.candidates.length === 1) this.selected = { type: 'candidate', id: this.candidates[0].id };
        } catch (err) {
            this.error = err.message || this.cfg.i18n.failed;
        } finally {
            this.searching = false;
            this.searched = true;
        }
    },
    scheduleRender() {
        clearTimeout(this.renderTimer);
        this.renderTimer = setTimeout(() => this.renderAll(), 120);
    },
    async renderAll() {
        if (this.tab !== 'design') return;
        const opts = { name: this.primaryName(), secondary: this.secondaryName(), initials: this.initials.trim() };
        for (const style of this.styles) {
            const canvas = document.createElement('canvas');
            await drawLogo(canvas, { ...opts, style });
            this.previews[style] = canvas.toDataURL('image/png');
        }
        if (!this.selected) this.selected = { type: 'style', id: 'navy' };
    },
    isSelected(type, id) {
        return this.selected?.type === type && this.selected?.id === id;
    },
    async save() {
        if (!this.selected || this.saving) return;
        this.saving = true;
        this.error = '';
        try {
            let r;
            if (this.selected.type === 'candidate') {
                const c = this.candidates.find((x) => x.id === this.selected.id);
                if (c.kind === 'raster') {
                    r = await postJson(this.cfg.applyUrl, { candidate: c.id, origin: 'website' });
                } else {
                    const fd = new FormData();
                    fd.append('logo', await rasterizeSvg(c.preview), 'logo.png');
                    fd.append('origin', 'website');
                    fd.append('source_url', c.url || '');
                    r = await postForm(this.cfg.applyUrl, fd);
                }
            } else {
                const canvas = document.createElement('canvas');
                await drawLogo(canvas, { style: this.selected.id, name: this.primaryName(), secondary: this.secondaryName(), initials: this.initials.trim() });
                const fd = new FormData();
                fd.append('logo', await toBlob(canvas), 'logo.png');
                fd.append('origin', 'generated');
                fd.append('style', this.selected.id);
                r = await postForm(this.cfg.applyUrl, fd);
            }
            window.location.assign(r.redirect);
        } catch (err) {
            this.error = err.message || this.cfg.i18n.failed;
            this.saving = false;
        }
    },
}));

Alpine.start();
