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
    submitter: null,
    ask(form, { title, message, confirmLabel, tone = 'danger' }, submitter = null) {
        Object.assign(this, { form, submitter, title, message, confirmLabel, tone, open: true, onAccept: null });
    },
    onAccept: null,
    accept() {
        const { form, submitter, onAccept } = this;
        this.open = false;
        this.form = null;
        this.submitter = null;
        this.onAccept = null;
        if (onAccept) onAccept();
        if (form) {
            form.dataset.confirmed = '1';
            // Re-submit with the button that was pressed, so its name/value (e.g. action=apply) is sent.
            if (form.requestSubmit) form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
            else form.submit();
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
    }, e.submitter);
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

/* Visitor analytics: first-party and cookie-free (an anonymous random token in this browser), plus the
   public view counter of the page. Engaged time only counts while the tab is visible. */
(() => {
    const html = document.documentElement;
    const key = html.dataset.page;
    if (!html.dataset.track || navigator.webdriver || document.visibilityState === 'prerender') return;
    const random = () => Array.from(crypto.getRandomValues(new Uint8Array(16)), (b) => (b % 36).toString(36)).join('');
    const stored = (storage, name) => {
        try {
            const value = storage.getItem(name);
            if (value) return [value, false];
            const fresh = random();
            storage.setItem(name, fresh);
            return [fresh, true];
        } catch {
            return [null, false];
        }
    };
    const [visitor, isNew] = stored(window.localStorage, 'dq_vid');
    const [session] = stored(window.sessionStorage, 'dq_sid');
    const params = new URLSearchParams(window.location.search);
    const send = (url, body, beaconOnly = false) => {
        const json = JSON.stringify(body);
        if (beaconOnly && navigator.sendBeacon) return navigator.sendBeacon(url, new Blob([json], { type: 'application/json' }));
        return fetch(url, { method: 'POST', keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: json })
            .then((r) => (r.ok ? r.json() : null))
            .catch(() => null);
    };
    const showCount = (views) => {
        const label = new Intl.NumberFormat(html.lang === 'ar' ? 'ar-QA-u-nu-latn' : 'en-US', { notation: views >= 100000 ? 'compact' : 'standard' }).format(views);
        let targets = document.querySelectorAll('[data-page-views]');
        if (!targets.length) targets = document.querySelectorAll('[data-page-views-fallback]');
        targets.forEach((el) => {
            el.querySelector('[data-page-views-count]').textContent = label;
            el.hidden = false;
        });
    };

    // Listings shown on this page ("search appearances" in the owner's insights), reported once each list is seen.
    const lists = document.querySelectorAll('[data-impressions]');
    if (lists.length && html.dataset.impressionsUrl && 'IntersectionObserver' in window) {
        const seen = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                seen.unobserve(entry.target);
                if (entry.target.dataset.impressions) send(html.dataset.impressionsUrl, { l: entry.target.dataset.impressions }, true);
            });
        });
        lists.forEach((el) => seen.observe(el));
    }

    send(html.dataset.track, {
        k: key || null,
        g: html.dataset.pageSig || null,
        p: window.location.pathname,
        t: document.title,
        r: document.referrer || null,
        u: params.get('utm_source'),
        v: visitor,
        s: session,
        n: isNew,
    }).then((data) => {
        if (!data) return;
        if (data.show && data.views) showCount(data.views);
        if (!data.id) return;

        let engaged = 0;
        let lastPing = 0;
        const ping = (beacon = false) => {
            if (engaged === lastPing) return;
            lastPing = engaged;
            send(html.dataset.ping, { id: data.id, token: data.token, e: engaged }, beacon);
        };
        const started = Date.now();
        const timer = setInterval(() => {
            if (document.visibilityState !== 'visible') return;
            engaged++;
            // At 15 s, then every minute, for up to 30 minutes on one page.
            if (engaged === 15 || (engaged > 15 && engaged % 60 === 0)) ping();
            if (Date.now() - started > 30 * 60 * 1000) clearInterval(timer);
        }, 1000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') ping(true);
        });
        window.addEventListener('pagehide', () => ping(true));
    });
})();

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
    load(rows) {
        this.days = JSON.parse(JSON.stringify(rows));
    },
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
                // Google refused (quota, key settings): stop and show why instead of going on without it.
                if (r.stopped) {
                    this.error = r.stopped;
                    break;
                }
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

