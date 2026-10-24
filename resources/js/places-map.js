import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../css/map.css';
import { QATAR_BOUNDS, addTiles } from './map-tiles';

/*
 * The Google Maps finder's map: a pin and radius to search around, and the
 * results coloured by how they match the directory. Labels are set with
 * textContent — place names come from Google.
 */
export function createFinderMap(el, { rtl = false, onPick, onSelect, labels = {} } = {}) {
    const map = L.map(el, { zoomControl: false, maxBounds: QATAR_BOUNDS.pad(0.6), minZoom: 8 }).fitBounds(QATAR_BOUNDS);
    L.control.zoom({ position: rtl ? 'topleft' : 'topright' }).addTo(map);
    let ready = false;
    addTiles(map, { onReady: () => (ready = true) });
    setTimeout(() => ready || map.invalidateSize(), 600);

    const results = L.featureGroup().addTo(map);
    const markers = new Map();
    let pin = null;
    let circle = null;
    let picking = false;

    map.on('click', (e) => {
        if (picking) onPick?.(e.latlng.lat, e.latlng.lng);
    });

    const icon = (state) => L.divIcon({ className: `dq-finder-pin dq-finder-pin--${state}`, html: '<span></span>', iconSize: [18, 18], iconAnchor: [9, 9], popupAnchor: [0, -10] });

    function popup(row) {
        const box = document.createElement('div');
        box.className = 'dq-popup';
        const state = document.createElement('p');
        state.className = 'dq-popup__cat';
        state.textContent = labels[row.state] || row.state;
        const title = document.createElement('button');
        title.type = 'button';
        title.className = 'dq-popup__title';
        title.textContent = row.name;
        title.addEventListener('click', () => onSelect?.(row.place_id));
        box.append(state, title);
        if (row.address) {
            const address = document.createElement('p');
            address.className = 'dq-popup__loc';
            address.textContent = row.address;
            box.append(address);
        }
        return box;
    }

    return {
        setPicking(on) {
            picking = on;
            el.classList.toggle('dq-picking', on);
        },
        setPoint(lat, lng, radiusKm) {
            const at = [lat, lng];
            if (!pin) {
                pin = L.marker(at, { icon: L.divIcon({ className: 'dq-pin', html: '<span class="dq-pin__dot"></span>', iconSize: [30, 38], iconAnchor: [15, 36] }), keyboard: false }).addTo(map);
                circle = L.circle(at, { radius: radiusKm * 1000, color: '#101c32', weight: 2, dashArray: '6 6', fillColor: '#d7b875', fillOpacity: 0.12 }).addTo(map);
            } else {
                pin.setLatLng(at);
                circle.setLatLng(at).setRadius(radiusKm * 1000);
            }
            map.fitBounds(circle.getBounds(), { padding: [24, 24], maxZoom: 15 });
        },
        clearPoint() {
            pin?.remove();
            circle?.remove();
            pin = circle = null;
        },
        setResults(rows, { fit = true } = {}) {
            results.clearLayers();
            markers.clear();
            rows.forEach((row) => this.addResult(row));
            if (fit && markers.size) map.fitBounds(results.getBounds().pad(0.2), { maxZoom: 15 });
        },
        addResult(row) {
            if (row.lat === null || row.lng === null || markers.has(row.place_id)) return;
            const marker = L.marker([row.lat, row.lng], {
                icon: icon(row.state),
                title: row.name,
                alt: row.name,
            });
            marker.bindPopup(() => popup(row), { maxWidth: 240 });
            marker.on('click', () => onSelect?.(row.place_id));
            marker.addTo(results);
            markers.set(row.place_id, marker);
        },
        setState(placeId, state) {
            markers.get(placeId)?.setIcon(icon(state));
        },
        focus(placeId) {
            const marker = markers.get(placeId);
            if (!marker) return;
            map.setView(marker.getLatLng(), Math.max(map.getZoom(), 16));
            marker.openPopup();
        },
        fitResults() {
            if (markers.size) map.fitBounds(results.getBounds().pad(0.2), { maxZoom: 15 });
        },
        invalidate() {
            map.invalidateSize();
        },
    };
}
