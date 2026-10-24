import L from 'leaflet';

export const QATAR_BOUNDS = L.latLngBounds([24.45, 50.7], [26.2, 51.7]);

/*
 * Map backgrounds, best first. If a provider is unreachable (blocked network,
 * outage, content blocker) the map switches to the next one instead of
 * staying blank; the businesses are shown whatever the background does.
 * (CARTO is not used: it now answers every tile with the same watermark
 * image, which loads "successfully" and so would never trigger the switch.)
 */
const OSM = '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a>';
export const TILE_PROVIDERS = [
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

/**
 * Adds the first background that works.
 * onReady(index) runs when a provider shows its first tile; onExhausted() when none works.
 */
export function addTiles(map, { onReady, onExhausted } = {}) {
    function useProvider(index) {
        const provider = TILE_PROVIDERS[index];
        if (!provider) {
            onExhausted?.();
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
                onReady?.(index);
            }
        });
        layer.on('tileerror', () => {
            failed++;
            if (failed >= TILE_FAILURES) next();
        });
        layer.addTo(map);
    }
    useProvider(0);
}
