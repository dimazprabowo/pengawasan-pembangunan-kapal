import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const INDONESIA_CENTER = [-2.5, 118];
const INDONESIA_ZOOM = 5;

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
})[c]);

const round7 = (value) => Math.round(Number(value) * 1e7) / 1e7;

const isDark = () => document.documentElement.classList.contains('dark');

/**
 * Base layer "peta" mengikuti tema (terang/gelap, auto saat tema diganti)
 * + "satelit". Kembalikan controller dengan setMode() & destroy().
 */
function createBaseLayers(map) {
    const layers = {
        peta: L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }),
        satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri',
            maxZoom: 19,
        }),
    };
    let mode = 'peta';
    let active = null;

    const apply = () => {
        const next = mode === 'satellite' ? layers.satellite : layers.peta;
        if (next !== active) {
            if (active) map.removeLayer(active);
            next.addTo(map);
            active = next;
        }
        next.getContainer().style.filter = next === layers.peta && isDark()
            ? 'invert(1) hue-rotate(180deg) brightness(0.85) contrast(0.9)'
            : '';
    };

    const observer = new MutationObserver(apply);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    apply();

    return {
        setMode(value) {
            mode = value;
            apply();
        },
        destroy() {
            observer.disconnect();
        },
    };
}

/**
 * Peta sebaran galangan di dashboard.
 * markers: [{ kode, nama, kota, provinsi, lat, lng, kapal, nc }]
 */
window.galanganMap = function ({ markers = [], selected = null } = {}) {
    let map = null;
    let base = null;
    const layers = {};

    return {
        markers,
        selected,
        search: '',
        mode: 'peta',
        fullscreen: false,

        get filtered() {
            const q = this.search.trim().toLowerCase();
            return this.markers
                .filter((m) => !q || [m.nama, m.kode, m.kota, m.provinsi].some((v) => (v ?? '').toLowerCase().includes(q)))
                .sort((a, b) => b.kapal - a.kapal || a.nama.localeCompare(b.nama));
        },

        get located() {
            return this.markers.filter((m) => m.lat !== null && m.lng !== null);
        },

        init() {
            map = L.map(this.$refs.map, { zoomControl: false, worldCopyJump: true })
                .setView(INDONESIA_CENTER, INDONESIA_ZOOM);
            L.control.zoom({ position: 'bottomright' }).addTo(map);
            L.control.scale({ position: 'bottomleft', imperial: false }).addTo(map);
            base = createBaseLayers(map);

            this.located.forEach((m) => {
                layers[m.kode] = L.marker([m.lat, m.lng], { icon: this.icon(m), riseOnHover: true })
                    .bindTooltip(
                        `<strong>${escapeHtml(m.nama)}</strong><br>${m.kapal} kapal${m.nc ? ` &middot; <span style="color:#dc2626">${m.nc} NC open</span>` : ''}`,
                        { direction: 'top', offset: [0, -20] },
                    )
                    .on('click', () => this.select(m.kode))
                    .addTo(map);
            });

            this.$watch('selected', () => this.refreshIcons());
            this.$watch('mode', (value) => base.setMode(value));

            const current = this.markers.find((m) => m.kode === this.selected);
            current && current.lat !== null ? this.focus(current, false) : this.fitAll();
        },

        icon(m) {
            const state = [
                'gm-marker',
                m.kapal > 0 ? 'gm-marker--active' : 'gm-marker--empty',
                m.kode === this.selected ? 'gm-marker--selected' : '',
            ].join(' ');

            return L.divIcon({
                className: '',
                html: `<div class="${state}">${m.nc > 0 ? '<span class="gm-marker__pulse"></span>' : ''}<span class="gm-marker__count">${Number(m.kapal)}</span></div>`,
                iconSize: [40, 40],
                iconAnchor: [20, 20],
            });
        },

        refreshIcons() {
            this.located.forEach((m) => {
                layers[m.kode]?.setIcon(this.icon(m));
                layers[m.kode]?.setZIndexOffset(m.kode === this.selected ? 1000 : 0);
            });
        },

        select(kode) {
            const m = this.markers.find((item) => item.kode === kode);
            if (!m) return;
            this.selected = kode;
            if (m.lat !== null) this.focus(m);
            this.$wire.selectGalangan(kode);
        },

        clear() {
            this.selected = null;
            this.fitAll();
            this.$wire.selectGalangan(null);
        },

        focus(m, animate = true) {
            const target = [m.lat, m.lng];
            const zoom = Math.max(map.getZoom(), 11);
            animate ? map.flyTo(target, zoom, { duration: 0.8 }) : map.setView(target, zoom);
        },

        fitAll() {
            if (!this.located.length) {
                map.setView(INDONESIA_CENTER, INDONESIA_ZOOM);
                return;
            }
            map.fitBounds(L.latLngBounds(this.located.map((m) => [m.lat, m.lng])), { padding: [48, 48], maxZoom: 11 });
        },

        toggleFullscreen() {
            this.fullscreen = !this.fullscreen;
            document.body.style.overflow = this.fullscreen ? 'hidden' : '';
            this.$nextTick(() => map.invalidateSize());
        },

        destroy() {
            document.body.style.overflow = '';
            base?.destroy();
            map?.remove();
        },
    };
};