/* Admin analytics: the real-time card refreshes itself every 15 seconds while the tab is visible. */
Alpine.data('realtimeAnalytics', (cfg) => ({
    data: cfg.initial,
    timer: null,
    init() {
        this.timer = setInterval(() => this.refresh(), 15000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') this.refresh();
        });
    },
    destroy() {
        clearInterval(this.timer);
    },
    async refresh() {
        if (document.visibilityState !== 'visible') return;
        try {
            const res = await fetch(this.cfg.url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (res.ok) this.data = await res.json();
        } catch {
            // Keep the last figures; the next tick tries again.
        }
    },
    cfg,
}));

/* Business page: find the company on another directory (QBCD…) and add what it knows to the review. */
Alpine.data('directoryImport', (cfg) => ({
    cfg,
    sourceId: cfg.sources.length ? cfg.sources[0].id : null,
    busy: false,
    phase: '',
    result: null,
    error: '',
    pasted: '',
    get sourceName() {
        const s = this.cfg.sources.find((x) => x.id === Number(this.sourceId));
        return s ? s.name : '';
    },
    async run(url = null) {
        if (this.busy || !this.sourceId) return;
        Object.assign(this, { busy: true, error: '', phase: url ? 'reading' : 'searching' });
        if (!url) this.result = null;
        const slow = setTimeout(() => { this.phase = 'reading'; }, 4000);
        try {
            const r = await postJson(this.cfg.url, { source_id: Number(this.sourceId), url });
            this.result = r;
            if (url) this.pasted = '';
        } catch (err) {
            this.error = err.message;
        } finally {
            clearTimeout(slow);
            this.busy = false;
        }
    },
}));

/* Auto discovery: crawl a source live, run after run, until nothing is left (or the admin stops). */
Alpine.data('discoveryRunner', (cfg) => ({
    cfg,
    open: false,
    busy: false,
    finished: false,
    stopping: false,
    runs: 0,
    read: 0,
    found: 0,
    created: 0,
    merged: 0,
    pending: cfg.pending || 0,
    listing: false,
    error: '',
    async start() {
        Object.assign(this, { busy: true, finished: false, stopping: false, runs: 0, read: 0, found: 0, created: 0, merged: 0, error: '' });
        let failures = 0;
        while (!this.stopping && this.runs < 120) {
            try {
                const r = await postJson(this.cfg.url);
                failures = 0;
                this.runs++;
                this.read += r.run.processed;
                this.found += r.run.discovered;
                this.created += r.run.created;
                this.merged += r.run.merged;
                this.pending = r.pending;
                this.listing = r.listing;
                this.error = r.message || '';
                if (!r.more) break;
            } catch (err) {
                this.error = err.message;
                if (err.status === 422 || ++failures >= 3) break;
                await new Promise((resolve) => setTimeout(resolve, 3000 * failures));
            }
        }
        this.busy = false;
        this.finished = true;
    },
    stop() {
        this.stopping = true;
    },
}));

