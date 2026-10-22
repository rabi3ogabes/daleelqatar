// Minimal fetch helpers with CSRF protection for same-origin requests.
let token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
let refreshing = null;

// Pages served from the CDN cache carry no session (see CacheGuestPages), so a
// fresh token is fetched once before the first form post or JSON request.
function needsToken() {
    return document.documentElement.dataset.edge === '1' || !document.cookie.split('; ').some((c) => c.startsWith('XSRF-TOKEN='));
}

export function ensureToken() {
    if (!needsToken()) return Promise.resolve(token);
    refreshing ??= fetch('/csrf-token', { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then((r) => (r.ok ? r.json() : Promise.reject(r)))
        .then((data) => {
            token = data.token;
            delete document.documentElement.dataset.edge;
            document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
            document.querySelectorAll('input[name="_token"]').forEach((input) => (input.value = token));
            return token;
        })
        .catch(() => token)
        .finally(() => (refreshing = null));
    return refreshing;
}

document.addEventListener(
    'submit',
    (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post' || form.dataset.tokenReady || !needsToken()) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        ensureToken().then(() => {
            form.dataset.tokenReady = '1';
            if (form.requestSubmit) form.requestSubmit(event.submitter && event.submitter.form === form ? event.submitter : undefined);
            else form.submit();
        });
    },
    true,
);

// A readable message when the server answered without one (e.g. a gateway timeout page).
function failure(res, data) {
    const html = document.documentElement.dataset;
    const message = data.message || ([502, 503, 504].includes(res.status) ? html.errorTimeout : html.errorGeneric) || 'Request failed';
    return Object.assign(new Error(message), { status: res.status, data });
}

export async function postJson(url, body = {}) {
    await ensureToken();
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token ?? '', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw failure(res, data);
    return data;
}

export async function postForm(url, formData) {
    await ensureToken();
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token ?? '', 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw failure(res, data);
    return data;
}

// Analytics beacons are CSRF-exempt (they may come from a cached page) and rate-limited server-side.
export function beacon(url, body = {}) {
    const fd = new FormData();
    fd.append('_token', token ?? '');
    Object.entries(body).forEach(([k, v]) => fd.append(k, v));
    if (navigator.sendBeacon) navigator.sendBeacon(url, fd);
    else fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true });
}