/**
 * Pemilih lokasi galangan (modal master data): klik/drag pin, cari alamat
 * via Nominatim OSM, atau gunakan lokasi perangkat. lat/lng di-entangle.
 */
window.galanganLocationPicker = function ({ lat, lng }) {
    let map = null;
    let base = null;
    let marker = null;

    return {
        lat,
        lng,
        query: '',
        results: [],
        searching: false,
        locating: false,
        message: '',
        dropUp: false,

        get hasPoint() {
            return this.lat !== null && this.lat !== '' && this.lng !== null && this.lng !== '';
        },

        init() {
            map = L.map(this.$refs.map, { zoomControl: true }).setView(INDONESIA_CENTER, INDONESIA_ZOOM);
            base = createBaseLayers(map);
            map.on('click', (e) => this.setPoint(e.latlng.lat, e.latlng.lng));

            this.$watch('lat', () => this.syncMarker());
            this.$watch('lng', () => this.syncMarker());
            this.syncMarker(true);
            setTimeout(() => map?.invalidateSize(), 250);
        },

        syncMarker(center = false) {
            if (!this.hasPoint) {
                marker?.remove();
                marker = null;
                return;
            }
            const point = [Number(this.lat), Number(this.lng)];
            if (Number.isNaN(point[0]) || Number.isNaN(point[1])) return;
            if (!marker) {
                marker = L.marker(point, {
                    draggable: true,
                    icon: L.divIcon({ className: '', html: '<div class="gm-pin"></div>', iconSize: [28, 28], iconAnchor: [14, 28] }),
                }).addTo(map);
                marker.on('dragend', () => {
                    const p = marker.getLatLng();
                    this.setPoint(p.lat, p.lng);
                });
            } else {
                marker.setLatLng(point);
            }
            if (center) map.setView(point, 14);
        },

        setPoint(latValue, lngValue) {
            this.lat = round7(latValue);
            this.lng = round7(lngValue);
        },

        setMode(value) {
            base.setMode(value);
        },

        async search() {
            const q = this.query.trim();
            if (q.length < 3) {
                this.message = 'Ketik minimal 3 karakter.';
                return;
            }
            this.searching = true;
            this.message = '';
            try {
                const params = new URLSearchParams({ format: 'jsonv2', q, countrycodes: 'id', limit: '6', addressdetails: '1' });
                const response = await fetch(`https://nominatim.openstreetmap.org/search?${params}`, {
                    headers: { 'Accept-Language': 'id' },
                });
                if (!response.ok) throw new Error(response.statusText);
                const data = await response.json();
                this.results = data.map((r) => ({
                    label: r.display_name,
                    lat: Number(r.lat),
                    lng: Number(r.lon),
                    kota: r.address?.city || r.address?.town || r.address?.regency || r.address?.county || r.address?.municipality || '',
                    provinsi: r.address?.state || '',
                }));
                if (!this.results.length) this.message = 'Lokasi tidak ditemukan. Coba kata kunci lain.';
                this.$nextTick(() => this.checkPosition());
            } catch (e) {
                this.message = 'Pencarian gagal. Periksa koneksi lalu coba lagi.';
            } finally {
                this.searching = false;
            }
        },

        checkPosition() {
            const rect = this.$refs.searchBox.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            this.dropUp = spaceBelow < 260 && rect.top > spaceBelow;
        },

        pick(result) {
            this.setPoint(result.lat, result.lng);
            map.setView([result.lat, result.lng], 15);
            // Hanya mengisi field alamat yang masih kosong — input user tidak ditimpa.
            if (!this.$wire.alamat) this.$wire.alamat = result.label;
            if (!this.$wire.kota && result.kota) this.$wire.kota = result.kota;
            if (!this.$wire.provinsi && result.provinsi) this.$wire.provinsi = result.provinsi;
            this.results = [];
            this.query = '';
        },

        locate() {
            if (!navigator.geolocation) {
                this.message = 'Perangkat tidak mendukung geolokasi.';
                return;
            }
            this.locating = true;
            this.message = '';
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.setPoint(pos.coords.latitude, pos.coords.longitude);
                    map.setView([pos.coords.latitude, pos.coords.longitude], 15);
                    this.locating = false;
                },
                () => {
                    this.message = 'Izin lokasi ditolak atau tidak tersedia.';
                    this.locating = false;
                },
                { enableHighAccuracy: true, timeout: 10000 },
            );
        },

        clear() {
            this.lat = null;
            this.lng = null;
            map.setView(INDONESIA_CENTER, INDONESIA_ZOOM);
        },

        destroy() {
            base?.destroy();
            map?.remove();
        },
    };
};
