import L from 'leaflet';
import 'leaflet.markercluster';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import '../css/map.css';

/*
 * Interactive map of Qatar with every business that has an exact location.
 * Points come from a cached JSON endpoint; markers are clustered and every
 * label is rendered with textContent (never innerHTML) — names are user data.
 */
const QATAR_BOUNDS = L.latLngBounds([24.45, 50.7], [26.2, 51.7]);

/*
 * Map backgrounds, best first. If a provider is unreachable (blocked network,
 * outage, content blocker) the map switches to the next one instead of
 * staying blank; the businesses are shown whatever the background does.
 * (CARTO is not used: it now answers every tile with the same watermark
 * image, which loads "successfully" and so would never trigger the switch.)
 */
const OSM = '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a>';
const TILE_PROVIDERS = [
    {
        url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        options: { maxZoom: 19, attribution: OSM },
    },
    {
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}',
        options: { maxZoom: 19, maxNativeZoom: 18, attribution: 'Tiles &copy; <a href="https://www.esri.com" rel="noopener">Esri</a>' },
    },
];

/** Tiles that fail before any loads, or no tile at all after this long, mean the provider is unusable. */
const TILE_FAILURES = 4;
const TILE_TIMEOUT_MS = 8000;
const DATA_TIMEOUT_MS = 25000;

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function initMap(root) {
    const cfg = JSON.parse(root.dataset.config);
    const canvas = root.querySelector('[data-map-canvas]');
    const search = root.querySelector('[data-map-search]');
    const category = root.querySelector('[data-map-category]');
    const count = root.querySelector('[data-map-count]');
    const locate = root.querySelector('[data-map-locate]');
    const reset = root.querySelector('[data-map-reset]');
    const loading = root.querySelector('[data-map-loading]');
    const errorBox = root.querySelector('[data-map-error]');
    const retry = root.querySelector('[data-map-retry]');
    const notice = root.querySelector('[data-map-notice]');
    const rtl = document.documentElement.dir === 'rtl';

    const map = L.map(canvas, {
        zoomControl: false,
        attributionControl: true,
        maxBounds: QATAR_BOUNDS.pad(0.6),
        minZoom: 8,
        worldCopyJump: false,
    }).fitBounds(QATAR_BOUNDS);
    L.control.zoom({ position: rtl ? 'topleft' : 'topright' }).addTo(map);

    function showNotice(text) {
        if (!notice) return;
        notice.textContent = text;
        notice.hidden = !text;
    }

    let backgroundReady = false;
    function useProvider(index) {
        const provider = TILE_PROVIDERS[index];
        if (!provider) {
            showNotice(cfg.labels.no_tiles);
            return;
        }
        const layer = L.tileLayer(provider.url, provider.options);
        let loaded = 0;
        let failed = 0;
        let switched = false;
        const next = () => {
            if (switched || loaded > 0) return;
            switched = true;
            clearTimeout(timer);
            map.removeLayer(layer);
            useProvider(index + 1);
        };
        const timer = setTimeout(next, TILE_TIMEOUT_MS);
        layer.on('tileload', () => {
            loaded++;
            if (loaded === 1) {
                clearTimeout(timer);
                backgroundReady = true;
                showNotice(index > 0 ? cfg.labels.backup : '');
                if (index > 0) setTimeout(() => showNotice(''), 4000);
            }
        });
        layer.on('tileerror', () => {
            failed++;
            if (failed >= TILE_FAILURES) next();
        });
        layer.addTo(map);
    }
    useProvider(0);
    // Leaflet measures its box once; if the page was still laying out, measure again.
    setTimeout(() => backgroundReady || map.invalidateSize(), 600);

    const pin = L.divIcon({ className: 'dq-pin', html: '<span class="dq-pin__dot"></span>', iconSize: [30, 38], iconAnchor: [15, 36], popupAnchor: [0, -32] });
    const clusters = L.markerClusterGroup({
        showCoverageOnHover: false,
        spiderfyOnMaxZoom: true,
        maxClusterRadius: 48,
        chunkedLoading: true,
        iconCreateFunction(cluster) {
            const n = cluster.getChildCount();
            const size = n < 25 ? 'sm' : n < 200 ? 'md' : 'lg';
            return L.divIcon({ className: `dq-cluster dq-cluster--${size}`, html: `<span>${n > 999 ? Math.round(n / 100) / 10 + 'k' : n}</span>`, iconSize: [44, 44] });
        },
    });
    map.addLayer(clusters);

    let points = [];
    const categories = cfg.categories;

    function popupFor(p) {
        const box = el('div', 'dq-popup');
        const cat = categories[p[5]];
        if (cat) box.appendChild(el('p', 'dq-popup__cat', cat.name));
        const title = el('a', 'dq-popup__title', p[3]);
        title.href = cfg.profileBase.replace('__SLUG__', encodeURIComponent(p[4]));
        box.appendChild(title);
        if (p[6]) box.appendChild(el('p', 'dq-popup__loc', p[6]));
        const cta = el('a', 'dq-popup__cta', cfg.labels.view);
        cta.href = title.href;
        box.appendChild(cta);
        return box;
    }

    function render() {
        const q = (search.value || '').trim().toLowerCase();
        const c = category.value;
        const matches = points.filter((p) => (!c || String(categories[p[5]]?.root) === c) && (!q || p[3].toLowerCase().includes(q)));
        clusters.clearLayers();
        clusters.addLayers(matches.map((p) => L.marker([p[1], p[2]], { icon: pin, title: p[3], alt: p[3] }).bindPopup(() => popupFor(p), { maxWidth: 260 })));
        count.textContent = cfg.labels.showing.replace(':count', matches.length.toLocaleString());
        if (q && matches.length && matches.length <= 200) {
            map.fitBounds(L.latLngBounds(matches.map((p) => [p[1], p[2]])).pad(0.3), { maxZoom: 16 });
        }
    }

    let timer;
    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(render, 200);
    });
    category.addEventListener('change', render);
    reset.addEventListener('click', () => {
        search.value = '';
        category.value = '';
        render();
        map.fitBounds(QATAR_BOUNDS);
    });
    locate.addEventListener('click', () => {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition((pos) => {
            const here = [pos.coords.latitude, pos.coords.longitude];
            map.setView(here, 15);
            L.circleMarker(here, { radius: 8, color: '#101C32', weight: 3, fillColor: '#D7B875', fillOpacity: 1 }).addTo(map);
        });
    });

    function load() {
        if (errorBox) errorBox.hidden = true;
        if (loading) loading.hidden = false;
        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        const timeout = setTimeout(() => controller?.abort(), DATA_TIMEOUT_MS);
        fetch(cfg.dataUrl, { headers: { Accept: 'application/json' }, signal: controller?.signal })
            .then((r) => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then((data) => {
                if (!Array.isArray(data.points)) throw new Error('Unexpected map data');
                points = data.points;
                Object.assign(categories, data.categories || {});
                render();
            })
            .catch(() => {
                if (errorBox) errorBox.hidden = false;
            })
            .finally(() => {
                clearTimeout(timeout);
                if (loading) loading.hidden = true;
            });
    }
    retry?.addEventListener('click', load);
    load();
}

document.querySelectorAll('[data-dq-map]').forEach(initMap);