/* Auto discovery form: work out method, start address and page pattern from the website address. */
Alpine.data('sourceAnalyzer', (cfg) => ({
    cfg,
    busy: false,
    result: null,
    error: '',
    async analyze() {
        const base = document.getElementById('f_base_url');
        if (!base || !base.value || this.busy) return;
        Object.assign(this, { busy: true, result: null, error: '' });
        try {
            this.result = await postJson(this.cfg.url, { url: base.value });
            if (!this.result.ok) this.error = this.result.message;
        } catch (err) {
            this.error = err.message;
        } finally {
            this.busy = false;
        }
    },
    applied: false,
    apply() {
        if (!this.result || !this.result.ok) return;
        const set = (id, value) => {
            const el = document.getElementById(id);
            if (el && value !== null && value !== undefined) {
                el.value = value;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };
        set('f_base_url', this.result.base_url);
        set('f_discovery', this.result.mode);
        set('f_start_urls', this.result.start_urls);
        set('f_url_pattern', this.result.url_pattern);
        const name = document.getElementById('f_name');
        if (name && !name.value) set('f_name', new URL(this.result.base_url).hostname.replace(/^www\./, ''));
        this.applied = true;
    },
}));

/* Smart categories: scan runner, "apply confident" runner and row actions. */
Alpine.data('categoryScanner', (cfg) => ({
    cfg,
    scope: cfg.scope || 'unchecked',
    ai: cfg.ai,
    busy: false,
    finished: false,
    stopping: false,
    processed: 0,
    suggested: 0,
    aiCount: 0,
    remaining: 0,
    error: '',
    get percent() {
        const total = this.processed + this.remaining;
        return total ? Math.min(100, Math.round((this.processed / total) * 100)) : 0;
    },
    async start() {
        Object.assign(this, { busy: true, finished: false, stopping: false, processed: 0, suggested: 0, aiCount: 0, error: '' });
        this.remaining = this.cfg.counts[this.scope] || 0;
        let cursor = 0;
        let failures = 0;
        while (!this.stopping) {
            try {
                const r = await postJson(this.cfg.url, { scope: this.scope, cursor, ai: this.ai });
                failures = 0;
                this.error = '';
                cursor = r.cursor;
                this.processed += r.processed;
                this.suggested += r.suggested;
                this.aiCount += r.ai;
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

Alpine.data('confidentApplier', (cfg) => ({
    cfg,
    busy: false,
    applied: 0,
    error: '',
    get percent() {
        return this.cfg.total ? Math.min(100, Math.round((this.applied / this.cfg.total) * 100)) : 0;
    },
    run() {
        if (this.busy) return;
        Alpine.store('confirm').ask(null, { message: this.cfg.confirm, tone: 'primary' });
        Alpine.store('confirm').onAccept = () => this.go();
    },
    async go() {
        this.busy = true;
        this.error = '';
        let failures = 0;
        for (;;) {
            try {
                const r = await postJson(this.cfg.url, { from: this.cfg.from || null, to: this.cfg.to || null });
                failures = 0;
                this.applied += r.applied;
                if (r.done || r.applied === 0) break;
            } catch (err) {
                this.error = err.message;
                if (err.status === 422 || ++failures >= 3) break;
                await new Promise((resolve) => setTimeout(resolve, 2000 * failures));
            }
        }
        this.busy = false;
        if (!this.error) window.location.reload();
    },
}));

Alpine.data('categoryRow', () => ({
    busy: false,
    gone: false,
    async act(form) {
        if (this.busy) return;
        this.busy = true;
        try {
            const r = await postForm(form.action, new FormData(form));
            this.gone = true;
            Alpine.store('toast').show(r.message);
        } catch (err) {
            Alpine.store('toast').show(err.message, 'error');
            this.busy = false;
        }
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


/* Quote request form: live recipient count, area picker, and a draft kept on this device ---------- */
Alpine.data('quoteForm', (state, areas) => ({
    ...state,
    areas,
    count: null,
    nearby: 0,
    verified: 0,
    loading: false,
    detailsLength: 0,
    timer: null,
    draftKey: 'dq_quote_draft',
    get areaList() {
        return this.areas.filter((a) => String(a.m) === this.municipality);
    },
    init() {
        if (this.restore) this.restoreDraft();
        this.detailsLength = (this.$refs.form.querySelector('[name=details]')?.value || '').length;
        this.refresh();
    },
    areaChanged() {
        if (!this.areaList.some((a) => String(a.id) === this.area)) this.area = '';
    },
    refresh() {
        clearTimeout(this.timer);
        if (!this.category) {
            this.count = null;
            return;
        }
        this.loading = true;
        this.timer = setTimeout(async () => {
            const params = new URLSearchParams({ category_id: this.category, municipality_id: this.municipality || '', area_id: this.area || '' });
            if (this.business) params.set('business', this.business);
            try {
                const r = await fetch(`${this.previewUrl}?${params}`, { headers: { Accept: 'application/json' } });
                const data = r.ok ? await r.json() : { count: null };
                this.count = data.count;
                this.nearby = data.nearby ?? 0;
                this.verified = data.verified ?? 0;
            } catch {
                this.count = null;
            } finally {
                this.loading = false;
            }
        }, 250);
    },
    fields() {
        const out = {};
        new FormData(this.$refs.form).forEach((v, k) => {
            if (!['_token', 'website', 'consent'].includes(k)) out[k] = v;
        });
        return out;
    },
    save() {
        try {
            localStorage.setItem(this.draftKey, JSON.stringify({ at: Date.now(), fields: this.fields() }));
        } catch {}
    },
    restoreDraft() {
        let draft = null;
        try {
            draft = JSON.parse(localStorage.getItem(this.draftKey) || 'null');
        } catch {}
        // Drafts older than a week are dropped.
        if (!draft || Date.now() - draft.at > 7 * 864e5) return;
        const f = draft.fields || {};
        const form = this.$refs.form;
        if (form.querySelector('[name=title]').value || form.querySelector('[name=details]').value) return;
        if (f.category_id && !this.category) this.category = f.category_id;
        if (f.municipality_id && !this.municipality) this.municipality = f.municipality_id;
        if (f.area_id && !this.area) this.$nextTick(() => (this.area = f.area_id));
        if (f.contact_method) this.contact = f.contact_method;
        ['title', 'details', 'contact_phone'].forEach((k) => {
            const el = form.querySelector(`[name=${k}]`);
            if (el && f[k] && !el.value) el.value = f[k];
        });
        if (f.timing) {
            const t = form.querySelector(`[name=timing][value="${f.timing}"]`);
            if (t) t.checked = true;
        }
    },
    submit(e) {
        this.save();
        if (this.guest) {
            e.preventDefault();
            window.location.href = this.loginUrl;
            return;
        }
    },
}));

/** Forgets the quote draft once the request was accepted. */
window.dqForgetQuoteDraft = () => {
    try {
        localStorage.removeItem('dq_quote_draft');
    } catch {}
};

/* Guide editor (admin): live count of qualifying listings, and the editor's pinned picks ---------- */
Alpine.data('guideEditor', (state, areas) => ({
    ...state,
    areas,
    count: 0,
    min: 3,
    items: [],
    loading: false,
    timer: null,
    get areaList() {
        return this.areas.filter((a) => String(a.m) === this.municipality);
    },
    init() {
        this.refresh();
    },
    areaChanged() {
        if (!this.areaList.some((a) => String(a.id) === this.area)) this.area = '';
    },
    isPinned(id) {
        return this.pinned.some((p) => p.id === id);
    },
    pinIndex(id) {
        return this.pinned.findIndex((p) => p.id === id);
    },
    toggle(item) {
        this.pinned = this.isPinned(item.id) ? this.pinned.filter((p) => p.id !== item.id) : [...this.pinned, { id: item.id, name: item.name }];
    },
    refresh() {
        clearTimeout(this.timer);
        this.loading = true;
        this.timer = setTimeout(async () => {
            const params = new URLSearchParams({
                category_id: this.category || '',
                municipality_id: this.municipality || '',
                area_id: this.area || '',
                open_24h: this.open24 ? 1 : 0,
                verified_only: this.verified ? 1 : 0,
            });
            try {
                const r = await fetch(`${this.previewUrl}?${params}`, { headers: { Accept: 'application/json' } });
                const data = r.ok ? await r.json() : null;
                if (data) {
                    this.count = data.count;
                    this.min = data.min;
                    this.items = data.items;
                }
            } finally {
                this.loading = false;
            }
        }, 250);
    },
}));

/* Admin enrichment: review suggested Arabic names in bulk ---------------------------------------
   Select rows (shift-click selects a range), the whole page, or every matching row across all
   pages; edit a name inline; approve or reject. Work runs in short server steps with progress. */
const ARABIC = /[؀-ۿ]/;
Alpine.data('nameReview', (cfg) => ({
    cfg,
    selected: {},
    allMatching: false,
    excluded: {},
    edits: {},
    errors: {},
    state: {},
    doneAction: {},
    busy: false,
    stopping: false,
    action: 'approve',
    progress: { processed: 0, total: 0 },
    summary: '',
    lastIndex: null,
    get openRows() {
        return this.cfg.rows.filter((r) => this.state[r.id] !== 'done');
    },
    get pageAll() {
        const open = this.openRows;
        return open.length > 0 && open.every((r) => this.isSelected(r.id));
    },
    get pageSome() {
        return this.openRows.some((r) => this.isSelected(r.id));
    },
    get count() {
        if (this.allMatching) return Math.max(0, this.cfg.total - Object.keys(this.excluded).length - this.doneCount);
        return Object.keys(this.selected).length;
    },
    get doneCount() {
        return Object.keys(this.state).filter((id) => this.state[id] === 'done').length;
    },
    get percent() {
        return this.progress.total ? Math.min(100, Math.round((this.progress.processed / this.progress.total) * 100)) : 0;
    },
    isSelected(id) {
        if (this.state[id] === 'done') return false;
        return this.allMatching ? !this.excluded[id] : !!this.selected[id];
    },
    setSelected(id, on) {
        if (this.allMatching) {
            if (on) delete this.excluded[id];
            else this.excluded[id] = true;
        } else if (on) this.selected[id] = true;
        else delete this.selected[id];
    },
    toggle(id, index, event) {
        const on = !this.isSelected(id);
        // Shift-click selects (or clears) every row between the last click and this one.
        if (event.shiftKey && this.lastIndex !== null) {
            const [from, to] = [Math.min(this.lastIndex, index), Math.max(this.lastIndex, index)];
            this.cfg.rows.slice(from, to + 1).forEach((r) => this.state[r.id] !== 'done' && this.setSelected(r.id, on));
        } else {
            this.setSelected(id, on);
        }
        this.lastIndex = index;
        this.summary = '';
    },
    togglePage(on) {
        this.openRows.forEach((r) => this.setSelected(r.id, on));
        if (!on && this.allMatching) this.clear();
        this.summary = '';
    },
    selectAllMatching() {
        this.allMatching = true;
        this.excluded = {};
        this.selected = {};
    },
    clear() {
        this.allMatching = false;
        this.selected = {};
        this.excluded = {};
        this.lastIndex = null;
    },
    value(id) {
        return this.edits[id] !== undefined ? this.edits[id] : this.cfg.rows.find((r) => r.id === id)?.suggested || '';
    },
    edit(id, value) {
        const row = this.cfg.rows.find((r) => r.id === id);
        if (row && value.trim() === row.suggested.trim()) delete this.edits[id];
        else this.edits[id] = value;
        delete this.errors[id];
        // Editing a name usually means it should be approved: select the row.
        if (!this.isSelected(id)) this.setSelected(id, true);
    },
    resetEdit(id) {
        delete this.edits[id];
        delete this.errors[id];
    },
    rowClass(id) {
        if (this.state[id] === 'done') return 'opacity-50';
        if (this.errors[id]) return 'bg-danger-soft/40';
        return this.isSelected(id) ? 'bg-gold-50/60' : '';
    },
    inputClass(id) {
        if (this.errors[id]) return 'input-invalid';
        return this.edits[id] !== undefined ? 'ring-2 ring-gold-300 border-gold-400' : '';
    },
    /** Edited names must still contain Arabic letters; returns false (and marks the rows) otherwise. */
    validEdits(ids) {
        let ok = true;
        ids.forEach((id) => {
            const v = this.edits[id];
            if (v !== undefined && !ARABIC.test(v)) {
                this.errors[id] = this.cfg.i18n.notArabic;
                ok = false;
            }
        });
        return ok;
    },
    editsFor(ids) {
        const out = {};
        Object.keys(this.edits).forEach((id) => {
            if (!ids || ids.includes(Number(id))) out[id] = this.edits[id];
        });
        return out;
    },
    single(action, id) {
        if (action === 'approve' && !this.validEdits([id])) return;
        this.run(action, { mode: 'ids', ids: [id] }, 1);
    },
    bulk(action) {
        const ids = this.allMatching ? null : Object.keys(this.selected).map(Number);
        const pageIds = this.cfg.rows.map((r) => r.id).filter((id) => this.isSelected(id));
        if (action === 'approve' && !this.validEdits(pageIds)) return;
        const total = this.count;
        if (!total) return;
        const go = () => this.run(action, ids ? { mode: 'ids', ids } : { mode: 'all', except: Object.keys(this.excluded).map(Number) }, total);
        // Large or "all matching" actions ask first; a few rows on this page go straight through.
        if (this.allMatching || total > 25) {
            const store = Alpine.store('confirm');
            store.ask(null, {
                title: this.cfg.i18n.confirmTitle,
                message: (action === 'approve' ? this.cfg.i18n.approveAll : this.cfg.i18n.rejectAll).replace(':count', total.toLocaleString()),
                confirmLabel: action === 'approve' ? this.cfg.i18n.approveLabel : this.cfg.i18n.rejectLabel,
                tone: action === 'approve' ? 'neutral' : 'danger',
            });
            store.onAccept = go;
        } else {
            go();
        }
    },
    async run(action, target, total) {
        Object.assign(this, { busy: true, stopping: false, action, summary: '', progress: { processed: 0, total } });
        const ids = target.mode === 'ids' ? target.ids : null;
        let cursor = 0;
        let done = 0;
        let failures = 0;
        const failed = [];
        while (!this.stopping) {
            try {
                const r = await postJson(this.cfg.url, {
                    action,
                    ...target,
                    edits: action === 'approve' ? this.editsFor(ids) : {},
                    cursor,
                    q: this.cfg.filters.q,
                    source: this.cfg.filters.source,
                });
                failures = 0;
                cursor = r.cursor;
                done += r.done;
                this.progress.processed += r.processed;
                if (this.progress.processed > this.progress.total) this.progress.total = this.progress.processed;
                r.failed.forEach((f) => {
                    failed.push(f);
                    this.errors[f.id] = f.error;
                });
                // Rows on this page that went through are marked done.
                this.cfg.rows.forEach((row) => {
                    if (row.id <= cursor && !this.errors[row.id] && (ids ? ids.includes(row.id) : !this.excluded[row.id])) {
                        this.state[row.id] = 'done';
                        this.doneAction[row.id] = action;
                        delete this.selected[row.id];
                    }
                });
                if (r.finished || r.processed === 0) break;
            } catch (err) {
                if (err.status === 422 || ++failures >= 4) {
                    failed.push({ id: 0, error: err.message || this.cfg.i18n.error });
                    break;
                }
                await new Promise((resolve) => setTimeout(resolve, 1500 * failures));
            }
        }
        this.busy = false;
        if (this.allMatching) this.clear();
        const message = (action === 'approve' ? this.cfg.i18n.doneApprove : this.cfg.i18n.doneReject).replace(':count', done.toLocaleString());
        const fatal = failed.find((f) => !f.id);
        const rowFailures = failed.filter((f) => f.id).length;
        if (fatal) this.summary = done ? `${message} · ${fatal.error}` : fatal.error;
        else if (total > 1 || rowFailures) this.summary = rowFailures ? `${message} · ${this.cfg.i18n.failed.replace(':count', rowFailures)}` : message;
        else Alpine.store('toast').show(message);
        // Whole page handled: load the next names.
        if (!failed.length && this.openRows.length === 0) setTimeout(() => window.location.reload(), 900);
    },
}));

/* Google Maps finder ----------------------------------------------------------- */
Alpine.data('placeFinder', (cfg) => {
    // Leaflet objects stay outside Alpine's reactive proxies.
    let map = null;
    const BULK_CHUNK = 4;

    return {
        cfg,
        keyword: '',
        mode: 'area',
        municipalityId: '',
        areaId: '',
        lat: null,
        lng: null,
        radius: cfg.radius,
        searching: false,
        searched: false,
        error: '',
        query: '',
        rows: [],
        next: null,
        loadingMore: false,
        filter: 'all',
        hideClosed: true,
        categoryFor: {},
        editingCategory: null,
        rowState: {},
        selected: {},
        publish: true,
        busy: false,
        stopping: false,
        progress: { done: 0, total: 0 },
        summary: '',
        highlight: null,
        lastParams: null,
        modal: { open: false, row: null, form: {}, saving: false, error: '', duplicate: null, force: false },

        init() {
            this.$nextTick(async () => {
                try {
                    const { createFinderMap } = await import('./places-map');
                    map = createFinderMap(this.$refs.map, {
                        rtl: document.documentElement.dir === 'rtl',
                        labels: cfg.i18n.states,
                        onPick: (lat, lng) => this.setPoint(lat, lng),
                        onSelect: (id) => this.reveal(id),
                    });
                    map.setPicking(this.mode === 'point');
                } catch (e) {
                    this.$refs.map.classList.add('hidden');
                }
            });
            this.$watch('mode', (mode) => {
                map?.setPicking(mode === 'point');
                if (mode !== 'point') map?.clearPoint();
                else if (this.lat !== null) map?.setPoint(this.lat, this.lng, this.radius);
            });
            this.$watch('radius', () => this.lat !== null && map?.setPoint(this.lat, this.lng, this.radius));
            this.$watch('municipalityId', () => (this.areaId = ''));
        },

        get areas() {
            return this.cfg.areas[this.municipalityId] || [];
        },

        quick(label) {
            this.keyword = label;
            this.$refs.keyword?.focus();
        },

        setPoint(lat, lng) {
            this.lat = Math.round(lat * 1e6) / 1e6;
            this.lng = Math.round(lng * 1e6) / 1e6;
            this.error = '';
            map?.setPoint(this.lat, this.lng, this.radius);
        },

        locate() {
            if (!navigator.geolocation) {
                this.error = this.cfg.i18n.locationFailed;
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => this.setPoint(pos.coords.latitude, pos.coords.longitude),
                () => (this.error = this.cfg.i18n.locationFailed),
                { enableHighAccuracy: true, timeout: 10000 },
            );
        },

        params() {
            const p = { keyword: this.keyword.trim(), mode: this.mode };
            if (this.mode === 'point') Object.assign(p, { lat: this.lat, lng: this.lng, radius: Number(this.radius) });
            else Object.assign(p, { municipality_id: this.municipalityId || null, area_id: this.areaId || null });
            return p;
        },

        async search() {
            if (this.searching || this.busy) return;
            this.error = '';
            if (this.keyword.trim().length < 2) {
                this.error = this.cfg.i18n.keywordRequired;
                this.$refs.keyword?.focus();
                return;
            }
            if (this.mode === 'point' && this.lat === null) {
                this.error = this.cfg.i18n.pinRequired;
                return;
            }
            this.searching = true;
            this.lastParams = this.params();
            try {
                const data = await postJson(this.cfg.urls.search, this.lastParams);
                this.query = data.query;
                this.rows = [];
                this.rowState = {};
                this.selected = {};
                this.categoryFor = {};
                this.summary = '';
                this.filter = 'all';
                this.append(data.rows);
                this.next = data.next;
                this.searched = true;
                map?.setResults(this.rows);
                this.$nextTick(() => this.$refs.results?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            } catch (e) {
                this.error = e.message || this.cfg.i18n.network;
            } finally {
                this.searching = false;
            }
        },

        async more() {
            if (!this.next || this.loadingMore) return;
            this.loadingMore = true;
            try {
                const data = await postJson(this.cfg.urls.search, { ...this.lastParams, page_token: this.next });
                const added = this.append(data.rows);
                added.forEach((row) => map?.addResult(row));
                this.next = data.next;
            } catch (e) {
                this.error = e.message || this.cfg.i18n.network;
            } finally {
                this.loadingMore = false;
            }
        },

        append(rows) {
            const known = new Set(this.rows.map((r) => r.place_id));
            const fresh = rows.filter((r) => !known.has(r.place_id));
            fresh.forEach((r) => {
                this.categoryFor[r.place_id] = r.category_id;
                if (r.business_status === 'CLOSED_PERMANENTLY' && r.state === 'new') r.state = 'closed';
            });
            this.rows.push(...fresh);
            return fresh;
        },

        group(row) {
            if (this.rowState[row.place_id]?.status === 'added') return 'listed';
            return { new: 'new', closed: 'new', likely: 'matched', possible: 'matched', linked: 'listed' }[row.state];
        },

        isClosed(row) {
            return row.business_status === 'CLOSED_PERMANENTLY';
        },

        count(group) {
            return this.rows.filter((r) => !(this.hideClosed && this.isClosed(r)) && (group === 'all' || this.group(r) === group)).length;
        },

        get visible() {
            return this.rows.filter((r) => !(this.hideClosed && this.isClosed(r)) && (this.filter === 'all' || this.group(r) === this.filter));
        },

        stateOf(row) {
            return this.rowState[row.place_id]?.status === 'added' ? 'added' : row.state;
        },

        categoryName(id) {
            return this.cfg.categoryNames[id] || '';
        },

        isFallback(row) {
            return Number(this.categoryFor[row.place_id]) === this.cfg.fallbackCategory;
        },

        reasonLabels(row) {
            return (row.reasons || []).filter((r) => this.cfg.i18n.reasons[r]).map((r) => ({ key: r, label: this.cfg.i18n.reasons[r], good: ['name_exact', 'phone_match', 'website_match', 'location_match', 'area_match', 'place_linked'].includes(r) }));
        },

        domain(url) {
            return (url || '').replace(/^https?:\/\/(www\.)?/, '').replace(/\/.*$/, '');
        },

        plural(forms, n) {
            const form = n === 1 ? forms.one : n === 2 ? forms.two : n >= 3 && n <= 10 ? forms.few : forms.many;
            return form.replace(':count', n.toLocaleString());
        },

        reviewsLabel(n) {
            return this.plural(this.cfg.i18n.reviews, n);
        },

        reveal(id) {
            const row = this.rows.find((r) => r.place_id === id);
            if (!row) return;
            if (!this.visible.includes(row)) {
                this.filter = 'all';
                if (this.isClosed(row)) this.hideClosed = false;
            }
            this.highlight = id;
            this.$nextTick(() => document.getElementById(`place-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
            setTimeout(() => this.highlight === id && (this.highlight = null), 2200);
        },

        showOnMap(row) {
            map?.focus(row.place_id);
            this.$refs.map?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        // Selection: only new places can be added in bulk.
        selectable(row) {
            return row.state === 'new' && !this.rowState[row.place_id];
        },
        get selectedIds() {
            return Object.keys(this.selected).filter((id) => this.selected[id]);
        },
        get selectableVisible() {
            return this.visible.filter((r) => this.selectable(r));
        },
        get allNewSelected() {
            const rows = this.selectableVisible;
            return rows.length > 0 && rows.every((r) => this.selected[r.place_id]);
        },
        toggleAllNew(on) {
            this.selectableVisible.forEach((r) => (this.selected[r.place_id] = on));
        },
        clearSelection() {
            this.selected = {};
        },

        // Add one place, after the admin checked it.
        openAdd(row, force = false) {
            this.modal = {
                open: true,
                row,
                force,
                saving: false,
                error: '',
                duplicate: null,
                form: {
                    name_en: row.name,
                    name_ar: '',
                    category_id: this.categoryFor[row.place_id] || '',
                    municipality_id: row.municipality_id || '',
                    area_id: row.area_id || '',
                    phone: row.phone || '',
                    website: row.website || '',
                    address_en: row.address || '',
                    hours: true,
                    publish: this.publish,
                },
            };
        },
        get modalAreas() {
            return this.cfg.areas[this.modal.form.municipality_id] || [];
        },
        closeModal() {
            if (!this.modal.saving) this.modal.open = false;
        },
        async saveAdd(force = this.modal.force) {
            const { row, form } = this.modal;
            this.modal.saving = true;
            this.modal.error = '';
            this.modal.duplicate = null;
            try {
                const payload = { place_id: row.place_id, ...form, force };
                ['category_id', 'municipality_id', 'area_id'].forEach((k) => (payload[k] = payload[k] === '' ? null : Number(payload[k])));
                const data = await postJson(this.cfg.urls.add, payload);
                this.markAdded(row, data.business);
                this.modal.open = false;
                Alpine.store('toast').show(data.message);
            } catch (e) {
                if (e.status === 409 && e.data?.business) this.modal.duplicate = e.data.business;
                else this.modal.error = e.message || this.cfg.i18n.network;
            } finally {
                this.modal.saving = false;
            }
        },
        markAdded(row, business) {
            this.rowState[row.place_id] = { status: 'added', business };
            delete this.selected[row.place_id];
            map?.setState(row.place_id, 'added');
        },

        // Compare a listing with Google: what differs goes to the enrichment review screen.
        async compare(row, businessId) {
            const id = businessId || row.business?.id;
            if (!id) return;
            this.modal.open = false;
            this.rowState[row.place_id] = { status: 'comparing' };
            try {
                const data = await postJson(this.cfg.urls.review, { business_id: id, place_id: row.place_id });
                this.rowState[row.place_id] = data.status === 'review'
                    ? { status: 'review', url: data.url, count: data.count }
                    : { status: 'same', message: data.message };
            } catch (e) {
                this.rowState[row.place_id] = { status: 'error', message: e.message || this.cfg.i18n.network };
            }
        },
        reviewLabel(n) {
            return this.plural(this.cfg.i18n.updates, n);
        },

        // Bulk add with the categories chosen in the list.
        get percent() {
            return this.progress.total ? Math.round((this.progress.done / this.progress.total) * 100) : 0;
        },
        bulkAdd() {
            const count = this.selectedIds.length;
            if (!count || this.busy) return;
            const store = Alpine.store('confirm');
            store.ask(null, {
                title: this.cfg.i18n.confirmTitle,
                message: `${this.cfg.i18n.confirmText.replace(':count', count)} ${this.publish ? this.cfg.i18n.confirmPublish : this.cfg.i18n.confirmDraft}`,
                confirmLabel: this.cfg.i18n.add,
                tone: 'primary',
            });
            store.onAccept = () => this.runBulk();
        },
        async runBulk() {
            let queue = this.selectedIds.slice();
            const tally = { added: 0, duplicates: 0, failed: 0 };
            this.busy = true;
            this.stopping = false;
            this.summary = '';
            this.progress = { done: 0, total: queue.length };
            let retries = 0;
            while (queue.length && !this.stopping) {
                const chunk = queue.slice(0, BULK_CHUNK);
                try {
                    const data = await postJson(this.cfg.urls.addMany, {
                        items: chunk.map((id) => ({ place_id: id, category_id: Number(this.categoryFor[id]) || null })),
                        publish: this.publish,
                    });
                    const results = data.results || [];
                    if (!results.length) {
                        if (++retries > 2) {
                            this.summary = this.cfg.i18n.noProgress;
                            break;
                        }
                        continue;
                    }
                    retries = 0;
                    results.forEach((r) => {
                        const row = this.rows.find((x) => x.place_id === r.place_id);
                        if (r.status === 'added') {
                            tally.added++;
                            if (row) this.markAdded(row, r.business);
                        } else if (r.status === 'duplicate') {
                            tally.duplicates++;
                            if (row) {
                                row.state = 'possible';
                                row.business = r.business;
                                row.reasons = [];
                                map?.setState(row.place_id, 'possible');
                            }
                            delete this.selected[r.place_id];
                        } else {
                            tally.failed++;
                            if (row) this.rowState[row.place_id] = { status: 'error', message: r.message };
                            delete this.selected[r.place_id];
                        }
                    });
                    const done = new Set(results.map((r) => r.place_id));
                    queue = queue.filter((id) => !done.has(id));
                    this.progress.done = this.progress.total - queue.length;
                } catch (e) {
                    if (++retries > 2 || [401, 403, 419, 422].includes(e.status)) {
                        this.summary = e.message || this.cfg.i18n.network;
                        break;
                    }
                    await new Promise((r) => setTimeout(r, 1200 * retries));
                }
            }
            this.busy = false;
            const done = this.cfg.i18n.done.replace(':added', tally.added).replace(':duplicates', tally.duplicates).replace(':failed', tally.failed);
            this.summary = this.summary ? `${done} · ${this.summary}` : done;
        },
    };
});

Alpine.start();
