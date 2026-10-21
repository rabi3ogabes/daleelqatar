// Minimal fetch helper with CSRF protection for same-origin requests.
const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

export async function postJson(url, body = {}) {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token ?? '', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw Object.assign(new Error(data.message || 'Request failed'), { status: res.status, data });
    return data;
}

export function beacon(url, body = {}) {
    const fd = new FormData();
    fd.append('_token', token ?? '');
    Object.entries(body).forEach(([k, v]) => fd.append(k, v));
    if (navigator.sendBeacon) navigator.sendBeacon(url, fd);
    else fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true });
}
